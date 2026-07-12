<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\EventSafetyEnforcementMode;
use App\Exceptions\EventSafetyException;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Payload-free, tenant-aware rollout resolution with an explicit safe default. */
final class EventSafetyEnforcementModeResolver
{
    public static function resolve(?int $tenantId = null): EventSafetyEnforcementMode
    {
        $inspection = self::inspect($tenantId);
        if (! $inspection['configuration_valid']) {
            throw new EventSafetyException('event_safety_rollout_configuration_invalid');
        }

        return EventSafetyEnforcementMode::from($inspection['resolved_mode']);
    }

    /**
     * @return array{
     *   resolved_mode:string,
     *   source:string,
     *   configuration_valid:bool,
     *   global_configuration_valid:bool,
     *   tenant_override_present:bool,
     *   tenant_configuration_valid:?bool,
     *   tenant_override_lookup_failed:bool
     * }
     */
    public static function inspect(?int $tenantId = null): array
    {
        $rawGlobal = config('events.safety.enforcement_mode', 'off');
        $globalCandidate = is_string($rawGlobal) ? trim($rawGlobal) : null;
        $globalMode = $globalCandidate !== null
            ? EventSafetyEnforcementMode::tryFrom($globalCandidate)
            : null;
        if ($globalMode === null) {
            Log::critical('Invalid global event safety enforcement mode', [
                'configuration_type' => get_debug_type($rawGlobal),
                'configuration_fingerprint' => self::fingerprint($rawGlobal),
            ]);
        }

        $overridePresent = false;
        $tenantValid = null;
        $lookupFailed = false;
        $tenantMode = null;
        if ($tenantId !== null && $tenantId > 0) {
            try {
                $rawOverride = TenantContext::runForTenant(
                    $tenantId,
                    static fn (): mixed => TenantContext::getSetting(
                        'events.safety_enforcement_mode',
                    ),
                );
                $overridePresent = $rawOverride !== null
                    && (! is_string($rawOverride) || trim($rawOverride) !== '');
                if ($overridePresent) {
                    $candidate = is_string($rawOverride) ? trim($rawOverride) : null;
                    $tenantMode = $candidate !== null
                        ? EventSafetyEnforcementMode::tryFrom($candidate)
                        : null;
                    $tenantValid = $tenantMode !== null;
                    if ($tenantMode === null) {
                        Log::critical('Invalid tenant event safety enforcement mode', [
                            'tenant_id' => $tenantId,
                            'configuration_type' => get_debug_type($rawOverride),
                            'configuration_fingerprint' => self::fingerprint($rawOverride),
                        ]);
                    }
                }
            } catch (Throwable $exception) {
                $lookupFailed = true;
                Log::warning('Event safety enforcement-mode lookup failed', [
                    'tenant_id' => $tenantId,
                    'exception' => $exception::class,
                ]);
            }
        }

        $valid = $globalMode !== null
            && ! $lookupFailed
            && (! $overridePresent || $tenantMode !== null);
        $resolved = $tenantMode ?? $globalMode ?? EventSafetyEnforcementMode::Off;

        return [
            'resolved_mode' => $resolved->value,
            'source' => $tenantMode !== null ? 'tenant_override' : 'global',
            'configuration_valid' => $valid,
            'global_configuration_valid' => $globalMode !== null,
            'tenant_override_present' => $overridePresent,
            'tenant_configuration_valid' => $tenantValid,
            'tenant_override_lookup_failed' => $lookupFailed,
        ];
    }

    private static function fingerprint(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return hash('sha256', get_debug_type($value) . ':' . (string) $value);
        }

        return hash('sha256', get_debug_type($value));
    }
}
