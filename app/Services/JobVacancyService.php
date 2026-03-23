<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\JobAlert;
use App\Models\JobApplication;
use App\Models\JobApplicationHistory;
use App\Models\JobVacancy;
use App\Models\SavedJob;
use App\Models\User;
use App\Services\JobModerationService;
use App\Services\JobSpamDetectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * JobVacancyService — Laravel DI-based service for job vacancy operations.
 *
 * Manages job vacancy CRUD, applications, saved jobs, alerts, analytics,
 * skills matching, featured jobs, and expiry/renewal.
 * All queries are tenant-scoped via HasTenantScope trait or explicit tenant_id.
 */
class JobVacancyService
{
    /** @var array Collected errors from the last operation */
    private array $errors = [];

    public function __construct(
        private readonly JobVacancy $vacancy,
    ) {}

    /**
     * Get collected errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all job vacancies with filtering and cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = [], ?int $userId = null): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->vacancy->newQuery()
            ->with(['creator:id,first_name,last_name,avatar_url'])
            ->leftJoin('organizations as o', 'job_vacancies.organization_id', '=', 'o.id')
            ->select('job_vacancies.*', 'o.name as organization_name', 'o.logo_url as organization_logo');

        if (!empty($filters['status'])) {
            $query->where('job_vacancies.status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $query->where('job_vacancies.type', $filters['type']);
        }
        if (!empty($filters['commitment'])) {
            $query->where('job_vacancies.commitment', $filters['commitment']);
        }
        if (!empty($filters['category'])) {
            $query->where('job_vacancies.category', $filters['category']);
        }
        if (!empty($filters['search'])) {
            $parsed = self::parseBooleanQuery($filters['search']);

            // Must terms (AND) — all must match title OR description OR skills_required
            foreach ($parsed['must'] as $term) {
                $query->where(function (Builder $inner) use ($term) {
                    $inner->where('job_vacancies.title', 'LIKE', "%{$term}%")
                          ->orWhere('job_vacancies.description', 'LIKE', "%{$term}%")
                          ->orWhere('job_vacancies.skills_required', 'LIKE', "%{$term}%");
                });
            }

            // Should terms (OR) — at least one must match
            if (!empty($parsed['should'])) {
                $query->where(function (Builder $inner) use ($parsed) {
                    foreach ($parsed['should'] as $term) {
                        $inner->orWhere(function (Builder $sub) use ($term) {
                            $sub->where('job_vacancies.title', 'LIKE', "%{$term}%")
                                ->orWhere('job_vacancies.description', 'LIKE', "%{$term}%");
                        });
                    }
                });
            }

            // Not terms — exclude
            foreach ($parsed['not'] as $term) {
                $query->where('job_vacancies.title', 'NOT LIKE', "%{$term}%")
                      ->where('job_vacancies.description', 'NOT LIKE', "%{$term}%");
            }
        }
        if (!empty($filters['user_id'])) {
            $query->where('job_vacancies.user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['featured'])) {
            $query->where('job_vacancies.is_featured', true)
                ->where(function (Builder $q) {
                    $q->whereNull('job_vacancies.featured_until')
                      ->orWhere('job_vacancies.featured_until', '>', now());
                });
        }

        // Haversine geolocation radius filter
        $lat = isset($filters['latitude']) ? (float) $filters['latitude'] : null;
        $lng = isset($filters['longitude']) ? (float) $filters['longitude'] : null;
        $radiusKm = isset($filters['radius_km']) ? (float) $filters['radius_km'] : null;

        if ($lat !== null && $lng !== null && $radiusKm !== null && $radiusKm > 0) {
            $query->whereNotNull('job_vacancies.latitude')
                ->whereNotNull('job_vacancies.longitude')
                ->whereRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(job_vacancies.latitude)) * cos(radians(job_vacancies.longitude) - radians(?)) + sin(radians(?)) * sin(radians(job_vacancies.latitude)))) <= ?',
                    [$lat, $lng, $lat, $radiusKm]
                );
        }

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('job_vacancies.id', '<', (int) $cursorId);
            }
        }

        $query->orderByRaw('(CASE WHEN job_vacancies.is_featured = 1 AND (job_vacancies.featured_until IS NULL OR job_vacancies.featured_until > NOW()) THEN 0 ELSE 1 END) ASC')
            ->orderByDesc('job_vacancies.created_at')
            ->orderByDesc('job_vacancies.id');

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $enriched = $items->map(fn ($item) => $this->enrichVacancy($item, $userId))->values()->all();

        return [
            'items' => $enriched,
            'cursor' => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single job vacancy by ID.
     */
    public function getById(int $id): ?array
    {
        $job = $this->vacancy->newQuery()
            ->leftJoin('organizations as o', 'job_vacancies.organization_id', '=', 'o.id')
            ->select('job_vacancies.*', 'o.name as organization_name', 'o.logo_url as organization_logo')
            ->where('job_vacancies.id', $id)
            ->first();

        if (!$job) {
            return null;
        }

        $data = $this->enrichVacancy($job);
        $data['applications_count'] = (int) JobApplication::where('vacancy_id', $id)->count();

        return $data;
    }

    /**
     * Get a single job vacancy by ID with optional userId for has_applied/is_saved checks.
     */
    public function legacyGetById(int|string $id, ?int $userId = null): ?array
    {
        $job = $this->vacancy->newQuery()
            ->leftJoin('organizations as o', 'job_vacancies.organization_id', '=', 'o.id')
            ->select('job_vacancies.*', 'o.name as organization_name', 'o.logo_url as organization_logo')
            ->where('job_vacancies.id', $id)
            ->first();

        if (!$job) {
            return null;
        }

        return $this->enrichVacancy($job, $userId);
    }

