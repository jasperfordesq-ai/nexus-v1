<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Organisations — accessible (GOV.UK) frontend parity methods.
 *
 * Brings the accessible volunteer-organisation pages up to functional parity
 * with the React OrganisationsPage / OrganisationDetailPage / RegisterOrganisationPage:
 *
 *  - a paginated browse list with cursor "load more" (the simple list at
 *    /organisations caps at 30 with no pagination);
 *  - a dedicated full-page registration form with per-field inline errors
 *    (the simple list embeds the form at the bottom of the page);
 *  - a "Manage my organisations" entry page for owners/admins;
 *  - a per-organisation open-jobs/vacancies listing;
 *  - an HTML-first "apply to a volunteering opportunity" form reachable from
 *    the organisation context (the React detail page opens a modal; without
 *    JavaScript we navigate to a confirm page that posts to the SAME
 *    volunteering.apply.store route the existing detail page uses).
 *
 * Every method calls the SAME service methods the React API controllers use
 * (VolunteerService / JobVacancyService) — no money/auth/notification logic is
 * reimplemented. New method names are module-prefixed (organisations*) and
 * unique across AlphaController and every sibling trait. Services are resolved
 * statically (VolunteerService) or via app() (JobVacancyService).
 */
trait OrganisationsParity
{
    /** Page size for the paginated organisations browse list (matches React ITEMS_PER_PAGE). */
    private const ORGANISATIONS_PER_PAGE = 20;

    /** Page size for an organisation's open-jobs listing (matches React per_page). */
    private const ORGANISATIONS_JOBS_PER_PAGE = 10;

