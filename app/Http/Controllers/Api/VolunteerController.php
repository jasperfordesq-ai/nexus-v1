<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Volunteering\CreateOpportunityRequest;
use App\Http\Requests\Volunteering\LogHoursRequest;
use App\Http\Requests\Volunteering\CreateReviewRequest;
use App\Services\VolunteerService;
use App\Services\VolunteerMatchingService;
use App\Core\TenantContext;
use App\Models\Notification;
use App\Models\User;
use App\Models\VolApplication;
use App\Models\VolOpportunity;
use App\Models\VolShift;

/**
 * VolunteerController -- Core volunteering: opportunities, applications, shifts, hours,
 * organisations, and reviews.
 *
 * Extracted sub-controllers:
 *  - VolunteerCheckInController (QR check-in/out/verify)
 *  - VolunteerExpenseController (expenses, policies)
 *  - VolunteerCertificateController (certificates, credentials)
 *  - VolunteerWellbeingController (wellbeing, emergency alerts, safeguarding, training, incidents)
 *  - VolunteerCommunityController (swaps, waitlist, group reservations, recurring, custom fields,
 *    accessibility, community projects, donations, giving days, webhooks, reminders, guardian consents)
 */
class VolunteerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VolunteerService $volunteerService,
        private readonly VolunteerMatchingService $volunteerMatchingService,
    ) {}

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('volunteering')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', __('api.volunteering_feature_disabled'), null, 403)
            );
        }
    }

    private function getErrorStatus(array $errors): int
    {
        foreach ($errors as $error) {
            $code = $error['code'] ?? '';
            if ($code === 'NOT_FOUND') return 404;
            if ($code === 'FORBIDDEN') return 403;
            if ($code === 'ALREADY_EXISTS') return 409;
            if ($code === 'FEATURE_DISABLED') return 403;
        }
        return 400;
    }

    // ========================================
    // OPPORTUNITIES
    // ========================================

    public function opportunities(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_list', 60, 60);

        $filters = ['limit' => $this->queryInt('per_page', 20, 1, 50)];
        if ($this->query('organization_id')) $filters['organization_id'] = (int) $this->query('organization_id');
        if ($this->query('category_id')) $filters['category_id'] = (int) $this->query('category_id');
        if ($this->query('search')) $filters['search'] = $this->query('search');
        if ($this->queryBool('is_remote')) $filters['is_remote'] = true;
        if ($this->query('cursor')) $filters['cursor'] = $this->query('cursor');

        $result = $this->volunteerService->getOpportunities($filters);
        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    public function showOpportunity($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_show', 120, 60);
        $opportunity = $this->volunteerService->getOpportunityById((int) $id, Auth::id());
        if (!$opportunity) return $this->respondWithError('NOT_FOUND', __('api.opportunity_not_found'), null, 404);
        return $this->respondWithData($opportunity);
    }

    public function show(int $id): JsonResponse
    {
        return $this->showOpportunity($id);
    }

    public function createOpportunity(CreateOpportunityRequest $request): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_create', 10, 60);
        $data = $this->getAllInput();
        $data['created_by'] = $userId;
        $opportunity = $this->volunteerService->createOpportunity($userId, $data);
        return $this->respondWithData($opportunity, null, 201);
    }

    public function updateOpportunity($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_update', 20, 60);

        $data = [];
        foreach (['title', 'description', 'location', 'skills_needed', 'start_date', 'end_date'] as $field) {
            if ($this->input($field) !== null) $data[$field] = trim($this->input($field));
        }
        if ($this->input('category_id') !== null) $data['category_id'] = $this->inputInt('category_id') ?: null;

        $success = $this->volunteerService->updateOpportunity((int) $id, $userId, $data);
        if (!$success) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        $opportunity = $this->volunteerService->getOpportunityById((int) $id, $userId);
        return $this->respondWithData($opportunity);
    }

    public function deleteOpportunity($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_delete', 10, 60);

        $success = $this->volunteerService->deleteOpportunity((int) $id, $userId);
        if (!$success) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    // ========================================
    // APPLICATIONS
    // ========================================

    public function apply(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_apply', 20, 60);
        $data = ['message' => trim($this->input('message', '')), 'shift_id' => $this->inputInt('shift_id') ?: null];

        // Check for duplicate application (tenant-scoped to prevent cross-tenant leaks)
        $existing = VolApplication::where('opportunity_id', $id)
            ->where('user_id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
        if ($existing) {
            return $this->respondWithError('ALREADY_EXISTS', __('api.already_applied'), null, 409);
        }

        try {
            $application = $this->volunteerService->apply($id, $userId, $data);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('NOT_FOUND', $e->getMessage(), null, 404);
        }

        // Notify the opportunity organizer about the new application
        try {
            $opportunity = VolOpportunity::find($id);
            if ($opportunity && $opportunity->created_by && $opportunity->created_by !== $userId) {
                $volunteer = User::find($userId);
                $volunteerName = $volunteer->name ?? 'Someone';
                Notification::createNotification(
                    (int) $opportunity->created_by,
                    "{$volunteerName} applied for your volunteer opportunity",
                    "/volunteering/opportunities/{$id}/applications",
                    'volunteering'
                );
            }
        } catch (\Throwable $e) {
            // Notification failure must not break the main flow
        }

        return $this->respondWithData($application, null, 201);
    }

    public function myApplications(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_apps', 60, 60);
        $applications = $this->volunteerService->getMyApplications($userId);
        return $this->respondWithData($applications);
    }

    public function opportunityApplications($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_opp_apps', 60, 60);

        $filters = ['limit' => $this->queryInt('per_page', 20, 1, 50)];
        if ($this->query('status')) $filters['status'] = $this->query('status');
        if ($this->query('cursor')) $filters['cursor'] = $this->query('cursor');

        $result = $this->volunteerService->getApplicationsForOpportunity((int) $id, $userId, $filters);
        if ($result === null) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['items' => $result['items'], 'cursor' => $result['cursor'], 'has_more' => $result['has_more']]);
    }

    public function handleApplication($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_handle_app', 30, 60);

        $action = $this->input('action');
        $orgNote = trim((string) ($this->input('org_note') ?? ''));
        if (!$action || !in_array($action, ['approve', 'decline'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.action_must_be_approve_or_decline'), 'action', 400);
        }

        $success = $this->volunteerService->handleApplication((int) $id, $userId, $action, $orgNote);
        if (!$success) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        // Notify the volunteer about the application decision
        try {
            $application = VolApplication::find($id);
            if ($application && $application->user_id) {
                $opportunityId = $application->opportunity_id;
                if ($action === 'approve') {
                    $message = 'Your volunteer application was accepted!';
                } else {
                    $message = 'Your volunteer application was not accepted';
                }
                Notification::createNotification(
                    (int) $application->user_id,
                    $message,
                    "/volunteering/opportunities/{$opportunityId}",
                    'volunteering'
                );
            }
        } catch (\Throwable $e) {
            // Notification failure must not break the main flow
        }

        return $this->respondWithData(['id' => (int) $id, 'status' => $action === 'approve' ? 'approved' : 'declined']);
    }

    public function withdrawApplication($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_withdraw', 20, 60);

        $success = $this->volunteerService->withdrawApplication((int) $id, $userId);
        if (!$success) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    // ========================================
    // SHIFTS
    // ========================================

    public function myShifts(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_shifts', 60, 60);
        $shifts = $this->volunteerService->getMyShifts($userId);
        return $this->respondWithData($shifts);
    }

    public function shifts($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_shifts', 120, 60);
        $shifts = $this->volunteerService->getShiftsForOpportunity((int) $id);
        return $this->respondWithData($shifts);
    }

    public function signUp($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_shift_signup', 20, 60);

        $success = $this->volunteerService->signUpForShift((int) $id, $userId);
        if (!$success) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        // Notify the opportunity organizer about the shift sign-up
        try {
            $shift = VolShift::with('opportunity')->find($id);
            if ($shift && $shift->opportunity && $shift->opportunity->created_by && $shift->opportunity->created_by !== $userId) {
                $volunteer = User::find($userId);
                $volunteerName = $volunteer->name ?? 'Someone';
                Notification::createNotification(
                    (int) $shift->opportunity->created_by,
                    "{$volunteerName} signed up for a shift",
                    "/volunteering/opportunities/{$shift->opportunity_id}",
                    'volunteering'
                );
            }
        } catch (\Throwable $e) {
            // Notification failure must not break the main flow
        }

        return $this->respondWithData(['shift_id' => (int) $id, 'message' => 'Successfully signed up for shift']);
    }

    public function cancelSignup($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_shift_cancel', 20, 60);

        $success = $this->volunteerService->cancelShiftSignup((int) $id, $userId);
        if (!$success) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    // ========================================
    // HOURS
    // ========================================

    public function logHours(LogHoursRequest $request): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_log_hours', 20, 60);

        $data = [
            'organization_id' => $this->inputInt('organization_id'),
            'opportunity_id'  => $this->inputInt('opportunity_id') ?: null,
            'date'            => $this->input('date'),
            'hours'           => (float) $this->input('hours'),
            'description'     => trim($this->input('description', '')),
        ];

        $logId = $this->volunteerService->logHours($userId, $data);
        if ($logId === null) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => $logId, 'status' => 'pending', 'message' => 'Hours logged successfully, pending verification'], null, 201);
    }

    public function myHours(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_hours', 60, 60);
        $hours = $this->volunteerService->getMyHours($userId);
        return $this->respondWithData($hours);
    }

    public function hoursSummary(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_hours_summary', 60, 60);
        $summary = $this->volunteerService->getHoursSummary($userId);
        return $this->respondWithData($summary);
    }

    public function pendingHoursReview(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_pending_hours', 60, 60);

        $filters = ['limit' => $this->queryInt('per_page', 20, 1, 50)];
        if ($this->query('cursor')) $filters['cursor'] = $this->query('cursor');

        $result = $this->volunteerService->getPendingHoursForOrgOwner($userId, $filters);
        return $this->respondWithData(['items' => $result['items'], 'cursor' => $result['cursor'], 'has_more' => $result['has_more']]);
    }

    public function verifyHours($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_verify_hours', 30, 60);

        $action = $this->input('action');
        if (!$action || !in_array($action, ['approve', 'decline'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.action_must_be_approve_or_decline'), 'action', 400);
        }

        $success = $this->volunteerService->verifyHours((int) $id, $userId, $action);
        if (!$success) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => (int) $id, 'status' => $action === 'approve' ? 'approved' : 'declined']);
    }

    // ========================================
    // ORGANISATIONS
    // ========================================

    public function organisations(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_orgs', 60, 60);
        $filters = ['limit' => $this->queryInt('per_page', 20, 1, 50)];
        if ($this->query('search')) $filters['search'] = $this->query('search');
        if ($this->query('cursor')) $filters['cursor'] = $this->query('cursor');
        $result = $this->volunteerService->getOrganisations($filters);
        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    public function showOrganisation($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_org_show', 120, 60);
        $org = $this->volunteerService->getOrganisationById((int) $id);
        if (!$org) return $this->respondWithError('NOT_FOUND', __('api.organization_not_found'), null, 404);
        return $this->respondWithData($org);
    }

    public function myOrganisations(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_orgs', 60, 60);
        $orgs = $this->volunteerService->getMyOrganizations($userId);
        return $this->respondWithData($orgs);
    }

    public function createOrganisation(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_org_create', 5, 60);

        $data = [
            'name'          => trim($this->input('name', '')),
            'description'   => trim($this->input('description', '')),
            'contact_email' => trim($this->input('contact_email', '')),
            'website'       => trim($this->input('website', '')),
        ];

        $orgId = $this->volunteerService->createOrganization($userId, $data);
        if ($orgId === null) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        $org = $this->volunteerService->getOrganizationById($orgId, true);
        return $this->respondWithData($org, null, 201);
    }

    // ========================================
    // REVIEWS
    // ========================================

    public function createReview(CreateReviewRequest $request): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_review', 10, 60);

        $targetType = $this->input('target_type');
        $targetId = $this->inputInt('target_id');
        $rating = $this->inputInt('rating');
        $comment = trim($this->input('comment', ''));

        $reviewId = $this->volunteerService->createReview($userId, $targetType, $targetId, $rating, $comment);
        if ($reviewId === null) {
            $errors = $this->volunteerService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => $reviewId, 'rating' => $rating, 'message' => 'Review submitted successfully'], null, 201);
    }

    public function getReviews($type, $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_reviews', 60, 60);
        if (!in_array($type, ['organization', 'user'])) return $this->respondWithError('VALIDATION_ERROR', __('api.type_must_be_org_or_user'), 'type', 400);
        $reviews = $this->volunteerService->getReviews($type, (int) $id);
        return $this->respondWithData(['reviews' => $reviews]);
    }

    // ========================================
    // RECOMMENDED SHIFTS
    // ========================================

    public function recommendedShifts(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_recommended', 30, 60);

        $shifts = $this->volunteerMatchingService->getRecommendedShifts($userId, [
            'limit' => $this->queryInt('limit', 10, 1, 20),
            'min_match_score' => $this->queryInt('min_score', 20, 0, 100),
        ]);

        return $this->respondWithData($shifts);
    }

    // ========================================
    // LEGACY V1 INDEX
    // ========================================

    public function index(): JsonResponse
    {
        $this->ensureFeature();
        $this->getUserId();
        $this->rateLimit('volunteering_legacy_list', 60, 60);

        $opportunities = VolOpportunity::where('tenant_id', TenantContext::getId())
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
        return $this->respondWithCollection($opportunities);
    }
}
