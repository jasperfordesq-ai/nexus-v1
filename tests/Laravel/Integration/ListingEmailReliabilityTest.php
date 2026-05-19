<?php
// Copyright Â© 2024â€“2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\Listing;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\ListingExpiryReminderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ListingEmailReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_listing_expiry_reminder_retries_without_duplicate_bell_after_email_failure(): void
    {
        TenantContext::setById($this->testTenantId);
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'listing-reminder-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
            'title' => 'Garden Help',
            'status' => 'active',
            'expires_at' => now()->addDays(6),
        ]);

        TenantContext::setById($this->testTenantId);
        $mailer = $this->fakeMailerSequence([false, true]);
        app()->instance(EmailDispatchService::class, $mailer);

        $failed = (new ListingExpiryReminderService())->sendDueReminders();

        $this->assertSame(['sent' => 0, 'errors' => 0], $failed);
        $this->assertCount(1, $mailer->calls);
        $this->assertSame(0, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $owner->id)
            ->where('type', 'listing_expiry')
            ->where('link', "/listings/{$listing->id}")
            ->count());
        $this->assertDatabaseMissing('listing_expiry_reminders_sent', [
            'tenant_id' => $this->testTenantId,
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'days_before_expiry' => 7,
        ]);

        TenantContext::setById($this->testTenantId);
        $retried = (new ListingExpiryReminderService())->sendDueReminders();

        $this->assertSame(['sent' => 1, 'errors' => 0], $retried);
        $this->assertCount(2, $mailer->calls);
        $this->assertSame(1, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $owner->id)
            ->where('type', 'listing_expiry')
            ->where('link', "/listings/{$listing->id}")
            ->count());
        $this->assertDatabaseHas('listing_expiry_reminders_sent', [
            'tenant_id' => $this->testTenantId,
            'listing_id' => $listing->id,
            'user_id' => $owner->id,
            'days_before_expiry' => 7,
        ]);
    }

    /**
     * @param list<bool> $results
     */
    private function fakeMailerSequence(array $results): EmailDispatchService
    {
        return new class($results) extends EmailDispatchService {
            /** @var list<array{to:string,subject:string,body:string,options:array<string,mixed>}> */
            public array $calls = [];
            private int $index = 0;

            /**
             * @param list<bool> $results
             */
            public function __construct(private readonly array $results)
            {
            }

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = [
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'options' => $options,
                ];

                $result = $this->results[$this->index] ?? true;
                $this->index++;

                return $result;
            }
        };
    }
}
