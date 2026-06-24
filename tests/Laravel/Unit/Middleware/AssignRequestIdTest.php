<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Http\Request;
use Tests\Laravel\TestCase;

class AssignRequestIdTest extends TestCase
{
    private AssignRequestId $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AssignRequestId();
    }

    private function makeNext(): \Closure
    {
        return function ($request) {
            return response('ok', 200);
        };
    }

    // -----------------------------------------------------------------------
    // A request id is always present on the response
    // -----------------------------------------------------------------------

    public function test_assigns_x_request_id_header_to_response(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertNotNull($response->headers->get(AssignRequestId::HEADER));
    }

    public function test_assigns_uuid_when_no_header_provided(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $id = $response->headers->get(AssignRequestId::HEADER);
        // UUIDs are 36 characters: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id
        );
    }

    // -----------------------------------------------------------------------
    // Inbound X-Request-Id is reused when valid
    // -----------------------------------------------------------------------

    public function test_reuses_valid_x_request_id_from_inbound_header(): void
    {
        $incomingId = 'my-trace-id-12345';
        $request = Request::create('/api/v2/feed', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => $incomingId,
        ]);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals($incomingId, $response->headers->get(AssignRequestId::HEADER));
    }

    public function test_reuses_alphanumeric_id_with_allowed_separators(): void
    {
        $incomingId = 'req:2026-06-24.abc_xyz-001';
        $request = Request::create('/api/v2/feed', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => $incomingId,
        ]);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals($incomingId, $response->headers->get(AssignRequestId::HEADER));
    }

    public function test_generates_new_id_when_inbound_header_contains_invalid_chars(): void
    {
        $badId = 'id with spaces!';
        $request = Request::create('/api/v2/feed', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => $badId,
        ]);

        $response = $this->middleware->handle($request, $this->makeNext());

        // Should NOT echo back the invalid id — a new uuid should be assigned
        $this->assertNotEquals($badId, $response->headers->get(AssignRequestId::HEADER));
        $this->assertNotEmpty($response->headers->get(AssignRequestId::HEADER));
    }

    public function test_generates_new_id_when_inbound_header_exceeds_128_chars(): void
    {
        $tooLong = str_repeat('a', 129);
        $request = Request::create('/api/v2/feed', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => $tooLong,
        ]);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertNotEquals($tooLong, $response->headers->get(AssignRequestId::HEADER));
        $this->assertNotEmpty($response->headers->get(AssignRequestId::HEADER));
    }

    // -----------------------------------------------------------------------
    // Request attributes and headers are set for downstream use
    // -----------------------------------------------------------------------

    public function test_sets_request_id_as_request_attribute(): void
    {
        $incomingId = 'trace-abc123';
        $request = Request::create('/api/v2/feed', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => $incomingId,
        ]);

        $capturedRequest = null;
        $next = function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return response('ok', 200);
        };

        $this->middleware->handle($request, $next);

        $this->assertNotNull($capturedRequest);
        $this->assertEquals($incomingId, $capturedRequest->attributes->get('request_id'));
    }

    public function test_sets_request_id_on_request_header_for_downstream(): void
    {
        $incomingId = 'trace-downstream';
        $request = Request::create('/api/v2/feed', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => $incomingId,
        ]);

        $capturedRequest = null;
        $next = function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return response('ok', 200);
        };

        $this->middleware->handle($request, $next);

        $this->assertEquals($incomingId, $capturedRequest->headers->get(AssignRequestId::HEADER));
    }

    // -----------------------------------------------------------------------
    // Response id and request attribute id are consistent
    // -----------------------------------------------------------------------

    public function test_response_id_matches_request_attribute_id(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');

        $capturedRequest = null;
        $next = function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return response('ok', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $responseId   = $response->headers->get(AssignRequestId::HEADER);
        $attributeId  = $capturedRequest->attributes->get('request_id');

        $this->assertEquals($responseId, $attributeId);
    }

    // -----------------------------------------------------------------------
    // Passes request through
    // -----------------------------------------------------------------------

    public function test_passes_request_to_next_middleware(): void
    {
        $called = false;
        $next = function ($request) use (&$called) {
            $called = true;
            return response('ok', 200);
        };

        $request = Request::create('/api/v2/feed', 'GET');
        $this->middleware->handle($request, $next);

        $this->assertTrue($called);
    }

    public function test_preserves_response_status_from_next(): void
    {
        $next = fn ($request) => response()->json(['created' => true], 201);

        $request = Request::create('/api/v2/listings', 'POST');
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(201, $response->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // Exception handling: id still appears on the error response
    // -----------------------------------------------------------------------

    public function test_response_contains_request_id_when_next_throws(): void
    {
        $request = Request::create('/api/v2/feed', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => 'throw-test-id',
        ]);

        $next = function ($req) {
            throw new \RuntimeException('Something broke');
        };

        // renderException calls app(ExceptionHandler::class) — the booted app handles it.
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('throw-test-id', $response->headers->get(AssignRequestId::HEADER));
    }
}
