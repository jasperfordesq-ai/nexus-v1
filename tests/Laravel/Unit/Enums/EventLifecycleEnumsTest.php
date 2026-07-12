<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Enums;

use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;
use App\Support\Events\EventLifecycleCompatibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class EventLifecycleEnumsTest extends TestCase
{
    public function test_publication_transition_graph_is_frozen_and_exhaustive(): void
    {
        self::assertSame([
            'draft' => ['pending_review', 'published', 'archived'],
            'pending_review' => ['draft', 'published', 'archived'],
            'published' => ['archived'],
            'archived' => ['draft'],
        ], $this->publicationGraph());
    }

    public function test_operational_transition_graph_is_frozen_and_exhaustive(): void
    {
        self::assertSame([
            'scheduled' => ['postponed', 'cancelled', 'completed'],
            'postponed' => ['scheduled', 'cancelled'],
            'cancelled' => ['scheduled'],
            'completed' => [],
        ], $this->operationalGraph());
    }

    /** @return iterable<string, array{?string, string, string}> */
    public static function legacyMappings(): iterable
    {
        yield 'null compatibility state' => [null, 'published', 'scheduled'];
        yield 'blank compatibility state' => ['', 'published', 'scheduled'];
        yield 'whitespace compatibility state' => ['  ', 'published', 'scheduled'];
        yield 'active' => ['active', 'published', 'scheduled'];
        yield 'draft' => ['draft', 'draft', 'scheduled'];
        yield 'cancelled' => ['cancelled', 'published', 'cancelled'];
        yield 'completed' => ['completed', 'published', 'completed'];
    }

    #[DataProvider('legacyMappings')]
    public function test_legacy_mapping_is_deterministic(
        ?string $legacy,
        string $publication,
        string $operational,
    ): void {
        self::assertSame($publication, EventPublicationState::fromLegacyStatus($legacy)->value);
        self::assertSame($operational, EventOperationalState::fromLegacyStatus($legacy)->value);
        self::assertSame(
            $legacy === null || trim($legacy) === '' ? 'active' : strtolower(trim($legacy)),
            EventLifecycleCompatibility::legacyMirror(
                EventPublicationState::fromLegacyStatus($legacy),
                EventOperationalState::fromLegacyStatus($legacy),
            ),
        );
    }

    public function test_unknown_legacy_status_fails_closed(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('event_lifecycle_unknown_legacy_status');

        EventPublicationState::fromLegacyStatus('unexpected');
    }

    public function test_unknown_or_conflicting_canonical_state_fails_closed(): void
    {
        foreach ([
            ['unexpected', 'scheduled', 'active'],
            ['published', 'unexpected', 'active'],
            ['published', 'scheduled', 'cancelled'],
            ['draft', 'postponed', 'draft'],
        ] as [$publication, $operational, $legacy]) {
            try {
                EventLifecycleCompatibility::resolve($publication, $operational, $legacy);
                self::fail('Invalid lifecycle storage was accepted.');
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** @return array<string, list<string>> */
    private function publicationGraph(): array
    {
        $graph = [];
        foreach (EventPublicationState::cases() as $state) {
            $graph[$state->value] = array_map(
                static fn (EventPublicationState $target): string => $target->value,
                $state->allowedTransitions(),
            );
        }

        return $graph;
    }

    /** @return array<string, list<string>> */
    private function operationalGraph(): array
    {
        $graph = [];
        foreach (EventOperationalState::cases() as $state) {
            $graph[$state->value] = array_map(
                static fn (EventOperationalState $target): string => $target->value,
                $state->allowedTransitions(),
            );
        }

        return $graph;
    }
}
