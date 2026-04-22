<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Events\VolunteerOpportunityCreated;
use App\I18n\LocaleContext;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies all admins, brokers, and coordinators when a new volunteer opportunity is posted.
 */
class NotifyAdminOfNewVolunteerOpportunity implements ShouldQueue
{
    public function handle(VolunteerOpportunityCreated $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            $opportunity = $event->opportunity;
            $tenantName  = TenantContext::get()['name'] ?? 'Project NEXUS';
            $baseUrl     = TenantContext::getFrontendUrl();
            $basePath    = TenantContext::getSlugPrefix();
            $oppUrl      = $baseUrl . $basePath . '/volunteering/' . $opportunity->id;

            $oppTitle = $opportunity->title ?? 'Untitled opportunity';

            // Load the poster's name
            $posterName = 'A member';
            if (!empty($opportunity->user_id)) {
                $poster = DB::table('users')
                    ->where('id', $opportunity->user_id)
                    ->where('tenant_id', $event->tenantId)
                    ->select(['first_name', 'last_name', 'name'])
                    ->first();
                if ($poster) {
                    $posterName = trim(($poster->first_name ?? '') . ' ' . ($poster->last_name ?? ''))
                        ?: ($poster->name ?? __('emails.common.fallback_member_name'));
                }
            }

            $admins = DB::table('users')
                ->where('tenant_id', $event->tenantId)
                ->whereIn('role', ['super_admin', 'admin', 'tenant_admin', 'broker', 'coordinator'])
                ->where('status', 'active')
                ->select(['id', 'email', 'first_name', 'name', 'preferred_language'])
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $mailer = Mailer::forCurrentTenant();

            foreach ($admins as $admin) {
                $adminEmail = $admin->email ?? null;
                if (!$adminEmail) {
                    continue;
                }

                LocaleContext::withLocale($admin, function () use ($admin, $opportunity, $oppTitle, $oppUrl, $posterName, $tenantName, $adminEmail, $mailer) {
                    $adminName = $admin->first_name ?? $admin->name ?? 'Admin';

                    $bellContent = __('emails_misc.admin_notify.new_vol_opp_bell', ['title' => $oppTitle]);
                    Notification::createNotification((int) $admin->id, $bellContent, '/volunteering/' . $opportunity->id, 'new_vol_opp_created');

                    $subject = __('emails_misc.admin_notify.new_vol_opp_subject', ['community' => $tenantName]);

                    $html = EmailTemplateBuilder::make()
                        ->theme('info')
                        ->title(__('emails_misc.admin_notify.new_vol_opp_title'))
                        ->previewText(__('emails_misc.admin_notify.new_vol_opp_preview', ['community' => $tenantName]))
                        ->greeting($adminName)
                        ->paragraph(__('emails_misc.admin_notify.new_vol_opp_body', ['community' => htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8')]))
                        ->highlight(htmlspecialchars($oppTitle, ENT_QUOTES, 'UTF-8'))
                        ->bulletList([
                            __('emails_misc.admin_notify.new_vol_opp_by_label') . ': ' . htmlspecialchars($posterName, ENT_QUOTES, 'UTF-8'),
                        ])
                        ->button(__('emails_misc.admin_notify.new_vol_opp_cta'), $oppUrl)
                        ->render();

                    if (!$mailer->send($adminEmail, $subject, $html)) {
                        Log::warning('NotifyAdminOfNewVolunteerOpportunity: email send failed', ['admin_id' => $admin->id, 'email' => $adminEmail]);
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::error('NotifyAdminOfNewVolunteerOpportunity listener failed', [
                'opportunity_id' => $event->opportunity->id ?? null,
                'tenant_id'      => $event->tenantId,
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ]);
        } finally {
            TenantContext::reset(); // Prevent context leaking to next queued job
        }
    }
}
