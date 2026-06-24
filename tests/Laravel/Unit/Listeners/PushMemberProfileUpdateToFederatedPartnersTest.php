<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\MemberProfileUpdated;
use App\Listeners\PushMemberProfileUpdateToFederatedPartners;
use App\Models\User;
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
 * PushMemberProfileUpdateToFederatedPartnersTest
 *
 * Unique tenant id 99674 — do not reuse in other test files.
 * Exercises the outbound member-profile push listener:
 * feature gates, syncable-field filter, federated-identity check,
 * allow_member_sync partner flag, and the happy-path HTTP push.
 */
class PushMemberProfileUpdateToFederatedPartnersTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID   = 99674;
    private const PARTNER_ID  = 996740;
    private const BASE_URL    = 'https://93.184.216.34';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake();

        // Ensure our unique test tenant exists.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'       => 'MemberPush Test Tenant',
                'slug'       => 'member-push-99674',
                'is_active'  => 1,
                'depth'      => 0,
                'features'   => json_encode(['federation' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);

        // Enable federation at system level.
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

        // Clear the static adapter cache so Http::fake() intercepts cleanly.
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

    /** Insert a partner row that has allow_member_sync = 1 and return its id. */
    private function insertPartnerWithMemberSync(int $id = self::PARTNER_ID): int
    {
        // api_key must be Laravel-encrypted; Crypt::decryptString on a plain string
        // throws RuntimeException (not DecryptException) so the fallback in
        // FederationExternalApiClient::decryptCredential() does NOT catch it.
        DB::table('federation_external_partners')->updateOrInsert(
            ['id' => $id],
            [
                'tenant_id'         => self::TENANT_ID,
                'name'              => 'MemberSync Partner',
                'base_url'          => self::BASE_URL,
                'api_path'          => '/api/v1/federation',
                'auth_method'       => 'api_key',
                'protocol_type'     => 'nexus',
                'api_key'           => $this->encryptApiKey('test-api-key'),
                'status'            => 'active',
                'allow_member_sync' => 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );
        return $id;
    }

    /** Insert a federated_identities row linking a local user to a partner. */
    private function insertFederatedIdentity(int $localUserId, int $partnerId, string $externalUserId = 'ext-user-1'): void
    {
        DB::table('federated_identities')->insert([
            'tenant_id'        => self::TENANT_ID,
            'local_user_id'    => $localUserId,
            'partner_id'       => $partnerId,
            'external_user_id' => $externalUserId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /** Create a user in the DB and return a User model instance. */
    private function createUser(array $attrs = []): User
    {
        $id = (int) DB::table('users')->insertGetId(array_merge([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Test User',
            'first_name' => 'Alice',
            'last_name'  => 'Smith',
            'email'      => 'alice-' . uniqid() . '@example.com',
            'password'   => 'hashed',
            'role'       => 'member',
            'created_at' => now(),
        ], $attrs));

        $user       = new User();
        $user->id   = $id;
        $user->name = $attrs['name'] ?? 'Test User';
        $user->first_name = $attrs['first_name'] ?? 'Alice';
        $user->last_name  = $attrs['last_name']  ?? 'Smith';

        return $user;
    }

    private function makeListener(): PushMemberProfileUpdateToFederatedPartners
    {
        $featureSvc = Mockery::mock(FederationFeatureService::class);
        $featureSvc->shouldReceive('isTenantFederationEnabled')->andReturn(true);
        return new PushMemberProfileUpdateToFederatedPartners($featureSvc);
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function test_implements_should_queue(): void
    {
        $this->assertContains(
            ShouldQueue::class,
            class_implements(PushMemberProfileUpdateToFederatedPartners::class) ?: []
        );
    }

    public function test_listener_routes_to_federation_queue(): void
    {
        $ref      = new \ReflectionClass(PushMemberProfileUpdateToFederatedPartners::class);
        $instance = $ref->newInstanceWithoutConstructor();
        $this->assertSame('federation', $ref->getProperty('queue')->getValue($instance));
    }

    public function test_skips_when_tenant_federation_feature_disabled(): void
    {
        $this->enableFeature(false);

        $featureSvc = Mockery::mock(FederationFeatureService::class);
        $featureSvc->shouldNotReceive('isTenantFederationEnabled');

        $user     = new User();
        $user->id = 9999;

        $listener = new PushMemberProfileUpdateToFederatedPartners($featureSvc);
        $listener->handle(new MemberProfileUpdated($user, ['first_name'], self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_skips_when_system_federation_disabled(): void
    {
        $this->enableFeature(true);

        $featureSvc = Mockery::mock(FederationFeatureService::class);
        $featureSvc->shouldReceive('isTenantFederationEnabled')->once()->andReturn(false);

        $user     = new User();
        $user->id = 9999;

        $listener = new PushMemberProfileUpdateToFederatedPartners($featureSvc);
        $listener->handle(new MemberProfileUpdated($user, ['first_name'], self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_skips_when_changed_fields_are_not_syncable(): void
    {
        // 'password' and 'email' are not in SYNCABLE_FIELDS.
        $user     = new User();
        $user->id = 9999;

        $featureSvc = Mockery::mock(FederationFeatureService::class);
        $featureSvc->shouldReceive('isTenantFederationEnabled')->andReturn(true);

        $listener = new PushMemberProfileUpdateToFederatedPartners($featureSvc);
        $listener->handle(new MemberProfileUpdated($user, ['password', 'email'], self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_skips_when_changed_fields_array_is_empty(): void
    {
        $user     = new User();
        $user->id = 9999;

        $featureSvc = Mockery::mock(FederationFeatureService::class);
        $featureSvc->shouldReceive('isTenantFederationEnabled')->andReturn(true);

        $listener = new PushMemberProfileUpdateToFederatedPartners($featureSvc);
        $listener->handle(new MemberProfileUpdated($user, [], self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_skips_when_user_has_no_federated_identity(): void
    {
        $user     = new User();
        $user->id = 8888888; // no federated_identities row for this id

        $listener = $this->makeListener();
        $listener->handle(new MemberProfileUpdated($user, ['first_name'], self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_skips_when_partner_does_not_have_allow_member_sync(): void
    {
        // Insert partner with allow_member_sync = 0.
        DB::table('federation_external_partners')->updateOrInsert(
            ['id' => self::PARTNER_ID],
            [
                'tenant_id'         => self::TENANT_ID,
                'name'              => 'NoSync Partner',
                'base_url'          => self::BASE_URL,
                'api_path'          => '/api/v1/federation',
                'auth_method'       => 'api_key',
                'protocol_type'     => 'nexus',
                'api_key'           => $this->encryptApiKey('test-api-key'),
                'status'            => 'active',
                'allow_member_sync' => 0,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        $user = $this->createUser();
        $this->insertFederatedIdentity($user->id, self::PARTNER_ID, 'ext-nosync-1');

        $listener = $this->makeListener();
        $listener->handle(new MemberProfileUpdated($user, ['first_name'], self::TENANT_ID));

        Http::assertNothingSent();
    }

    public function test_pushes_to_partner_on_happy_path(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $this->insertPartnerWithMemberSync();
        $user = $this->createUser(['first_name' => 'Bob']);
        $this->insertFederatedIdentity($user->id, self::PARTNER_ID, 'ext-bob-1');

        $listener = $this->makeListener();
        $listener->handle(new MemberProfileUpdated($user, ['first_name'], self::TENANT_ID));

        Http::assertSent(function ($request) {
            $body = $request->data();
            return isset($body['action'])
                && $body['action'] === 'profile_updated'
                && isset($body['changed_fields'])
                && in_array('first_name', $body['changed_fields'], true)
                && isset($body['profile']['first_name']);
        });
    }

    public function test_payload_contains_external_user_id(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $this->insertPartnerWithMemberSync();
        $user = $this->createUser();
        $this->insertFederatedIdentity($user->id, self::PARTNER_ID, 'ext-uid-999');

        $listener = $this->makeListener();
        $listener->handle(new MemberProfileUpdated($user, ['first_name'], self::TENANT_ID));

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ($body['external_user_id'] ?? null) === 'ext-uid-999';
        });
    }

    public function test_payload_contains_tenant_id(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $this->insertPartnerWithMemberSync();
        $user = $this->createUser();
        $this->insertFederatedIdentity($user->id, self::PARTNER_ID, 'ext-tid-1');

        $listener = $this->makeListener();
        $listener->handle(new MemberProfileUpdated($user, ['first_name'], self::TENANT_ID));

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ((int) ($body['tenant_id'] ?? 0)) === self::TENANT_ID;
        });
    }

    public function test_filters_non_syncable_fields_from_payload(): void
    {
        // Provide a mix of syncable and non-syncable fields.
        // Only syncable ones must appear in the profile.
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $this->insertPartnerWithMemberSync();
        $user = $this->createUser(['first_name' => 'Charlie', 'bio' => 'hello']);

        $this->insertFederatedIdentity($user->id, self::PARTNER_ID, 'ext-filter-1');

        $listener = $this->makeListener();
        // 'password' is not syncable; 'first_name' and 'bio' are.
        $listener->handle(new MemberProfileUpdated($user, ['first_name', 'bio', 'password'], self::TENANT_ID));

        Http::assertSent(function ($request) {
            $body   = $request->data();
            $fields = $body['changed_fields'] ?? [];
            return in_array('first_name', $fields, true)
                && in_array('bio', $fields, true)
                && !in_array('password', $fields, true);
        });
    }

    public function test_restores_tenant_context_after_handle(): void
    {
        // Queue workers reset tenant; console mode leaves context null after a scoped listener.
        $user     = new User();
        $user->id = 9999;

        $featureSvc = Mockery::mock(FederationFeatureService::class);
        $featureSvc->shouldReceive('isTenantFederationEnabled')->andReturn(false);

        $listener = new PushMemberProfileUpdateToFederatedPartners($featureSvc);
        $listener->handle(new MemberProfileUpdated($user, ['first_name'], self::TENANT_ID));

        // In console/queue mode restoreAfterScopedListener calls reset().
        $this->assertNull(TenantContext::currentId());
        Http::assertNothingSent();
    }
}
