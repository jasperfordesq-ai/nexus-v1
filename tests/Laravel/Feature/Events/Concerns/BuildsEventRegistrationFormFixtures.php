<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events\Concerns;

use App\Core\TenantContext;
use App\Models\EventRegistrationFormVersion;
use App\Models\EventRegistrationSettings;
use App\Models\User;
use App\Services\EventRegistrationFormService;
use App\Services\EventRegistrationSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

trait BuildsEventRegistrationFormFixtures
{
    protected function eventUser(array $overrides = [], int $tenantId = 2): User
    {
        $user = User::factory()->forTenant($tenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
        TenantContext::setById($this->testTenantId);

        return $user;
    }

    /** @return array{0:int,1:CarbonImmutable,2:CarbonImmutable} */
    protected function registrationEvent(
        int $ownerId,
        ?CarbonImmutable $start = null,
        ?CarbonImmutable $end = null,
        string $timezone = 'UTC',
        int $tenantId = 2,
        bool $template = false,
    ): array {
        $start ??= CarbonImmutable::now($timezone)->addMonth()->startOfHour();
        $end ??= $start->addHours(3);
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => 'Registration foundation fixture',
            'description' => 'Registration foundation fixture.',
            'start_time' => $start->utc(),
            'end_time' => $end->utc(),
            'timezone' => $timezone,
            'timezone_source' => 'test',
            'all_day' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => $template,
            'occurrence_key' => $template
                ? null
                : 'test:event:' . bin2hex(random_bytes(12)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$eventId, $start, $end];
    }

    protected function canonicalRegistration(
        int $eventId,
        int $userId,
        string $state = 'confirmed',
    ): int {
        $now = CarbonImmutable::now('UTC');

        return (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $userId,
            'capacity_pool_key' => 'event',
            'allocation_key' => null,
            'registration_state' => $state,
            'registration_version' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => $userId,
            'invited_at' => $state === 'invited' ? $now : null,
            'pending_at' => $state === 'pending' ? $now : null,
            'confirmed_at' => $state === 'confirmed' ? $now : null,
            'declined_at' => $state === 'declined' ? $now : null,
            'cancelled_at' => $state === 'cancelled' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function registrationSettings(
        int $eventId,
        User $owner,
        CarbonImmutable $start,
        bool $guestsEnabled = false,
        int $maxGuests = 0,
        int $guestRetentionDays = 30,
    ): EventRegistrationSettings {
        $timezone = $start->getTimezone()->getName();
        $settings = (new EventRegistrationSettingsService())->save(
            $eventId,
            $owner,
            [
                'approval_mode' => 'manual',
                'opens_at' => $start->subDays(20)->format('Y-m-d\TH:i:sP'),
                'closes_at' => $start->format('Y-m-d\TH:i:sP'),
                'cancellation_cutoff_at' => $start->format('Y-m-d\TH:i:sP'),
                'per_member_limit' => 1,
                'guests_enabled' => $guestsEnabled,
                'max_guests_per_registration' => $maxGuests,
                'guest_retention_days' => $guestRetentionDays,
            ],
            null,
            'settings-create-' . bin2hex(random_bytes(8)),
        );
        $published = (new EventRegistrationSettingsService())->publish(
            $eventId,
            $owner,
            (int) $settings['settings']->revision,
            'settings-publish-' . bin2hex(random_bytes(8)),
        );
        $expectedTimezone = in_array($timezone, ['Z', '+00:00', 'GMT'], true)
            ? 'UTC'
            : $timezone;
        self::assertSame(
            $expectedTimezone,
            (string) $published['settings']->event_timezone_snapshot,
        );

        return $published['settings'];
    }

    /** @param list<array<string,mixed>>|null $questions */
    protected function publishedRegistrationForm(
        int $eventId,
        User $owner,
        EventRegistrationSettings $settings,
        ?array $questions = null,
    ): EventRegistrationFormVersion {
        $forms = new EventRegistrationFormService();
        $draft = $forms->createDraft(
            $eventId,
            $owner,
            'Registration details',
            null,
            $questions ?? $this->standardRegistrationQuestions(),
            (int) $settings->revision,
            'form-create-' . bin2hex(random_bytes(8)),
        );
        $published = $forms->publish(
            $eventId,
            (int) $draft['form']->id,
            $owner,
            (int) $draft['form']->revision,
            $draft['settings_revision'],
            'form-publish-' . bin2hex(random_bytes(8)),
        );

        return $published['form'];
    }

    /** @return list<array<string,mixed>> */
    protected function standardRegistrationQuestions(int $retentionDays = 30): array
    {
        return [
            [
                'stable_key' => 'display_name',
                'question_type' => 'short_text',
                'prompt' => 'Preferred name',
                'is_required' => true,
                'data_classification' => 'internal',
                'purpose' => 'Event administration',
                'retention_days' => $retentionDays,
            ],
            [
                'stable_key' => 'dietary_needs',
                'question_type' => 'dietary',
                'prompt' => 'Dietary requirements',
                'is_required' => false,
                'data_classification' => 'sensitive',
                'purpose' => 'Safe catering',
                'retention_days' => $retentionDays,
            ],
            [
                'stable_key' => 'meal_choice',
                'question_type' => 'single_choice',
                'prompt' => 'Meal choice',
                'is_required' => true,
                'data_classification' => 'internal',
                'purpose' => 'Catering planning',
                'retention_days' => $retentionDays,
                'choice_options' => ['Plant-based', 'Standard'],
            ],
            [
                'stable_key' => 'waiver',
                'question_type' => 'waiver',
                'prompt' => 'Safety waiver',
                'is_required' => true,
                'data_classification' => 'confidential',
                'purpose' => 'Record participation consent',
                'retention_days' => $retentionDays,
                'displayed_text' => 'I accept the event safety terms.',
                'displayed_text_version' => 'safety-2026-01',
            ],
        ];
    }
}
