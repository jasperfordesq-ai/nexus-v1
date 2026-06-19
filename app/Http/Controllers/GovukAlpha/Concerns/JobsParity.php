<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Jobs — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * Closes parity gaps J8 (analytics dashboard), J3 (pipeline board), J5
 * (qualification tool), talent search, and the employer brand page. All five
 * mirror the React jobs pages and reuse the exact services the JobVacancies
 * controller calls (no money/auth/notification logic reimplemented).
 */
trait JobsParity
{
    /**
     * J8 — Full analytics dashboard for one of the member's own vacancies.
     * Mirrors react JobAnalyticsPage: key metrics, views-by-day + weekly-trend
     * sparkbars, applications-by-stage, referral + scorecard stats, and the
     * predictions block. Reuses JobVacancyService::getAnalytics, which itself
     * enforces owner / admin / hiring-team management rights (null => not yours).
     */
    public function jobsAnalytics(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $svc = app(\App\Services\JobVacancyService::class);

        $job = null;
        try {
            $job = $svc->legacyGetById($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($job === null, 404);

        // getAnalytics enforces owner/admin/hiring-team rights; null on an
        // existing job means the viewer may not manage it.
        $analytics = null;
        try {
            $analytics = $svc->getAnalytics($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($analytics === null, 403);

        $predictions = $this->jobsBuildPredictions($id, $userId);

        return $this->view('accessible-frontend::jobs-analytics', [
            'title' => __('govuk_alpha_jobs.analytics.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'jobsActiveTab' => 'mine',
            'job' => $job,
            'analytics' => $analytics,
            'predictions' => $predictions,
        ]);
    }

    /**
     * J3 — Pipeline board. The accessible (no-JS) equivalent of the React
     * drag-and-drop kanban: applicants grouped into stage columns, each card
     * carrying a status-change form (the same persisted path the applicants
     * list uses, via govuk-alpha.jobs.applicants.status). Reuses
     * JobVacancyService::getApplications (owner/admin/team only => null).
     */
    public function jobsPipeline(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $svc = app(\App\Services\JobVacancyService::class);

        $job = null;
        try {
            $job = $svc->legacyGetById($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($job === null, 404);

        $applications = null;
        try {
            $applications = $svc->getApplications($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($applications === null, 403);

        // Group into the canonical pipeline columns. Anything off-pipeline
        // (withdrawn, unknown) lands in a trailing "other" bucket so nothing
        // silently disappears.
        $columns = ['applied', 'screening', 'interview', 'offer', 'accepted', 'rejected'];
        $grouped = array_fill_keys($columns, []);
        $grouped['other'] = [];
        foreach ((array) $applications as $app) {
            $stage = (string) ($app['stage'] ?? $app['status'] ?? 'applied');
            $bucket = in_array($stage, $columns, true) ? $stage : 'other';
            $grouped[$bucket][] = $app;
        }
        if ($grouped['other'] === []) {
            unset($grouped['other']);
        }

        return $this->view('accessible-frontend::jobs-pipeline', [
            'title' => __('govuk_alpha_jobs.pipeline.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'jobsActiveTab' => 'mine',
            'job' => $job,
            'pipeline' => $grouped,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * J5 — "Am I qualified?" assessment for a single vacancy. Mirrors the React
     * QualificationModal: overall match %, skill breakdown, the four scored
     * dimensions, and the readable summary. Reuses
     * JobVacancyService::getQualificationAssessment (null => job not found in
     * this tenant). Any member may run it against any open vacancy.
     */
    public function jobsQualification(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $assessment = null;
        try {
            $assessment = app(\App\Services\JobVacancyService::class)->getQualificationAssessment($userId, $id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($assessment === null, 404);

        return $this->view('accessible-frontend::jobs-qualification', [
            'title' => __('govuk_alpha_jobs.qualification.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'jobsActiveTab' => 'browse',
            'jobId' => $id,
            'assessment' => $assessment,
        ]);
    }

    /**
     * Talent search — employers (vacancy owners / admins) discover community
     * members who have opted into being searchable. Mirrors React
     * TalentSearchPage: keyword / skills / location filters returning candidate
     * cards. Reuses CandidateSearchService::search; access is gated to people
     * who can post jobs, exactly like the API's canSearchTalent.
     */
    public function jobsTalentSearch(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->jobsCanSearchTalent($userId), 403);

        $keywords = trim(self::asStr($request->query('keywords')));
        $skills = trim(self::asStr($request->query('skills')));
        $location = trim(self::asStr($request->query('location')));
        $offset = max(0, (int) $request->query('offset', 0));
        $perPage = 20;

        $filters = ['limit' => $perPage, 'offset' => $offset];
        if ($keywords !== '') {
            $filters['keywords'] = mb_substr($keywords, 0, 120);
        }
        if ($skills !== '') {
            $filters['skills'] = array_filter(array_map('trim', explode(',', $skills)));
        }
        if ($location !== '') {
            $filters['location'] = mb_substr($location, 0, 120);
        }

        $items = [];
        $total = 0;
        $hasSearched = $keywords !== '' || $skills !== '' || $location !== '';
        try {
            $result = app(\App\Services\CandidateSearchService::class)->search($filters, \App\Core\TenantContext::getId());
            $items = is_array($result['items'] ?? null) ? $result['items'] : [];
            $total = (int) ($result['total'] ?? 0);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::jobs-talent-search', [
            'title' => __('govuk_alpha_jobs.talent.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'jobsActiveTab' => 'mine',
            'candidates' => $items,
            'talentTotal' => $total,
            'talentFilters' => ['keywords' => $keywords, 'skills' => $skills, 'location' => $location],
            'talentMeta' => ['offset' => $offset, 'per_page' => $perPage, 'has_more' => ($offset + $perPage) < $total],
            'hasSearched' => $hasSearched,
        ]);
    }

    /**
     * Talent search — full profile for one opted-in candidate. Reuses
     * CandidateSearchService::getCandidateProfile (null => not searchable / not
     * in this tenant => 404).
     */
    public function jobsTalentProfile(Request $request, string $tenantSlug, int $candidateId): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->jobsCanSearchTalent($userId), 403);

        $profile = null;
        try {
            $profile = app(\App\Services\CandidateSearchService::class)->getCandidateProfile($candidateId, \App\Core\TenantContext::getId());
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($profile === null, 404);

        return $this->view('accessible-frontend::jobs-talent-profile', [
            'title' => trim((string) ($profile['name'] ?? '')) ?: __('govuk_alpha_jobs.talent.profile_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'jobsActiveTab' => 'mine',
            'candidate' => $profile,
        ]);
    }

    /**
     * Employer brand page — a public-within-tenant profile for an employer
     * (vacancy poster): their open vacancies plus their employer reviews and
     * the aggregate rating with the four review dimensions. Mirrors React
     * EmployerBrandPage. Reuses JobVacancyService::getAll(user_id) and the
     * Review model exactly as JobVacanciesController::employerReviews does.
     */
    public function jobsEmployerBrand(Request $request, string $tenantSlug, int $employerId): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = \App\Core\TenantContext::getId();

        // The employer must be a member of this tenant — a cross-tenant id is
        // invisible (404). Use a tenant-scoped lookup.
        $employer = \App\Models\User::where('id', $employerId)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'first_name', 'last_name', 'avatar_url', 'bio', 'resume_headline', 'location', 'created_at']);
        abort_if($employer === null, 404);

        // Open vacancies posted by this employer.
        $openJobs = [];
        try {
            $result = app(\App\Services\JobVacancyService::class)->getAll(
                ['user_id' => $employerId, 'status' => 'open', 'limit' => 50, 'sort' => 'newest'],
                $userId
            );
            $openJobs = is_array($result['items'] ?? null) ? $result['items'] : [];
        } catch (\Throwable $e) {
            report($e);
        }

        // Employer reviews + aggregate stats, mirroring employerReviews().
        $reviews = [];
        $reviewStats = ['average_rating' => null, 'total_reviews' => 0, 'dimensions' => []];
        try {
            $rows = \App\Models\Review::where('tenant_id', $tenantId)
                ->where('receiver_id', $employerId)
                ->where('review_type', 'employer')
                ->where('status', 'approved')
                ->with('reviewer:id,first_name,last_name,avatar_url')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            $reviews = $rows->map(function ($r) {
                $dims = is_array($r->dimensions) ? $r->dimensions : [];
                return [
                    'id' => (int) $r->id,
                    'rating' => (int) $r->rating,
                    'comment' => (string) ($r->comment ?? ''),
                    'dimensions' => $dims,
                    'reviewer_name' => $r->reviewer
                        ? trim(($r->reviewer->first_name ?? '') . ' ' . ($r->reviewer->last_name ?? ''))
                        : '',
                    'created_at' => $r->created_at?->toIso8601String(),
                ];
            })->all();

            $count = count($reviews);
            $reviewStats['total_reviews'] = $count;
            if ($count > 0) {
                $reviewStats['average_rating'] = round(array_sum(array_column($reviews, 'rating')) / $count, 1);

                // Average each dimension across reviews that carry it.
                foreach (['respect', 'communication', 'flexibility', 'impact'] as $dim) {
                    $vals = [];
                    foreach ($reviews as $rv) {
                        if (isset($rv['dimensions'][$dim]) && is_numeric($rv['dimensions'][$dim])) {
                            $vals[] = (float) $rv['dimensions'][$dim];
                        }
                    }
                    if ($vals !== []) {
                        $reviewStats['dimensions'][$dim] = round(array_sum($vals) / count($vals), 1);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::jobs-employer-brand', [
            'title' => trim(($employer->first_name ?? '') . ' ' . ($employer->last_name ?? '')) ?: __('govuk_alpha_jobs.employer.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'jobsActiveTab' => 'browse',
            'employer' => [
                'id' => (int) $employer->id,
                'name' => trim(($employer->first_name ?? '') . ' ' . ($employer->last_name ?? '')),
                'avatar_url' => $employer->avatar_url,
                'headline' => $employer->resume_headline,
                'bio' => $employer->bio,
                'location' => $employer->location,
                'member_since' => $employer->created_at?->toIso8601String(),
            ],
            'openJobs' => $openJobs,
            'employerReviews' => $reviews,
            'reviewStats' => $reviewStats,
        ]);
    }

    /**
     * Employer onboarding — the no-JS equivalent of the React EmployerOnboarding
     * wizard's welcome + tips steps. The React wizard's "organisation" step is
     * cosmetic (localStorage only; nothing is persisted server-side) and its only
     * real action is posting a first vacancy via POST /v2/jobs. The accessible
     * create-vacancy form (govuk-alpha.jobs.create / .store) already provides that
     * exact path, so this page is a guided landing that funnels first-time posters
     * into it — no new mutation, no invented "create-org" endpoint. Any
     * authenticated member who can see the jobs module may view it.
     */
    public function jobsEmployerOnboarding(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Does the member already post opportunities? Used to soften the copy
        // (returning posters see "post another" rather than "get started").
        $hasPosted = false;
        try {
            $hasPosted = \App\Models\JobVacancy::where('tenant_id', \App\Core\TenantContext::getId())
                ->where('user_id', $userId)
                ->exists();
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::jobs-onboarding', [
            'title' => __('govuk_alpha_jobs.onboarding.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'jobsActiveTab' => 'mine',
            'hasPosted' => $hasPosted,
        ]);
    }

    /**
     * Interviews & offers inbox — the candidate-side responses centre. Mirrors
     * the React MyApplicationsPage inline interview/offer cards (which merge in
     * /v2/jobs/my-interviews + /v2/jobs/my-offers). Lists the member's proposed
     * interviews and pending/decided offers, each with the same accept / decline
     * / reject actions. Reuses JobInterviewService::getForUser and
     * JobOfferService::getForUser (both candidate-scoped to this tenant).
     */
    public function jobsResponses(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        [$interviews, $offers] = $this->jobsResponsesData($userId);

        return $this->view('accessible-frontend::jobs-responses', [
            'title' => __('govuk_alpha_jobs.responses.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'jobsActiveTab' => 'responses',
            'interviews' => $interviews,
            'offers' => $offers,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Candidate accepts a proposed interview. JobInterviewService::accept enforces
     * that only the applicant on the interview can accept (and only while still
     * 'proposed'); it fires the poster notification internally, already wrapped in
     * LocaleContext. We never re-implement that logic — false simply routes back
     * with an error flash.
     */
    public function jobsAcceptInterview(Request $request, string $tenantSlug, int $interviewId): RedirectResponse
    {
        return $this->jobsHandleResponseAction(
            $tenantSlug,
            fn (int $uid) => \App\Services\JobInterviewService::accept($interviewId, $uid, $this->jobsResponseNote($request)),
            'interview-accepted',
            'interview-failed'
        );
    }

    /** Candidate declines a proposed interview. See jobsAcceptInterview for the gating contract. */
    public function jobsDeclineInterview(Request $request, string $tenantSlug, int $interviewId): RedirectResponse
    {
        return $this->jobsHandleResponseAction(
            $tenantSlug,
            fn (int $uid) => \App\Services\JobInterviewService::decline($interviewId, $uid, $this->jobsResponseNote($request)),
            'interview-declined',
            'interview-failed'
        );
    }

    /**
     * Candidate accepts a pending offer. JobOfferService::accept enforces that
     * only the applicant can accept, atomically fills the vacancy, withdraws
     * sibling offers and mints timebank credits inside a locked transaction. We
     * call it as-is — no money logic is duplicated here.
     */
    public function jobsAcceptOffer(Request $request, string $tenantSlug, int $offerId): RedirectResponse
    {
        return $this->jobsHandleResponseAction(
            $tenantSlug,
            fn (int $uid) => \App\Services\JobOfferService::accept($offerId, $uid),
            'offer-accepted',
            'offer-failed'
        );
    }

    /** Candidate rejects a pending offer. See jobsAcceptOffer for the gating/credit contract. */
    public function jobsRejectOffer(Request $request, string $tenantSlug, int $offerId): RedirectResponse
    {
        return $this->jobsHandleResponseAction(
            $tenantSlug,
            fn (int $uid) => \App\Services\JobOfferService::reject($offerId, $uid),
            'offer-rejected',
            'offer-failed'
        );
    }

    // =====================================================================
    // Internal helpers (module-prefixed; not routed)
    // =====================================================================

    /**
     * Shared scaffold for the four candidate response POSTs: auth-gate, run the
     * service closure (which carries its own owner/state checks), and redirect
     * back to the responses inbox with a success or failure flash.
     *
     * @param callable(int): bool $action
     */
    private function jobsHandleResponseAction(string $tenantSlug, callable $action, string $okStatus, string $failStatus): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $ok = false;
        try {
            $ok = (bool) $action($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.jobs.responses', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? $okStatus : $failStatus,
        ]);
    }

    /** Optional free-text note attached to an interview accept/decline (trimmed, capped). */
    private function jobsResponseNote(Request $request): ?string
    {
        $note = trim(self::asStr($request->input('note')));
        if ($note === '') {
            return null;
        }
        return mb_substr($note, 0, 1000);
    }

    /**
     * Build the candidate's interview + offer lists for the responses inbox.
     * Normalises each to a flat, view-safe shape (the service returns full
     * Eloquent arrays). Anything the member cannot act on (already-decided
     * interviews/offers) still appears, read-only, so they have a record.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function jobsResponsesData(int $userId): array
    {
        $interviews = [];
        try {
            foreach (\App\Services\JobInterviewService::getForUser($userId) as $row) {
                $vacancy = is_array($row['vacancy'] ?? null) ? $row['vacancy'] : [];
                $interviews[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'vacancy_id' => (int) ($row['vacancy_id'] ?? ($vacancy['id'] ?? 0)),
                    'vacancy_title' => trim((string) ($vacancy['title'] ?? '')),
                    'interview_type' => (string) ($row['interview_type'] ?? 'video'),
                    'scheduled_at' => $row['scheduled_at'] ?? null,
                    'duration_mins' => isset($row['duration_mins']) ? (int) $row['duration_mins'] : null,
                    'location_notes' => trim((string) ($row['location_notes'] ?? '')),
                    'status' => (string) ($row['status'] ?? 'proposed'),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $offers = [];
        try {
            foreach (\App\Services\JobOfferService::getForUser($userId) as $row) {
                $vacancy = is_array($row['vacancy'] ?? null) ? $row['vacancy'] : [];
                $offers[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'vacancy_id' => (int) ($row['vacancy_id'] ?? ($vacancy['id'] ?? 0)),
                    'vacancy_title' => trim((string) ($vacancy['title'] ?? '')),
                    'salary_offered' => $row['salary_offered'] ?? null,
                    'salary_currency' => trim((string) ($row['salary_currency'] ?? '')),
                    'salary_type' => (string) ($row['salary_type'] ?? ''),
                    'start_date' => $row['start_date'] ?? null,
                    'message' => trim((string) ($row['message'] ?? '')),
                    'status' => (string) ($row['status'] ?? 'pending'),
                    'expires_at' => $row['expires_at'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return [$interviews, $offers];
    }

    /**
     * True when the member may use talent search: any vacancy owner, or a
     * tenant admin / super-admin. Mirrors JobVacanciesController::canSearchTalent
     * without reaching into the controller's private method.
     */
    private function jobsCanSearchTalent(int $userId): bool
    {
        $tenantId = \App\Core\TenantContext::getId();

        $user = \App\Models\User::where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'role', 'is_admin', 'is_super_admin', 'is_tenant_super_admin', 'is_god']);

        if ($user) {
            $role = (string) ($user->role ?? '');
            if (in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'], true)
                || (bool) ($user->is_admin ?? false)
                || (bool) ($user->is_super_admin ?? false)
                || (bool) ($user->is_tenant_super_admin ?? false)
                || (bool) ($user->is_god ?? false)
            ) {
                return true;
            }
        }

        return \App\Models\JobVacancy::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->exists();
    }

    // =========================================================================
    // Bias Audit — admin-only hiring-fairness analytics (mirrors React BiasAuditPage)
    // =========================================================================

    /**
     * Bias audit dashboard — admin-only hiring-process analytics.
     *
     * Mirrors React BiasAuditPage: application funnel, rejection rates by
     * stage, average time-in-stage, skills-match correlation, and source
     * effectiveness. Accepts optional `from`, `to` (Y-m-d) and `job_id`
     * query params, defaulting to the last 12 months across all jobs.
     *
     * Calls JobBiasAuditService::generateReport directly (the same service
     * AdminJobsController::biasAudit calls) — no HTTP round-trip.
     * Auth mirrors AdminJobsController::requireAdminForJobs: feature gate +
     * admin role check matching requireAdmin() roles.
     */
    public function jobsBiasAudit(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Require admin role — mirrors BaseApiController::requireAdmin()
        $tenantId = \App\Core\TenantContext::getId();
        $adminUser = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first(['id', 'role', 'is_super_admin', 'is_tenant_super_admin']);

        // EXACTLY mirrors BaseApiController::requireAdmin() — role allow-list OR the
        // two super-admin flags. Do NOT add is_admin / is_god column checks: the API
        // gate ignores them, and honouring them here would make this accessible route
        // a WIDER door to hiring analytics than the React/API path.
        $isAdmin = $adminUser && (
            in_array((string) ($adminUser->role ?? ''), ['admin', 'tenant_admin', 'super_admin', 'god'], true)
            || (bool) ($adminUser->is_super_admin ?? false)
            || (bool) ($adminUser->is_tenant_super_admin ?? false)
        );

        abort_unless($isAdmin, 403);

        // Sanitise and clamp filter inputs
        $dateFrom = null;
        $dateTo   = null;
        $jobId    = null;

        $rawFrom = trim((string) ($request->query('from') ?? ''));
        $rawTo   = trim((string) ($request->query('to') ?? ''));
        $rawJob  = trim((string) ($request->query('job_id') ?? ''));

        if ($rawFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom)) {
            $dateFrom = $rawFrom;
        }
        if ($rawTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTo)) {
            $dateTo = $rawTo;
        }
        if ($rawJob !== '' && ctype_digit($rawJob)) {
            $jobId = (int) $rawJob;
        }

        $report = null;
        try {
            $report = app(\App\Services\JobBiasAuditService::class)
                ->generateReport($tenantId, $jobId, $dateFrom, $dateTo);
        } catch (\Throwable $e) {
            report($e);
        }

        // Optionally build a list of jobs for the filter <select>
        $jobs = [];
        try {
            $jobs = \Illuminate\Support\Facades\DB::table('job_vacancies')
                ->where('tenant_id', $tenantId)
                ->orderBy('title')
                ->pluck('title', 'id')
                ->toArray();
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::jobs-bias-audit', [
            'title'      => __('govuk_alpha_jobs.bias_audit.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav'  => 'admin',
            'report'     => $report,
            'jobs'       => $jobs,
            'filterFrom' => $dateFrom,
            'filterTo'   => $dateTo,
            'filterJob'  => $jobId,
        ]);
    }

    /**
     * Build the predictions block shown on the analytics page, mirroring
     * JobVacanciesController::predictions (same similar-jobs aggregation and
     * salary comparison). AI narrative insights are intentionally omitted in
     * the accessible build — the numeric predictions stand alone. Returns null
     * when the caller cannot manage the vacancy (defensive; the page already
     * gated via getAnalytics).
     *
     * @return array<string, mixed>|null
     */
    private function jobsBuildPredictions(int $jobId, int $userId): ?array
    {
        $tenantId = \App\Core\TenantContext::getId();

        try {
            $vacancy = \App\Models\JobVacancy::where('tenant_id', $tenantId)->find($jobId);
            if ($vacancy === null) {
                return null;
            }

            $similarJobs = \App\Models\JobVacancy::where('tenant_id', $tenantId)
                ->where('id', '!=', $jobId)
                ->where('type', $vacancy->type)
                ->where('status', 'filled')
                ->select('id', 'applications_count', 'views_count', 'created_at', 'updated_at')
                ->limit(50)
                ->get();

            $avgApplications = (float) ($similarJobs->avg('applications_count') ?: 0);
            $avgViews = (float) ($similarJobs->avg('views_count') ?: 0);
            $avgDaysToFill = $similarJobs->count() > 0
                ? (float) $similarJobs->avg(fn ($j) => $j->created_at->diffInDays($j->updated_at))
                : null;

            $currentApps = (int) ($vacancy->applications_count ?? 0);
            $currentViews = (int) ($vacancy->views_count ?? 0);
            $daysPosted = $vacancy->created_at ? (int) now()->diffInDays($vacancy->created_at) : 0;

            $currentConversion = $currentViews > 0 ? round(($currentApps / $currentViews) * 100, 1) : 0.0;
            $avgConversion = $avgViews > 0 ? round(($avgApplications / $avgViews) * 100, 1) : 0.0;

            $avgSalary = \App\Models\JobVacancy::where('tenant_id', $tenantId)
                ->where('type', $vacancy->type)
                ->whereNotNull('salary_min')
                ->where('salary_min', '>', 0)
                ->avg('salary_min');

            $salaryComparison = null;
            if ($avgSalary && $vacancy->salary_min) {
                $diff = (int) round((((float) $vacancy->salary_min - (float) $avgSalary) / (float) $avgSalary) * 100, 0);
                $salaryComparison = [
                    'your_salary' => (float) $vacancy->salary_min,
                    'market_avg' => round((float) $avgSalary, 0),
                    'diff_percent' => $diff,
                ];
            }

            return [
                'expected_applications' => [
                    'value' => (int) max(1, round($avgApplications)),
                    'current' => $currentApps,
                    'above_average' => $currentApps >= $avgApplications,
                ],
                'estimated_time_to_fill' => [
                    'value' => $avgDaysToFill ? (int) round($avgDaysToFill) : null,
                    'days_posted' => $daysPosted,
                ],
                'conversion_rate' => [
                    'yours' => $currentConversion,
                    'average' => $avgConversion,
                    'above_average' => $currentConversion >= $avgConversion,
                ],
                'salary_comparison' => $salaryComparison,
                'similar_jobs_analyzed' => $similarJobs->count(),
            ];
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Download a job application's attached CV. Mirrors
     * JobVacanciesController::downloadCv exactly — only the applicant, the job
     * poster, or a tenant admin may fetch it, blind-hiring hides the CV from the
     * poster, and the lookup is tenant-scoped so a cross-tenant application id
     * 404s. Without this the accessible employer pipeline could show a CV
     * filename but offer no way to open it.
     */
    public function jobsDownloadCv(Request $request, string $tenantSlug, int $applicationId): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = \App\Core\TenantContext::getId();
        $application = \App\Models\JobApplication::with(['vacancy'])->find($applicationId);

        abort_if(
            ! $application || ! $application->vacancy || (int) $application->vacancy->tenant_id !== $tenantId,
            404
        );

        $isApplicant = (int) $application->user_id === $userId;
        $isPoster = (int) $application->vacancy->user_id === $userId;
        if (! $isApplicant && ! $isPoster) {
            $user = \App\Models\User::where('id', $userId)->first(['id', 'role']);
            $isAdmin = $user && in_array($user->role, ['admin', 'super_admin', 'tenant_admin'], true);
            abort_unless($isAdmin, 403);
        }

        // Blind hiring: the poster (and admins) must not see the CV; only the
        // applicant themselves can download their own.
        abort_if(! $isApplicant && (bool) ($application->vacancy->blind_hiring ?? false), 403);

        abort_if(empty($application->cv_path), 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($application->cv_path), 404);

        $filename = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $application->cv_filename ?? basename($application->cv_path));

        return response()->download(
            \Illuminate\Support\Facades\Storage::disk('local')->path($application->cv_path),
            (string) $filename
        );
    }

    /**
     * Application status-history timeline for one of the member's applications.
     * Mirrors React MyApplicationsPage's timeline. JobVacancyService::
     * getApplicationHistory enforces applicant/owner/admin access (null => not
     * yours) and redacts notes + reviewer name for the applicant.
     */
    public function jobsApplicationHistory(Request $request, string $tenantSlug, int $applicationId): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('job_vacancies'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $svc = app(\App\Services\JobVacancyService::class);

        $history = null;
        try {
            $history = $svc->getApplicationHistory($applicationId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        // null => not found OR forbidden (service sets errors); a 404 keeps the
        // accessible path from leaking which applications exist.
        abort_if($history === null, 404);

        // Vacancy title for the page heading (tenant + access already enforced
        // by getApplicationHistory above).
        $vacancyTitle = '';
        try {
            $row = \Illuminate\Support\Facades\DB::table('job_vacancy_applications as a')
                ->join('job_vacancies as v', 'v.id', '=', 'a.vacancy_id')
                ->where('a.id', $applicationId)
                ->where('a.tenant_id', \App\Core\TenantContext::getId())
                ->value('v.title');
            $vacancyTitle = (string) ($row ?? '');
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::jobs-application-history', [
            'title' => __('govuk_alpha_jobs.history.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'jobsActiveTab' => 'applications',
            'applicationId' => $applicationId,
            'vacancyTitle' => $vacancyTitle,
            'history' => is_array($history) ? $history : [],
        ]);
    }
}
