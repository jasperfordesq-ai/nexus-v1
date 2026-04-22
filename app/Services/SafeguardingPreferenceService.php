<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\TenantSafeguardingOption;
use App\Models\UserSafeguardingPreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CRUD for tenant safeguarding options and member preferences.
 *
 * All access to member preferences is audit-logged. This service also handles
 * country preset application and integrates with SafeguardingTriggerService
 * to activate broker protections when preferences are saved.
 */
class SafeguardingPreferenceService
{
    // =========================================================================
    // Tenant Safeguarding Options (Admin CRUD)
    // =========================================================================

    /**
     * Get all active safeguarding options for a tenant, ordered by sort_order.
     */
    public static function getOptionsForTenant(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        return TenantSafeguardingOption::where('tenant_id', $tenantId)
            ->active()
            ->get()
            ->map(fn ($opt) => $opt->toArray())
            ->all();
    }

    /**
     * Get ALL options for a tenant (including inactive), for admin management.
     */
    public static function getAllOptionsForTenant(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        return TenantSafeguardingOption::where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($opt) => $opt->toArray())
            ->all();
    }

    /**
     * Create a new safeguarding option for a tenant.
     */
    public static function createOption(int $tenantId, array $data): TenantSafeguardingOption
    {
        $data['label'] = strip_tags(trim($data['label']));
        if (isset($data['description'])) {
            $data['description'] = strip_tags(trim($data['description']));
        }

        $option = TenantSafeguardingOption::create([
            'tenant_id' => $tenantId,
            'option_key' => $data['option_key'],
            'option_type' => $data['option_type'] ?? 'checkbox',
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'help_url' => self::validateUrl($data['help_url'] ?? null),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'is_required' => $data['is_required'] ?? false,
            'select_options' => $data['select_options'] ?? null,
            'triggers' => $data['triggers'] ?? [],
            'preset_source' => $data['preset_source'] ?? null,
        ]);

        self::logActivity(null, 'safeguarding_option_created', 'safeguarding_option', $option->id, [
            'option_key' => $option->option_key,
            'label' => $option->label,
        ]);

        return $option;
    }

    /**
     * Update an existing safeguarding option.
     */
    public static function updateOption(int $optionId, array $data): bool
    {
        $option = TenantSafeguardingOption::where('id', $optionId)
            ->where('tenant_id', TenantContext::getId())
            ->first();
        if (!$option) {
            return false;
        }

        $fillable = ['label', 'description', 'help_url', 'sort_order', 'is_active', 'is_required', 'option_type', 'select_options', 'triggers'];
        $updates = array_intersect_key($data, array_flip($fillable));
        if (isset($updates['label'])) {
            $updates['label'] = strip_tags(trim($updates['label']));
        }
        if (isset($updates['description'])) {
            $updates['description'] = strip_tags(trim($updates['description']));
        }
        if (array_key_exists('help_url', $updates)) {
            $updates['help_url'] = self::validateUrl($updates['help_url']);
        }

        $option->update($updates);

        // Re-evaluate triggers for affected users when option activation or triggers change
        if (array_key_exists('is_active', $updates) || array_key_exists('triggers', $updates)) {
            $affectedUserIds = \App\Models\UserSafeguardingPreference::where('option_id', $optionId)
                ->whereNull('revoked_at')
                ->distinct()
                ->pluck('user_id');

            $tenantId = TenantContext::getId();
            foreach ($affectedUserIds as $userId) {
                // Full re-evaluation: invalidates cache, re-merges triggers, updates
                // user_messaging_restrictions, and re-dispatches notifications if needed
                SafeguardingTriggerService::activateTriggersForUser((int) $userId, $tenantId);
            }
        }

        self::logActivity(null, 'safeguarding_option_updated', 'safeguarding_option', $optionId, [
            'option_key' => $option->option_key,
            'changes' => array_keys($updates),
        ]);

        return true;
    }

    /**
     * Soft-delete (deactivate) a safeguarding option. Keeps data for audit trail.
     */
    public static function deleteOption(int $optionId): bool
    {
        $option = TenantSafeguardingOption::where('id', $optionId)
            ->where('tenant_id', TenantContext::getId())
            ->first();
        if (!$option) {
            return false;
        }

        $option->update(['is_active' => false]);

        // Re-evaluate triggers for affected users — deactivating an option may
        // remove monitoring/broker-approval requirements if it was the only trigger
        $affectedUserIds = \App\Models\UserSafeguardingPreference::where('option_id', $optionId)
            ->whereNull('revoked_at')
            ->distinct()
            ->pluck('user_id');

        $tenantId = TenantContext::getId();
        foreach ($affectedUserIds as $userId) {
            SafeguardingTriggerService::activateTriggersForUser((int) $userId, $tenantId);
        }

        self::logActivity(null, 'safeguarding_option_deleted', 'safeguarding_option', $optionId, [
            'option_key' => $option->option_key,
        ]);

        return true;
    }

    /**
     * Reorder options for a tenant.
     *
     * @param array $order Array of [id => sort_order]
     */
    public static function reorderOptions(int $tenantId, array $order): void
    {
        foreach ($order as $optionId => $sortOrder) {
            TenantSafeguardingOption::where('id', (int) $optionId)
                ->where('tenant_id', $tenantId)
                ->update(['sort_order' => (int) $sortOrder]);
        }
    }

    // =========================================================================
    // Country Preset Application
    // =========================================================================

    /**
     * Apply a country preset to a tenant. Uses INSERT IGNORE to avoid
     * overwriting existing custom options with the same option_key.
     *
     * @return array List of option_keys that were created (not already existing)
     */
    public static function applyCountryPreset(int $tenantId, string $presetKey): array
    {
        $presets = config('safeguarding_presets', []);
        $preset = $presets[$presetKey] ?? null;

        if (!$preset || empty($preset['options'])) {
            return [];
        }

        $created = [];
        $sortOrder = 0;

        foreach ($preset['options'] as $opt) {
            $sortOrder += 10;

            // INSERT IGNORE — won't overwrite existing option_keys
            $existing = TenantSafeguardingOption::where('tenant_id', $tenantId)
                ->where('option_key', $opt['option_key'])
                ->first();

            if ($existing) {
                continue; // Don't overwrite admin customisations
            }

            TenantSafeguardingOption::create([
                'tenant_id' => $tenantId,
                'option_key' => $opt['option_key'],
                'option_type' => $opt['option_type'] ?? 'checkbox',
                'label' => $opt['label'],
                'description' => $opt['description'] ?? null,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'is_required' => false,
                'triggers' => $opt['triggers'] ?? [],
                'preset_source' => $presetKey,
            ]);

            $created[] = $opt['option_key'];
        }

        self::logActivity(null, 'safeguarding_preset_applied', 'tenant', $tenantId, [
            'preset' => $presetKey,
            'options_created' => $created,
        ]);

        return $created;
    }

    /**
     * Get available country presets (for admin UI dropdown).
     */
    public static function getAvailablePresets(): array
    {
        $presets = config('safeguarding_presets', []);
        $result = [];

        foreach ($presets as $key => $preset) {
            $result[] = [
                'key' => $key,
                'name' => $preset['name'] ?? $key,
                'vetting_authority' => $preset['vetting_authority'] ?? '',
                'help_text' => $preset['help_text'] ?? '',
                'option_count' => count($preset['options'] ?? []),
            ];
        }

        return $result;
    }

    // =========================================================================
    // User Safeguarding Preferences (Member CRUD)
    // =========================================================================

    /**
     * Get a user's safeguarding preferences. ALWAYS audit-logged.
     *
     * @param int $accessorId Who is reading this data
     * @param string $accessorRole admin, broker, or member
     * @param string $context Why the data is being accessed (e.g., 'onboarding_review', 'broker_dashboard')
     */
    public static function getUserPreferences(
        int $tenantId,
        int $userId,
        int $accessorId,
        string $accessorRole,
        string $context = 'api_request'
    ): array {
        // Audit log every read
        self::logActivity($accessorId, 'safeguarding_preferences_viewed', 'user', $userId, [
            'accessor_role' => $accessorRole,
            'context' => $context,
        ]);

        return UserSafeguardingPreference::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->active()
            ->with('option')
            ->get()
            ->map(fn ($pref) => [
                'id' => $pref->id,
                'option_id' => $pref->option_id,
                'option_key' => $pref->option?->option_key,
                'option_label' => $pref->option?->label,
                'selected_value' => $pref->selected_value,
                'notes' => $pref->notes,
                'consent_given_at' => $pref->consent_given_at?->toISOString(),
            ])
            ->all();
    }

    /**
     * Save a user's safeguarding preferences from onboarding.
     * Records GDPR consent per option. Triggers broker protections.
     *
     * @param array $preferences Array of [{option_id, value, notes?}]
     * @param string|null $ipAddress User's IP for consent record
     */
    public static function saveUserPreferences(
        int $userId,
        array $preferences,
        ?string $ipAddress = null
    ): void {
        $tenantId = TenantContext::getId();
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($preferences as $pref) {
                $optionId = (int) ($pref['option_id'] ?? 0);
                $value = (string) ($pref['value'] ?? '1');
                $notes = $pref['notes'] ?? null;

                if ($optionId <= 0) {
                    continue;
                }

                // Validate option exists and belongs to this tenant
                $option = TenantSafeguardingOption::where('id', $optionId)
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->first();

                if (!$option) {
                    continue;
                }

                // Upsert preference with consent timestamp
                UserSafeguardingPreference::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'option_id' => $optionId,
                    ],
                    [
                        'selected_value' => $value,
                        'notes' => $notes,
                        'consent_given_at' => $now,
                        'consent_ip' => $ipAddress,
                        'revoked_at' => null, // Re-consent clears any previous revocation
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Audit log
        self::logActivity($userId, 'safeguarding_preferences_updated', 'user', $userId, [
            'options_count' => count($preferences),
        ]);

        // Activate broker protections based on triggers
        SafeguardingTriggerService::activateTriggersForUser($userId, $tenantId);
    }

    /**
     * Revoke a specific preference (member withdraws consent).
     */
    public static function revokePreference(int $userId, int $optionId): bool
    {
        $tenantId = TenantContext::getId();

        $pref = UserSafeguardingPreference::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('option_id', $optionId)
            ->whereNull('revoked_at')
            ->first();

        if (!$pref) {
            return false;
        }

        $optionLabel = $pref->option?->label ?? "option #{$optionId}";

        $pref->update(['revoked_at' => now()]);

        self::logActivity($userId, 'safeguarding_consent_revoked', 'user', $userId, [
            'option_id' => $optionId,
        ]);

        // Re-evaluate triggers (may deactivate protections)
        SafeguardingTriggerService::activateTriggersForUser($userId, $tenantId);

        // Bell admins/brokers — a member has withdrawn consent, protections
        // may have changed. Render per-staff so each admin sees the bell in
        // their own preferred_language (including the fallback_member_name
        // when the revoker has no usable display name).
        try {
            $revoker = \App\Models\User::find($userId);
            $hasName = $revoker
                && trim(($revoker->first_name ?? '') . ' ' . ($revoker->last_name ?? '')) !== '';
            $legacyName = $revoker ? ($revoker->name ?? null) : null;

            $staffUsers = DB::select(
                "SELECT id, preferred_language FROM users WHERE tenant_id = ? AND role IN ('admin', 'tenant_admin', 'broker', 'super_admin') AND status = 'active'",
                [$tenantId]
            );

            foreach ($staffUsers as $staff) {
                LocaleContext::withLocale($staff, function () use ($staff, $tenantId, $userId, $optionLabel, $hasName, $revoker, $legacyName) {
                    if ($hasName) {
                        $revokerName = trim(($revoker->first_name ?? '') . ' ' . ($revoker->last_name ?? ''));
                    } elseif (!empty($legacyName)) {
                        $revokerName = $legacyName;
                    } else {
                        // Revoker row missing or no display name — use the
                        // translated fallback, rendered in this staff member's
                        // preferred_language.
                        $revokerName = __('emails.common.fallback_member_name');
                    }

                    $bellMessage = __('emails_misc.safeguarding.consent_revoked_admin_bell', [
                        'name'   => $revokerName,
                        'option' => $optionLabel,
                    ]);

                    \App\Models\Notification::create([
                        'tenant_id' => $tenantId,
                        'user_id'   => $staff->id,
                        'type'      => 'safeguarding_flag',
                        'message'   => $bellMessage,
                        'link'      => "/admin/safeguarding?user={$userId}",
                        'is_read'   => false,
                    ]);
                });
            }
        } catch (\Throwable $notifErr) {
            Log::warning('SafeguardingPreferenceService::revokePreference: admin bell failed', [
                'user_id' => $userId,
                'error'   => $notifErr->getMessage(),
            ]);
        }

        return true;
    }

    // =========================================================================
    // Audit Logging
    // =========================================================================

    /**
     * Validate a URL is HTTP(S) — reject javascript: and other schemes.
     */
    private static function validateUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $url = trim($url);
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return null;
    }

    /**
     * Log safeguarding-related activity to the activity_log table.
     */
    private static function logActivity(
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId,
        array $details = []
    ): void {
        try {
            DB::table('activity_log')->insert([
                'tenant_id' => TenantContext::getId(),
                'user_id' => $userId,
                'action' => $action,
                'action_type' => 'safeguarding',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => json_encode($details),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SafeguardingPreferenceService: failed to log activity', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
