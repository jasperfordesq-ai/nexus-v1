<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Services\SafeguardingService;
use App\Services\VolunteerService;
use App\Services\VolunteerEmergencyAlertService;
use App\Services\VolunteerWellbeingService;
use App\Services\VolunteerMatchingService;
use App\Services\VolunteerDonationService;
use App\Services\VolunteerExpenseService;
use App\Services\VolOrgWalletService;
use App\Services\ShiftGroupReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Volunteering — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * This trait builds the org-management suite (my-organisations, dashboard,
 * settings, wallet, create-opportunity) plus three member features (emergency
 * alerts, credential uploads, wellbeing) that the existing AlphaController
 * volunteering methods do not cover. It calls the SAME services the React API
 * controllers call — no money/auth/notification logic is reimplemented.
 */
trait VolunteeringParity
{
    /**
     * Shared auth + feature + org-management gate for the org suite.
     *
     * Returns one of:
     *   - RedirectResponse  (not logged in → login)
     *   - Response          (feature off → 403, or org missing → 404, or
     *                        not an owner/admin of the org → 403; each is the
     *                        rendered error page produced by abort())
     *   - array{0:int,1:object}  ([userId, orgRow]) when access is granted
     *
     * Mirrors VolunteerController::ensureOrgAccess (owner row OR active
     * owner/admin org_members membership). Cross-tenant org rows return 404,
     * non-owners return 403 — exactly the React API behaviour.
     *
     * @return array{0:int,1:object}|RedirectResponse
     */
    private function volunteeringOrgGate(string $tenantSlug, int $orgId): array|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $org = DB::selectOne(
            'SELECT id, name, user_id, status, description, contact_email, website, balance
             FROM vol_organizations WHERE id = ? AND tenant_id = ?',
            [$orgId, $tenantId]
        );
        // Cross-tenant or non-existent org → 404 (never leak existence).
        abort_if($org === null, 404);

        $canManage = (int) $org->user_id === $userId;
        if (!$canManage) {
            $membership = DB::selectOne(
                "SELECT role FROM org_members WHERE tenant_id = ? AND organization_id = ? AND org_type = 'volunteer' AND user_id = ? AND status = 'active'",
                [$tenantId, $orgId, $userId]
            );
            $canManage = $membership !== null && in_array($membership->role, ['owner', 'admin'], true);
        }
        // Authenticated but not an owner/admin → 403.
        abort_unless($canManage, 403);

