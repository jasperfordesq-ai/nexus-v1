<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Integration;

use App\Services\EmailDispatchService;
use App\Services\EventNotificationService;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class EventNotificationProducerLocaleTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 996;

    private $emailAlias;
    private $webPushAlias;

    protected function setUp(): void
    {
        $this->emailAlias = Mockery::mock('alias:' . EmailDispatchService::class)->shouldIgnoreMissing();
        $this->webPushAlias = Mockery::mock('alias:' . WebPushService::class)->shouldIgnoreMissing();

        parent::setUp();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name' => 'Locale Event Tenant',
                'slug' => 'locale-event-996',
                'domain' => null,
                'features' => json_encode(['events' => true]),
                'is_active' => true,
                'depth' => 0,
                'allows_subtenants' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function test_initial_attendee_bell_subject_and_email_body_use_recipient_locale(): void
    {
        App::setLocale('en');
        $organizer = $this->seedUser('organizer', 'en');
        $attendee = $this->seedUser('attendee', 'de');
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $organizer->id,
            'title' => 'Community Workshop',
            'description' => 'A workshop.',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => self::TENANT_ID,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $emails = [];
        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->twice()
            ->andReturnUsing(function (...$args) use (&$emails): bool {
                $emails[(string) $args[0]] = [
                    'subject' => (string) $args[1],
                    'body' => (string) $args[2],
                ];
                return true;
            });
        $this->webPushAlias->shouldReceive('sendToUserStatic')->twice()->andReturn(true);

        (new EventNotificationService())->notifyEventCreated(
            self::TENANT_ID,
            $eventId,
            $organizer->id,
            true,
        );

        $expected = 'Ihre Teilnahme an Community Workshop ist bestätigt';
        $attendeeBell = DB::table('notifications')
            ->where('tenant_id', self::TENANT_ID)
            ->where('user_id', $attendee->id)
            ->where('type', 'event_created')
            ->first();
        $this->assertNotNull($attendeeBell);
        $this->assertSame($expected, $attendeeBell->message);
        $this->assertSame($expected, $emails[$attendee->email]['subject']);
        $this->assertStringContainsString($expected, $emails[$attendee->email]['body']);
        $this->assertStringNotContainsString('You are confirmed', $emails[$attendee->email]['body']);
        $this->assertSame('en', App::getLocale(), 'LocaleContext must restore the caller locale.');
    }

    private function seedUser(string $suffix, string $locale): object
    {
        $email = $suffix . '-' . uniqid('', true) . '@example.com';
        $id = (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'name' => ucfirst($suffix) . ' User',
            'first_name' => ucfirst($suffix),
            'last_name' => 'User',
            'email' => $email,
            'role' => 'member',
            'status' => 'active',
            'preferred_language' => $locale,
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('notification_settings')->insert([
            'user_id' => $id,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'instant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['id' => $id, 'email' => $email];
    }
}
