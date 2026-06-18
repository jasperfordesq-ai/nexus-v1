<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Services\VolunteerService;
use App\Services\VolunteerEmergencyAlertService;
use App\Services\VolunteerWellbeingService;
use App\Services\VolunteerMatchingService;
use App\Services\VolOrgWalletService;
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
            'SELECT id, name, user_id, status, description, contact_email, website, balance, auto_pay_enabled
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
            'autoPayEnabled' => (bool) ($org->auto_pay_enabled ?? false),
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
    // ORG WALLET — balance, transactions, deposit, auto-pay (React OrgWalletTab)
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
            'autoPayEnabled' => (bool) ($org->auto_pay_enabled ?? false),
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

        $tenantId = TenantContext::getId();
        $enabled = $request->input('enabled') === '1' || $request->boolean('enabled');

        $ok = false;
        try {
            DB::update(
                'UPDATE vol_organizations SET auto_pay_enabled = ? WHERE id = ? AND tenant_id = ?',
                [$enabled ? 1 : 0, $id, $tenantId]
            );
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.volunteering.org.wallet', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? ($enabled ? 'autopay-enabled' : 'autopay-disabled') : 'autopay-failed',
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
            'federated_visibility' => $request->input('federated_visibility') === '1' || $request->boolean('federated_visibility')
                ? 'network' : 'local',
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
}
