<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Federation;

use App\Core\TenantContext;
use App\Http\Controllers\Api\FederationV2Controller;
use App\Models\User;
use App\Services\FederationFeatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

/**
 * Robustness regressions for the federation browse endpoints
 * (members/listings/events/groups in FederationV2Controller).
 *
 * Array-valued query params (?partner_id[]=1, ?skills[]=x, ?q[]=y) previously
 * reached str_starts_with()/explode() as arrays and threw an uncaught
 * TypeError → HTTP 500, because parsePartnerFilter() runs BEFORE each
 * endpoint's try/catch. The queryScalar() coercion must turn these into a
 * clean 200 with the filter simply ignored.
 */
class FederationV2BrowseRobustnessTest extends TestCase
{
    use DatabaseTransactions;
    use FederationIntegrationHarness;

    private const TENANT_ID = 2; // hour-timebank

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableFederationForTenant(self::TENANT_ID);
        $this->app->make(FederationFeatureService::class)->clearCache();

        TenantContext::setById(self::TENANT_ID);
    }

    public function test_array_valued_query_params_do_not_500_on_browse_endpoints(): void
    {
        $userId = $this->makeFederatedUser(self::TENANT_ID);

        foreach (['members', 'listings', 'events', 'groups'] as $endpoint) {
            $response = $this->callBrowseEndpoint($userId, $endpoint, [
                'partner_id' => ['1'],
                'skills'     => ['gardening'],
                'q'          => ['sofa'],
            ]);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "GET /v2/federation/{$endpoint} with array params must not 500: " . (string) $response->getContent()
            );
        }
    }

    public function test_scalar_partner_id_still_filters_normally(): void
    {
        $userId = $this->makeFederatedUser(self::TENANT_ID);

        $response = $this->callBrowseEndpoint($userId, 'members', ['partner_id' => '999999']);

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame([], $body['data'] ?? null, 'A non-partner id must return an empty result set');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeFederatedUser(int $tenantId): int
    {
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id'          => $tenantId,
            'first_name'         => 'Browse',
            'last_name'          => 'Tester',
            'email'              => 'browse.' . uniqid('', true) . '@example.com',
            'username'           => 'b_' . substr(md5(uniqid('', true)), 0, 12),
            'password'           => password_hash('password', PASSWORD_BCRYPT),
            'balance'            => 0,
            'status'             => 'active',
            'preferred_language' => 'en',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $this->optInUserToFederation($userId);

        return $userId;
    }

    /**
     * Invoke a FederationV2Controller browse method directly with the given
     * query parameters bound on the request.
     *
     * @param array<string, mixed> $query
     */
    private function callBrowseEndpoint(int $userId, string $method, array $query): JsonResponse
    {
        TenantContext::setById(self::TENANT_ID);
        $user = User::query()->find($userId);
        $this->assertNotNull($user, 'Browse user must exist for the test.');
        $this->actingAs($user);

        $this->app->instance('request', Request::create(
            '/api/v2/federation/' . $method,
            'GET',
            $query
        ));

        TenantContext::setById(self::TENANT_ID);

        return $this->app->make(FederationV2Controller::class)->{$method}();
    }
}