    /**
     * Create a new job vacancy.
     */
    public function create(int $userId, array $data): int
    {
        $this->errors = [];

        // EU Pay Transparency Directive (June 2026) compliance — salary range required unless negotiable
        $salaryNegotiable = !empty($data['salary_negotiable']) && $data['salary_negotiable'];
        if (!$salaryNegotiable) {
            $hasSalaryMin = isset($data['salary_min']) && $data['salary_min'] !== null && $data['salary_min'] !== '';
            $hasSalaryMax = isset($data['salary_max']) && $data['salary_max'] !== null && $data['salary_max'] !== '';
            if (!$hasSalaryMin || !$hasSalaryMax) {
                // Only enforce for paid job types
                $jobType = $data['type'] ?? 'volunteer';
                if ($jobType === 'paid') {
                    $this->errors[] = ['code' => 'VALIDATION_SALARY_REQUIRED', 'message' => 'A salary range (min and max) is required unless the role is marked as salary negotiable. EU Pay Transparency Directive compliance.'];
                    return 0;
                }
            }
        }

        // Salary min cannot exceed max
        if (!empty($data['salary_min']) && !empty($data['salary_max']) && (float)$data['salary_min'] > (float)$data['salary_max']) {
            $this->errors[] = ['code' => 'VALIDATION_SALARY_RANGE', 'message' => 'Minimum salary cannot exceed maximum salary'];
            return 0;
        }

        $tenantId = TenantContext::getId();

        // Run spam detection (Agent B)
        $spamResult = JobSpamDetectionService::analyzeJob($data, $userId, $tenantId);
        $spamScore = $spamResult['score'];
        $spamFlags = $spamResult['flags'];
        $spamAction = $spamResult['action'];

        // Determine initial status and moderation_status
        $status = 'open';
        $moderationStatus = null;

        if ($spamAction === 'block') {
            $status = 'closed';
            $moderationStatus = 'rejected';
        } elseif ($spamAction === 'flag' || JobModerationService::isModerationEnabled($tenantId)) {
            $status = 'draft';
            $moderationStatus = 'pending_review';
        }

        $vacancy = $this->vacancy->newQuery()->create(array_filter([
            'title'          => trim($data['title']),
            'description'    => trim($data['description'] ?? ''),
            'type'           => $data['type'] ?? 'volunteer',
            'commitment'     => $data['commitment'] ?? 'flexible',
            'location'       => $data['location'] ?? null,
            'status'         => $status,
            'user_id'        => $userId,
            'tagline'        => isset($data['tagline']) ? trim($data['tagline']) : null,
            'video_url'      => $data['video_url'] ?? null,
            'culture_photos' => $data['culture_photos'] ?? null,
            'company_size'   => $data['company_size'] ?? null,
            'benefits'       => $data['benefits'] ?? null,
            'blind_hiring'   => !empty($data['blind_hiring']) ? 1 : 0,
            'moderation_status' => $moderationStatus,
            'spam_score'     => $spamScore,
            'spam_flags'     => !empty($spamFlags) ? $spamFlags : null,
        ], fn($v) => $v !== null));

        // Log spam detection results (Agent B)
        if ($spamAction === 'block') {
            Log::warning("Job #{$vacancy->id} auto-blocked by spam detection (score: {$spamScore})", [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'flags' => $spamFlags,
            ]);
        } elseif ($spamAction === 'flag') {
            Log::info("Job #{$vacancy->id} auto-flagged for moderation (score: {$spamScore})", [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'flags' => $spamFlags,
            ]);
        }

        // Dispatch webhook for vacancy creation
        try {
            \App\Services\WebhookDispatchService::dispatch('job.vacancy.created', [
                'vacancy_id' => $vacancy->id,
                'title'      => $vacancy->title,
                'type'       => $vacancy->type,
                'tenant_id'  => TenantContext::getId(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('JobVacancyService::create webhook dispatch failed: ' . $e->getMessage());
        }

        // Fire event to notify job alert subscribers
        try {
            $creator = User::find($userId);
            if ($creator) {
                event(new \App\Events\JobVacancyCreated($vacancy, $creator, TenantContext::getId()));
            }
        } catch (\Throwable $e) {
            Log::warning('JobVacancyService::create event dispatch failed: ' . $e->getMessage());
        }

        return $vacancy->id;
    }

    /**
     * Update an existing job vacancy.
     */
    public function update(int $id, int $userId, array $data): bool
    {
        $this->errors = [];

        $vacancy = $this->vacancy->newQuery()->find($id);
        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return false;
        }

        // Check ownership or admin
        if ((int) $vacancy->user_id !== $userId) {
            $user = User::where('id', $userId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'You can only edit your own job vacancies'];
                return false;
            }
        }

        $allowedFields = [
            'title', 'description', 'location', 'latitude', 'longitude', 'is_remote', 'type', 'commitment',
            'category', 'skills_required', 'hours_per_week', 'time_credits',
            'contact_email', 'contact_phone', 'deadline', 'status', 'organization_id',
            'salary_min', 'salary_max', 'salary_type', 'salary_currency', 'salary_negotiable',
            'tagline', 'video_url', 'culture_photos', 'company_size', 'benefits',
            'blind_hiring',
        ];

        $updates = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (empty($updates)) {
            return true;
        }

        // EU Pay Transparency Directive (June 2026) compliance — salary range required unless negotiable
        // Only validate when salary fields or type are being touched in this update
        $salaryFieldsTouched = array_key_exists('salary_min', $updates) || array_key_exists('salary_max', $updates)
            || array_key_exists('salary_negotiable', $updates) || array_key_exists('type', $updates);
        $typeAfterUpdate = $updates['type'] ?? $vacancy->type ?? null;
        if ($salaryFieldsTouched && $typeAfterUpdate === 'paid') {
            $salaryNegotiable = array_key_exists('salary_negotiable', $updates)
                ? !empty($updates['salary_negotiable'])
                : !empty($vacancy->salary_negotiable);

            if (!$salaryNegotiable) {
                $salaryMin = array_key_exists('salary_min', $updates) ? $updates['salary_min'] : $vacancy->salary_min;
                $salaryMax = array_key_exists('salary_max', $updates) ? $updates['salary_max'] : $vacancy->salary_max;
                if (($salaryMin === null || $salaryMin === '') || ($salaryMax === null || $salaryMax === '')) {
                    $this->errors[] = ['code' => 'VALIDATION_SALARY_REQUIRED', 'message' => 'A salary range (min and max) is required unless the role is marked as salary negotiable. EU Pay Transparency Directive compliance.'];
                    return false;
                }
            }
        }

        // Salary min cannot exceed max
        $finalMin = array_key_exists('salary_min', $updates) ? $updates['salary_min'] : ($vacancy->salary_min ?? null);
        $finalMax = array_key_exists('salary_max', $updates) ? $updates['salary_max'] : ($vacancy->salary_max ?? null);
        if (!empty($finalMin) && !empty($finalMax) && (float)$finalMin > (float)$finalMax) {
            $this->errors[] = ['code' => 'VALIDATION_SALARY_RANGE', 'message' => 'Minimum salary cannot exceed maximum salary'];
            return false;
        }

        try {
            $vacancy->update($updates);
            return true;
        } catch (\Throwable $e) {
            Log::error('JobVacancyService::update failed: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_INTERNAL_ERROR', 'message' => 'Failed to update job vacancy'];
            return false;
        }
    }

    /**
     * Delete a job vacancy.
     */
    public function delete(int $id, int $adminId): bool
    {
        $this->errors = [];

        $vacancy = $this->vacancy->newQuery()->find($id);
        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return false;
        }

        // Check ownership or admin
        if ((int) $vacancy->user_id !== $adminId) {
            $user = User::where('id', $adminId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'You can only delete your own job vacancies'];
                return false;
            }
        }

