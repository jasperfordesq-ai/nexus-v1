<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Enums\EventRegistrationSettingsStatus;
use App\Services\EventRegistrationSettingsService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

final class EventRegistrationSettingsOutboxContractTest extends TestCase
{
    /** @return iterable<string,array{?stdClass,?CarbonImmutable,bool}> */
    public static function policyChanges(): iterable
    {
        yield 'published cutoff moved' => [
            self::settings('published', '2030-05-01 09:00:00'),
            CarbonImmutable::parse('2030-05-01 08:00:00', 'UTC'),
            true,
        ];
        yield 'published cutoff removed' => [
            self::settings('published', '2030-05-01 09:00:00'),
            null,
            true,
        ];
        yield 'same instant in another offset' => [
            self::settings('published', '2030-05-01 09:00:00'),
            CarbonImmutable::parse('2030-05-01T10:00:00+01:00'),
            false,
        ];
        yield 'draft policy is not participant-visible' => [
            self::settings('draft', '2030-05-01 09:00:00'),
            CarbonImmutable::parse('2030-05-01 08:00:00', 'UTC'),
            false,
        ];
        yield 'initial settings are not an update' => [
            null,
            CarbonImmutable::parse('2030-05-01 08:00:00', 'UTC'),
            false,
        ];
    }

    #[DataProvider('policyChanges')]
    public function test_only_material_changes_to_published_policy_emit_an_update_fact(
        ?stdClass $settings,
        ?CarbonImmutable $next,
        bool $expected,
    ): void {
        $service = new EventRegistrationSettingsService();
        $method = new ReflectionMethod($service, 'publishedCancellationPolicyChanged');

        self::assertSame($expected, $method->invoke($service, $settings, $next));
    }

    private static function settings(string $status, ?string $cutoff): stdClass
    {
        return (object) [
            'status' => $status === 'published'
                ? EventRegistrationSettingsStatus::Published->value
                : $status,
            'cancellation_cutoff_at_utc' => $cutoff,
        ];
    }
}
