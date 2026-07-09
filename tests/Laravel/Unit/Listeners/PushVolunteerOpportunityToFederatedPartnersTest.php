<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\VolunteerOpportunityCreated;
use App\Events\VolunteerOpportunityUpdated;
use App\Listeners\PushVolunteerOpportunityToFederatedPartners;
use App\Models\VolOpportunity;
use App\Services\FederationExternalApiClient;
use App\Services\FederationFeatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * PushVolunteerOpportunityToFederatedPartnersTest
 *
 * Unique tenant id 99675 — do not reuse in other test files.
 * Exercises the outbound volunteer-opportunity push listener:
 * feature gates, local-only guard (is_federated / external_id),
 * federated_visibility gate, is_active / status guard,
 * allow_volunteering partner flag, create vs update action, and HTTP push.
 */
class PushVolunteerOpportunityToFederatedPartnersTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID  = 99675;
    private const PARTNER_ID = 996750;
    private const BASE_URL   = 'https://93.184.216.34';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'       => 'VolPush Test Tenant',
                'slug'       => 'vol-push-99675',
                'is_active'  => 1,
                'depth'      => 0,
                'features'   => json_encode(['federation' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);

        DB::table('federation_system_control')->updateOrInsert(
            ['id' => 1],
            [
                'federation_enabled'        => 1,
                'whitelist_mode_enabled'    => 0,
                'emergency_lockdown_active' => 0,
            ]
        );

        // Rebind the encrypter with the valid test key so Crypt::decryptString()
        // works inside FederationExternalApiClient::decryptCredential().
        // The Docker container OS env has an invalid APP_KEY that prevents the
        // default EncryptionServiceProvider singleton from initialising.
        $validKey = base64_decode('HfQEDtbtr90JIXhsaAhSFWnzIo1f31VZ2e5qLqKKnls=');
        $this->app->instance('encrypter', new \Illuminate\Encryption\Encrypter($validKey, 'AES-256-CBC'));

        FederationExternalApiClient::clearAdapterCache();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Encrypt a credential string using the known test APP_KEY from .env.testing.
     *
     * The Docker container's OS env has an invalid APP_KEY which prevents the
     * Crypt facade singleton from initialising, so we bypass it and instantiate
     * the Encrypter directly with the valid 32-byte key.
     */
    private function encryptApiKey(string $value): string
    {
        $rawKey = base64_decode('HfQEDtbtr90JIXhsaAhSFWnzIo1f31VZ2e5qLqKKnls=');
        return (new \Illuminate\Encryption\Encrypter($rawKey, 'AES-256-CBC'))->encryptString($value);
    }

    private function enableFeature(bool $enabled): void
    {
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode(['federation' => $enabled])]);
        TenantContext::setById(self::TENANT_ID);
    }

    /** Insert an active partner with allow_volunteering = 1. */
    private function insertVolPartner(int $id = self::PARTNER_ID): int
    {
        DB::table('federation_external_partners')->updateOrInsert(
            ['id' => $id],
            [
                'tenant_id'         => self::TENANT_ID,
                'name'              => 'Vol Partner',
                'base_url'          => self::BASE_URL,
                'api_path'          => '/api/v1/federation',
                'auth_method'       => 'api_key',
                'protocol_type'     => 'nexus',
                'api_key'           => $this->encryptApiKey('test-api-key'),
                'status'            => 'active',
                'allow_volunteering'=> 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );
        return $id;
    }

    /** Make a publishable local VolOpportunity stub. */
    private function makeOpportunity(array $attrs = []): VolOpportunity
    {
        $opp                       = new VolOpportunity();
        $opp->id                   = $attrs['id'] ?? 5001;
        $opp->title                = $attrs['title'] ?? 'Help wanted';
        $opp->description          = $attrs['description'] ?? 'Desc';
        $opp->location             = $attrs['location'] ?? null;
        $opp->is_active            = $attrs['is_active'] ?? true;
        $opp->status               = $attrs['status'] ?? 'active';
        $opp->federated_visibility = $attrs['federated_visibility'] ?? 'listed';
        $opp->is_federated         = $attrs['is_federated'] ?? false;
        $opp->external_id          = $attrs['external_id'] ?? null;
        $opp->organization_id      = $attrs['organization_id'] ?? null;
        $opp->created_by           = $attrs['created_by'] ?? null;
        return $opp;
    }

    private function makeListener(): PushVolunteerOpportunityToFederatedPartners
    {
        $featureSvc = Mockery::mock(FederationFeatureService::class);
        $featureSvc->shouldReceive('isTenantFederationEnabled')->andReturn(true);
        return new PushVolunteerOpportunityToFederatedPartners($featureSvc);
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function test_implements_should_queue(): void
    {
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushVolunteerOpportunityToFederatedPartners::class) ?: []
        );
    }

    public function test_listener_queue_and_config(): void
    {
        $ref      = new \ReflectionClass(PushVolunteerOpportunityToFederatedPartners::class);
        $instance = $ref->newInstanceWithoutConstructor();

        $this->assertSame('federation', $ref->getProperty('queue')->getValue($instance));
        $this->assertSame(1, $ref->getProperty('tries')->getValue($instance));
        $this->assertSame(60, $ref->getProperty('timeout')->getValue($instance));
    }

    public function test_skips_when_tenant_federation_feature_disabled(): void
    {
        $this->enableFeature(false);

        $featureSvc = Mockery::mock(FederationFeatureService::class);
        $featureSvc->shouldNotReceive('isTenantFederationEnabled');

        $listener = new PushVolunteerOpportunityToFederatedPartners($featureSvc);
        $listener->handle(new VolunteerOpportunityCreated(
            $this->makeOpportunity(),
            self::TENANT_ID
        ));

        Http::assertNothingSent();
    }

    public function test_skips_when_system_federation_disabled(): void
    {
        $this->enableFeature(true);

        $featureSvc = Mockery::mock(FederationFeatureService::class);
        $featureSvc->shouldReceive('isTenantFederationEnabled')->once()->andReturn(false);

        $listener = new PushVolunteerOpportunityToFederatedPartners($featureSvc);
        $listener->handle(new VolunteerOpportunityCreated(
            $this->makeOpportunity(),
            self::TENANT_ID
        ));

        Http::assertNothingSent();
    }

    public function test_skips_when_opportunity_is_already_federated(): void
    {
        // is_federated = true marks rows imported FROM a partner — must not re-export.
        $opp = $this->makeOpportunity(['is_federated' => true]);

        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityCreated($opp, self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_skips_when_opportunity_has_external_id(): void
    {
        // external_id set also indicates an imported/federated row.
        $opp = $this->makeOpportunity(['external_id' => 'ext-remote-99']);

        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityCreated($opp, self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_skips_when_federated_visibility_is_none(): void
    {
        $opp = $this->makeOpportunity(['federated_visibility' => 'none']);

        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityCreated($opp, self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_skips_when_opportunity_is_not_active(): void
    {
        $opp = $this->makeOpportunity(['is_active' => false]);

        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityCreated($opp, self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_skips_when_status_is_closed(): void
    {
        $opp = $this->makeOpportunity(['status' => 'closed']);

        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityCreated($opp, self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_skips_when_no_partners_with_allow_volunteering(): void
    {
        // No partner row inserted for this tenant; getActivePartnersWithFlag returns [].
        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityCreated(
            $this->makeOpportunity(),
            self::TENANT_ID
        ));

        Http::assertNothingSent();
    }

    public function test_pushes_with_action_created_on_created_event(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->insertVolPartner();

        $opp = $this->makeOpportunity(['id' => 5002, 'title' => 'Garden help']);
        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityCreated($opp, self::TENANT_ID));

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ($body['action'] ?? null) === 'created'
                && ($body['id'] ?? null) === 5002
                && ($body['title'] ?? null) === 'Garden help';
        });
    }

    public function test_pushes_with_action_updated_on_updated_event(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->insertVolPartner();

        $opp = $this->makeOpportunity(['id' => 5003, 'title' => 'Updated garden help']);
        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityUpdated($opp, self::TENANT_ID));

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ($body['action'] ?? null) === 'updated';
        });
    }

    public function test_un_sharing_a_listed_opportunity_retracts_from_partners(): void
    {
        // VOL-BE-004: federated_visibility listed -> none must push action='deleted'
        // so partners drop the withdrawn opportunity instead of displaying it forever.
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->insertVolPartner();

        $opp = $this->makeOpportunity(['id' => 5005, 'federated_visibility' => 'none']);
        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityUpdated($opp, self::TENANT_ID, 'listed'));

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ($body['action'] ?? null) === 'deleted'
                && ((string) ($body['external_id'] ?? '')) === '5005';
        });
    }

    public function test_update_of_never_shared_opportunity_does_not_retract(): void
    {
        // A row whose prior visibility was not 'listed' must not generate a
        // spurious retraction on update.
        $this->insertVolPartner();

        $opp = $this->makeOpportunity(['id' => 5006, 'federated_visibility' => 'none']);
        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityUpdated($opp, self::TENANT_ID, 'none'));

        Http::assertNothingSent();
    }

    public function test_payload_does_not_leak_local_tenant_or_creator_ids(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->insertVolPartner();

        $opp = $this->makeOpportunity(['id' => 5004, 'created_by' => 4242]);
        $listener = $this->makeListener();
        $listener->handle(new VolunteerOpportunityCreated($opp, self::TENANT_ID));

        Http::assertSent(function ($request) {
            $body = $request->data();

            // The request must still carry real, partner-relevant data...
            $carriesData = ((string) ($body['external_id'] ?? '')) === '5004';

            // ...but must NOT leak the sender's internal user/tenant ids: no
            // outbound adapter or inbound consumer reads them (see the listener).
            $noLeak = !array_key_exists('created_by', $body)
                && !array_key_exists('tenant_id', $body);

            return $carriesData && $noLeak;
        });
    }

    public function test_restores_tenant_context_after_handle(): void
    {
        $featureSvc = Mockery::mock(FederationFeatureService::class);
        $featureSvc->shouldReceive('isTenantFederationEnabled')->andReturn(false);

        $listener = new PushVolunteerOpportunityToFederatedPartners($featureSvc);
        $listener->handle(new VolunteerOpportunityCreated(
            $this->makeOpportunity(),
            self::TENANT_ID
        ));

        // Console/queue mode: restoreAfterScopedListener calls reset().
        $this->assertNull(TenantContext::currentId());
        Http::assertNothingSent();
    }
}
