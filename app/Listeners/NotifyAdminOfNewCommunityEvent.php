<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\EmailTemplateBuilder;
use App\Core\TenantContext;
use App\Events\CommunityEventCreated;
use App\I18n\LocaleContext;
use App\Models\Notification;
use App\Services\EmailDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies all admins, brokers, and coordinators when a new community event is created.
 */
class NotifyAdminOfNewCommunityEvent implements ShouldQueue
{
    /**
     * Fail fast instead of letting redis re-deliver this fanout mid-flight
     * (retry_after=90s). A re-delivery would re-email EVERY admin. $timeout<retry_after
     * plus the Cache idempotency guard keep one event → one fanout.
     */
    public int $tries = 1;
    public int $timeout = 60;

    public function handle(CommunityEventCreated $event): void
    {
        // Idempotency guard: suppress duplicate/concurrent re-deliveries so the admin
        // fanout (email + bell to every admin) runs exactly once per event.
        $entityId = (int) ($event->event->id ?? 0);
        $tenantId = (int) ($event->tenantId ?? 0);
        $handledKey = null;
        $claimKey = null;
        $claimAcquired = false;
        if ($entityId > 0) {
            $handledKey = 'notify_admin_new_event:done:' . $tenantId . ':' . $entityId;
            $claimKey = 'notify_admin_new_event:claim:' . $tenantId . ':' . $entityId;
            if (Cache::has($handledKey)) {
                Log::info('NotifyAdminOfNewCommunityEvent: duplicate fanout suppressed', ['entity_id' => $entityId, 'tenant_id' => $tenantId]);
                return;
            }
            $claimAcquired = Cache::add($claimKey, 1, now()->addMinutes(5));
            if (!$claimAcquired) {
                Log::info('NotifyAdminOfNewCommunityEvent: concurrent fanout suppressed', ['entity_id' => $entityId, 'tenant_id' => $tenantId]);
                return;
            }
        }

        $previousTenantId = TenantContext::currentId();

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

            foreach ($admins as $admin) {
                $adminEmail = $admin->email ?? null;
                if (!$adminEmail) {
                    continue;
                }

                LocaleContext::withLocale($admin, function () use ($admin, $communityEvent, $eventTitle, $eventUrl, $creatorName, $tenantName, $adminEmail, $event) {
                    $adminName = $admin->first_name ?? $admin->name ?? 'Admin';

                    $bellContent = __('emails_misc.admin_notify.new_event_bell', ['title' => $eventTitle]);
                    Notification::createNotification((int) $admin->id, $bellContent, '/events/' . $communityEvent->id, 'new_event_created');

                    $subject = __('emails_misc.admin_notify.new_event_subject', ['community' => $tenantName]);

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

                    if (!EmailDispatchService::sendRaw($adminEmail, $subject, $html, null, null, null, 'admin_new_event', ['tenant_id' => $event->tenantId])) {
                        Log::warning('NotifyAdminOfNewCommunityEvent: email send failed', ['admin_id' => $admin->id, 'email' => $adminEmail]);
                    }
                });
            }

            // Mark handled only after the full fanout ran, so a redis re-delivery can't re-email admins.
            if ($handledKey !== null) {
                Cache::put($handledKey, 1, now()->addHours(24));
            }
        } catch (\Throwable $e) {
            Log::error('NotifyAdminOfNewCommunityEvent listener failed', [
                'event_id'  => $event->event->id ?? null,
                'tenant_id' => $event->tenantId,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
        } finally {
            if ($claimAcquired && $claimKey !== null) {
                Cache::forget($claimKey);
            }
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }
    }
}
