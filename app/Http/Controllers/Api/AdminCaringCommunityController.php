<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\CaringCommunity\CaringCommunityAlertService;
use App\Services\CaringCommunity\CaringCommunityForecastService;
use App\Services\CaringCommunity\CaringHourTransferService;
use App\Services\CaringCommunity\CaringNudgeService;
use App\Services\CaringCommunity\CaringRegionalPointService;
use App\Services\CaringCommunity\KpiBaselineService;
use App\Services\CaringCommunity\OperatingPolicyService;
use App\Services\CaringCommunity\PaperOnboardingIntakeService;
use App\Services\CaringCommunity\PilotDisclosurePackService;
use App\Services\CaringCommunity\PilotScoreboardService;
use App\Services\CaringCommunity\SafeguardingService;
use App\Services\CaringCommunity\VereinMemberImportService;
use App\Services\CaringCommunityMemberStatementService;
use App\Services\CaringCommunityRolePresetService;
use App\Services\CaringCommunityWorkflowPolicyService;
use App\Services\CaringCommunityWorkflowService;
use App\Services\CaringInviteCodeService;
use App\Services\CaringLoyaltyService;
use App\Services\CaringSupportRelationshipService;
use App\Services\CaringTandemMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AdminCaringCommunityController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CaringCommunityWorkflowService $workflowService,
        private readonly CaringCommunityRolePresetService $rolePresetService,
        private readonly CaringCommunityWorkflowPolicyService $policyService,
        private readonly CaringCommunityMemberStatementService $memberStatementService,
        private readonly CaringSupportRelationshipService $supportRelationshipService,
        private readonly CaringInviteCodeService $inviteCodeService,
        private readonly CaringTandemMatchingService $tandemMatchingService,
        private readonly CaringLoyaltyService $loyaltyService,
        private readonly CaringCommunityForecastService $forecastService,
        private readonly CaringCommunityAlertService $alertService,
        private readonly CaringHourTransferService $hourTransferService,
        private readonly SafeguardingService $safeguardingService,
        private readonly CaringRegionalPointService $regionalPointService,
        private readonly VereinMemberImportService $vereinMemberImportService,
        private readonly CaringNudgeService $nudgeService,
        private readonly KpiBaselineService $kpiBaselineService,
        private readonly PaperOnboardingIntakeService $paperOnboardingIntakeService,
        private readonly PilotScoreboardService $pilotScoreboardService,
        private readonly OperatingPolicyService $operatingPolicyService,
        private readonly PilotDisclosurePackService $disclosurePackService,
    ) {
    }

    // -------------------------------------------------------------------------
    // Safeguarding Reports
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/admin/caring-community/safeguarding/reports?status=&severity=
     */
    public function safeguardingList(): JsonResponse
    {
        $disabled = $this->guardSafeguarding('view');
        if ($disabled) return $disabled;

        $status = (string) ($this->query('status') ?? '');
        $severity = (string) ($this->query('severity') ?? '');

        return $this->respondWithData([
            'items' => $this->safeguardingService->listReports(
                $status !== '' ? $status : null,
                $severity !== '' ? $severity : null,
            ),
        ]);
    }

    /**
     * GET /api/v2/admin/caring-community/safeguarding/reports/{id}
     */
    public function safeguardingShow(int $id): JsonResponse
    {
        $disabled = $this->guardSafeguarding('view');
        if ($disabled) return $disabled;

        $detail = $this->safeguardingService->reportDetail($id);
        if ($detail === null) {
            return $this->respondWithError('NOT_FOUND', __('api.not_found'), null, 404);
        }

        return $this->respondWithData($detail);
    }

    /**
     * POST /api/v2/admin/caring-community/safeguarding/reports/{id}/assign
     * Body: { assignee_user_id }
     */
    public function safeguardingAssign(int $id): JsonResponse
    {
        $disabled = $this->guardSafeguarding('manage');
        if ($disabled) return $disabled;

        $actorId = (int) auth()->id();
        $assigneeId = $this->inputInt('assignee_user_id', null, 1);
        if ($assigneeId === null) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'assignee_user_id', 422);
        }

        try {
            $this->safeguardingService->assignReport($id, $assigneeId, $actorId);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('NOT_FOUND', $e->getMessage(), null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }

    /**
     * POST /api/v2/admin/caring-community/safeguarding/reports/{id}/escalate
     * Body: { note? }
     */
    public function safeguardingEscalate(int $id): JsonResponse
    {
        $disabled = $this->guardSafeguarding('manage');
        if ($disabled) return $disabled;

        $actorId = (int) auth()->id();
        $note = trim((string) ($this->getAllInput()['note'] ?? ''));

        try {
            $this->safeguardingService->escalateReport($id, $actorId, $note !== '' ? $note : null);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('NOT_FOUND', $e->getMessage(), null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }

    /**
     * POST /api/v2/admin/caring-community/safeguarding/reports/{id}/status
     * Body: { status, notes? }
     */
    public function safeguardingStatus(int $id): JsonResponse
    {
        $disabled = $this->guardSafeguarding('manage');
        if ($disabled) return $disabled;

        $actorId = (int) auth()->id();
        $input = $this->getAllInput();
        $status = (string) ($input['status'] ?? '');
        $notes = trim((string) ($input['notes'] ?? ''));

        try {
            $this->safeguardingService->changeStatus($id, $status, $actorId, $notes !== '' ? $notes : null);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('NOT_FOUND', $e->getMessage(), null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }

    /**
     * POST /api/v2/admin/caring-community/safeguarding/reports/{id}/note
     * Body: { note }
     */
    public function safeguardingNote(int $id): JsonResponse
    {
        $disabled = $this->guardSafeguarding('manage');
        if ($disabled) return $disabled;

        $actorId = (int) auth()->id();
        $note = trim((string) ($this->getAllInput()['note'] ?? ''));

        try {
            $this->safeguardingService->addNote($id, $actorId, $note);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('NOT_FOUND', $e->getMessage(), null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }

    /**
     * GET /api/v2/admin/caring-community/safeguarding/dashboard
     */
    public function safeguardingDashboard(): JsonResponse
    {
        $disabled = $this->guardSafeguarding('view');
        if ($disabled) return $disabled;

        return $this->respondWithData($this->safeguardingService->dashboardSummary());
    }

    /**
     * Combined feature + permission guard for safeguarding endpoints.
     *
     * @param string $level 'view' or 'manage'
     */
    private function guardSafeguarding(string $level): ?JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        // Coordinators / admins / super-admins always pass. Otherwise check
        // whether the user holds the safeguarding.view permission via RBAC.
        $userId = (int) auth()->id();
        $user = \DB::table('users')->where('id', $userId)->first(['role', 'is_super_admin', 'is_tenant_super_admin']);
        $role = $user ? (string) ($user->role ?? 'member') : 'member';

        if (in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god', 'coordinator', 'broker'], true)) {
            return null;
        }
        if ($user && (($user->is_super_admin ?? false) || ($user->is_tenant_super_admin ?? false))) {
            return null;
        }

        // Check permission tables — schema may vary, do this defensively
        try {
            $perm = (string) ($level === 'manage' ? 'safeguarding.manage' : 'safeguarding.view');
            $hasPerm = \DB::table('user_permissions as up')
                ->join('permissions as p', 'p.id', '=', 'up.permission_id')
                ->where('up.user_id', $userId)
                ->whereIn('p.name', [$perm, 'safeguarding.view'])
                ->exists();
            if ($hasPerm) {
                return null;
            }
        } catch (\Throwable $e) {
            // If permission tables aren't shaped as expected, fall through to deny.
        }

        return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.admin_access_required'), null, 403);
    }

    /**
     * GET /api/v2/admin/caring-community/hour-transfer/pending
     *
     * Pending outbound transfers — initiated by members of THIS tenant
     * awaiting admin approval before funds are debited.
     */
    public function hourTransferPending(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData([
            'items' => $this->hourTransferService->pendingAtSource(),
        ]);
    }

    /**
     * POST /api/v2/admin/caring-community/hour-transfer/{id}/approve
     */
    public function hourTransferApprove(int $id): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $approverId = $this->requireAdmin();

        try {
            $result = $this->hourTransferService->approveAtSource($id, $approverId);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('TRANSFER_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData($result + ['success' => true]);
    }

    /**
     * POST /api/v2/admin/caring-community/hour-transfer/{id}/reject
     * Body: { reason? }
     */
    public function hourTransferReject(int $id): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $approverId = $this->requireAdmin();
        $reason = trim((string) ($this->getAllInput()['reason'] ?? ''));

        try {
            $this->hourTransferService->rejectAtSource($id, $approverId, $reason);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('TRANSFER_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData(['success' => true, 'status' => 'rejected']);
    }

    /**
     * GET /api/v2/admin/caring-community/hour-transfer/inbound
     *
     * Recent inbound transfers — received from other cooperatives in the last 90 days.
     */
    public function hourTransferInbound(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData([
            'items' => $this->hourTransferService->recentAtDestination(),
        ]);
    }

    /**
     * GET /api/v2/admin/caring-community/forecast
     *
     * Forward-looking coordinator dashboard — Tom Debus's AI/Daten pillar.
     * Linear regression on the past 6 months projects the next 3 months
     * of approved hours, distinct active members, and recipients reached.
     * Proactive alerts surface signals the coordinator should act on now.
     */
    public function forecast(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData([
            'hours'        => $this->forecastService->forecastHours(3),
            'members'      => $this->forecastService->forecastMembers(3),
            'recipients'   => $this->forecastService->forecastRecipients(3),
            'alerts'       => $this->alertService->activeAlerts(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function workflow(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->workflowService->summary(TenantContext::getId()));
    }

    public function rolePresets(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->rolePresetService->status(TenantContext::getId()));
    }

    public function installRolePresets(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $preset = request()->input('preset');
        $presetKey = is_string($preset) && $preset !== '' ? $preset : null;

        return $this->respondWithData($this->rolePresetService->install(TenantContext::getId(), $presetKey));
    }

    public function updatePolicy(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->policyService->update(TenantContext::getId(), $this->getAllInput()));
    }

    public function assignReview(int $id): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $assigneeId = $this->inputInt('assigned_to', null, 1);
        $review = $this->workflowService->assignReview(TenantContext::getId(), $id, $assigneeId);
        if (!$review) {
            return $this->respondWithError('NOT_FOUND', __('api.caring_review_assignment_failed'), null, 404);
        }

        return $this->respondWithData([
            'review' => $review,
            'message' => __('api.caring_review_assigned'),
        ]);
    }

    public function escalateReview(int $id): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $review = $this->workflowService->escalateReview(TenantContext::getId(), $id, (string) request()->input('note', ''));
        if (!$review) {
            return $this->respondWithError('NOT_FOUND', __('api.caring_review_escalation_failed'), null, 404);
        }

        return $this->respondWithData([
            'review' => $review,
            'message' => __('api.caring_review_escalated'),
        ]);
    }

    public function decideReview(int $id): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $action = (string) request()->input('action', '');
        if (!in_array($action, ['approve', 'decline'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.decision_required'), 'action', 400);
        }

        $result = $this->workflowService->decideReview(TenantContext::getId(), $id, (int) auth()->id(), $action);
        if (!$result) {
            return $this->respondWithError('NOT_FOUND', __('api.caring_review_decision_failed'), null, 404);
        }

        return $this->respondWithData([
            'review' => $result,
            'message' => $action === 'approve' ? __('api.caring_review_approved') : __('api.caring_review_declined'),
        ]);
    }

    public function memberStatement(int $userId): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $filters = [
            'start_date' => $this->query('start_date'),
            'end_date' => $this->query('end_date'),
        ];
        $format = (string) $this->query('format', 'json');

        if ($format === 'csv') {
            $csv = $this->memberStatementService->csv(TenantContext::getId(), $userId, $filters);
            if (!$csv) {
                return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
            }

            return $this->respondWithData($csv);
        }

        $statement = $this->memberStatementService->statement(TenantContext::getId(), $userId, $filters);
        if (!$statement) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        return $this->respondWithData($statement);
    }

    public function supportRelationships(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->supportRelationshipService->list(TenantContext::getId(), [
            'status' => $this->query('status', 'active'),
        ]));
    }

    public function createSupportRelationship(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $tenantId = TenantContext::getId();
        $coordinatorId = (int) auth()->id();
        $input = $this->getAllInput();
        $result = $this->supportRelationshipService->create($tenantId, $input, $coordinatorId);
        if (($result['success'] ?? false) !== true) {
            $code = (string) ($result['code'] ?? 'VALIDATION_ERROR');
            $message = $code === 'USER_NOT_FOUND'
                ? __('api.user_not_found')
                : __('api.caring_support_relationship_create_failed');
            return $this->respondWithError($code, $message, null, 422);
        }

        // Log so we don't keep re-suggesting this pair to coordinators.
        $supporterId = (int) ($input['supporter_id'] ?? 0);
        $recipientId = (int) ($input['recipient_id'] ?? 0);
        if ($supporterId > 0 && $recipientId > 0) {
            $this->tandemMatchingService->markSuggestionAsConsidered(
                $tenantId,
                $supporterId,
                $recipientId,
                'created_relationship',
                $coordinatorId,
            );
        }

        return $this->respondWithData($result['relationship'], null, 201);
    }

    /**
     * GET /api/v2/admin/caring-community/tandem-suggestions
     *
     * Returns coordinator-ready supporter/recipient pair suggestions.
     */
    public function tandemSuggestions(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $limit = $this->queryInt('limit', 20, 1, 100);
        $suggestions = $this->tandemMatchingService->suggestTandems(TenantContext::getId(), $limit);

        return $this->respondWithData([
            'suggestions' => $suggestions,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v2/admin/caring-community/tandem-suggestions/dismiss
     *
     * Suppress a suggested pair for 90 days so it stops appearing in the list.
     */
    public function dismissTandemSuggestion(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $supporterId = $this->inputInt('supporter_id', null, 1);
        $recipientId = $this->inputInt('recipient_id', null, 1);
        if ($supporterId === null || $recipientId === null || $supporterId === $recipientId) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.validation_failed'), null, 422);
        }

        $this->tandemMatchingService->markSuggestionAsConsidered(
            TenantContext::getId(),
            $supporterId,
            $recipientId,
            'dismissed',
            (int) auth()->id(),
        );

        return $this->respondWithData(['success' => true]);
    }

    public function nudgeAnalytics(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->nudgeService->analytics(TenantContext::getId()));
    }

    public function updateNudgeConfig(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData([
            'config' => $this->nudgeService->updateConfig(TenantContext::getId(), $this->getAllInput()),
        ]);
    }

    public function dispatchNudges(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $dryRun = filter_var($this->getAllInput()['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $limit = $this->inputInt('limit', null, 1, 250);

        return $this->respondWithData(
            $this->nudgeService->dispatchDue(TenantContext::getId(), $limit, $dryRun),
            null,
            $dryRun ? 200 : 201,
        );
    }

    public function updateSupportRelationship(int $id): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $relationship = $this->supportRelationshipService->update(TenantContext::getId(), $id, $this->getAllInput());
        if (!$relationship) {
            return $this->respondWithError('NOT_FOUND', __('api.caring_support_relationship_not_found'), null, 404);
        }

        return $this->respondWithData($relationship);
    }

    public function logSupportRelationshipHours(int $id): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $result = $this->supportRelationshipService->logHours(TenantContext::getId(), $id, $this->getAllInput(), (int) auth()->id());
        if (($result['success'] ?? false) !== true) {
            $code = (string) ($result['code'] ?? 'VALIDATION_ERROR');
            $message = match ($code) {
                'NOT_FOUND' => __('api.caring_support_relationship_not_found'),
                'RELATIONSHIP_INACTIVE' => __('api.caring_support_relationship_inactive'),
                'ALREADY_EXISTS' => __('api.caring_support_relationship_log_duplicate'),
                default => __('api.caring_support_relationship_log_failed'),
            };

            return $this->respondWithError($code, $message, null, $code === 'NOT_FOUND' ? 404 : 422);
        }

        return $this->respondWithData($result, null, 201);
    }

    /**
     * POST /api/v2/admin/caring-community/assisted-onboarding
     *
     * Coordinator creates a member account on behalf of a participant who
     * cannot self-register (e.g. elderly, non-technical). Returns a temporary
     * password the coordinator can share with the new member in person.
     */
    public function assistedOnboarding(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $guard = $this->guardCaringCommunity();
        if ($guard !== null) return $guard;

        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        $fullName = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $note = trim((string) ($input['note'] ?? ''));

        // Validate
        $errors = [];
        if ($fullName === '') {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.first_name_required'), 'field' => 'name'];
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.valid_email_required'), 'field' => 'email'];
        }
        if (!empty($errors)) {
            return $this->respondWithErrors($errors, 422);
        }

        // Duplicate email check
        if (User::findByEmail($email)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.email_already_exists'), 'email', 422);
        }

        // Split name into first / last
        $parts = explode(' ', $fullName, 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';

        // Generate a secure temporary password
        $tempPassword = substr(bin2hex(random_bytes(12)), 0, 16);

        $newUserId = User::createWithTenant([
            'first_name'  => $firstName,
            'last_name'   => $lastName,
            'email'       => $email,
            'password'    => $tempPassword,
            'phone'       => $phone ?: null,
            'role'        => 'member',
            'is_approved' => 1,
        ], $tenantId);

        if (!$newUserId) {
            return $this->respondWithError('SERVER_ERROR', __('api.user_created_failed'), null, 500);
        }

        ActivityLog::log($adminId, 'coordinator_assisted_onboarding', "Coordinator-assisted onboarding: {$email}" . ($note ? " — {$note}" : ''));

        // Send welcome email if the email looks real (skip dummy placeholder addresses)
        $isDummy = str_ends_with($email, '.invalid') || str_ends_with($email, '.placeholder');
        if (!$isDummy) {
            try {
                $newUser = User::findById($newUserId, true);
                LocaleContext::withLocale($newUser['preferred_language'] ?? null, function () use ($email, $tempPassword) {
                    $tenant = TenantContext::get();
                    $tenantName = $tenant['name'] ?? 'Project NEXUS';
                    $loginLink = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/login';

                    $html = EmailTemplateBuilder::make()
                        ->title(__('emails_misc.admin_actions.welcome_created_title'))
                        ->previewText(__('emails_misc.admin_actions.welcome_created_preview'))
                        ->greeting(__('emails_misc.admin_actions.welcome_created_greeting', ['community' => $tenantName]))
                        ->paragraph(__('emails_misc.admin_actions.welcome_created_body_intro', ['community' => $tenantName]))
                        ->paragraph(__('emails_misc.admin_actions.welcome_created_body_credentials'))
                        ->infoCard([
                            __('emails_misc.admin_actions.welcome_created_info_email')    => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
                            __('emails_misc.admin_actions.welcome_created_info_password') => htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8'),
                        ])
                        ->paragraph(__('emails_misc.admin_actions.welcome_created_body_change_pass'))
                        ->button(__('emails_misc.admin_actions.welcome_created_cta'), $loginLink)
                        ->render();

                    $mailer = Mailer::forCurrentTenant();
                    $mailer->send($email, __('emails_misc.admin_actions.welcome_created_subject', ['community' => $tenantName]), $html);
                });
            } catch (\Throwable $e) {
                Log::warning('[AdminCC] Assisted onboarding welcome email failed: ' . $e->getMessage());
            }
        }

        return $this->respondWithData([
            'success'       => true,
            'user'          => [
                'id'    => $newUserId,
                'name'  => trim("{$firstName} {$lastName}"),
                'email' => $email,
            ],
            'temp_password' => $tempPassword,
        ], null, 201);
    }

    /**
     * GET /api/v2/admin/caring-community/paper-onboarding
     */
    public function paperOnboardingList(): JsonResponse
    {
        $this->requireAdmin();
        $guard = $this->guardCaringCommunity();
        if ($guard !== null) return $guard;

        $status = trim((string) request()->query('status', 'pending_review'));
        $limit = max(1, min(100, (int) request()->query('limit', 20)));

        return $this->respondWithData(
            $this->paperOnboardingIntakeService->list(TenantContext::getId(), $status, $limit)
        );
    }

    /**
     * POST /api/v2/admin/caring-community/paper-onboarding
     *
     * Coordinator uploads a scanned KISS consent/onboarding form. OCR is
     * intentionally behind PaperOnboardingIntakeService so this endpoint stays
     * stable when a real OCR provider replaces the manual-review stub.
     */
    public function paperOnboardingUpload(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $guard = $this->guardCaringCommunity();
        if ($guard !== null) return $guard;

        $file = request()->file('file');
        if (!$file || !$file->isValid()) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.caring_paper_onboarding_file_required'), 'file', 422);
        }

        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array((string) $file->getMimeType(), $allowedMimeTypes, true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.caring_paper_onboarding_invalid_file_type'), 'file', 422);
        }

        if ((int) $file->getSize() > 10 * 1024 * 1024) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.caring_paper_onboarding_file_too_large'), 'file', 422);
        }

        $input = $this->getAllInput();
        $intake = $this->paperOnboardingIntakeService->createFromUpload(TenantContext::getId(), $adminId, $file, [
            'name' => $input['name'] ?? null,
            'date_of_birth' => $input['date_of_birth'] ?? null,
            'address' => $input['address'] ?? null,
            'phone' => $input['phone'] ?? null,
            'email' => $input['email'] ?? null,
        ]);

        ActivityLog::log($adminId, 'caring_paper_onboarding_uploaded', 'Paper onboarding form uploaded for coordinator review');

        return $this->respondWithData($intake, null, 201);
    }

    /**
     * POST /api/v2/admin/caring-community/paper-onboarding/{id}/confirm
     */
    public function paperOnboardingConfirm(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $guard = $this->guardCaringCommunity();
        if ($guard !== null) return $guard;

        $result = $this->paperOnboardingIntakeService->confirm(
            TenantContext::getId(),
            $id,
            $adminId,
            $this->getAllInput()
        );

        if (($result['success'] ?? false) !== true) {
            $code = (string) ($result['code'] ?? 'CREATE_FAILED');
            $message = match ($code) {
                'NOT_FOUND' => __('api.caring_paper_onboarding_not_found'),
                'ALREADY_REVIEWED' => __('api.caring_paper_onboarding_already_reviewed'),
                'EMAIL_EXISTS' => __('api.email_already_exists'),
                'VALIDATION_ERROR' => __('api.caring_paper_onboarding_review_required'),
                default => __('api.user_created_failed'),
            };
            $status = $code === 'NOT_FOUND' ? 404 : 422;

            return $this->respondWithError($code, $message, null, $status);
        }

        $user = $result['user'] ?? [];
        $email = is_array($user) ? (string) ($user['email'] ?? '') : '';
        ActivityLog::log($adminId, 'caring_paper_onboarding_confirmed', "Paper onboarding confirmed: {$email}");

        return $this->respondWithData($result, null, 201);
    }

    /**
     * POST /api/v2/admin/caring-community/invite-codes
     *
     * Generate a new invite code for a coordinator to share with a prospective
     * member. The code allows the recipient to reach a warm-welcome page and
     * continue to registration without needing an email invitation.
     */
    public function generateInviteCode(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $guard = $this->guardCaringCommunity();
        if ($guard !== null) return $guard;

        $tenantId = TenantContext::getId();
        $input    = $this->getAllInput();

        $label       = trim((string) ($input['label'] ?? ''));
        $expiresDays = max(1, min(365, (int) ($input['expires_days'] ?? 30)));

        $result = $this->inviteCodeService->generate($tenantId, $adminId, $label ?: null, $expiresDays);

        if (($result['success'] ?? false) !== true) {
            return $this->respondWithError('SERVER_ERROR', __('api.server_error'), null, 500);
        }

        return $this->respondWithData($result['code'], null, 201);
    }

    /**
     * GET /api/v2/admin/caring-community/invite-codes
     *
     * List the last 20 invite codes created for this tenant, with usage status.
     */
    public function listInviteCodes(): JsonResponse
    {
        $guard = $this->guardCaringCommunity();
        if ($guard !== null) return $guard;

        return $this->respondWithData(
            $this->inviteCodeService->list(TenantContext::getId())
        );
    }

    /**
     * GET /api/v2/admin/caring-community/favours
     *
     * Returns the last 50 informal favours recorded for this tenant.
     * Hides the offerer's name when is_anonymous is true.
     */
    public function listFavours(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $tenantId = TenantContext::getId();

        if (!\Illuminate\Support\Facades\Schema::hasTable('caring_favours')) {
            return $this->respondWithData(['count' => 0, 'items' => []]);
        }

        $rows = \Illuminate\Support\Facades\DB::select(
            "SELECT
                cf.id,
                cf.category,
                cf.description,
                cf.favour_date,
                cf.is_anonymous,
                cf.created_at,
                u.name        AS offerer_name,
                u.first_name  AS offerer_first_name,
                u.last_name   AS offerer_last_name
             FROM caring_favours cf
             LEFT JOIN users u
                    ON u.id = cf.offered_by_user_id
                   AND u.tenant_id = cf.tenant_id
             WHERE cf.tenant_id = ?
             ORDER BY cf.created_at DESC
             LIMIT 50",
            [$tenantId]
        );

        $total = (int) \Illuminate\Support\Facades\DB::table('caring_favours')
            ->where('tenant_id', $tenantId)
            ->count();

        $items = array_map(static function (object $row): array {
            $isAnon = (bool) $row->is_anonymous;
            $name = '';
            if (!$isAnon) {
                $full = trim((string) ($row->offerer_first_name ?? '') . ' ' . (string) ($row->offerer_last_name ?? ''));
                $name = $full !== '' ? $full : (string) ($row->offerer_name ?? '');
            }

            return [
                'id'          => (int) $row->id,
                'category'    => $row->category,
                'description' => (string) $row->description,
                'favour_date' => (string) $row->favour_date,
                'is_anonymous' => $isAnon,
                'offerer_name' => $isAnon ? null : $name,
                'created_at'  => (string) $row->created_at,
            ];
        }, $rows);

        return $this->respondWithData([
            'count' => $total,
            'items' => $items,
        ]);
    }

    /**
     * GET /api/v2/admin/caring-community/loyalty/redemptions
     *
     * Returns the most recent tenant-wide loyalty redemptions plus
     * aggregate stats for the KISS Workflow Console card.
     */
    public function listLoyaltyRedemptions(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $limit = (int) (request()->query('limit') ?? 50);

        return $this->respondWithData([
            'stats'       => $this->loyaltyService->tenantStats(),
            'redemptions' => $this->loyaltyService->listTenantRedemptions($limit),
        ]);
    }

    /**
     * GET /api/v2/admin/caring-community/loyalty/seller-settings/{userId}
     *
     * Read a single seller's loyalty configuration. Returns sane defaults
     * when no row exists yet so the admin form can render.
     */
    public function getLoyaltySellerSettings(int $userId): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->loyaltyService->getSellerSettings($userId));
    }

    /**
     * PUT /api/v2/admin/caring-community/loyalty/seller-settings
     *
     * Body: { seller_user_id, accepts_time_credits, loyalty_chf_per_hour, loyalty_max_discount_pct }
     */
    public function updateLoyaltySellerSettings(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $input = $this->getAllInput();

        $sellerId = (int) ($input['seller_user_id'] ?? 0);
        if ($sellerId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'seller_user_id', 422);
        }

        try {
            $result = $this->loyaltyService->updateSellerSettings(
                sellerId:           $sellerId,
                acceptsTimeCredits: (bool) ($input['accepts_time_credits'] ?? false),
                chfPerHour:         (float) ($input['loyalty_chf_per_hour'] ?? 25.0),
                maxDiscountPct:     (int) ($input['loyalty_max_discount_pct'] ?? 50),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 503);
        }

        return $this->respondWithData($result);
    }

    public function getRegionalPointSellerSettings(int $userId): JsonResponse
    {
        $disabled = $this->guardRegionalPoints();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->regionalPointService->getMarketplaceSellerSettings($userId));
    }

    public function updateRegionalPointSellerSettings(): JsonResponse
    {
        $disabled = $this->guardRegionalPoints();
        if ($disabled) return $disabled;

        $input = $this->getAllInput();
        $sellerId = (int) ($input['seller_user_id'] ?? 0);
        if ($sellerId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'seller_user_id', 422);
        }

        try {
            $result = $this->regionalPointService->updateMarketplaceSellerSettings(
                sellerId: $sellerId,
                acceptsRegionalPoints: (bool) ($input['accepts_regional_points'] ?? false),
                pointsPerChf: (float) ($input['regional_points_per_chf'] ?? 10.0),
                maxDiscountPct: (int) ($input['regional_points_max_discount_pct'] ?? 25),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('REGIONAL_POINTS_SELLER_SETTINGS_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData($result);
    }

    public function regionalPointsConfig(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->regionalPointService->getConfig(TenantContext::getId()));
    }

    public function updateRegionalPointsConfig(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData(
            $this->regionalPointService->updateConfig(TenantContext::getId(), $this->getAllInput())
        );
    }

    public function regionalPointsLedger(): JsonResponse
    {
        $disabled = $this->guardRegionalPoints();
        if ($disabled) return $disabled;

        try {
            return $this->respondWithData([
                'stats' => $this->regionalPointService->tenantStats(),
                'items' => $this->regionalPointService->tenantLedger((int) ($this->query('limit') ?? 100)),
            ]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    public function issueRegionalPoints(): JsonResponse
    {
        $disabled = $this->guardRegionalPoints();
        if ($disabled) return $disabled;

        $actorId = $this->requireAdmin();
        $input = $this->getAllInput();
        $userId = (int) ($input['user_id'] ?? 0);
        $points = (float) ($input['points'] ?? 0);
        $description = (string) ($input['description'] ?? '');

        if ($userId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'user_id', 422);
        }

        try {
            return $this->respondWithData(
                $this->regionalPointService->issue($userId, $points, $description, $actorId),
                null,
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    public function adjustRegionalPoints(): JsonResponse
    {
        $disabled = $this->guardRegionalPoints();
        if ($disabled) return $disabled;

        $actorId = $this->requireAdmin();
        $input = $this->getAllInput();
        $userId = (int) ($input['user_id'] ?? 0);
        $pointsDelta = (float) ($input['points_delta'] ?? 0);
        $description = (string) ($input['description'] ?? '');

        if ($userId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'user_id', 422);
        }

        try {
            return $this->respondWithData(
                $this->regionalPointService->adjust($userId, $pointsDelta, $description, $actorId)
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('REGIONAL_POINTS_FAILED', $e->getMessage(), null, 422);
        }
    }

    public function previewVereinMemberImport(int $organizationId): JsonResponse
    {
        $disabled = $this->guardCaringCommunityFeature();
        if ($disabled) return $disabled;

        $actorId = $this->requireAuth();
        $tenantId = TenantContext::getId();
        if (!$this->canManageVereinMembers($tenantId, $actorId, $organizationId)) {
            return $this->respondWithError('FORBIDDEN', __('api.admin_access_required'), null, 403);
        }

        $csv = (string) ($this->getAllInput()['csv'] ?? '');
        try {
            return $this->respondWithData(
                $this->vereinMemberImportService->preview($tenantId, $organizationId, $csv)
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('VEREIN_IMPORT_UNAVAILABLE', $e->getMessage(), null, 503);
        }
    }

    public function importVereinMembers(int $organizationId): JsonResponse
    {
        $disabled = $this->guardCaringCommunityFeature();
        if ($disabled) return $disabled;

        $actorId = $this->requireAuth();
        $tenantId = TenantContext::getId();
        if (!$this->canManageVereinMembers($tenantId, $actorId, $organizationId)) {
            return $this->respondWithError('FORBIDDEN', __('api.admin_access_required'), null, 403);
        }

        $csv = (string) ($this->getAllInput()['csv'] ?? '');
        try {
            return $this->respondWithData(
                $this->vereinMemberImportService->import($tenantId, $organizationId, $actorId, $csv),
                null,
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('VEREIN_IMPORT_UNAVAILABLE', $e->getMessage(), null, 503);
        }
    }

    public function assignVereinAdmin(int $organizationId): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $actorId = $this->requireAdmin();
        $userId = (int) (($this->getAllInput()['user_id'] ?? 0));
        if ($userId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'user_id', 422);
        }

        try {
            return $this->respondWithData(
                $this->vereinMemberImportService->assignVereinAdmin(TenantContext::getId(), $organizationId, $userId, $actorId),
                null,
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('VEREIN_ADMIN_UNAVAILABLE', $e->getMessage(), null, 503);
        }
    }

    // -------------------------------------------------------------------------
    // AG66 — KPI Baseline methods
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/admin/caring-community/kpi-baselines
     */
    public function listKpiBaselines(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData($this->kpiBaselineService->listBaselines(TenantContext::getId()));
    }

    /**
     * POST /api/v2/admin/caring-community/kpi-baselines
     * Body: { label?, period?: { start, end }, notes? }
     */
    public function captureKpiBaseline(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $userId   = (int) auth()->id();
        $tenantId = TenantContext::getId();
        $data     = $this->getAllInput();

        $label  = trim((string) ($data['label'] ?? '')) ?: 'Baseline ' . now()->format('Y-m-d');
        $period = is_array($data['period'] ?? null)
            ? $data['period']
            : ['start' => now()->subYear()->toDateString(), 'end' => now()->toDateString()];
        $notes  = isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null;
        $metricOverrides = is_array($data['metrics'] ?? null) ? $data['metrics'] : [];

        return $this->respondWithData(
            $this->kpiBaselineService->captureBaseline($tenantId, $label, $period, $notes, $userId, $metricOverrides),
            null,
            201,
        );
    }

    /**
     * GET /api/v2/admin/caring-community/kpi-baselines/{id}/compare
     */
    public function compareKpiBaseline(int $id): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $result = $this->kpiBaselineService->compareWithBaseline($id, TenantContext::getId());
        if (isset($result['error']) && $result['error'] === 'baseline_not_found') {
            return $this->respondWithError('NOT_FOUND', __('api.not_found'), null, 404);
        }

        return $this->respondWithData($result);
    }

    // -------------------------------------------------------------------------
    // Warmth Pass — Care Recipient Circle
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/admin/caring-community/recipient/{userId}/circle
     *
     * Returns the support network (circle) for a care recipient, together with
     * open help requests and any safeguarding flags.
     */
    public function recipientCircle(int $userId): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $tenantId = TenantContext::getId();

        // Recipient user details
        $recipient = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($recipient === null) {
            return $this->respondWithError('NOT_FOUND', __('api.user_not_found'), null, 404);
        }

        $recipientName = '';
        if (!empty($recipient->name)) {
            $recipientName = (string) $recipient->name;
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('users', 'first_name')) {
            $recipientName = trim(
                ((string) ($recipient->first_name ?? '')) . ' ' . ((string) ($recipient->last_name ?? ''))
            );
        }

        $memberSince = null;
        if (!empty($recipient->created_at)) {
            try {
                $memberSince = (string) \Carbon\Carbon::parse((string) $recipient->created_at)->toDateString();
            } catch (\Throwable) {
                $memberSince = null;
            }
        }

        // Support relationships + supporters
        $relationships = [];
        $totalHoursReceived = 0.0;

        if (\Illuminate\Support\Facades\Schema::hasTable('caring_support_relationships')) {
            $hasRelationshipId = \Illuminate\Support\Facades\Schema::hasColumn('vol_logs', 'caring_support_relationship_id');

            $rows = \Illuminate\Support\Facades\DB::table('caring_support_relationships as csr')
                ->join('users as u', 'u.id', '=', 'csr.supporter_user_id')
                ->where('csr.recipient_user_id', $userId)
                ->where('csr.tenant_id', $tenantId)
                ->where('csr.status', 'active')
                ->select([
                    'csr.id',
                    'csr.supporter_user_id',
                    'csr.relationship_type',
                    'csr.last_activity_at',
                    'csr.status',
                    'u.name as supporter_name',
                    'u.first_name as supporter_first_name',
                    'u.last_name as supporter_last_name',
                    'u.trust_tier as supporter_trust_tier',
                ])
                ->get();

            foreach ($rows as $row) {
                $relId = (int) $row->id;

                // Hours for this relationship
                $hoursForRel = 0.0;
                if ($hasRelationshipId && \Illuminate\Support\Facades\Schema::hasTable('vol_logs')) {
                    $hoursForRel = (float) \Illuminate\Support\Facades\DB::table('vol_logs')
                        ->where('caring_support_relationship_id', $relId)
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'approved')
                        ->sum('hours');
                }

                $totalHoursReceived += $hoursForRel;

                // Supporter name
                $supporterName = '';
                if (!empty($row->supporter_name)) {
                    $supporterName = (string) $row->supporter_name;
                } else {
                    $supporterName = trim(
                        ((string) ($row->supporter_first_name ?? '')) . ' ' . ((string) ($row->supporter_last_name ?? ''))
                    );
                }

                $lastActivityAt = null;
                if (!empty($row->last_activity_at)) {
                    try {
                        $lastActivityAt = (string) \Carbon\Carbon::parse((string) $row->last_activity_at)->toIso8601String();
                    } catch (\Throwable) {
                        $lastActivityAt = null;
                    }
                }

                $relationships[] = [
                    'id'               => $relId,
                    'supporter'        => [
                        'id'         => (int) $row->supporter_user_id,
                        'name'       => $supporterName,
                        'trust_tier' => (int) ($row->supporter_trust_tier ?? 0),
                    ],
                    'type'             => (string) ($row->relationship_type ?? ''),
                    'hours_logged'     => $hoursForRel,
                    'last_activity_at' => $lastActivityAt,
                    'status'           => (string) ($row->status ?? ''),
                ];
            }
        }

        // Open help requests
        $openHelpRequests = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('caring_help_requests')) {
            $closedStatuses = ['matched', 'cancelled', 'closed'];
            $placeholders   = implode(',', array_fill(0, count($closedStatuses), '?'));

            $openHelpRequests = (int) \Illuminate\Support\Facades\DB::table('caring_help_requests')
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', $closedStatuses)
                ->count();
        }

        // Safeguarding flags
        $safeguardingFlags = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('safeguarding_reports')) {
            $subjectCol = 'subject_user_id';
            if (!\Illuminate\Support\Facades\Schema::hasColumn('safeguarding_reports', $subjectCol)) {
                $subjectCol = \Illuminate\Support\Facades\Schema::hasColumn('safeguarding_reports', 'reported_user_id')
                    ? 'reported_user_id'
                    : null;
            }

            if ($subjectCol !== null) {
                $safeguardingFlags = (int) \Illuminate\Support\Facades\DB::table('safeguarding_reports')
                    ->where($subjectCol, $userId)
                    ->where('tenant_id', $tenantId)
                    ->count();
            }
        }

        return $this->respondWithData([
            'recipient'              => [
                'id'           => $userId,
                'name'         => $recipientName,
                'trust_tier'   => (int) ($recipient->trust_tier ?? 0),
                'member_since' => $memberSince,
            ],
            'support_relationships'  => $relationships,
            'total_hours_received'   => $totalHoursReceived,
            'open_help_requests'     => $openHelpRequests,
            'safeguarding_flags'     => $safeguardingFlags,
        ]);
    }

    // -------------------------------------------------------------------------
    // Municipal ROI
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/admin/caring-community/municipal-roi
     *
     * Aggregated ROI metrics for municipal/cooperative impact reporting.
     * Uses Swiss formal care assistant hourly rate (CHF 35) and the
     * prevention multiplier (×2) from the KISS/Age-Stiftung methodology.
     */
    public function municipalRoi(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $tenantId = TenantContext::getId();
        $now      = now();
        $oneYearAgo     = $now->copy()->subYear();
        $twoYearsAgo    = $now->copy()->subYears(2);

        // Total approved hours (all time)
        $totalHours = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('vol_logs')) {
            $totalHours = (float) \Illuminate\Support\Facades\DB::table('vol_logs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->sum('hours');
        }

        // Active members (distinct users with ≥1 approved log in the last year)
        $activeMembers = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('vol_logs')) {
            $activeMembers = (int) \Illuminate\Support\Facades\DB::table('vol_logs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where('created_at', '>=', $oneYearAgo->toDateTimeString())
                ->distinct('user_id')
                ->count('user_id');
        }

        // Active support relationships
        $activeRelationships = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('caring_support_relationships')) {
            $activeRelationships = (int) \Illuminate\Support\Facades\DB::table('caring_support_relationships')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count();
        }

        // Distinct recipients
        $recipientCount = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('caring_support_relationships')) {
            $recipientCount = (int) \Illuminate\Support\Facades\DB::table('caring_support_relationships')
                ->where('tenant_id', $tenantId)
                ->distinct('recipient_user_id')
                ->count('recipient_user_id');
        }

        // Total approved exchanges
        $totalExchanges = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('vol_logs')) {
            $totalExchanges = (int) \Illuminate\Support\Facades\DB::table('vol_logs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->count();
        }

        // Trend: hours in last 12 months vs 12–24 months ago
        $hoursThisYear = 0.0;
        $hoursPriorYear = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('vol_logs')) {
            $hoursThisYear = (float) \Illuminate\Support\Facades\DB::table('vol_logs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where('created_at', '>=', $oneYearAgo->toDateTimeString())
                ->sum('hours');

            $hoursPriorYear = (float) \Illuminate\Support\Facades\DB::table('vol_logs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where('created_at', '>=', $twoYearsAgo->toDateTimeString())
                ->where('created_at', '<', $oneYearAgo->toDateTimeString())
                ->sum('hours');
        }

        $hoursYoyPct = null;
        if ($hoursPriorYear > 0) {
            $hoursYoyPct = round((($hoursThisYear - $hoursPriorYear) / $hoursPriorYear) * 100, 2);
        }

        // ROI computation (Swiss formal care assistant rate)
        $hourlyRateChf          = 35;
        $formalCareOffsetChf    = round($totalHours * $hourlyRateChf, 2);
        $preventionValueChf     = round($formalCareOffsetChf * 2, 2);

        return $this->respondWithData([
            'total_hours'          => $totalHours,
            'active_members'       => $activeMembers,
            'active_relationships' => $activeRelationships,
            'recipient_count'      => $recipientCount,
            'total_exchanges'      => $totalExchanges,
            'roi'                  => [
                'hourly_rate_chf'            => $hourlyRateChf,
                'formal_care_offset_chf'     => $formalCareOffsetChf,
                'prevention_value_chf'       => $preventionValueChf,
                'social_isolation_prevented' => $recipientCount,
            ],
            'trend'                => [
                'hours_yoy_pct' => $hoursYoyPct,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // AG83 — Pilot Success Scoreboard
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/admin/caring-community/pilot-scoreboard
     */
    public function pilotScoreboard(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData(
            $this->pilotScoreboardService->scoreboard(TenantContext::getId())
        );
    }

    /**
     * GET /api/v2/admin/caring-community/pilot-scoreboard/baselines
     */
    public function pilotScoreboardBaselines(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData([
            'items' => $this->pilotScoreboardService->listBaselines(TenantContext::getId()),
        ]);
    }

    /**
     * POST /api/v2/admin/caring-community/pilot-scoreboard/pre-pilot
     * Body: { notes? }
     */
    public function capturePrePilotBaseline(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $actorId = (int) auth()->id();
        $notes = $this->input('notes');
        $notes = is_string($notes) ? trim($notes) : null;
        $notes = $notes === '' ? null : $notes;

        $baseline = $this->pilotScoreboardService->capturePrePilotBaseline(
            TenantContext::getId(),
            $actorId,
            $notes,
        );

        if (isset($baseline['error'])) {
            return $this->respondWithError('UNAVAILABLE', __('api.service_unavailable'), null, 503);
        }

        return $this->respondWithData($baseline);
    }

    /**
     * POST /api/v2/admin/caring-community/pilot-scoreboard/quarterly
     * Body: { label?, notes? }
     */
    public function captureQuarterlyReview(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $actorId = (int) auth()->id();
        $rawLabel = $this->input('label');
        $label = is_string($rawLabel) && trim($rawLabel) !== ''
            ? mb_substr(trim($rawLabel), 0, 120)
            : 'quarterly_' . now()->format('Y_m');

        if ($label === PilotScoreboardService::PRE_PILOT_LABEL) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.field_required'),
                'label',
                422,
            );
        }

        $notes = $this->input('notes');
        $notes = is_string($notes) ? trim($notes) : null;
        $notes = $notes === '' ? null : $notes;

        $baseline = $this->pilotScoreboardService->captureBaseline(
            TenantContext::getId(),
            $label,
            $actorId,
            $notes,
        );

        if (isset($baseline['error'])) {
            return $this->respondWithError('UNAVAILABLE', __('api.service_unavailable'), null, 503);
        }

        return $this->respondWithData($baseline);
    }

    // -------------------------------------------------------------------------
    // AG81 — Operating Policy
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/admin/caring-community/operating-policy
     */
    public function operatingPolicyShow(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData(
            $this->operatingPolicyService->get(TenantContext::getId())
        );
    }

    /**
     * PUT /api/v2/admin/caring-community/operating-policy
     */
    public function operatingPolicyUpdate(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $payload = (array) request()->all();
        $result = $this->operatingPolicyService->update(TenantContext::getId(), $payload);

        if (isset($result['errors']) && $result['errors'] !== []) {
            return $this->respondWithErrors(array_map(
                fn ($e) => ['code' => 'VALIDATION_ERROR', 'message' => $e['message'], 'field' => $e['field']],
                $result['errors'],
            ), 422);
        }

        return $this->respondWithData($result['policy'] ?? []);
    }

    // -------------------------------------------------------------------------
    // AG80 — FADP / nDSG Disclosure Pack
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/admin/caring-community/disclosure-pack
     */
    public function disclosurePackShow(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData(
            $this->disclosurePackService->get(TenantContext::getId())
        );
    }

    /**
     * PUT /api/v2/admin/caring-community/disclosure-pack
     */
    public function disclosurePackUpdate(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        $payload = (array) request()->all();
        $result = $this->disclosurePackService->update(TenantContext::getId(), $payload);

        if (isset($result['errors']) && $result['errors'] !== []) {
            return $this->respondWithErrors(array_map(
                fn ($e) => ['code' => 'VALIDATION_ERROR', 'message' => $e['message'], 'field' => $e['field']],
                $result['errors'],
            ), 422);
        }

        return $this->respondWithData($result['pack'] ?? []);
    }

    /**
     * GET /api/v2/admin/caring-community/disclosure-pack/export
     * Returns Markdown for printing/sharing as the formal pilot disclosure document.
     */
    public function disclosurePackExport(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        return $this->respondWithData([
            'format'   => 'markdown',
            'content'  => $this->disclosurePackService->renderMarkdown(TenantContext::getId()),
            'filename' => 'fadp-ndsg-disclosure-pack.md',
        ]);
    }

    private function guardCaringCommunity(): ?JsonResponse
    {
        $this->requireAdmin();

        return $this->guardCaringCommunityFeature();
    }

    private function guardCaringCommunityFeature(): ?JsonResponse
    {
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return null;
    }

    private function guardRegionalPoints(): ?JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) return $disabled;

        if (!$this->regionalPointService->isEnabled(TenantContext::getId())) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.caring_regional_points_disabled'), null, 403);
        }

        return null;
    }

    private function canManageVereinMembers(int $tenantId, int $actorId, int $organizationId): bool
    {
        $actor = User::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $actorId)
            ->first(['role', 'is_admin', 'is_super_admin', 'is_tenant_super_admin', 'is_god']);

        if ($actor && (
            in_array((string) $actor->role, ['admin', 'tenant_admin', 'super_admin', 'god'], true)
            || (int) ($actor->is_admin ?? 0) === 1
            || (int) ($actor->is_super_admin ?? 0) === 1
            || (int) ($actor->is_tenant_super_admin ?? 0) === 1
            || (int) ($actor->is_god ?? 0) === 1
        )) {
            return true;
        }

        return $this->vereinMemberImportService->userHasPermissionInOrg(
            $tenantId,
            $actorId,
            $organizationId,
            'verein.members.import'
        );
    }
}
