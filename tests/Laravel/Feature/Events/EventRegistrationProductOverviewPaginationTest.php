<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Services\EventInvitationCampaignService;
use App\Services\EventRegistrationGuestService;
use App\Services\EventRegistrationProductQueryService;
use App\Services\EventRegistrationSubmissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventRegistrationFormFixtures;
use Tests\Laravel\TestCase;

final class EventRegistrationProductOverviewPaginationTest extends TestCase
{
    use BuildsEventRegistrationFormFixtures;
    use DatabaseTransactions;

    public function test_organizer_overview_pages_every_large_collection_and_reports_truthful_totals(): void
    {
        $owner = $this->eventUser();
        [$eventId, $start] = $this->registrationEvent((int) $owner->id);
        $settings = $this->registrationSettings($eventId, $owner, $start, true, 2, 30);
        $form = $this->publishedRegistrationForm($eventId, $owner, $settings);
        $submissionIds = [];
        $guestIds = [];
        $submissions = new EventRegistrationSubmissionService();
        $guests = new EventRegistrationGuestService();
        $campaigns = new EventInvitationCampaignService();

        for ($index = 1; $index <= 3; $index++) {
            $member = $this->eventUser();
            $registrationId = $this->canonicalRegistration($eventId, (int) $member->id);
            $submission = $submissions->saveDraft(
                $eventId,
                $registrationId,
                (int) $form->id,
                $member,
                [
                    'display_name' => "Member {$index}",
                    'meal_choice' => 'Plant-based',
                    'waiver' => true,
                ],
                null,
                "registration-overview-submission-{$index}",
            );
            $submissionIds[] = (int) $submission['submission']->id;
            $guest = $guests->capture(
                $eventId,
                $registrationId,
                $member,
                1,
                "Guest {$index}",
                null,
                null,
                true,
                'Guest privacy notice',
                'guest-notice-v1',
            );
            $guestIds[] = (int) $guest['guest']->id;
            $campaigns->preview(
                $eventId,
                $owner,
                'member',
                ['member_ids' => [(int) $member->id]],
                "registration-overview-campaign-{$index}",
                'en',
            );
        }

        $queries = new EventRegistrationProductQueryService();
        $first = $queries->organizerOverview($eventId, $owner, [
            'submissions_per_page' => 2,
            'campaigns_per_page' => 2,
            'guests_per_page' => 2,
        ]);
        self::assertCount(2, $first['submissions']);
        self::assertCount(2, $first['campaigns']);
        self::assertCount(2, $first['guests']);
        self::assertSame([
            'submissions_total' => 3,
            'campaigns_total' => 3,
            'guests_total' => 3,
        ], $first['summary']);
        foreach (['submissions', 'campaigns', 'guests'] as $collection) {
            self::assertSame(1, $first['pagination'][$collection]['page']);
            self::assertSame(2, $first['pagination'][$collection]['per_page']);
            self::assertSame(3, $first['pagination'][$collection]['total']);
            self::assertSame(2, $first['pagination'][$collection]['page_count']);
            self::assertTrue($first['pagination'][$collection]['has_more']);
            self::assertSame(2, $first['pagination'][$collection]['next_page']);
        }

        $second = $queries->organizerOverview($eventId, $owner, [
            'submissions_page' => 2,
            'submissions_per_page' => 2,
            'campaigns_page' => 2,
            'campaigns_per_page' => 2,
            'guests_page' => 2,
            'guests_per_page' => 2,
        ]);
        foreach (['submissions', 'campaigns', 'guests'] as $collection) {
            self::assertCount(1, $second[$collection]);
            self::assertSame(2, $second['pagination'][$collection]['page']);
            self::assertSame(1, $second['pagination'][$collection]['page_count']);
            self::assertFalse($second['pagination'][$collection]['has_more']);
            self::assertSame(1, $second['pagination'][$collection]['previous_page']);
            self::assertNull($second['pagination'][$collection]['next_page']);
        }
        self::assertSame(
            [],
            array_values(array_intersect(
                array_column($first['submissions'], 'id'),
                array_column($second['submissions'], 'id'),
            )),
        );
        self::assertSame(
            [],
            array_values(array_intersect(
                array_column($first['guests'], 'id'),
                array_column($second['guests'], 'id'),
            )),
        );
        self::assertEqualsCanonicalizing($submissionIds, [
            ...array_column($first['submissions'], 'id'),
            ...array_column($second['submissions'], 'id'),
        ]);
        self::assertEqualsCanonicalizing($guestIds, [
            ...array_column($first['guests'], 'id'),
            ...array_column($second['guests'], 'id'),
        ]);

        $bounded = $queries->organizerOverview($eventId, $owner, ['submissions_per_page' => 5000]);
        self::assertSame(100, $bounded['pagination']['submissions']['per_page']);

        Sanctum::actingAs($owner, ['*']);
        $response = $this->apiGet(
            "/v2/events/{$eventId}/registration-product/manage?submissions_page=2&submissions_per_page=2",
        );
        $response
            ->assertOk()
            ->assertJsonPath('data.pagination.submissions.page', 2)
            ->assertJsonPath('data.pagination.submissions.total', 3)
            ->assertJsonCount(1, 'data.submissions');
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->apiGet("/v2/events/{$eventId}/registration-product/manage?guests_page=0")
            ->assertStatus(422);
    }
}
