<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Events\ListingCreated;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies all admins, brokers, and coordinators when a new listing is posted.
 */
class NotifyAdminOfNewListing implements ShouldQueue
{
    public function handle(ListingCreated $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            $listing    = $event->listing;
            $poster     = $event->user;
            $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
            $baseUrl    = TenantContext::getFrontendUrl();
            $basePath   = TenantContext::getSlugPrefix();
            $listingUrl = $baseUrl . $basePath . '/listings/' . $listing->id;

            $posterName   = trim(($poster->first_name ?? '') . ' ' . ($poster->last_name ?? ''))
                ?: ($poster->name ?? 'A member');
            $listingTitle = $listing->title ?? 'Untitled';
            $listingType  = ucfirst($listing->type ?? 'listing');

            $admins = DB::table('users')
                ->where('tenant_id', $event->tenantId)
                ->whereIn('role', ['super_admin', 'admin', 'tenant_admin', 'broker', 'coordinator'])
                ->where('status', 'active')
                ->select(['id', 'email', 'first_name', 'name'])
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $subject = __('emails_misc.admin_notify.new_listing_subject', ['community' => $tenantName]);
            $mailer  = Mailer::forCurrentTenant();

            foreach ($admins as $admin) {
                $adminEmail = $admin->email ?? null;
                if (!$adminEmail) {
                    continue;
                }

                $adminName = $admin->first_name ?? $admin->name ?? 'Admin';

                // In-app bell notification
                $bellContent = __('emails_misc.admin_notify.new_listing_bell', [
                    'title'  => $listingTitle,
                    'poster' => $posterName,
                ]);
                Notification::createNotification((int) $admin->id, $bellContent, '/listings/' . $listing->id, 'new_listing_created');

                // Email
                $html = EmailTemplateBuilder::make()
                    ->theme('info')
                    ->title(__('emails_misc.admin_notify.new_listing_title'))
                    ->previewText(__('emails_misc.admin_notify.new_listing_preview', ['community' => $tenantName]))
                    ->greeting($adminName)
                    ->paragraph(__('emails_misc.admin_notify.new_listing_body', ['community' => htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8')]))
                    ->highlight(htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8'))
                    ->bulletList([
                        __('emails_misc.admin_notify.new_listing_type_label') . ': ' . $listingType,
                        __('emails_misc.admin_notify.new_listing_by_label') . ': ' . htmlspecialchars($posterName, ENT_QUOTES, 'UTF-8'),
                    ])
                    ->button(__('emails_misc.admin_notify.new_listing_cta'), $listingUrl)
                    ->render();

                if (!$mailer->send($adminEmail, $subject, $html)) {
                    Log::warning('NotifyAdminOfNewListing: email send failed', ['admin_id' => $admin->id, 'email' => $adminEmail]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('NotifyAdminOfNewListing listener failed', [
                'listing_id' => $event->listing->id ?? null,
                'tenant_id'  => $event->tenantId,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
        } finally {
            TenantContext::reset(); // Prevent context leaking to next queued job
        }
    }
}