        return [$userId, $org];
    }

    // =========================================================================
    // MY ORGANISATIONS — dedicated full page (React MyOrganisationsPage)
    // =========================================================================

    public function volunteeringMyOrganisations(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $roleFilter = $this->allowed($request->query('role'), ['owner', 'admin', 'member'], null);
        $cursor = self::asStr($request->query('cursor'));

        $organizations = [];
        $meta = ['has_more' => false, 'cursor' => null];
        try {
            $filters = ['limit' => 20];
            if ($cursor !== '') {
                $filters['cursor'] = $cursor;
            }
            $result = VolunteerService::getMyOrganizations($userId, $filters);
            $organizations = $result['items'] ?? [];
            $meta = ['has_more' => (bool) ($result['has_more'] ?? false), 'cursor' => $result['cursor'] ?? null];
        } catch (\Throwable $e) {
            report($e);
        }

        // Optional client-driven role filter (the service returns member_role).
        if ($roleFilter !== null) {
            $organizations = array_values(array_filter(
                $organizations,
                static fn (array $o): bool => (string) ($o['member_role'] ?? 'member') === $roleFilter
            ));
        }

        return $this->view('accessible-frontend::volunteering-my-organisations', [
            'title' => __('govuk_alpha_volunteering.my_orgs.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'organizations' => $organizations,
            'meta' => $meta,
            'roleFilter' => $roleFilter,
        ]);
    }

    // =========================================================================
    // ORG DASHBOARD — stats + quick actions (React OrgOverviewTab)
    // =========================================================================

    public function volunteeringOrgDashboard(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $gate = $this->volunteeringOrgGate($tenantSlug, $id);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }
        [, $org] = $gate;

        $tenantId = TenantContext::getId();
        $stats = [
            'total_volunteers' => 0,
            'pending_applications' => 0,
            'pending_hours' => 0,
            'total_approved_hours' => 0.0,
            'active_opportunities' => 0,
        ];

        // Same aggregate SELECT as VolunteerController::orgStats.
        try {
            $row = DB::selectOne("
                SELECT
                    (SELECT COUNT(DISTINCT va.user_id) FROM vol_applications va
                     JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                     WHERE vo.organization_id = ? AND va.tenant_id = ? AND va.status = 'approved') as total_volunteers,
                    (SELECT COUNT(*) FROM vol_applications va2
                     JOIN vol_opportunities vo2 ON va2.opportunity_id = vo2.id
                     WHERE vo2.organization_id = ? AND va2.tenant_id = ? AND va2.status = 'pending') as pending_applications,
                    (SELECT COUNT(*) FROM vol_logs vl
                     WHERE vl.organization_id = ? AND vl.tenant_id = ? AND vl.status = 'pending') as pending_hours,
                    (SELECT COALESCE(SUM(vl2.hours), 0) FROM vol_logs vl2
                     WHERE vl2.organization_id = ? AND vl2.tenant_id = ? AND vl2.status = 'approved') as total_approved_hours,
                    (SELECT COUNT(*) FROM vol_opportunities vo3
                     WHERE vo3.organization_id = ? AND vo3.tenant_id = ? AND vo3.is_active = 1 AND vo3.status IN ('open', 'active')) as active_opportunities
            ", [$id, $tenantId, $id, $tenantId, $id, $tenantId, $id, $tenantId, $id, $tenantId]);

            if ($row !== null) {
                $stats = [
                    'total_volunteers' => (int) $row->total_volunteers,
                    'pending_applications' => (int) $row->pending_applications,
                    'pending_hours' => (int) $row->pending_hours,
                    'total_approved_hours' => (float) $row->total_approved_hours,
                    'active_opportunities' => (int) $row->active_opportunities,
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::volunteering-org-dashboard', [
            'title' => __('govuk_alpha_volunteering.org_dashboard.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'orgId' => $id,
            'orgName' => (string) ($org->name ?? ''),
            'orgStatus' => (string) ($org->status ?? 'pending'),
            'stats' => $stats,
            'walletBalance' => (float) ($org->balance ?? 0),
        ]);
    }

    /**
     * Read-only "Volunteers roster" for an organisation the member manages —
     * approved volunteers with their total approved hours, role count and most
     * recent application date. Mirrors VolunteerController::orgVolunteers exactly
     * (same tenant + org scoping, approved-only, correlated hours subquery to
     * avoid the logs×applications fan-out). The org gate restricts this to the
     * org owner/admin, so one community's roster can never leak to another.
     */
    public function volunteeringOrgVolunteers(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $gate = $this->volunteeringOrgGate($tenantSlug, $id);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }
        [, $org] = $gate;

        $tenantId = TenantContext::getId();
        $limit = 20;
        $cursor = (int) self::asStr($request->query('cursor'));

        $params = [$id, $tenantId, $tenantId, $id, $tenantId];
        $cursorClause = '';
        if ($cursor > 0) {
            $cursorClause = ' AND u.id < ?';
            $params[] = $cursor;
        }
        $params[] = $limit + 1;

        $volunteers = [];
        $meta = ['has_more' => false, 'cursor' => null];
        try {
            $rows = DB::select("
                SELECT u.id, u.name, u.avatar_url, u.email,
                       MAX(va.created_at) as applied_at,
                       COALESCE((SELECT SUM(vl.hours) FROM vol_logs vl
                                 WHERE vl.user_id = u.id AND vl.organization_id = ?
                                   AND vl.tenant_id = ? AND vl.status = 'approved'), 0) as total_hours,
                       COUNT(DISTINCT va.id) as applications_count
                FROM users u
                INNER JOIN vol_applications va ON va.user_id = u.id AND va.tenant_id = u.tenant_id AND va.status = 'approved' AND va.tenant_id = ?
                INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id AND vo.tenant_id = va.tenant_id AND vo.organization_id = ?
                WHERE u.tenant_id = ?
                {$cursorClause}
                GROUP BY u.id, u.name, u.avatar_url, u.email
                ORDER BY u.id DESC
                LIMIT ?
            ", $params);

            $hasMore = count($rows) > $limit;
            if ($hasMore) {
                array_pop($rows);
            }
            $volunteers = array_map(static fn ($r) => [
                'id' => (int) $r->id,
                'name' => (string) ($r->name ?? ''),
                'avatar_url' => $r->avatar_url,
                'email' => (string) ($r->email ?? ''),
                'total_hours' => (float) $r->total_hours,
                'applications_count' => (int) $r->applications_count,
                'applied_at' => $r->applied_at,
            ], $rows);
            $last = $volunteers !== [] ? end($volunteers) : null;
            $meta = [
                'has_more' => $hasMore,
                'cursor' => $hasMore && $last ? (string) $last['id'] : null,
            ];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::volunteering-org-volunteers', [
            'title' => __('govuk_alpha_volunteering.org_volunteers.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'orgId' => $id,
            'orgName' => (string) ($org->name ?? ''),
            'volunteers' => $volunteers,
            'meta' => $meta,
        ]);
    }

    // =========================================================================
    // ORG SETTINGS — edit name/description/contact/website (React OrgSettingsTab)
    // =========================================================================

    public function volunteeringOrgSettings(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $gate = $this->volunteeringOrgGate($tenantSlug, $id);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }
        [, $org] = $gate;

        return $this->view('accessible-frontend::volunteering-org-settings', [
            'title' => __('govuk_alpha_volunteering.org_settings.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'orgId' => $id,
            'org' => [
                'name' => (string) ($org->name ?? ''),
                'description' => (string) ($org->description ?? ''),
                'contact_email' => (string) ($org->contact_email ?? ''),
                'website' => (string) ($org->website ?? ''),
            ],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function volunteeringUpdateOrgSettings(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $gate = $this->volunteeringOrgGate($tenantSlug, $id);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $tenantId = TenantContext::getId();
        $name = trim(self::asStr($request->input('name')));
        $description = trim(self::asStr($request->input('description')));
        $contactEmail = trim(self::asStr($request->input('contact_email')));
        $website = trim(self::asStr($request->input('website')));

        // Mirror React: name is required; contact_email must be a valid email if set.
        if ($name === '') {
            return redirect()->route('govuk-alpha.volunteering.org.settings', [
                'tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'name-required',
            ]);
        }
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('govuk-alpha.volunteering.org.settings', [
                'tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'email-invalid',
            ]);
        }

        $ok = false;
        try {
            // Same UPDATE columns VolunteerController::updateOrganisation writes.
            DB::update(
                'UPDATE vol_organizations SET name = ?, description = ?, contact_email = ?, website = ? WHERE id = ? AND tenant_id = ?',
                [$name, $description, $contactEmail, $website, $id, $tenantId]
            );
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.org.settings', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'settings-saved' : 'settings-failed',
        ]);
    }

    // =========================================================================
    // ORG WALLET — balance, transactions, deposit, auto-credit (React OrgWalletTab)
    // =========================================================================

    public function volunteeringOrgWallet(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $gate = $this->volunteeringOrgGate($tenantSlug, $id);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }
        [, $org] = $gate;

        $summary = ['balance' => 0.0, 'total_deposited' => 0.0, 'total_paid_out' => 0.0, 'transaction_count' => 0, 'pending_hours_value' => 0.0];
        $transactions = [];
        try {
            $summary = VolOrgWalletService::getWalletSummary($id);
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $transactions = VolOrgWalletService::getTransactions($id, ['limit' => 20])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::volunteering-org-wallet', [
            'title' => __('govuk_alpha_volunteering.org_wallet.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'orgId' => $id,
            'orgName' => (string) ($org->name ?? ''),
            'summary' => $summary,
            'transactions' => $transactions,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function volunteeringOrgWalletDeposit(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $gate = $this->volunteeringOrgGate($tenantSlug, $id);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }
        [$userId] = $gate;

        $amount = (float) $request->input('amount', 0);
        $note = trim(self::asStr($request->input('note')));

        if ($amount <= 0) {
            return redirect()->route('govuk-alpha.volunteering.org.wallet', [
                'tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'deposit-amount-invalid',
            ]);
        }

        // Call the SAME balance-mutating service the React API uses — it runs the
        // transaction + lockForUpdate and validates the user's own balance.
        $status = 'deposit-failed';
        try {
            $result = VolOrgWalletService::depositFromUser($userId, $id, $amount, $note !== '' ? $note : null);
            $status = ($result['success'] ?? false) ? 'deposit-made' : 'deposit-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.org.wallet', [
            'tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status,
        ]);
    }

    public function volunteeringOrgAutoPay(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $gate = $this->volunteeringOrgGate($tenantSlug, $id);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        return redirect()->route('govuk-alpha.volunteering.org.wallet', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => 'auto-credit-always-on',
        ]);
    }

    // =========================================================================
    // CREATE OPPORTUNITY — org side (React CreateOpportunityPage)
    // =========================================================================

    public function volunteeringCreateOpportunity(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Only orgs the user can manage AND that are approved may receive new
        // opportunities (createOpportunity enforces this server-side too).
        $manageableOrgs = [];
        try {
            $orgs = VolunteerService::getMyOrganizations($userId, ['limit' => 50])['items'] ?? [];
            foreach ($orgs as $o) {
                $role = (string) ($o['member_role'] ?? 'member');
                $statusValue = (string) ($o['status'] ?? 'pending');
                if (in_array($role, ['owner', 'admin'], true) && in_array($statusValue, ['approved', 'active'], true)) {
                    $manageableOrgs[] = ['id' => (int) ($o['id'] ?? 0), 'name' => (string) ($o['name'] ?? '')];
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::volunteering-create-opportunity', [
            'title' => __('govuk_alpha_volunteering.create_opp.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'organizations' => $manageableOrgs,
            'categories' => $this->categoriesForTypes(['volunteering', 'volunteer']),
            'status' => self::asStr($request->query('status')) ?: null,
            'old' => [],
        ]);
    }

    public function volunteeringStoreOpportunity(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $organizationId = (int) $request->input('organization_id', 0);
        $title = trim(self::asStr($request->input('title')));
        $description = trim(self::asStr($request->input('description')));

        // Minimal client-side validation; the service re-validates ownership,
        // org-approval and category. Title/description/org are required.
        if ($organizationId <= 0 || $title === '' || $description === '') {
            return redirect()->route('govuk-alpha.volunteering.opportunities.create', [
                'tenantSlug' => $tenantSlug, 'status' => 'opp-validation',
            ]);
        }

        $categoryId = (int) $request->input('category_id', 0);
        $data = [
            'organization_id' => $organizationId,
            'title' => $title,
            'description' => $description,
            'location' => trim(self::asStr($request->input('location'))),
            'is_remote' => $request->input('is_remote') === '1' || $request->boolean('is_remote'),
            'skills_needed' => trim(self::asStr($request->input('skills_needed'))),
            'start_date' => self::asStr($request->input('start_date')) ?: null,
            'end_date' => self::asStr($request->input('end_date')) ?: null,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            // The service only accepts 'listed'/'none' (see sanitizeFederatedVisibility
            // + UpdateOpportunityRequest in:none,listed). 'network'/'local' were
            // silently sanitized away, so the federation-share checkbox never worked.
            'federated_visibility' => $request->input('federated_visibility') === '1' || $request->boolean('federated_visibility')
                ? 'listed' : 'none',
        ];

        $opportunity = null;
        try {
            // SAME service the React createOpportunity endpoint calls — it
            // dispatches VolunteerOpportunityCreated (notifications) internally.
            $opportunity = VolunteerService::createOpportunity($userId, $data);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($opportunity === null) {
            // Surface the first service error code as a status flag.
            $errors = [];
            try {
                $errors = VolunteerService::getErrors();
            } catch (\Throwable $e) {
                // ignore
            }
            $code = $errors[0]['code'] ?? 'opp-create-failed';
            $status = match ($code) {
                'FORBIDDEN' => 'opp-forbidden',
                'NOT_FOUND' => 'opp-org-not-found',
                'VALIDATION_ERROR' => 'opp-validation',
                default => 'opp-create-failed',
            };
            return redirect()->route('govuk-alpha.volunteering.opportunities.create', [
                'tenantSlug' => $tenantSlug, 'status' => $status,
            ]);
        }

        return redirect()->route('govuk-alpha.volunteering.show', [
            'tenantSlug' => $tenantSlug,
            'id' => (int) $opportunity->id,
            'status' => 'opp-created',
        ]);
    }

    // =========================================================================
    // EMERGENCY ALERTS — urgent shift-fill list + respond (React EmergencyAlertsTab)
    // =========================================================================

    public function volunteeringEmergencyAlerts(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $alerts = [];
        try {
            $alerts = VolunteerEmergencyAlertService::getUserAlerts($userId, ['limit' => 20])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::volunteering-emergency-alerts', [
            'title' => __('govuk_alpha_volunteering.emergency.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'alerts' => $alerts,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function volunteeringRespondEmergencyAlert(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // React maps the buttons to accepted/declined; service validates the
        // recipient row (cross-user → false) and runs in a locked transaction.
        $response = self::asStr($request->input('response')) === 'declined' ? 'declined' : 'accepted';

        $ok = false;
        try {
            $ok = VolunteerEmergencyAlertService::respond($id, $userId, $response);
        } catch (\Throwable $e) {
            report($e);
        }

        $status = $ok
            ? ($response === 'accepted' ? 'alert-accepted' : 'alert-declined')
            : 'alert-respond-failed';

        return redirect()->route('govuk-alpha.volunteering.emergency-alerts', [
            'tenantSlug' => $tenantSlug, 'status' => $status,
        ]);
    }

    // =========================================================================
    // CREDENTIAL VERIFICATION — upload + status + delete (React CredentialVerificationTab)
    // =========================================================================

    public function volunteeringCredentials(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $credentials = [];
        try {
            // Same SELECT shape as VolunteerCertificateController::myCredentials.
            $rows = DB::select(
                'SELECT id, credential_type, file_name, status, expires_at, created_at
                 FROM vol_credentials WHERE user_id = ? AND tenant_id = ? ORDER BY created_at DESC',
                [$userId, $tenantId]
            );
            $credentials = array_map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'credential_type' => (string) ($r->credential_type ?? ''),
                'file_name' => $r->file_name,
                'status' => (string) ($r->status ?? 'pending'),
                'expires_at' => $r->expires_at,
                'created_at' => $r->created_at,
            ], $rows);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::volunteering-credentials', [
            'title' => __('govuk_alpha_volunteering.credentials.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'credentials' => $credentials,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function volunteeringUploadCredential(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $type = trim(self::asStr($request->input('credential_type')));
        $expiresAt = self::asStr($request->input('expiry_date')) ?: null;

        if ($type === '') {
            return redirect()->route('govuk-alpha.volunteering.credentials', [
                'tenantSlug' => $tenantSlug, 'status' => 'credential-type-required',
            ]);
        }

        $file = $request->file('document');
        if ($file === null || !$file->isValid()) {
            return redirect()->route('govuk-alpha.volunteering.credentials', [
                'tenantSlug' => $tenantSlug, 'status' => 'credential-file-required',
            ]);
        }

        // Same allow-list + 10MB cap as VolunteerCertificateController::uploadCredential.
        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $mime = (string) $file->getMimeType();
        if (!isset($extensions[$mime])) {
            return redirect()->route('govuk-alpha.volunteering.credentials', [
                'tenantSlug' => $tenantSlug, 'status' => 'credential-file-type',
            ]);
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return redirect()->route('govuk-alpha.volunteering.credentials', [
                'tenantSlug' => $tenantSlug, 'status' => 'credential-file-size',
            ]);
        }

        $ok = false;
        try {
            $storagePath = 'volunteer-credentials/' . $tenantId . '/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            Storage::disk('local')->put($storagePath, file_get_contents($file->getRealPath()));

            DB::insert(
                "INSERT INTO vol_credentials (tenant_id, user_id, credential_type, file_url, file_name, status, expires_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())",
                [$tenantId, $userId, $type, 'private:' . $storagePath, $file->getClientOriginalName(), $expiresAt]
            );
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.credentials', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'credential-uploaded' : 'credential-upload-failed',
        ]);
    }

    public function volunteeringDeleteCredential(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $ok = false;
        try {
            // Ownership-scoped delete (user_id + tenant_id) — never another
            // user's credential. Mirrors deleteCredential including file cleanup.
            $credential = DB::selectOne(
                'SELECT file_url FROM vol_credentials WHERE id = ? AND user_id = ? AND tenant_id = ?',
                [$id, $userId, $tenantId]
            );
            $affected = DB::delete(
                'DELETE FROM vol_credentials WHERE id = ? AND user_id = ? AND tenant_id = ?',
                [$id, $userId, $tenantId]
            );
            $ok = $affected > 0;

            if ($ok && $credential !== null && str_starts_with((string) $credential->file_url, 'private:')) {
                $path = substr((string) $credential->file_url, strlen('private:'));
                $expectedPrefix = 'volunteer-credentials/' . $tenantId . '/';
                if (str_starts_with($path, $expectedPrefix) && !str_contains($path, '..')) {
                    Storage::disk('local')->delete($path);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.credentials', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'credential-deleted' : 'credential-delete-failed',
        ]);
    }

    // =========================================================================
    // WELLBEING — burnout dashboard + mood check-in (React WellbeingTab)
    // =========================================================================

    public function volunteeringWellbeing(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();

        // Burnout assessment from the SAME service the React dashboard uses.
        $score = 100;
        $burnoutRisk = 'low';
        $warnings = [];
        try {
            $assessment = VolunteerWellbeingService::detectBurnoutRisk($userId);
            $score = max(0, min(100, 100 - (int) ($assessment['risk_score'] ?? 0)));
            $burnoutRisk = match ($assessment['risk_level'] ?? 'low') {
                'critical', 'high' => 'high',
                'moderate' => 'moderate',
                default => 'low',
            };
            $indicators = $assessment['indicators'] ?? [];
            if (($indicators['shift_frequency']['trend'] ?? '') === 'declining') {
                $warnings[] = 'frequency';
            }
            if (($indicators['cancellation_rate']['rate_percent'] ?? 0) > 30) {
                $warnings[] = 'cancellation';
            }
            if (($indicators['hours_trend']['trend'] ?? '') === 'declining_significantly') {
                $warnings[] = 'hours';
            }
            if (($indicators['engagement_gap']['days_since_last_activity'] ?? 0) > 30) {
                $warnings[] = 'engagement';
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Hours-this-week / streak, mirroring the API dashboard's read queries.
        $hoursThisWeek = 0.0;
        $hoursThisMonth = 0.0;
        $streak = 0;
        $recentCheckins = [];
        try {
            $week = DB::selectOne(
                "SELECT COALESCE(SUM(hours), 0) as total FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND status = 'approved' AND date_logged >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$userId, $tenantId]
            );
            $hoursThisWeek = round((float) ($week->total ?? 0), 1);

            $month = DB::selectOne(
                "SELECT COALESCE(SUM(hours), 0) as total FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND status = 'approved' AND date_logged >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$userId, $tenantId]
            );
            $hoursThisMonth = round((float) ($month->total ?? 0), 1);

            $dates = DB::select(
                "SELECT DISTINCT DATE(date_logged) as d FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND status = 'approved' ORDER BY d DESC LIMIT 90",
                [$userId, $tenantId]
            );
            $today = new \DateTime();
            foreach ($dates as $i => $dateRow) {
                $expected = (clone $today)->modify("-{$i} days")->format('Y-m-d');
                if ($dateRow->d === $expected) {
                    $streak++;
                } else {
                    break;
                }
            }

            $checkinRows = DB::select(
                'SELECT id, mood, note, created_at FROM vol_mood_checkins WHERE user_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT 10',
                [$userId, $tenantId]
            );
            $recentCheckins = array_map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'mood' => (int) $r->mood,
                'note' => $r->note,
                'created_at' => $r->created_at,
            ], $checkinRows);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::volunteering-wellbeing', [
            'title' => __('govuk_alpha_volunteering.wellbeing.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'score' => $score,
            'burnoutRisk' => $burnoutRisk,
            'warnings' => $warnings,
            'hoursThisWeek' => $hoursThisWeek,
            'hoursThisMonth' => $hoursThisMonth,
            'streakDays' => $streak,
            'recentCheckins' => $recentCheckins,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function volunteeringWellbeingCheckin(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $mood = (int) $request->input('mood', 0);
        if ($mood < 1 || $mood > 5) {
            return redirect()->route('govuk-alpha.volunteering.wellbeing', [
                'tenantSlug' => $tenantSlug, 'status' => 'mood-invalid',
            ]);
        }

        $note = trim(self::asStr($request->input('note')));
        if ($note !== '') {
            $note = mb_substr($note, 0, 500);
        }

        $ok = false;
        try {
            DB::insert(
                'INSERT INTO vol_mood_checkins (tenant_id, user_id, mood, note, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$tenantId, $userId, $mood, $note !== '' ? $note : null]
            );
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.wellbeing', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'checkin-saved' : 'checkin-failed',
        ]);
    }

    // =========================================================================
    // RECOMMENDED SHIFTS — dedicated "For you" page (React RecommendedShiftsTab)
    // =========================================================================

    public function volunteeringRecommendedShifts(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $shifts = [];
        try {
            // Same matching service + options the React recommendedShifts uses.
            $shifts = app(VolunteerMatchingService::class)->getRecommendedShifts($userId, [
                'limit' => 15,
                'min_match_score' => 20,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::volunteering-recommended', [
            'title' => __('govuk_alpha_volunteering.recommended.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'shifts' => $shifts,
        ]);
    }

    // =========================================================================
    // DONATIONS / GIVING — community fundraising page (React DonationsTab)
    //
    // Money donations (NOT time credits) towards the community and active
    // giving-day campaigns, plus the donor's own giving history. Calls the SAME
    // VolunteerDonationService the React API (VolunteerCommunityController) uses
    // — no money logic is reimplemented. Stripe card checkout is a JS-only
    // ceremony and is excluded; this HTML-first form records OFFLINE donations
    // (bank transfer / PayPal) as 'pending', exactly as the service does for the
    // React modal. An administrator later marks them completed.
    // =========================================================================

    /** Offline payment methods offered on the no-JS donate form. */
    private const VOL_DONATION_PAYMENT_METHODS = ['bank_transfer', 'paypal'];

    public function volunteeringDonations(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Active giving days (with completed-donation totals/counts) and the
        // caller's own donation history — the same two reads the React tab does.
        $givingDays = [];
        $donations = [];
        try {
            $givingDays = VolunteerDonationService::getGivingDays();
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $result = app(VolunteerDonationService::class)->getDonations([
                'user_id' => $userId,
                'limit' => 20,
            ]);
            $donations = $result['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        // Header stats derived from the giving-day campaign totals, matching the
        // React tab's client-side reduction.
        $totalRaised = 0.0;
        $totalDonors = 0;
        $activeCampaigns = 0;
        foreach ($givingDays as $day) {
            $totalRaised += (float) ($day['raised_amount'] ?? 0);
            $totalDonors += (int) ($day['donor_count'] ?? 0);
            if (($day['status'] ?? '') === 'active' || !empty($day['is_active'])) {
                $activeCampaigns++;
            }
        }

        return $this->view('accessible-frontend::volunteering-donations', [
            'title' => __('govuk_alpha_volunteering.donations.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'givingDays' => $givingDays,
            'donations' => $donations,
            'paymentMethods' => self::VOL_DONATION_PAYMENT_METHODS,
            'stats' => [
                'total_raised' => $totalRaised,
                'total_donors' => $totalDonors,
                'active_campaigns' => $activeCampaigns,
            ],
            'status' => self::asStr($request->query('status')) ?: null,
            'donateError' => self::asStr($request->query('donate_error')) ?: null,
        ]);
    }

    public function volunteeringStoreDonation(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $redirect = fn (string $status, ?string $error = null): RedirectResponse => redirect()
            ->route('govuk-alpha.volunteering.donations', array_filter([
                'tenantSlug' => $tenantSlug,
                'status' => $status,
                'donate_error' => $error,
            ], static fn ($v) => $v !== null))
            ->withFragment('donate');

        // Whole-currency-unit amount, positive and sanely capped (the service
        // re-validates, but we give a friendly inline error here too).
        $amount = (float) $request->input('amount', 0);
        if ($amount <= 0) {
            return $redirect('donate-failed', 'amount');
        }
        if ($amount > 1000000) {
            return $redirect('donate-failed', 'amount-max');
        }

        // Whitelist the offline payment method — never trust the posted value.
        $paymentMethod = $this->allowed(
            $request->input('payment_method'),
            self::VOL_DONATION_PAYMENT_METHODS,
            'bank_transfer'
        );

        // Optional giving-day target. 0/empty means a general community donation.
        $givingDayId = (int) $request->input('giving_day_id', 0);

        $message = trim(self::asStr($request->input('message')));
        if ($message !== '') {
            $message = mb_substr($message, 0, 500);
        }
        $isAnonymous = $request->boolean('is_anonymous');

        $data = [
            'amount' => $amount,
            // Currency is intentionally omitted: the service records the
            // tenant's configured currency (TenantContext::getCurrency()).
            // Hardcoding 'EUR' here mislabelled donations for non-euro
            // communities and would now be rejected by the service's
            // tenant-currency guard.
            'payment_method' => $paymentMethod,
            'message' => $message,
            'is_anonymous' => $isAnonymous,
        ];
        if ($givingDayId > 0) {
            $data['giving_day_id'] = $givingDayId;
        }

        try {
            // Same service + 'pending' status the React modal produces. The
            // service does all money/validation/tenant-scoping itself.
            VolunteerDonationService::createDonation($userId, $data);
        } catch (\InvalidArgumentException $e) {
            // Validation failure (bad amount/currency/giving-day) — surface a
            // generic inline error; the detail came from a translated message.
            report($e);
            return $redirect('donate-failed', 'validation');
        } catch (\Throwable $e) {
            report($e);
            return $redirect('donate-failed');
        }

        return $redirect('donate-recorded');
    }

    // =========================================================================
    // GROUP SIGN-UPS — team shift reservations (React GroupSignUpTab)
    //
    // Lists every group reservation the caller leads or is a member of, calling
    // the SAME ShiftGroupReservationService the React API (VolunteerCommunity-
    // Controller::myGroupReservations) uses. Group leaders can add a member by
    // user ID, remove a member, or cancel the whole reservation — all via plain
    // no-JS POST forms. The React tab's debounced user-search autocomplete is a
    // JS-only convenience; the HTML-first form takes the numeric user ID
    // directly (the service validates leadership + capacity + membership). No
    // reservation/slot/membership logic is reimplemented here.
    // =========================================================================

    public function volunteeringGroupSignups(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $reservations = [];
        try {
            // Same read the React tab issues against /v2/volunteering/group-reservations.
            $reservations = ShiftGroupReservationService::getUserReservations($userId, $tenantId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::volunteering-group-signups', [
            'title' => __('govuk_alpha_volunteering.group_signups.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'reservations' => $reservations,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function volunteeringAddGroupMember(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $memberUserId = (int) $request->input('user_id', 0);
        if ($memberUserId <= 0) {
            return redirect()->route('govuk-alpha.volunteering.group-signups', [
                'tenantSlug' => $tenantSlug, 'status' => 'member-id-required',
            ]);
        }

        $ok = false;
        try {
            // The service enforces leadership, capacity and the active-reservation
            // check, running the slot increment inside a locked transaction.
            $ok = ShiftGroupReservationService::addMember($id, $memberUserId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.group-signups', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'member-added' : 'member-add-failed',
        ]);
    }

    public function volunteeringRemoveGroupMember(Request $request, string $tenantSlug, int $id, int $userId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $leaderId = $this->currentUserId();
        if ($leaderId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $ok = false;
        try {
            // Leadership-gated removal; decrements filled_slots in a transaction.
            $ok = ShiftGroupReservationService::removeMember($id, $userId, $leaderId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.group-signups', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'member-removed' : 'member-remove-failed',
        ]);
    }

    public function volunteeringCancelGroupReservation(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $ok = false;
        try {
            // Leadership-gated cancel; cancels the reservation + all member rows.
            $ok = ShiftGroupReservationService::cancelReservation($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.group-signups', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'reservation-cancelled' : 'reservation-cancel-failed',
        ]);
    }

    // =========================================================================
    // EXPENSES — claim travel/meals/supplies costs back (React ExpensesTab)
    //
    // Lists the caller's own expense claims with totals, plus an HTML-first
    // submit form. Calls the SAME VolunteerExpenseService the React API
    // (VolunteerExpenseController::myExpenses / submitExpense) uses — all policy
    // validation, org-relationship checks and tenant-scoping live in the service.
    // The org dropdown is filled from VolunteerService::getMyOrganizations,
    // exactly as the React tab does. Receipt file upload is omitted (the React
    // member form does not upload a receipt either — it posts type/amount/
    // description/currency only).
    // =========================================================================

    /** Expense types accepted by VolunteerExpenseService::submitExpense. */
    private const VOL_EXPENSE_TYPES = ['travel', 'meals', 'supplies', 'equipment', 'parking', 'other'];

    public function volunteeringExpenses(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // The caller's own claims (same filter the React member tab passes).
        $expenses = [];
        try {
            $result = VolunteerExpenseService::getExpenses([
                'user_id' => $userId,
                'limit' => 50,
            ]);
            $expenses = $result['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        // Header totals — mirror the React tab's client-side reduction over the
        // loaded claims (claimed / approved-or-paid / paid).
        $totalClaimed = 0.0;
        $totalApproved = 0.0;
        $totalPaid = 0.0;
        foreach ($expenses as $expense) {
            $amount = (float) ($expense['amount'] ?? 0);
            $statusValue = (string) ($expense['status'] ?? '');
            $totalClaimed += $amount;
            if ($statusValue === 'approved' || $statusValue === 'paid') {
                $totalApproved += $amount;
            }
            if ($statusValue === 'paid') {
                $totalPaid += $amount;
            }
        }

        // Organisations the caller can claim against (the form's org dropdown).
        $organizations = [];
        try {
            $orgs = VolunteerService::getMyOrganizations($userId, ['limit' => 50])['items'] ?? [];
            foreach ($orgs as $o) {
                $organizations[] = [
                    'id' => (int) ($o['id'] ?? 0),
                    'name' => (string) ($o['name'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::volunteering-expenses', [
            'title' => __('govuk_alpha_volunteering.expenses.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'volunteering',
            'expenses' => $expenses,
            'organizations' => $organizations,
            'expenseTypes' => self::VOL_EXPENSE_TYPES,
            'stats' => [
                'total_claimed' => $totalClaimed,
                'total_approved' => $totalApproved,
                'total_paid' => $totalPaid,
            ],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function volunteeringSubmitExpense(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $organizationId = (int) $request->input('organization_id', 0);
        $expenseType = $this->allowed($request->input('expense_type'), self::VOL_EXPENSE_TYPES, 'travel');
        $amount = (float) $request->input('amount', 0);
        $description = trim(self::asStr($request->input('description')));
        $currency = trim(self::asStr($request->input('currency')));

        // Friendly inline validation; the service re-validates everything.
        if ($organizationId <= 0) {
            return redirect()->route('govuk-alpha.volunteering.expenses', [
                'tenantSlug' => $tenantSlug, 'status' => 'expense-org-required',
            ]);
        }
        if ($amount <= 0) {
            return redirect()->route('govuk-alpha.volunteering.expenses', [
                'tenantSlug' => $tenantSlug, 'status' => 'expense-amount-invalid',
            ]);
        }
        if ($description === '') {
            return redirect()->route('govuk-alpha.volunteering.expenses', [
                'tenantSlug' => $tenantSlug, 'status' => 'expense-description-required',
            ]);
        }

        $data = [
            'organization_id' => $organizationId,
            'expense_type' => $expenseType,
            'amount' => $amount,
            'description' => $description,
        ];
        if ($currency !== '') {
            $data['currency'] = mb_substr($currency, 0, 10);
        }

        try {
            // Same service the React submitExpense endpoint calls — it owns
            // policy limits, org-relationship checks and the insert. It also
            // throws on validation/forbidden/not-found, which we map to a flag.
            VolunteerExpenseService::submitExpense($userId, $data);
        } catch (\InvalidArgumentException $e) {
            report($e);
            return redirect()->route('govuk-alpha.volunteering.expenses', [
                'tenantSlug' => $tenantSlug, 'status' => 'expense-validation',
            ]);
        } catch (\RuntimeException $e) {
            report($e);
            $status = (int) $e->getCode() === 403 ? 'expense-forbidden' : 'expense-not-found';
            return redirect()->route('govuk-alpha.volunteering.expenses', [
                'tenantSlug' => $tenantSlug, 'status' => $status,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.volunteering.expenses', [
                'tenantSlug' => $tenantSlug, 'status' => 'expense-failed',
            ]);
        }

        return redirect()->route('govuk-alpha.volunteering.expenses', [
            'tenantSlug' => $tenantSlug, 'status' => 'expense-submitted',
        ]);
    }

    // =========================================================================
    // SAFEGUARDING — training records + incident reports
    // Mirrors React SafeguardingTab (GET /v2/volunteering/training + incidents,
    // POST /v2/volunteering/training, POST /v2/volunteering/incidents).
    // Backing service: SafeguardingService::getTrainingForUser,
    //   recordTraining, getIncidentsByReporter, reportIncident.
    // =========================================================================

    /**
     * Render the safeguarding page (training records + incident reports list).
     */
    public function volunteeringSafeguarding(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $svc = app(SafeguardingService::class);

        try {
            $trainingResult = $svc->getTrainingForUser($userId, $tenantId);
            $trainings = $trainingResult['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
            $trainings = [];
        }

        try {
            $incidentResult = $svc->getIncidentsByReporter($userId, $tenantId);
            $incidents = $incidentResult['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
            $incidents = [];
        }

        $status = self::asStr($request->query('status')) ?: null;

        return $this->view('accessible-frontend::volunteering-safeguarding', [
            'tenantSlug' => $tenantSlug,
            'trainings' => $trainings,
            'incidents' => $incidents,
            'status'    => $status,
            // Both /volunteering/training and /volunteering/incidents resolve to
            // this method, so the default tab must come from the URL path; an
            // explicit ?tab= query still overrides it.
            'subView'   => $this->allowed(
                self::asStr($request->query('tab')),
                ['training', 'incidents'],
                $request->is('*/volunteering/incidents') ? 'incidents' : 'training'
            ),
        ]);
    }

    /**
     * Handle POST — log a new training record.
     */
    public function volunteeringSafeguardingLogTraining(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();

        $trainingType = $this->allowed(
            self::asStr($request->input('training_type')),
            ['children_first', 'vulnerable_adults', 'first_aid', 'manual_handling', 'other'],
            ''
        );
        if ($trainingType === '') {
            return redirect()->route('govuk-alpha.volunteering.training', [
                'tenantSlug' => $tenantSlug, 'status' => 'training-type-required', 'tab' => 'training',
            ]);
        }

        $trainingName = trim(self::asStr($request->input('training_name')));
        if ($trainingName === '') {
            return redirect()->route('govuk-alpha.volunteering.training', [
                'tenantSlug' => $tenantSlug, 'status' => 'training-name-required', 'tab' => 'training',
            ]);
        }

        $completedAt = self::asStr($request->input('completed_at'));
        if ($completedAt === '') {
            return redirect()->route('govuk-alpha.volunteering.training', [
                'tenantSlug' => $tenantSlug, 'status' => 'training-date-required', 'tab' => 'training',
            ]);
        }

        $provider   = trim(self::asStr($request->input('provider'))) ?: null;
        $expiresAt  = trim(self::asStr($request->input('expires_at'))) ?: null;

        try {
            $result = app(SafeguardingService::class)->recordTraining($userId, [
                'training_type' => $trainingType,
                'training_name' => $trainingName,
                'provider'      => $provider,
                'completed_at'  => $completedAt,
                'expires_at'    => $expiresAt,
            ], $tenantId);

            if ($result === false) {
                return redirect()->route('govuk-alpha.volunteering.training', [
                    'tenantSlug' => $tenantSlug, 'status' => 'training-failed', 'tab' => 'training',
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.volunteering.training', [
                'tenantSlug' => $tenantSlug, 'status' => 'training-failed', 'tab' => 'training',
            ]);
        }

        return redirect()->route('govuk-alpha.volunteering.training', [
            'tenantSlug' => $tenantSlug, 'status' => 'training-added', 'tab' => 'training',
        ]);
    }

    /**
     * Handle POST — report a safeguarding incident.
     */
    public function volunteeringSafeguardingReportIncident(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();

        $title = trim(self::asStr($request->input('title')));
        if ($title === '') {
            return redirect()->route('govuk-alpha.volunteering.incidents', [
                'tenantSlug' => $tenantSlug, 'status' => 'incident-title-required', 'tab' => 'incidents',
            ]);
        }

        $description = trim(self::asStr($request->input('description')));
        if ($description === '' || mb_strlen($description) < 20) {
            return redirect()->route('govuk-alpha.volunteering.incidents', [
                'tenantSlug' => $tenantSlug, 'status' => 'incident-description-too-short', 'tab' => 'incidents',
            ]);
        }

        $severity = $this->allowed(
            self::asStr($request->input('severity')),
            ['low', 'medium', 'high', 'critical'],
            'low'
        );
        $category = trim(self::asStr($request->input('category'))) ?: 'general';

        try {
            $result = app(SafeguardingService::class)->reportIncident($userId, [
                'title'         => $title,
                'description'   => $description,
                'severity'      => $severity,
                'category'      => $category,
                'incident_type' => 'other',
            ], $tenantId);

            if ($result === false) {
                return redirect()->route('govuk-alpha.volunteering.incidents', [
                    'tenantSlug' => $tenantSlug, 'status' => 'incident-failed', 'tab' => 'incidents',
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.volunteering.incidents', [
                'tenantSlug' => $tenantSlug, 'status' => 'incident-failed', 'tab' => 'incidents',
            ]);
        }

        return redirect()->route('govuk-alpha.volunteering.incidents', [
            'tenantSlug' => $tenantSlug, 'status' => 'incident-reported', 'tab' => 'incidents',
        ]);
    }
}
