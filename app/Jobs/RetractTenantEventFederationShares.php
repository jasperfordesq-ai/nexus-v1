<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Jobs;

use App\Core\TenantContext;
use App\Enums\EventFederationTombstoneReason;
use App\Models\Event;
use App\Services\EventConfigurationService;
use App\Services\EventFederationPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Retract every externally shared Event after a tenant disables federation. */
final class RetractTenantEventFederationShares implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;

    public function __construct(
        private readonly int $tenantId,
        private readonly int $actorId,
    ) {}

    public function handle(EventFederationPublisher $publisher): void
    {
        TenantContext::runForTenant($this->tenantId, function () use ($publisher): void {
            if ((bool) app(EventConfigurationService::class)->value(
                'federation_sharing_enabled',
                true,
                $this->tenantId,
            )) {
                return;
            }

            Event::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->where('federated_visibility', '<>', 'none')
                ->orderBy('id')
                ->select(['id'])
                ->chunkById(100, function ($rows) use ($publisher): void {
                    foreach ($rows as $row) {
                        DB::transaction(function () use ($publisher, $row): void {
                            /** @var Event|null $event */
                            $event = Event::withoutGlobalScopes()
                                ->where('tenant_id', $this->tenantId)
                                ->whereKey((int) $row->id)
                                ->lockForUpdate()
                                ->first();
                            if ($event === null || (string) $event->federated_visibility === 'none') {
                                return;
                            }
                            $event->forceFill([
                                'federated_visibility' => 'none',
                                'federation_version' => (int) $event->federation_version + 1,
                                'updated_at' => now(),
                            ])->save();
                            $publisher->publish($event, EventFederationTombstoneReason::VisibilityWithdrawn);
                        }, 3);
                    }
                });
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Tenant Event federation retraction failed', [
            'tenant_id' => $this->tenantId,
            'actor_id' => $this->actorId,
            'exception' => $exception::class,
        ]);
    }
}
