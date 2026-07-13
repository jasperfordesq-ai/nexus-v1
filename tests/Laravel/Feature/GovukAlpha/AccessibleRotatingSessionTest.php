<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\ApiErrorCodes;
use App\Core\ClientIp;
use App\Core\TenantContext;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TotpController;
use App\Http\Controllers\GovukAlpha\Support\AccessibleIdentityResolver;
use App\Models\User;
use App\Services\RateLimitService;
use Illuminate\Cookie\CookieJar;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\Laravel\TestCase;

class AccessibleRotatingSessionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        ClientIp::clearCache();
        RateLimitService::clear('auth:refresh:127.0.0.1');
    }

    protected function tearDown(): void
    {
        foreach ([
            'user_id',
            'tenant_id',
            '_api_session_bridge_version',
            '_api_access_user_id',
            '_api_access_tenant_id',
            '_api_access_issued_at',
            '_api_access_expires_at',
        ] as $key) {
            unset($_SESSION[$key]);
        }

        parent::tearDown();
    }

    public function test_login_issues_short_access_and_http_only_refresh_cookies(): void
    {
        $email = 'accessible-rotation-' . bin2hex(random_bytes(4)) . '@example.test';
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('CorrectPassword123!'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->alphaPost("/{$this->testTenantSlug}/accessible/login", [
            'email' => $email,
            'password' => 'CorrectPassword123!',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/dashboard");
        $this->assertAccessCookiePolicy($response);
        $refreshCookie = $this->responseCookie($response, 'accessible_refresh_token');
        $this->assertTrue($refreshCookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $refreshCookie->getSameSite()));
        $this->assertGreaterThan(24 * 60 * 60, $refreshCookie->getExpiresTime() - time());
        $this->assertDatabaseHas('refresh_token_sessions', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
        ]);
    }

    public function test_two_factor_completion_issues_the_same_cookie_pair(): void
    {
        $totp = $this->getMockBuilder(TotpController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['verify'])
            ->getMock();
        $totp->expects($this->once())
            ->method('verify')
            ->willReturn(response()->json([
                'success' => true,
                'access_token' => 'accessible-totp-access',
                'refresh_token' => 'accessible-totp-refresh',
                'refresh_expires_in' => 3600,
            ]));
        $this->app->instance(TotpController::class, $totp);

        $response = $this->alphaPost(
            "/{$this->testTenantSlug}/accessible/login/two-factor",
            [
                'code' => '123456',
            ],
            ['alpha_2fa_token' => 'pending-accessible-challenge'],
        );

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/dashboard");
        $response->assertSessionMissing('alpha_2fa_token');
        $this->assertAccessCookiePolicy($response);
        $refreshCookie = $this->responseCookie($response, 'accessible_refresh_token');
        $this->assertTrue($refreshCookie->isHttpOnly());
        $this->assertGreaterThanOrEqual(59 * 60, $refreshCookie->getExpiresTime() - time());
        $this->assertLessThanOrEqual(61 * 60, $refreshCookie->getExpiresTime() - time());
    }

    public function test_invalid_access_cookie_rotates_once_through_the_tracked_refresh_family(): void
    {
        $email = 'accessible-refresh-' . bin2hex(random_bytes(4)) . '@example.test';
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('CorrectPassword123!'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
        $login = $this->alphaPost("/{$this->testTenantSlug}/accessible/login", [
            'email' => $email,
            'password' => 'CorrectPassword123!',
        ]);
        $login->assertRedirect("/{$this->testTenantSlug}/accessible/dashboard");
        $encryptedRefreshCookie = $this->responseCookie($login, 'accessible_refresh_token');

        $response = $this
            ->withCookie('auth_token', 'expired-or-invalid-access-token')
            ->withUnencryptedCookie(
                'accessible_refresh_token',
                $encryptedRefreshCookie->getValue(),
            )
            ->get("/{$this->testTenantSlug}/accessible/feed");

        $response->assertOk();
        $this->assertAccessCookiePolicy($response);
        $rotatedAccessCookie = $this->responseCookie($response, 'auth_token');
        $rotatedRefreshCookie = $this->responseCookie($response, 'accessible_refresh_token');

        // Feature tests reuse one application cookie jar across synthetic HTTP
        // requests. The queued winner pair has already been copied onto this
        // response, but Laravel deliberately leaves it in that jar; a real
        // Apache/PHP request gets a fresh application lifecycle. Flush that
        // test-only residue so the next response measures only what the losing
        // refresh attempt queues.
        /** @var CookieJar $cookieJar */
        $cookieJar = $this->app->make('cookie');
        $cookieJar->flushQueuedCookies();

        $this->assertSame(
            2,
            DB::table('refresh_token_sessions')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $user->id)
                ->count(),
            'One resolver pass must consume one refresh row and mint one replacement only.',
        );
        $this->assertSame(
            1,
            DB::table('refresh_token_sessions')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $user->id)
                ->whereNotNull('consumed_at')
                ->count(),
        );

        // A request that was already in flight with the same old pair loses
        // the rotation race. It must not expire cookies or revoke the winner.
        $loser = $this
            ->withCookie('auth_token', 'expired-or-invalid-access-token')
            ->withUnencryptedCookie(
                'accessible_refresh_token',
                $encryptedRefreshCookie->getValue(),
            )
            ->get("/{$this->testTenantSlug}/accessible/feed");

        $loser->assertRedirect(
            "/{$this->testTenantSlug}/accessible/login?status=auth-required",
        );
        $loser->assertCookieMissing('auth_token');
        $loser->assertCookieMissing('accessible_refresh_token');
        $this->assertSame(
            0,
            DB::table('refresh_token_sessions')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $user->id)
                ->whereNotNull('revoked_at')
                ->count(),
        );

        // The queued-cookie middleware must make the rotated pair usable by the
        // next server-rendered request without performing a second rotation.
        $followUp = $this
            ->withUnencryptedCookie('auth_token', $rotatedAccessCookie->getValue())
            ->withUnencryptedCookie('accessible_refresh_token', $rotatedRefreshCookie->getValue())
            ->get("/{$this->testTenantSlug}/accessible/feed");

        $followUp->assertOk();
        $this->assertSame(
            2,
            DB::table('refresh_token_sessions')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $user->id)
                ->count(),
        );
    }

    public function test_unrelated_409_refresh_failure_expires_existing_cookies(): void
    {
        $auth = $this->getMockBuilder(AuthController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['refreshToken'])
            ->getMock();
        $auth->expects($this->once())
            ->method('refreshToken')
            ->willReturn(response()->json([
                'success' => false,
                'errors' => [['code' => ApiErrorCodes::RESOURCE_CONFLICT]],
            ], 409));
        $this->app->instance(AuthController::class, $auth);

        $response = $this
            ->withCookie('auth_token', 'expired-or-invalid-access-token')
            ->withCookie('accessible_refresh_token', 'conflicting-refresh-token')
            ->get("/{$this->testTenantSlug}/accessible/feed");

        $response->assertRedirect(
            "/{$this->testTenantSlug}/accessible/login?status=auth-required",
        );
        $response->assertCookieExpired('auth_token');
        $response->assertCookieExpired('accessible_refresh_token');
    }

    public function test_transient_refresh_failure_preserves_existing_cookies(): void
    {
        $auth = $this->getMockBuilder(AuthController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['refreshToken'])
            ->getMock();
        $auth->expects($this->once())
            ->method('refreshToken')
            ->willReturn(response()->json([
                'success' => false,
                'errors' => [['code' => 'TEMPORARY_FAILURE']],
            ], 503));
        $this->app->instance(AuthController::class, $auth);

        $response = $this
            ->withCookie('auth_token', 'expired-or-invalid-access-token')
            ->withCookie('accessible_refresh_token', 'potentially-valid-refresh-token')
            ->get("/{$this->testTenantSlug}/accessible/feed");

        $response->assertRedirect(
            "/{$this->testTenantSlug}/accessible/login?status=auth-required",
        );
        $response->assertCookieMissing('auth_token');
        $response->assertCookieMissing('accessible_refresh_token');
    }

    public function test_unstamped_legacy_php_session_cannot_bypass_access_expiry(): void
    {
        $this->ensureNativePhpSession();
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['tenant_id'] = $this->testTenantId;

        $this->assertNull(
            app(AccessibleIdentityResolver::class)->userId(Request::create('/', 'GET')),
        );
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_stamped_legacy_php_session_is_accepted_within_its_fixed_window(): void
    {
        $this->ensureNativePhpSession();
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $issuedAt = time() - 1;
        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['tenant_id'] = $this->testTenantId;
        $_SESSION['_api_session_bridge_version'] = 1;
        $_SESSION['_api_access_user_id'] = (int) $user->id;
        $_SESSION['_api_access_tenant_id'] = $this->testTenantId;
        $_SESSION['_api_access_issued_at'] = $issuedAt;
        $_SESSION['_api_access_expires_at'] = $issuedAt + 900;

        $this->assertSame(
            (int) $user->id,
            app(AccessibleIdentityResolver::class)->userId(Request::create('/', 'GET')),
        );
    }

    public function test_expired_stamped_legacy_php_session_is_cleared(): void
    {
        $this->ensureNativePhpSession();
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $issuedAt = time() - 901;
        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['tenant_id'] = $this->testTenantId;
        $_SESSION['_api_session_bridge_version'] = 1;
        $_SESSION['_api_access_user_id'] = (int) $user->id;
        $_SESSION['_api_access_tenant_id'] = $this->testTenantId;
        $_SESSION['_api_access_issued_at'] = $issuedAt;
        $_SESSION['_api_access_expires_at'] = $issuedAt + 900;

        $this->assertNull(
            app(AccessibleIdentityResolver::class)->userId(Request::create('/', 'GET')),
        );
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_invalid_refresh_fails_closed_and_expires_both_cookies(): void
    {
        $response = $this
            ->withCookie('auth_token', 'expired-or-invalid-access-token')
            ->withCookie('accessible_refresh_token', 'invalid-refresh-token')
            ->get("/{$this->testTenantSlug}/accessible/feed");

        $response->assertRedirect(
            "/{$this->testTenantSlug}/accessible/login?status=auth-required",
        );
        $response->assertCookieExpired('auth_token');
        $response->assertCookieExpired('accessible_refresh_token');
    }

    public function test_logout_expires_cookies_and_revokes_the_refresh_family(): void
    {
        $email = 'accessible-logout-' . bin2hex(random_bytes(4)) . '@example.test';
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('CorrectPassword123!'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
        $login = $this->alphaPost("/{$this->testTenantSlug}/accessible/login", [
            'email' => $email,
            'password' => 'CorrectPassword123!',
        ]);
        $login->assertRedirect("/{$this->testTenantSlug}/accessible/dashboard");

        $accessCookie = $this->responseCookie($login, 'auth_token');
        $refreshCookie = $this->responseCookie($login, 'accessible_refresh_token');
        $response = $this
            ->withUnencryptedCookie('auth_token', $accessCookie->getValue())
            ->withUnencryptedCookie('accessible_refresh_token', $refreshCookie->getValue())
            ->alphaPost("/{$this->testTenantSlug}/accessible/logout");

        $response->assertRedirect(
            "/{$this->testTenantSlug}/accessible/login?status=signed-out",
        );
        $response->assertCookieExpired('auth_token');
        $response->assertCookieExpired('accessible_refresh_token');
        $this->assertDatabaseMissing('refresh_token_sessions', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('refresh_token_sessions', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'revocation_reason' => 'user_logout',
        ]);
    }

    public function test_account_deletion_request_revokes_all_sessions_before_sign_out(): void
    {
        $email = 'accessible-delete-' . bin2hex(random_bytes(4)) . '@example.test';
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('CorrectPassword123!'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
        $login = $this->alphaPost("/{$this->testTenantSlug}/accessible/login", [
            'email' => $email,
            'password' => 'CorrectPassword123!',
        ]);
        $secondRefresh = app(\App\Services\TokenService::class)
            ->generateRefreshToken((int) $user->id, $this->testTenantId);

        $response = $this
            ->withUnencryptedCookie('auth_token', $this->responseCookie($login, 'auth_token')->getValue())
            ->withUnencryptedCookie(
                'accessible_refresh_token',
                $this->responseCookie($login, 'accessible_refresh_token')->getValue(),
            )
            ->alphaPost("/{$this->testTenantSlug}/accessible/profile/delete-account", [
                'password' => 'CorrectPassword123!',
                'confirm' => '1',
            ]);

        $response->assertRedirect(
            "/{$this->testTenantSlug}/accessible/login?status=account-deletion-requested",
        );
        $response->assertCookieExpired('auth_token');
        $response->assertCookieExpired('accessible_refresh_token');
        $this->assertNull(app(\App\Services\TokenService::class)->validateRefreshToken($secondRefresh));
        $this->assertDatabaseMissing('refresh_token_sessions', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
    }

    private function assertAccessCookiePolicy(TestResponse $response): void
    {
        $cookie = $this->responseCookie($response, 'auth_token');
        $remaining = $cookie->getExpiresTime() - time();

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/', $cookie->getPath());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
        $this->assertGreaterThanOrEqual(14 * 60, $remaining);
        $this->assertLessThanOrEqual(16 * 60, $remaining);
    }

    private function responseCookie(TestResponse $response, string $name): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        $this->fail("Response did not contain the {$name} cookie.");
    }

    private function ensureNativePhpSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $session
     */
    private function alphaPost(string $uri, array $data = [], array $session = []): TestResponse
    {
        $csrfToken = 'accessible-rotating-session-csrf';

        return $this->withSession(array_merge($session, ['_token' => $csrfToken]))
            ->post($uri, array_merge(['_token' => $csrfToken], $data));
    }
}
