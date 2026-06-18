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

    // =====================================================================
    // Internal helpers (module-prefixed; not routed)
    // =====================================================================

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
}
