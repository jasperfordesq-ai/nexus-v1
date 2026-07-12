<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\Event;
use App\Models\User;
use App\Services\EventNotificationPreferenceResolver;
use App\Services\EventReminderPreferenceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\Laravel\TestCase;

final class EventReminderPreferenceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventReminderPreferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->service = app(EventReminderPreferenceService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_replace_is_strict_versioned_and_idempotent(): void
    {
        [$user, $event] = $this->subject();
        $overrides = [
            'email_enabled' => true,
            'in_app_enabled' => null,
            'web_push_enabled' => false,
            'fcm_enabled' => true,
            'realtime_enabled' => null,
            'cadence' => 'instant',
            'reminders_enabled' => true,
        ];
        $rules = [[
            'offset_minutes' => 10080,
            'email_enabled' => true,
            'in_app_enabled' => true,
            'web_push_enabled' => false,
            'fcm_enabled' => true,
            'realtime_enabled' => true,
        ], [
            'offset_minutes' => 30,
        ]];

        $created = $this->service->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            $overrides,
            $rules,
            0,
        );
        self::assertSame(1, $created['revision']);
        self::assertSame($overrides, $created['overrides']);
        self::assertSame([10080, 30], array_column($created['rules'], 'offset_minutes'));

        $replayed = $this->service->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            $overrides,
            $rules,
            1,
        );
        self::assertSame(1, $replayed['revision']);
        self::assertSame(2, DB::table('event_reminder_rules')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->count());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('event_reminder_preference_version_conflict');
        $this->service->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            ['email_enabled' => false],
            $rules,
            0,
        );
    }

    /** @dataProvider invalidRuleProvider */
    public function test_invalid_rules_fail_before_any_mutation(array $rules, string $message): void
    {
        [$user, $event] = $this->subject();

        try {
            $this->service->replaceEventPreferences(
                (int) $event->id,
                (int) $user->id,
                [],
                $rules,
                0,
            );
            self::fail('Invalid reminder rule was accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
        }
        self::assertDatabaseMissing('event_notification_preferences', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
        self::assertDatabaseMissing('event_reminder_rules', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
    }

    public static function invalidRuleProvider(): array
    {
        return [
            'below minimum' => [[['offset_minutes' => 4]], 'event_reminder_rule_offset_invalid'],
            'above maximum' => [[['offset_minutes' => 525601]], 'event_reminder_rule_offset_invalid'],
            'duplicate' => [[['offset_minutes' => 60], ['offset_minutes' => 60]], 'event_reminder_rule_offset_duplicate'],
            'string offset' => [[['offset_minutes' => '60']], 'event_reminder_rule_offset_invalid'],
            'unknown field' => [[['offset_minutes' => 60, 'type' => 'both']], 'event_reminder_rule_field_invalid'],
            'non-boolean channel' => [[['offset_minutes' => 60, 'email_enabled' => 1]], 'event_notification_preference_email_enabled_invalid'],
            'disabled rules still count toward the limit' => [array_fill(0, 11, [
                'offset_minutes' => 60,
                'enabled' => false,
            ]), 'event_reminder_rule_limit_exceeded'],
        ];
    }

    public function test_explicit_opt_out_at_any_user_scope_vetoes_a_more_specific_opt_in(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'notification_preferences' => [
                'email_events' => true,
                'push_enabled' => true,
            ],
        ]);
        $categoryId = (int) DB::table('categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Reminder category ' . uniqid(),
            'slug' => 'reminder-' . uniqid(),
            'type' => 'event',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'is_recurring_template' => false,
        ]);
        DB::table('notification_settings')->updateOrInsert(
            ['user_id' => $user->id, 'context_type' => 'global', 'context_id' => 0],
            ['frequency' => 'instant'],
        );

        $categoryPreference = $this->service->replaceCategoryPreference($categoryId, (int) $user->id, [
            'email_enabled' => false,
            'web_push_enabled' => false,
            'cadence' => 'off',
        ], 0);
        $this->service->replaceEventPreferences((int) $event->id, (int) $user->id, [
            'email_enabled' => true,
            'web_push_enabled' => true,
            'cadence' => 'instant',
        ], [['offset_minutes' => 60]], 0);

        $resolved = EventNotificationPreferenceResolver::resolveForEvent(
            (int) $user->id,
            $this->testTenantId,
            (int) $event->id,
        );
        self::assertFalse($resolved['channels']['email']);
        self::assertSame('category', $resolved['channel_sources']['email']);
        self::assertFalse($resolved['channels']['web_push']);
        self::assertSame('category', $resolved['channel_sources']['web_push']);
        self::assertSame('off', $resolved['cadence']);
        self::assertSame('category', $resolved['cadence_source']);

        $this->service->replaceCategoryPreference(
            $categoryId,
            (int) $user->id,
            ['email_enabled' => true],
            $categoryPreference['revision'],
        );
        DB::table('users')->where('id', $user->id)->update([
            'notification_preferences' => json_encode([
                'email_events' => false,
                'push_enabled' => true,
            ], JSON_THROW_ON_ERROR),
        ]);
        $globalVeto = EventNotificationPreferenceResolver::resolveForEvent(
            (int) $user->id,
            $this->testTenantId,
            (int) $event->id,
        );
        self::assertFalse($globalVeto['channels']['email']);
        self::assertSame('global', $globalVeto['channel_sources']['email']);
    }

    public function test_cross_tenant_event_is_indistinguishable_from_missing(): void
    {
        [$user, $event] = $this->subject();
        TenantContext::setById(999);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event_reminder_concrete_event_not_found');
        $this->service->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            [],
            [['offset_minutes' => 60]],
            0,
        );
    }

    /** @return array{User,Event} */
    private function subject(): array
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'is_recurring_template' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
        ]);

        return [$user, $event];
    }
}
