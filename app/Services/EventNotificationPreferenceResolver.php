<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Http\Controllers\Api\NotificationUnsubscribeController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Canonical resolver for Events email consent, cadence, and unsubscribe URLs.
 *
 * The email_events JSON preference is an opt-out toggle for existing members:
 * an absent key remains enabled, while an explicit false always wins over the
 * legacy notification_settings cadence. Lookup failures fail closed so a
 * worker cannot email a recipient when their consent state cannot be verified.
 */
final class EventNotificationPreferenceResolver
{
    public const EMAIL_PREFERENCE_KEY = 'email_events';
    public const UNSUBSCRIBE_CATEGORY = 'events';

    /** @var array<string,string> */
    private const CHANNEL_COLUMNS = [
        'email' => 'email_enabled',
        'in_app' => 'in_app_enabled',
        'web_push' => 'web_push_enabled',
        'fcm' => 'fcm_enabled',
        'realtime' => 'realtime_enabled',
    ];

    public static function allowsEmail(int $userId, int $tenantId): bool
    {
        if ($userId <= 0 || $tenantId <= 0) {
            return false;
        }

        try {
            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->first(['notification_preferences']);

            if ($user === null) {
                return false;
            }

            $raw = $user->notification_preferences ?? null;
            if ($raw === null || $raw === '') {
                return true;
            }

            $preferences = is_array($raw) ? $raw : json_decode((string) $raw, true);
            if (!is_array($preferences)) {
                Log::warning('[EventNotificationPreferenceResolver] Invalid notification preferences JSON', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                ]);

                return false;
            }

            if (!array_key_exists(self::EMAIL_PREFERENCE_KEY, $preferences)) {
                return true;
            }

            $enabled = filter_var(
                $preferences[self::EMAIL_PREFERENCE_KEY],
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            );
            if ($enabled === null) {
                Log::warning('[EventNotificationPreferenceResolver] Invalid Events email preference value', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                ]);

                return false;
            }

