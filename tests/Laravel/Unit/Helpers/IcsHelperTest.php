<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Helpers;

use App\Helpers\IcsHelper;
use PHPUnit\Framework\TestCase;

class IcsHelperTest extends TestCase
{
    // -------------------------------------------------------
    // generate()
    // -------------------------------------------------------

    public function test_generate_returns_valid_ics_structure(): void
    {
        $ics = IcsHelper::generate(
            'Community Meeting',
            'Monthly gathering',
            'Town Hall',
            '2026-03-25 10:00:00',
            '2026-03-25 12:00:00'
        );

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
    }

    public function test_generate_includes_summary(): void
    {
        $ics = IcsHelper::generate('My Event', 'Desc', 'Loc', '2026-01-01 09:00:00', '2026-01-01 10:00:00');
        $this->assertStringContainsString('SUMMARY:My Event', $ics);
    }

    public function test_generate_includes_location(): void
    {
        $ics = IcsHelper::generate('Event', 'Desc', 'Main Street', '2026-01-01 09:00:00', '2026-01-01 10:00:00');
        $this->assertStringContainsString('LOCATION:Main Street', $ics);
    }

    public function test_generate_includes_version(): void
    {
        $ics = IcsHelper::generate('Event', 'Desc', 'Loc', '2026-01-01 09:00:00', '2026-01-01 10:00:00');
        $this->assertStringContainsString('VERSION:2.0', $ics);
    }

    public function test_generate_includes_prodid(): void
    {
        $ics = IcsHelper::generate('Event', 'Desc', 'Loc', '2026-01-01 09:00:00', '2026-01-01 10:00:00');
        $this->assertStringContainsString('PRODID:-//Nexus//Timebank Platform//EN', $ics);
    }

    public function test_generate_includes_uid(): void
    {
        $ics = IcsHelper::generate('Event', 'Desc', 'Loc', '2026-01-01 09:00:00', '2026-01-01 10:00:00');
        $this->assertStringContainsString('UID:', $ics);
        $this->assertStringContainsString('@nexus-timebank', $ics);
    }

    public function test_generate_includes_status_confirmed(): void
    {
        $ics = IcsHelper::generate('Event', 'Desc', 'Loc', '2026-01-01 09:00:00', '2026-01-01 10:00:00');
        $this->assertStringContainsString('STATUS:CONFIRMED', $ics);
    }

    public function test_generate_formats_dates_in_utc(): void
    {
        $ics = IcsHelper::generate('Event', 'Desc', 'Loc', '2026-06-15 14:00:00', '2026-06-15 16:00:00');
        // UTC format: YYYYMMDDTHHmmSSZ
        $this->assertMatchesRegularExpression('/DTSTART:\d{8}T\d{6}Z/', $ics);
        $this->assertMatchesRegularExpression('/DTEND:\d{8}T\d{6}Z/', $ics);
    }

    public function test_generate_escapes_special_characters(): void
    {
        $ics = IcsHelper::generate(
            'Event; with, special chars',
            "Description\nwith newlines",
            'Location; test',
            '2026-01-01 09:00:00',
            '2026-01-01 10:00:00'
        );
        // Semicolons should be escaped as \;
        $this->assertStringContainsString('\\;', $ics);
        // Commas should be escaped as \,
        $this->assertStringContainsString('\\,', $ics);
    }
}