    /**
     * GET /organisations/browse
     *
     * Paginated directory with cursor-based "load more", mirroring the React
     * OrganisationsPage (search + ITEMS_PER_PAGE + Load More). The existing
     * /organisations page renders a non-paginated, hard-capped list; this adds
     * the missing pagination affordance. Read-only path: VolunteerService::getOrganisations().
     */
    public function organisationsBrowse(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $q = trim(self::asStr($request->query('q')));
        $cursor = trim(self::asStr($request->query('cursor')));

        $filters = ['limit' => self::ORGANISATIONS_PER_PAGE];
        if ($q !== '') {
            $filters['search'] = $q;
        }
        if ($cursor !== '') {
            $filters['cursor'] = $cursor;
        }

        $organisations = [];
        $hasMore = false;
        $nextCursor = null;
        $error = false;
        try {
            $result = \App\Services\VolunteerService::getOrganisations($filters);
            $organisations = is_array($result['items'] ?? null) ? $result['items'] : [];
            $hasMore = (bool) ($result['has_more'] ?? false);
            $nextCursor = $result['cursor'] ?? null;
        } catch (\Throwable $e) {
            report($e);
            $error = true;
        }

        // Surface "Manage my organisations" only when the viewer actually owns or
        // admins at least one live org (parity with the React manage button).
        $manageableCount = $this->organisationsManageableCount($userId);

        return $this->view('accessible-frontend::organisations-browse', [
            'title' => __('govuk_alpha_organisations.browse.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'organisations' => $organisations,
            'organisationsQuery' => $q,
            'hasMore' => $hasMore,
            'nextCursor' => is_string($nextCursor) ? $nextCursor : null,
            'error' => $error,
            'manageableCount' => $manageableCount,
        ]);
    }

    /**
     * GET /organisations/register
     *
     * Dedicated full-page registration form. The React RegisterOrganisationPage
     * is a standalone route; the simple accessible list embeds the form. This
     * surfaces per-field inline errors via the ?status query param the store
     * action redirects to.
     */
    public function organisationsRegisterForm(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $this->view('accessible-frontend::organisations-register', [
            'title' => __('govuk_alpha_organisations.register.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * POST /organisations/register — create a new volunteer organisation.
     *
     * Mirrors VolunteerService::createOrganization() exactly (the same call the
     * React API controller / existing AlphaController::storeOrganisation use):
     * bona-fide-only fields, an explicit terms agreement, and status=pending so
     * an administrator vets the org before it is publicly listed. Validation is
     * server-side; failures redirect back to the register form with the offending
     * field flagged so a no-JS error summary can anchor to it.
     */
    public function organisationsRegister(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $name = trim(self::asStr($request->input('name')));
        $description = trim(self::asStr($request->input('description')));
        $email = trim(self::asStr($request->input('email')));
        $website = trim(self::asStr($request->input('website')));
        $agreedTerms = self::asStr($request->input('agreed_terms'));
        $termsAccepted = in_array(strtolower($agreedTerms), ['1', 'on', 'true'], true);

        // Determine the first failing field so the inline summary can anchor to it.
        $statusField = null;
        if (mb_strlen($name) < 3) {
            $statusField = 'org-name-invalid';
        } elseif (mb_strlen($description) < 20) {
            $statusField = 'org-description-invalid';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $statusField = 'org-email-invalid';
        } elseif ($website !== '' && preg_match('#^https?://#i', $website) !== 1) {
            $statusField = 'org-website-invalid';
        } elseif (! $termsAccepted) {
            $statusField = 'org-terms-required';
        }

        if ($statusField !== null) {
            return redirect()
                ->route('govuk-alpha.organisations.register.form', ['tenantSlug' => $tenantSlug, 'status' => $statusField])
                ->withInput();
        }

        $ok = false;
        try {
            $id = \App\Services\VolunteerService::createOrganization($userId, [
                'name' => mb_substr($name, 0, 255),
                'description' => $description,
                'contact_email' => $email,
                'website' => $website ?: null,
            ]);
            $ok = $id !== null;
        } catch (\Throwable $e) {
            report($e);
        }

        if (! $ok) {
            return redirect()
                ->route('govuk-alpha.organisations.register.form', ['tenantSlug' => $tenantSlug, 'status' => 'org-failed'])
                ->withInput();
        }

        // On success, return to the simple directory which already renders a
        // success banner for the org-submitted status (parity with React's toast).
        return redirect()->route('govuk-alpha.organisations.index', [
            'tenantSlug' => $tenantSlug,
            'status' => 'org-submitted',
        ]);
    }

    /**
     * GET /organisations/manage
     *
     * "Manage my organisations" entry page — lists the live organisations the
     * signed-in user owns or admins, each linking to the existing org-management
     * dashboard. Mirrors the React /v2/volunteering/my-organisations data the
     * manage button is built from (VolunteerService::getMyOrganizations).
     */
    public function organisationsManage(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $organisations = [];
        $error = false;
        try {
            $result = \App\Services\VolunteerService::getMyOrganizations($userId, ['limit' => 50]);
            $organisations = is_array($result['items'] ?? null) ? $result['items'] : [];
        } catch (\Throwable $e) {
            report($e);
            $error = true;
        }

        // Only owners/admins of live (approved/active) orgs get the manage link;
        // pending or member rows are shown with a status note instead.
        return $this->view('accessible-frontend::organisations-manage', [
            'title' => __('govuk_alpha_organisations.manage.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'organisations' => $organisations,
            'error' => $error,
        ]);
    }

    /**
     * GET /organisations/{id}/jobs
     *
     * An organisation's open paid/volunteer/timebank job vacancies. The React
     * detail page fetches /v2/jobs?organization_id={id}&status=open and renders
     * a "Job openings" section; the accessible detail page has none. This mirrors
     * that query via JobVacancyService::getAll(). Gated on job_vacancies (the
     * feature that owns vacancies) AND the org must exist in this tenant (404).
     */
    public function organisationsJobs(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        // The org profile itself is a volunteering feature; the jobs listing is a
        // job_vacancies feature. Require both, matching where each lives in React.
        abort_unless(TenantContext::hasFeature('volunteering'), 403);
        abort_unless(TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Cross-tenant or missing org → 404 (getOrganisationById is tenant-scoped
        // through the model/TenantContext).
        $org = null;
        try {
            $org = \App\Services\VolunteerService::getOrganisationById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($org === null, 404);

        $jobs = [];
        try {
            $result = app(\App\Services\JobVacancyService::class)->getAll([
                'organization_id' => $id,
                'status' => 'open',
                'limit' => self::ORGANISATIONS_JOBS_PER_PAGE,
            ], $userId);
            $jobs = is_array($result['items'] ?? null) ? $result['items'] : [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::organisations-jobs', [
            'title' => __('govuk_alpha_organisations.jobs.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'organisation' => $org,
            'orgJobs' => $jobs,
        ]);
    }

    /**
     * GET /organisations/opportunities/{id}/apply
     *
     * HTML-first "apply to a volunteering opportunity" confirm page reachable
     * from the organisation context. React opens a cover-message modal inline on
     * the org detail page; without JavaScript we render a dedicated page whose
     * form POSTs to the EXISTING volunteering.apply.store route (no new apply
     * logic). The opportunity is fetched with the viewer id so an already-applied
     * member is shown that state rather than the form (parity with the React
     * "Applied" chip hiding the Apply button).
     */
    public function organisationsApplyForm(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('volunteering'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // getOpportunityById is tenant-scoped and returns null for cross-tenant,
        // non-public or missing opportunities → 404.
        $opportunity = null;
        try {
            $opportunity = \App\Services\VolunteerService::getOpportunityById($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($opportunity === null, 404);

        $orgId = (int) ($opportunity['organization_id'] ?? 0);

        return $this->view('accessible-frontend::organisations-apply', [
            'title' => __('govuk_alpha_organisations.apply.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'opportunity' => $opportunity,
            'opportunityId' => $id,
            'orgId' => $orgId,
            'hasApplied' => (bool) ($opportunity['has_applied'] ?? false),
        ]);
    }

    // ---------------------------------------------------------------
    // Private helpers (organisations-prefixed; unique across the controller)
    // ---------------------------------------------------------------

    /**
     * Count the live organisations the user owns or admins (approved/active +
     * owner/admin role), matching the React manageableOrgs filter. Best-effort:
     * any failure yields 0 so the manage entry simply does not show.
     */
    private function organisationsManageableCount(int $userId): int
    {
        try {
            $result = \App\Services\VolunteerService::getMyOrganizations($userId, ['limit' => 50]);
            $items = is_array($result['items'] ?? null) ? $result['items'] : [];

            return count(array_filter($items, static function ($o) {
                $status = is_array($o) ? (string) ($o['status'] ?? '') : '';
                $role = is_array($o) ? (string) ($o['member_role'] ?? '') : '';

                return in_array($status, ['approved', 'active'], true)
                    && in_array($role, ['owner', 'admin'], true);
            }));
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }
}