            return $enabled;
        } catch (\Throwable $e) {
            Log::warning('[EventNotificationPreferenceResolver] Preference lookup failed', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve the existing Events email cadence after the category consent
     * gate has passed. Precedence is global, then the tenant default. There is
     * no event context in notification_settings; configured per-event reminders
     * are handled separately. email_events=false overrides every cadence.
     * Invalid values fail closed to "off".
     */
    public static function frequency(int $userId, int $tenantId): string
    {
        if (!self::allowsEmail($userId, $tenantId)) {
            return 'off';
        }

        try {
            $frequency = DB::table('notification_settings')
                ->where('user_id', $userId)
                ->where('context_type', 'global')
                ->where('context_id', 0)
                ->value('frequency');

            if ($frequency === null) {
                $frequency = TenantContext::runForTenant($tenantId, static function (): string {
                    $tenant = TenantContext::get();
                    $configuration = json_decode((string) ($tenant['configuration'] ?? '{}'), true);

                    return (string) ($configuration['notifications']['default_frequency'] ?? 'off');
                });
            }

            return self::normalizeFrequency((string) $frequency);
        } catch (\Throwable $e) {
            Log::warning('[EventNotificationPreferenceResolver] Frequency lookup failed', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return 'off';
        }
    }

    /**
     * Resolve channel, cadence, and reminder inheritance for one concrete event.
     * Any explicit user opt-out at event, category, or global scope is a hard
     * veto. Otherwise the most-specific non-null choice wins before the tenant
     * default. Lookup or data-integrity failures fail closed.
     *
     * @return array{
     *   channels:array{email:bool,in_app:bool,web_push:bool,fcm:bool,realtime:bool},
     *   channel_sources:array{email:string,in_app:string,web_push:string,fcm:string,realtime:string},
     *   cadence:string,
     *   cadence_source:string,
     *   reminders_enabled:bool,
     *   reminders_source:string
     * }
     */
    public static function resolveForEvent(
        int $userId,
        int $tenantId,
        int $eventId,
        bool $allowRecurringTemplate = false,
    ): array
    {
        if ($userId <= 0 || $tenantId <= 0 || $eventId <= 0) {
            return self::failClosedResolution('invalid_identity');
        }

        try {
            $eventQuery = DB::table('events')
                ->where('tenant_id', $tenantId)
                ->where('id', $eventId);
            if (! $allowRecurringTemplate) {
                $eventQuery->where('is_recurring_template', false);
            }
            $event = $eventQuery->first(['id', 'category_id']);
            $user = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('id', $userId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->first(['notification_preferences']);
            if ($event === null || $user === null) {
                return self::failClosedResolution('subject_unavailable');
            }

            $global = self::globalPreferences($user->notification_preferences ?? null);
            if ($global === null) {
                return self::failClosedResolution('global_preferences_invalid');
            }
            $eventPreference = DB::table('event_notification_preferences')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('event_id', $eventId)
                ->whereNull('category_id')
                ->first();
            $categoryPreference = null;
            $categoryId = (int) ($event->category_id ?? 0);
            if ($categoryId > 0) {
                $categoryBelongsToTenant = DB::table('categories')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $categoryId)
                    ->whereIn('type', ['event', 'events'])
                    ->exists();
                if (! $categoryBelongsToTenant) {
                    return self::failClosedResolution('category_scope_invalid');
                }
                $categoryPreference = DB::table('event_notification_preferences')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->where('category_id', $categoryId)
                    ->whereNull('event_id')
                    ->first();
            }

            $defaults = self::tenantDefaults($tenantId);
            $globalEmail = self::globalBoolean($global, self::EMAIL_PREFERENCE_KEY);
            $globalPush = self::globalBoolean($global, 'push_enabled');
            if ((array_key_exists(self::EMAIL_PREFERENCE_KEY, $global) && $globalEmail === null)
                || (array_key_exists('push_enabled', $global) && $globalPush === null)) {
                return self::failClosedResolution('global_channel_invalid');
            }

            $channels = [];
            $sources = [];
            foreach (self::CHANNEL_COLUMNS as $channel => $column) {
                $globalValue = match ($channel) {
                    'email' => $globalEmail,
                    'web_push', 'fcm' => $globalPush,
                    default => null,
                };
                [$channels[$channel], $sources[$channel]] = self::resolveBooleanLayers(
                    self::nullableDatabaseBoolean($eventPreference, $column),
                    self::nullableDatabaseBoolean($categoryPreference, $column),
                    $globalValue,
                    $defaults['channels'][$channel],
                );
            }

            $globalCadence = self::globalCadence($userId);
            [$cadence, $cadenceSource] = self::resolveCadenceLayers(
                self::nullableCadence($eventPreference),
                self::nullableCadence($categoryPreference),
                $globalCadence,
                $defaults['cadence'],
            );
            [$remindersEnabled, $remindersSource] = self::resolveBooleanLayers(
                self::nullableDatabaseBoolean($eventPreference, 'reminders_enabled'),
                self::nullableDatabaseBoolean($categoryPreference, 'reminders_enabled'),
                null,
                $defaults['reminders_enabled'],
            );

            /** @var array{email:bool,in_app:bool,web_push:bool,fcm:bool,realtime:bool} $channels */
            /** @var array{email:string,in_app:string,web_push:string,fcm:string,realtime:string} $sources */
            return [
                'channels' => $channels,
                'channel_sources' => $sources,
                'cadence' => $cadence,
                'cadence_source' => $cadenceSource,
                'reminders_enabled' => $remindersEnabled,
                'reminders_source' => $remindersSource,
            ];
        } catch (\Throwable $e) {
            Log::warning('[EventNotificationPreferenceResolver] Event preference resolution failed', [
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return self::failClosedResolution('lookup_failed');
        }
    }

    public static function allowsChannelForEvent(
        int $userId,
        int $tenantId,
        int $eventId,
        string $channel,
    ): bool {
        if (! array_key_exists($channel, self::CHANNEL_COLUMNS)) {
            return false;
        }

        return self::resolveForEvent($userId, $tenantId, $eventId)['channels'][$channel];
    }

    public static function allowsEmailForEvent(int $userId, int $tenantId, int $eventId): bool
    {
        return self::allowsChannelForEvent($userId, $tenantId, $eventId, 'email');
    }

    /** Build a tenant-correct one-click URL that disables only Events email. */
    public static function unsubscribeUrl(int $userId, int $tenantId): string
    {
        return TenantContext::runForTenant(
            $tenantId,
            static fn (): string => NotificationUnsubscribeController::buildSignedUrl(
                $userId,
                $tenantId,
                self::UNSUBSCRIBE_CATEGORY,
            ),
        );
    }

    public static function isEventActivityType(string $activityType): bool
    {
        return str_starts_with(strtolower(trim($activityType)), 'event_');
    }

    public static function isCriticalEventActivity(string $activityType): bool
    {
        $normalized = strtolower(trim($activityType));
        foreach (['cancellation', 'cancelled', 'reconciliation', 'retraction'] as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    public static function isRecipientEligible(int $userId, int $tenantId): bool
    {
        if ($userId <= 0 || $tenantId <= 0) {
            return false;
        }

        try {
            return DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('[EventNotificationPreferenceResolver] Recipient eligibility lookup failed', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Routine Events background traffic stops with the tenant feature. Safety
     * and state-reconciliation messages remain deliverable after disablement.
     */
    public static function allowsBackgroundActivity(int $tenantId, string $activityType): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        $normalized = strtolower(trim($activityType));
        if (self::isCriticalEventActivity($normalized)) {
            return true;
        }

        if (!self::isEventActivityType($normalized)) {
            return true;
        }

        try {
            return (bool) TenantContext::runForTenant(
                $tenantId,
                static fn (): bool => (bool) TenantContext::hasFeature('events'),
            );
        } catch (\Throwable $e) {
            Log::warning('[EventNotificationPreferenceResolver] Events feature lookup failed', [
                'tenant_id' => $tenantId,
                'activity_type' => $normalized,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private static function normalizeFrequency(string $frequency): string
    {
        $normalized = strtolower(trim($frequency));
        if ($normalized === 'weekly') {
            return 'monthly';
        }

        return in_array($normalized, ['off', 'instant', 'daily', 'monthly'], true)
            ? $normalized
            : 'off';
    }

    /** @return array<string,mixed>|null */
    private static function globalPreferences(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function globalBoolean(array $preferences, string $key): ?bool
    {
        if (! array_key_exists($key, $preferences)) {
            return null;
        }

        return filter_var($preferences[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private static function globalCadence(int $userId): ?string
    {
        $frequency = DB::table('notification_settings')
            ->where('user_id', $userId)
            ->where('context_type', 'global')
            ->where('context_id', 0)
            ->value('frequency');
        if ($frequency === null) {
            return null;
        }

        return self::normalizeFrequency((string) $frequency);
    }

    private static function nullableDatabaseBoolean(?object $row, string $column): ?bool
    {
        if ($row === null || ! property_exists($row, $column) || $row->{$column} === null) {
            return null;
        }

        return match ($row->{$column}) {
            true, 1, '1' => true,
            false, 0, '0' => false,
            default => throw new \UnexpectedValueException('event_notification_preference_boolean_invalid'),
        };
    }

    private static function nullableCadence(?object $row): ?string
    {
        if ($row === null || ($row->cadence ?? null) === null) {
            return null;
        }
        $cadence = strtolower(trim((string) $row->cadence));
        return in_array($cadence, EventReminderPreferenceService::CADENCES, true)
            ? $cadence
            : 'off';
    }

    /** @return array{bool,string} */
    private static function resolveBooleanLayers(
        ?bool $event,
        ?bool $category,
        ?bool $global,
        bool $tenant,
    ): array {
        foreach ([
            'event' => $event,
            'category' => $category,
            'global' => $global,
        ] as $source => $value) {
            if ($value === false) {
                return [false, $source];
            }
        }
        foreach ([
            'event' => $event,
            'category' => $category,
            'global' => $global,
        ] as $source => $value) {
            if ($value === true) {
                return [true, $source];
            }
        }

        return [$tenant, 'tenant'];
    }

    /** @return array{string,string} */
    private static function resolveCadenceLayers(
        ?string $event,
        ?string $category,
        ?string $global,
        string $tenant,
    ): array {
        foreach ([
            'event' => $event,
            'category' => $category,
            'global' => $global,
        ] as $source => $value) {
            if ($value === 'off') {
                return ['off', $source];
            }
        }
        foreach ([
            'event' => $event,
            'category' => $category,
            'global' => $global,
        ] as $source => $value) {
            if ($value !== null && in_array($value, EventReminderPreferenceService::CADENCES, true)) {
                return [$value, $source];
            }
        }

        return [$tenant, 'tenant'];
    }

    /**
     * @return array{
     *   channels:array{email:bool,in_app:bool,web_push:bool,fcm:bool,realtime:bool},
     *   cadence:string,
     *   reminders_enabled:bool
     * }
     */
    private static function tenantDefaults(int $tenantId): array
    {
        $configuration = DB::table('tenants')->where('id', $tenantId)->value('configuration');
        $decoded = is_array($configuration)
            ? $configuration
            : json_decode((string) ($configuration ?? '{}'), true);
        $tenant = is_array($decoded)
            ? ($decoded['notifications']['event_defaults'] ?? [])
            : [];
        if (! is_array($tenant)) {
            $tenant = [];
        }
        $tenantChannels = $tenant['channels'] ?? [];
        if (! is_array($tenantChannels)) {
            $tenantChannels = [];
        }
        $configuredChannels = config('events.reminders.default_channels', []);
        if (! is_array($configuredChannels)) {
            $configuredChannels = [];
        }

        $channels = [];
        foreach (array_keys(self::CHANNEL_COLUMNS) as $channel) {
            $fallback = filter_var(
                $configuredChannels[$channel] ?? true,
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;
            $channels[$channel] = filter_var(
                $tenantChannels[$channel] ?? $fallback,
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;
        }
        $tenantFrequency = is_array($decoded)
            ? ($decoded['notifications']['default_frequency'] ?? null)
            : null;
        $cadence = self::normalizeFrequency((string) (
            $tenant['cadence']
            ?? $tenantFrequency
            ?? config('events.reminders.default_cadence', 'off')
        ));
        $remindersEnabled = filter_var(
            $tenant['reminders_enabled']
            ?? config('events.reminders.default_enabled', true),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE,
        ) ?? false;

        /** @var array{email:bool,in_app:bool,web_push:bool,fcm:bool,realtime:bool} $channels */
        return [
            'channels' => $channels,
            'cadence' => $cadence,
            'reminders_enabled' => $remindersEnabled,
        ];
    }

    /**
     * @return array{
     *   channels:array{email:false,in_app:false,web_push:false,fcm:false,realtime:false},
     *   channel_sources:array{email:string,in_app:string,web_push:string,fcm:string,realtime:string},
     *   cadence:'off',cadence_source:string,reminders_enabled:false,reminders_source:string
     * }
     */
    private static function failClosedResolution(string $reason): array
    {
        return [
            'channels' => [
                'email' => false,
                'in_app' => false,
                'web_push' => false,
                'fcm' => false,
                'realtime' => false,
            ],
            'channel_sources' => [
                'email' => $reason,
                'in_app' => $reason,
                'web_push' => $reason,
                'fcm' => $reason,
                'realtime' => $reason,
            ],
            'cadence' => 'off',
            'cadence_source' => $reason,
            'reminders_enabled' => false,
            'reminders_source' => $reason,
        ];
    }
}
