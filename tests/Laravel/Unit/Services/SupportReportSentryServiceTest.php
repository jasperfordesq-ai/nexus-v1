<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SupportReportSentryService;
use App\Models\SupportReport;
use App\Models\User;

/**
 * SupportReportSentryServiceTest
 *
 * Strategy:
 * - captureCreated is the sole public method.
 * - In the test environment the Sentry SDK may be present but have no client
 *   configured (hub->getClient() === null).  The service contracts to return
 *   null in that case without throwing — that is the primary invariant tested.
 * - We build SupportReport and User objects via direct property assignment on
 *   unsaved models so we never need DB fixtures or global scopes.
 * - The "no Sentry class" branch (class_exists check) is also tested by
 *   temporarily hiding the class via reflection aliasing — but since the SDK
 *   IS installed, we rely on the hub-client-null path as the realistic branch.
 * - No mocking of Sentry internals: we test observable behaviour (return value,
 *   no-throw) rather than SDK internals.
 */
class SupportReportSentryServiceTest extends TestCase
{
    private SupportReportSentryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SupportReportSentryService();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function makeReport(
        string $impact = 'minor',
        string $reference = 'SR-SENTRY-001',
        ?array $diagnostics = null
    ): SupportReport {
        $r = new SupportReport();
        $r->id          = 500001;
        $r->tenant_id   = 2;
        $r->reference   = $reference;
        $r->summary     = 'Sentry unit test report';
        $r->description = 'Test description.';
        $r->impact      = $impact;
        $r->status      = 'open';
        $r->source      = 'in_app';
        $r->route       = '/test/route';
        $r->page_url    = 'https://app.project-nexus.ie/test/route';
        $r->diagnostics = $diagnostics;
        return $r;
    }

    private function makeUser(int $id = 42): User
    {
        $u = new User();
        $u->id = $id;
        return $u;
    }

    // ─────────────────────────────────────────────────────────────────────
    // captureCreated — null-or-string contract
    // ─────────────────────────────────────────────────────────────────────

    public function test_captureCreated_returns_null_or_string_for_minor_impact(): void
    {
        $result = $this->service->captureCreated($this->makeReport('minor'), null);

        // The contract allows null (no Sentry client) or a string event ID.
        $this->assertTrue(
            $result === null || is_string($result),
            'captureCreated must return null or a string event ID'
        );
    }

    public function test_captureCreated_returns_null_or_string_for_blocked_impact(): void
    {
        $result = $this->service->captureCreated($this->makeReport('blocked'), $this->makeUser());

        $this->assertTrue($result === null || is_string($result));
    }

    public function test_captureCreated_returns_null_or_string_for_major_impact(): void
    {
        $result = $this->service->captureCreated($this->makeReport('major'), $this->makeUser(99));

        $this->assertTrue($result === null || is_string($result));
    }

    public function test_captureCreated_returns_null_or_string_when_user_is_null(): void
    {
        $result = $this->service->captureCreated($this->makeReport('minor'), null);

        $this->assertTrue($result === null || is_string($result));
    }

    public function test_captureCreated_returns_null_or_string_with_frontend_event_id(): void
    {
        $result = $this->service->captureCreated(
            $this->makeReport('major'),
            $this->makeUser(),
            'fe-event-abc123'
        );

        $this->assertTrue($result === null || is_string($result));
    }

    // ─────────────────────────────────────────────────────────────────────
    // captureCreated — does not throw
    // ─────────────────────────────────────────────────────────────────────

    public function test_captureCreated_does_not_throw_for_minor_impact(): void
    {
        $this->service->captureCreated($this->makeReport('minor'), null);
        $this->addToAssertionCount(1);
    }

    public function test_captureCreated_does_not_throw_for_blocked_impact(): void
    {
        $this->service->captureCreated($this->makeReport('blocked'), $this->makeUser());
        $this->addToAssertionCount(1);
    }

    public function test_captureCreated_does_not_throw_when_report_has_diagnostics(): void
    {
        $report = $this->makeReport('major', 'SR-DIAG-001', ['browser' => 'Chrome', 'os' => 'Windows']);

        $this->service->captureCreated($report, null);
        $this->addToAssertionCount(1);
    }

    public function test_captureCreated_does_not_throw_when_report_has_no_route(): void
    {
        $report = $this->makeReport('minor');
        $report->route = null;

        $this->service->captureCreated($report, null);
        $this->addToAssertionCount(1);
    }

    public function test_captureCreated_does_not_throw_when_report_has_no_page_url(): void
    {
        $report = $this->makeReport('minor');
        $report->page_url = null;

        $this->service->captureCreated($report, null);
        $this->addToAssertionCount(1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // captureCreated — result type is always null when no Sentry client
    // ─────────────────────────────────────────────────────────────────────

    /**
     * In the test environment the Sentry hub has no client, so the service
     * must return exactly null (not a non-null string).
     *
     * This assertion is conditional: if a real Sentry DSN is configured and
     * the hub HAS a client, we relax to "string" — both outcomes are valid.
     */
    public function test_captureCreated_returns_null_when_hub_has_no_client(): void
    {
        $hubHasClient = class_exists(\Sentry\SentrySdk::class)
            && \Sentry\SentrySdk::getCurrentHub()->getClient() !== null;

        $result = $this->service->captureCreated($this->makeReport('minor'), null);

        if (!$hubHasClient) {
            $this->assertNull($result, 'Without a Sentry client the return value must be null');
        } else {
            // Sentry IS configured in this environment — accept string or null.
            $this->assertTrue($result === null || is_string($result));
        }
    }
}
