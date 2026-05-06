<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha;

use App\Core\TenantContext;
use App\Models\Category;
use App\Services\FeedService;
use App\Services\ListingService;
use App\Services\OnboardingConfigService;
use App\Services\RegistrationService;
use App\Services\SearchService;
use App\Services\TokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class AlphaController extends Controller
{
    public function __construct(
        private readonly FeedService $feedService,
        private readonly ListingService $listingService,
        private readonly RegistrationService $registrationService,
    ) {}

    public function home(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::home', [
            'title' => __('govuk_alpha.home.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'home',
            'isAuthenticated' => $this->currentUserId() !== null,
            'status' => $request->query('status'),
        ]);
    }

    public function login(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::login', [
            'title' => __('govuk_alpha.auth.login_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'login',
            'status' => $request->query('status'),
        ]);
    }

    public function storeLogin(string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        try {
            $response = app(\App\Http\Controllers\Api\AuthController::class)->login();
            $payload = $response->getData(true);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'login-failed']);
        }

        if (($payload['success'] ?? false) === true) {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'signed-in']);
        }

        if (($payload['requires_2fa'] ?? false) === true) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'two-factor-required']);
        }

        return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'login-failed']);
    }

    public function register(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::register', [
            'title' => __('govuk_alpha.auth.register_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'register',
            'status' => $request->query('status'),
        ]);
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
            'password' => $request->input('password'),
            'newsletter_opt_in' => $request->boolean('newsletter_opt_in'),
        ], TenantContext::getId());

        if (isset($result['error'])) {
            return redirect()->route('govuk-alpha.register', ['tenantSlug' => $tenantSlug, 'status' => 'register-failed']);
        }

        return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'register-created']);
    }

    public function feed(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        $type = $this->allowed($request->query('type', 'all'), ['all', 'posts', 'listings', 'events', 'goals', 'polls'], 'all');
        $perPage = $this->intQuery($request, 'per_page', 10, 1, 50);

        $items = [];
        $meta = ['has_more' => false, 'cursor' => null, 'per_page' => $perPage];
        $error = null;

        if ($userId !== null) {
            try {
                $result = $this->feedService->getFeed($userId, [
                    'limit' => $perPage,
                    'type' => $type,
                    'mode' => 'chronological',
                    'cursor' => $request->query('cursor'),
                ]);
                $items = $result['items'] ?? [];
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
            'meta' => $meta,
            'selectedType' => $type,
            'requiresAuth' => $userId === null,
            'error' => $error,
            'status' => $request->query('status'),
        ]);
    }

    public function storeFeedPost(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]);
        }

        $content = trim((string) $request->input('content', ''));
        if ($content === '') {
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'post-empty']);
        }

        try {
            $this->feedService->createPost($userId, [
                'content' => $content,
                'visibility' => 'public',
            ]);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'post-failed']);
        }

        return redirect()->route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug, 'status' => 'post-created']);
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

        foreach (['type', 'category_id', 'search', 'cursor'] as $key) {
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

        $listing = $this->listingService->getById($id, false, $this->currentUserId());
        abort_if($listing === null, 404);

        return $this->view('accessible-frontend::listing-detail', [
            'title' => $listing['title'] ?? __('govuk_alpha.listings.detail_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'listings',
            'listing' => $listing,
        ]);
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
        ];
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

    private function listingFilters(Request $request): array
    {
        $type = $this->allowed($request->query('type'), ['offer', 'request'], null);

        return [
            'search' => trim((string) $request->query('q', '')) ?: null,
            'type' => $type,
            'category_id' => $request->query('category_id') ? (int) $request->query('category_id') : null,
            'cursor' => $request->query('cursor'),
        ];
    }

    private function memberFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'sort' => $this->allowed($request->query('sort', 'name'), ['name', 'joined', 'rating', 'hours_given'], 'name'),
            'order' => $this->allowed(strtoupper((string) $request->query('order', 'ASC')), ['ASC', 'DESC'], 'ASC'),
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
