<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Events\CommunityEventCreated;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies all admins, brokers, and coordinators when a new community event is created.
 */
class NotifyAdminOfNewCommunityEvent implements ShouldQueue
{
    public function handle(CommunityEventCreated $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            $communityEvent = $event->event;
            $tenantName     = TenantContext::get()['name'] ?? 'Project NEXUS';
            $baseUrl        = TenantContext::getFrontendUrl();
            $basePath       = TenantContext::getSlugPrefix();
            $eventUrl       = $baseUrl . $basePath . '/events/' . $communityEvent->id;

            $eventTitle = $communityEvent->title ?? 'Untitled event';

            // Load the creator's name
            $creatorName = 'A member';
            if (!empty($communityEvent->created_by)) {
                $creator = DB::table('users')
                    ->where('id', $communityEvent->created_by)
                    ->where('tenant_id', $event->tenantId)
                    ->select(['first_name', 'last_name', 'name'])
                    ->first();
                if ($creator) {
                    $creatorName = trim(($creator->first_name ?? '') . ' ' . ($creator->last_name ?? ''))
                        ?: ($creator->name ?? 'A member');
                }
            }

            $admins = DB::table('users')
                ->where('tenant_id', $event->tenantId)
                ->whereIn('role', ['super_admin', 'admin', 'tenant_admin', 'broker', 'coordinator'])
                ->where('status', 'active')
                ->select(['id', 'email', 'first_name', 'name'])
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $subject = __('emails_misc.admin_notify.new_event_subject', ['community' => $tenantName]);
            $mailer  = Mailer::forCurrentTenant();

            foreach ($admins as $admin) {
                $adminEmail = $admin->email ?? null;
                if (!$adminEmail) {
                    continue;
                }

                $adminName = $admin->first_name ?? $admin->name ?? 'Admin';

                $bellContent = __('emails_misc.admin_notify.new_event_bell', ['title' => $eventTitle]);
                Notification::createNotification((int) $admin->id, $bellContent, '/events/' . $communityEvent->id, 'new_event_created');

                $html = EmailTemplateBuilder::make()
                    ->theme('info')
                    ->title(__('emails_misc.admin_notify.new_event_title'))
                    ->previewText(__('emails_misc.admin_notify.new_event_preview', ['community' => $tenantName]))
                    ->greeting($adminName)
                    ->paragraph(__('emails_misc.admin_notify.new_event_body', ['community' => htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8')]))
                    ->highlight(htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8'))
                    ->bulletList([
                        __('emails_misc.admin_notify.new_event_by_label') . ': ' . htmlspecialchars($creatorName, ENT_QUOTES, 'UTF-8'),
                    ])
                    ->button(__('emails_misc.admin_notify.new_event_cta'), $eventUrl)
                    ->render();

                if (!$mailer->send($adminEmail, $subject, $html)) {
                    Log::warning('NotifyAdminOfNewCommunityEvent: email send failed', ['admin_id' => $admin->id, 'email' => $adminEmail]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('NotifyAdminOfNewCommunityEvent listener failed', [
                'event_id'  => $event->event->id ?? null,
                'tenant_id' => $event->tenantId,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
        } finally {
            TenantContext::reset(); // Prevent context leaking to next queued job
        }
    }
}
