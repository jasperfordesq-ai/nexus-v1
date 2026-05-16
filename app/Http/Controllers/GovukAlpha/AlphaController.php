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
use App\Services\BrokerControlConfigService;
use App\Services\CommentService;
use App\Services\EventService;
use App\Services\ExchangeService;
use App\Services\ExchangeWorkflowService;
use App\Services\FeedService;
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
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'two-factor-required']);
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

        return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => $status]);
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
        $requiresInviteCode = ($policy['registration_mode'] ?? 'open') === 'invite_only';

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
            'title' => __('govuk_alpha.auth.register_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'register',
            'status' => self::asStr($request->query('status')) ?: null,
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
                $listings = $result['items'] ?? [];
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->view('accessible-frontend::dashboard', [
            'title' => __('govuk_alpha.dashboard.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'dashboard',
            'profile' => $profile,
            'displayName' => $this->profileDisplayName($profile),
            'profileStats' => $this->profileStats($profile),
            'feedItems' => $feedItems,
            'listings' => $listings,
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
            'subject' => trim(self::asStr($request->input('subject'))) ?: 'General Inquiry',
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
            $items = $result['items'] ?? [];
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
        ]);
    }

    public function event(string $tenantSlug, int $id): Response
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);

        $event = EventService::getById($id, $this->currentUserId());
        abort_if($event === null, 404);

        return $this->view('accessible-frontend::event-detail', [
            'title' => $event['title'] ?? __('govuk_alpha.events.detail_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'requiresAuth' => $this->currentUserId() === null,
            'status' => self::asStr(request()->query('status')) ?: null,
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
        } catch (ValidationException) {
            return redirect()
                ->route('govuk-alpha.events.create', ['tenantSlug' => $tenantSlug, 'status' => 'event-create-failed'])
                ->withInput();
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('govuk-alpha.events.create', ['tenantSlug' => $tenantSlug, 'status' => 'event-create-failed'])
                ->withInput();
        }

        return redirect()->route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'status' => 'event-created']);
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
        $selectedTab = $this->allowed($request->query('tab', 'opportunities'), ['opportunities', 'applications', 'organisations'], 'opportunities');
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
            'applications' => $userId ? (VolunteerService::getMyApplications($userId, ['limit' => 5])['items'] ?? []) : [],
            'organizations' => $userId ? (VolunteerService::getMyOrganizations($userId, ['limit' => 5])['items'] ?? []) : [],
            'selectedTab' => $selectedTab,
        ]);
    }

    public function volunteerOpportunity(Request $request, string $tenantSlug, int $id): Response
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $opportunity = VolunteerService::getOpportunityById($id, $this->currentUserId());
        abort_if($opportunity === null, 404);

        return $this->view('accessible-frontend::volunteer-opportunity', [
            'title' => $opportunity['title'] ?? __('govuk_alpha.volunteering.detail_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'opportunity' => $opportunity,
            'requiresAuth' => $this->currentUserId() === null,
            'status' => self::asStr($request->query('status')) ?: null,
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

    public function feed(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);
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
            'error' => $error,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function storeFeedPost(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
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
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'post-failed']);
        }

        return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'post-created']);
    }

    public function storeFeedLike(Request $request, string $tenantSlug, string $type, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
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

        try {
            CommentService::create($targetType, $id, $userId, TenantContext::getId(), [
                'content' => $content,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return $this->redirectToFeed($request, $tenantSlug, 'comment-failed', $targetType, $id);
        }

        return $this->redirectToFeed($request, $tenantSlug, 'comment-created', $targetType, $id);
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

        $items = [];
        $meta = ['total_items' => 0, 'has_more' => false, 'cursor' => null];
        $error = null;

        try {
            $result = $this->listingService->getAll($query);
            $items = $result['items'] ?? [];
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
        ]);
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

        return $this->view('accessible-frontend::exchange-request', [
            'title' => __('govuk_alpha.exchanges.request_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'exchanges',
            'listing' => $listing,
            'config' => BrokerControlConfigService::getConfig('exchange_workflow'),
            'status' => self::asStr(request()->query('status')) ?: null,
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

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $exchange = ExchangeWorkflowService::getExchange($id);
        abort_if($exchange === null, 404);
        abort_unless((int) $exchange['requester_id'] === $userId || (int) $exchange['provider_id'] === $userId, 404);

        return $this->view('accessible-frontend::exchange-detail', [
            'title' => $exchange['listing_title'] ?? __('govuk_alpha.exchanges.detail_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'exchanges',
            'exchange' => $exchange,
            'history' => ExchangeWorkflowService::getExchangeHistory($id),
            'status' => self::asStr(request()->query('status')) ?: null,
            'currentUserId' => $userId,
        ]);
    }

    public function storeExchangeAction(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

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
        ]);
    }

    public function conversation(Request $request, string $tenantSlug, int $userId): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

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
        ]);
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

        return $this->view('accessible-frontend::profile-settings', [
            'title' => __('govuk_alpha.profile_settings.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'profile',
            'profile' => $profile,
            'displayName' => $this->profileDisplayName($profile),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
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
                'updated_at' => now(),
            ]);

        return redirect()->route('govuk-alpha.profile.me', ['tenantSlug' => $tenantSlug, 'status' => 'profile-updated']);
    }

    private function view(string $name, array $data = [], int $status = 200): Response
    {
        return response()
            ->view($name, array_merge($this->sharedViewData(), $data), $status)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function sharedViewData(): array
    {
        return [
            'assetEntrypoint' => $this->assetEntrypoint(),
            'tenant' => TenantContext::get(),
            'isAuthenticated' => $this->currentUserId() !== null,
            'alphaNavItems' => $this->alphaNavItems(),
            'alphaFooterLinks' => $this->alphaFooterLinks(),
            'feedbackUrl' => $this->feedbackUrl(),
            'mainSiteUrl' => $this->mainSiteUrl(),
        ];
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
        if ($tenantSlug === '') {
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
            $items['dashboard'] = route('govuk-alpha.dashboard', ['tenantSlug' => $tenantSlug]);
            $items['messages'] = route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug]);
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

        return $items;
    }

    private function alphaFooterLinks(): array
    {
        $tenant = TenantContext::get();
        $tenantSlug = (string) ($tenant['slug'] ?? '');
        if ($tenantSlug === '') {
            return [];
        }

        $items = [
            'contact' => route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug]),
        ];

        if ($this->currentUserId() !== null) {
            $items['logout'] = route('govuk-alpha.logout', ['tenantSlug' => $tenantSlug]);
        }

        return $items;
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

    private function listingFilters(Request $request): array
    {
        $type = $this->allowed($request->query('type'), ['offer', 'request'], null);
        $hours = $this->allowed($request->query('hours', 'any'), ['any', 'quick', 'short', 'half_day', 'full_day'], 'any');
        $service = $this->allowed($request->query('service', 'any'), ['any', 'remote', 'in_person'], 'any');
        $posted = $this->allowed($request->query('posted', 'any'), ['any', '1', '7', '30'], 'any');

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
            'items' => array_map(fn (object $row): array => (array) $row, $items),
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
