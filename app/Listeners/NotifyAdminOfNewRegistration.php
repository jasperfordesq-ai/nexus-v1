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
            $profileUrl = $baseUrl . $basePath . '/admin/members/' . $user->id;

            $newUserName  = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                ?: ($user->name ?? 'Unknown');
            $newUserEmail = $user->email ?? '';

            $admins = DB::table('users')
                ->where('tenant_id', $event->tenantId)
                ->whereIn('role', ['super_admin', 'admin', 'tenant_admin', 'broker', 'coordinator'])
                ->where('status', 'active')
                ->select(['id', 'email', 'first_name', 'name'])
                ->get();

            if ($admins->isEmpty()) {
                Log::info('NotifyAdminOfNewRegistration: no active admins found for tenant', ['tenant_id' => $event->tenantId]);
                return;
            }

            $subject = __('emails_misc.admin_notify.new_user_subject', ['community' => $tenantName]);
            $mailer  = Mailer::forCurrentTenant();

            foreach ($admins as $admin) {
                $adminEmail = $admin->email ?? null;
                if (!$adminEmail) {
                    continue;
                }

                $adminName = $admin->first_name ?? $admin->name ?? 'Admin';

                try {
                    // In-app bell notification
                    $bellContent = __('emails_misc.admin_notify.new_user_bell', ['name' => $newUserName]);
                    Notification::createNotification((int) $admin->id, $bellContent, '/admin/members', 'new_user_registered');

                    // Email
                    $html = EmailTemplateBuilder::make()
                        ->theme('info')
                        ->title(__('emails_misc.admin_notify.new_user_title'))
                        ->previewText(__('emails_misc.admin_notify.new_user_preview', ['community' => $tenantName]))
                        ->greeting($adminName)
                        ->paragraph(__('emails_misc.admin_notify.new_user_body', ['community' => htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8')]))
                        ->highlight(htmlspecialchars($newUserName, ENT_QUOTES, 'UTF-8'))
                        ->bulletList(array_filter([
                            __('emails_misc.admin_notify.new_user_email_label') . ': ' . htmlspecialchars($newUserEmail, ENT_QUOTES, 'UTF-8'),
                            __('emails_misc.admin_notify.new_user_status_label') . ': ' . ucfirst($user->status ?? 'pending'),
                        ]))
                        ->button(__('emails_misc.admin_notify.new_user_cta'), $profileUrl)
                        ->render();

                    if (!$mailer->send($adminEmail, $subject, $html)) {
                        Log::warning('NotifyAdminOfNewRegistration: email send failed', ['admin_id' => $admin->id, 'email' => $adminEmail]);
                    }
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
