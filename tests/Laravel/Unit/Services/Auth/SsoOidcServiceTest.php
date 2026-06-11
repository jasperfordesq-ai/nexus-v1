<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Auth;

use App\Services\Auth\SsoOidcService;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

/**
 * SSO engine (IT-Sec-05) — DB-free unit coverage: state signing,
 * PKCE, domain gating, email extraction, OIDC discovery, and full
 * ID-token validation against a real RS256 keypair.
 */
class SsoOidcServiceTest extends TestCase
{
    private SsoOidcService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SsoOidcService();
        Cache::flush();
    }

    private function invokePrivate(string $method, ...$args)
    {
        $ref = new \ReflectionMethod(SsoOidcService::class, $method);
        return $ref->invoke($this->service, ...$args);
    }

    // ------------------------------------------------------------ state

    public function test_state_round_trips(): void
    {
        $state = $this->invokePrivate('buildState', 7, 'entra', 'nonce123');
        $payload = $this->invokePrivate('verifyState', $state);

        $this->assertSame(7, $payload['tenant_id']);
        $this->assertSame('entra', $payload['provider_key']);
        $this->assertSame('nonce123', $payload['state_nonce']);
    }

    public function test_tampered_state_is_rejected(): void
    {
        $state = $this->invokePrivate('buildState', 7, 'entra', 'nonce123');
        [$body, $sig] = explode('.', $state, 2);
        $forged = base64_encode(json_encode(['t' => 999, 'p' => 'entra', 'n' => 'nonce123', 'x' => now()->timestamp]));

        $this->expectExceptionMessage('SSO state signature mismatch.');
        $this->invokePrivate('verifyState', $forged . '.' . $sig);
    }

    // ------------------------------------------------------------ PKCE

    public function test_pkce_challenge_matches_rfc7636_vector(): void
    {
        // RFC 7636 appendix B test vector
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $this->assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            $this->invokePrivate('pkceChallenge', $verifier)
        );
    }

    // ------------------------------------------------------ domain gating

    public function test_domain_allow_list_blocks_other_domains(): void
    {
        $provider = (object) ['allowed_email_domains' => json_encode(['coventry.gov.uk'])];

        // Allowed domain passes
        $this->invokePrivate('assertDomainAllowed', $provider, 'jane@coventry.gov.uk');

        // Different domain is rejected
        $this->expectExceptionMessage(__('api.sso_domain_not_allowed'));
        $this->invokePrivate('assertDomainAllowed', $provider, 'jane@evil.example');
    }

    public function test_empty_allow_list_permits_any_domain(): void
    {
        $provider = (object) ['allowed_email_domains' => null];
        $this->invokePrivate('assertDomainAllowed', $provider, 'anyone@anywhere.example');
        $this->assertTrue(true);
    }

    public function test_subdomain_does_not_satisfy_allow_list(): void
    {
        $provider = (object) ['allowed_email_domains' => json_encode(['coventry.gov.uk'])];
        $this->expectExceptionMessage(__('api.sso_domain_not_allowed'));
        $this->invokePrivate('assertDomainAllowed', $provider, 'jane@sub.coventry.gov.uk');
    }

    public function test_normalise_domains_accepts_csv_and_strips_at(): void
    {
        $this->assertSame(
            ['coventry.gov.uk', 'example.org'],
            $this->invokePrivate('normaliseDomains', "@Coventry.gov.uk, example.org\n coventry.gov.uk")
        );
        $this->assertSame([], $this->invokePrivate('normaliseDomains', 'not a domain'));
    }

    // ------------------------------------------------------------ claims

    public function test_extract_email_prefers_email_claim_and_validates(): void
    {
        $this->assertSame('a@b.example', $this->invokePrivate('extractEmail', ['email' => 'A@b.example', 'preferred_username' => 'x@y.example']));
        $this->assertSame('x@y.example', $this->invokePrivate('extractEmail', ['preferred_username' => 'x@y.example']));
        $this->assertNull($this->invokePrivate('extractEmail', ['preferred_username' => 'not-an-email']));
    }

    public function test_identity_provider_string_is_tenant_qualified(): void
    {
        $this->assertSame('sso:7:entra', $this->service->identityProviderString(7, 'entra'));
    }

    // --------------------------------------------------------- discovery

    public function test_discovery_fetches_and_validates_document(): void
    {
        Http::fake([
            'https://idp.example/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://idp.example',
                'authorization_endpoint' => 'https://idp.example/authorize',
                'token_endpoint' => 'https://idp.example/token',
                'jwks_uri' => 'https://idp.example/keys',
            ]),
        ]);

        $doc = $this->service->discover('https://idp.example/');
        $this->assertSame('https://idp.example/authorize', $doc['authorization_endpoint']);
    }

    public function test_discovery_rejects_incomplete_document(): void
    {
        Http::fake([
            'https://bad.example/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://bad.example',
            ]),
        ]);

        $this->expectExceptionMessage("missing 'authorization_endpoint'");
        $this->service->discover('https://bad.example');
    }

    // ------------------------------------------- ID token validation (RS256)

    /**
     * Full code-exchange validation against a locally generated RSA
     * keypair: good token passes; wrong nonce, audience and signature
     * are each rejected.
     */
    public function test_exchange_validates_real_rs256_id_token(): void
    {
        [$privateKey, $jwks] = $this->makeKeypairAndJwks('test-key-1');

        $issuer = 'https://login.example/v2.0';
        $discovery = [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/authorize',
            'token_endpoint' => $issuer . '/token',
            'jwks_uri' => $issuer . '/keys',
        ];
        $provider = (object) [
            'client_id' => 'client-abc',
            'client_secret_encrypted' => null,
        ];
        $flow = ['code_verifier' => str_repeat('v', 96), 'oidc_nonce' => 'nonce-xyz'];

        $claims = [
            'iss' => $issuer,
            'aud' => 'client-abc',
            'sub' => 'user-123',
            'email' => 'jane@coventry.gov.uk',
            'name' => 'Jane Smith',
            'nonce' => 'nonce-xyz',
            'iat' => time(),
            'exp' => time() + 300,
        ];
        $idToken = JWT::encode($claims, $privateKey, 'RS256', 'test-key-1');

        Http::fake([
            $issuer . '/token' => Http::response(['id_token' => $idToken]),
            $issuer . '/keys' => Http::response($jwks),
        ]);

        $validated = $this->invokePrivate('exchangeAndValidate', $provider, $discovery, 'auth-code', $flow);
        $this->assertSame('user-123', $validated['sub']);
        $this->assertSame('jane@coventry.gov.uk', $validated['email']);
    }

    public function test_exchange_rejects_wrong_nonce(): void
    {
        [$privateKey, $jwks] = $this->makeKeypairAndJwks('test-key-1');
        $issuer = 'https://login.example/v2.0';
        $discovery = [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/authorize',
            'token_endpoint' => $issuer . '/token',
            'jwks_uri' => $issuer . '/keys',
        ];
        $provider = (object) ['client_id' => 'client-abc', 'client_secret_encrypted' => null];

        $idToken = JWT::encode([
            'iss' => $issuer, 'aud' => 'client-abc', 'sub' => 'user-123',
            'nonce' => 'attacker-nonce', 'iat' => time(), 'exp' => time() + 300,
        ], $privateKey, 'RS256', 'test-key-1');

        Http::fake([
            $issuer . '/token' => Http::response(['id_token' => $idToken]),
            $issuer . '/keys' => Http::response($jwks),
        ]);

        $this->expectExceptionMessage('ID token nonce mismatch.');
        $this->invokePrivate('exchangeAndValidate', $provider, $discovery, 'auth-code', [
            'code_verifier' => 'v', 'oidc_nonce' => 'real-nonce',
        ]);
    }

    public function test_exchange_rejects_wrong_audience(): void
    {
        [$privateKey, $jwks] = $this->makeKeypairAndJwks('test-key-1');
        $issuer = 'https://login.example/v2.0';
        $discovery = [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/authorize',
            'token_endpoint' => $issuer . '/token',
            'jwks_uri' => $issuer . '/keys',
        ];
        $provider = (object) ['client_id' => 'client-abc', 'client_secret_encrypted' => null];

        $idToken = JWT::encode([
            'iss' => $issuer, 'aud' => 'some-other-app', 'sub' => 'user-123',
            'nonce' => 'n', 'iat' => time(), 'exp' => time() + 300,
        ], $privateKey, 'RS256', 'test-key-1');

        Http::fake([
            $issuer . '/token' => Http::response(['id_token' => $idToken]),
            $issuer . '/keys' => Http::response($jwks),
        ]);

        $this->expectExceptionMessage('ID token audience mismatch.');
        $this->invokePrivate('exchangeAndValidate', $provider, $discovery, 'auth-code', [
            'code_verifier' => 'v', 'oidc_nonce' => 'n',
        ]);
    }

    public function test_exchange_rejects_token_signed_by_unknown_key(): void
    {
        [, $jwks] = $this->makeKeypairAndJwks('test-key-1');
        [$attackerKey] = $this->makeKeypairAndJwks('attacker-key');

        $issuer = 'https://login.example/v2.0';
        $discovery = [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/authorize',
            'token_endpoint' => $issuer . '/token',
            'jwks_uri' => $issuer . '/keys',
        ];
        $provider = (object) ['client_id' => 'client-abc', 'client_secret_encrypted' => null];

        // Signed by a key that is NOT in the issuer's JWKS (kid matches
        // nothing after refresh either).
        $idToken = JWT::encode([
            'iss' => $issuer, 'aud' => 'client-abc', 'sub' => 'user-123',
            'nonce' => 'n', 'iat' => time(), 'exp' => time() + 300,
        ], $attackerKey, 'RS256', 'attacker-key');

        Http::fake([
            $issuer . '/token' => Http::response(['id_token' => $idToken]),
            $issuer . '/keys' => Http::response($jwks),
        ]);

        $this->expectException(\Throwable::class);
        $this->invokePrivate('exchangeAndValidate', $provider, $discovery, 'auth-code', [
            'code_verifier' => 'v', 'oidc_nonce' => 'n',
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array{0:\OpenSSLAsymmetricKey, 1:array{keys:array<int,array<string,string>>}}
     */
    private function makeKeypairAndJwks(string $kid): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        // Windows PHP ships openssl.cnf in extras/ssl but doesn't load it
        // by default — without it openssl_pkey_new() returns false.
        $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_file($cnf)) {
            $config['config'] = $cnf;
        }
        $res = openssl_pkey_new($config);
        $this->assertNotFalse($res, 'openssl_pkey_new failed: ' . (string) openssl_error_string());
        $details = openssl_pkey_get_details($res);

        $b64url = static fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        $jwks = ['keys' => [[
            'kty' => 'RSA',
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $b64url($details['rsa']['n']),
            'e' => $b64url($details['rsa']['e']),
        ]]];

        return [$res, $jwks];
    }
}
