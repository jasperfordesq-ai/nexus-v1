<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Events\GroupCreated;
use App\I18n\LocaleContext;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies all admins, brokers, and coordinators when a new group is created.
 */
class NotifyAdminOfNewGroup implements ShouldQueue
{
    public function handle(GroupCreated $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            $group      = $event->group;
            $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
            $baseUrl    = TenantContext::getFrontendUrl();
            $basePath   = TenantContext::getSlugPrefix();
            $groupUrl   = $baseUrl . $basePath . '/groups/' . $group->id;

            $groupName = $group->name ?? 'Untitled group';

            // Load the group owner's name
            $creatorName = 'A member';
            if (!empty($group->owner_id)) {
                $creator = DB::table('users')
                    ->where('id', $group->owner_id)
                    ->where('tenant_id', $event->tenantId)
                    ->select(['first_name', 'last_name', 'name'])
                    ->first();
                if ($creator) {
                    $creatorName = trim(($creator->first_name ?? '') . ' ' . ($creator->last_name ?? ''))
                        ?: ($creator->name ?? __('emails.common.fallback_member_name'));
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

                LocaleContext::withLocale($admin, function () use ($admin, $group, $groupName, $groupUrl, $creatorName, $tenantName, $adminEmail, $mailer) {
                    $adminName = $admin->first_name ?? $admin->name ?? 'Admin';

                    $bellContent = __('emails_misc.admin_notify.new_group_bell', ['name' => $groupName]);
                    Notification::createNotification((int) $admin->id, $bellContent, '/groups/' . $group->id, 'new_group_created');

                    $subject = __('emails_misc.admin_notify.new_group_subject', ['community' => $tenantName]);

                    $html = EmailTemplateBuilder::make()
                        ->theme('info')
                        ->title(__('emails_misc.admin_notify.new_group_title'))
                        ->previewText(__('emails_misc.admin_notify.new_group_preview', ['community' => $tenantName]))
                        ->greeting($adminName)
                        ->paragraph(__('emails_misc.admin_notify.new_group_body', ['community' => htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8')]))
                        ->highlight(htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'))
                        ->bulletList([
                            __('emails_misc.admin_notify.new_group_by_label') . ': ' . htmlspecialchars($creatorName, ENT_QUOTES, 'UTF-8'),
                        ])
                        ->button(__('emails_misc.admin_notify.new_group_cta'), $groupUrl)
                        ->render();

                    if (!$mailer->send($adminEmail, $subject, $html)) {
                        Log::warning('NotifyAdminOfNewGroup: email send failed', ['admin_id' => $admin->id, 'email' => $adminEmail]);
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::error('NotifyAdminOfNewGroup listener failed', [
                'group_id'  => $event->group->id ?? null,
                'tenant_id' => $event->tenantId,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
        } finally {
            TenantContext::reset(); // Prevent context leaking to next queued job
        }
    }
}