        try {
            // Delete applications first
            JobApplication::where('vacancy_id', $id)->delete();
            $vacancy->delete();
            return true;
        } catch (\Throwable $e) {
            Log::error('JobVacancyService::delete failed: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_INTERNAL_ERROR', 'message' => 'Failed to delete job vacancy'];
            return false;
        }
    }

    /**
     * Apply to a job vacancy.
     *
     * Accepts optional cv_path, cv_filename, cv_size for CV file upload support.
     *
     * @return int|null Application ID or null if already applied.
     */
    public function apply(int $jobId, int $userId, array $data = []): ?int
    {
        $this->errors = [];

        // Verify vacancy exists and is open
        $vacancy = $this->vacancy->newQuery()->find($jobId);
        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return null;
        }
        if ($vacancy->status === 'closed' || $vacancy->status === 'filled') {
            $this->errors[] = ['code' => 'VACANCY_CLOSED', 'message' => 'This vacancy is no longer accepting applications'];
            return null;
        }

        $exists = JobApplication::where('vacancy_id', $jobId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            $this->errors[] = ['code' => 'RESOURCE_CONFLICT', 'message' => 'You have already applied to this vacancy'];
            return null;
        }

        $application = JobApplication::create([
            'vacancy_id' => $jobId,
            'user_id' => $userId,
            'message' => $data['cover_letter'] ?? null,
            'cv_path' => $data['cv_path'] ?? null,
            'cv_filename' => $data['cv_filename'] ?? null,
            'cv_size' => isset($data['cv_size']) ? (int) $data['cv_size'] : null,
            'status' => 'pending',
            'stage' => 'applied',
        ]);

        // Log initial application in history
        $this->logApplicationHistory($application->id, null, 'applied', $userId, 'Application submitted');

        // Increment applications count
        $this->vacancy->newQuery()
            ->where('id', $jobId)
            ->increment('applications_count');

        // Dispatch webhook for new application
        try {
            \App\Services\WebhookDispatchService::dispatch('job.application.created', [
                'application_id' => $application->id,
                'vacancy_id'     => $jobId,
                'user_id'        => $userId,
                'tenant_id'      => TenantContext::getId(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('JobVacancyService::apply webhook dispatch failed: ' . $e->getMessage());
        }

        return $application->id;
    }

    /**
     * Apply with message string (legacy signature).
     */
    public function legacyApply(int $jobId, int $userId, ?string $message = null): ?int
    {
        return $this->apply($jobId, $userId, ['cover_letter' => $message]);
    }

    /**
     * Increment view count for a vacancy.
     */
    public function incrementViews(int $id, ?int $userId = null): void
    {
        $tenantId = TenantContext::getId();

        try {
            $this->vacancy->newQuery()
                ->where('id', $id)
                ->increment('views_count');

            // Log individual view for analytics
            DB::table('job_vacancy_views')->insert([
                'vacancy_id' => $id,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'viewed_at' => now(),
                'ip_hash' => null,
            ]);
        } catch (\Throwable $e) {
            // Non-critical
            Log::warning('JobVacancyService::incrementViews failed: ' . $e->getMessage());
        }
    }

    /**
     * Feature a job vacancy (admin only).
     */
    public function featureJob(int $id, int $adminId, int $days = 7): bool
    {
        $this->errors = [];

        $days = max(1, min(90, $days));

        $user = User::where('id', $adminId)->first(['id', 'role']);
        if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
            $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'Only admins can feature jobs'];
            return false;
        }

        $job = $this->vacancy->newQuery()->find($id);
        if (!$job) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return false;
        }

        try {
            $job->update([
                'is_featured' => true,
                'featured_until' => now()->addDays($days),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('JobVacancyService::featureJob failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unfeature a job vacancy (admin only).
     */
    public function unfeatureJob(int $id, int $adminId): bool
    {
        $this->errors = [];

        $user = User::where('id', $adminId)->first(['id', 'role']);
        if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
            $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'Only admins can unfeature jobs'];
            return false;
        }

        try {
            $this->vacancy->newQuery()
                ->where('id', $id)
                ->update(['is_featured' => false, 'featured_until' => null]);
            return true;
        } catch (\Throwable $e) {
            Log::error('JobVacancyService::unfeatureJob failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get applications for a vacancy (owner/admin only).
     */
    public function getApplications(int $jobId, int $adminId): ?array
    {
        $this->errors = [];

        $vacancy = $this->vacancy->newQuery()->find($jobId);
        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return null;
        }

        // Check ownership or admin
        if ((int) $vacancy->user_id !== $adminId) {
            $user = User::where('id', $adminId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'Only the vacancy owner can view applications'];
                return null;
            }
        }

        $isBlindHiring = (bool) ($vacancy->blind_hiring ?? false);

        $applications = JobApplication::with(['applicant:id,first_name,last_name,avatar_url,email'])
            ->where('vacancy_id', $jobId)
            ->orderByDesc('created_at')
            ->get();

        $candidateNumber = 0;

        return $applications
            ->map(function ($app) use ($isBlindHiring, &$candidateNumber) {
                $data = $app->toArray();
                $candidateNumber++;

                if ($isBlindHiring) {
                    // Anonymize: strip names, avatars, emails (Agent C)
                    $data['applicant'] = [
                        'id' => (int) $app->user_id,
                        'name' => 'Candidate #' . $candidateNumber,
                        'avatar_url' => null,
                        'email' => null,
                    ];
                } else {
                    $data['applicant'] = [
                        'id' => (int) $app->user_id,
                        'name' => $app->applicant ? trim(($app->applicant->first_name ?? '') . ' ' . ($app->applicant->last_name ?? '')) : null,
                        'avatar_url' => $app->applicant->avatar_url ?? null,
                        'email' => $app->applicant->email ?? null,
                    ];
                }

                return $data;
            })
            ->all();
    }

    /**
     * Update an application status/stage.
     */
    public function updateApplicationStatus(int $applicationId, int $adminId, string $status, ?string $notes = null): bool
    {
        $this->errors = [];

        $validStatuses = ['applied', 'pending', 'screening', 'reviewed', 'shortlisted', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn'];
        if (!in_array($status, $validStatuses)) {
            $this->errors[] = ['code' => 'VALIDATION_INVALID_VALUE', 'message' => 'Invalid application status'];
            return false;
        }

        $application = JobApplication::with(['vacancy'])->find($applicationId);
        if (!$application) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Application not found'];
            return false;
        }

        // Must be tenant-scoped
        $tenantId = TenantContext::getId();
        if (!$application->vacancy || (int) $application->vacancy->tenant_id !== $tenantId) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Application not found'];
            return false;
        }

        // Check vacancy ownership or admin
        if ((int) $application->vacancy->user_id !== $adminId) {
            $user = User::where('id', $adminId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'Only the vacancy owner can update applications'];
                return false;
            }
        }

        $previousStatus = $application->stage ?? $application->status ?? 'applied';

        try {
            $application->update([
                'status' => $status,
                'stage' => $status,
                'reviewer_notes' => $notes ? trim($notes) : null,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            $this->logApplicationHistory($applicationId, $previousStatus, $status, $adminId, $notes);

            // Dispatch webhook for application status change
            try {
                \App\Services\WebhookDispatchService::dispatch('job.application.status_changed', [
                    'application_id' => $applicationId,
                    'vacancy_id'     => $application->vacancy_id,
                    'user_id'        => $application->user_id,
                    'from'           => $previousStatus,
                    'to'             => $status,
                    'tenant_id'      => TenantContext::getId(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('JobVacancyService::updateApplicationStatus webhook dispatch failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('JobVacancyService::updateApplicationStatus failed: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_INTERNAL_ERROR', 'message' => 'Failed to update application'];
            return false;
        }
    }

    // =========================================================================
    // SAVED JOBS
    // =========================================================================

    /**
     * Save (bookmark) a job.
     */
    public function saveJob(int $id, int $userId): bool
    {
        $this->errors = [];

        $job = $this->vacancy->newQuery()->find($id);
        if (!$job) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return false;
        }

        $existing = SavedJob::where('job_id', $id)->where('user_id', $userId)->exists();
        if ($existing) {
            return true; // Idempotent
        }

        try {
            SavedJob::create([
                'user_id' => $userId,
                'job_id' => $id,
                'saved_at' => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('JobVacancyService::saveJob failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unsave (remove bookmark) a job.
     */
    public function unsaveJob(int $id, int $userId): void
    {
        SavedJob::where('job_id', $id)->where('user_id', $userId)->delete();
    }

    /**
     * Get saved jobs for a user.
     */
    public function getSavedJobs(int $userId, array $filters = []): array
    {
        $limit = (int) ($filters['limit'] ?? 20);
        $cursor = $filters['cursor'] ?? null;

        $query = SavedJob::where('saved_jobs.user_id', $userId)
            ->join('job_vacancies as jv', 'saved_jobs.job_id', '=', 'jv.id')
            ->leftJoin('users as u', 'jv.user_id', '=', 'u.id')
            ->leftJoin('organizations as o', 'jv.organization_id', '=', 'o.id')
            ->select(
                'jv.*',
                'u.first_name as creator_first_name',
                'u.last_name as creator_last_name',
                'u.avatar_url as creator_avatar',
                'o.name as organization_name',
                'o.logo_url as organization_logo',
                'saved_jobs.id as saved_id',
                'saved_jobs.saved_at'
            );

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('saved_jobs.id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('saved_jobs.saved_at')->orderByDesc('saved_jobs.id');

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows->pop();
        }

        $enriched = $rows->map(function ($row) use ($userId) {
            $data = $this->enrichVacancyArray((array) $row->getAttributes(), $userId);
            $data['saved_at'] = $row->saved_at;
            $data['is_saved'] = true;
            return $data;
        })->values()->all();

        return [
            'items' => $enriched,
            'cursor' => $hasMore && $rows->isNotEmpty() ? base64_encode((string) $rows->last()->saved_id) : null,
            'has_more' => $hasMore,
        ];
    }

    // =========================================================================
    // MY APPLICATIONS / MY POSTINGS
    // =========================================================================

    /**
     * Get user's own applications with vacancy info.
     */
    public function getMyApplications(int $userId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = (int) ($filters['limit'] ?? 20);
        $cursor = $filters['cursor'] ?? null;
        $status = $filters['status'] ?? null;

        $query = JobApplication::join('job_vacancies as jv', 'job_vacancy_applications.vacancy_id', '=', 'jv.id')
            ->where('job_vacancy_applications.user_id', $userId)
            ->where('jv.tenant_id', $tenantId)
            ->select(
                'job_vacancy_applications.*',
                'jv.title as vacancy_title',
                'jv.type as vacancy_type',
                'jv.commitment as vacancy_commitment',
                'jv.status as vacancy_status',
                'jv.location as vacancy_location',
                'jv.is_remote as vacancy_is_remote',
                'jv.deadline as vacancy_deadline'
            );

        if ($status && in_array($status, ['applied', 'pending', 'screening', 'reviewed', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn'])) {
            $query->where('job_vacancy_applications.status', $status);
        }

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('job_vacancy_applications.id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('job_vacancy_applications.created_at')
            ->orderByDesc('job_vacancy_applications.id');

        $applications = $query->limit($limit + 1)->get();
        $hasMore = $applications->count() > $limit;
        if ($hasMore) {
            $applications->pop();
        }

        $items = $applications->map(function ($app) {
            $data = $app->toArray();
            $data['id'] = (int) $data['id'];
            $data['vacancy_id'] = (int) $data['vacancy_id'];
            $data['user_id'] = (int) $data['user_id'];
            $data['vacancy'] = [
                'id' => (int) $data['vacancy_id'],
                'title' => $data['vacancy_title'] ?? null,
                'type' => $data['vacancy_type'] ?? null,
                'commitment' => $data['vacancy_commitment'] ?? null,
                'status' => $data['vacancy_status'] ?? null,
                'location' => $data['vacancy_location'] ?? null,
                'is_remote' => (bool) ($data['vacancy_is_remote'] ?? false),
                'deadline' => $data['vacancy_deadline'] ?? null,
            ];
            unset($data['vacancy_title'], $data['vacancy_type'], $data['vacancy_commitment'],
                  $data['vacancy_status'], $data['vacancy_location'], $data['vacancy_is_remote'],
                  $data['vacancy_deadline']);
            return $data;
        })->values()->all();

        return [
            'items' => $items,
            'cursor' => $hasMore && $applications->isNotEmpty() ? base64_encode((string) $applications->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get all job vacancies posted by a specific user.
     */
    public function getMyPostings(int $userId, int $_tenantId, array $params = []): array
    {
        $limit = min((int) ($params['limit'] ?? 20), 50);
        $cursor = $params['cursor'] ?? null;

        // $_tenantId kept for API compatibility; tenant scoping is via HasTenantScope
        $query = $this->vacancy->newQuery()
            ->leftJoin('organizations as o', 'job_vacancies.organization_id', '=', 'o.id')
            ->select('job_vacancies.*', 'o.name as organization_name', 'o.logo_url as organization_logo')
            ->where('job_vacancies.user_id', $userId);

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('job_vacancies.id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('job_vacancies.created_at')->orderByDesc('job_vacancies.id');

        $vacancies = $query->limit($limit + 1)->get();
        $hasMore = $vacancies->count() > $limit;
        if ($hasMore) {
            $vacancies->pop();
        }

        $enriched = $vacancies->map(fn ($v) => $this->enrichVacancy($v, $userId))->values()->all();

        return [
            'items' => $enriched,
            'cursor' => $hasMore && $vacancies->isNotEmpty() ? base64_encode((string) $vacancies->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    // =========================================================================
    // ALERTS
    // =========================================================================

    /**
     * Get alerts for a user.
     */
    public function getAlerts(int $userId): array
    {
        return JobAlert::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($alert) {
                $data = $alert->toArray();
                $data['id'] = (int) $data['id'];
                $data['user_id'] = (int) $data['user_id'];
                $data['is_active'] = (bool) $data['is_active'];
                $data['is_remote_only'] = (bool) $data['is_remote_only'];
                return $data;
            })
            ->all();
    }

    /**
     * Create a job alert subscription.
     */
    public function subscribeAlert(int $userId, array $data): ?int
    {
        $this->errors = [];

        try {
            $alert = JobAlert::create([
                'user_id' => $userId,
                'keywords' => isset($data['keywords']) ? (mb_substr(trim($data['keywords']), 0, 500) ?: null) : null,
                'categories' => isset($data['categories']) ? (mb_substr(trim($data['categories']), 0, 500) ?: null) : null,
                'type' => isset($data['type']) && in_array($data['type'], ['paid', 'volunteer', 'timebank']) ? $data['type'] : null,
                'commitment' => isset($data['commitment']) && in_array($data['commitment'], ['full_time', 'part_time', 'flexible', 'one_off']) ? $data['commitment'] : null,
                'location' => isset($data['location']) ? (mb_substr(trim($data['location']), 0, 500) ?: null) : null,
                'is_remote_only' => !empty($data['is_remote_only']),
                'is_active' => true,
                'created_at' => now(),
            ]);

            return $alert->id;
        } catch (\Throwable $e) {
            Log::error('JobVacancyService::subscribeAlert failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a job alert permanently.
     */
    public function deleteAlert(int $id, int $userId): void
    {
        JobAlert::where('id', $id)->where('user_id', $userId)->delete();
    }

    /**
     * Unsubscribe (deactivate) a job alert.
     */
    public function unsubscribeAlert(int $id, int $userId): void
    {
        JobAlert::where('id', $id)->where('user_id', $userId)->update(['is_active' => false]);
    }

    /**
     * Resubscribe (reactivate) a paused job alert.
     */
    public function resubscribeAlert(int $id, int $userId): void
    {
        JobAlert::where('id', $id)->where('user_id', $userId)->update(['is_active' => true]);
    }

    // =========================================================================
    // SKILLS MATCHING
    // =========================================================================

    /**
     * Calculate match percentage between a user's skills and a job's required skills.
     */
    public function calculateMatchPercentage(int $userId, int $jobId): array
    {
        $user = User::find($userId, ['id', 'skills']);
        $userSkills = [];
        if ($user && !empty($user->skills)) {
            $userSkills = array_filter(array_map(fn ($s) => strtolower(trim($s)), explode(',', $user->skills)));
        }

        $job = $this->vacancy->newQuery()->find($jobId, ['id', 'skills_required']);
        $requiredSkills = [];
        if ($job && !empty($job->skills_required)) {
            $requiredSkills = array_filter(array_map(fn ($s) => strtolower(trim($s)), explode(',', $job->skills_required)));
        }

        if (empty($requiredSkills)) {
            return ['percentage' => 100, 'matched' => [], 'missing' => [], 'user_skills' => $userSkills, 'required_skills' => $requiredSkills];
        }
        if (empty($userSkills)) {
            return ['percentage' => 0, 'matched' => [], 'missing' => $requiredSkills, 'user_skills' => $userSkills, 'required_skills' => $requiredSkills];
        }

        $matched = [];
        $missing = [];

        foreach ($requiredSkills as $required) {
            $isMatched = false;
            foreach ($userSkills as $userSkill) {
                if ($required === $userSkill || str_contains($required, $userSkill) || str_contains($userSkill, $required)) {
                    $matched[] = $required;
                    $isMatched = true;
                    break;
                }
                similar_text($required, $userSkill, $pct);
                if ($pct >= 75) {
                    $matched[] = $required;
                    $isMatched = true;
                    break;
                }
            }
            if (!$isMatched) {
                $missing[] = $required;
            }
        }

        return [
            'percentage' => (int) round((count($matched) / count($requiredSkills)) * 100),
            'matched' => $matched,
            'missing' => $missing,
            'user_skills' => $userSkills,
            'required_skills' => $requiredSkills,
        ];
    }

    /**
     * Get qualification assessment for a user against a job.
     */
    public function getQualificationAssessment(int $userId, int $jobId): ?array
    {
        $this->errors = [];

        $vacancy = $this->legacyGetById($jobId, $userId);
        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return null;
        }

        $matchData = $this->calculateMatchPercentage($userId, $jobId);

        $breakdown = array_map(fn ($skill) => [
            'skill' => $skill,
            'matched' => in_array($skill, $matchData['matched']),
        ], $matchData['required_skills']);

        $level = 'low';
        if ($matchData['percentage'] >= 80) {
            $level = 'excellent';
        } elseif ($matchData['percentage'] >= 60) {
            $level = 'good';
        } elseif ($matchData['percentage'] >= 40) {
            $level = 'moderate';
        }

        // Commitment match
        $commitmentScore = in_array($vacancy['commitment'] ?? '', ['flexible', 'part_time']) ? 80 : 60;
        $commitmentNotes = ucfirst(str_replace('_', ' ', $vacancy['commitment'] ?? ''));

        // Remote match
        $isRemote = !empty($vacancy['is_remote']);

        // Location distance (Haversine)
        $locationDistanceKm = null;
        if (!empty($vacancy['latitude']) && !empty($vacancy['longitude'])) {
            $user = User::where('id', $userId)->first(['latitude', 'longitude']);
            if ($user && !empty($user->latitude) && !empty($user->longitude)) {
                $earthRadius = 6371;
                $latDiff = deg2rad((float) $vacancy['latitude'] - (float) $user->latitude);
                $lngDiff = deg2rad((float) $vacancy['longitude'] - (float) $user->longitude);
                $a = sin($latDiff / 2) ** 2
                    + cos(deg2rad((float) $user->latitude)) * cos(deg2rad((float) $vacancy['latitude'])) * sin($lngDiff / 2) ** 2;
                $locationDistanceKm = round($earthRadius * 2 * asin(sqrt($a)), 1);
            }
        }

        // Salary transparency
        $salaryDisclosed = !empty($vacancy['salary_min']) || !empty($vacancy['salary_negotiable']);

        return [
            'job_id' => $jobId,
            'job_title' => $vacancy['title'],
            'percentage' => $matchData['percentage'],
            'level' => $level,
            'total_required' => count($matchData['required_skills']),
            'total_matched' => count($matchData['matched']),
            'total_missing' => count($matchData['missing']),
            'breakdown' => $breakdown,
            'matched_skills' => $matchData['matched'],
            'missing_skills' => $matchData['missing'],
            'user_skills' => $matchData['user_skills'],
            'commitment_notes' => $commitmentNotes,
            'remote_available' => $isRemote,
            'location_distance_km' => $locationDistanceKm,
            'salary_disclosed' => $salaryDisclosed,
            'dimensions' => [
                ['label' => 'Skills Match',    'score' => $matchData['percentage'],   'detail' => count($matchData['matched']) . '/' . count($matchData['required_skills']) . ' skills matched'],
                ['label' => 'Remote Work',     'score' => $isRemote ? 100 : 50,       'detail' => $isRemote ? 'Remote position available' : 'On-site role'],
                ['label' => 'Commitment',      'score' => $commitmentScore,           'detail' => $commitmentNotes],
                ['label' => 'Pay Transparency','score' => $salaryDisclosed ? 100 : 50,'detail' => !empty($vacancy['salary_min']) ? 'Salary range disclosed' : 'Salary not specified'],
            ],
            'ai_summary' => self::generateMatchSummary($matchData['percentage'], $matchData['matched'], $matchData['missing'], $vacancy),
        ];
    }

    /**
     * Generate a concise human-readable match summary.
     */
    private static function generateMatchSummary(int $pct, array $matched, array $missing, array $vacancy): string
    {
        $lines = [];
        if ($pct >= 80) {
            $lines[] = "Strong match ({$pct}%).";
        } elseif ($pct >= 50) {
            $lines[] = "Partial match ({$pct}%).";
        } else {
            $lines[] = "Developing match ({$pct}%).";
        }
        if (!empty($matched)) {
            $lines[] = 'You have: ' . implode(', ', array_slice($matched, 0, 5)) . '.';
        }
        if (!empty($missing)) {
            $lines[] = 'To develop: ' . implode(', ', array_slice($missing, 0, 3)) . '.';
        }
        if ($vacancy['is_remote'] ?? false) {
            $lines[] = 'Remote-friendly role.';
        }
        return implode(' ', $lines);
    }

    // =========================================================================
    // APPLICATION HISTORY
    // =========================================================================

    /**
     * Get status history for an application.
     */
    public function getApplicationHistory(int $applicationId, int $userId): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $application = JobApplication::with(['vacancy'])->find($applicationId);
        if (!$application || !$application->vacancy || (int) $application->vacancy->tenant_id !== $tenantId) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Application not found'];
            return null;
        }

        // Must be applicant, vacancy owner, or admin
        $isApplicant = (int) $application->user_id === $userId;
        $isOwner = (int) $application->vacancy->user_id === $userId;

        if (!$isApplicant && !$isOwner) {
            $user = User::where('id', $userId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'Access denied'];
                return null;
            }
        }

        return JobApplicationHistory::with(['changer:id,first_name,last_name'])
            ->where('application_id', $applicationId)
            ->orderBy('changed_at')
            ->get()
            ->map(function ($entry) {
                $data = $entry->toArray();
                $data['id'] = (int) $data['id'];
                $data['application_id'] = (int) $data['application_id'];
                $data['changed_by_name'] = $entry->changer
                    ? trim(($entry->changer->first_name ?? '') . ' ' . ($entry->changer->last_name ?? ''))
                    : null;
                unset($data['changer']);
                return $data;
            })
            ->all();
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    /**
     * Get analytics for a job vacancy.
     */
    public function getAnalytics(int $jobId, int $userId): ?array
    {
        $this->errors = [];

        $vacancy = $this->legacyGetById($jobId);
        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return null;
        }

        $tenantId = TenantContext::getId();

        // Check ownership or admin
        if ((int) $vacancy['user_id'] !== $userId) {
            $user = User::where('id', $userId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'Access denied'];
                return null;
            }
        }

        $viewsByDay = DB::table('job_vacancy_views')
            ->where('vacancy_id', $jobId)
            ->where('tenant_id', $tenantId)
            ->where('viewed_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(viewed_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $uniqueViewers = (int) DB::table('job_vacancy_views')
            ->where('vacancy_id', $jobId)
            ->where('tenant_id', $tenantId)
            ->selectRaw('COUNT(DISTINCT COALESCE(user_id, ip_hash)) as count')
            ->value('count');

        $applicationsByStatus = DB::table('job_vacancy_applications as a')
            ->join('job_vacancies as jv', 'a.vacancy_id', '=', 'jv.id')
            ->where('jv.tenant_id', $tenantId)
            ->where('a.vacancy_id', $jobId)
            ->selectRaw('COALESCE(a.stage, a.status) as stage, COUNT(*) as count')
            ->groupByRaw('COALESCE(a.stage, a.status)')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $totalViews = (int) $vacancy['views_count'];
        $totalApps = (int) $vacancy['applications_count'];
        $conversionRate = $totalViews > 0 ? round(($totalApps / $totalViews) * 100, 1) : 0;

        $avgTimeToApply = DB::table('job_vacancy_applications as a')
            ->join('job_vacancies as jv', 'a.vacancy_id', '=', 'jv.id')
            ->where('a.vacancy_id', $jobId)
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, jv.created_at, a.created_at)) as avg_hours')
            ->value('avg_hours');

        $timeToFill = null;
        if ($vacancy['status'] === 'filled') {
            $acceptedAt = JobApplication::where('vacancy_id', $jobId)
                ->where('status', 'accepted')
                ->min('reviewed_at');
            if ($acceptedAt) {
                $timeToFill = (int) ((strtotime($acceptedAt) - strtotime($vacancy['created_at'])) / 86400);
            }
        }

        return [
            'job_id' => $jobId,
            'total_views' => $totalViews,
            'unique_viewers' => $uniqueViewers,
            'total_applications' => $totalApps,
            'conversion_rate' => $conversionRate,
            'avg_time_to_apply_hours' => $avgTimeToApply ? round((float) $avgTimeToApply, 1) : null,
            'time_to_fill_days' => $timeToFill,
            'views_by_day' => $viewsByDay,
            'applications_by_stage' => $applicationsByStatus,
            'created_at' => $vacancy['created_at'],
            'status' => $vacancy['status'],
            'referral_stats' => self::getReferralStats((int) $jobId, $tenantId),
            'scorecard_avg'  => self::getScorecardAvg((int) $jobId, $tenantId),
            'weekly_trend'   => self::getWeeklyApplicationTrend((int) $jobId, $tenantId),
        ];
    }

    private static function getReferralStats(int $jobId, int $tenantId): array
    {
        try {
            $total   = DB::table('job_referrals')->where('tenant_id', $tenantId)->where('vacancy_id', $jobId)->count();
            $applied = DB::table('job_referrals')->where('tenant_id', $tenantId)->where('vacancy_id', $jobId)->where('applied', true)->count();
            return ['total_shares' => $total, 'referral_applications' => $applied, 'referral_conversion_pct' => $total > 0 ? round(($applied / $total) * 100, 1) : 0];
        } catch (\Throwable $e) {
            return ['total_shares' => 0, 'referral_applications' => 0, 'referral_conversion_pct' => 0];
        }
    }

    private static function getScorecardAvg(int $jobId, int $tenantId): ?float
    {
        try {
            $avg = DB::table('job_scorecards')
                ->where('tenant_id', $tenantId)
                ->where('vacancy_id', $jobId)
                ->avg(DB::raw('(total_score / max_score) * 100'));
            return $avg !== null ? round((float) $avg, 1) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function getWeeklyApplicationTrend(int $jobId, int $tenantId): array
    {
        try {
            return DB::table('job_vacancy_applications')
                ->where('vacancy_id', $jobId)
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subWeeks(8))
                ->selectRaw('YEARWEEK(created_at, 1) as week, COUNT(*) as count')
                ->groupBy('week')
                ->orderBy('week')
                ->get()
                ->map(fn($r) => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =========================================================================
    // JOB RENEWAL
    // =========================================================================

    /**
     * Renew a job vacancy (extend deadline).
     */
    public function renewJob(int $id, int $userId, int $days = 30): bool
    {
        $this->errors = [];

        $vacancy = $this->vacancy->newQuery()->find($id);
        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Job vacancy not found'];
            return false;
        }

        // Check ownership or admin
        if ((int) $vacancy->user_id !== $userId) {
            $user = User::where('id', $userId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'You can only renew your own job vacancies'];
                return false;
            }
        }

        try {
            $baseDate = ($vacancy->deadline && $vacancy->deadline->isFuture())
                ? $vacancy->deadline
                : now();
            $newDeadline = $baseDate->copy()->addDays($days);

            $vacancy->update([
                'deadline' => $newDeadline,
                'status' => 'open',
                'expired_at' => null,
                'renewed_at' => now(),
                'renewal_count' => ($vacancy->renewal_count ?? 0) + 1,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("JobVacancyService::renewJob failed: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // RECOMMENDED JOBS
    // =========================================================================

    /**
     * Get recommended job vacancies for a user based on skills matching.
     *
     * Recommends open jobs where skills_required overlaps with the user's skills.
     * Excludes jobs the user has already applied to, and jobs posted by the user.
     * Orders by match score descending, then by newest first.
     */
    public function getRecommended(int $userId, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();
        $limit = max(1, min(20, $limit));

        // 1. Get the user's skills (comma-separated string on users.skills column)
        $user = User::find($userId, ['id', 'skills']);
        $userSkills = [];
        if ($user && !empty($user->skills)) {
            $userSkills = array_values(array_filter(array_map(fn ($s) => strtolower(trim($s)), explode(',', $user->skills))));
        }

        // 2. Get open vacancies in this tenant, excluding applied-to and own postings
        $appliedIds = JobApplication::where('user_id', $userId)
            ->pluck('vacancy_id')
            ->toArray();

        $query = $this->vacancy->newQuery()
            ->leftJoin('organizations as o', 'job_vacancies.organization_id', '=', 'o.id')
            ->select('job_vacancies.*', 'o.name as organization_name', 'o.logo_url as organization_logo')
            ->where('job_vacancies.status', 'open')
            ->where('job_vacancies.user_id', '!=', $userId);

        if (!empty($appliedIds)) {
            $query->whereNotIn('job_vacancies.id', $appliedIds);
        }

        $vacancies = $query->orderByRaw(
            '(CASE WHEN job_vacancies.is_featured = 1 AND (job_vacancies.featured_until IS NULL OR job_vacancies.featured_until > NOW()) THEN 0 ELSE 1 END) ASC'
        )
            ->orderByDesc('job_vacancies.created_at')
            ->limit(200) // Fetch a wider set for scoring, then trim to $limit
            ->get();

        if ($vacancies->isEmpty()) {
            return [];
        }

        // 3. Score each vacancy by skill overlap, using same fuzzy matching as calculateMatchPercentage()
        $scored = $vacancies->map(function ($vacancy) use ($userId, $userSkills) {
            $data = $this->enrichVacancy($vacancy, $userId);

            $requiredSkills = [];
            if (!empty($vacancy->skills_required)) {
                $requiredSkills = array_values(array_filter(array_map(fn ($s) => strtolower(trim($s)), explode(',', $vacancy->skills_required))));
            }

            $score = 0;
            if (!empty($requiredSkills) && !empty($userSkills)) {
                $matched = 0;
                foreach ($requiredSkills as $required) {
                    foreach ($userSkills as $userSkill) {
                        if ($required === $userSkill || str_contains($required, $userSkill) || str_contains($userSkill, $required)) {
                            $matched++;
                            break;
                        }
                        similar_text($required, $userSkill, $pct);
                        if ($pct >= 75) {
                            $matched++;
                            break;
                        }
                    }
                }
                $score = (int) round(($matched / count($requiredSkills)) * 100);
            } elseif (empty($requiredSkills)) {
                // No required skills — neutral score so it can still appear
                $score = 50;
            }

            $data['match_score'] = $score;
            return $data;
        });

        // 4. Sort by match_score DESC (jobs with higher skill overlap first)
        $sorted = $scored->sortByDesc('match_score')->values()->take($limit)->all();

        return $sorted;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Enrich a vacancy model/row with creator info, skills array, and application status.
     */
    private function enrichVacancy($vacancy, ?int $userId = null): array
    {
        $data = is_array($vacancy) ? $vacancy : $vacancy->toArray();
        return $this->enrichVacancyArray($data, $userId);
    }

    /**
     * Enrich a vacancy array with formatted fields.
     */
    private function enrichVacancyArray(array $data, ?int $userId = null): array
    {
        // Format creator info
        $data['creator'] = [
            'id' => (int) ($data['user_id'] ?? 0),
            'name' => trim(($data['creator_first_name'] ?? $data['creator']['first_name'] ?? '') . ' ' . ($data['creator_last_name'] ?? $data['creator']['last_name'] ?? '')),
            'avatar_url' => $data['creator_avatar'] ?? $data['creator']['avatar_url'] ?? null,
        ];

        // Format organization info
        if (!empty($data['organization_id'])) {
            $data['organization'] = [
                'id' => (int) $data['organization_id'],
                'name' => $data['organization_name'] ?? null,
                'logo_url' => $data['organization_logo'] ?? null,
            ];
        } else {
            $data['organization'] = null;
        }

        // Parse skills as array
        $data['skills'] = !empty($data['skills_required'])
            ? array_map('trim', explode(',', $data['skills_required']))
            : [];

        // Cast numeric fields
        $data['id'] = (int) ($data['id'] ?? 0);
        $data['tenant_id'] = (int) ($data['tenant_id'] ?? 0);
        $data['user_id'] = (int) ($data['user_id'] ?? 0);
        $data['views_count'] = (int) ($data['views_count'] ?? 0);
        $data['applications_count'] = (int) ($data['applications_count'] ?? 0);
        $data['is_remote'] = (bool) ($data['is_remote'] ?? false);
        $data['is_featured'] = (bool) ($data['is_featured'] ?? false);
        $data['salary_negotiable'] = (bool) ($data['salary_negotiable'] ?? false);
        $data['renewal_count'] = (int) ($data['renewal_count'] ?? 0);
        $data['blind_hiring'] = (bool) ($data['blind_hiring'] ?? false);

        // Auto-expire featured status
        if ($data['is_featured'] && !empty($data['featured_until']) && strtotime($data['featured_until']) < time()) {
            $data['is_featured'] = false;
        }

        // Check if user has applied / saved
        if ($userId) {
            $application = DB::table('job_vacancy_applications as jva')
                ->join('job_vacancies as jv', 'jva.vacancy_id', '=', 'jv.id')
                ->where('jv.tenant_id', $data['tenant_id'])
                ->where('jva.vacancy_id', $data['id'])
                ->where('jva.user_id', $userId)
                ->select('jva.id', 'jva.status', 'jva.stage')
                ->first();

            $data['has_applied'] = !empty($application);
            $data['application_status'] = $application->status ?? null;
            $data['application_stage'] = $application->stage ?? $application->status ?? null;

            $data['is_saved'] = SavedJob::where('job_id', $data['id'])
                ->where('user_id', $userId)
                ->exists();
        } else {
            $data['has_applied'] = false;
            $data['application_status'] = null;
            $data['application_stage'] = null;
            $data['is_saved'] = false;
        }

        // Clean up redundant fields
        unset(
            $data['creator_first_name'],
            $data['creator_last_name'],
            $data['creator_avatar'],
            $data['organization_name'],
            $data['organization_logo'],
            $data['saved_id']
        );

        return $data;
    }

    /**
     * Log an application status change to history.
     */
    private function logApplicationHistory(int $applicationId, ?string $fromStatus, string $toStatus, int $changedBy, ?string $notes = null): void
    {
        try {
            JobApplicationHistory::create([
                'tenant_id' => TenantContext::getId(),
                'application_id' => $applicationId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $changedBy,
                'changed_at' => now(),
                'notes' => $notes ? trim($notes) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('JobVacancyService: Failed to log application history: ' . $e->getMessage());
        }
    }

    /**
     * Parse a boolean search query into LIKE clauses.
     * Supports AND (space / +), OR (|), NOT (-word).
     * Returns ['must' => [], 'should' => [], 'not' => []]
     */
    private static function parseBooleanQuery(string $query): array
    {
        $must   = [];
        $should = [];
        $not    = [];

        // Split on | for OR groups
        $orParts = array_map('trim', explode('|', $query));

        foreach ($orParts as $part) {
            // Split on spaces/+ for AND terms within this OR group
            $terms = preg_split('/[\s+]+/', trim($part), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($terms as $term) {
                if (str_starts_with($term, '-') && strlen($term) > 1) {
                    $not[] = substr($term, 1);
                } elseif (count($orParts) > 1) {
                    $should[] = $term;
                } else {
                    $must[] = $term;
                }
            }
        }

        return ['must' => $must, 'should' => $should, 'not' => $not];
    }

    // =========================================================================
    // CSV EXPORT
    // =========================================================================

    /**
     * Export applications for a vacancy as CSV string.
     */
    public function exportApplicationsCsv(int $jobId, int $userId): ?string
    {
        $this->errors = [];
        $vacancy = $this->legacyGetById($jobId);
        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Not found'];
            return null;
        }
        $tenantId = TenantContext::getId();
        if ((int) $vacancy['user_id'] !== $userId) {
            $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'Access denied'];
            return null;
        }

        $apps = JobApplication::with(['applicant:id,first_name,last_name,email'])
            ->where('tenant_id', $tenantId)
            ->where('vacancy_id', $jobId)
            ->orderBy('created_at')
            ->get();

        $rows = [['ID', 'Name', 'Email', 'Status', 'Stage', 'Applied At', 'Updated At']];
        foreach ($apps as $app) {
            $rows[] = [
                $app->id,
                ($app->applicant->first_name ?? '') . ' ' . ($app->applicant->last_name ?? ''),
                $app->applicant->email ?? '',
                $app->status,
                $app->stage ?? $app->status,
                $app->created_at?->toDateTimeString() ?? '',
                $app->updated_at?->toDateTimeString() ?? '',
            ];
        }

        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    /**
     * Bulk update application statuses (employer only).
     *
     * @param int   $vacancyId
     * @param int   $userId       Must be the vacancy owner.
     * @param int[] $applicationIds
     * @param string $newStatus   e.g. 'rejected', 'screening', 'reviewed'
     * @return int  Number of records updated.
     */
    public function bulkUpdateApplicationStatus(int $vacancyId, int $userId, array $applicationIds, string $newStatus): int
    {
        $this->errors = [];
        $tenantId     = TenantContext::getId();

        $vacancy = $this->legacyGetById($vacancyId);
        if (!$vacancy) {
            $this->errors[] = ['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Not found'];
            return 0;
        }
        if ((int) $vacancy['user_id'] !== $userId) {
            $this->errors[] = ['code' => 'RESOURCE_FORBIDDEN', 'message' => 'Access denied'];
            return 0;
        }

        $allowed = ['applied','screening','reviewed','interview','offer','accepted','rejected','withdrawn'];
        if (!in_array($newStatus, $allowed, true)) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid status'];
            return 0;
        }

        try {
            $updated = JobApplication::where('tenant_id', $tenantId)
                ->where('vacancy_id', $vacancyId)
                ->whereIn('id', $applicationIds)
                ->update(['status' => $newStatus, 'stage' => $newStatus]);

            // Fire webhook for bulk action
            try {
                \App\Services\WebhookDispatchService::dispatch('job.application.bulk_status_changed', [
                    'vacancy_id'      => $vacancyId,
                    'application_ids' => $applicationIds,
                    'new_status'      => $newStatus,
                    'tenant_id'       => $tenantId,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Bulk status webhook failed: ' . $e->getMessage());
            }

            return $updated;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('bulkUpdateApplicationStatus failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    // =====================================================================
    // DUPLICATE DETECTION (Agent A)
    // =====================================================================

    /**
     * Find similar job vacancies based on title word matching.
     *
     * Compares individual words from the input title against open/draft
     * job vacancy titles within the same tenant (and optionally organization).
     * Returns matches sorted by similarity score (descending).
     *
     * @return array<int, array{id: int, title: string, status: string, similarity: float, created_at: string}>
     */
    public function findSimilarJobs(string $title, ?int $orgId, int $tenantId): array
    {
        // Extract meaningful words (3+ chars, lowercased)
        $words = array_filter(
            array_map('strtolower', preg_split('/\s+/', trim($title))),
            fn (string $w) => strlen($w) >= 3
        );

        if (empty($words)) {
            return [];
        }

        // Build query for open/draft jobs in the same tenant
        $query = DB::table('job_vacancies')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'draft'])
            ->select(['id', 'title', 'status', 'created_at']);

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        // Filter to rows that match at least one word
        $query->where(function ($q) use ($words) {
            foreach ($words as $word) {
                $q->orWhere('title', 'LIKE', '%' . $word . '%');
            }
        });

        $candidates = $query->orderByDesc('created_at')->limit(20)->get();

        $results = [];
        $totalWords = count($words);

        foreach ($candidates as $row) {
            $candidateTitle = strtolower($row->title);
            $matchCount = 0;

            foreach ($words as $word) {
                if (str_contains($candidateTitle, $word)) {
                    $matchCount++;
                }
            }

            $similarity = round($matchCount / $totalWords, 2);

            // Only include results with >= 40% word overlap
            if ($similarity >= 0.4) {
                $results[] = [
                    'id' => $row->id,
                    'title' => $row->title,
                    'status' => $row->status,
                    'similarity' => $similarity,
                    'created_at' => $row->created_at,
                ];
            }
        }

        // Sort by similarity descending
        usort($results, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, 5);
    }
}
