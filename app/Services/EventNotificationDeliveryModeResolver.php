<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\EventNotificationDeliveryMode;
use Illuminate\Support\Facades\Log;

final class EventNotificationDeliveryModeResolver
{
    public static function resolve(?int $tenantId = null): EventNotificationDeliveryMode
    {
        return EventNotificationDeliveryMode::from(self::inspect($tenantId)['resolved_mode']);
    }

    /**
     * Payload-free configuration inspection for health/readiness surfaces.
     * Raw invalid values are never returned or logged.
     *
     * @return array{
     *   resolved_mode:string,
     *   source:string,
     *   global_configuration_valid:bool,
     *   tenant_override_present:bool,
     *   tenant_configuration_valid:?bool,
     *   tenant_override_lookup_failed:bool
     * }
     */
    public static function inspect(?int $tenantId = null): array
    {
        $rawGlobal = config('events.notification_delivery.mode', 'outbox_authoritative');
        $globalCandidate = is_string($rawGlobal) ? trim($rawGlobal) : null;
        $globalMode = $globalCandidate !== null
            ? EventNotificationDeliveryMode::tryFrom($globalCandidate)
            : null;
        if ($globalMode === null) {
            Log::critical('Invalid global event notification delivery mode; failing safe', [
                'configuration_type' => get_debug_type($rawGlobal),
                'configuration_fingerprint' => self::fingerprint($rawGlobal),
            ]);
        }

        $tenantOverridePresent = false;
        $tenantConfigurationValid = null;
        $tenantLookupFailed = false;
        $tenantMode = null;

        if ($tenantId !== null && $tenantId > 0) {
            try {
                $override = TenantContext::runForTenant(
                    $tenantId,
                    static fn () => TenantContext::getSetting('events.notification_delivery_mode'),
                );
                $tenantOverridePresent = $override !== null
                    && (! is_string($override) || trim($override) !== '');
                if ($tenantOverridePresent) {
                    $candidate = is_string($override) ? trim($override) : null;
                    $tenantMode = $candidate !== null
                        ? EventNotificationDeliveryMode::tryFrom($candidate)
                        : null;
                    $tenantConfigurationValid = $tenantMode !== null;
                    if ($tenantMode === null) {
                        Log::critical('Invalid tenant event notification delivery mode; using safe fallback', [
                            'tenant_id' => $tenantId,
                            'configuration_type' => get_debug_type($override),
                            'configuration_fingerprint' => self::fingerprint($override),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $tenantLookupFailed = true;
                Log::warning('Event notification delivery-mode override lookup failed', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $resolved = $tenantMode ?? $globalMode ?? EventNotificationDeliveryMode::Direct;
        $source = $tenantMode !== null
            ? 'tenant_override'
            : ($globalMode !== null ? 'global' : 'safe_default');

        return [
            'resolved_mode' => $resolved->value,
            'source' => $source,
            'global_configuration_valid' => $globalMode !== null,
            'tenant_override_present' => $tenantOverridePresent,
            'tenant_configuration_valid' => $tenantConfigurationValid,
            'tenant_override_lookup_failed' => $tenantLookupFailed,
        ];
    }

    public static function consumerEnabled(): bool
    {
        return (bool) config('events.notification_delivery.consumer_enabled', true);
    }

    private static function fingerprint(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return hash('sha256', get_debug_type($value) . ':' . (string) $value);
        }

        return hash('sha256', get_debug_type($value));
    }
}
