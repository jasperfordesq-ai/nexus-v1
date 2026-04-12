<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Volunteering\ApplyOpportunityRequest;
use App\Http\Requests\Volunteering\CreateOpportunityRequest;
use App\Http\Requests\Volunteering\CreateOrganisationRequest;
use App\Http\Requests\Volunteering\CreateReviewRequest;
use App\Http\Requests\Volunteering\HandleApplicationRequest;
use App\Http\Requests\Volunteering\LogHoursRequest;
use App\Http\Requests\Volunteering\UpdateOpportunityRequest;
use App\Http\Requests\Volunteering\VerifyHoursRequest;
use App\Services\VolunteerService;
use App\Services\VolunteerMatchingService;
use App\Core\TenantContext;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\VolOrgWalletService;
use App\Models\VolApplication;
use App\Models\VolOpportunity;
use App\Models\VolShift;
use Illuminate\Support\Facades\DB;

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

    public function updateOpportunity(UpdateOpportunityRequest $request, $id): JsonResponse
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

    public function apply(ApplyOpportunityRequest $request, int $id): JsonResponse
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
                $notifContent = "{$volunteerName} applied for your volunteer opportunity: {$opportunity->title}";
                $orgId = $opportunity->organization_id;
                $notifLink = "/volunteering/org/{$orgId}/dashboard?tab=applications";

                $htmlContent = NotificationDispatcher::buildVolApplicationReceivedEmail(
                    $volunteerName,
                    $opportunity->title,
                    (int) $orgId
                );

                NotificationDispatcher::dispatch(
                    (int) $opportunity->created_by,
                    'global',
                    0,
                    'vol_application_received',
                    $notifContent,
                    $notifLink,
                    $htmlContent
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

    public function handleApplication(HandleApplicationRequest $request, $id): JsonResponse
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

        // Notify the volunteer about the application decision (bell + email + push)
        try {
            $application = VolApplication::find($id);
            if ($application && $application->user_id) {
                $opportunityId = $application->opportunity_id;
                $opportunity = VolOpportunity::find($opportunityId);
                $oppTitle = $opportunity->title ?? 'a volunteer opportunity';

                if ($action === 'approve') {
                    $message = "Your volunteer application for \"{$oppTitle}\" was accepted!";
                    $notifType = 'vol_application_approved';
                    $htmlContent = NotificationDispatcher::buildVolApplicationApprovedEmail($oppTitle, $opportunityId);
                } else {
                    $message = "Your volunteer application for \"{$oppTitle}\" was not accepted";
                    $notifType = 'vol_application_declined';
                    $htmlContent = NotificationDispatcher::buildVolApplicationDeclinedEmail($oppTitle);
                }

                NotificationDispatcher::dispatch(
                    (int) $application->user_id,
                    'global',
                    0,
                    $notifType,
                    $message,
                    "/volunteering/opportunities/{$opportunityId}",
                    $htmlContent
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

        return $this->respondWithData(['shift_id' => (int) $id, 'message' => __('api_controllers_2.volunteer.signed_up_for_shift')]);
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
        return $this->respondWithData(['id' => $logId, 'status' => 'pending', 'message' => __('api_controllers_2.volunteer.hours_logged_pending')], null, 201);
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

    public function verifyHours(VerifyHoursRequest $request, $id): JsonResponse
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

    public function createOrganisation(CreateOrganisationRequest $request): JsonResponse
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
        return $this->respondWithData(['id' => $reviewId, 'rating' => $rating, 'message' => __('api_controllers_2.volunteer.review_submitted')], null, 201);
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
    // ORGANISATION DASHBOARD & WALLET
    // ========================================

    /**
     * Verify the current user is owner/admin of the given vol org.
     * Returns the org row or null (and sets 403 response).
     */
    private function ensureOrgAccess(int $orgId): ?object
    {
        $tenantId = TenantContext::getId();
        $userId = $this->getUserId();

        $org = DB::selectOne(
            "SELECT * FROM vol_organizations WHERE id = ? AND tenant_id = ?",
            [$orgId, $tenantId]
        );
        if (!$org) {
            return null;
        }

        // Org creator always has access
        if ((int) $org->user_id === $userId) {
            return $org;
        }

        // Org members with owner/admin role have access
        $membership = DB::selectOne(
            "SELECT role FROM org_members WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [$tenantId, $orgId, $userId]
        );

        if ($membership && in_array($membership->role, ['owner', 'admin'], true)) {
            return $org;
        }

        // Site admins (super_admin, god) can access any org dashboard
        $user = $this->resolveUser();
        $role = $user->role ?? 'member';
        if (in_array($role, ['super_admin', 'god']) || ($user->is_super_admin ?? false) || ($user->is_tenant_super_admin ?? false)) {
            return $org;
        }

        return null;
    }

    public function orgStats($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_org_stats', 60, 60);
        $org = $this->ensureOrgAccess((int) $id);
        if (!$org) return $this->respondWithError('FORBIDDEN', __('api_controllers_2.volunteer.access_denied'), null, 403);

        $tenantId = TenantContext::getId();
        $orgId = (int) $org->id;

        $stats = DB::selectOne("
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
                 WHERE vo3.organization_id = ? AND vo3.tenant_id = ? AND vo3.status = 'active') as active_opportunities
        ", [$orgId, $tenantId, $orgId, $tenantId, $orgId, $tenantId, $orgId, $tenantId, $orgId, $tenantId]);

        return $this->respondWithData([
            'total_volunteers' => (int) $stats->total_volunteers,
            'pending_applications' => (int) $stats->pending_applications,
            'pending_hours' => (int) $stats->pending_hours,
            'total_approved_hours' => (float) $stats->total_approved_hours,
            'active_opportunities' => (int) $stats->active_opportunities,
            'wallet_balance' => (float) $org->balance,
            'auto_pay_enabled' => (bool) $org->auto_pay_enabled,
            'org_name' => $org->name,
        ]);
    }

    public function orgWalletBalance($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_org_wallet', 60, 60);
        $org = $this->ensureOrgAccess((int) $id);
        if (!$org) return $this->respondWithError('FORBIDDEN', __('api_controllers_2.volunteer.access_denied'), null, 403);

        $summary = VolOrgWalletService::getWalletSummary((int) $id);
        return $this->respondWithData($summary);
    }

    public function orgWalletTransactions($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_org_wallet_txns', 60, 60);
        $org = $this->ensureOrgAccess((int) $id);
        if (!$org) return $this->respondWithError('FORBIDDEN', __('api_controllers_2.volunteer.access_denied'), null, 403);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
            'cursor' => $this->query('cursor'),
            'type' => $this->query('type'),
        ];

        $result = VolOrgWalletService::getTransactions((int) $id, $filters);
        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    public function orgWalletDeposit($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_org_wallet_deposit', 10, 60);
        $org = $this->ensureOrgAccess((int) $id);
        if (!$org) return $this->respondWithError('FORBIDDEN', __('api_controllers_2.volunteer.access_denied'), null, 403);

        $amount = (float) $this->input('amount', 0);
        $note = trim((string) $this->input('note', ''));

        if ($amount <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api_controllers_2.volunteer.amount_gt_zero'), 'amount', 400);
        }

        $result = VolOrgWalletService::depositFromUser($userId, (int) $id, $amount, $note ?: null);
        if (!$result['success']) {
            return $this->respondWithError('VALIDATION_ERROR', $result['message'], null, 400);
        }

        return $this->respondWithData([
            'message' => $result['message'],
            'new_balance' => $result['new_balance'],
        ]);
    }

    public function orgWalletAutoPayToggle($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_org_autopay', 20, 60);
        $org = $this->ensureOrgAccess((int) $id);
        if (!$org) return $this->respondWithError('FORBIDDEN', __('api_controllers_2.volunteer.access_denied'), null, 403);

        $enabled = $this->inputBool('enabled');
        $tenantId = TenantContext::getId();

        DB::update(
            "UPDATE vol_organizations SET auto_pay_enabled = ? WHERE id = ? AND tenant_id = ?",
            [$enabled ? 1 : 0, (int) $id, $tenantId]
        );

        return $this->respondWithData(['auto_pay_enabled' => $enabled]);
    }

    public function orgVolunteers($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_org_volunteers', 60, 60);
        $org = $this->ensureOrgAccess((int) $id);
        if (!$org) return $this->respondWithError('FORBIDDEN', __('api_controllers_2.volunteer.access_denied'), null, 403);

        $tenantId = TenantContext::getId();
        $orgId = (int) $id;
        $limit = $this->queryInt('per_page', 20, 1, 50);
        $cursor = $this->query('cursor');

        $params = [$orgId, $tenantId, $tenantId];
        $cursorClause = '';
        if ($cursor) {
            $cursorClause = ' AND u.id < ?';
            $params[] = (int) $cursor;
        }
        $params[] = $limit + 1;

        $rows = DB::select("
            SELECT u.id, u.name, u.avatar_url, u.email,
                   MAX(va.created_at) as applied_at,
                   COALESCE(SUM(CASE WHEN vl.status = 'approved' THEN vl.hours ELSE 0 END), 0) as total_hours,
                   COUNT(DISTINCT va.id) as applications_count
            FROM users u
            INNER JOIN vol_applications va ON va.user_id = u.id AND va.status = 'approved'
            INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id AND vo.organization_id = ?
            LEFT JOIN vol_logs vl ON vl.user_id = u.id AND vl.organization_id = vo.organization_id AND vl.tenant_id = ?
            WHERE va.tenant_id = ?
            {$cursorClause}
            GROUP BY u.id, u.name, u.avatar_url, u.email
            ORDER BY u.id DESC
            LIMIT ?
        ", $params);

        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);

        $items = array_map(fn ($r) => [
            'id' => (int) $r->id,
            'name' => $r->name,
            'avatar_url' => $r->avatar_url,
            'email' => $r->email,
            'total_hours' => (float) $r->total_hours,
            'applications_count' => (int) $r->applications_count,
            'applied_at' => $r->applied_at,
        ], $rows);

        $lastItem = end($items);
        $nextCursor = $lastItem ? (string) $lastItem['id'] : null;
        return $this->respondWithCollection($items, $hasMore ? $nextCursor : null, $limit, $hasMore);
    }

    public function orgApplications($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_org_applications', 60, 60);
        $org = $this->ensureOrgAccess((int) $id);
        if (!$org) return $this->respondWithError('FORBIDDEN', __('api_controllers_2.volunteer.access_denied'), null, 403);

        $tenantId = TenantContext::getId();
        $orgId = (int) $id;
        $limit = $this->queryInt('per_page', 20, 1, 50);
        $cursor = $this->query('cursor');
        $statusFilter = $this->query('status');

        $params = [$orgId, $tenantId];
        $whereClauses = '';
        if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'declined'])) {
            $whereClauses .= ' AND va.status = ?';
            $params[] = $statusFilter;
        }
        if ($cursor) {
            $whereClauses .= ' AND va.id < ?';
            $params[] = (int) $cursor;
        }
        $params[] = $limit + 1;

        $rows = DB::select("
            SELECT va.id, va.status, va.message, va.org_note, va.created_at, va.shift_id,
                   u.id as user_id, u.name as user_name, u.avatar_url as user_avatar,
                   u.email as user_email,
                   vo.id as opportunity_id, vo.title as opportunity_title,
                   vs.start_time as shift_start, vs.end_time as shift_end
            FROM vol_applications va
            INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id AND vo.organization_id = ?
            INNER JOIN users u ON va.user_id = u.id
            LEFT JOIN vol_shifts vs ON va.shift_id = vs.id
            WHERE va.tenant_id = ?
            {$whereClauses}
            ORDER BY va.id DESC
            LIMIT ?
        ", $params);

        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);

        $items = array_map(fn ($r) => [
            'id' => (int) $r->id,
            'status' => $r->status,
            'message' => $r->message,
            'org_note' => $r->org_note,
            'created_at' => $r->created_at,
            'user' => [
                'id' => (int) $r->user_id,
                'name' => $r->user_name,
                'avatar_url' => $r->user_avatar,
                'email' => $r->user_email,
            ],
            'opportunity' => [
                'id' => (int) $r->opportunity_id,
                'title' => $r->opportunity_title,
            ],
            'shift' => $r->shift_start ? [
                'start_time' => $r->shift_start,
                'end_time' => $r->shift_end,
            ] : null,
        ], $rows);

        $lastItem = end($items);
        $nextCursor = $lastItem ? (string) $lastItem['id'] : null;
        return $this->respondWithCollection($items, $hasMore ? $nextCursor : null, $limit, $hasMore);
    }

    public function orgHoursPending($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_org_hours_pending', 60, 60);
        $org = $this->ensureOrgAccess((int) $id);
        if (!$org) return $this->respondWithError('FORBIDDEN', __('api_controllers_2.volunteer.access_denied'), null, 403);

        $tenantId = TenantContext::getId();
        $orgId = (int) $id;
        $limit = $this->queryInt('per_page', 20, 1, 50);
        $cursor = $this->query('cursor');

        $params = [$orgId, $tenantId];
        $cursorClause = '';
        if ($cursor) {
            $cursorClause = ' AND vl.id < ?';
            $params[] = (int) $cursor;
        }
        $params[] = $limit + 1;

        $rows = DB::select("
            SELECT vl.id, vl.hours, vl.date_logged as date, vl.description, vl.status, vl.created_at,
                   u.id as user_id, u.name as user_name, u.avatar_url as user_avatar,
                   vo.id as opportunity_id, vo.title as opportunity_title
            FROM vol_logs vl
            INNER JOIN users u ON vl.user_id = u.id
            LEFT JOIN vol_opportunities vo ON vl.opportunity_id = vo.id
            WHERE vl.organization_id = ? AND vl.tenant_id = ? AND vl.status = 'pending'
            {$cursorClause}
            ORDER BY vl.id DESC
            LIMIT ?
        ", $params);

        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);

        $items = array_map(fn ($r) => [
            'id' => (int) $r->id,
            'hours' => (float) $r->hours,
            'date' => $r->date,
            'description' => $r->description,
            'status' => $r->status,
            'created_at' => $r->created_at,
            'user' => [
                'id' => (int) $r->user_id,
                'name' => $r->user_name,
                'avatar_url' => $r->user_avatar,
            ],
            'opportunity' => $r->opportunity_id ? [
                'id' => (int) $r->opportunity_id,
                'title' => $r->opportunity_title,
            ] : null,
        ], $rows);

        $lastItem = end($items);
        $nextCursor = $lastItem ? (string) $lastItem['id'] : null;
        return $this->respondWithCollection($items, $hasMore ? $nextCursor : null, $limit, $hasMore);
    }

    public function updateOrganisation($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_org_update', 10, 60);
        $org = $this->ensureOrgAccess((int) $id);
        if (!$org) return $this->respondWithError('FORBIDDEN', __('api_controllers_2.volunteer.access_denied'), null, 403);

        $tenantId = TenantContext::getId();
        $updates = [];
        $params = [];

        $fields = ['name', 'description', 'contact_email', 'website'];
        foreach ($fields as $field) {
            $value = $this->input($field);
            if ($value !== null) {
                $updates[] = "{$field} = ?";
                $params[] = trim($value);
            }
        }

        if (empty($updates)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api_controllers_2.volunteer.no_fields_to_update'), null, 400);
        }

        $params[] = (int) $id;
        $params[] = $tenantId;
        DB::update("UPDATE vol_organizations SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?", $params);

        $updatedOrg = $this->volunteerService->getOrganizationById((int) $id, true);
        return $this->respondWithData($updatedOrg);
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
