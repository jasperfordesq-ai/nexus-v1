<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Events\UserRegistered;
use App\I18n\LocaleContext;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies all admins, brokers, and coordinators when a new user registers.
 */
class NotifyAdminOfNewRegistration implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        try {
            if (!TenantContext::setById($event->tenantId)) {
                throw new \RuntimeException("Tenant {$event->tenantId} not found — cannot send admin registration notification.");
            }

            $user = $event->user;
            $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
            $baseUrl    = TenantContext::getFrontendUrl();
            $basePath   = TenantContext::getSlugPrefix();
            // Recipients include broker/coordinator roles (line 43) who can't
            // hit /admin/* routes — they're redirected to /dashboard. Use the
            // user-facing /profile/{id} route which works for everyone.
            $profileUrl = $baseUrl . $basePath . '/profile/' . $user->id;

            $admins = DB::table('users')
                ->where('tenant_id', $event->tenantId)
                ->whereIn('role', ['super_admin', 'admin', 'tenant_admin', 'broker', 'coordinator'])
                ->where('status', 'active')
                ->select(['id', 'email', 'first_name', 'name', 'preferred_language'])
                ->get();

            if ($admins->isEmpty()) {
                Log::info('NotifyAdminOfNewRegistration: no active admins found for tenant', ['tenant_id' => $event->tenantId]);
                return;
            }

            $mailer = Mailer::forCurrentTenant();

            foreach ($admins as $admin) {
                $adminEmail = $admin->email ?? null;
                if (!$adminEmail) {
                    continue;
                }

                try {
                    LocaleContext::withLocale($admin, function () use ($admin, $user, $profileUrl, $tenantName, $adminEmail, $mailer) {
                        $adminName = $admin->first_name ?? $admin->name ?? 'Admin';

                        $bellContent = __('emails_misc.admin_notify.new_user_bell');
                        // Bell goes to broker/coordinator recipients too (line 43).
                        // Use the broker panel members list which all admin-tier
                        // and broker-tier roles can access.
                        Notification::createNotification((int) $admin->id, $bellContent, '/broker/members', 'new_user_registered');

                        $subject = __('emails_misc.admin_notify.new_user_subject', ['community' => $tenantName]);

                        $html = EmailTemplateBuilder::make()
                            ->theme('info')
                            ->title(__('emails_misc.admin_notify.new_user_title'))
                            ->previewText(__('emails_misc.admin_notify.new_user_preview', ['community' => $tenantName]))
                            ->greeting($adminName)
                            ->paragraph(__('emails_misc.admin_notify.new_user_body', ['community' => htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8')]))
                            ->button(__('emails_misc.admin_notify.new_user_cta'), $profileUrl)
                            ->render();

                        if (!$mailer->send($adminEmail, $subject, $html)) {
                            Log::warning('NotifyAdminOfNewRegistration: email send failed', ['admin_id' => $admin->id, 'email' => $adminEmail]);
                        }
                    });
                } catch (\Throwable $e) {
                    Log::error('NotifyAdminOfNewRegistration: failed for admin', [
                        'admin_id'  => $admin->id,
                        'user_id'   => $user->id,
                        'tenant_id' => $event->tenantId,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            TenantContext::reset();
        }
    }
}
