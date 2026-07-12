<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Models;

use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;
use App\Models\Event;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class EventLifecycleModelTest extends TestCase
{
    public function test_lifecycle_state_is_cast_but_not_mass_assignable(): void
    {
        $event = new Event();
        $event->setAttribute('publication_status', 'pending_review');
        $event->setAttribute('operational_status', 'scheduled');

        self::assertSame(EventPublicationState::PendingReview, $event->publication_status);
        self::assertSame(EventOperationalState::Scheduled, $event->operational_status);
        foreach ([
            'publication_status',
            'operational_status',
            'lifecycle_version',
            'publication_status_changed_by',
            'operational_status_changed_by',
            'moderated_by',
        ] as $attribute) {
            self::assertNotContains($attribute, $event->getFillable());
        }
    }

    public function test_sensitive_lifecycle_metadata_is_hidden_from_direct_serialization(): void
    {
        $event = new Event();

        foreach ([
            'publication_status_changed_by',
            'operational_status_changed_by',
            'moderation_submitted_by',
            'moderated_by',
            'moderation_reason',
            'lifecycle_reason',
        ] as $attribute) {
            self::assertContains($attribute, $event->getHidden());
        }
    }

    public function test_event_exposes_typed_status_history_relation(): void
    {
        $method = new ReflectionMethod(Event::class, 'statusHistory');

        self::assertSame(HasMany::class, (string) $method->getReturnType());
    }
}
