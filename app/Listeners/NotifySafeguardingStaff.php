<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Events\SafeguardingFlaggedEvent;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies all admin and broker users when a member self-identifies
 * safeguarding needs during onboarding.
 *
 * Sends both in-app notifications and emails so that safeguarding flags
 * are never missed. This is queued for performance but runs quickly.
 */
class NotifySafeguardingStaff implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    public function handle(SafeguardingFlaggedEvent $event): void
    {
        try {
            $tenantId = $event->tenantId;
            $flaggedUserId = $event->userId;

            // Set tenant context for queued job — required for HasTenantScope
            // and any service that reads TenantContext::getId()
            \App\Core\TenantContext::setById($tenantId);

            // Load the flagged member's name
            $flaggedUser = User::find($flaggedUserId);
            $memberName = $flaggedUser
                ? trim(($flaggedUser->first_name ?? '') . ' ' . ($flaggedUser->last_name ?? '')) ?: ($flaggedUser->name ?? 'Unknown member')
                : 'Unknown member';

            // Build a summary of what was selected
            $selectedOptions = DB::select(
                "SELECT tso.label FROM user_safeguarding_preferences usp
                 JOIN tenant_safeguarding_options tso ON tso.id = usp.option_id
                 WHERE usp.user_id = ? AND usp.tenant_id = ? AND usp.revoked_at IS NULL AND tso.is_active = 1",
                [$flaggedUserId, $tenantId]
            );
            $optionLabels = array_map(fn ($row) => $row->label, $selectedOptions);
            $optionSummary = !empty($optionLabels)
                ? implode(', ', $optionLabels)
                : 'safeguarding support options';

            // Find all admin, tenant_admin, broker, and super_admin users for this tenant
            $staffUsers = DB::select(
                "SELECT id, email, first_name, name, role FROM users
                 WHERE tenant_id = ? AND role IN ('admin', 'tenant_admin', 'broker', 'super_admin') AND status = 'active'",
                [$tenantId]
            );

            if (empty($staffUsers)) {
                Log::warning('NotifySafeguardingStaff: no admin/broker users found for tenant', [
                    'tenant_id' => $tenantId,
                    'flagged_user_id' => $flaggedUserId,
                ]);
                return;
            }

            $notificationMessage = "Safeguarding flag: {$memberName} indicated support needs during onboarding — {$optionSummary}";
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

                // Email notification
                if (!empty($staff->email)) {
                    $this->sendEmail($staff, $memberName, $optionLabels, $flaggedUserId, $tenantId);
                }
            }

            Log::info('NotifySafeguardingStaff: notified staff', [
                'tenant_id' => $tenantId,
                'flagged_user_id' => $flaggedUserId,
                'staff_notified' => count($staffUsers),
                'options_selected' => $optionLabels,
            ]);
        } catch (\Throwable $e) {
            Log::error('NotifySafeguardingStaff: failed to notify staff', [
                'user_id' => $event->userId ?? null,
                'tenant_id' => $event->tenantId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendEmail(object $staff, string $memberName, array $optionLabels, int $flaggedUserId, int $tenantId): void
    {
        try {
            $staffName = $staff->first_name ?? $staff->name ?? 'Team member';
            $optionList = implode("\n  - ", $optionLabels);

            /** @var EmailService $emailService */
            $emailService = app(EmailService::class);

            $emailService->send(
                $staff->email,
                "[NEXUS] Safeguarding flag — {$memberName}",
                "Hi {$staffName},\n\n"
                . "A member has indicated safeguarding support needs during onboarding.\n\n"
                . "Member: {$memberName}\n"
                . "Selected options:\n  - {$optionList}\n\n"
                . "Please review this in the admin panel and take appropriate action.\n"
                . "This may include assigning a guardian, adjusting messaging permissions, or contacting the member directly.\n\n"
                . "This is an automated notification from the platform safeguarding system.\n"
                . "All access to safeguarding data is audit-logged.\n"
            );
        } catch (\Throwable $e) {
            Log::error('NotifySafeguardingStaff: failed to send email', [
                'staff_id' => $staff->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
