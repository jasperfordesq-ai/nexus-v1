<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\NotificationSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Atomic notification-settings endpoint for the React member settings page.
 */
class NotificationSettingsController extends BaseApiController
{
    protected bool $isV2Api = true;

    private const MATCH_FREQUENCIES = ['daily', 'weekly', 'monthly', 'fortnightly', 'never'];

    private const DIGEST_FREQUENCIES = ['instant', 'daily', 'weekly', 'monthly', 'off'];

    public function __construct(
        private readonly NotificationSettingsService $settingsService,
    ) {}

    /**
     * PUT /api/v2/users/me/notification-settings
     */
    public function update(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('atomic_notification_settings_update', 10, 60);

        $payload = $this->validateAndNormalize($this->getAllInput());
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        try {
            $settings = $this->settingsService->updateAtomically(
                $userId,
                TenantContext::getId(),
                $payload['notifications'],
                $payload['match_preferences'],
                $payload['digest_frequency'],
            );
        } catch (\Throwable $e) {
            Log::error('Atomic notification settings update failed', [
                'user_id' => $userId,
                'tenant_id' => TenantContext::getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->respondWithError(
                'UPDATE_FAILED',
                __('api_controllers_2.users.notification_settings_update_failed'),
                null,
                500,
            );
        }

        return $this->respondWithData($settings);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *     notifications: array<string, bool>,
     *     match_preferences: array{notification_frequency: string, notify_hot_matches: bool, notify_mutual_matches: bool},
     *     digest_frequency: string
     * }|JsonResponse
     */
    private function validateAndNormalize(array $input): array|JsonResponse
    {
        if (! isset($input['notifications']) || ! is_array($input['notifications'])) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.user_no_valid_prefs'),
                'notifications',
                422,
            );
        }

        $notifications = [];
        $notificationKeys = [
            ...array_keys(NotificationSettingsService::GENERAL_DEFAULTS),
            NotificationSettingsService::FEDERATION_KEY,
        ];
        foreach ($notificationKeys as $key) {
            if (! array_key_exists($key, $input['notifications'])) {
                return $this->invalidBoolean("notifications.{$key}");
            }

            $value = filter_var(
                $input['notifications'][$key],
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            );
            if ($value === null) {
                return $this->invalidBoolean("notifications.{$key}");
            }
            $notifications[$key] = $value;
        }

        if (! isset($input['match_preferences']) || ! is_array($input['match_preferences'])) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.user_no_valid_prefs'),
                'match_preferences',
                422,
            );
        }

        $matchFrequency = $input['match_preferences']['notification_frequency'] ?? null;
        if (! is_string($matchFrequency) || ! in_array($matchFrequency, self::MATCH_FREQUENCIES, true)) {
            return $this->invalidFrequency('match_preferences.notification_frequency');
        }
        if ($matchFrequency === 'weekly') {
            $matchFrequency = 'monthly';
        }

        $matchPreferences = ['notification_frequency' => $matchFrequency];
        foreach (['notify_hot_matches', 'notify_mutual_matches'] as $key) {
            if (! array_key_exists($key, $input['match_preferences'])) {
                return $this->invalidBoolean("match_preferences.{$key}");
            }
            $value = filter_var(
                $input['match_preferences'][$key],
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            );
            if ($value === null) {
                return $this->invalidBoolean("match_preferences.{$key}");
            }
            $matchPreferences[$key] = $value;
        }

        $digestFrequency = $input['digest_frequency'] ?? null;
        if (! is_string($digestFrequency) || ! in_array($digestFrequency, self::DIGEST_FREQUENCIES, true)) {
            return $this->invalidFrequency('digest_frequency');
        }
        if ($digestFrequency === 'weekly') {
            $digestFrequency = 'monthly';
        }

        /** @var array{notification_frequency: string, notify_hot_matches: bool, notify_mutual_matches: bool} $matchPreferences */
        return [
            'notifications' => $notifications,
            'match_preferences' => $matchPreferences,
            'digest_frequency' => $digestFrequency,
        ];
    }

    private function invalidBoolean(string $field): JsonResponse
    {
        return $this->respondWithError(
            'VALIDATION_ERROR',
            __('api.user_invalid_notification_preference', ['field' => $field]),
            $field,
            422,
        );
    }

    private function invalidFrequency(string $field): JsonResponse
    {
        return $this->respondWithError(
            'VALIDATION_ERROR',
            __('api_controllers_2.users.invalid_frequency'),
            $field,
            422,
        );
    }
}
