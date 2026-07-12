<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use App\Services\NotificationSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature coverage for the all-or-nothing React notification-settings save.
 */
class NotificationSettingsControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        DB::table('users')->where('id', $user->id)->update([
            'notification_preferences' => json_encode([
                'email_messages' => 1,
                'preserved_future_preference' => 1,
            ]),
            'federation_notifications_enabled' => 1,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        $notifications = NotificationSettingsService::GENERAL_DEFAULTS;
        $notifications['email_messages'] = false;
        $notifications['email_events'] = false;
        $notifications['push_enabled'] = false;
        $notifications[NotificationSettingsService::FEDERATION_KEY] = false;

        return [
            'notifications' => $notifications,
            'match_preferences' => [
                'notification_frequency' => 'fortnightly',
                'notify_hot_matches' => false,
                'notify_mutual_matches' => true,
            ],
            'digest_frequency' => 'daily',
        ];
    }

    public function test_update_requires_authentication(): void
    {
        $this->apiPut('/v2/users/me/notification-settings', $this->validPayload())
            ->assertStatus(401);
    }

    public function test_update_persists_all_three_groups_and_returns_canonical_values(): void
    {
        $user = $this->authenticatedUser();
        $payload = $this->validPayload();
        $payload['match_preferences']['notification_frequency'] = 'weekly';
        $payload['digest_frequency'] = 'weekly';

        $response = $this->apiPut('/v2/users/me/notification-settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.notifications.email_messages', false)
            ->assertJsonPath('data.notifications.email_events', false)
            ->assertJsonPath('data.notifications.federation_notifications_enabled', false)
            ->assertJsonPath('data.match_preferences.notification_frequency', 'monthly')
            ->assertJsonPath('data.match_preferences.notify_hot_matches', false)
            ->assertJsonPath('data.match_preferences.notify_mutual_matches', true)
            ->assertJsonPath('data.digest_frequency', 'monthly');

        $userRow = DB::table('users')
            ->where('id', $user->id)
            ->where('tenant_id', $this->testTenantId)
            ->first();
        $storedNotifications = json_decode((string) $userRow->notification_preferences, true);
        $this->assertSame(0, $storedNotifications['email_messages']);
        $this->assertSame(0, $storedNotifications['email_events']);
        $this->assertSame(1, $storedNotifications['preserved_future_preference']);
        $this->assertSame(0, (int) $userRow->federation_notifications_enabled);

        $this->assertDatabaseHas('match_preferences', [
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'notification_frequency' => 'monthly',
            'notify_hot_matches' => 0,
            'notify_mutual_matches' => 1,
        ]);
        $this->assertDatabaseHas('notification_settings', [
            'user_id' => $user->id,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'monthly',
        ]);
    }

    public function test_validation_finishes_before_any_group_is_written(): void
    {
        $user = $this->authenticatedUser();
        $this->seedExistingSettings($user);

        $payload = $this->validPayload();
        $payload['match_preferences']['notification_frequency'] = 'hourly';

        $this->apiPut('/v2/users/me/notification-settings', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.field', 'match_preferences.notification_frequency');

        $this->assertExistingSettingsRemain($user);
    }

    public function test_missing_notification_boolean_is_rejected(): void
    {
        $this->authenticatedUser();
        $payload = $this->validPayload();
        unset($payload['notifications']['email_messages']);

        $this->apiPut('/v2/users/me/notification-settings', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'notifications.email_messages');
    }

    public function test_invalid_match_boolean_is_rejected(): void
    {
        $this->authenticatedUser();
        $payload = $this->validPayload();
        $payload['match_preferences']['notify_hot_matches'] = 'sometimes';

        $this->apiPut('/v2/users/me/notification-settings', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'match_preferences.notify_hot_matches');
    }

    public function test_invalid_digest_frequency_is_rejected(): void
    {
        $this->authenticatedUser();
        $payload = $this->validPayload();
        $payload['digest_frequency'] = 'hourly';

        $this->apiPut('/v2/users/me/notification-settings', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'digest_frequency');
    }

    public function test_failure_in_the_first_write_does_not_touch_other_groups(): void
    {
        $user = $this->authenticatedUser();
        $this->seedExistingSettings($user);

        $failingService = new class extends NotificationSettingsService {
            protected function persistGeneralPreferences(
                int $userId,
                int $tenantId,
                object $user,
                array $notifications,
            ): array {
                throw new \RuntimeException('Deliberate first-write failure for transaction coverage.');
            }
        };
        $this->app->instance(NotificationSettingsService::class, $failingService);

        $this->apiPut('/v2/users/me/notification-settings', $this->validPayload())
            ->assertStatus(500);

        $this->assertExistingSettingsRemain($user);
    }

    public function test_failure_in_the_second_write_rolls_back_the_first_group(): void
    {
        $user = $this->authenticatedUser();
        $this->seedExistingSettings($user);

        $failingService = new class extends NotificationSettingsService {
            protected function persistMatchPreferences(int $userId, int $tenantId, array $preferences): void
            {
                throw new \RuntimeException('Deliberate second-write failure for transaction coverage.');
            }
        };
        $this->app->instance(NotificationSettingsService::class, $failingService);

        $this->apiPut('/v2/users/me/notification-settings', $this->validPayload())
            ->assertStatus(500);

        $this->assertExistingSettingsRemain($user);
    }

    public function test_failure_in_the_final_write_rolls_back_the_other_groups(): void
    {
        $user = $this->authenticatedUser();
        $this->seedExistingSettings($user);

        $failingService = new class extends NotificationSettingsService {
            protected function persistDigestFrequency(int $userId, string $digestFrequency): void
            {
                throw new \RuntimeException('Deliberate final-write failure for transaction coverage.');
            }
        };
        $this->app->instance(NotificationSettingsService::class, $failingService);

        $this->apiPut('/v2/users/me/notification-settings', $this->validPayload())
            ->assertStatus(500)
            ->assertJsonPath('errors.0.code', 'UPDATE_FAILED');

        $this->assertExistingSettingsRemain($user);
    }

    public function test_service_rejects_a_user_outside_the_supplied_tenant_before_writing(): void
    {
        $user = $this->authenticatedUser();
        $this->seedExistingSettings($user);
        $payload = $this->validPayload();

        try {
            $this->app->make(NotificationSettingsService::class)->updateAtomically(
                (int) $user->id,
                999,
                $payload['notifications'],
                $payload['match_preferences'],
                $payload['digest_frequency'],
            );
            $this->fail('A member must not be writable through another tenant scope.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('request tenant', $e->getMessage());
        }

        $this->assertExistingSettingsRemain($user);
    }

    private function seedExistingSettings(User $user): void
    {
        DB::table('users')
            ->where('id', $user->id)
            ->where('tenant_id', $this->testTenantId)
            ->update([
                'notification_preferences' => json_encode([
                    'email_messages' => 1,
                    'email_events' => 1,
                ]),
                'federation_notifications_enabled' => 1,
            ]);

        DB::table('match_preferences')->updateOrInsert(
            ['user_id' => $user->id, 'tenant_id' => $this->testTenantId],
            [
                'notification_frequency' => 'daily',
                'notify_hot_matches' => 1,
                'notify_mutual_matches' => 0,
                'updated_at' => now(),
            ],
        );
        DB::table('notification_settings')->updateOrInsert(
            ['user_id' => $user->id, 'context_type' => 'global', 'context_id' => 0],
            ['frequency' => 'instant'],
        );
    }

    private function assertExistingSettingsRemain(User $user): void
    {
        $userRow = DB::table('users')
            ->where('id', $user->id)
            ->where('tenant_id', $this->testTenantId)
            ->first();
        $storedNotifications = json_decode((string) $userRow->notification_preferences, true);

        $this->assertSame(1, $storedNotifications['email_messages']);
        $this->assertSame(1, $storedNotifications['email_events']);
        $this->assertSame(1, (int) $userRow->federation_notifications_enabled);
        $this->assertDatabaseHas('match_preferences', [
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'notification_frequency' => 'daily',
            'notify_hot_matches' => 1,
            'notify_mutual_matches' => 0,
        ]);
        $this->assertDatabaseHas('notification_settings', [
            'user_id' => $user->id,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'instant',
        ]);
    }
}
