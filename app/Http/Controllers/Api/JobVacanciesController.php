<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Services\AiChatService;
use App\Services\JobGdprService;
use App\Services\JobInterviewService;
use App\Services\JobOfferService;
use App\Services\JobReferralService;
use App\Services\JobSavedProfileService;
use App\Services\JobScorecardService;
use App\Services\JobTeamService;
use App\Services\JobPipelineRuleService;
use App\Services\JobVacancyService;
use App\Services\JobTemplateService;
use App\Services\SalaryBenchmarkService;
use App\Services\CandidateSearchService;
use App\Services\JobInterviewSchedulingService;
use App\Core\TenantContext;

/**
 * JobVacanciesController — Community job vacancy listings.
 *
 * Core CRUD uses Eloquent via JobVacancyService. All remaining endpoints
 * now call legacy JobVacancyService static methods directly instead of
 * ob_start() delegation.
 */
class JobVacanciesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly JobVacancyService $jobService,
        private readonly AiChatService $aiService,
        private readonly CandidateSearchService $candidateService,
        private readonly JobInterviewSchedulingService $schedulingService,
    ) {}

    /**
     * Ensure the job_vacancies feature is enabled.
     */
    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('job_vacancies')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'Job Vacancies module is not enabled for this community', null, 403)
            );
        }
    }

    // =====================================================================
    // CORE CRUD (Eloquent via JobVacancyService)
    // =====================================================================

    /** GET /api/v2/jobs — list jobs with filters + cursor pagination */
    public function index(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('jobs_list', 60, 60);

        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        $validStatuses = ['open', 'closed', 'filled', 'draft', 'expired', 'pending_review'];
        $validTypes = ['paid', 'volunteer', 'internship', 'timebank'];
        $validCommitments = ['full_time', 'part_time', 'one_off', 'flexible'];

        if ($this->query('status') && in_array($this->query('status'), $validStatuses, true)) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('type') && in_array($this->query('type'), $validTypes, true)) {
            $filters['type'] = $this->query('type');
        }
        if ($this->query('commitment') && in_array($this->query('commitment'), $validCommitments, true)) {
            $filters['commitment'] = $this->query('commitment');
        }
        if ($this->query('category')) {
            $filters['category'] = $this->query('category');
        }
        if ($this->query('search')) {
            $filters['search'] = $this->query('search');
        }
        if ($this->query('user_id')) {
            $filters['user_id'] = $this->queryInt('user_id');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($this->query('featured')) {
            $filters['featured'] = $this->queryBool('featured');
        }
        if ($this->query('latitude') !== null) {
            $filters['latitude'] = (float) $this->query('latitude');
        }
        if ($this->query('longitude') !== null) {
            $filters['longitude'] = (float) $this->query('longitude');
        }
        if ($this->query('radius_km') !== null) {
            $filters['radius_km'] = (float) $this->query('radius_km');
        }
        if ($this->query('is_remote')) {
            $filters['is_remote'] = true;
        }
        if ($this->query('organization_id')) {
            $filters['organization_id'] = $this->queryInt('organization_id');
        }
        if ($this->query('exclude')) {
            $filters['exclude'] = $this->queryInt('exclude');
        }

        $validSorts = ['newest', 'deadline', 'salary_desc'];
        if ($this->query('sort') && in_array($this->query('sort'), $validSorts, true)) {
            $filters['sort'] = $this->query('sort');
        }

        $result = $this->jobService->getAll($filters, $userId);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /** GET /api/v2/jobs/{id} — single job vacancy */
    public function show($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('jobs_show', 60, 60);

        $userId = $this->getOptionalUserId();

        $job = $this->jobService->legacyGetById($id, $userId);

        if (!$job) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Job vacancy not found', null, 404);
        }

        // Increment views
        $this->jobService->incrementViews($id, $userId);

        return $this->respondWithData($job);
    }

    /** POST /api/v2/jobs — create a new job vacancy */
    public function store(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_create', 5, 60);

        $data = $this->getAllInput();

        // Validate required fields before calling service
        $title = trim($data['title'] ?? '');
        if (empty($title)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Title is required', 'title', 422);
        }

        try {
            $jobId = $this->jobService->create($userId, $data);
        } catch (\Throwable $e) {
            return $this->respondWithErrors([['code' => 'SERVER_INTERNAL_ERROR', 'message' => 'Failed to create job vacancy']], 500);
        }

        if (!$jobId) {
            $errors = $this->jobService->getErrors();
            return $this->respondWithErrors($errors ?: [['code' => 'SERVER_INTERNAL_ERROR', 'message' => 'Failed to create job vacancy']], 422);
        }

        $job = $this->jobService->getById($jobId);

        // Include moderation notice if job was flagged or requires review (Agent B)
        $meta = null;
        if ($job && isset($job['moderation_status'])) {
            if ($job['moderation_status'] === 'pending_review') {
                $meta = ['notice' => 'Your job posting has been submitted for review and will be published once approved.'];
            } elseif ($job['moderation_status'] === 'rejected') {
                $meta = ['notice' => 'Your job posting could not be published. Please contact support for more information.'];
            }
        }

        return $this->respondWithData($job, $meta, 201);
    }

    /** PUT /api/v2/jobs/{id} — update a job vacancy */
    public function update($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_update', 10, 60);

        $data = $this->getAllInput();

        $success = $this->jobService->update((int) $id, $userId, $data);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            return $this->respondWithErrors($errors, $status);
        }

        $vacancy = $this->jobService->legacyGetById((int) $id, $userId);

        return $this->respondWithData($vacancy);
    }

    /** DELETE /api/v2/jobs/{id} — delete a job vacancy */
    public function destroy($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_delete', 5, 60);

        $success = $this->jobService->delete((int) $id, $userId);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            return $this->respondWithErrors($errors, $status);
        }

        return $this->noContent();
    }

    /** POST /api/v2/jobs/{id}/apply — apply to a job (supports CV file upload via multipart) */
    public function apply(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_apply', 5, 60);

        $tenantId = TenantContext::getId();

        $cvPath = null;
        $cvFilename = null;
        $cvSize = null;

        if (request()->hasFile('cv')) {
            $file = request()->file('cv');
            $allowed = ['pdf', 'doc', 'docx'];
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, $allowed)) {
                return $this->respondWithError('VALIDATION_INVALID_VALUE', 'Invalid file type. Allowed: PDF, DOC, DOCX', 'cv', 422);
            }
            if ($file->getSize() > 5 * 1024 * 1024) {
                return $this->respondWithError('VALIDATION_FILE_TOO_LARGE', 'File too large. Maximum 5MB', 'cv', 422);
            }
            $cvPath = $file->store("job-applications/{$tenantId}", 'local');
            $cvFilename = $file->getClientOriginalName();
            $cvSize = $file->getSize();
        }

        $message = $this->input('message');

        $applicationId = $this->jobService->apply($id, $userId, [
            'cover_letter' => $message,
            'cv_path' => $cvPath,
            'cv_filename' => $cvFilename,
            'cv_size' => $cvSize,
        ]);

        if ($applicationId === null) {
            $errors = $this->jobService->getErrors();
            $status = 400;

            if (empty($errors)) {
                // apply() returns null when already applied (idempotency)
                return $this->respondWithError('RESOURCE_CONFLICT', 'You have already applied to this vacancy', null, 409);
            }

            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_CONFLICT') {
                    $status = 409;
                    break;
                }
            }

            return $this->respondWithErrors($errors, $status);
        }

        $vacancy = $this->jobService->legacyGetById($id, $userId);

        return $this->respondWithData($vacancy, null, 201);
    }

    /** GET /api/v2/jobs/applications/{id}/cv — download CV for an application */
    public function downloadCv(int $applicationId): Response|JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_cv_download', 20, 60);

        $tenantId = TenantContext::getId();

        $application = JobApplication::with(['vacancy'])->find($applicationId);

        if (!$application || !$application->vacancy || (int) $application->vacancy->tenant_id !== $tenantId) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Application not found', null, 404);
        }

        // Only allow: the applicant themselves, the job poster, or an admin
        $isApplicant = (int) $application->user_id === $userId;
        $isPoster = $application->vacancy && (int) $application->vacancy->user_id === $userId;

        if (!$isApplicant && !$isPoster) {
            $user = \App\Models\User::where('id', $userId)->first(['id', 'role']);
            $isAdmin = $user && in_array($user->role, ['admin', 'super_admin', 'tenant_admin']);
            if (!$isAdmin) {
                return $this->respondWithError('RESOURCE_FORBIDDEN', 'Access denied', null, 403);
            }
        }

        if (empty($application->cv_path)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'No CV attached to this application', null, 404);
        }

        if (!Storage::disk('local')->exists($application->cv_path)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'CV file not found', null, 404);
        }

        $filename = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $application->cv_filename ?? basename($application->cv_path));

        return response()->download(
            Storage::disk('local')->path($application->cv_path),
            $filename
        );
    }

    /** GET /api/v2/jobs/recommended — recommended jobs for the authenticated user */
    public function recommended(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_recommended', 30, 60);

        $limit = min((int) ($this->query('limit', 10)), 20);
        $jobs = $this->jobService->getRecommended($userId, $limit);

        return $this->respondWithData(['data' => $jobs]);
    }

    // =====================================================================
    // SAVED JOBS (J1)
    // =====================================================================

    /** GET /api/v2/jobs/saved */
    public function savedJobs(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_saved_list', 30, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->jobService->getSavedJobs($userId, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /** POST /api/v2/jobs/{id}/save */
    public function saveJob($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_save', 30, 60);

        $success = $this->jobService->saveJob((int) $id, $userId);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        return $this->respondWithData(['message' => 'Job saved successfully', 'is_saved' => true], null, 201);
    }

    /** DELETE /api/v2/jobs/{id}/save */
    public function unsaveJob($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_unsave', 30, 60);

        $this->jobService->unsaveJob((int) $id, $userId);

        return $this->respondWithData(['message' => 'Job removed from saved', 'is_saved' => false]);
    }

    // =====================================================================
    // MY APPLICATIONS & MY POSTINGS
    // =====================================================================

    /** GET /api/v2/jobs/my-applications */
    public function myApplications(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_my_apps', 30, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->jobService->getMyApplications($userId, $filters);

        return $this->respondWithData([
            'items' => $result['items'],
            'cursor' => $result['cursor'],
            'has_more' => $result['has_more'],
        ]);
    }

    /** GET /api/v2/jobs/my-postings */
    public function myPostings(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_my_postings', 30, 60);

        $tenantId = TenantContext::getId();

        $params = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('cursor')) {
            $params['cursor'] = $this->query('cursor');
        }

        $result = $this->jobService->getMyPostings($userId, $tenantId, $params);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $params['limit'],
            $result['has_more']
        );
    }

    // =====================================================================
    // JOB ALERTS (J6)
    // =====================================================================

    /** GET /api/v2/jobs/alerts */
    public function listAlerts(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_list', 30, 60);

        $alerts = $this->jobService->getAlerts($userId);

        return $this->respondWithData($alerts);
    }

    /** POST /api/v2/jobs/alerts */
    public function createAlert(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_create', 5, 60);

        $data = $this->getAllInput();

        $alertId = $this->jobService->subscribeAlert($userId, $data);

        if ($alertId === null) {
            $errors = $this->jobService->getErrors();
            return $this->respondWithErrors($errors, 422);
        }

        return $this->respondWithData([
            'id' => $alertId,
            'message' => 'Job alert created successfully',
        ], null, 201);
    }

    /** DELETE /api/v2/jobs/alerts/{id} */
    public function deleteAlert($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_delete', 10, 60);

        $this->jobService->deleteAlert((int) $id, $userId);

        return $this->noContent();
    }

    /** PUT /api/v2/jobs/alerts/{id}/unsubscribe */
    public function unsubscribeAlert($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_unsub', 10, 60);

        $this->jobService->unsubscribeAlert((int) $id, $userId);

        return $this->respondWithData(['message' => 'Alert unsubscribed successfully']);
    }

    /** PUT /api/v2/jobs/alerts/{id}/resubscribe */
    public function resubscribeAlert($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_resub', 10, 60);

        $this->jobService->resubscribeAlert((int) $id, $userId);

        return $this->respondWithData(['message' => 'Alert resubscribed successfully']);
    }

    // =====================================================================
    // SKILLS MATCHING (J2) & QUALIFICATION (J5)
    // =====================================================================

    /** GET /api/v2/jobs/{id}/match */
    public function matchPercentage($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_match', 30, 60);

        $result = $this->jobService->calculateMatchPercentage($userId, (int) $id);

        return $this->respondWithData($result);
    }

    /** GET /api/v2/jobs/{id}/qualified */
    public function qualificationAssessment($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_qualified', 20, 60);

        $result = $this->jobService->getQualificationAssessment($userId, (int) $id);

        if ($result === null) {
            $errors = $this->jobService->getErrors();
            return $this->respondWithErrors($errors, 404);
        }

        return $this->respondWithData($result);
    }

    // =====================================================================
    // APPLICATIONS MANAGEMENT
    // =====================================================================

    /** GET /api/v2/jobs/{id}/applications */
    public function applications($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_applications', 30, 60);

        $applications = $this->jobService->getApplications((int) $id, $userId);

        if ($applications === null) {
            $errors = $this->jobService->getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($applications);
    }

    /** PUT /api/v2/jobs/applications/{id} */
    public function updateApplication($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_app_update', 10, 60);

        $status = $this->input('status');
        $notes = $this->input('notes');

        if (empty($status)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Status is required', 'status', 400);
        }

        $success = $this->jobService->updateApplicationStatus((int) $id, $userId, $status, $notes);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            $httpStatus = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $httpStatus = 403;
                    break;
                }
            }

            return $this->respondWithErrors($errors, $httpStatus);
        }

        return $this->respondWithData(['message' => 'Application updated successfully']);
    }

    /** GET /api/v2/jobs/applications/{id}/history */
    public function applicationHistory($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_app_history', 30, 60);

        $history = $this->jobService->getApplicationHistory((int) $id, $userId);

        if ($history === null) {
            $errors = $this->jobService->getErrors();
            $httpStatus = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $httpStatus = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $httpStatus);
        }

        return $this->respondWithData($history);
    }

    // =====================================================================
    // ANALYTICS & FEATURED (J8, J10)
    // =====================================================================

    /** GET /api/v2/jobs/{id}/analytics */
    public function analytics($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_analytics', 20, 60);

        $analytics = $this->jobService->getAnalytics((int) $id, $userId);

        if ($analytics === null) {
            $errors = $this->jobService->getErrors();
            $httpStatus = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $httpStatus = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $httpStatus);
        }

        return $this->respondWithData($analytics);
    }

    /** POST /api/v2/jobs/{id}/renew */
    public function renewJob($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_renew', 5, 60);

        $days = $this->input('days') ?? 30;
        $days = max(1, min(90, (int) $days));

        $success = $this->jobService->renewJob((int) $id, $userId, $days);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            $httpStatus = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $httpStatus = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $httpStatus);
        }

        $vacancy = $this->jobService->legacyGetById((int) $id, $userId);

        return $this->respondWithData($vacancy);
    }

    /** POST /api/v2/jobs/{id}/feature */
    public function featureJob($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAdmin();
        $this->rateLimit('jobs_feature', 10, 60);

        $days = $this->input('days') ?? 7;
        $days = max(1, min(30, (int) $days));

        $success = $this->jobService->featureJob((int) $id, $userId, $days);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            $httpStatus = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $httpStatus = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $httpStatus);
        }

        $vacancy = $this->jobService->legacyGetById((int) $id, $userId);

        return $this->respondWithData($vacancy);
    }

    /** DELETE /api/v2/jobs/{id}/feature */
    public function unfeatureJob($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAdmin();
        $this->rateLimit('jobs_unfeature', 10, 60);

        $success = $this->jobService->unfeatureJob((int) $id, $userId);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        $vacancy = $this->jobService->legacyGetById((int) $id, $userId);

        return $this->respondWithData($vacancy);
    }

    // =====================================================================
    // INTERVIEWS
    // =====================================================================

    /** POST /api/v2/jobs/applications/{id}/interview — propose an interview */
    public function proposeInterview(Request $request, int $applicationId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_interview_propose', 10, 60);

        $data = $this->getAllInput();

        if (empty($data['scheduled_at'])) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'scheduled_at is required', 'scheduled_at', 422);
        }

        $interview = JobInterviewService::propose($applicationId, $userId, $data);

        if ($interview === false) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Unable to propose interview. Check application ownership and data.', null, 422);
        }

        return $this->respondWithData($interview, null, 201);
    }

    /** PUT /api/v2/jobs/interviews/{id}/accept — candidate accepts an interview */
    public function acceptInterview(Request $request, int $interviewId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_interview_accept', 10, 60);

        $notes = $this->input('notes');

        $success = JobInterviewService::accept($interviewId, $userId, $notes);

        if (!$success) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Unable to accept interview. It may not exist or already been actioned.', null, 422);
        }

        return $this->respondWithData(['message' => 'Interview accepted successfully']);
    }

    /** PUT /api/v2/jobs/interviews/{id}/decline — candidate declines an interview */
    public function declineInterview(Request $request, int $interviewId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_interview_decline', 10, 60);

        $notes = $this->input('notes');

        $success = JobInterviewService::decline($interviewId, $userId, $notes);

        if (!$success) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Unable to decline interview. It may not exist or already been actioned.', null, 422);
        }

        return $this->respondWithData(['message' => 'Interview declined successfully']);
    }

    /** DELETE /api/v2/jobs/interviews/{id} — employer cancels an interview */
    public function cancelInterview(Request $request, int $interviewId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_interview_cancel', 10, 60);

        $success = JobInterviewService::cancel($interviewId, $userId);

        if (!$success) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Unable to cancel interview. It may not exist or already been completed.', null, 422);
        }

        return $this->noContent();
    }

    /** GET /api/v2/jobs/{id}/interviews — employer lists interviews for a vacancy */
    public function getInterviews(Request $request, int $vacancyId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_interviews_list', 30, 60);

        // Verify caller owns the vacancy or is admin
        $vacancy = \App\Models\JobVacancy::find($vacancyId);
        if (!$vacancy) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Vacancy not found', null, 404);
        }
        if ((int) $vacancy->user_id !== $userId) {
            $user = \App\Models\User::where('id', $userId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                return $this->respondWithError('RESOURCE_FORBIDDEN', 'Only the vacancy owner can view interviews', null, 403);
            }
        }

        $interviews = JobInterviewService::getForVacancy($vacancyId);

        return $this->respondWithData($interviews);
    }

    /** GET /api/v2/jobs/my-interviews — candidate lists their own interviews */
    public function myInterviews(Request $request): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_my_interviews', 30, 60);

        $interviews = JobInterviewService::getForUser($userId);

        return $this->respondWithData($interviews);
    }

    // =====================================================================
    // OFFERS
    // =====================================================================

    /** POST /api/v2/jobs/applications/{id}/offer — employer sends a job offer */
    public function createOffer(Request $request, int $applicationId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_offer_create', 10, 60);

        $data = $this->getAllInput();

        $offer = JobOfferService::create($applicationId, $userId, $data);

        if ($offer === false) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Unable to create offer. Check application ownership or an offer may already exist.', null, 422);
        }

        return $this->respondWithData($offer, null, 201);
    }

    /** PUT /api/v2/jobs/offers/{id}/accept — candidate accepts a job offer */
    public function acceptOffer(Request $request, int $offerId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_offer_accept', 10, 60);

        $success = JobOfferService::accept($offerId, $userId);

        if (!$success) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Unable to accept offer. It may not exist or already been actioned.', null, 422);
        }

        return $this->respondWithData(['message' => 'Offer accepted successfully']);
    }

    /** PUT /api/v2/jobs/offers/{id}/reject — candidate rejects a job offer */
    public function rejectOffer(Request $request, int $offerId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_offer_reject', 10, 60);

        $success = JobOfferService::reject($offerId, $userId);

        if (!$success) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Unable to reject offer. It may not exist or already been actioned.', null, 422);
        }

        return $this->respondWithData(['message' => 'Offer rejected successfully']);
    }

    /** DELETE /api/v2/jobs/offers/{id} — employer withdraws a job offer */
    public function withdrawOffer(Request $request, int $offerId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_offer_withdraw', 10, 60);

        $success = JobOfferService::withdraw($offerId, $userId);

        if (!$success) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Unable to withdraw offer. It may not exist or already been actioned.', null, 422);
        }

        return $this->noContent();
    }

    /** GET /api/v2/jobs/applications/{id}/offer — get the offer for an application */
    public function getApplicationOffer(Request $request, int $applicationId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_offer_get', 30, 60);

        $offer = JobOfferService::getForApplication($applicationId, $userId);

        if ($offer === null) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Offer not found', null, 404);
        }

        return $this->respondWithData($offer);
    }

    /** GET /api/v2/jobs/my-offers — candidate lists their own offers */
    public function myOffers(Request $request): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_my_offers', 30, 60);

        $offers = JobOfferService::getForUser($userId);

        return $this->respondWithData($offers);
    }

    // =====================================================================
    // AI CV PARSING
    // =====================================================================

    /** GET /api/v2/jobs/applications/{id}/parse-cv — AI-powered CV parsing */
    public function parseResumeCv(Request $request, int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_parse_cv', 5, 60);

        $tenantId = TenantContext::getId();

        $application = JobApplication::with(['vacancy'])->find($id);

        if (!$application || !$application->vacancy || (int) $application->vacancy->tenant_id !== $tenantId) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Application not found', null, 404);
        }

        // Only allow: the applicant themselves or the job poster
        $isApplicant = (int) $application->user_id === $userId;
        $isPoster = $application->vacancy && (int) $application->vacancy->user_id === $userId;

        if (!$isApplicant && !$isPoster) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Access denied', null, 403);
        }

        if (empty($application->cv_path)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'No CV attached to this application', null, 404);
        }

        $parsed = AiChatService::parseResume($application->cv_path);

        return $this->respondWithData($parsed);
    }

    // ── Referral endpoints ─────────────────────────────────────────────────

    /**
     * POST /v2/jobs/{id}/referral
     * Get or create a shareable referral token for a vacancy.
     */
    public function getOrCreateReferral(int $id): JsonResponse
    {
        $userId   = $this->getOptionalUserId();
        $referral = JobReferralService::getOrCreate($id, $userId);

        if (empty($referral)) {
            return $this->respondWithError('SERVER_INTERNAL_ERROR', 'Unable to create referral token', null, 422);
        }
        return $this->respondWithData(['referral' => $referral], null, 201);
    }

    /**
     * GET /v2/jobs/{id}/referral-stats
     * Employer views referral stats for their vacancy.
     */
    public function referralStats(int $id): JsonResponse
    {
        $userId = $this->getUserId();

        // Verify caller owns the vacancy or is admin
        $vacancy = \App\Models\JobVacancy::find($id);
        if (!$vacancy) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Vacancy not found', null, 404);
        }
        if ((int) $vacancy->user_id !== $userId) {
            $user = \App\Models\User::where('id', $userId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                return $this->respondWithError('RESOURCE_FORBIDDEN', 'Only the vacancy owner can view referral stats', null, 403);
            }
        }

        $stats = JobReferralService::getStats($id);
        return $this->respondWithData(['stats' => $stats]);
    }

    // ── Scorecard endpoints ────────────────────────────────────────────────

    /**
     * PUT /v2/jobs/applications/{id}/scorecard
     * Reviewer upserts their scorecard for an application.
     */
    public function upsertScorecard(int $id): JsonResponse
    {
        $userId = $this->getUserId();

        // Verify caller owns the vacancy or is admin (only employer/team should score)
        $application = JobApplication::with(['vacancy'])->find($id);
        if (!$application || !$application->vacancy || (int) $application->vacancy->tenant_id !== TenantContext::getId()) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Application not found', null, 404);
        }
        if ((int) $application->vacancy->user_id !== $userId) {
            $user = \App\Models\User::where('id', $userId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                return $this->respondWithError('RESOURCE_FORBIDDEN', 'Only the vacancy owner can score applications', null, 403);
            }
        }

        $data   = $this->getAllInput();
        $result = JobScorecardService::upsert($id, $userId, $data);

        if ($result === false) {
            return $this->respondWithError('SERVER_INTERNAL_ERROR', 'Unable to save scorecard', null, 422);
        }
        return $this->respondWithData(['scorecard' => $result]);
    }

    /**
     * GET /v2/jobs/applications/{id}/scorecards
     * Get all scorecards for an application (employer/team view).
     */
    public function getScorecards(int $id): JsonResponse
    {
        $userId = $this->getUserId();

        // Verify caller owns the vacancy, is the applicant, or is admin
        $application = JobApplication::with(['vacancy'])->find($id);
        if (!$application || !$application->vacancy || (int) $application->vacancy->tenant_id !== TenantContext::getId()) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Application not found', null, 404);
        }
        $isApplicant = (int) $application->user_id === $userId;
        $isPoster = (int) $application->vacancy->user_id === $userId;
        if (!$isApplicant && !$isPoster) {
            $user = \App\Models\User::where('id', $userId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                return $this->respondWithError('RESOURCE_FORBIDDEN', 'Access denied', null, 403);
            }
        }

        $cards = JobScorecardService::getForApplication($id);
        return $this->respondWithData(['data' => $cards]);
    }

    // ── Team endpoints ─────────────────────────────────────────────────────

    /**
     * POST /v2/jobs/{id}/team
     * Add a team member to a vacancy.
     */
    public function addTeamMember(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $data   = $this->getAllInput();
        $result = JobTeamService::addMember(
            $id,
            $userId,
            (int) ($data['user_id'] ?? 0),
            $data['role'] ?? 'reviewer'
        );

        if ($result === false) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Unable to add team member', null, 422);
        }
        return $this->respondWithData(['member' => $result], null, 201);
    }

    /**
     * DELETE /v2/jobs/{id}/team/{userId}
     * Remove a team member from a vacancy.
     */
    public function removeTeamMember(int $id, int $userId): JsonResponse
    {
        $currentUserId = $this->getUserId();

        $ok = JobTeamService::removeMember($id, $currentUserId, $userId);
        if (!$ok) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'Unable to remove team member', null, 422);
        }
        return $this->respondWithData(['success' => true]);
    }

    /**
     * GET /v2/jobs/{id}/team
     * List team members for a vacancy.
     */
    public function getTeam(int $id): JsonResponse
    {
        $userId = $this->getUserId();

        // Verify caller owns the vacancy or is admin
        $vacancy = \App\Models\JobVacancy::find($id);
        if (!$vacancy) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Vacancy not found', null, 404);
        }
        if ((int) $vacancy->user_id !== $userId) {
            $user = \App\Models\User::where('id', $userId)->first(['id', 'role']);
            if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
                return $this->respondWithError('RESOURCE_FORBIDDEN', 'Only the vacancy owner can view team members', null, 403);
            }
        }

        $members = JobTeamService::getMembers($id);
        return $this->respondWithData(['data' => $members]);
    }

    // ── Saved profile endpoints ────────────────────────────────────────────

    /**
     * GET /v2/jobs/saved-profile
     * Get the current user's saved application profile.
     */
    public function getSavedProfile(): JsonResponse
    {
        $userId  = $this->getUserId();
        $profile = JobSavedProfileService::get($userId);
        return $this->respondWithData(['profile' => $profile]);
    }

    /**
     * PUT /v2/jobs/saved-profile
     * Save/update the current user's application profile (cover letter + CV metadata).
     * Note: actual CV file upload happens via the apply endpoint; this stores metadata + text.
     */
    public function saveSavedProfile(): JsonResponse
    {
        $userId = $this->getUserId();
        $data   = $this->getAllInput();
        $result = JobSavedProfileService::save($userId, $data);

        if ($result === false) {
            return $this->respondWithError('SERVER_INTERNAL_ERROR', 'Unable to save profile', null, 422);
        }
        return $this->respondWithData(['profile' => $result]);
    }

    // ── Job Templates ─────────────────────────────────────────────────────

    /**
     * GET /v2/jobs/templates
     */
    public function listTemplates(): JsonResponse
    {
        $userId = $this->getUserId();
        return $this->respondWithData(['data' => JobTemplateService::list($userId)]);
    }

    /**
     * POST /v2/jobs/templates
     */
    public function createTemplate(): JsonResponse
    {
        $userId = $this->getUserId();
        $data   = $this->getAllInput();
        $result = JobTemplateService::create($userId, $data);
        return $result !== false
            ? $this->respondWithData(['template' => $result], null, 201)
            : $this->respondWithError('SERVER_INTERNAL_ERROR', 'Unable to create template', null, 422);
    }

    /**
     * GET /v2/jobs/templates/{id}
     */
    public function getTemplate(int $id): JsonResponse
    {
        $userId   = $this->getUserId();
        $template = JobTemplateService::get($id, $userId);
        return $template
            ? $this->respondWithData(['template' => $template])
            : $this->respondWithError('RESOURCE_NOT_FOUND', 'Not found', null, 404);
    }

    /**
     * DELETE /v2/jobs/templates/{id}
     */
    public function deleteTemplate(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $ok     = JobTemplateService::delete($id, $userId);
        return $ok
            ? $this->respondWithData(['success' => true])
            : $this->respondWithError('RESOURCE_NOT_FOUND', 'Not found', null, 404);
    }

    // ── Salary Benchmarks ─────────────────────────────────────────────────

    /**
     * GET /v2/jobs/salary-benchmark?title={title}
     */
    public function salaryBenchmark(): JsonResponse
    {
        $title = $this->query('title', '');
        if (!$title) return $this->respondWithData(['benchmark' => null]);
        $benchmark = SalaryBenchmarkService::findForTitle($title);
        return $this->respondWithData(['benchmark' => $benchmark]);
    }

    // ── GDPR ──────────────────────────────────────────────────────────────

    /** GET /v2/jobs/gdpr-export */
    public function gdprExport(): JsonResponse
    {
        $userId = $this->getUserId();
        $data = JobGdprService::exportUserData($userId);
        return $this->respondWithData(['data' => $data]);
    }

    /** DELETE /v2/jobs/gdpr-erase-me */
    public function gdprErase(): JsonResponse
    {
        $userId = $this->getUserId();
        $ok = JobGdprService::eraseUserData($userId);
        return $ok
            ? $this->respondWithData(['success' => true, 'message' => 'Your job application data has been anonymised.'])
            : $this->respondWithError('SERVER_INTERNAL_ERROR', 'Erasure failed', null, 500);
    }

    /** GET /v2/jobs/{id}/applications/export-csv */
    public function exportApplicationsCsv(int $id): \Illuminate\Http\Response|JsonResponse
    {
        $this->rateLimit('jobs_export_csv', 10, 60);
        $userId = $this->getUserId();

        $csv = $this->jobService->exportApplicationsCsv($id, $userId);
        if ($csv === null) {
            $errors = $this->jobService->getErrors();
            $code   = ($errors[0]['code'] ?? '') === 'RESOURCE_FORBIDDEN' ? 403 : 404;
            return $this->respondWithErrors($errors, $code);
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"job-{$id}-applications.csv\"",
        ]);
    }

    // ── Pipeline Rules ─────────────────────────────────────────────────────

    /** GET /v2/jobs/{id}/pipeline-rules */
    public function listPipelineRules(int $id): JsonResponse
    {
        $this->getUserId();
        return $this->respondWithData(['data' => JobPipelineRuleService::listForVacancy($id)]);
    }

    /** POST /v2/jobs/{id}/pipeline-rules */
    public function createPipelineRule(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $data   = $this->getAllInput();
        $result = JobPipelineRuleService::create($id, $userId, $data);
        return $result !== false
            ? $this->respondWithData(['rule' => $result], null, 201)
            : $this->respondWithError('VALIDATION_ERROR', 'Unable to create rule', null, 422);
    }

    /** DELETE /v2/jobs/pipeline-rules/{id} */
    public function deletePipelineRule(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $ok     = JobPipelineRuleService::delete($id, $userId);
        return $ok
            ? $this->respondWithData(['success' => true])
            : $this->respondWithError('RESOURCE_NOT_FOUND', 'Not found', null, 404);
    }

    /** POST /v2/jobs/{id}/pipeline-rules/run */
    public function runPipelineRules(int $id): JsonResponse
    {
        $this->getUserId();
        $count = JobPipelineRuleService::runForVacancy($id);
        return $this->respondWithData(['actioned' => $count]);
    }

    // ── Bulk Application Actions ───────────────────────────────────────────

    /** POST /v2/jobs/{id}/applications/bulk-status */
    public function bulkUpdateApplicationStatus(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $data   = $this->getAllInput();
        $ids    = array_map('intval', (array) ($data['application_ids'] ?? []));
        $status = $data['status'] ?? '';

        if (empty($ids) || !$status) {
            return $this->respondWithError('VALIDATION_ERROR', 'application_ids and status are required', null, 422);
        }

        if (count($ids) > 1000) {
            return $this->respondWithError('VALIDATION_ERROR', 'Maximum 1000 application IDs per request', null, 422);
        }

        $count  = $this->jobService->bulkUpdateApplicationStatus($id, $userId, $ids, $status);
        $errors = $this->jobService->getErrors();
        if (!empty($errors)) {
            return $this->respondWithErrors($errors, 422);
        }
        return $this->respondWithData(['updated' => $count]);
    }

    // =====================================================================
    // AI JOB DESCRIPTION GENERATOR (Agent A)
    // =====================================================================

    /** POST /api/v2/jobs/generate-description — AI-generated job description */
    public function generateDescription(): JsonResponse
    {
        $this->ensureFeature();
        $this->getUserId();
        $this->rateLimit('jobs_ai_generate', 10, 60);

        $title = $this->input('title');
        $skills = $this->input('skills', []);
        $type = $this->input('type', 'paid');
        $commitment = $this->input('commitment', 'flexible');

        if (empty($title)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Job title is required', 'title', 400);
        }

        $skillsList = is_array($skills) ? implode(', ', $skills) : (string) $skills;

        $typeLabel = match ($type) {
            'paid' => 'paid position',
            'volunteer' => 'volunteer role',
            'timebank' => 'timebanking opportunity',
            default => 'position',
        };

        $commitmentLabel = match ($commitment) {
            'full_time' => 'full-time',
            'part_time' => 'part-time',
            'flexible' => 'flexible hours',
            'one_off' => 'one-off project',
            default => 'flexible',
        };

        $prompt = "Write a professional, engaging job description for a community platform. "
            . "The role is: \"{$title}\". "
            . "It is a {$commitmentLabel} {$typeLabel}. "
            . (!empty($skillsList) ? "Required skills include: {$skillsList}. " : '')
            . "Write 3-5 paragraphs covering: role overview, key responsibilities, ideal candidate qualities, and what the community offers. "
            . "Use a warm, community-oriented tone. Do not include a job title heading — just the description body. "
            . "Do not use markdown formatting.";

        $result = $this->aiService->chat(0, $prompt, [
            'system_prompt' => 'You are a professional job description writer for a community timebanking platform. Write clear, inclusive, and welcoming job descriptions.',
            'max_tokens' => 1024,
            'model' => 'gpt-4o-mini',
        ]);

        if (!empty($result['error'])) {
            return $this->respondWithError('AI_SERVICE_ERROR', $result['reply'] ?? 'Failed to generate description', null, 503);
        }

        return $this->respondWithData(['description' => $result['reply']]);
    }

    // =====================================================================
    // DUPLICATE JOB DETECTION (Agent A)
    // =====================================================================

    /** POST /api/v2/jobs/check-duplicate — find similar open/draft jobs */
    public function checkDuplicate(): JsonResponse
    {
        $this->ensureFeature();
        $this->getUserId();
        $this->rateLimit('jobs_check_dup', 30, 60);

        $title = $this->input('title');
        $organizationId = $this->input('organization_id');

        if (empty($title)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Job title is required', 'title', 400);
        }

        $tenantId = TenantContext::getId();
        $duplicates = $this->jobService->findSimilarJobs(
            (string) $title,
            $organizationId ? (int) $organizationId : null,
            $tenantId
        );

        return $this->respondWithData(['duplicates' => $duplicates]);
    }

    // =====================================================================
    // TALENT SEARCH — Candidate Resume Database (Agent C)
    // =====================================================================

    /** GET /api/v2/jobs/talent-search — search candidates who opted in */
    public function talentSearch(): JsonResponse
    {
        $this->ensureFeature();
        $this->getUserId(); // Requires authentication
        $this->rateLimit('talent_search', 30, 60);

        $tenantId = TenantContext::getId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
            'offset' => $this->queryInt('offset', 0),
        ];

        if ($this->query('keywords')) {
            $filters['keywords'] = $this->query('keywords');
        }
        if ($this->query('skills')) {
            $skills = $this->query('skills');
            $filters['skills'] = is_string($skills) ? array_filter(array_map('trim', explode(',', $skills))) : [];
        }
        if ($this->query('location')) {
            $filters['location'] = $this->query('location');
        }

        $result = $this->candidateService->search($filters, $tenantId);

        return $this->respondWithData($result);
    }

    /** GET /api/v2/jobs/talent-search/{id} — view a candidate profile */
    public function talentProfile(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->getUserId(); // Requires authentication
        $this->rateLimit('talent_profile', 60, 60);

        $tenantId = TenantContext::getId();

        $profile = $this->candidateService->getCandidateProfile($id, $tenantId);

        if (!$profile) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Candidate profile not found or not searchable', null, 404);
        }

        return $this->respondWithData($profile);
    }

    /** PUT /api/v2/users/me/resume-visibility — toggle resume searchability */
    public function updateResumeVisibility(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('resume_visibility', 10, 60);

        $tenantId = TenantContext::getId();
        $searchable = $this->inputBool('searchable');

        $success = $this->candidateService->updateResumeVisibility($userId, $tenantId, $searchable);

        if (!$success) {
            return $this->respondWithError('SERVER_INTERNAL_ERROR', 'Failed to update resume visibility', null, 500);
        }

        return $this->respondWithData([
            'searchable' => $searchable,
            'message' => $searchable
                ? 'Your profile is now searchable by employers'
                : 'Your profile is no longer searchable by employers',
        ]);
    }

    // =====================================================================
    // INTERVIEW SELF-SCHEDULING (Agent E)
    // =====================================================================

    /** GET /api/v2/jobs/{id}/interview-slots — list slots for a job */
    public function listInterviewSlots(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('jobs_slots_list', 30, 60);

        $tenantId = TenantContext::getId();
        $slots = $this->schedulingService->getAvailableSlots($id, $tenantId);

        return $this->respondWithData($slots);
    }

    /** POST /api/v2/jobs/{id}/interview-slots — employer creates slots */
    public function createInterviewSlots(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_slots_create', 10, 60);

        $tenantId = TenantContext::getId();
        $data = $this->getAllInput();
        $slots = $data['slots'] ?? [];

        if (empty($slots) || !is_array($slots)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'At least one slot is required', 'slots', 400);
        }

        $created = $this->schedulingService->createSlots($id, $userId, $slots, $tenantId);

        if (empty($created) && !empty($this->schedulingService->getErrors())) {
            $errors = $this->schedulingService->getErrors();
            $status = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($created, null, 201);
    }

    /** POST /api/v2/jobs/{id}/interview-slots/bulk — employer bulk-creates slots */
    public function bulkCreateInterviewSlots(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_slots_bulk', 5, 60);

        $tenantId = TenantContext::getId();
        $data = $this->getAllInput();

        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;
        $duration = (int) ($data['duration_minutes'] ?? 30);
        $dayConfig = $data['day_config'] ?? [];

        if (empty($dateFrom) || empty($dateTo)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'date_from and date_to are required', 'date_from', 400);
        }

        if (empty($dayConfig)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'day_config is required', 'day_config', 400);
        }

        $created = $this->schedulingService->bulkCreateSlots(
            $id,
            $userId,
            $dateFrom,
            $dateTo,
            $duration,
            $dayConfig,
            $tenantId
        );

        if (empty($created) && !empty($this->schedulingService->getErrors())) {
            $errors = $this->schedulingService->getErrors();
            $status = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($created, null, 201);
    }

    /** POST /api/v2/jobs/interview-slots/{slotId}/book — candidate books a slot */
    public function bookInterviewSlot(int $slotId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_slots_book', 10, 60);

        $tenantId = TenantContext::getId();
        $result = $this->schedulingService->bookSlot($slotId, $userId, $tenantId);

        if ($result === null) {
            $errors = $this->schedulingService->getErrors();
            $status = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_CONFLICT') {
                    $status = 409;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($result, null, 201);
    }

    /** DELETE /api/v2/jobs/interview-slots/{slotId}/book — cancel a booking */
    public function cancelInterviewSlotBooking(int $slotId): JsonResponse
    {
        $this->ensureFeature();
        $this->getUserId();
        $this->rateLimit('jobs_slots_cancel', 10, 60);

        $tenantId = TenantContext::getId();
        $success = $this->schedulingService->cancelSlotBooking($slotId, $tenantId);

        if (!$success) {
            $errors = $this->schedulingService->getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        return $this->respondWithData(['message' => 'Booking cancelled']);
    }

    /** DELETE /api/v2/jobs/interview-slots/{slotId} — employer deletes a slot */
    public function deleteInterviewSlot(int $slotId): JsonResponse
    {
        $this->ensureFeature();
        $this->getUserId();
        $this->rateLimit('jobs_slots_delete', 10, 60);

        $tenantId = TenantContext::getId();
        $success = $this->schedulingService->deleteSlot($slotId, $tenantId);

        if (!$success) {
            $errors = $this->schedulingService->getErrors();
            return $this->respondWithErrors($errors, 404);
        }

        return $this->noContent();
    }
}
