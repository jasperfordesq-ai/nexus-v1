<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Http\Controllers\Api\NotificationUnsubscribeController;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\EventNotificationPreferenceResolver;
use App\Services\EventNotificationService;
use App\Services\CronJobRunner;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class EventNotificationPreferenceComplianceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
    }

    public function test_events_email_defaults_on_but_explicit_opt_out_wins(): void
    {
        $user = $this->member();

        $this->assertTrue(EventNotificationPreferenceResolver::allowsEmail($user->id, $this->testTenantId));

        $this->setPreferences($user->id, ['email_events' => false]);

        $this->assertFalse(EventNotificationPreferenceResolver::allowsEmail($user->id, $this->testTenantId));
        $this->assertFalse(EventNotificationPreferenceResolver::allowsEmail($user->id, $this->testTenantId + 1000));
    }

    public function test_inactive_or_deleted_recipients_are_not_email_eligible(): void
    {
        $inactive = $this->member();
        $deleted = $this->member();
        DB::table('users')->where('id', $inactive->id)->where('tenant_id', $this->testTenantId)
            ->update(['status' => 'inactive']);
        DB::table('users')->where('id', $deleted->id)->where('tenant_id', $this->testTenantId)
            ->update(['deleted_at' => now()]);

        $this->assertFalse(EventNotificationPreferenceResolver::allowsEmail($inactive->id, $this->testTenantId));
        $this->assertFalse(EventNotificationPreferenceResolver::allowsEmail($deleted->id, $this->testTenantId));
    }

    public function test_events_opt_out_overrides_global_and_tenant_cadence_precedence(): void
    {
        $user = $this->member();
        $tenantConfiguration = json_decode((string) DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->value('configuration'), true);
        $tenantDefault = (string) ($tenantConfiguration['notifications']['default_frequency'] ?? 'off');
        $expectedTenantDefault = $tenantDefault === 'weekly'
            ? 'monthly'
            : (in_array($tenantDefault, ['off', 'instant', 'daily', 'monthly'], true) ? $tenantDefault : 'off');

        $this->assertSame($expectedTenantDefault, EventNotificationPreferenceResolver::frequency($user->id, $this->testTenantId));

        DB::table('notification_settings')->insert([
            'user_id' => $user->id,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'daily',
        ]);

        $this->assertSame('daily', EventNotificationPreferenceResolver::frequency($user->id, $this->testTenantId));

        $this->setPreferences($user->id, ['email_events' => false]);

        $this->assertSame('off', EventNotificationPreferenceResolver::frequency($user->id, $this->testTenantId));
    }

    public function test_events_unsubscribe_disables_only_events_email(): void
    {
        $user = $this->member();
        $this->setPreferences($user->id, [
            'email_events' => true,
            'email_messages' => true,
        ]);

        $url = EventNotificationPreferenceResolver::unsubscribeUrl($user->id, $this->testTenantId);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $response = $this->apiPost('/v2/notifications/unsubscribe?token=' . rawurlencode((string) ($query['token'] ?? '')), []);

        $response->assertStatus(200);
        $response->assertJsonPath('data.category', 'events');
        $preferences = $this->storedPreferences($user->id);
        $this->assertFalse((bool) $preferences['email_events']);
        $this->assertTrue((bool) $preferences['email_messages']);
    }

    public function test_unsubscribe_confirmation_uses_recipient_locale_and_captured_tenant(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'preferred_language' => 'de',
            'notification_preferences' => ['email_events' => true],
        ]);
        $tenantName = (string) DB::table('tenants')->where('id', $this->testTenantId)->value('name');
        $url = EventNotificationPreferenceResolver::unsubscribeUrl($user->id, $this->testTenantId);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $response = $this->apiGet('/v2/notifications/unsubscribe?token=' . rawurlencode((string) ($query['token'] ?? '')));

        $response->assertStatus(200);
        $response->assertSee('lang="de"', false);
        $response->assertSee('Sie wurden abgemeldet');
        $response->assertSee($tenantName);
    }

    public function test_instant_worker_rechecks_events_opt_out_without_suppressing_other_rows(): void
    {
        DB::table('notification_queue')->delete();
        Cache::forget('notification_queue:instant:runner_lock');
        $user = $this->member();
        $this->setPreferences($user->id, ['email_events' => true, 'email_digest' => true]);
        $eventQueueId = $this->enqueue($user->id, 'event_update', 'instant', 'Event changed');
        $messageQueueId = $this->enqueue($user->id, 'new_message', 'instant', 'New message');
        $this->unsubscribeEvents($user->id);

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);
        $this->invokeCronMethod('runInstantQueueInternal');

        $this->assertCount(1, $mailer->calls);
        $this->assertDatabaseHas('notification_queue', ['id' => $eventQueueId, 'status' => 'suppressed']);
        $this->assertDatabaseHas('notification_queue', ['id' => $messageQueueId, 'status' => 'sent']);
    }

    public function test_digest_worker_rechecks_mixed_batch_after_unsubscribe(): void
    {
        DB::table('notification_queue')->delete();
        $user = $this->member();
        $this->setPreferences($user->id, ['email_events' => true, 'email_digest' => true]);
        $eventQueueId = $this->enqueue($user->id, 'event_cancellation', 'daily', 'Cancelled event');
        $messageQueueId = $this->enqueue($user->id, 'new_message', 'daily', 'Message still eligible');
        $this->unsubscribeEvents($user->id);

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);
        $this->invokeCronMethod('processDigest', ['daily']);

        $this->assertCount(1, $mailer->calls);
        $this->assertStringNotContainsString('Cancelled event', $mailer->calls[0]['body']);
        $this->assertStringContainsString('Message still eligible', $mailer->calls[0]['body']);
        $this->assertDatabaseHas('notification_queue', ['id' => $eventQueueId, 'status' => 'suppressed']);
        $this->assertDatabaseHas('notification_queue', ['id' => $messageQueueId, 'status' => 'sent']);
        $this->assertTokenCategory((string) $mailer->calls[0]['options']['unsubscribeUrl'], 'digest');
    }

    public function test_digest_worker_suppresses_stale_events_when_current_cadence_is_off(): void
    {
        DB::table('notification_queue')->delete();
        $user = $this->member();
        $this->setPreferences($user->id, ['email_events' => true, 'email_digest' => true]);
        $this->setFrequency($user->id, 'off');
        $eventQueueId = $this->enqueue($user->id, 'event_update', 'daily', 'Stale event update');
        $messageQueueId = $this->enqueue($user->id, 'new_message', 'daily', 'Eligible message');

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);
        $this->invokeCronMethod('processDigest', ['daily']);

        $this->assertCount(1, $mailer->calls);
        $this->assertStringNotContainsString('Stale event update', $mailer->calls[0]['body']);
        $this->assertStringContainsString('Eligible message', $mailer->calls[0]['body']);
        $this->assertDatabaseHas('notification_queue', ['id' => $eventQueueId, 'status' => 'suppressed']);
        $this->assertDatabaseHas('notification_queue', ['id' => $messageQueueId, 'status' => 'sent']);
    }

    public function test_digest_worker_atomically_moves_daily_event_row_to_current_monthly_cadence(): void
    {
        DB::table('notification_queue')->delete();
        $user = $this->member();
        $this->setPreferences($user->id, ['email_events' => true, 'email_digest' => true]);
        $this->setFrequency($user->id, 'monthly');
        $eventQueueId = $this->enqueue($user->id, 'event_update', 'daily', 'Move me monthly');
        $messageQueueId = $this->enqueue($user->id, 'new_message', 'daily', 'Keep me daily');

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);
        $this->invokeCronMethod('processDigest', ['daily']);

        $this->assertCount(1, $mailer->calls);
        $this->assertDatabaseHas('notification_queue', [
            'id' => $eventQueueId,
            'status' => 'pending',
            'frequency' => 'monthly',
            'processing_batch_id' => null,
        ]);
        $this->assertDatabaseHas('notification_queue', ['id' => $messageQueueId, 'status' => 'sent']);
    }

    public function test_mixed_digest_contains_visible_events_only_unsubscribe_action(): void
    {
        DB::table('notification_queue')->delete();
        $user = $this->member();
        $this->setPreferences($user->id, ['email_events' => true, 'email_digest' => true]);
        $this->setFrequency($user->id, 'daily');
        $this->enqueue($user->id, 'event_update', 'daily', 'Event item');
        $this->enqueue($user->id, 'new_message', 'daily', 'Message item');

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);
        $this->invokeCronMethod('processDigest', ['daily']);

        $this->assertCount(1, $mailer->calls);
        $this->assertStringContainsString(__('emails.digest.unsubscribe_events'), $mailer->calls[0]['body']);
        $this->assertStringContainsString(
            htmlspecialchars(EventNotificationPreferenceResolver::unsubscribeUrl($user->id, $this->testTenantId), ENT_QUOTES, 'UTF-8'),
            $mailer->calls[0]['body'],
        );
        $this->assertTokenCategory((string) $mailer->calls[0]['options']['unsubscribeUrl'], 'digest');
    }

    public function test_disabled_events_feature_suppresses_routine_queue_rows_but_allows_cancellation_and_reenable(): void
    {
        DB::table('notification_queue')->delete();
        $user = $this->member();
        $this->setPreferences($user->id, ['email_events' => true, 'email_digest' => true]);
        $this->setFrequency($user->id, 'daily');
        $this->setEventsFeature(false);
        $routineId = $this->enqueue($user->id, 'event_update', 'daily', 'Routine event update');
        $cancellationId = $this->enqueue($user->id, 'event_cancellation', 'daily', 'Safety cancellation');
        $this->enqueue($user->id, 'new_message', 'daily', 'Unrelated message');

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);
        $this->invokeCronMethod('processDigest', ['daily']);

        $this->assertDatabaseHas('notification_queue', ['id' => $routineId, 'status' => 'suppressed']);
        $this->assertDatabaseHas('notification_queue', ['id' => $cancellationId, 'status' => 'sent']);
        $this->assertStringContainsString('Safety cancellation', $mailer->calls[0]['body']);

        $this->setEventsFeature(true);
        $reenabledId = $this->enqueue($user->id, 'event_update', 'daily', 'Events are back');
        $this->invokeCronMethod('processDigest', ['daily']);

        $this->assertDatabaseHas('notification_queue', ['id' => $reenabledId, 'status' => 'sent']);
    }

    public function test_all_and_federation_unsubscribe_categories_remain_scoped(): void
    {
        $allUser = $this->member();
        $this->setPreferences($allUser->id, ['email_events' => true, 'email_messages' => true]);
        $this->unsubscribeCategory($allUser->id, 'all');
        $allPreferences = $this->storedPreferences($allUser->id);
        $this->assertFalse((bool) $allPreferences['email_events']);
        $this->assertFalse((bool) $allPreferences['email_messages']);

        $federationUser = $this->member();
        $this->setPreferences($federationUser->id, ['email_events' => true, 'email_messages' => true]);
        $this->unsubscribeCategory($federationUser->id, 'federation');
        $federationPreferences = $this->storedPreferences($federationUser->id);
        $this->assertTrue((bool) $federationPreferences['email_events']);
        $this->assertTrue((bool) $federationPreferences['email_messages']);
        $this->assertSame(0, (int) DB::table('users')
            ->where('id', $federationUser->id)
            ->where('tenant_id', $this->testTenantId)
            ->value('federation_notifications_enabled'));
    }

    public function test_lifecycle_email_opt_out_keeps_bell_and_explicit_url_is_category_scoped(): void
    {
        $optedOut = $this->member();
        $enabled = $this->member();
        $this->setPreferences($optedOut->id, ['email_events' => false]);
        $this->setPreferences($enabled->id, ['email_events' => true]);

        foreach ([$optedOut, $enabled] as $user) {
            DB::table('notification_settings')->insert([
                'user_id' => $user->id,
                'context_type' => 'global',
                'context_id' => 0,
                'frequency' => 'instant',
            ]);
        }

        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);
        $event = (object) [
            'id' => 987654,
            'title' => 'Preference compliance event',
            'start_time' => now()->addDay()->toDateTimeString(),
            'location' => 'Community Hall',
        ];

        $count = (new EventNotificationService())->notifyCancellation(
            $this->testTenantId,
            $event->id,
            null,
            [$optedOut->id, $enabled->id],
            $event,
        );

        $this->assertSame(2, $count);
        $this->assertCount(1, $mailer->calls);
        $this->assertSame($enabled->email, $mailer->calls[0]['to']);
        $this->assertSame('event_cancellation', $mailer->calls[0]['options']['category']);
        $this->assertEventsCategoryToken((string) $mailer->calls[0]['options']['unsubscribeUrl']);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $optedOut->id,
            'type' => 'event',
        ]);
    }

    private function member(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'preferred_language' => 'en',
        ]);
    }

    /** @param array<string, bool> $preferences */
    private function setPreferences(int $userId, array $preferences): void
    {
        DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $this->testTenantId)
            ->update(['notification_preferences' => json_encode($preferences)]);
    }

    /** @return array<string, mixed> */
    private function storedPreferences(int $userId): array
    {
        $raw = DB::table('users')->where('id', $userId)->value('notification_preferences');

        return json_decode((string) $raw, true) ?: [];
    }

    private function assertEventsCategoryToken(string $url): void
    {
        $this->assertTokenCategory($url, 'events');
    }

    private function assertTokenCategory(string $url, string $category): void
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $token = strtr((string) ($query['token'] ?? ''), '-_', '+/');
        $decoded = base64_decode($token, true);

        $this->assertIsString($decoded);
        $this->assertStringContainsString('.' . $category . '.', $decoded);
    }

    private function unsubscribeEvents(int $userId): void
    {
        $this->unsubscribeCategory($userId, 'events');
    }

    private function unsubscribeCategory(int $userId, string $category): void
    {
        $url = NotificationUnsubscribeController::buildSignedUrl($userId, $this->testTenantId, $category);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->apiPost('/v2/notifications/unsubscribe?token=' . rawurlencode((string) ($query['token'] ?? '')), [])
            ->assertStatus(200);
    }

    private function setFrequency(int $userId, string $frequency): void
    {
        DB::table('notification_settings')->updateOrInsert(
            ['user_id' => $userId, 'context_type' => 'global', 'context_id' => 0],
            ['frequency' => $frequency],
        );
    }

    private function setEventsFeature(bool $enabled): void
    {
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode(array_merge(
                TenantFeatureConfig::FEATURE_DEFAULTS,
                ['events' => $enabled],
            ), JSON_THROW_ON_ERROR)]);
        TenantContext::setById($this->testTenantId);
    }

    private function enqueue(int $userId, string $activityType, string $frequency, string $content): int
    {
        return (int) DB::table('notification_queue')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'activity_type' => $activityType,
            'content_snippet' => $content,
            'link' => '/notifications',
            'frequency' => $frequency,
            'email_body' => '<p>' . $content . '</p>',
            'status' => 'pending',
            'created_at' => now(),
        ]);
    }

    /** @param list<mixed> $arguments */
    private function invokeCronMethod(string $methodName, array $arguments = []): void
    {
        $method = new \ReflectionMethod(CronJobRunner::class, $methodName);
        $method->setAccessible(true);
        ob_start();
        try {
            $method->invokeArgs(new CronJobRunner(), $arguments);
        } finally {
            ob_end_clean();
        }
    }

    private function fakeMailer(): EmailDispatchService
    {
        return new class extends EmailDispatchService {
            /** @var list<array{to:string,subject:string,body:string,options:array<string,mixed>}> */
            public array $calls = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return true;
            }
        };
    }
}
