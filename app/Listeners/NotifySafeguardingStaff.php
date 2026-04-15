<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Events\SafeguardingFlaggedEvent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies all admin and broker users when a member self-identifies
 * safeguarding needs during onboarding.
 *
 * Sends both in-app notifications and HTML emails so that safeguarding
 * flags are never missed. This is queued for performance but runs quickly.
 */
class NotifySafeguardingStaff implements ShouldQueue
{
    /**
     * Retry up to 3 times with exponential backoff.
     * Safeguarding notifications are legally critical — must not be silently lost.
     */
    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct()
    {
        //
    }

    public function handle(SafeguardingFlaggedEvent $event): void
    {
        try {
            $tenantId      = $event->tenantId;
            $flaggedUserId = $event->userId;

            // Set tenant context for queued job
            TenantContext::setById($tenantId);

            // Load the flagged member's name
            $flaggedUser = User::find($flaggedUserId);
            $memberName  = $flaggedUser
                ? trim(($flaggedUser->first_name ?? '') . ' ' . ($flaggedUser->last_name ?? '')) ?: ($flaggedUser->name ?? 'Unknown member')
                : 'Unknown member';

            // Build a summary of what was selected
            $selectedOptions = DB::select(
                "SELECT tso.label FROM user_safeguarding_preferences usp
                 JOIN tenant_safeguarding_options tso ON tso.id = usp.option_id
                 WHERE usp.user_id = ? AND usp.tenant_id = ? AND usp.revoked_at IS NULL AND tso.is_active = 1",
                [$flaggedUserId, $tenantId]
            );
            $optionLabels  = array_map(fn ($row) => $row->label, $selectedOptions);
            $optionSummary = !empty($optionLabels)
                ? implode(', ', $optionLabels)
                : __('emails_misc.safeguarding.onboarding_flag_options_label');

            // Find all admin, tenant_admin, broker, and super_admin users for this tenant
            $staffUsers = DB::select(
                "SELECT id, email, first_name, name, role FROM users
                 WHERE tenant_id = ? AND role IN ('admin', 'tenant_admin', 'broker', 'super_admin') AND status = 'active'",
                [$tenantId]
            );

            if (empty($staffUsers)) {
                Log::warning('NotifySafeguardingStaff: no admin/broker users found for tenant', [
                    'tenant_id'       => $tenantId,
                    'flagged_user_id' => $flaggedUserId,
                ]);
                return;
            }

            $notificationMessage = __('emails_misc.safeguarding.onboarding_flag_bell', [
                'name'    => $memberName,
                'options' => $optionSummary,
            ]);
            $adminLink = "/admin/safeguarding?user={$flaggedUserId}";

            foreach ($staffUsers as $staff) {
                // In-app notification
                Notification::create([
                    'tenant_id'  => $tenantId,
                    'user_id'    => $staff->id,
                    'type'       => 'safeguarding_flag',
                    'message'    => $notificationMessage,
                    'link'       => $adminLink,
                    'is_read'    => false,
                    'created_at' => now(),
                ]);

                // HTML email notification
                if (!empty($staff->email)) {
                    $this->sendEmail($staff, $memberName, $optionLabels, $flaggedUserId, $tenantId, $adminLink);
                }
            }

            Log::info('NotifySafeguardingStaff: notified staff', [
                'tenant_id'        => $tenantId,
                'flagged_user_id'  => $flaggedUserId,
                'staff_notified'   => count($staffUsers),
                'options_selected' => $optionLabels,
            ]);
        } catch (\Throwable $e) {
            Log::error('NotifySafeguardingStaff: failed to notify staff', [
                'user_id'   => $event->userId ?? null,
                'tenant_id' => $event->tenantId ?? null,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            // Re-throw so Laravel queue retries (safeguarding notifications are legally critical)
            throw $e;
        }
    }

    /**
     * Handle a listener failure — log for monitoring.
     */
    public function failed(SafeguardingFlaggedEvent $event, \Throwable $exception): void
    {
        Log::critical('NotifySafeguardingStaff: PERMANENTLY FAILED after all retries', [
            'user_id'   => $event->userId,
            'tenant_id' => $event->tenantId,
            'error'     => $exception->getMessage(),
        ]);
    }

    private function sendEmail(
        object $staff,
        string $memberName,
        array  $optionLabels,
        int    $flaggedUserId,
        int    $tenantId,
        string $adminLink
    ): void {
        $staffName  = $staff->first_name ?? $staff->name ?? 'Team member';
        $adminUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $adminLink;

        $bulletItems = !empty($optionLabels)
            ? $optionLabels
            : [__('emails_misc.safeguarding.onboarding_flag_options_label')];

        $html = EmailTemplateBuilder::make()
            ->theme('warning')
            ->title(__('emails_misc.safeguarding.onboarding_flag_title'))
            ->previewText(__('emails_misc.safeguarding.onboarding_flag_preview', ['name' => $memberName]))
            ->greeting($staffName)
            ->paragraph(__('emails_misc.safeguarding.onboarding_flag_body'))
            ->highlight(htmlspecialchars($memberName, ENT_QUOTES, 'UTF-8'), '🚨')
            ->bulletList($bulletItems)
            ->paragraph(__('emails_misc.safeguarding.onboarding_flag_review'))
            ->paragraph('<em>' . __('emails_misc.safeguarding.onboarding_flag_audit_note') . '</em>')
            ->button(__('emails_misc.safeguarding.onboarding_flag_cta'), $adminUrl)
            ->render();

        $subject = __('emails_misc.safeguarding.onboarding_flag_subject', ['name' => $memberName]);
        $mailer  = Mailer::forCurrentTenant();
        $sent    = $mailer->send($staff->email, $subject, $html);

        if (!$sent) {
            Log::critical('NotifySafeguardingStaff: email send returned false — safeguarding notification not delivered', [
                'staff_id'        => $staff->id,
                'staff_email'     => $staff->email,
                'flagged_user_id' => $flaggedUserId,
                'tenant_id'       => $tenantId,
            ]);
            throw new \RuntimeException(
                "Safeguarding email failed to send to staff {$staff->id} ({$staff->email}) — queue will retry"
            );
        }
    }
}
