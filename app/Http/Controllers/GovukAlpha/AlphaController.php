<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha;

use App\Core\TenantContext;
use App\Core\Validator;
use App\Http\Controllers\Api\CoreController;
use App\Models\Category;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\BrokerControlConfigService;
use App\Services\CommentService;
use App\Services\ConnectionService;
use App\Services\EventService;
use App\Services\ExchangeService;
use App\Services\ExchangeWorkflowService;
use App\Services\FeedService;
use App\Services\LegalDocumentService;
use App\Services\ListingConfigurationService;
use App\Services\ListingService;
use App\Services\MessageService;
use App\Services\OnboardingConfigService;
use App\Services\RegistrationService;
use App\Services\SearchService;
use App\Services\SocialNotificationService;
use App\Services\TokenService;
use App\Services\UserService;
use App\Services\VolunteerService;
use App\Support\FeedItemTables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AlphaController extends Controller
{
    private const VALID_FEED_LIKE_TARGETS = [
        'post', 'listing', 'event', 'poll', 'goal',
        'resource', 'volunteer', 'volunteering', 'review', 'challenge', 'comment',
        'job', 'blog', 'discussion',
    ];

    /** Accessibility-need categories — mirrors the vol_accessibility_needs ENUM. */
    private const ACCESSIBILITY_NEED_TYPES = [
        'mobility', 'visual', 'hearing', 'cognitive', 'dietary', 'language', 'other',
    ];

    /** The platform's 11 supported locales (matches the React LanguageSwitcher). */
    private const ALPHA_LOCALES = [
        'en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar',
    ];

    public function __construct(
        private readonly FeedService $feedService,
        private readonly ListingService $listingService,
        private readonly RegistrationService $registrationService,
    ) {}

    /**
     * Safely coerce a request value to a string, returning the default when
     * the value is an array or non-scalar (e.g. attacker passes `?q[]=foo`).
     * Avoids PHP "Array to string conversion" warnings reported via Sentry.
     */
    private static function asStr(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }
        return $default;
    }

    public function tenantChooser(): Response|RedirectResponse
    {
        $tenant = TenantContext::get();
        if (($tenant['id'] ?? 1) > 1 && !empty($tenant['slug'])) {
            return redirect()->route('govuk-alpha.home', ['tenantSlug' => $tenant['slug']]);
        }

        $tenants = DB::table('tenants')
            ->select('id', 'name', 'slug', 'tagline')
            ->where('is_active', 1)
            ->where('id', '>', 1)
            ->orderBy('name')
            ->get()
            ->map(fn (object $tenant): array => (array) $tenant)
            ->all();

        return $this->view('accessible-frontend::tenant-chooser', [
            'title' => __('govuk_alpha.tenant_chooser.title'),
            'tenantSlug' => '',
            'activeNav' => 'tenant-chooser',
            'tenants' => $tenants,
        ]);
    }

    public function hostHome(): RedirectResponse
    {
        return $this->redirectHostTenantRoute('govuk-alpha.home');
    }

    public function hostLogin(): RedirectResponse
    {
        return $this->redirectHostTenantRoute('govuk-alpha.login');
    }

    public function hostRegister(): RedirectResponse
    {
        return $this->redirectHostTenantRoute('govuk-alpha.register');
    }

    public function hostContact(): RedirectResponse
    {
        return $this->redirectHostTenantRoute('govuk-alpha.contact');
    }

    public function home(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::home', [
            'title' => __('govuk_alpha.home.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'home',
            'isAuthenticated' => $this->currentUserId() !== null,
            'status' => self::asStr($request->query('status')) ?: null,
            // Live community stats (members / hours / listings / communities),
            // the same tenant-scoped, cached figures the about page already shows.
            'stats' => $this->platformStats(),
            'modules' => [
                'feed' => TenantContext::hasModule('feed'),
                'listings' => TenantContext::hasModule('listings'),
                'members' => TenantContext::hasFeature('connections'),
            ],
        ]);
    }

    public function login(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::login', [
            'title' => __('govuk_alpha.auth.login_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'login',
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function storeLogin(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        $previousStatelessHeader = $_SERVER['HTTP_X_STATELESS_AUTH'] ?? null;
        $_SERVER['HTTP_X_STATELESS_AUTH'] = '1';

        try {
            $response = app(\App\Http\Controllers\Api\AuthController::class)->login();
            $payload = $response->getData(true);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'login-failed']);
        } finally {
            if ($previousStatelessHeader === null) {
                unset($_SERVER['HTTP_X_STATELESS_AUTH']);
            } else {
                $_SERVER['HTTP_X_STATELESS_AUTH'] = $previousStatelessHeader;
            }
        }

        if (($payload['success'] ?? false) === true) {
            $token = (string) ($payload['access_token'] ?? $payload['token'] ?? $payload['sanctum_token'] ?? '');
            if ($token === '') {
                return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'login-failed']);
            }

            return redirect()
                ->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'signed-in'])
                ->withCookie(cookie(
                    'auth_token',
                    $token,
                    60 * 24 * 7,
                    '/',
                    null,
                    $request->isSecure(),
                    true,
                    false,
                    'Lax'
                ));
        }

        if (($payload['requires_2fa'] ?? false) === true) {
            // Carry the short-lived (5-min, single-use) challenge token to the 2FA
            // code page via the session — never in the URL. Email+password are not
            // re-sent; the token identifies the pending challenge.
            $twoFactorToken = (string) ($payload['two_factor_token'] ?? '');
            if ($twoFactorToken === '' || !$request->hasSession()) {
                return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'two-factor-required']);
            }
            $request->session()->put('alpha_2fa_token', $twoFactorToken);

            return redirect()->route('govuk-alpha.login.twofactor', ['tenantSlug' => $tenantSlug]);
        }

        // Surface the specific failure reason so the Blade page can show a
        // useful message instead of a generic "login failed" for every code
        // path (rate limit, suspended, unverified, etc.).
        $errors = $payload['errors'] ?? [];
        $code = (string) ($errors[0]['code'] ?? '');
        $status = match (true) {
            $code === 'RATE_LIMIT_EXCEEDED',
            $code === 'RATE_LIMITED'                  => 'rate-limited',
            $code === 'AUTH_EMAIL_NOT_VERIFIED'       => 'email-not-verified',
            $code === 'AUTH_PENDING_VERIFICATION'     => 'pending-verification',
            $code === 'AUTH_ACCOUNT_SUSPENDED'        => 'account-suspended',
            default                                    => 'login-failed',
        };

        return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => $status])
            ->withInput($request->only('email'));
    }

    /**
     * Resend an email-verification link. Delegates to the v2 endpoint, which
     * always returns a generic success (anti-enumeration), so the alpha shows
     * the same confirmation regardless of whether the address exists.
     */
    public function resendVerification(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        try {
            app(\App\Http\Controllers\Api\EmailVerificationController::class)->resendVerificationByEmail();
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'verification-resent']);
    }

    public function twoFactor(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        $token = $request->hasSession() ? (string) $request->session()->get('alpha_2fa_token', '') : '';
        if ($token === '') {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'two-factor-expired']);
        }

        return $this->view('accessible-frontend::two-factor', [
            'title' => __('govuk_alpha.auth.two_factor_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'login',
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function storeTwoFactor(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        $token = $request->hasSession() ? (string) $request->session()->get('alpha_2fa_token', '') : '';
        if ($token === '') {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'two-factor-expired']);
        }

        $code = trim(self::asStr($request->input('code')));
        if ($code === '') {
            return redirect()->route('govuk-alpha.login.twofactor', ['tenantSlug' => $tenantSlug, 'status' => 'two-factor-code-required']);
        }

        // Delegate to the same v2 contract the React verify2FA() uses.
        $previousStatelessHeader = $_SERVER['HTTP_X_STATELESS_AUTH'] ?? null;
        $_SERVER['HTTP_X_STATELESS_AUTH'] = '1';
        $request->merge([
            'two_factor_token' => $token,
            'code' => $code,
            'use_backup_code' => $request->boolean('use_backup_code'),
        ]);

        try {
            $response = app(\App\Http\Controllers\Api\TotpController::class)->verify();
            $payload = $response->getData(true);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('govuk-alpha.login.twofactor', ['tenantSlug' => $tenantSlug, 'status' => 'two-factor-failed']);
        } finally {
            if ($previousStatelessHeader === null) {
                unset($_SERVER['HTTP_X_STATELESS_AUTH']);
            } else {
                $_SERVER['HTTP_X_STATELESS_AUTH'] = $previousStatelessHeader;
            }
        }

        if (($payload['success'] ?? false) === true) {
            $accessToken = (string) ($payload['access_token'] ?? $payload['token'] ?? $payload['sanctum_token'] ?? '');
            if ($accessToken === '') {
                return redirect()->route('govuk-alpha.login.twofactor', ['tenantSlug' => $tenantSlug, 'status' => 'two-factor-failed']);
            }

            if ($request->hasSession()) {
                $request->session()->forget('alpha_2fa_token');
            }

            return redirect()
                ->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'signed-in'])
                ->withCookie(cookie('auth_token', $accessToken, 60 * 24 * 7, '/', null, $request->isSecure(), true, false, 'Lax'));
        }

        // The challenge is single-use with a 5-minute TTL and a 5-attempt cap — when
        // it is spent, send the member back to sign in rather than loop the code page.
        $errorCode = strtoupper((string) ($payload['errors'][0]['code'] ?? $payload['code'] ?? ''));
        if (str_contains($errorCode, 'EXPIRED') || str_contains($errorCode, 'CHALLENGE') || str_contains($errorCode, 'ATTEMPT')) {
            if ($request->hasSession()) {
                $request->session()->forget('alpha_2fa_token');
            }

            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'two-factor-expired']);
        }

        return redirect()->route('govuk-alpha.login.twofactor', ['tenantSlug' => $tenantSlug, 'status' => 'two-factor-invalid']);
    }

    public function forgotPassword(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::forgot-password', [
            'title' => __('govuk_alpha.auth.forgot_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'login',
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function sendPasswordReset(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        $email = trim(self::asStr($request->input('email')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()
                ->route('govuk-alpha.login.forgot', ['tenantSlug' => $tenantSlug, 'status' => 'forgot-invalid'])
                ->withInput();
        }

        // Delegate to the same v2 contract the React ForgotPasswordPage uses. It is
        // deliberately anti-enumerating (always 200 "if an account exists…"), so we
        // surface a single generic confirmation regardless of whether the email exists.
        $request->merge(['email' => $email]);
        $statusKey = 'forgot-sent';
        try {
            $response = app(\App\Http\Controllers\Api\PasswordResetController::class)->forgotPassword();
            if ($response->getStatusCode() === 429) {
                $statusKey = 'forgot-rate-limited';
            }
        } catch (HttpResponseException $e) {
            $statusKey = $e->getResponse()->getStatusCode() === 429 ? 'forgot-rate-limited' : 'forgot-sent';
        } catch (\Throwable $e) {
            report($e);
            // Never reveal a failure that could leak account existence.
        }

        return redirect()->route('govuk-alpha.login.forgot', ['tenantSlug' => $tenantSlug, 'status' => $statusKey]);
    }

    public function showResetPassword(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::reset-password', [
            'title' => __('govuk_alpha.auth.reset_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'login',
            'token' => self::asStr($request->query('token')),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function storeResetPassword(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        $token = trim(self::asStr($request->input('token')));
        $password = self::asStr($request->input('password'));
        $confirm = self::asStr($request->input('password_confirmation'));

        // Friendly client-side-style pre-checks before hitting the v2 contract.
        $preStatus = match (true) {
            $token === '' => 'reset-token-missing',
            $password === '' => 'reset-weak',
            mb_strlen($password) < 12 => 'reset-weak',
            $password !== $confirm => 'reset-mismatch',
            default => null,
        };
        if ($preStatus !== null) {
            return redirect()->route('govuk-alpha.password.reset', ['tenantSlug' => $tenantSlug, 'token' => $token, 'status' => $preStatus]);
        }

        $request->merge(['token' => $token, 'password' => $password, 'password_confirmation' => $confirm]);

        try {
            $response = app(\App\Http\Controllers\Api\PasswordResetController::class)->resetPassword();
            $payload = $response->getData(true);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300 && ($payload['success'] ?? false) === true) {
                return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'password-reset']);
            }

            $code = strtoupper((string) ($payload['errors'][0]['code'] ?? $payload['code'] ?? ''));
            $status = match (true) {
                str_contains($code, 'PWNED')   => 'reset-pwned',
                str_contains($code, 'REUSED')  => 'reset-reused',
                str_contains($code, 'WEAK')    => 'reset-weak',
                str_contains($code, 'TOKEN'),
                $response->getStatusCode() === 400,
                $response->getStatusCode() === 404 => 'reset-token-invalid',
                default                         => 'reset-failed',
            };
        } catch (HttpResponseException $e) {
            $status = $e->getResponse()->getStatusCode() === 429 ? 'reset-rate-limited' : 'reset-failed';
        } catch (\Throwable $e) {
            report($e);
            $status = 'reset-failed';
        }

        return redirect()->route('govuk-alpha.password.reset', ['tenantSlug' => $tenantSlug, 'token' => $token, 'status' => $status]);
    }

    public function logout(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['user_id']);
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'signed-out'])
            ->withCookie(cookie()->forget('auth_token', '/'));
    }

    public function register(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        $tenantId = TenantContext::getId();
        $policy = \App\Services\Identity\RegistrationPolicyService::getEffectivePolicy($tenantId);
        $registrationMode = (string) ($policy['registration_mode'] ?? 'open');
        $registrationClosed = $registrationMode === 'closed';
        $requiresInviteCode = $registrationMode === 'invite_only';

        // Resolve maps config to drive the Google Places autocomplete on the
        // location field. Mirrors MapsConfigController's gating: no API key
        // unless maps feature is on AND provider is google.
        $mapsEnabled = TenantContext::hasFeature('maps');
        $mapProvider = $this->resolveTenantSetting($tenantId, 'general.map_provider', 'google');
        $geocodingProvider = $this->resolveTenantSetting($tenantId, 'general.geocoding_provider', 'google');
        $tenantGoogleKey = $this->resolveTenantSetting($tenantId, 'general.google_maps_api_key', '');
        $envGoogleKey = (string) (getenv('GOOGLE_MAPS_API_KEY') ?: '');
        $googleApiKey = ($mapsEnabled && $geocodingProvider === 'google')
            ? ($tenantGoogleKey !== '' ? $tenantGoogleKey : $envGoogleKey)
            : '';

        return $this->view('accessible-frontend::register', [
            'title' => $registrationClosed
                ? __('govuk_alpha.auth.registration_closed_title')
                : __('govuk_alpha.auth.register_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'register',
            'status' => self::asStr($request->query('status')) ?: null,
            'registrationClosed' => $registrationClosed,
            'requiresInviteCode' => $requiresInviteCode,
            'googleMapsApiKey' => $googleApiKey,
            'geocodingProvider' => $geocodingProvider,
            // Server timestamp (ms) used by the min-form-time bot gate. Sent
            // back as a hidden field so we don't need a session round-trip.
            'formStartedAt' => (int) (microtime(true) * 1000),
        ]);
    }

    private function resolveTenantSetting(int $tenantId, string $key, string $default): string
    {
        if ($tenantId <= 0) {
            return $default;
        }
        try {
            $value = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', $key)
                ->value('setting_value');
            return is_string($value) && $value !== '' ? $value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public function storeRegister(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        $result = $this->registrationService->register([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'location' => $request->input('location'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
            'profile_type' => $request->input('profile_type', 'individual'),
            'organization_name' => $request->input('organization_name'),
            'invite_code' => $request->input('invite_code'),
            'terms_accepted' => $request->boolean('terms_accepted'),
            'newsletter_opt_in' => $request->boolean('newsletter_opt_in'),
            'form_started_at' => $request->input('form_started_at'),
            // Bot honeypot — hidden input in the Blade form. Real users
            // never see or fill it; bots auto-fill everything.
            'honeypot' => $request->input('website'),
        ], TenantContext::getId());

        if (isset($result['error'])) {
            // Map service-level error codes to specific Blade statuses so the
            // user sees a useful message ("you already have an account" vs a
            // password breach hit) instead of a single generic prompt.
            $code = (string) ($result['code'] ?? '');
            $status = match (true) {
                $code === 'VALIDATION_DUPLICATE'  => 'register-duplicate',
                $code === 'PASSWORD_PWNED'        => 'register-password-pwned',
                $code === 'PASSWORD_MISMATCH'     => 'register-password-mismatch',
                $code === 'TERMS_REQUIRED'        => 'register-terms-required',
                $code === 'INVITE_REQUIRED'       => 'register-invite-required',
                $code === 'INVITE_INVALID'        => 'register-invite-invalid',
                $code === 'LOCATION_NOT_VERIFIED' => 'register-location-unverified',
                $code === 'EMAIL_DISPOSABLE'      => 'register-email-disposable',
                $code === 'EMAIL_DOMAIN_INVALID'  => 'register-email-domain-invalid',
                $code === 'REGISTRATION_DAILY_LIMIT' => 'register-daily-limit',
                $code === 'REGISTRATION_TENANT_PAUSED' => 'register-tenant-paused',
                $code === 'REGISTRATION_CLOSED'    => 'register-closed',
                $code === 'VALIDATION_ERROR'      => 'register-validation',
                default                            => 'register-failed',
            };
            return redirect()->route('govuk-alpha.register', ['tenantSlug' => $tenantSlug, 'status' => $status])
                ->withInput();
        }

        return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'register-created']);
    }

    public function dashboard(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();

        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $profile = $this->profileForViewer($userId, $userId);
        abort_if($profile === null, 404);

        $feedItems = [];
        $listings = [];

        try {
            $feed = $this->feedService->getFeed($userId, [
                'limit' => 5,
                'type' => 'all',
                'mode' => 'chronological',
            ]);
            $feedItems = $feed['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        if (TenantContext::hasModule('listings')) {
            try {
                $result = $this->listingService->getAll(['limit' => 5]);
                $listings = $this->withResolvedImageKey($result['items'] ?? [], 'image_url');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Core time-credit metric + gamification + upcoming events — the React
        // dashboard surfaces all of these; the accessible one previously showed none.
        $tenantId = TenantContext::getId();
        $wallet = null;
        try {
            $wallet = app(\App\Services\WalletService::class)->getBalance($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        $gamification = null;
        $badges = [];
        try {
            $gamification = \App\Services\GamificationService::getProfile($userId, $tenantId);
            $badges = \App\Services\GamificationService::getBadges($userId, $tenantId);
        } catch (\Throwable $e) {
            report($e);
        }

        $upcomingEvents = [];
        if (TenantContext::hasFeature('events')) {
            try {
                $upcomingEvents = EventService::getAll(['when' => 'upcoming', 'limit' => 3])['items'] ?? [];
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Personalised dashboard extras the React dashboard surfaces: a pending
        // review prompt, endorsements received, and the onboarding state.
        $onboardingCompleted = (bool) DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->value('onboarding_completed');

        $pendingReviewCount = 0;
        try {
            $pendingReviewCount = (int) (app(\App\Services\ReviewService::class)
                ->getPendingReviews($userId, ['limit' => 1])['meta']['total'] ?? 0);
        } catch (\Throwable $e) {
            report($e);
        }

        $endorsements = [];
        try {
            $endorsements = \App\Services\EndorsementService::getEndorsementsForUser($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        $firstName = trim((string) ($profile['first_name'] ?? '')) ?: $this->profileDisplayName($profile);

        return $this->view('accessible-frontend::dashboard', [
            'title' => __('govuk_alpha.dashboard.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'dashboard',
            'profile' => $profile,
            'displayName' => $this->profileDisplayName($profile),
            'profileStats' => $this->profileStats($profile),
            'feedItems' => $feedItems,
            'listings' => $listings,
            'wallet' => $wallet,
            'gamification' => $gamification,
            'badges' => $badges,
            'upcomingEvents' => $upcomingEvents,
            'onboardingCompleted' => $onboardingCompleted,
            'pendingReviewCount' => $pendingReviewCount,
            'endorsements' => $endorsements,
            'firstName' => $firstName,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function contact(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        $user = null;
        $userId = $this->currentUserId();
        if ($userId !== null) {
            $user = DB::table('users')
                ->select('name', 'first_name', 'last_name', 'email')
                ->where('id', $userId)
                ->where('tenant_id', TenantContext::getId())
                ->first();
        }

        return $this->view('accessible-frontend::contact', [
            'title' => __('govuk_alpha.contact.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'contact',
            'status' => self::asStr($request->query('status')) ?: null,
            'contactUser' => $user ? (array) $user : null,
            'turnstileSiteKey' => (string) env('TURNSTILE_SITE_KEY', ''),
        ]);
    }

    public function storeContact(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        // Cloudflare Turnstile gate on contact form submissions.
        $turnstileToken = $request->input('cf-turnstile-response');
        if (! app(\App\Services\TurnstileService::class)->verify($turnstileToken, $request->ip())) {
            return redirect()
                ->route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug, 'status' => 'contact-turnstile-failed'])
                ->withErrors(['turnstile' => __('api.turnstile_failed')])
                ->withInput();
        }

        $name = trim(self::asStr($request->input('name')));
        $email = trim(self::asStr($request->input('email')));
        $message = trim(self::asStr($request->input('message')));

        $errors = [];
        if ($name === '') {
            $errors['name'] = __('govuk_alpha.contact.errors.name_required');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('govuk_alpha.contact.errors.email_required');
        }
        if ($message === '') {
            $errors['message'] = __('govuk_alpha.contact.errors.message_required');
        }

        if ($errors !== []) {
            return redirect()
                ->route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug, 'status' => 'contact-validation'])
                ->withErrors($errors)
                ->withInput();
        }

        $request->merge([
            'name' => $name,
            'email' => $email,
            'subject' => trim(self::asStr($request->input('subject'))) ?: __('govuk_alpha.contact.form.subjects.general'),
            'message' => $message,
        ]);

        try {
            $response = app(CoreController::class)->apiSubmit();
        } catch (HttpResponseException $e) {
            $status = $e->getResponse()->getStatusCode() === 429 ? 'contact-rate-limited' : 'contact-failed';

            return redirect()
                ->route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug, 'status' => $status])
                ->withInput();
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug, 'status' => 'contact-failed'])
                ->withInput();
        }

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return redirect()->route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug, 'status' => 'contact-sent']);
        }

        return redirect()
            ->route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug, 'status' => 'contact-failed'])
            ->withInput();
    }

    public function about(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);
        $communityName = (string) (TenantContext::get()['name'] ?? $tenantSlug);

        return $this->view('accessible-frontend::about', [
            'title' => __('govuk_alpha.about.title', ['name' => $communityName]),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'about',
            'stats' => $this->platformStats(),
            'contributors' => $this->aboutContributors(),
        ]);
    }

    public function trustSafety(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::trust-safety', [
            'title' => __('govuk_alpha.trust_safety.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'trust-safety',
        ]);
    }

    public function accessibility(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::accessibility', [
            'title' => __('govuk_alpha.accessibility.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'accessibility',
        ]);
    }

    public function legalHub(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::legal-hub', [
            'title' => __('govuk_alpha.legal.hub_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'legal',
        ]);
    }

    public function legalDocument(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        // The document type is supplied via route defaults (see routes/govuk-alpha.php).
        $type = self::asStr($request->route('type'));
        $allowed = ['terms', 'privacy', 'cookies', 'community_guidelines', 'acceptable_use'];
        abort_unless(in_array($type, $allowed, true), 404);

        return $this->view('accessible-frontend::legal-document', [
            'title' => __('govuk_alpha.legal.documents.' . $type . '.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'legal',
            'docType' => $type,
            // Tenant-managed document (admin-editable, same source as the React app),
            // or null when none is published — the view renders a GOV.UK fallback.
            'document' => LegalDocumentService::getDocument($type),
        ]);
    }

    public function help(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        $search = trim(self::asStr($request->query('q')));
        $groups = app(\App\Services\HelpService::class)
            ->getFaqs(TenantContext::getId(), null, $search !== '' ? $search : null);

        return $this->view('accessible-frontend::help', [
            'title' => __('govuk_alpha.help.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'help',
            'faqGroups' => $groups,
            'searchQuery' => $search,
        ]);
    }

    public function kb(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        $search = trim(self::asStr($request->query('q')));
        $cursor = self::asStr($request->query('cursor')) ?: null;
        $service = app(\App\Services\KnowledgeBaseService::class);

        if ($search !== '') {
            $result = ['items' => $service->search($search, 20), 'cursor' => null, 'has_more' => false];
        } else {
            $result = $service->getAll([
                'limit' => 12,
                'cursor' => $cursor,
                'published_only' => true,
            ]);
        }

        return $this->view('accessible-frontend::kb-index', [
            'title' => __('govuk_alpha.kb.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'kb',
            'articles' => $result['items'] ?? [],
            'hasMore' => (bool) ($result['has_more'] ?? false),
            'nextCursor' => $result['cursor'] ?? null,
            'searchQuery' => $search,
        ]);
    }

    public function kbArticle(Request $request, string $tenantSlug, int $id): Response
    {
        $this->assertTenantSlug($tenantSlug);

        $article = app(\App\Services\KnowledgeBaseService::class)->getById($id, true);
        abort_if($article === null, 404);

        return $this->view('accessible-frontend::kb-article', [
            'title' => (string) ($article['title'] ?? __('govuk_alpha.kb.title')),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'kb',
            'article' => $article,
        ]);
    }

    public function blog(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        $search = trim(self::asStr($request->query('q')));
        $cursor = self::asStr($request->query('cursor')) ?: null;
        $categoryRaw = self::asStr($request->query('category'));
        $categoryId = ctype_digit($categoryRaw) ? (int) $categoryRaw : null;

        $service = app(\App\Services\BlogService::class);
        $result = $service->getAll([
            'limit' => 12,
            'cursor' => $cursor,
            'search' => $search !== '' ? $search : null,
            'category_id' => $categoryId,
        ]);

        return $this->view('accessible-frontend::blog-index', [
            'title' => __('govuk_alpha.blog.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'blog',
            'posts' => $result['items'] ?? [],
            'categories' => $service->getCategories(),
            'hasMore' => (bool) ($result['has_more'] ?? false),
            'nextCursor' => $result['cursor'] ?? null,
            'searchQuery' => $search,
            'categoryId' => $categoryId,
        ]);
    }

    public function blogPost(Request $request, string $tenantSlug, string $slug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        $post = app(\App\Services\BlogService::class)->getBySlug($slug);
        abort_if($post === null, 404);

        return $this->view('accessible-frontend::blog-post', [
            'title' => (string) ($post['title'] ?? __('govuk_alpha.blog.title')),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'blog',
            'post' => $post,
        ]);
    }

    public function events(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);
        if (!TenantContext::hasFeature('events')) {
            return $this->view('accessible-frontend::events', [
                'title' => __('govuk_alpha.events.title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'items' => [],
                'categories' => [],
                'filters' => $this->eventFilters($request),
                'meta' => ['has_more' => false, 'cursor' => null],
                'moduleDisabled' => true,
                'error' => null,
            ], 403);
        }

        $filters = $this->eventFilters($request);
        $query = [
            'limit' => 12,
            'when' => $filters['when'],
        ];

        foreach (['category_id', 'search', 'cursor'] as $key) {
            if ($filters[$key] !== null && $filters[$key] !== '') {
                $query[$key] = $filters[$key];
            }
        }

        $items = [];
        $meta = ['has_more' => false, 'cursor' => null];
        $error = null;

        try {
            $result = EventService::getAll($query);
            $items = $this->withResolvedImageKey($result['items'] ?? [], 'cover_image');
            $meta = [
                'has_more' => (bool) ($result['has_more'] ?? false),
                'cursor' => $result['cursor'] ?? null,
            ];
        } catch (\Throwable $e) {
            report($e);
            $error = __('govuk_alpha.states.error_title');
        }

        return $this->view('accessible-frontend::events', [
            'title' => __('govuk_alpha.events.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'items' => $items,
            'categories' => $this->categoriesForTypes(['events', 'event']),
            'filters' => $filters,
            'meta' => $meta,
            'moduleDisabled' => false,
            'error' => $error,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function event(string $tenantSlug, int $id): Response
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);

        $viewerId = $this->currentUserId();
        $event = EventService::getById($id, $viewerId);
        abort_if($event === null, 404);

        $event['cover_image'] = $this->resolveAsset($event['cover_image'] ?? null);

        // Attendee roster (going + interested). EventService::getAttendees
        // self-enforces roster privacy, returning an empty list when the viewer
        // may not see it.
        $attendees = [];
        try {
            $attendees = EventService::getAttendees($id, ['status' => 'all', 'limit' => 50], $viewerId)['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::event-detail', [
            'title' => $event['title'] ?? __('govuk_alpha.events.detail_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'requiresAuth' => $viewerId === null,
            'isOwner' => $viewerId !== null && (int) ($event['user_id'] ?? 0) === $viewerId,
            'attendees' => $attendees,
            'status' => self::asStr(request()->query('status')) ?: null,
            'ogImage' => $this->absoluteAssetUrl($event['cover_image'] ?? null),
            'ogImageAlt' => $event['cover_image'] ? ($event['title'] ?? null) : null,
        ]);
    }

    public function createEvent(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);

        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $this->view('accessible-frontend::event-create', [
            'title' => __('govuk_alpha.events.create_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'categories' => $this->categoriesForTypes(['events', 'event']),
            'status' => $request->query('status') ?: session('status'),
        ]);
    }

    public function storeEvent(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        try {
            $event = EventService::create($userId, $this->eventInput($request));
            $eventId = (int) $event->id;

            $this->attachEventCoverImage($request, $eventId, $userId);

            try {
                \App\Services\GamificationService::awardXP($userId, \App\Services\GamificationService::XP_VALUES['create_event'], 'create_event', __('govuk_alpha.events.gamification_reason'));
            } catch (\Throwable $e) {
                Log::warning('Accessible event XP award failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }

            try {
                app(\App\Services\FeedActivityService::class)->recordActivity(
                    TenantContext::getId(),
                    $userId,
                    'event',
                    $eventId,
                    [
                        'title' => $event->title,
                        'content' => $event->description,
                        'image_url' => $event->image_url,
                        'group_id' => $event->group_id,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Accessible event feed activity failed', ['event_id' => $eventId, 'error' => $e->getMessage()]);
            }
        } catch (ValidationException $e) {
            // Forward the per-field error bag so the create form can render an
            // anchored govuk-error-summary + inline govuk-error-message per field.
            return redirect()
                ->route('govuk-alpha.events.create', ['tenantSlug' => $tenantSlug])
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('govuk-alpha.events.create', ['tenantSlug' => $tenantSlug, 'status' => 'event-create-failed'])
                ->withInput();
        }

        return redirect()->route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'status' => 'event-created']);
    }

    /**
     * Resolve an event the current user owns, or null (with the right HTTP abort)
     * for the organiser-only edit/cancel/delete flows.
     */
    private function ownedEventOrAbort(string $tenantSlug, int $id): array
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        abort_if($userId === null, 401);

        $event = EventService::getById($id, $userId);
        abort_if($event === null, 404);
        abort_unless((int) ($event['user_id'] ?? 0) === $userId, 403);

        return [$event, $userId];
    }

    public function editEvent(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        $event = EventService::getById($id, $userId);
        abort_if($event === null, 404);
        abort_unless((int) ($event['user_id'] ?? 0) === $userId, 403);

        return $this->view('accessible-frontend::event-edit', [
            'title' => __('govuk_alpha.events.edit_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'categories' => $this->categoriesForTypes(['events', 'event']),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function updateEvent(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        [, $userId] = $this->ownedEventOrAbort($tenantSlug, $id);

        try {
            $ok = EventService::update($id, $userId, $this->eventInput($request));
        } catch (ValidationException $e) {
            return redirect()
                ->route('govuk-alpha.events.edit', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        return redirect()->route('govuk-alpha.events.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'event-updated' : 'event-update-failed',
        ]);
    }

    public function cancelEvent(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        [, $userId] = $this->ownedEventOrAbort($tenantSlug, $id);

        $reason = trim(self::asStr($request->input('reason')));
        $ok = false;
        try {
            $ok = EventService::cancelEvent($id, $userId, $reason);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.events.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'event-cancelled' : 'event-cancel-failed',
        ]);
    }

    public function deleteEvent(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        [, $userId] = $this->ownedEventOrAbort($tenantSlug, $id);

        $ok = false;
        try {
            $ok = EventService::delete($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        // The event no longer exists on success, so return to the events list.
        return redirect()->route('govuk-alpha.events.index', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'event-deleted' : 'event-delete-failed',
        ]);
    }

    public function storeEventRsvp(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $status = $this->allowed($request->input('status', 'going'), ['going', 'interested', 'not_going'], 'going');

        try {
            if (!EventService::rsvp($id, $userId, $status)) {
                return redirect()->route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'rsvp-failed']);
            }
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'rsvp-failed']);
        }

        return redirect()->route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'rsvp-updated']);
    }

    public function volunteering(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->view('accessible-frontend::volunteering', [
                'title' => __('govuk_alpha.volunteering.title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'volunteering',
                'items' => [],
                'categories' => [],
                'filters' => $this->volunteeringFilters($request),
                'meta' => ['has_more' => false, 'cursor' => null],
                'moduleDisabled' => true,
                'error' => null,
                'requiresAuth' => $this->currentUserId() === null,
                'hoursSummary' => null,
                'applications' => [],
                'organizations' => [],
                'selectedTab' => 'opportunities',
            ], 403);
        }

        $filters = $this->volunteeringFilters($request);
        $selectedTab = $this->allowed($request->query('tab', 'opportunities'), ['opportunities', 'applications', 'organisations', 'recommended'], 'opportunities');
        $query = ['limit' => 12];
        foreach (['category_id', 'search', 'cursor', 'is_remote'] as $key) {
            if ($filters[$key] !== null && $filters[$key] !== '') {
                $query[$key] = $filters[$key];
            }
        }

        $items = [];
        $meta = ['has_more' => false, 'cursor' => null];
        $error = null;
        $userId = $this->currentUserId();

        try {
            $result = VolunteerService::getOpportunities($query);
            $items = $result['items'] ?? [];
            $meta = [
                'has_more' => (bool) ($result['has_more'] ?? false),
                'cursor' => $result['cursor'] ?? null,
            ];
        } catch (\Throwable $e) {
            report($e);
            $error = __('govuk_alpha.states.error_title');
        }

        // Applications tab: status filter + cursor pagination (was a fixed top-5).
        $applicationsStatus = $this->allowed($request->query('app_status'), ['pending', 'approved', 'declined', 'withdrawn'], null);
        $applications = ['items' => [], 'cursor' => null, 'has_more' => false];
        if ($userId !== null) {
            $applicationsQuery = ['limit' => 10];
            if ($applicationsStatus !== null) {
                $applicationsQuery['status'] = $applicationsStatus;
            }
            $applicationsCursor = self::asStr($request->query('app_cursor'));
            if ($applicationsCursor !== '') {
                $applicationsQuery['cursor'] = $applicationsCursor;
            }
            $applications = VolunteerService::getMyApplications($userId, $applicationsQuery);
        }

        // Recommended (skills-based) shifts — read-only "For you" tab.
        $recommendedShifts = [];
        if ($userId !== null && $selectedTab === 'recommended') {
            try {
                $recommendedShifts = app(\App\Services\VolunteerMatchingService::class)->getRecommendedShifts($userId, ['limit' => 10]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->view('accessible-frontend::volunteering', [
            'title' => __('govuk_alpha.volunteering.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'items' => $items,
            'categories' => $this->categoriesForTypes(['volunteering', 'volunteer']),
            'filters' => $filters,
            'meta' => $meta,
            'moduleDisabled' => false,
            'error' => $error,
            'requiresAuth' => $userId === null,
            'hoursSummary' => $userId ? VolunteerService::getHoursSummary($userId) : null,
            'applications' => $applications['items'] ?? [],
            'applicationsMeta' => ['has_more' => (bool) ($applications['has_more'] ?? false), 'cursor' => $applications['cursor'] ?? null],
            'applicationsStatus' => $applicationsStatus,
            'organizations' => $userId ? (VolunteerService::getMyOrganizations($userId, ['limit' => 5])['items'] ?? []) : [],
            'recommendedShifts' => $recommendedShifts,
            'selectedTab' => $selectedTab,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function volunteerOpportunity(Request $request, string $tenantSlug, int $id): Response
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $opportunity = VolunteerService::getOpportunityById($id, $this->currentUserId());
        abort_if($opportunity === null, 404);

        // There is no opportunity-level image in the payload; the organisation logo
        // is the only image React shows here too. Resolve it to a same-origin URL.
        $orgLogo = $this->resolveAsset($opportunity['organization']['logo_url'] ?? null);
        $opportunity['organization']['logo_url'] = $orgLogo;

        return $this->view('accessible-frontend::volunteer-opportunity', [
            'title' => $opportunity['title'] ?? __('govuk_alpha.volunteering.detail_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'opportunity' => $opportunity,
            'requiresAuth' => $this->currentUserId() === null,
            'status' => self::asStr($request->query('status')) ?: null,
            'ogImage' => $this->absoluteAssetUrl($orgLogo),
            'ogImageAlt' => $orgLogo ? ($opportunity['organization']['name'] ?? null) : null,
        ]);
    }

    public function applyVolunteerOpportunity(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        try {
            VolunteerService::apply($id, $userId, [
                'message' => trim(self::asStr($request->input('message'))),
                'shift_id' => $request->input('shift_id') ? (int) $request->input('shift_id') : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'apply-failed']);
        }

        return redirect()->route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'apply-created']);
    }

    public function signUpForVolunteerShift(Request $request, string $tenantSlug, int $id, int $shiftId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $ok = false;
        try {
            $ok = VolunteerService::signUpForShift($shiftId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'shift-signed-up' : 'shift-signup-failed',
        ]);
    }

    public function cancelVolunteerShift(Request $request, string $tenantSlug, int $id, int $shiftId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $ok = false;
        try {
            $ok = VolunteerService::cancelShiftSignup($shiftId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'shift-cancelled' : 'shift-cancel-failed',
        ]);
    }

    public function withdrawVolunteerApplication(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $ok = false;
        try {
            $ok = VolunteerService::withdrawApplication($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.index', [
            'tenantSlug' => $tenantSlug,
            'tab' => 'applications',
            'status' => $ok ? 'application-withdrawn' : 'application-withdraw-failed',
        ]);
    }

    public function volunteeringHours(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $applications = VolunteerService::getMyApplications($userId, ['limit' => 50, 'status' => 'approved'])['items'] ?? [];
        $organizations = VolunteerService::getMyOrganizations($userId, ['limit' => 50])['items'] ?? [];

        return $this->view('accessible-frontend::volunteering-hours', [
            'title' => __('govuk_alpha.volunteering.hours_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'summary' => VolunteerService::getHoursSummary($userId),
            'logs' => VolunteerService::getMyHours($userId, ['limit' => 10])['items'] ?? [],
            'organizations' => $this->volunteeringHourOrganizations($organizations, $applications),
            'applications' => $applications,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function storeVolunteeringHours(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        try {
            $logId = VolunteerService::logHours($userId, [
                'organization_id' => (int) $request->input('organization_id'),
                'opportunity_id' => $request->input('opportunity_id') ? (int) $request->input('opportunity_id') : null,
                'date' => $request->input('date'),
                'hours' => (float) $request->input('hours'),
                'description' => trim(self::asStr($request->input('description'))),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $logId = null;
        }

        if ($logId === null) {
            return redirect()->route('govuk-alpha.volunteering.hours', ['tenantSlug' => $tenantSlug, 'status' => 'hours-failed']);
        }

        return redirect()->route('govuk-alpha.volunteering.hours', ['tenantSlug' => $tenantSlug, 'status' => 'hours-created']);
    }

    public function volunteerAccessibility(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $needs = \App\Services\VolunteerFormService::getAccessibilityNeeds($userId, TenantContext::getId());

        // HTML-first simplification: surface the set of need categories plus one
        // shared set of detail fields (the React tab supports per-need detail; the
        // accessible form keeps it to a single, clearer form). Read back the first
        // non-empty value for each shared field.
        $selectedTypes = [];
        $shared = ['description' => '', 'accommodations' => '', 'emergency_name' => '', 'emergency_phone' => ''];
        foreach ($needs as $need) {
            $type = (string) ($need['need_type'] ?? '');
            if (in_array($type, self::ACCESSIBILITY_NEED_TYPES, true)) {
                $selectedTypes[] = $type;
            }
            foreach ([
                'description' => 'description',
                'accommodations' => 'accommodations_required',
                'emergency_name' => 'emergency_contact_name',
                'emergency_phone' => 'emergency_contact_phone',
            ] as $viewKey => $rowKey) {
                if ($shared[$viewKey] === '' && !empty($need[$rowKey])) {
                    $shared[$viewKey] = (string) $need[$rowKey];
                }
            }
        }

        return $this->view('accessible-frontend::volunteering-accessibility', [
            'title' => __('govuk_alpha.volunteering.accessibility_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'needTypes' => self::ACCESSIBILITY_NEED_TYPES,
            'selectedTypes' => array_values(array_unique($selectedTypes)),
            'accessibility' => $shared,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function updateVolunteerAccessibility(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $rawTypes = $request->input('need_types', []);
        $rawTypes = is_array($rawTypes) ? array_map('strval', $rawTypes) : [];
        $selected = array_values(array_intersect(self::ACCESSIBILITY_NEED_TYPES, $rawTypes));

        $sharedDetail = [
            'description' => trim(self::asStr($request->input('description'))) ?: null,
            'accommodations_required' => trim(self::asStr($request->input('accommodations_required'))) ?: null,
            'emergency_contact_name' => trim(self::asStr($request->input('emergency_contact_name'))) ?: null,
            'emergency_contact_phone' => trim(self::asStr($request->input('emergency_contact_phone'))) ?: null,
        ];

        $needs = array_map(
            static fn (string $type): array => array_merge(['need_type' => $type], $sharedDetail),
            $selected
        );

        $ok = false;
        try {
            $ok = \App\Services\VolunteerFormService::updateAccessibilityNeeds($userId, $needs, TenantContext::getId());
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.accessibility', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'accessibility-saved' : 'accessibility-failed',
        ]);
    }

    public function feed(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        $type = $this->allowed($request->query('type', 'all'), ['all', 'following', 'saved', 'posts', 'listings', 'events', 'goals', 'polls', 'jobs', 'challenges', 'volunteering', 'blogs', 'discussions'], 'all');
        $mode = $this->allowed($request->query('mode', 'ranking'), ['ranking', 'recent'], 'ranking');
        $subtype = $type === 'listings'
            ? $this->allowed($request->query('subtype'), ['offer', 'request'], null)
            : null;
        $perPage = $this->intQuery($request, 'per_page', 10, 1, 50);

        $items = [];
        $meta = ['has_more' => false, 'cursor' => null, 'per_page' => $perPage];
        $error = null;

        if ($userId !== null) {
            try {
                $result = $this->feedService->getFeed($userId, [
                    'limit' => $perPage,
                    'type' => $type,
                    'mode' => $mode === 'recent' ? 'recent' : 'ranked',
                    'subtype' => $subtype,
                    'cursor' => self::asStr($request->query('cursor')) ?: null,
                ]);
                $items = $this->attachPostMedia($result['items'] ?? []);
                $meta = [
                    'has_more' => (bool) ($result['has_more'] ?? false),
                    'cursor' => $result['cursor'] ?? null,
                    'per_page' => $perPage,
                ];
            } catch (\Throwable $e) {
                report($e);
                $error = __('govuk_alpha.states.error_title');
            }
        }

        return $this->view('accessible-frontend::feed', [
            'title' => __('govuk_alpha.feed.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'feed',
            'items' => $items,
            'commentsByTarget' => $this->commentsForFeedItems($items, $userId),
            'meta' => $meta,
            'selectedType' => $type,
            'selectedMode' => $mode,
            'selectedSubtype' => $subtype,
            'requiresAuth' => $userId === null,
            'currentUserId' => $userId,
            'error' => $error,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function storeFeedPost(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]);
        }

        $content = trim(self::asStr($request->input('content')));
        if ($content === '') {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'post-empty']);
        }

        try {
            $post = $this->feedService->createPost($userId, [
                'content' => $content,
                'visibility' => 'public',
            ]);
            if (is_array($post) && isset($post['error'])) {
                return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'post-failed']);
            }

            // Optional photo — attach via the shared post-media pipeline (validates,
            // strips EXIF, builds a thumbnail). Best-effort: a bad image must not
            // discard the text the member already wrote.
            if ($post instanceof \App\Models\FeedPost && $request->hasFile('image')) {
                $this->attachFeedPostImage($request, (int) $post->id);
            }
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'post-failed']);
        }

        return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'post-created']);
    }

    private function attachFeedPostImage(Request $request, int $postId): void
    {
        $file = $request->file('image');
        if ($file === null || is_array($file) || !$file->isValid()) {
            return;
        }

        try {
            app(\App\Services\PostMediaService::class)->attachMedia(
                $postId,
                [$file],
                [trim(self::asStr($request->input('image_alt')))]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function storeFeedPollVote(Request $request, string $tenantSlug, int $pollId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return $this->redirectToFeed($request, $tenantSlug, 'auth-required', 'poll', $pollId);
        }

        $optionId = (int) $request->input('option_id');
        if ($optionId <= 0) {
            return $this->redirectToFeed($request, $tenantSlug, 'poll-vote-failed', 'poll', $pollId);
        }

        $ok = false;
        try {
            $ok = \App\Services\PollService::vote($pollId, $optionId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->redirectToFeed($request, $tenantSlug, $ok ? 'poll-voted' : 'poll-vote-failed', 'poll', $pollId);
    }

    public function storeFeedLike(Request $request, string $tenantSlug, string $type, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return $this->redirectToFeed($request, $tenantSlug, 'auth-required', $type, $id);
        }

        $targetType = $this->normalizeFeedTargetType($type);
        if (!in_array($targetType, self::VALID_FEED_LIKE_TARGETS, true) || !FeedItemTables::canView($targetType, $id, $userId)) {
            return $this->redirectToFeed($request, $tenantSlug, 'like-failed', $targetType, $id);
        }

        try {
            $result = $this->toggleFeedItemLike($targetType, $id, $userId);
            return $this->redirectToFeed($request, $tenantSlug, $result['liked'] ? 'like-added' : 'like-removed', $targetType, $id);
        } catch (\Throwable $e) {
            report($e);
            return $this->redirectToFeed($request, $tenantSlug, 'like-failed', $targetType, $id);
        }
    }

    public function storeFeedComment(Request $request, string $tenantSlug, string $type, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return $this->redirectToFeed($request, $tenantSlug, 'auth-required', $type, $id);
        }

        $targetType = $this->normalizeFeedTargetType($type);
        $content = trim(self::asStr($request->input('content')));

        if ($content === '') {
            return $this->redirectToFeed($request, $tenantSlug, 'comment-empty', $targetType, $id);
        }

        if (mb_strlen($content) > 10000) {
            return $this->redirectToFeed($request, $tenantSlug, 'comment-too-long', $targetType, $id);
        }

        if (!FeedItemTables::isCommentable($targetType) || !FeedItemTables::canView($targetType, $id, $userId)) {
            return $this->redirectToFeed($request, $tenantSlug, 'comment-failed', $targetType, $id);
        }

        $data = ['content' => $content];
        // A reply carries the parent comment id; CommentService::create enforces
        // the nesting-depth limit and that the parent belongs to the same target.
        $parentId = (int) $request->input('parent_id', 0);
        if ($parentId > 0) {
            $data['parent_id'] = $parentId;
        }

        try {
            CommentService::create($targetType, $id, $userId, TenantContext::getId(), $data);
        } catch (\Throwable $e) {
            report($e);
            return $this->redirectToFeed($request, $tenantSlug, 'comment-failed', $targetType, $id);
        }

        return $this->redirectToFeed($request, $tenantSlug, 'comment-created', $targetType, $id);
    }

    public function updateFeedPost(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $content = trim(self::asStr($request->input('content')));
        if ($content === '') {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'post-empty']);
        }

        $status = 'post-update-failed';
        try {
            $result = $this->feedService->updatePost($id, $userId, ['content' => $content]);
            $status = ! empty($result['success']) ? 'post-updated' : 'post-update-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    public function deleteFeedPost(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $status = 'post-delete-failed';
        try {
            $status = $this->feedService->deletePost($id, $userId) ? 'post-deleted' : 'post-delete-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    /** Hide a feed item from the viewer's own feed (any feed item type). */
    public function hideFeedItem(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $validTypes = ['post', 'listing', 'event', 'poll', 'goal', 'review', 'job', 'challenge', 'volunteer', 'resource', 'blog', 'discussion'];
        $type = in_array($request->input('type'), $validTypes, true) ? (string) $request->input('type') : 'post';

        $status = 'moderation-failed';
        if ($id > 0 && FeedItemTables::canView($type, $id, $userId)) {
            try {
                DB::table('feed_hidden')->insertOrIgnore([
                    'user_id'     => $userId,
                    'tenant_id'   => TenantContext::getId(),
                    'target_type' => $type,
                    'target_id'   => $id,
                    'created_at'  => now(),
                ]);
                $status = 'content-hidden';
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    /** Mute another member so their content stops appearing in the viewer's feed. */
    public function muteFeedUser(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $status = 'moderation-failed';
        if ($id > 0 && $id !== $userId && DB::table('users')->where('id', $id)->where('tenant_id', $tenantId)->exists()) {
            try {
                DB::table('feed_muted_users')->insertOrIgnore([
                    'user_id'       => $userId,
                    'tenant_id'     => $tenantId,
                    'muted_user_id' => $id,
                    'created_at'    => now(),
                ]);
                $status = 'author-muted';
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    /** Report a feed item to moderators (mirrors SocialController::reportItemV2). */
    public function reportFeedItem(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $rawType = (string) $request->input('type', 'post');
        $type = $rawType === 'volunteering' ? 'volunteer' : $rawType;
        $reason = trim(self::asStr($request->input('reason')));

        $status = 'moderation-failed';
        if (
            in_array($type, \App\Services\ReactionService::VALID_TARGET_TYPES, true)
            && $id > 0
            && $reason !== ''
            && FeedItemTables::canView($type, $id, $userId)
        ) {
            $reason = mb_substr($reason, 0, 1000);
            $already = DB::table('reports')
                ->where('reporter_id', $userId)->where('tenant_id', $tenantId)
                ->where('target_type', $type)->where('target_id', $id)->exists();

            if ($already) {
                // Idempotent from the member's perspective — already flagged.
                $status = 'content-reported';
            } else {
                try {
                    $reportId = DB::table('reports')->insertGetId([
                        'reporter_id' => $userId,
                        'tenant_id'   => $tenantId,
                        'target_type' => $type,
                        'target_id'   => $id,
                        'reason'      => $reason,
                        'status'      => 'open',
                        'created_at'  => now(),
                    ]);
                    try {
                        \App\Services\NotificationDispatcher::notifyModerationAdmins(
                            'social_report_created',
                            '/admin/reports',
                            'svc_notifications_2.social_report.admin_alert',
                            'emails_misc.moderation.social_subject',
                            'emails_misc.moderation.social_body',
                            ['target_type' => $type, 'reason' => $reason, 'report_id' => $reportId]
                        );
                    } catch (\Throwable $notifyError) {
                        Log::warning('Alpha social report admin alert failed: ' . $notifyError->getMessage());
                    }
                    $status = 'content-reported';
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    public function updateFeedComment(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $content = trim(self::asStr($request->input('content')));
        if ($content === '') {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'comment-empty']);
        }

        $status = 'comment-update-failed';
        try {
            // CommentService::update is owner-scoped and returns the new content on
            // success, null when the comment is missing or not the user's own.
            $status = CommentService::update($id, $userId, $content) !== null ? 'comment-updated' : 'comment-update-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    public function deleteFeedComment(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $status = 'comment-delete-failed';
        try {
            $status = CommentService::delete($id, $userId) > 0 ? 'comment-deleted' : 'comment-delete-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    /**
     * Enrich feed post items with their attached images from post_media.
     * FeedService leaves top-level posts without media; the accessible
     * frontend has no API client, so we load it here.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function attachPostMedia(array $items): array
    {
        $postIds = [];
        foreach ($items as $item) {
            if (($item['type'] ?? null) === 'post' && !empty($item['id'])) {
                $postIds[] = (int) $item['id'];
            }
        }
        if ($postIds === []) {
            return $items;
        }

        $rows = DB::table('post_media')
            ->whereIn('post_id', $postIds)
            ->where('media_type', 'image')
            ->orderBy('post_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['post_id', 'file_url', 'thumbnail_url', 'alt_text']);

        $mediaByPost = [];
        foreach ($rows as $row) {
            $mediaByPost[(int) $row->post_id][] = [
                'file_url' => $row->file_url,
                'thumbnail_url' => $row->thumbnail_url,
                'alt_text' => $row->alt_text,
            ];
        }

        foreach ($items as &$item) {
            if (($item['type'] ?? null) === 'post' && !empty($item['id'])) {
                $item['media'] = $mediaByPost[(int) $item['id']] ?? [];
            }
        }
        unset($item);

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<int, array<int, array<string, mixed>>>>
     */
    private function commentsForFeedItems(array $items, ?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        $commentsByTarget = [];
        foreach ($items as $item) {
            $targetType = $this->normalizeFeedTargetType((string) ($item['type'] ?? ''));
            $targetId = (int) ($item['id'] ?? 0);
            if ($targetId <= 0 || !FeedItemTables::isCommentable($targetType)) {
                continue;
            }

            try {
                $commentsByTarget[$targetType][$targetId] = CommentService::getForEntity($targetType, $targetId, $userId);
            } catch (\Throwable $e) {
                report($e);
                $commentsByTarget[$targetType][$targetId] = [];
            }
        }

        return $commentsByTarget;
    }

    /**
     * @return array{liked: bool, likes_count: int}
     */
    private function toggleFeedItemLike(string $targetType, int $targetId, int $userId): array
    {
        $tenantId = TenantContext::getId();

        $existing = DB::table('likes')
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing) {
            DB::table('likes')
                ->where('id', $existing->id)
                ->where('tenant_id', $tenantId)
                ->delete();

            if ($targetType === 'post') {
                DB::table('feed_posts')
                    ->where('id', $targetId)
                    ->where('tenant_id', $tenantId)
                    ->update(['likes_count' => DB::raw('GREATEST(likes_count - 1, 0)')]);
            }

            $liked = false;
        } else {
            $affected = DB::affectingStatement(
                'INSERT IGNORE INTO likes (user_id, target_type, target_id, tenant_id, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$userId, $targetType, $targetId, $tenantId]
            );

            if ($affected > 0 && $targetType === 'post') {
                $updated = DB::table('feed_posts')
                    ->where('id', $targetId)
                    ->where('tenant_id', $tenantId)
                    ->increment('likes_count');

                if ($updated === 0) {
                    DB::table('likes')
                        ->where('user_id', $userId)
                        ->where('target_type', $targetType)
                        ->where('target_id', $targetId)
                        ->where('tenant_id', $tenantId)
                        ->delete();
                }
            }

            $liked = true;

            try {
                $contentOwnerId = SocialNotificationService::getContentOwnerId($targetType, $targetId);
                if ($contentOwnerId && $contentOwnerId !== $userId) {
                    SocialNotificationService::notifyLike(
                        $contentOwnerId,
                        $userId,
                        $targetType,
                        $targetId,
                        SocialNotificationService::getContentPreview($targetType, $targetId)
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('AlphaController::toggleFeedItemLike notification failed', ['error' => $e->getMessage()]);
            }
        }

        $count = (int) DB::table('likes')
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('tenant_id', $tenantId)
            ->count();

        return ['liked' => $liked, 'likes_count' => $count];
    }

    private function normalizeFeedTargetType(string $targetType): string
    {
        return $targetType === 'volunteering' ? 'volunteer' : $targetType;
    }

    private function redirectToFeed(Request $request, string $tenantSlug, string $status, ?string $targetType = null, ?int $targetId = null): RedirectResponse
    {
        $query = ['tenantSlug' => $tenantSlug, 'status' => $status];
        foreach (['type', 'mode', 'subtype', 'per_page', 'cursor'] as $key) {
            $value = $request->input($key, $request->query($key));
            if ($value !== null && $value !== '') {
                $query[$key] = $value;
            }
        }

        $url = route('govuk-alpha.feed', $query);
        if ($targetType !== null && $targetId !== null) {
            $url .= '#feed-item-' . preg_replace('/[^a-z0-9_-]/i', '-', $targetType) . '-' . $targetId;
        }

        return redirect()->to($url);
    }

    public function listings(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);
        if (!TenantContext::hasModule('listings')) {
            return $this->view('accessible-frontend::listings', [
                'title' => __('govuk_alpha.listings.title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'listings',
                'items' => [],
                'categories' => [],
                'meta' => ['total_items' => 0, 'has_more' => false, 'cursor' => null],
                'filters' => $this->listingFilters($request),
                'moduleDisabled' => true,
                'error' => null,
            ], 403);
        }

        $filters = $this->listingFilters($request);
        $query = ['limit' => 12];

        foreach (['type', 'category_id', 'search', 'cursor', 'min_hours', 'max_hours', 'service_type', 'posted_within'] as $key) {
            if ($filters[$key] !== null && $filters[$key] !== '') {
                $query[$key] = $filters[$key];
            }
        }

        // "Recommended" surfaces featured listings first; "newest" (default) keeps
        // the id-descending order. Full personalised ranking is React-only.
        if (($filters['sort'] ?? 'newest') === 'recommended') {
            $query['featured_first'] = true;
        }

        $items = [];
        $meta = ['total_items' => 0, 'has_more' => false, 'cursor' => null];
        $error = null;

        try {
            $result = $this->listingService->getAll($query);
            $items = $this->withResolvedImageKey($result['items'] ?? [], 'image_url');
            $meta = [
                'total_items' => $this->listingService->countAll($query),
                'has_more' => (bool) ($result['has_more'] ?? false),
                'cursor' => $result['cursor'] ?? null,
            ];
        } catch (\Throwable $e) {
            report($e);
            $error = __('govuk_alpha.states.error_title');
        }

        $categories = Category::where('type', 'listing')
            ->where('tenant_id', TenantContext::getId())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        return $this->view('accessible-frontend::listings', [
            'title' => __('govuk_alpha.listings.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'listings',
            'items' => $items,
            'categories' => $categories,
            'meta' => $meta,
            'filters' => $filters,
            'moduleDisabled' => false,
            'error' => $error,
        ]);
    }

    public function listing(string $tenantSlug, int $id): Response
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        $listing = $this->listingService->getById($id, false, $this->currentUserId());
        abort_if($listing === null, 404);
        $ownerId = (int) ($listing['user_id'] ?? $listing['author_id'] ?? $listing['user']['id'] ?? 0);
        $isOwner = $userId !== null && $ownerId === $userId;

        // Resolve the cover image to a same-origin URL and attach the multi-image
        // gallery (the ListingImage rows are not returned by ListingService::getById,
        // so we load them here exactly as the React ListingsController::show() does).
        $listing['image_url'] = $this->resolveAsset($listing['image_url'] ?? null);
        $listing['images'] = $this->listingGallery($id, $listing['image_url']);
        $listing['author_avatar'] = $this->resolveAsset(
            $listing['author_avatar'] ?? $listing['user']['avatar_url'] ?? $listing['user']['avatar'] ?? null
        );

        return $this->view('accessible-frontend::listing-detail', [
            'title' => $listing['title'] ?? __('govuk_alpha.listings.detail_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'listings',
            'listing' => $listing,
            'requiresAuth' => $userId === null,
            'isOwner' => $isOwner,
            'exchangeWorkflowEnabled' => BrokerControlConfigService::isExchangeWorkflowEnabled(),
            'directMessagingEnabled' => BrokerControlConfigService::isDirectMessagingEnabled(),
            'activeExchange' => $userId && !$isOwner ? ExchangeWorkflowService::getActiveExchangeForListing($userId, $id) : null,
            'status' => self::asStr(request()->query('status')) ?: null,
            'ogImage' => $this->absoluteAssetUrl($listing['image_url'] ?? null),
            'ogImageAlt' => $listing['image_url'] ? ($listing['title'] ?? null) : null,
        ]);
    }

    public function createListing(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $this->view('accessible-frontend::listing-create', $this->listingFormViewData($tenantSlug, $request));
    }

    public function storeListing(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        [$data, $errors] = $this->validateListingInput($request);
        if ($errors !== []) {
            return redirect()
                ->route('govuk-alpha.listings.create', ['tenantSlug' => $tenantSlug])
                ->withErrors($errors)
                ->withInput();
        }

        try {
            $listing = $this->listingService->create($userId, $data);
            $listingId = (int) $listing->id;

            // Optional cover photo. Mirror ListingsController::uploadImage(): push the
            // file through the shared pipeline and set the listing cover. Best-effort —
            // a bad image must never discard a listing the member already wrote.
            if ($request->hasFile('image')) {
                $this->attachListingCoverImage($request, $listingId);
            }
        } catch (ValidationException $e) {
            // Conditional service-side rules (per-user cap, tenant requirements not
            // mirrored above) — surface them per field where we can.
            return redirect()
                ->route('govuk-alpha.listings.create', ['tenantSlug' => $tenantSlug])
                ->withErrors($this->flattenValidationErrors($e))
                ->withInput();
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('govuk-alpha.listings.create', ['tenantSlug' => $tenantSlug, 'status' => 'listing-create-failed'])
                ->withInput();
        }

        return redirect()->route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId, 'status' => 'listing-created']);
    }

    public function requestExchange(string $tenantSlug, int $listingId): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        if (!BrokerControlConfigService::isExchangeWorkflowEnabled()) {
            return redirect()->route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId, 'status' => 'exchange-disabled']);
        }

        $listing = $this->listingService->getById($listingId, false, $userId);
        abort_if($listing === null, 404);

        if ((int) ($listing['user_id'] ?? $listing['author_id'] ?? $listing['user']['id'] ?? 0) === $userId) {
            return redirect()->route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId, 'status' => 'own-listing']);
        }

        $walletBalance = null;
        try {
            $walletBalance = (float) (app(\App\Services\WalletService::class)->getBalance($userId)['balance'] ?? 0);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::exchange-request', [
            'title' => __('govuk_alpha.exchanges.request_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'exchanges',
            'listing' => $listing,
            'config' => BrokerControlConfigService::getConfig('exchange_workflow'),
            'status' => self::asStr(request()->query('status')) ?: null,
            'walletBalance' => $walletBalance,
        ]);
    }

    public function storeExchangeRequest(Request $request, string $tenantSlug, int $listingId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        if (!BrokerControlConfigService::isExchangeWorkflowEnabled()) {
            return redirect()->route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId, 'status' => 'exchange-disabled']);
        }

        $hours = max(0.25, min(24, (float) $request->input('proposed_hours', 1)));
        $prepTime = $request->input('prep_time');

        try {
            $violations = ExchangeWorkflowService::checkComplianceRequirements($listingId, $userId);
            if (!empty($violations)) {
                return redirect()->route('govuk-alpha.exchanges.request', ['tenantSlug' => $tenantSlug, 'listingId' => $listingId, 'status' => 'compliance-failed']);
            }

            $exchangeId = ExchangeWorkflowService::createRequest($userId, $listingId, [
                'proposed_hours' => $hours,
                'prep_time' => $prepTime !== null && $prepTime !== '' ? (float) $prepTime : null,
                'message' => trim(self::asStr($request->input('message'))) ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $exchangeId = null;
        }

        if (!$exchangeId) {
            return redirect()->route('govuk-alpha.exchanges.request', ['tenantSlug' => $tenantSlug, 'listingId' => $listingId, 'status' => 'exchange-failed']);
        }

        return redirect()->route('govuk-alpha.exchanges.show', ['tenantSlug' => $tenantSlug, 'id' => $exchangeId, 'status' => 'exchange-created']);
    }

    public function exchanges(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $status = $this->allowed($request->query('status_filter'), ['active', 'pending_provider', 'pending_broker', 'accepted', 'in_progress', 'pending_confirmation', 'completed', 'cancelled', 'disputed'], null);
        $filters = ['limit' => 20];
        if ($status) {
            $filters['status'] = $status;
        }
        $cursorParam = self::asStr($request->query('cursor'));
        if ($cursorParam !== '') {
            $filters['cursor'] = $cursorParam;
        }

        $result = app(ExchangeService::class)->getAll($userId, $filters);

        return $this->view('accessible-frontend::exchanges', [
            'title' => __('govuk_alpha.exchanges.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'exchanges',
            'items' => $result['items'] ?? [],
            'meta' => ['has_more' => (bool) ($result['has_more'] ?? false), 'cursor' => $result['cursor'] ?? null],
            'filters' => ['status_filter' => $status],
            'workflowEnabled' => BrokerControlConfigService::isExchangeWorkflowEnabled(),
            'currentUserId' => $userId,
        ]);
    }

    public function exchange(string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $exchange = ExchangeWorkflowService::getExchange($id);
        abort_if($exchange === null, 404);
        abort_unless((int) $exchange['requester_id'] === $userId || (int) $exchange['provider_id'] === $userId, 404);

        // Post-completion review prompt: only once the exchange is completed and the
        // viewer has not already rated it (mirrors the React detail page's has_rated gate).
        $canReview = false;
        $ratings = [];
        if (($exchange['status'] ?? '') === 'completed') {
            try {
                $ratingService = app(\App\Services\ExchangeRatingService::class);
                $canReview = !$ratingService->hasRated($id, $userId);
                // Surface the ratings each party has left (mirrors the React detail
                // page's GET /v2/exchanges/:id/ratings section).
                $ratings = $ratingService->getRatingsForExchange($id);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->view('accessible-frontend::exchange-detail', [
            'title' => $exchange['listing_title'] ?? __('govuk_alpha.exchanges.detail_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'exchanges',
            'exchange' => $exchange,
            'history' => ExchangeWorkflowService::getExchangeHistory($id),
            'status' => self::asStr(request()->query('status')) ?: null,
            'currentUserId' => $userId,
            'canReview' => $canReview,
            'ratings' => $ratings,
        ]);
    }

    public function storeExchangeRating(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $exchange = ExchangeWorkflowService::getExchange($id);
        abort_if($exchange === null, 404);
        abort_unless((int) $exchange['requester_id'] === $userId || (int) $exchange['provider_id'] === $userId, 404);

        $rating = (int) $request->input('rating');
        if ($rating < 1 || $rating > 5) {
            return redirect()->route('govuk-alpha.exchanges.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'rating-invalid']);
        }

        $comment = trim(self::asStr($request->input('comment'))) ?: null;
        $ok = false;
        try {
            $result = app(\App\Services\ExchangeRatingService::class)->submitRating($id, $userId, $rating, $comment);
            $ok = (bool) ($result['success'] ?? false);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.exchanges.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'rating-submitted' : 'rating-failed',
        ]);
    }

    public function storeExchangeAction(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $exchange = ExchangeWorkflowService::getExchange($id);
        abort_if($exchange === null, 404);
        abort_unless((int) $exchange['requester_id'] === $userId || (int) $exchange['provider_id'] === $userId, 404);

        $action = $this->allowed($request->input('action'), ['accept', 'decline', 'start', 'complete', 'confirm', 'cancel'], '');
        $ok = false;

        try {
            $ok = match ($action) {
                'accept' => ExchangeWorkflowService::acceptRequest($id, $userId),
                'decline' => ExchangeWorkflowService::declineRequest($id, $userId, trim(self::asStr($request->input('reason')))),
                'start' => ExchangeWorkflowService::startProgress($id, $userId),
                'complete' => ExchangeWorkflowService::markReadyForConfirmation($id, $userId),
                'confirm' => ExchangeWorkflowService::confirmCompletion($id, $userId, max(0.25, min(24, (float) $request->input('hours', 0)))),
                'cancel' => ExchangeWorkflowService::cancelExchange($id, $userId, trim(self::asStr($request->input('reason')))),
                default => false,
            };
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.exchanges.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'exchange-updated' : 'exchange-action-failed',
        ]);
    }

    public function messages(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $showArchived = $request->boolean('archived');
        $result = MessageService::getConversations($userId, [
            'limit' => 20,
            'archived' => $showArchived,
            'cursor' => self::asStr($request->query('cursor')) ?: null,
        ]);

        // Inline "start a conversation" search so a member can find someone and
        // open a thread without leaving the messages page (React uses a modal).
        $searchQuery = trim(self::asStr($request->query('q')));
        $searchResults = [];
        if ($searchQuery !== '') {
            $searchResults = $this->messageUserSearch($searchQuery, $userId);
        }

        return $this->view('accessible-frontend::messages', [
            'title' => __('govuk_alpha.messages.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'messages',
            'items' => $result['items'] ?? [],
            'meta' => ['has_more' => (bool) ($result['has_more'] ?? false), 'cursor' => $result['cursor'] ?? null],
            'showArchived' => $showArchived,
            'directMessagingEnabled' => BrokerControlConfigService::isDirectMessagingEnabled(),
            'restriction' => app(\App\Services\BrokerMessageVisibilityService::class)->getUserRestrictionStatus($userId),
            'status' => self::asStr($request->query('status')) ?: null,
            'currentUserId' => $userId,
            'searchQuery' => $searchQuery,
            'searchResults' => $searchResults,
        ]);
    }

    /**
     * Find up to 10 members (tenant-scoped, excluding self) matching a query,
     * for the messages page's inline start-a-conversation search.
     *
     * @return array<int, array<string, mixed>>
     */
    private function messageUserSearch(string $query, int $viewerId): array
    {
        try {
            $tenantId = TenantContext::getId();
            $ids = SearchService::searchUsersStatic($query, $tenantId);
            if (!is_array($ids) || empty($ids)) {
                return [];
            }
            $ids = array_slice(array_map('intval', $ids), 0, 10);

            return DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('id', '!=', $viewerId)
                // Honour the search opt-out: the Meilisearch path that produced
                // these IDs does not carry privacy_search, so re-filter here.
                ->where(function ($q) {
                    $q->where('privacy_search', 1)->orWhereNull('privacy_search');
                })
                ->whereIn('id', $ids)
                ->select(
                    'id',
                    DB::raw("CASE WHEN profile_type = 'organisation' AND organization_name IS NOT NULL AND organization_name != '' THEN organization_name ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) END as name"),
                    // Disambiguation fields for the recipient picker: two members can
                    // share a display name, so callers render location + "member since".
                    'location',
                    'created_at'
                )
                ->get()
                ->map(static fn ($r): array => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Resolve a single transfer recipient by id (used when the JS autocomplete
     * picks a member). Re-applies the same tenant + active + search-opt-out
     * filters as the text search, so a picked id can't bypass them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function walletRecipientById(int $recipientId, int $viewerId): array
    {
        if ($recipientId <= 0 || $recipientId === $viewerId) {
            return [];
        }

        try {
            $row = DB::table('users')
                ->where('id', $recipientId)
                ->where('tenant_id', TenantContext::getId())
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->where('privacy_search', 1)->orWhereNull('privacy_search');
                })
                ->select(
                    'id',
                    DB::raw("CASE WHEN profile_type = 'organisation' AND organization_name IS NOT NULL AND organization_name != '' THEN organization_name ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) END as name"),
                    'location',
                    'created_at'
                )
                ->first();

            return $row ? [(array) $row] : [];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * JSON suggestions for the wallet recipient autocomplete (progressive
     * enhancement). Reuses the tenant-scoped, privacy-respecting member search
     * and adds the disambiguation fields (location + "member since"). The no-JS
     * path never calls this — it uses the server-rendered search results.
     */
    public function walletRecipients(Request $request, string $tenantSlug): \Illuminate\Http\JsonResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('wallet'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return response()->json(['results' => []], 401);
        }

        $query = trim(self::asStr($request->query('q')));
        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];
        foreach ($this->messageUserSearch($query, $userId) as $r) {
            $since = !empty($r['created_at'])
                ? \Illuminate\Support\Carbon::parse($r['created_at'])->translatedFormat('M Y')
                : null;
            $results[] = [
                'id'       => (int) ($r['id'] ?? 0),
                'name'     => trim((string) ($r['name'] ?? '')),
                'location' => (trim((string) ($r['location'] ?? '')) ?: null),
                'since'    => $since,
            ];
        }

        return response()->json(['results' => $results]);
    }

    /**
     * "My account" hub — a single landing page that gathers the member's
     * personal/transactional facilities (Wallet, Messages, Connections, Profile,
     * Settings) so the flat GOV.UK service navigation can stay focused on
     * community + discovery. Linked from the top header account zone.
     */
    public function account(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $this->view('accessible-frontend::account', [
            'title' => __('govuk_alpha.account.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'account',
        ]);
    }

    // === Gamification (reached from the My account hub) ===

    /** Achievements: the member's level/XP plus earned badges and badges in progress. */
    public function achievements(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('gamification'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $profile = [];
        $badges = [];
        $progress = [];
        try {
            $profile = \App\Services\GamificationService::getProfile($userId, $tenantId);
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $badges = \App\Services\GamificationService::getBadges($userId, $tenantId);
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $progress = \App\Services\GamificationService::getBadgeProgress($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::achievements', [
            'title' => __('govuk_alpha.achievements.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'achievements',
            'gamProfile' => is_array($profile) ? $profile : [],
            'earnedBadges' => is_array($badges) ? $badges : [],
            'badgeProgress' => is_array($progress) ? $progress : [],
        ]);
    }

    /** Leaderboard: members ranked by a chosen metric + period (server-rendered filter). */
    public function leaderboard(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('gamification'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $validTypes = ['credits_earned', 'credits_spent', 'vol_hours', 'badges', 'xp', 'connections', 'reviews', 'posts', 'streak'];
        $validPeriods = ['all_time', 'month', 'week'];
        $type = in_array($request->query('type'), $validTypes, true) ? (string) $request->query('type') : 'credits_earned';
        $period = in_array($request->query('period'), $validPeriods, true) ? (string) $request->query('period') : 'all_time';

        $rows = [];
        try {
            $svc = app(\App\Services\LeaderboardService::class);
            $rows = $svc->getLeaderboardByType(TenantContext::getId(), $type, $period, 20, $userId);
            foreach ($rows as &$row) {
                $row['score_display'] = $svc->formatScore($row['score'] ?? 0, $type);
            }
            unset($row);
        } catch (\Throwable $e) {
            report($e);
            $rows = [];
        }

        return $this->view('accessible-frontend::leaderboard', [
            'title' => __('govuk_alpha.leaderboard.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'leaderboard',
            'leaderboardRows' => is_array($rows) ? $rows : [],
            'leaderboardType' => $type,
            'leaderboardPeriod' => $period,
            'leaderboardTypes' => $validTypes,
            'leaderboardPeriods' => $validPeriods,
        ]);
    }

    /** NEXUS score: the member's reputation score with its category breakdown + tips. */
    public function nexusScore(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('gamification'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $score = [];
        try {
            $score = app(\App\Services\NexusScoreCacheService::class)->getScore($userId, TenantContext::getId());
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::nexus-score', [
            'title' => __('govuk_alpha.nexus_score.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'nexus_score',
            'nexusScore' => is_array($score) ? $score : [],
        ]);
    }

    // === Notifications inbox (auth-only; notifications are always-on) ===

    public function notifications(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $unreadOnly = self::asStr($request->query('filter')) === 'unread';
        $filters = ['limit' => 30];
        if ($unreadOnly) {
            $filters['unread_only'] = true;
        }
        $cursor = self::asStr($request->query('cursor'));
        if ($cursor !== '') {
            $filters['cursor'] = $cursor;
        }

        $data = ['items' => [], 'cursor' => null, 'has_more' => false];
        $counts = ['total' => 0];
        try {
            $svc = app(\App\Services\NotificationService::class);
            $data = $svc->getAll($userId, $filters);
            $counts = $svc->getCounts($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::notifications', [
            'title' => __('govuk_alpha.notifications.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'notifications',
            'notifications' => is_array($data['items'] ?? null) ? $data['items'] : [],
            'notificationsCursor' => $data['cursor'] ?? null,
            'notificationsHasMore' => (bool) ($data['has_more'] ?? false),
            'notificationCounts' => is_array($counts) ? $counts : ['total' => 0],
            'notificationsUnreadOnly' => $unreadOnly,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function markAllNotificationsRead(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        try {
            app(\App\Services\NotificationService::class)->markAllRead($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.notifications.index', ['tenantSlug' => $tenantSlug, 'status' => 'marked-read']);
    }

    public function deleteNotification(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        try {
            app(\App\Services\NotificationService::class)->delete($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.notifications.index', ['tenantSlug' => $tenantSlug, 'status' => 'notification-deleted']);
    }

    // === Personal activity (auth-only, self) ===

    public function activity(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $data = [];
        try {
            $data = app(\App\Services\MemberActivityService::class)->getDashboardData($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::activity', [
            'title' => __('govuk_alpha.activity.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'activity',
            'activity' => is_array($data) ? $data : [],
        ]);
    }

    // === Reviews (received / given / pending) ===

    public function reviews(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('reviews'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $received = [];
        $given = [];
        $pending = [];
        $stats = [];
        try {
            $svc = app(\App\Services\ReviewService::class);
            $received = $svc->getForUser($userId, ['limit' => 20])['items'] ?? [];
            $given = $svc->getGivenByUser($userId, ['limit' => 20])['items'] ?? [];
            $pending = $svc->getPendingReviews($userId, ['limit' => 20])['items'] ?? [];
            $stats = $svc->getStats($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::reviews', [
            'title' => __('govuk_alpha.reviews_page.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'reviews',
            'reviewsReceived' => is_array($received) ? $received : [],
            'reviewsGiven' => is_array($given) ? $given : [],
            'reviewsPending' => is_array($pending) ? $pending : [],
            'reviewStats' => is_array($stats) ? $stats : [],
        ]);
    }

    // === Static marketing pages (public) ===

    public function features(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::features', [
            'title' => __('govuk_alpha.features.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'features',
        ]);
    }

    public function faq(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::faq', [
            'title' => __('govuk_alpha.faq.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'faq',
        ]);
    }

    // === Explore hub (discovery facilities — keeps the flat service nav lean) ===

    public function explore(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $this->view('accessible-frontend::explore', [
            'title' => __('govuk_alpha.explore.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
        ]);
    }

    // === Global search ===

    public function search(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('search'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $q = trim(self::asStr($request->query('q')));
        $type = in_array($request->query('type'), ['all', 'listing', 'user', 'event', 'group'], true) ? (string) $request->query('type') : 'all';
        $results = [];
        $total = 0;
        if ($q !== '') {
            try {
                $r = app(\App\Services\SearchService::class)->unifiedSearch($q, $userId, ['type' => $type, 'limit' => 30]);
                $results = is_array($r['items'] ?? null) ? $r['items'] : [];
                $total = (int) ($r['total'] ?? count($results));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->view('accessible-frontend::search', [
            'title' => __('govuk_alpha.search.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'searchQuery' => $q,
            'searchType' => $type,
            'searchResults' => $results,
            'searchTotal' => $total,
        ]);
    }

    // === Groups ===

    public function groups(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('groups'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $q = trim(self::asStr($request->query('q')));
        $filters = ['limit' => 30, 'viewer_user_id' => $userId];
        if ($q !== '') {
            $filters['search'] = $q;
        }
        $items = [];
        try {
            $items = \App\Services\GroupService::getAll($filters)['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::groups', [
            'title' => __('govuk_alpha.groups.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'groups' => is_array($items) ? $items : [],
            'groupsQuery' => $q,
        ]);
    }

    public function group(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('groups'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $group = null;
        $members = [];
        try {
            $group = \App\Services\GroupService::getById($id, $userId, true);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($group === null, 404);
        try {
            $members = \App\Services\GroupService::getMembers($id, ['limit' => 50])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        $isMember = false;
        foreach ($members as $m) {
            if ((int) ($m['user_id'] ?? ($m['id'] ?? 0)) === $userId) {
                $isMember = true;
                break;
            }
        }

        return $this->view('accessible-frontend::group-detail', [
            'title' => ($group['name'] ?? '') ?: __('govuk_alpha.groups.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'group' => $group,
            'groupMembers' => is_array($members) ? $members : [],
            'isMember' => $isMember,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function joinGroup(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        abort_unless(TenantContext::hasFeature('groups'), 403);
        $ok = false;
        try {
            $res = \App\Services\GroupService::join($id, $userId);
            $ok = (bool) ($res['success'] ?? (is_array($res) ? !isset($res['error']) : (bool) $res));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $ok ? 'group-joined' : 'group-failed']);
    }

    public function leaveGroup(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        abort_unless(TenantContext::hasFeature('groups'), 403);
        $ok = false;
        try {
            $ok = \App\Services\GroupService::leave($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $ok ? 'group-left' : 'group-failed']);
    }

    // === Goals ===

    public function goals(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('goals'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $items = [];
        try {
            $items = app(\App\Services\GoalService::class)->getAll(['limit' => 30, 'user_id' => $userId])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::goals', [
            'title' => __('govuk_alpha.goals.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'goals' => is_array($items) ? $items : [],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function goal(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('goals'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $goal = null;
        try {
            $model = app(\App\Services\GoalService::class)->getById($id);
            $goal = $model?->toArray();
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($goal === null, 404);

        return $this->view('accessible-frontend::goal-detail', [
            'title' => ($goal['title'] ?? '') ?: __('govuk_alpha.goals.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'goal' => $goal,
            'isOwner' => (int) ($goal['user_id'] ?? 0) === $userId,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function storeGoal(Request $request, string $tenantSlug): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        abort_unless(TenantContext::hasFeature('goals'), 403);

        $title = trim(self::asStr($request->input('title')));
        $target = (float) $request->input('target_value');
        if ($title === '' || $target <= 0) {
            return redirect()->route('govuk-alpha.goals.index', ['tenantSlug' => $tenantSlug, 'status' => 'goal-invalid']);
        }

        $ok = false;
        try {
            $goal = app(\App\Services\GoalService::class)->create($userId, [
                'title' => mb_substr($title, 0, 255),
                'description' => trim(self::asStr($request->input('description'))) ?: null,
                'target_value' => $target,
                'current_value' => 0,
                'deadline' => self::asStr($request->input('deadline')) ?: null,
                'is_public' => $request->boolean('is_public'),
            ]);
            $ok = $goal !== null;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.goals.index', ['tenantSlug' => $tenantSlug, 'status' => $ok ? 'goal-created' : 'goal-failed']);
    }

    public function incrementGoal(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        abort_unless(TenantContext::hasFeature('goals'), 403);

        $inc = (float) $request->input('increment');
        $status = 'goal-invalid';
        if ($inc > 0) {
            try {
                $status = app(\App\Services\GoalService::class)->incrementProgress($id, $userId, $inc) !== null ? 'goal-updated' : 'goal-failed';
            } catch (\Throwable $e) {
                report($e);
                $status = 'goal-failed';
            }
        }

        return redirect()->route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]);
    }

    public function completeGoal(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        abort_unless(TenantContext::hasFeature('goals'), 403);

        $ok = false;
        try {
            $ok = app(\App\Services\GoalService::class)->complete($id, $userId) !== null;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $ok ? 'goal-completed' : 'goal-failed']);
    }

    // === Skills directory (ungated — no 'skills' feature flag) ===

    public function skills(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $svc = app(\App\Services\SkillTaxonomyService::class);
        $tree = [];
        try {
            $tree = $svc->getTree(true);
        } catch (\Throwable $e) {
            report($e);
        }
        $skillQuery = trim(self::asStr($request->query('skill')));
        $skillMembers = [];
        if ($skillQuery !== '') {
            try {
                $skillMembers = $svc->getMembersWithSkill($skillQuery, 40);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->view('accessible-frontend::skills', [
            'title' => __('govuk_alpha.skills.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'skillTree' => is_array($tree) ? $tree : [],
            'skillQuery' => $skillQuery,
            'skillMembers' => is_array($skillMembers) ? $skillMembers : [],
        ]);
    }

    // === Organisations ===

    public function organisations(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('organisations'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $q = trim(self::asStr($request->query('q')));
        $filters = ['limit' => 30];
        if ($q !== '') {
            $filters['search'] = $q;
        }
        $items = [];
        try {
            $items = \App\Services\VolunteerService::getOrganisations($filters)['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::organisations', [
            'title' => __('govuk_alpha.organisations.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'organisations' => is_array($items) ? $items : [],
            'organisationsQuery' => $q,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function organisation(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('organisations'), 403);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $org = null;
        try {
            $org = \App\Services\VolunteerService::getOrganisationById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($org === null, 404);

        return $this->view('accessible-frontend::organisation-detail', [
            'title' => ($org['name'] ?? '') ?: __('govuk_alpha.organisations.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'organisation' => $org,
        ]);
    }

    public function storeOrganisation(Request $request, string $tenantSlug): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        abort_unless(TenantContext::hasFeature('organisations'), 403);

        $name = trim(self::asStr($request->input('name')));
        if ($name === '') {
            return redirect()->route('govuk-alpha.organisations.index', ['tenantSlug' => $tenantSlug, 'status' => 'org-invalid']);
        }

        $ok = false;
        try {
            $id = \App\Services\VolunteerService::createOrganization($userId, [
                'name' => mb_substr($name, 0, 255),
                'description' => trim(self::asStr($request->input('description'))) ?: null,
                'email' => trim(self::asStr($request->input('email'))) ?: null,
                'website' => trim(self::asStr($request->input('website'))) ?: null,
            ]);
            $ok = $id !== null;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.organisations.index', ['tenantSlug' => $tenantSlug, 'status' => $ok ? 'org-submitted' : 'org-failed']);
    }

    // === Saved / bookmarks (ungated, auth-only) ===

    public function saved(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $items = [];
        try {
            $paginator = app(\App\Services\BookmarkService::class)->getUserBookmarks($userId, null, null, 1, 50);
            $items = method_exists($paginator, 'items') ? $paginator->items() : (array) $paginator;
            $items = array_map(static fn ($b) => is_array($b) ? $b : (array) $b, $items);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::saved', [
            'title' => __('govuk_alpha.saved.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'saved',
            'savedItems' => is_array($items) ? $items : [],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    // === Resources ===

    public function resources(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $q = trim(self::asStr($request->query('q')));
        $filters = ['limit' => 30];
        if ($q !== '') {
            $filters['search'] = $q;
        }
        $items = [];
        try {
            $items = app(\App\Services\ResourceService::class)->getAll($filters)['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::resources', [
            'title' => __('govuk_alpha.resources.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'resources' => is_array($items) ? $items : [],
            'resourcesQuery' => $q,
        ]);
    }

    // === Jobs / vacancies ===

    public function jobs(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $q = trim(self::asStr($request->query('q')));
        $filters = ['limit' => 30, 'status' => 'open'];
        if ($q !== '') {
            $filters['search'] = $q;
        }
        $items = [];
        try {
            $items = app(\App\Services\JobVacancyService::class)->getAll($filters, $userId)['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::jobs', [
            'title' => __('govuk_alpha.jobs.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'jobs' => is_array($items) ? $items : [],
            'jobsQuery' => $q,
        ]);
    }

    public function job(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('job_vacancies'), 403);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $job = null;
        try {
            $job = app(\App\Services\JobVacancyService::class)->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($job === null, 404);

        return $this->view('accessible-frontend::job-detail', [
            'title' => ($job['title'] ?? '') ?: __('govuk_alpha.jobs.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'job' => $job,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function applyJob(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        abort_unless(TenantContext::hasFeature('job_vacancies'), 403);

        $cover = trim(self::asStr($request->input('cover_letter')));
        $ok = false;
        try {
            $ok = app(\App\Services\JobVacancyService::class)->apply($id, $userId, ['cover_letter' => mb_substr($cover, 0, 5000)]) !== null;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $ok ? 'applied' : 'apply-failed']);
    }

    // === Ideation challenges ===

    public function ideation(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('ideation_challenges'), 403);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $items = [];
        try {
            $items = app(\App\Services\IdeationChallengeService::class)->getAll(['limit' => 30])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::ideation', [
            'title' => __('govuk_alpha.ideation.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'challenges' => is_array($items) ? $items : [],
        ]);
    }

    public function ideationChallenge(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('ideation_challenges'), 403);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $svc = app(\App\Services\IdeationChallengeService::class);
        $challenge = null;
        $ideas = [];
        try {
            $challenge = $svc->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($challenge === null, 404);
        try {
            $ideas = $svc->getIdeas($id, ['limit' => 30, 'sort' => 'votes'])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::ideation-detail', [
            'title' => ($challenge['title'] ?? '') ?: __('govuk_alpha.ideation.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'challenge' => $challenge,
            'ideas' => is_array($ideas) ? $ideas : [],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function submitIdea(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        abort_unless(TenantContext::hasFeature('ideation_challenges'), 403);

        $title = trim(self::asStr($request->input('idea_title')));
        $content = trim(self::asStr($request->input('idea_content')));
        if ($title === '') {
            return redirect()->route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'idea-invalid'])->withFragment('submit');
        }
        $ok = false;
        try {
            $ok = app(\App\Services\IdeationChallengeService::class)->submitIdea($id, $userId, [
                'title' => mb_substr($title, 0, 255),
                'description' => mb_substr($content, 0, 5000),
            ]) > 0;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $ok ? 'idea-submitted' : 'idea-failed']);
    }

    public function voteIdea(Request $request, string $tenantSlug, int $challengeId, int $ideaId): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        abort_unless(TenantContext::hasFeature('ideation_challenges'), 403);

        $ok = false;
        try {
            $ok = app(\App\Services\IdeationChallengeService::class)->voteIdea($ideaId, $userId) !== null;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $challengeId, 'status' => $ok ? 'idea-voted' : 'idea-failed'])->withFragment('ideas');
    }

    /**
     * "How timebanking works" — a plain, public educational page. No auth or
     * module gate: it helps newcomers (and the accessibility-first audience in
     * particular) understand the model before signing up.
     */
    public function guide(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::guide', [
            'title' => __('govuk_alpha.guide.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'guide',
        ]);
    }

    /**
     * Connections inbox: the member's accepted network plus pending requests
     * (received — which they can accept/decline — and sent, which they can
     * cancel). Backed by the tenant-scoped ConnectionService.
     */
    public function connections(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('connections'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $accepted = [];
        $received = [];
        $sent = [];
        $counts = ['received' => 0, 'sent' => 0, 'total_friends' => 0];

        try {
            $accepted = \App\Services\ConnectionService::getConnections($userId, ['status' => 'accepted', 'limit' => 50])['items'] ?? [];
            $received = \App\Services\ConnectionService::getConnections($userId, ['status' => 'pending_received', 'limit' => 50])['items'] ?? [];
            $sent = \App\Services\ConnectionService::getConnections($userId, ['status' => 'pending_sent', 'limit' => 50])['items'] ?? [];
            $counts = \App\Services\ConnectionService::getPendingCounts($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::connections', [
            'title' => __('govuk_alpha.connections.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'connections',
            'acceptedConnections' => $accepted,
            'receivedRequests' => $received,
            'sentRequests' => $sent,
            'connectionCounts' => $counts,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function acceptConnection(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        return $this->connectionAction($tenantSlug, $id, 'accept');
    }

    public function declineConnection(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        return $this->connectionAction($tenantSlug, $id, 'decline');
    }

    public function cancelConnection(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        return $this->connectionAction($tenantSlug, $id, 'remove');
    }

    /** Shared accept/decline/remove handler for connection requests. */
    private function connectionAction(string $tenantSlug, int $id, string $action): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('connections'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $ok = false;
        try {
            // ConnectionService verifies the actor is the receiver (accept/decline)
            // or a participant (remove), so a member can only act on their own.
            $ok = match ($action) {
                'accept'  => \App\Services\ConnectionService::acceptRequest($id, $userId),
                'decline' => \App\Services\ConnectionService::rejectRequest($id, $userId),
                'remove'  => \App\Services\ConnectionService::removeConnection($id, $userId),
                default   => false,
            };
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        $okStatus = match ($action) {
            'accept'  => 'connection-accepted',
            'decline' => 'connection-declined',
            default   => 'connection-removed',
        };

        return redirect()->route('govuk-alpha.connections.index', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? $okStatus : 'connection-failed',
        ])->withFragment('connections-top');
    }

    /**
     * Matches — members whose offers/requests complement the viewer's listings,
     * ranked by SmartMatchingEngine (the same engine that powers the React app).
     * Gated by the listings module since matches are listing-based.
     */
    public function matches(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $matches = [];
        try {
            $matches = app(\App\Services\SmartMatchingEngine::class)->findMatchesForUser($userId, ['limit' => 20]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::matches', [
            'title' => __('govuk_alpha.matches.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'matches',
            'matches' => is_array($matches) ? $matches : [],
        ]);
    }

    /**
     * Community polls — a standalone listing where members can vote and see
     * results. The alpha already votes on polls inline in the feed; this gives
     * them their own page. Ballot integrity (hidden running totals while a poll
     * is open) is enforced by PollService::getById.
     */
    public function polls(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('polls'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $polls = [];
        try {
            $list = \App\Services\PollService::getAll(['limit' => 30])['items'] ?? [];
            foreach ($list as $p) {
                $full = \App\Services\PollService::getById((int) ($p['id'] ?? 0), $userId);
                if ($full !== null) {
                    $polls[] = $full;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::polls', [
            'title' => __('govuk_alpha.polls.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'polls',
            'polls' => $polls,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Cast a vote on a poll from the standalone polls page. */
    public function storePollVote(Request $request, string $tenantSlug, int $pollId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('polls'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $optionId = (int) $request->input('option_id');
        $ok = false;
        if ($optionId > 0) {
            try {
                $ok = \App\Services\PollService::vote($pollId, $optionId, $userId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.polls.index', [
            'tenantSlug' => $tenantSlug, 'status' => $ok ? 'voted' : 'vote-failed',
        ])->withFragment('poll-' . $pollId);
    }

    // === Group exchanges (multi-party time-credit exchanges) ===

    /** List the viewer's group exchanges (as organiser or participant). */
    public function groupExchanges(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('group_exchanges'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $items = [];
        try {
            $items = app(\App\Services\GroupExchangeService::class)->listForUser($userId, ['limit' => 50])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::group-exchanges', [
            'title' => __('govuk_alpha.group_exchanges.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'group_exchanges',
            'exchanges' => $items,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Show the "start a group exchange" form. */
    public function createGroupExchange(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('group_exchanges'), 403);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $this->view('accessible-frontend::group-exchange-create', [
            'title' => __('govuk_alpha.group_exchanges.create_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'group_exchanges',
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Create a draft group exchange, then redirect to its detail page to add people. */
    public function storeGroupExchange(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('group_exchanges'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $title = trim(self::asStr($request->input('title')));
        $hours = round((float) $request->input('total_hours'), 2);
        $rawSplit = self::asStr($request->input('split_type'));
        $splitType = in_array($rawSplit, ['equal', 'custom'], true) ? $rawSplit : 'equal';
        $description = trim(self::asStr($request->input('description')));

        if ($title === '' || $hours <= 0) {
            return redirect()->route('govuk-alpha.group-exchanges.create', ['tenantSlug' => $tenantSlug, 'status' => 'create-invalid']);
        }

        $id = null;
        try {
            $id = app(\App\Services\GroupExchangeService::class)->create($userId, [
                'title' => $title,
                'description' => $description,
                'total_hours' => $hours,
                'split_type' => $splitType,
                'status' => 'draft',
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        if (!$id) {
            return redirect()->route('govuk-alpha.group-exchanges.create', ['tenantSlug' => $tenantSlug, 'status' => 'create-failed']);
        }

        return redirect()->route('govuk-alpha.group-exchanges.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'created']);
    }

    /** Group exchange detail — info, participants, calculated split, and role-appropriate actions. */
    public function groupExchange(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('group_exchanges'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $svc = app(\App\Services\GroupExchangeService::class);
        $exchange = $svc->get($id);
        abort_if($exchange === null, 404);

        $participants = is_array($exchange['participants'] ?? null) ? $exchange['participants'] : [];
        $isOrganizer = (int) ($exchange['organizer_id'] ?? 0) === $userId;
        $viewerRow = null;
        foreach ($participants as $p) {
            if ((int) ($p['user_id'] ?? 0) === $userId) {
                $viewerRow = $p;
                break;
            }
        }
        $isParticipant = $viewerRow !== null;
        abort_unless($isOrganizer || $isParticipant, 403);

        // Organiser can edit while the exchange is not yet completed/cancelled.
        $editable = $isOrganizer && in_array($exchange['status'] ?? '', ['draft', 'pending', 'approved'], true);

        $splitByUser = [];
        try {
            foreach ($svc->calculateSplit($id) as $s) {
                $splitByUser[(int) ($s['user_id'] ?? 0)] = round((float) ($s['hours'] ?? 0), 2);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Member search to add a participant (organiser + editable only). Hide
        // anyone already in the exchange.
        $existingIds = array_map(static fn ($p): int => (int) ($p['user_id'] ?? 0), $participants);
        $participantQuery = trim(self::asStr($request->query('participant_q')));
        $participantResults = [];
        if ($editable && $participantQuery !== '') {
            $participantResults = array_values(array_filter(
                $this->messageUserSearch($participantQuery, $userId),
                static fn ($r): bool => !in_array((int) ($r['id'] ?? 0), $existingIds, true)
            ));
        }

        return $this->view('accessible-frontend::group-exchange-detail', [
            'title' => ($exchange['title'] ?? '') ?: __('govuk_alpha.group_exchanges.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'group_exchanges',
            'exchange' => $exchange,
            'participants' => $participants,
            'splitByUser' => $splitByUser,
            'isOrganizer' => $isOrganizer,
            'isParticipant' => $isParticipant,
            'editable' => $editable,
            'viewerConfirmed' => (bool) ($viewerRow['confirmed'] ?? false),
            'participantQuery' => $participantQuery,
            'participantResults' => $participantResults,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function addGroupExchangeParticipant(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $svc = app(\App\Services\GroupExchangeService::class);
        $exchange = $svc->get($id);
        if ($exchange === null) {
            abort(404);
        }
        // Organiser only, and only while the exchange is still editable.
        if ((int) ($exchange['organizer_id'] ?? 0) !== $userId || !in_array($exchange['status'] ?? '', ['draft', 'pending', 'approved'], true)) {
            return $this->geRedirect($tenantSlug, $id, 'add-failed');
        }

        $participantId = (int) $request->input('participant_id');
        $rawRole = self::asStr($request->input('role'));
        $role = in_array($rawRole, ['provider', 'receiver'], true) ? $rawRole : 'provider';
        $hours = round((float) $request->input('hours'), 2);
        if ($hours < 0) {
            $hours = 0;
        }

        // Defensive same-tenant check (the service does not re-scope the id).
        $sameTenant = $participantId > 0 && DB::table('users')
            ->where('id', $participantId)
            ->where('tenant_id', TenantContext::getId())
            ->exists();
        if (!$sameTenant) {
            return $this->geRedirect($tenantSlug, $id, 'add-failed');
        }

        $ok = false;
        try {
            $ok = $svc->addParticipant($id, $participantId, $role, $hours, 1.0);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->geRedirect($tenantSlug, $id, $ok ? 'participant-added' : 'add-failed');
    }

    public function removeGroupExchangeParticipant(Request $request, string $tenantSlug, int $id, int $participantUserId): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $svc = app(\App\Services\GroupExchangeService::class);
        $exchange = $svc->get($id);
        if ($exchange === null) {
            abort(404);
        }
        if ((int) ($exchange['organizer_id'] ?? 0) !== $userId || !in_array($exchange['status'] ?? '', ['draft', 'pending', 'approved'], true)) {
            return $this->geRedirect($tenantSlug, $id, 'failed');
        }

        $ok = false;
        try {
            $ok = $svc->removeParticipant($id, $participantUserId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->geRedirect($tenantSlug, $id, $ok ? 'participant-removed' : 'failed');
    }

    public function confirmGroupExchange(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $svc = app(\App\Services\GroupExchangeService::class);
        $exchange = $svc->get($id);
        if ($exchange === null) {
            abort(404);
        }
        $participants = is_array($exchange['participants'] ?? null) ? $exchange['participants'] : [];
        $isParticipant = false;
        foreach ($participants as $p) {
            if ((int) ($p['user_id'] ?? 0) === $userId) {
                $isParticipant = true;
                break;
            }
        }
        if (!$isParticipant) {
            return $this->geRedirect($tenantSlug, $id, 'failed');
        }

        $ok = false;
        try {
            $ok = $svc->confirmParticipation($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->geRedirect($tenantSlug, $id, $ok ? 'confirmed' : 'failed');
    }

    public function completeGroupExchange(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $svc = app(\App\Services\GroupExchangeService::class);
        $exchange = $svc->get($id);
        if ($exchange === null) {
            abort(404);
        }
        if ((int) ($exchange['organizer_id'] ?? 0) !== $userId) {
            abort(403);
        }

        $success = false;
        try {
            $result = $svc->complete($id);
            $success = (bool) ($result['success'] ?? false);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->geRedirect($tenantSlug, $id, $success ? 'completed' : 'complete-failed');
    }

    public function cancelGroupExchange(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->geGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $svc = app(\App\Services\GroupExchangeService::class);
        $exchange = $svc->get($id);
        if ($exchange === null) {
            abort(404);
        }
        if ((int) ($exchange['organizer_id'] ?? 0) !== $userId) {
            abort(403);
        }

        $ok = false;
        try {
            $ok = $svc->updateStatus($id, 'cancelled');
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->geRedirect($tenantSlug, $id, $ok ? 'cancelled' : 'failed');
    }

    /** Shared auth/feature guard for group-exchange POST actions; returns the user id or a redirect. */
    private function geGuard(string $tenantSlug): int|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('group_exchanges'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $userId;
    }

    private function geRedirect(string $tenantSlug, int $id, string $status): RedirectResponse
    {
        return redirect()->route('govuk-alpha.group-exchanges.show', [
            'tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status,
        ])->withFragment('group-exchange-top');
    }

    /** Time-credit wallet: balance, transaction history, and a transfer form. */
    public function wallet(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('wallet'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $walletService = app(\App\Services\WalletService::class);

        $wallet = null;
        try {
            $wallet = $walletService->getBalance($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        $transactions = [];
        try {
            $transactions = $walletService->getTransactions($userId, ['type' => 'all', 'limit' => 20])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        // Reuse the tenant-scoped member search to pick a recipient safely.
        // A `recipient_id` (set by the JS autocomplete enhancement when a member
        // is picked) resolves to exactly that one member; otherwise fall back to
        // the no-JS text search. Both honour the same tenant + privacy filters.
        $recipientId = (int) $request->query('recipient_id');
        $recipientQuery = trim(self::asStr($request->query('recipient_q')));
        if ($recipientId > 0) {
            $recipientResults = $this->walletRecipientById($recipientId, $userId);
        } elseif ($recipientQuery !== '') {
            $recipientResults = $this->messageUserSearch($recipientQuery, $userId);
        } else {
            $recipientResults = [];
        }

        return $this->view('accessible-frontend::wallet', [
            'title' => __('govuk_alpha.wallet.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'wallet',
            'wallet' => $wallet,
            'transactions' => $transactions,
            'recipientQuery' => $recipientQuery,
            'recipientResults' => $recipientResults,
            'status' => self::asStr($request->query('status')) ?: null,
            'transferError' => self::asStr($request->query('error')) ?: null,
        ]);
    }

    /** Transfer time credits to another member via the canonical WalletService. */
    public function transferCredits(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('wallet'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $recipientId = (int) $request->input('recipient_id');
        $amount = (float) $request->input('amount');
        $note = trim(self::asStr($request->input('note')));

        $fail = fn (string $error): RedirectResponse => redirect()->route('govuk-alpha.wallet.index', [
            'tenantSlug' => $tenantSlug, 'status' => 'transfer-failed', 'error' => $error,
        ])->withFragment('transfer');

        if ($recipientId <= 0 || $amount <= 0) {
            return $fail('invalid');
        }

        // Defensive tenant check: only ever transfer to a member of THIS tenant
        // (the WalletService resolves the recipient by id without re-scoping).
        $sameTenant = DB::table('users')
            ->where('id', $recipientId)
            ->where('tenant_id', TenantContext::getId())
            ->exists();
        if (!$sameTenant) {
            return $fail('not-found');
        }

        try {
            app(\App\Services\WalletService::class)->transfer($userId, [
                'recipient'   => $recipientId,
                'amount'      => $amount,
                'description' => mb_substr($note, 0, 255),
            ]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            return $fail(match (true) {
                str_contains($msg, 'Insufficient')  => 'insufficient',
                str_contains($msg, 'not found')     => 'not-found',
                str_contains($msg, 'yourself')      => 'self',
                str_contains($msg, 'not active')    => 'inactive',
                str_contains($msg, 'exceed')        => 'too-large',
                str_contains($msg, 'decimal')       => 'decimals',
                default                             => 'failed',
            });
        }

        return redirect()->route('govuk-alpha.wallet.index', ['tenantSlug' => $tenantSlug, 'status' => 'transfer-sent'])->withFragment('transactions');
    }

    public function conversation(Request $request, string $tenantSlug, int $userId): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $currentUserId = $this->currentUserId();
        if ($currentUserId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $conversation = MessageService::getConversation($userId, $currentUserId);
        abort_if($conversation === null, 404);

        $result = MessageService::getMessages($userId, $currentUserId, [
            'limit' => 50,
            'cursor' => self::asStr($request->query('cursor')) ?: null,
        ]);
        MessageService::markAsRead($userId, $currentUserId);

        $listing = null;
        if ($request->query('listing')) {
            $listing = $this->listingService->getById((int) $request->query('listing'), false, $currentUserId);
        }

        return $this->view('accessible-frontend::conversation', [
            'title' => __('govuk_alpha.messages.conversation_title', ['name' => $conversation['other_user']['name'] ?? __('govuk_alpha.members.unknown_member')]),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'messages',
            'conversation' => $conversation,
            'messages' => array_reverse($result['items'] ?? []),
            'meta' => ['has_more' => (bool) ($result['has_more'] ?? false), 'cursor' => $result['cursor'] ?? null],
            'listing' => $listing,
            'status' => self::asStr($request->query('status')) ?: null,
            'currentUserId' => $currentUserId,
            'directMessagingEnabled' => BrokerControlConfigService::isDirectMessagingEnabled(),
            'restriction' => app(\App\Services\BrokerMessageVisibilityService::class)->getUserRestrictionStatus($currentUserId),
        ]);
    }

    public function storeMessage(Request $request, string $tenantSlug, int $userId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $currentUserId = $this->currentUserId();
        if ($currentUserId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $body = trim(self::asStr($request->input('body')));
        if ($body === '') {
            return redirect()->route('govuk-alpha.messages.show', ['tenantSlug' => $tenantSlug, 'userId' => $userId, 'status' => 'message-empty']);
        }

        if (!BrokerControlConfigService::isDirectMessagingEnabled()) {
            return redirect()->route('govuk-alpha.messages.show', ['tenantSlug' => $tenantSlug, 'userId' => $userId, 'status' => 'message-disabled']);
        }

        $message = MessageService::send($currentUserId, [
            'recipient_id' => $userId,
            'body' => $body,
            'context_type' => $request->input('context_type') ?: null,
            'context_id' => $request->input('context_id') ?: null,
        ]);

        return redirect()->route('govuk-alpha.messages.show', [
            'tenantSlug' => $tenantSlug,
            'userId' => $userId,
            'status' => !empty($message) ? 'message-sent' : 'message-failed',
        ]);
    }

    public function archiveConversation(string $tenantSlug, int $userId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $currentUserId = $this->currentUserId();
        if ($currentUserId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        MessageService::archiveConversation($userId, $currentUserId, 'self');

        return redirect()->route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug, 'status' => 'conversation-archived']);
    }

    public function restoreConversation(string $tenantSlug, int $userId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('messages'), 403);

        $currentUserId = $this->currentUserId();
        if ($currentUserId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        MessageService::unarchiveConversation($userId, $currentUserId);

        return redirect()->route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug, 'archived' => 1, 'status' => 'conversation-restored']);
    }

    public function members(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('connections'), 403);
        $userId = $this->currentUserId();
        $filters = $this->memberFilters($request);
        $items = [];
        $meta = ['total_items' => 0, 'offset' => $filters['offset'], 'per_page' => $filters['limit'], 'has_more' => false];
        $error = null;

        if ($userId !== null) {
            try {
                $result = $this->memberDirectory($filters, $userId);
                $items = $result['items'];
                $meta = $result['meta'];
            } catch (\Throwable $e) {
                report($e);
                $error = __('govuk_alpha.states.error_title');
            }
        }

        return $this->view('accessible-frontend::members', [
            'title' => __('govuk_alpha.members.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'members',
            'items' => $items,
            'meta' => $meta,
            'filters' => $filters,
            'requiresAuth' => $userId === null,
            'error' => $error,
        ]);
    }

    public function myProfile(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();

        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $this->memberProfile($request, $tenantSlug, $userId);
    }

    public function memberProfile(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('connections'), 403);
        $viewerId = $this->currentUserId();

        if ($viewerId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $profile = $this->profileForViewer($id, $viewerId);
        abort_if($profile === null, 404);

        $displayName = $this->profileDisplayName($profile);

        return $this->view('accessible-frontend::profile', [
            'title' => $displayName,
            'tenantSlug' => $tenantSlug,
            'activeNav' => $id === $viewerId ? 'profile' : 'members',
            'profile' => $profile,
            'displayName' => $displayName,
            'isOwnProfile' => $id === $viewerId,
            'status' => self::asStr($request->query('status')) ?: null,
            'profileStats' => $this->profileStats($profile),
            'profileListings' => $this->profileListings($id),
            'profileSkills' => $this->profileSkills($id, $profile),
            'profileAvailability' => $this->profileAvailability($id, $profile),
            'profileReviews' => $this->profileReviews($id),
            'memberId' => $id,
            'directMessagingEnabled' => BrokerControlConfigService::isDirectMessagingEnabled(),
            'profileBadges' => $this->memberBadges($id),
            'connectionState' => $id === $viewerId
                ? null
                : (ConnectionService::getStatus($viewerId, $id)['status'] ?? 'none'),
            'endorsements' => $this->memberEndorsements($id, $viewerId),
            'canEndorse' => $id !== $viewerId,
            'profileActivity' => $this->profileActivity($id),
        ]);
    }

    /**
     * Recent public activity for a member (posts, hours, comments, connections,
     * event RSVPs) via the shared MemberActivityService. Never blocks render.
     *
     * @return array<int, array<string, mixed>>
     */
    private function profileActivity(int $id): array
    {
        try {
            return app(\App\Services\MemberActivityService::class)
                ->getRecentTimeline($id, TenantContext::getId(), 12);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Skill-endorsement counts for a profile, plus the set of skills the viewer
     * has already endorsed (so the UI can offer endorse vs remove). Never blocks
     * the page render.
     *
     * @return array{counts: array<string, int>, viewerEndorsed: array<int, string>}
     */
    private function memberEndorsements(int $profileUserId, int $viewerId): array
    {
        $counts = [];
        $viewerEndorsed = [];

        try {
            foreach (\App\Services\EndorsementService::getEndorsements($profileUserId) as $group) {
                $skill = (string) ($group['skill_name'] ?? '');
                if ($skill === '') {
                    continue;
                }
                $counts[$skill] = (int) ($group['count'] ?? 0);
                foreach (($group['endorsers'] ?? []) as $endorser) {
                    if ((int) ($endorser['id'] ?? 0) === $viewerId) {
                        $viewerEndorsed[] = $skill;
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return ['counts' => $counts, 'viewerEndorsed' => $viewerEndorsed];
    }

    public function endorseMemberSkill(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('connections'), 403);

        $viewerId = $this->currentUserId();
        if ($viewerId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $skillName = trim(self::asStr($request->input('skill_name')));
        $action = $this->allowed($request->input('action'), ['endorse', 'remove'], '');

        if ($id === $viewerId || $skillName === '' || $action === '') {
            return redirect()->route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'endorsement-failed']);
        }

        // Confirm the target is a real member of this tenant before acting.
        abort_if($this->profileForViewer($id, $viewerId) === null, 404);

        $status = 'endorsement-failed';
        try {
            if ($action === 'endorse') {
                $status = \App\Services\EndorsementService::endorse($viewerId, $id, $skillName) !== null
                    ? 'endorsement-added' : 'endorsement-failed';
            } else {
                $status = \App\Services\EndorsementService::removeEndorsement($viewerId, $id, $skillName)
                    ? 'endorsement-removed' : 'endorsement-failed';
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]);
    }

    /**
     * Send, accept, decline, cancel or remove a connection with another member.
     *
     * The action is dispatched on a single posted field (mirroring the exchange
     * action form), and every transition is re-validated against the live
     * connection status server-side so a stale form cannot drive an invalid
     * state change. The connection id is always resolved from the service, never
     * trusted from the request.
     */
    public function updateMemberConnection(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('connections'), 403);

        $viewerId = $this->currentUserId();
        if ($viewerId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        if ($id === $viewerId) {
            return redirect()->route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'connection-failed']);
        }

        // Confirm the target is a real member of this tenant before acting.
        $target = $this->profileForViewer($id, $viewerId);
        abort_if($target === null, 404);

        $action = $this->allowed($request->input('action'), ['connect', 'accept', 'decline', 'cancel', 'remove'], '');
        $current = ConnectionService::getStatus($viewerId, $id);
        $connectionId = (int) ($current['connection_id'] ?? 0);
        $status = 'connection-failed';

        try {
            switch ($action) {
                case 'connect':
                    if (($current['status'] ?? 'none') === 'none') {
                        $status = ConnectionService::sendRequest($viewerId, $id) === true
                            ? 'connection-sent' : 'connection-failed';
                    }
                    break;
                case 'accept':
                    if (($current['status'] ?? null) === 'pending_received' && $connectionId > 0) {
                        $status = ConnectionService::acceptRequest($connectionId, $viewerId)
                            ? 'connection-accepted' : 'connection-failed';
                    }
                    break;
                case 'decline':
                    if (($current['status'] ?? null) === 'pending_received' && $connectionId > 0) {
                        $status = ConnectionService::rejectRequest($connectionId, $viewerId)
                            ? 'connection-declined' : 'connection-failed';
                    }
                    break;
                case 'cancel':
                    if (($current['status'] ?? null) === 'pending_sent' && $connectionId > 0) {
                        $status = ConnectionService::removeConnection($connectionId, $viewerId)
                            ? 'connection-cancelled' : 'connection-failed';
                    }
                    break;
                case 'remove':
                    if (($current['status'] ?? null) === 'connected' && $connectionId > 0) {
                        $status = ConnectionService::removeConnection($connectionId, $viewerId)
                            ? 'connection-removed' : 'connection-failed';
                    }
                    break;
            }
        } catch (\Throwable $e) {
            report($e);
            $status = 'connection-failed';
        }

        return redirect()->route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]);
    }

    /**
     * A member's earned badges (public achievements), for the profile page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function memberBadges(int $userId): array
    {
        try {
            return \App\Services\GamificationService::getBadges($userId, TenantContext::getId());
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    public function profileSettings(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();

        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $profile = $this->profileForViewer($userId, $userId);
        abort_if($profile === null, 404);

        $account = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->select(
                'newsletter_opt_in', 'email', 'preferred_language', 'privacy_contact',
                'prefers_chronological_feed', 'auto_translate_ugc', 'auto_translate_target_locale'
            )
            ->first();

        return $this->view('accessible-frontend::profile-settings', [
            'title' => __('govuk_alpha.profile_settings.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'profile',
            'profile' => $profile,
            'displayName' => $this->profileDisplayName($profile),
            'avatarUrl' => $profile['avatar_url'] ?? $profile['avatar'] ?? null,
            'marketingOptIn' => (bool) ($account->newsletter_opt_in ?? false),
            'privacyContact' => (bool) ($account->privacy_contact ?? false),
            'currentEmail' => (string) ($account->email ?? ''),
            'currentLanguage' => (string) ($account->preferred_language ?? app()->getLocale()),
            'locales' => self::ALPHA_LOCALES,
            'notificationPrefs' => $this->alphaNotificationPrefs($userId),
            'passkeys' => $this->alphaPasskeys($userId),
            'prefersChronological' => (bool) ($account->prefers_chronological_feed ?? false),
            'autoTranslate' => (bool) ($account->auto_translate_ugc ?? false),
            'autoTranslateLocale' => (string) ($account->auto_translate_target_locale ?? $account->preferred_language ?? 'en'),
            'matchPrefs' => $this->alphaMatchPrefs($userId),
            'mySkills' => $this->alphaUserSkills($userId),
            'sessions' => $this->alphaSessions($userId),
            'safeguarding' => $this->alphaSafeguardingPreferences($userId),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Match-digest preferences (frequency + hot/mutual toggles) for the alpha
     * settings page, mirroring MatchPreferencesController::show defaults.
     *
     * @return array{notification_frequency: string, notify_hot_matches: bool, notify_mutual_matches: bool}
     */
    private function alphaMatchPrefs(int $userId): array
    {
        $defaults = ['notification_frequency' => 'monthly', 'notify_hot_matches' => true, 'notify_mutual_matches' => true];
        try {
            $prefs = \App\Services\MatchingService::getPreferences($userId);

            return [
                'notification_frequency' => (string) ($prefs['notification_frequency'] ?? 'monthly'),
                'notify_hot_matches' => (bool) ($prefs['notify_hot_matches'] ?? true),
                'notify_mutual_matches' => (bool) ($prefs['notify_mutual_matches'] ?? true),
            ];
        } catch (\Throwable $e) {
            report($e);

            return $defaults;
        }
    }

    /**
     * The viewer's skills (offering/requesting) via the shared
     * SkillTaxonomyService (tenant-scoped internally).
     *
     * @return array<int, array<string, mixed>>
     */
    private function alphaUserSkills(int $userId): array
    {
        try {
            return app(\App\Services\SkillTaxonomyService::class)->getUserSkills($userId);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * The viewer's active safeguarding preferences (label + what each one
     * activates), mirroring SafeguardingMemberController::myPreferences. Empty
     * for members who never set any.
     *
     * @return array<int, array<string, mixed>>
     */
    private function alphaSafeguardingPreferences(int $userId): array
    {
        try {
            $tenantId = TenantContext::getId();
            $rows = DB::table('user_safeguarding_preferences as p')
                ->join('tenant_safeguarding_options as o', function ($join) use ($tenantId) {
                    $join->on('o.id', '=', 'p.option_id')->where('o.tenant_id', $tenantId)->where('o.is_active', 1);
                })
                ->where('p.tenant_id', $tenantId)
                ->where('p.user_id', $userId)
                ->whereNull('p.revoked_at')
                ->select(['p.option_id', 'o.label', 'o.description', 'o.triggers'])
                ->orderBy('o.sort_order')
                ->get();

            return $rows->map(static function ($row): array {
                $triggers = is_string($row->triggers) ? (json_decode($row->triggers, true) ?: []) : (array) ($row->triggers ?? []);

                return [
                    'option_id' => (int) $row->option_id,
                    'label' => $row->label,
                    'description' => $row->description,
                    'restricts_messaging' => (bool) ($triggers['restricts_messaging'] ?? false),
                    'restricts_matching' => (bool) ($triggers['restricts_matching'] ?? false),
                    'requires_broker_approval' => (bool) ($triggers['requires_broker_approval'] ?? false),
                    'requires_vetted_interaction' => (bool) ($triggers['requires_vetted_interaction'] ?? false),
                ];
            })->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * The viewer's active sign-in sessions/devices (read-only list; no revoke
     * endpoint exists), mirroring UsersController::sessions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function alphaSessions(int $userId): array
    {
        try {
            return DB::table('sessions')
                ->where('user_id', $userId)
                ->where('tenant_id', TenantContext::getId())
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->orderByDesc('last_activity')
                ->limit(20)
                ->select(['id', 'ip_address', 'user_agent', 'device_type', 'last_activity'])
                ->get()
                ->map(static fn ($r): array => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * The 16 notification toggles with their defaults, mirroring
     * UsersController::notificationPreferences so the alpha settings page
     * shows the same controls as the React NotificationsTab.
     *
     * @return array<string, bool>
     */
    private function alphaNotificationPrefs(int $userId): array
    {
        $prefs = User::getNotificationPreferences($userId);
        $fedEnabled = (bool) (DB::table('users')->where('id', $userId)->value('federation_notifications_enabled') ?? 1);

        return [
            'email_messages'                => (bool) ($prefs['email_messages'] ?? true),
            'email_connections'             => (bool) ($prefs['email_connections'] ?? true),
            'caring_smart_nudges'           => (bool) ($prefs['caring_smart_nudges'] ?? true),
            'federation_notifications_enabled' => $fedEnabled,
            'email_listings'                => (bool) ($prefs['email_listings'] ?? true),
            'email_transactions'            => (bool) ($prefs['email_transactions'] ?? true),
            'email_reviews'                 => (bool) ($prefs['email_reviews'] ?? true),
            'email_gamification_digest'     => (bool) ($prefs['email_gamification_digest'] ?? true),
            'email_gamification_milestones' => (bool) ($prefs['email_gamification_milestones'] ?? true),
            'email_digest'                  => (bool) ($prefs['email_digest'] ?? false),
            'email_org_payments'            => (bool) ($prefs['email_org_payments'] ?? true),
            'email_org_transfers'           => (bool) ($prefs['email_org_transfers'] ?? true),
            'email_org_membership'          => (bool) ($prefs['email_org_membership'] ?? true),
            'email_org_admin'               => (bool) ($prefs['email_org_admin'] ?? true),
            'push_enabled'                  => (bool) ($prefs['push_enabled'] ?? true),
            'push_campaigns_opted_in'       => (bool) ($prefs['push_campaigns_opted_in'] ?? false),
        ];
    }

    /**
     * Persist the alpha notification-preference form. Checkboxes that are
     * unticked do not post, so every known key is read as a boolean (absent =
     * off) to honour the user's full intent.
     */
    public function updateProfileNotifications(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $jsonKeys = [
            'email_messages', 'email_connections', 'caring_smart_nudges',
            'email_listings', 'email_transactions', 'email_reviews',
            'email_gamification_digest', 'email_gamification_milestones', 'email_digest',
            'email_org_payments', 'email_org_transfers', 'email_org_membership', 'email_org_admin',
            'push_enabled', 'push_campaigns_opted_in',
        ];
        $prefs = [];
        foreach ($jsonKeys as $key) {
            $prefs[$key] = $request->boolean($key);
        }

        try {
            $ok = User::updateNotificationPreferences($userId, $prefs);
            DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', TenantContext::getId())
                ->update([
                    'federation_notifications_enabled' => $request->boolean('federation_notifications_enabled') ? 1 : 0,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        return redirect()->route('govuk-alpha.profile.settings', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'notifications-saved' : 'notifications-failed',
        ])->withFragment('notifications');
    }

    /**
     * The viewer's registered passkeys (tenant + user scoped), mirroring
     * WebAuthnController::credentials so the alpha settings page can list,
     * rename and remove them without JavaScript.
     *
     * @return array<int, array<string, mixed>>
     */
    private function alphaPasskeys(int $userId): array
    {
        try {
            $rows = DB::select(
                'SELECT credential_id, device_name, authenticator_type, created_at, last_used_at
                 FROM webauthn_credentials
                 WHERE user_id = ? AND tenant_id = ?
                 ORDER BY created_at DESC',
                [$userId, TenantContext::getId()]
            );

            return array_map(static fn ($r): array => (array) $r, $rows);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Rename one of the viewer's passkeys. */
    public function renameProfilePasskey(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $credentialId = trim(self::asStr($request->input('credential_id')));
        $name = mb_substr(trim(self::asStr($request->input('device_name'))), 0, 100);

        if ($credentialId === '' || $name === '') {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'passkey-name-required'])->withFragment('passkeys');
        }

        $affected = DB::update(
            'UPDATE webauthn_credentials SET device_name = ? WHERE credential_id = ? AND user_id = ? AND tenant_id = ?',
            [$name, $credentialId, $userId, TenantContext::getId()]
        );

        return redirect()->route('govuk-alpha.profile.settings', [
            'tenantSlug' => $tenantSlug,
            'status' => $affected > 0 ? 'passkey-renamed' : 'passkey-not-found',
        ])->withFragment('passkeys');
    }

    /** Remove one of the viewer's passkeys. */
    public function removeProfilePasskey(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $credentialId = trim(self::asStr($request->input('credential_id')));
        if ($credentialId === '') {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'passkey-not-found'])->withFragment('passkeys');
        }

        $deleted = DB::delete(
            'DELETE FROM webauthn_credentials WHERE credential_id = ? AND user_id = ? AND tenant_id = ?',
            [$credentialId, $userId, TenantContext::getId()]
        );

        return redirect()->route('govuk-alpha.profile.settings', [
            'tenantSlug' => $tenantSlug,
            'status' => $deleted > 0 ? 'passkey-removed' : 'passkey-not-found',
        ])->withFragment('passkeys');
    }

    /** Personalisation: chronological feed + UGC auto-translation (user columns). */
    public function updateProfilePersonalisation(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $locale = $this->allowed($request->input('auto_translate_target_locale'), self::ALPHA_LOCALES, null);

        try {
            DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', TenantContext::getId())
                ->update([
                    'prefers_chronological_feed'   => $request->boolean('prefers_chronological'),
                    'auto_translate_ugc'           => $request->boolean('auto_translate_ugc'),
                    'auto_translate_target_locale' => $locale,
                    'updated_at'                   => now(),
                ]);
            $status = 'personalisation-saved';
        } catch (\Throwable $e) {
            report($e);
            $status = 'personalisation-failed';
        }

        return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => $status])->withFragment('personalisation');
    }

    /** Match-digest preferences (frequency + hot/mutual toggles). */
    public function updateProfileMatchPreferences(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $freq = $this->allowed($request->input('notification_frequency'), ['daily', 'weekly', 'monthly', 'fortnightly', 'never'], 'monthly');

        try {
            // Preserve the rest of the preference row (distance, score, …). The
            // alpha does not edit category matches, so drop 'categories' from the
            // payload — that keeps the existing categories untouched AND avoids the
            // service's optional (non-tenant-scoped) category-sync delete path.
            $updated = \App\Services\MatchingService::getPreferences($userId);
            unset($updated['categories']);
            $updated['notification_frequency'] = $freq;
            $updated['notify_hot_matches'] = $request->boolean('notify_hot_matches');
            $updated['notify_mutual_matches'] = $request->boolean('notify_mutual_matches');
            $ok = \App\Services\MatchingService::savePreferences($userId, $updated);
            $status = $ok ? 'match-prefs-saved' : 'match-prefs-failed';
        } catch (\Throwable $e) {
            report($e);
            $status = 'match-prefs-failed';
        }

        return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => $status])->withFragment('match-preferences');
    }

    /** Add a skill (offering/requesting) to the viewer's profile. */
    public function addProfileSkill(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $name = trim(self::asStr($request->input('skill_name')));
        if ($name === '') {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'skill-name-required'])->withFragment('skills');
        }

        // Offer is the sensible default; both can be set.
        $isOffering = $request->boolean('is_offering') || !$request->boolean('is_requesting');

        try {
            $id = app(\App\Services\SkillTaxonomyService::class)->addUserSkill($userId, [
                'skill_name'    => mb_substr($name, 0, 100),
                'is_offering'   => $isOffering,
                'is_requesting' => $request->boolean('is_requesting'),
            ]);
            $status = $id !== null ? 'skill-added' : 'skill-failed';
        } catch (\Throwable $e) {
            report($e);
            $status = 'skill-failed';
        }

        return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => $status])->withFragment('skills');
    }

    /** Remove one of the viewer's skills. */
    public function removeProfileSkill(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $skillId = (int) $request->input('user_skill_id');
        try {
            $ok = $skillId > 0 && app(\App\Services\SkillTaxonomyService::class)->removeSkill($userId, $skillId);
            $status = $ok ? 'skill-removed' : 'skill-failed';
        } catch (\Throwable $e) {
            report($e);
            $status = 'skill-failed';
        }

        return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => $status])->withFragment('skills');
    }

    /** Revoke (withdraw) one of the viewer's safeguarding preferences. */
    public function revokeProfileSafeguarding(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $optionId = (int) $request->input('option_id');
        try {
            $ok = $optionId > 0 && \App\Services\SafeguardingPreferenceService::revokePreference($userId, $optionId);
            $status = $ok ? 'safeguarding-revoked' : 'safeguarding-failed';
        } catch (\Throwable $e) {
            report($e);
            $status = 'safeguarding-failed';
        }

        return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => $status])->withFragment('safeguarding');
    }

    public function updateProfileEmail(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $email = trim(self::asStr($request->input('email')));
        $currentPassword = self::asStr($request->input('current_password'));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'email-invalid']);
        }

        // The v2 profile update does not gate email changes on the password, but a
        // self-service email change is sensitive — re-authenticate first.
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->select('password_hash', 'email')
            ->first();

        if ($user === null || !password_verify($currentPassword, (string) $user->password_hash)) {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'email-password-incorrect']);
        }

        if (strcasecmp($email, (string) $user->email) === 0) {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'email-unchanged']);
        }

        try {
            $ok = UserService::updateProfile($userId, ['email' => $email]);
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        return redirect()->route('govuk-alpha.profile.settings', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'email-changed' : 'email-failed',
        ]);
    }

    public function updateProfilePassword(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $current = self::asStr($request->input('current_password'));
        $new = self::asStr($request->input('new_password'));
        $confirm = self::asStr($request->input('new_password_confirmation'));

        $preStatus = match (true) {
            $current === ''                  => 'password-current-required',
            $new === '' || mb_strlen($new) < 12 => 'password-weak',
            $new !== $confirm                => 'password-mismatch',
            default                          => null,
        };
        if ($preStatus !== null) {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => $preStatus]);
        }

        try {
            $ok = UserService::updatePassword($userId, $current, $new);
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        if ($ok) {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'password-changed']);
        }

        $code = strtoupper((string) (UserService::getErrors()[0]['code'] ?? ''));
        $status = match (true) {
            str_contains($code, 'INVALID') => 'password-current-incorrect',
            str_contains($code, 'REUSED')  => 'password-reused',
            str_contains($code, 'WEAK')    => 'password-weak',
            default                        => 'password-failed',
        };

        return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    public function updateProfileLanguage(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $language = $this->allowed($request->input('language'), self::ALPHA_LOCALES, null);
        if ($language === null) {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'language-invalid']);
        }

        DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->update(['preferred_language' => $language, 'updated_at' => now()]);

        if ($request->hasSession()) {
            $request->session()->put('locale', $language);
        }
        $_SESSION['locale'] = $language;

        return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'language-changed']);
    }

    public function updateProfileSettings(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();

        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $profileType = $this->allowed($request->input('profile_type', 'individual'), ['individual', 'organisation'], 'individual');
        $privacyProfile = $this->allowed($request->input('privacy_profile', 'public'), ['public', 'members', 'connections'], 'public');

        $data = [
            'first_name' => trim(self::asStr($request->input('first_name'))),
            'last_name' => trim(self::asStr($request->input('last_name'))),
            'phone' => trim(self::asStr($request->input('phone'))),
            'profile_type' => $profileType,
            'organization_name' => trim(self::asStr($request->input('organization_name'))),
            'tagline' => trim(self::asStr($request->input('tagline'))),
            'bio' => trim(self::asStr($request->input('bio'))),
            'location' => trim(self::asStr($request->input('location'))),
        ];

        if (!$this->profileSettingsInputIsValid($data)) {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'profile-update-failed']);
        }

        // Profile photo: validate and store BEFORE the text update so an invalid
        // image aborts the whole save with a specific message rather than silently
        // persisting half the form. ImageUploader crops avatars to a 400x400 square.
        if ($request->hasFile('avatar')) {
            $avatarResult = $this->storeUploadedAvatar($request, $userId);
            if ($avatarResult === false) {
                return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'avatar-invalid']);
            }
        } elseif ($request->boolean('remove_avatar')) {
            DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', TenantContext::getId())
                ->update(['avatar_url' => null, 'updated_at' => now()]);
        }

        $success = UserService::updateProfile($userId, $data);
        if (!$success) {
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'profile-update-failed']);
        }

        DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->update([
                'name' => trim($data['first_name'] . ' ' . $data['last_name']) ?: ($data['organization_name'] ?: __('govuk_alpha.members.unknown_member')),
                'privacy_profile' => $privacyProfile,
                'privacy_search' => $request->boolean('privacy_search'),
                // Whether other members may contact this member directly.
                'privacy_contact' => $request->boolean('privacy_contact'),
                // GDPR marketing consent — recipient-controlled newsletter opt-in.
                'newsletter_opt_in' => $request->boolean('newsletter_opt_in'),
                'updated_at' => now(),
            ]);

        return redirect()->route('govuk-alpha.profile.me', ['tenantSlug' => $tenantSlug, 'status' => 'profile-updated']);
    }

    /**
     * Store an uploaded profile photo for the user via the shared image
     * pipeline (validates MIME/size, crops to a 400x400 square). Returns the
     * stored URL on success, or false when the upload is missing/invalid so the
     * caller can surface a single "avatar-invalid" message.
     */
    private function storeUploadedAvatar(Request $request, int $userId): string|false
    {
        $file = $request->file('avatar');
        if ($file === null || is_array($file) || !$file->isValid()) {
            return false;
        }

        try {
            $avatarUrl = UserService::updateAvatar($userId, [
                'name' => $file->getClientOriginalName(),
                'type' => $file->getMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error' => UPLOAD_ERR_OK,
                'size' => $file->getSize(),
            ]);
        } catch (\Throwable $e) {
            // ImageUploader throws on disallowed type / oversize / decompression
            // bomb — treat all as a validation failure, not a 500.
            report($e);
            return false;
        }

        return $avatarUrl ?: false;
    }

    public function requestDataExport(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();

        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        try {
            app(\App\Services\Enterprise\GdprService::class)->createRequest($userId, 'portability', [
                'notes' => __('govuk_alpha.profile_settings.data_export_note'),
                'metadata' => ['requested_via' => 'accessible-frontend', 'self_service' => true],
            ]);
        } catch (\RuntimeException) {
            // Same-type request already pending — tell the user it's in progress.
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'data-export-exists']);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'data-export-failed']);
        }

        return redirect()->route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug, 'status' => 'data-export-requested']);
    }

    public function confirmDeleteAccount(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();

        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $this->view('accessible-frontend::profile-delete', [
            'title' => __('govuk_alpha.delete_account.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'profile',
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function deleteAccount(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();

        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $password = self::asStr($request->input('password'));
        if ($password === '') {
            return redirect()->route('govuk-alpha.profile.delete', ['tenantSlug' => $tenantSlug, 'status' => 'delete-password-required']);
        }

        if (!$request->boolean('confirm')) {
            return redirect()->route('govuk-alpha.profile.delete', ['tenantSlug' => $tenantSlug, 'status' => 'delete-confirm-required']);
        }

        // Re-authenticate with the password before destroying the account —
        // mirrors the React/API GdprController::deleteAccount contract.
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->select('password_hash')
            ->first();

        if ($user === null || !password_verify($password, (string) $user->password_hash)) {
            return redirect()->route('govuk-alpha.profile.delete', ['tenantSlug' => $tenantSlug, 'status' => 'delete-password-incorrect']);
        }

        try {
            app(\App\Services\Enterprise\GdprService::class)->createRequest($userId, 'erasure', [
                'notes' => trim(self::asStr($request->input('reason'))) ?: null,
                'metadata' => ['requested_via' => 'accessible-frontend', 'self_service' => true],
            ]);
        } catch (\RuntimeException) {
            // Erasure already pending — fall through to sign-out + confirmation.
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.profile.delete', ['tenantSlug' => $tenantSlug, 'status' => 'delete-failed']);
        }

        // Sign the user out immediately — the API contract returns logout_required.
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['user_id']);
        }
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'account-deletion-requested'])
            ->withCookie(cookie()->forget('auth_token', '/'));
    }

    private function view(string $name, array $data = [], int $status = 200): Response
    {
        return response()
            ->view($name, array_merge($this->sharedViewData(), $data), $status)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function sharedViewData(): array
    {
        $logoUrl = TenantContext::getSetting('logo_url');
        $logoDarkUrl = TenantContext::getSetting('logo_dark_url');
        $logoUrl = is_string($logoUrl) ? $logoUrl : null;
        $logoDarkUrl = is_string($logoDarkUrl) ? $logoDarkUrl : null;
        // Header is always black, so the dark variant (when present) is what's shown.
        $effectiveLogo = $logoDarkUrl ?: $logoUrl;

        // Optional per-tenant header theming (stored next to the logo in
        // tenants.configuration). Values are re-validated to #rrggbb here so
        // they are safe to inline into a <style> block; anything invalid or
        // absent falls back to the stock GOV.UK black + blue in the stylesheet.
        // When a custom background is set we also pick a readable foreground
        // (white or near-black) so header text keeps WCAG-AA contrast.
        $headerBg = $this->normalizeHeaderColor(TenantContext::getSetting('header_bg_color'));
        $headerAccent = $this->normalizeHeaderColor(TenantContext::getSetting('header_accent_color'));
        $headerFg = $headerBg !== null ? $this->readableForeground($headerBg) : null;

        return [
            'assetEntrypoint' => $this->assetEntrypoint(),
            'tenant' => TenantContext::get(),
            // Tenant-uploaded header logo (overrides the text brand). Resolved to
            // same-origin URLs. The GOV.UK alpha header is always black, so the
            // template prefers the dark-background variant when one was uploaded.
            'tenantLogoUrl' => $this->resolveAsset($logoUrl),
            'tenantLogoDarkUrl' => $this->resolveAsset($logoDarkUrl),
            // Smart sizing: bucket the logo by aspect ratio so a wide wordmark and
            // a square/stacked crest both read at a sensible size in the header.
            'tenantLogoShape' => $this->logoShapeClass($effectiveLogo),
            // Per-tenant header colours (null = keep the stock GOV.UK black + blue).
            'alphaHeaderBg' => $headerBg,
            'alphaHeaderAccent' => $headerAccent,
            'alphaHeaderFg' => $headerFg['fg'] ?? null,
            'alphaHeaderFgHover' => $headerFg['hover'] ?? null,
            'isAuthenticated' => $this->currentUserId() !== null,
            'alphaNavItems' => $this->alphaNavItems(),
            'alphaFooterColumns' => $this->alphaFooterColumns(),
            'alphaSignOutUrl' => $this->alphaSignOutUrl(),
            // Global, no-JS language switcher + RTL support (all 11 platform
            // locales are enabled for every tenant by default).
            'alphaLocaleOptions' => $this->alphaLocaleOptions(),
            'alphaCurrentLocale' => app()->getLocale(),
            'alphaTextDirection' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'alphaUnreadMessages' => $this->alphaUnreadMessages(),
            'feedbackUrl' => $this->feedbackUrl(),
            'mainSiteUrl' => $this->mainSiteUrl(),
            'metaDescription' => __('govuk_alpha.seo.description'),
            'canonicalUrl' => request()->url(),
            'robotsDirective' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'defaultOgImage' => 'https://project-nexus.ie/og-image.png',
        ];
    }

    /**
     * Validate + normalise a #rrggbb hex colour for safe inlining into the
     * header <style> block. Accepts an optional '#' and 3- or 6-digit hex
     * (shorthand expanded); returns null for empty / invalid input.
     */
    private function normalizeHeaderColor($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $hex = ltrim(trim($value), '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? '#' . strtolower($hex) : null;
    }

    /**
     * Pick a header foreground (text) colour that stays legible on $hexBg,
     * using the WCAG relative-luminance contrast formula — white vs near-black,
     * whichever yields the higher contrast ratio. Returns the base text colour
     * and a slightly-muted hover variant.
     *
     * @return array{fg: string, hover: string}
     */
    private function readableForeground(string $hexBg): array
    {
        $channel = static function (string $pair): float {
            $c = hexdec($pair) / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $channel(substr($hexBg, 1, 2))
            + 0.7152 * $channel(substr($hexBg, 3, 2))
            + 0.0722 * $channel(substr($hexBg, 5, 2));

        // Contrast ratio of white (L=1) and near-black (#0b0c0c, L≈0) on $hexBg.
        $contrastWhite = (1.0 + 0.05) / ($luminance + 0.05);
        $contrastBlack = ($luminance + 0.05) / (0.0 + 0.05);

        return $contrastWhite >= $contrastBlack
            ? ['fg' => '#ffffff', 'hover' => '#f3f2f1']
            : ['fg' => '#0b0c0c', 'hover' => '#505a5f'];
    }

    /**
     * Language options for the global locale switcher, keyed by code with the
     * language's own endonym as the label (a name reads the same in every locale).
     *
     * @return array<string, string>
     */
    private function alphaLocaleOptions(): array
    {
        $options = [];
        foreach (self::ALPHA_LOCALES as $code) {
            $options[$code] = __('govuk_alpha.profile_settings.languages.' . $code);
        }

        return $options;
    }

    /**
     * Unread-message total for the signed-in member, for the nav badge. Returns
     * 0 for anonymous viewers or on any failure (never blocks a page render).
     */
    private function alphaUnreadMessages(): int
    {
        $userId = $this->currentUserId();
        if ($userId === null || ! TenantContext::hasModule('messages')) {
            return 0;
        }

        try {
            return MessageService::getUnreadCount($userId);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function mainSiteUrl(): string
    {
        $tenant = TenantContext::get();
        $tenantSlug = (string) ($tenant['slug'] ?? '');
        $frontendUrl = rtrim(TenantContext::getFrontendUrl(), '/');

        if ($tenantSlug === '') {
            return $frontendUrl;
        }

        $host = strtolower((string) parse_url($frontendUrl, PHP_URL_HOST));
        $sharedHosts = ['app.project-nexus.ie', 'localhost', '127.0.0.1'];

        if (in_array($host, $sharedHosts, true)) {
            return $frontendUrl . '/' . rawurlencode($tenantSlug);
        }

        return $frontendUrl;
    }

    private function feedbackUrl(): string
    {
        $tenant = TenantContext::get();
        $tenantSlug = (string) ($tenant['slug'] ?? '');

        // The host tenant (id 1) renders tenant-agnostic pages (the tenant
        // chooser) — same rule as tenantChooser(). Its feedback link must be
        // the generic platform mailto, not a tenant contact form.
        if ($tenantSlug === '' || (int) ($tenant['id'] ?? 0) <= 1) {
            return (string) __('govuk_alpha.feedback_url');
        }

        return route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug]);
    }

    private function alphaNavItems(): array
    {
        $tenant = TenantContext::get();
        $tenantSlug = (string) ($tenant['slug'] ?? '');
        if ($tenantSlug === '') {
            return [];
        }

        $userId = $this->currentUserId();
        $items = [];

        if ($userId === null) {
            $items['home'] = route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]);
        } else {
            // Personal/transactional items (Wallet, Messages, Connections, Matches,
            // Group exchanges, gamification) live in the top header "My account"
            // hub, not the service nav — which is reserved for community +
            // discovery facilities to keep the flat GOV.UK bar uncrowded. See
            // account() + the header in layout.blade.php.
            $items['dashboard'] = route('govuk-alpha.dashboard', ['tenantSlug' => $tenantSlug]);
        }

        if (TenantContext::hasModule('feed')) {
            $items['feed'] = route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]);
        }

        if (TenantContext::hasModule('listings')) {
            $items['listings'] = route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]);
            if ($userId !== null && BrokerControlConfigService::isExchangeWorkflowEnabled()) {
                $items['exchanges'] = route('govuk-alpha.exchanges.index', ['tenantSlug' => $tenantSlug]);
            }
        }

        if (TenantContext::hasFeature('connections')) {
            $items['members'] = route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]);
        }

        if (TenantContext::hasFeature('events')) {
            $items['events'] = route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]);
        }

        if (TenantContext::hasFeature('volunteering')) {
            $items['volunteering'] = route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]);
        }

        // "Explore" is the gateway to discovery facilities (groups, goals, skills,
        // organisations, marketplace, jobs, courses, …) so the flat bar stays lean.
        if ($userId !== null) {
            $items['explore'] = route('govuk-alpha.explore', ['tenantSlug' => $tenantSlug]);
        }

        // Polls sit last in the bar, after Volunteering.
        if ($userId !== null && TenantContext::hasFeature('polls')) {
            $items['polls'] = route('govuk-alpha.polls.index', ['tenantSlug' => $tenantSlug]);
        }

        return $items;
    }

    /**
     * Build the GOV.UK footer navigation columns. The React frontend footer is
     * the source of truth for which links appear; the Platform column is gated
     * by the same module/feature checks as alphaNavItems(), while Support and
     * Legal are universal. Each value is `key => href`; the Blade resolves the
     * label via govuk_alpha.footer.columns.<column>.<key>.
     */
    private function alphaFooterColumns(): array
    {
        $tenant = TenantContext::get();
        $tenantSlug = (string) ($tenant['slug'] ?? '');
        if ($tenantSlug === '') {
            return [];
        }

        $route = static fn (string $name): string => route($name, ['tenantSlug' => $tenantSlug]);

        $platform = [];
        if (TenantContext::hasModule('listings')) {
            $platform['listings'] = $route('govuk-alpha.listings.index');
        }
        if (TenantContext::hasFeature('connections')) {
            $platform['members'] = $route('govuk-alpha.members.index');
        }
        if (TenantContext::hasFeature('events')) {
            $platform['events'] = $route('govuk-alpha.events.index');
        }
        if (TenantContext::hasFeature('volunteering')) {
            $platform['volunteering'] = $route('govuk-alpha.volunteering.index');
        }
        if (TenantContext::hasFeature('blog')) {
            $platform['blog'] = $route('govuk-alpha.blog.index');
        }

        $support = [
            'help' => $route('govuk-alpha.help'),
            'kb' => $route('govuk-alpha.kb.index'),
            'trust_safety' => $route('govuk-alpha.trust-safety'),
            'contact' => $route('govuk-alpha.contact'),
            'about' => $route('govuk-alpha.about'),
        ];

        $legal = [
            'legal_hub' => $route('govuk-alpha.legal.hub'),
            'terms' => $route('govuk-alpha.legal.terms'),
            'privacy' => $route('govuk-alpha.legal.privacy'),
            'community_guidelines' => $route('govuk-alpha.legal.community-guidelines'),
            'acceptable_use' => $route('govuk-alpha.legal.acceptable-use'),
            'cookies' => $route('govuk-alpha.legal.cookies'),
            'accessibility' => $route('govuk-alpha.accessibility'),
        ];

        $columns = ['support' => $support, 'legal' => $legal];
        if ($platform !== []) {
            $columns = ['platform' => $platform] + $columns;
        }

        return $columns;
    }

    /**
     * The sign-out URL for the footer meta row. Sign-out changes state, so the
     * Blade renders it as a CSRF-protected POST form, not a link. Null when the
     * visitor is not signed in or no tenant is resolved.
     */
    private function alphaSignOutUrl(): ?string
    {
        $tenant = TenantContext::get();
        $tenantSlug = (string) ($tenant['slug'] ?? '');
        if ($tenantSlug === '' || $this->currentUserId() === null) {
            return null;
        }

        return route('govuk-alpha.logout', ['tenantSlug' => $tenantSlug]);
    }

    /**
     * Live platform statistics for the About page, reusing the same cached
     * computation as the React app's GET /v2/platform/stats. Returns null on
     * any failure so the About page simply hides the stats band.
     */
    private function platformStats(): ?array
    {
        try {
            $response = app(\App\Http\Controllers\Api\TenantBootstrapController::class)->platformStats();
            $decoded = json_decode((string) $response->getContent(), true);
            if (! is_array($decoded)) {
                return null;
            }

            return isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * The shared contributors list rendered on the About page — the same
     * react-frontend/src/data/contributors.json the React About page uses, so
     * credits stay in a single source of truth.
     */
    private function aboutContributors(): array
    {
        $path = base_path('react-frontend/src/data/contributors.json');
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function assetEntrypoint(): array
    {
        $manifestPath = base_path('httpdocs/build/accessible-frontend/.vite/manifest.json');
        if (!is_file($manifestPath)) {
            return ['css' => [], 'js' => null];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $entry = $manifest['accessible-frontend/src/app.ts'] ?? [];

        return [
            'css' => array_map(fn (string $file): string => '/build/accessible-frontend/' . $file, $entry['css'] ?? []),
            'js' => isset($entry['file']) ? '/build/accessible-frontend/' . $entry['file'] : null,
        ];
    }

    private function assertTenantSlug(string $tenantSlug): void
    {
        $tenant = TenantContext::get();
        abort_unless(($tenant['slug'] ?? '') === $tenantSlug, 404);
    }

    private function redirectHostTenantRoute(string $routeName): RedirectResponse
    {
        $tenant = TenantContext::get();
        if (($tenant['id'] ?? 1) > 1 && !empty($tenant['slug'])) {
            return redirect()->route($routeName, ['tenantSlug' => $tenant['slug']]);
        }

        return redirect()->route('govuk-alpha.tenant-chooser');
    }

    private function currentUserId(): ?int
    {
        $user = Auth::user();
        if ($user) {
            return (int) $user->id;
        }

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        $token = request()->bearerToken() ?: request()->cookie('auth_token');
        if (!$token) {
            return null;
        }

        try {
            $payload = app(TokenService::class)->validateToken($token);
            $userId = (int) ($payload['user_id'] ?? $payload['sub'] ?? 0);
            if ($userId > 0) {
                return $this->validatedTenantUserId($userId);
            }
        } catch (\Throwable) {
            // Try Sanctum tokens below.
        }

        try {
            $accessToken = PersonalAccessToken::findToken($token);
            $tokenable = $accessToken?->tokenable;
            if ($tokenable && isset($tokenable->id)) {
                return $this->validatedTenantUserId((int) $tokenable->id);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function validatedTenantUserId(int $userId): ?int
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('is_approved', 1)
                    ->orWhereIn('role', ['admin', 'tenant_admin', 'super_admin', 'god'])
                    ->orWhere('is_tenant_super_admin', 1)
                    ->orWhere('is_super_admin', 1)
                    ->orWhere('is_god', 1);
            })
            ->first(['id']);

        return $user ? (int) $user->id : null;
    }

    private function profileForViewer(int $profileUserId, int $viewerId): ?array
    {
        return $profileUserId === $viewerId
            ? UserService::getOwnProfile($profileUserId)
            : UserService::getPublicProfile($profileUserId, $viewerId);
    }

    private function profileDisplayName(array $profile): string
    {
        $name = trim((string) ($profile['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $fallback = trim(((string) ($profile['first_name'] ?? '')) . ' ' . ((string) ($profile['last_name'] ?? '')));
        return $fallback !== '' ? $fallback : __('govuk_alpha.members.unknown_member');
    }

    private function profileStats(array $profile): array
    {
        $stats = $profile['stats'] ?? [];

        return [
            'hours_given' => (float) ($profile['total_hours_given'] ?? $stats['total_hours_given'] ?? $stats['given_count'] ?? 0),
            'hours_received' => (float) ($profile['total_hours_received'] ?? $stats['total_hours_received'] ?? $stats['received_count'] ?? 0),
            'listings_count' => (int) ($stats['listings_count'] ?? 0),
            'offers_count' => (int) ($stats['offers_count'] ?? 0),
            'requests_count' => (int) ($stats['requests_count'] ?? 0),
            'rating' => $profile['rating'] ?? $stats['average_rating'] ?? null,
            'level' => (int) ($profile['level'] ?? 1),
            'xp' => (int) ($profile['xp'] ?? 0),
        ];
    }

    private function profileListings(int $profileUserId): array
    {
        if (!TenantContext::hasModule('listings')) {
            return [];
        }

        try {
            $result = $this->listingService->getAll([
                'user_id' => $profileUserId,
                'limit' => 6,
            ]);

            return $result['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    private function profileSkills(int $profileUserId, array $profile): array
    {
        $tenantId = TenantContext::getId();
        $rows = DB::table('user_skills')
            ->select('skill_name', 'proficiency', 'is_offering', 'is_requesting')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $profileUserId)
            ->orderByDesc('is_offering')
            ->orderBy('skill_name')
            ->limit(20)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        if (!empty($rows)) {
            return $rows;
        }

        return collect($profile['skills'] ?? [])
            ->filter(fn (mixed $skill): bool => trim((string) $skill) !== '')
            ->map(fn (mixed $skill): array => [
                'skill_name' => trim((string) $skill),
                'proficiency' => null,
                'is_offering' => true,
                'is_requesting' => false,
            ])
            ->values()
            ->all();
    }

    private function profileAvailability(int $profileUserId, array $profile): array
    {
        $tenantId = TenantContext::getId();
        $days = [
            __('govuk_alpha.profile.days.sunday'),
            __('govuk_alpha.profile.days.monday'),
            __('govuk_alpha.profile.days.tuesday'),
            __('govuk_alpha.profile.days.wednesday'),
            __('govuk_alpha.profile.days.thursday'),
            __('govuk_alpha.profile.days.friday'),
            __('govuk_alpha.profile.days.saturday'),
        ];

        $rows = DB::table('member_availability')
            ->select('day_of_week', 'start_time', 'end_time', 'specific_date', 'note')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $profileUserId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->limit(12)
            ->get()
            ->map(function (object $row) use ($days): array {
                $day = isset($days[(int) $row->day_of_week]) ? $days[(int) $row->day_of_week] : '';
                return [
                    'label' => $row->specific_date ?: $day,
                    'time' => substr((string) $row->start_time, 0, 5) . ' - ' . substr((string) $row->end_time, 0, 5),
                    'note' => $row->note,
                ];
            })
            ->all();

        if (!empty($rows)) {
            return $rows;
        }

        $summary = trim((string) ($profile['availability'] ?? ''));
        return $summary === '' ? [] : [['label' => $summary, 'time' => '', 'note' => null]];
    }

    private function profileReviews(int $profileUserId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('reviews as r')
            ->leftJoin('users as reviewer', function ($join) use ($tenantId) {
                $join->on('reviewer.id', '=', 'r.reviewer_id')
                    ->where('reviewer.tenant_id', '=', $tenantId);
            })
            ->select(
                'r.rating',
                'r.comment',
                'r.created_at',
                'r.is_anonymous',
                DB::raw("CASE WHEN r.is_anonymous = 1 THEN NULL WHEN reviewer.profile_type = 'organisation' AND reviewer.organization_name IS NOT NULL AND reviewer.organization_name != '' THEN reviewer.organization_name ELSE CONCAT(COALESCE(reviewer.first_name, ''), ' ', COALESCE(reviewer.last_name, '')) END as reviewer_name")
            )
            ->where('r.tenant_id', $tenantId)
            ->where('r.receiver_id', $profileUserId)
            ->where(function ($query) {
                $query->whereNull('r.status')->orWhere('r.status', 'approved');
            })
            ->orderByDesc('r.created_at')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    private function profileSettingsInputIsValid(array $data): bool
    {
        if (($data['first_name'] ?? '') === '' && ($data['organization_name'] ?? '') === '') {
            return false;
        }

        if (($data['phone'] ?? '') !== '' && !Validator::isPhone((string) $data['phone'])) {
            return false;
        }

        return mb_strlen((string) ($data['bio'] ?? '')) <= 5000
            && mb_strlen((string) ($data['tagline'] ?? '')) <= 255
            && mb_strlen((string) ($data['location'] ?? '')) <= 255;
    }

    /**
     * Resolve a stored asset path to a same-origin URL the browser can load,
     * mirroring the React frontend's resolveAssetUrl(): pass through absolute
     * http(s) URLs, otherwise ensure a leading-slash same-origin path. Returns
     * null for empty values so templates can omit the figure entirely.
     */
    private function resolveAsset(?string $path): ?string
    {
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }
        return '/' . ltrim($path, '/');
    }

    /**
     * Bucket a header logo by aspect ratio so the template can size it sensibly:
     * a wide wordmark needs little height, a square/stacked crest needs more.
     * Measured from the local file via getimagesize; SVGs and anything we can't
     * measure fall back to 'landscape'. Returns 'wide' | 'landscape' | 'square'.
     */
    private function logoShapeClass(?string $url): string
    {
        return \App\Support\LogoShape::classify($url);
    }

    /**
     * Turn a resolved (possibly relative) asset URL into an absolute URL for
     * social/Open Graph tags. Already-absolute URLs are returned unchanged.
     */
    private function absoluteAssetUrl(?string $resolved): ?string
    {
        if ($resolved === null || $resolved === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $resolved) === 1) {
            return $resolved;
        }
        return url($resolved);
    }

    /**
     * Resolve a named cover-image key on each item in place so the templates
     * only ever receive a browser-ready URL (or null). Used for listings
     * (image_url) and events (cover_image).
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function withResolvedImageKey(array $items, string $key): array
    {
        foreach ($items as &$item) {
            if (is_array($item)) {
                $item[$key] = $this->resolveAsset($item[$key] ?? null);
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Load a listing's image gallery from the listing_images table, mirroring
     * ListingsController::show(). Tenant scoping is automatic via the
     * ListingImage HasTenantScope global scope. The cover image is skipped so
     * the hero photo is never duplicated as a thumbnail.
     *
     * @return array<int, array{id: int, url: string, sort_order: int, alt_text: ?string}>
     */
    private function listingGallery(int $listingId, ?string $coverUrl = null): array
    {
        try {
            return ListingImage::where('listing_id', $listingId)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($image): array => [
                    'id' => (int) $image->id,
                    'url' => $this->resolveAsset($image->image_url),
                    'sort_order' => (int) $image->sort_order,
                    'alt_text' => $image->alt_text,
                ])
                ->filter(fn (array $image): bool => $image['url'] !== null && $image['url'] !== $coverUrl)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Shared view data for the create-listing form (GET render + error redirect
     * back). The required-field markers follow the same tenant configuration the
     * React CreateListingPage reads.
     *
     * @return array<string, mixed>
     */
    private function listingFormViewData(string $tenantSlug, Request $request): array
    {
        return [
            'title' => __('govuk_alpha.listings.create.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'listings',
            'categories' => Category::where('type', 'listing')
                ->where('tenant_id', TenantContext::getId())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray(),
            'requireCategory' => $this->listingConfigBool(ListingConfigurationService::CONFIG_REQUIRE_CATEGORY),
            'requireLocation' => $this->listingConfigBool(ListingConfigurationService::CONFIG_REQUIRE_LOCATION),
            'requireHours' => $this->listingConfigBool(ListingConfigurationService::CONFIG_REQUIRE_HOURS_ESTIMATE),
            'enableServiceType' => $this->listingConfigBool(ListingConfigurationService::CONFIG_ENABLE_SERVICE_TYPE),
            'status' => self::asStr($request->query('status')) ?: null,
        ];
    }

    /**
     * Validate the create-listing form and build the ListingService::create()
     * payload. Returns [$data, $errors] where $errors is keyed by field name so
     * the Blade form can anchor a GOV.UK error summary and per-field messages.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validateListingInput(Request $request): array
    {
        $minTitle = max(1, (int) ListingConfigurationService::get(ListingConfigurationService::CONFIG_MIN_TITLE_LENGTH));
        $minDescription = max(1, (int) ListingConfigurationService::get(ListingConfigurationService::CONFIG_MIN_DESCRIPTION_LENGTH));

        $title = trim(self::asStr($request->input('title')));
        $description = trim(self::asStr($request->input('description')));
        $type = $this->allowed($request->input('type'), ['offer', 'request'], '');
        $serviceType = $this->allowed($request->input('service_type'), ['physical_only', 'remote_only', 'hybrid', 'location_dependent'], 'physical_only');
        $categoryRaw = self::asStr($request->input('category_id'));
        $categoryId = $categoryRaw !== '' ? (int) $categoryRaw : null;
        $hoursRaw = self::asStr($request->input('hours_estimate'));
        $hours = $hoursRaw !== '' ? (float) $hoursRaw : null;
        $location = trim(self::asStr($request->input('location')));

        $errors = [];

        if ($title === '') {
            $errors['title'] = __('govuk_alpha.listings.create.errors.title_required');
        } elseif (mb_strlen($title) < $minTitle) {
            $errors['title'] = __('govuk_alpha.listings.create.errors.title_min', ['min' => $minTitle]);
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = __('govuk_alpha.listings.create.errors.title_max');
        }

        if ($description === '') {
            $errors['description'] = __('govuk_alpha.listings.create.errors.description_required');
        } elseif (mb_strlen($description) < $minDescription) {
            $errors['description'] = __('govuk_alpha.listings.create.errors.description_min', ['min' => $minDescription]);
        } elseif (mb_strlen($description) > 10000) {
            $errors['description'] = __('govuk_alpha.listings.create.errors.description_max');
        }

        if ($type === '') {
            $errors['type'] = __('govuk_alpha.listings.create.errors.type_required');
        }

        if ($this->listingConfigBool(ListingConfigurationService::CONFIG_REQUIRE_CATEGORY) && $categoryId === null) {
            $errors['category_id'] = __('govuk_alpha.listings.create.errors.category_required');
        }

        if ($this->listingConfigBool(ListingConfigurationService::CONFIG_REQUIRE_HOURS_ESTIMATE) && $hours === null) {
            $errors['hours_estimate'] = __('govuk_alpha.listings.create.errors.hours_required');
        } elseif ($hours !== null && ($hours < 0.5 || $hours > 2000)) {
            $errors['hours_estimate'] = __('govuk_alpha.listings.create.errors.hours_range');
        }

        if ($this->listingConfigBool(ListingConfigurationService::CONFIG_REQUIRE_LOCATION) && $location === '') {
            $errors['location'] = __('govuk_alpha.listings.create.errors.location_required');
        }

        $data = [
            'title' => $title,
            'description' => $description,
            'type' => $type !== '' ? $type : 'offer',
            'category_id' => $categoryId,
            'hours_estimate' => $hours,
            'service_type' => $serviceType,
            'location' => $location !== '' ? $location : null,
        ];

        return [$data, $errors];
    }

    /**
     * Upload an optional cover photo for a freshly-created listing and set it as
     * the listing cover, mirroring the React /v2/listings/{id}/image endpoint.
     * Best-effort: failures are reported but never bubble up to the member.
     */
    private function attachListingCoverImage(Request $request, int $listingId): void
    {
        $file = $request->file('image');
        if ($file === null || is_array($file) || !$file->isValid()) {
            return;
        }

        try {
            $imageUrl = \App\Core\ImageUploader::upload([
                'name' => $file->getClientOriginalName(),
                'type' => $file->getMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error' => UPLOAD_ERR_OK,
                'size' => $file->getSize(),
            ], 'listings');

            if (is_string($imageUrl) && $imageUrl !== '') {
                ListingService::update($listingId, ['image_url' => $imageUrl]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Attach an optional uploaded cover image to a newly-created event.
     *
     * Mirrors the listing cover-image flow: the upload is best-effort (a failed
     * or absent image never blocks event creation) and EventService::updateImage
     * re-checks ownership and tenant scope before writing.
     */
    private function attachEventCoverImage(Request $request, int $eventId, int $userId): void
    {
        $file = $request->file('image');
        if ($file === null || is_array($file) || !$file->isValid()) {
            return;
        }

        try {
            $imageUrl = \App\Core\ImageUploader::upload([
                'name' => $file->getClientOriginalName(),
                'type' => $file->getMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error' => UPLOAD_ERR_OK,
                'size' => $file->getSize(),
            ], 'events');

            if (is_string($imageUrl) && $imageUrl !== '') {
                EventService::updateImage($eventId, $userId, $imageUrl);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Flatten a Laravel ValidationException into a field => message map for the
     * GOV.UK error summary, falling back to a single generic message.
     *
     * @return array<string, string>
     */
    private function flattenValidationErrors(ValidationException $e): array
    {
        $out = [];
        foreach ($e->errors() as $field => $messages) {
            $message = is_array($messages) ? (string) reset($messages) : (string) $messages;
            if ($message !== '') {
                $out[(string) $field] = $message;
            }
        }

        return $out !== [] ? $out : ['title' => __('govuk_alpha.listings.create.errors.failed')];
    }

    private function listingConfigBool(string $key): bool
    {
        return filter_var(ListingConfigurationService::get($key), FILTER_VALIDATE_BOOLEAN);
    }

    private function listingFilters(Request $request): array
    {
        $type = $this->allowed($request->query('type'), ['offer', 'request'], null);
        $hours = $this->allowed($request->query('hours', 'any'), ['any', 'quick', 'short', 'half_day', 'full_day'], 'any');
        $service = $this->allowed($request->query('service', 'any'), ['any', 'remote', 'in_person'], 'any');
        $posted = $this->allowed($request->query('posted', 'any'), ['any', '1', '7', '30'], 'any');
        $sort = $this->allowed($request->query('sort', 'newest'), ['newest', 'recommended'], 'newest');

        $hoursMap = [
            'quick' => ['max_hours' => 1],
            'short' => ['min_hours' => 1, 'max_hours' => 3],
            'half_day' => ['min_hours' => 3, 'max_hours' => 6],
            'full_day' => ['min_hours' => 6],
        ];

        $filters = [
            'search' => trim(self::asStr($request->query('q'))) ?: null,
            'type' => $type,
            'category_id' => self::asStr($request->query('category_id')) !== '' ? (int) self::asStr($request->query('category_id')) : null,
            'cursor' => self::asStr($request->query('cursor')) ?: null,
            'hours' => $hours,
            'service' => $service,
            'posted' => $posted,
            'sort' => $sort,
            'min_hours' => null,
            'max_hours' => null,
            'service_type' => null,
            'posted_within' => null,
        ];

        if ($hours !== 'any') {
            $filters = array_merge($filters, $hoursMap[$hours] ?? []);
        }

        if ($service === 'remote') {
            $filters['service_type'] = 'remote_only,hybrid';
        } elseif ($service === 'in_person') {
            $filters['service_type'] = 'physical_only';
        }

        if ($posted !== 'any') {
            $filters['posted_within'] = (int) $posted;
        }

        return $filters;
    }

    private function eventFilters(Request $request): array
    {
        return [
            'search' => trim(self::asStr($request->query('q'))) ?: null,
            'when' => $this->allowed($request->query('when', 'upcoming'), ['upcoming', 'past', 'all'], 'upcoming'),
            'category_id' => self::asStr($request->query('category_id')) !== '' ? (int) self::asStr($request->query('category_id')) : null,
            'cursor' => self::asStr($request->query('cursor')) ?: null,
        ];
    }

    private function eventInput(Request $request): array
    {
        $maxAttendees = trim(self::asStr($request->input('max_attendees')));
        $location = trim(self::asStr($request->input('location')));
        $onlineLink = trim(self::asStr($request->input('online_link')));
        $endTime = trim(self::asStr($request->input('end_time')));
        $categoryId = self::asStr($request->input('category_id'));

        return [
            'title' => trim(self::asStr($request->input('title'))),
            'description' => trim(self::asStr($request->input('description'))),
            'start_time' => $request->input('start_time'),
            'end_time' => $endTime !== '' ? $endTime : null,
            'location' => $location !== '' ? $location : null,
            'category_id' => $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null,
            'max_attendees' => $maxAttendees !== '' ? max(1, (int) $maxAttendees) : null,
            'is_online' => $request->boolean('is_online'),
            'online_link' => $onlineLink !== '' ? $onlineLink : null,
        ];
    }

    private function volunteeringFilters(Request $request): array
    {
        return [
            'search' => trim(self::asStr($request->query('q'))) ?: null,
            'category_id' => self::asStr($request->query('category_id')) !== '' ? (int) self::asStr($request->query('category_id')) : null,
            'is_remote' => $request->boolean('is_remote') ? true : null,
            'cursor' => self::asStr($request->query('cursor')) ?: null,
        ];
    }

    private function categoriesForTypes(array $types): array
    {
        return Category::whereIn('type', $types)
            ->where('tenant_id', TenantContext::getId())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    private function volunteeringHourOrganizations(array $organizations, array $applications): array
    {
        $byId = [];

        foreach ($organizations as $organization) {
            if (!empty($organization['id'])) {
                $byId[(int) $organization['id']] = $organization;
            }
        }

        foreach ($applications as $application) {
            $organization = $application['organization'] ?? null;
            if (is_array($organization) && !empty($organization['id'])) {
                $byId[(int) $organization['id']] = $organization;
            }
        }

        uasort($byId, fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return array_values($byId);
    }

    private function memberFilters(Request $request): array
    {
        return [
            'q' => trim(self::asStr($request->query('q'))),
            'sort' => $this->allowed($request->query('sort', 'name'), ['name', 'joined', 'rating', 'hours_given'], 'name'),
            'order' => $this->allowed(strtoupper(self::asStr($request->query('order'))), ['ASC', 'DESC'], 'ASC'),
            'limit' => $this->intQuery($request, 'limit', 20, 1, 100),
            'offset' => $this->intQuery($request, 'offset', 0, 0, 100000),
        ];
    }

    private function memberDirectory(array $filters, int $viewerId): array
    {
        $tenantId = TenantContext::getId();
        $params = [$tenantId, 'active'];
        $where = 'u.tenant_id = ? AND u.status = ? AND u.id != ? AND (u.privacy_search = 1 OR u.privacy_search IS NULL)';
        $params[] = $viewerId;

        if ($filters['q'] !== '') {
            $memberIds = SearchService::searchUsersStatic($filters['q'], $tenantId);
            if ($memberIds !== false && !empty($memberIds)) {
                $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
                $where .= " AND u.id IN ($placeholders)";
                $params = array_merge($params, array_map('intval', $memberIds));
            } elseif ($memberIds !== false) {
                $where .= ' AND 1=0';
            }
        }

        foreach (OnboardingConfigService::getVisibilitySqlConditions($tenantId) as $condition) {
            $where .= " AND ($condition)";
        }

        $orderBy = [
            'name' => 'u.name',
            'joined' => 'u.created_at',
            'rating' => 'rating',
            'hours_given' => 'total_hours_given',
        ][$filters['sort']] ?? 'u.name';

        $total = (int) DB::selectOne("SELECT COUNT(*) as total FROM users u WHERE $where", $params)->total;

        $sql = "SELECT u.id,
                       CASE
                           WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != '' THEN u.organization_name
                           ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
                       END as name,
                       u.avatar_url as avatar,
                       COALESCE(u.tagline, LEFT(u.bio, 120)) as tagline,
                       u.location,
                       u.created_at,
                       u.is_verified,
                       r.avg_rating as rating,
                       COALESCE(tg.total_given, 0) as total_hours_given,
                       COALESCE(tr.total_received, 0) as total_hours_received
                FROM users u
                LEFT JOIN (SELECT receiver_id, AVG(rating) as avg_rating FROM reviews WHERE tenant_id = ? GROUP BY receiver_id) r ON r.receiver_id = u.id
                LEFT JOIN (SELECT sender_id, COALESCE(SUM(amount), 0) as total_given FROM transactions WHERE status = 'completed' AND tenant_id = ? GROUP BY sender_id) tg ON tg.sender_id = u.id
                LEFT JOIN (SELECT receiver_id, COALESCE(SUM(amount), 0) as total_received FROM transactions WHERE status = 'completed' AND tenant_id = ? GROUP BY receiver_id) tr ON tr.receiver_id = u.id
                WHERE $where
                ORDER BY $orderBy {$filters['order']}
                LIMIT ? OFFSET ?";

        $items = DB::select($sql, array_merge([$tenantId, $tenantId, $tenantId], $params, [$filters['limit'], $filters['offset']]));

        return [
            // Attach the viewer's connection state per card (≤ per_page lookups) so
            // the directory shows "Connected"/"Request sent" at a glance.
            'items' => array_map(function (object $row) use ($viewerId): array {
                $member = (array) $row;
                try {
                    $member['connection_state'] = ConnectionService::getStatus($viewerId, (int) $member['id'])['status'] ?? 'none';
                } catch (\Throwable $e) {
                    $member['connection_state'] = 'none';
                }

                return $member;
            }, $items),
            'meta' => [
                'total_items' => $total,
                'offset' => $filters['offset'],
                'per_page' => $filters['limit'],
                'has_more' => ($filters['offset'] + $filters['limit']) < $total,
            ],
        ];
    }

    private function intQuery(Request $request, string $key, int $default, int $min, int $max): int
    {
        $value = $request->query($key, $default);
        return max($min, min($max, is_numeric($value) ? (int) $value : $default));
    }

    private function allowed(mixed $value, array $allowed, mixed $default): mixed
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
