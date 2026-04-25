<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Services\VettingService;

/**
 * AdminVettingController -- Admin member vetting and background check management.
 *
 * All methods require admin authentication.
 * All methods are native Laravel — no legacy delegation remains.
 */
class AdminVettingController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VettingService $vettingService,
    ) {}

    /** GET /api/v2/admin/vetting */
    public function list(): JsonResponse
    {
        $this->requireBrokerOrAdmin();

        try {
            $filters = [
                'status' => $this->query('status'),
                'vetting_type' => $this->query('vetting_type'),
                'search' => $this->query('search'),
                'expiring_soon' => $this->queryBool('expiring_soon'),
                'expired' => $this->queryBool('expired'),
                'page' => $this->queryInt('page', 1, 1),
                'per_page' => $this->queryInt('per_page', 25, 10, 100),
            ];

            $result = $this->vettingService->getAll($filters);

            return $this->respondWithPaginatedCollection(
                $result['data'],
                $result['pagination']['total'],
                $result['pagination']['page'],
                $result['pagination']['per_page']
            );
        } catch (\Exception $e) {
            return $this->respondWithPaginatedCollection([], 0, 1, 25);
        }
    }

    /** GET /api/v2/admin/vetting/stats */
    public function stats(): JsonResponse
    {
        $this->requireBrokerOrAdmin();
        return $this->respondWithData($this->vettingService->getStats());
    }

    /** GET /api/v2/admin/vetting/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireBrokerOrAdmin();

        try {
            $record = $this->vettingService->getById($id);
            if (!$record) {
                return $this->respondWithError('NOT_FOUND', __('api.vetting_record_not_found'), null, 404);
            }
            return $this->respondWithData($record);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.vetting_fetch_failed'), null, 500);
        }
    }

    /** POST /api/v2/admin/vetting */
    public function store(): JsonResponse
    {
        $adminId = $this->requireBrokerOrAdmin();

        $userId = $this->inputInt('user_id');
        $vettingType = $this->input('vetting_type');

        if (!$userId) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.user_id_required'), 'user_id');
        }

        $validTypes = ['dbs_basic', 'dbs_standard', 'dbs_enhanced', 'garda_vetting',
                        'access_ni', 'pvg_scotland', 'international', 'other'];
        if ($vettingType && !in_array($vettingType, $validTypes, true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_vetting_type'), 'vetting_type');
        }

        $validStatuses = ['pending', 'submitted', 'verified', 'expired', 'rejected', 'revoked'];
        $status = $this->input('status', 'pending');
        if (!in_array($status, $validStatuses, true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_status'), 'status');
        }

        // Verify user exists in current tenant
        $tenantId = $this->getTenantId();
        $userExists = DB::selectOne("SELECT id FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
        if (!$userExists) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.user_not_found_in_tenant'), 'user_id');
        }

        // Validate dates
        $issueDate = $this->input('issue_date');
        $expiryDate = $this->input('expiry_date');
        if ($issueDate && !strtotime($issueDate)) return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_issue_date_format'), 'issue_date');
        if ($expiryDate && !strtotime($expiryDate)) return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_expiry_date_format'), 'expiry_date');
        if ($issueDate && $expiryDate && strtotime($expiryDate) < strtotime($issueDate)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.expiry_after_issue_date'), 'expiry_date');
        }

        try {
            $data = [
                'user_id' => $userId,
                'vetting_type' => $vettingType ?? 'dbs_basic',
                'status' => $status,
                'reference_number' => $this->input('reference_number'),
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate,
                'notes' => $this->input('notes'),
                'works_with_children' => $this->inputBool('works_with_children') ? 1 : 0,
                'works_with_vulnerable_adults' => $this->inputBool('works_with_vulnerable_adults') ? 1 : 0,
                'requires_enhanced_check' => $this->inputBool('requires_enhanced_check') ? 1 : 0,
            ];

            $id = $this->vettingService->create($data);
            ActivityLog::log($adminId, 'vetting_record_created', "Created vetting record #{$id} for user #{$userId} ({$data['vetting_type']})", false, null, 'admin', 'vetting_record', $id);

            $record = $this->vettingService->getById($id);
            return $this->respondWithData($record, null, 201);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.vetting_create_failed'), null, 500);
        }
    }

    /** PUT /api/v2/admin/vetting/{id} */
    public function update(int $id): JsonResponse
    {
        $adminId = $this->requireBrokerOrAdmin();

        try {
            $existing = $this->vettingService->getById($id);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', __('api.vetting_record_not_found'), null, 404);
            }

            $allInput = $this->getAllInput();

            $validTypes = ['dbs_basic', 'dbs_standard', 'dbs_enhanced', 'garda_vetting',
                            'access_ni', 'pvg_scotland', 'international', 'other'];
            $validStatuses = ['pending', 'submitted', 'verified', 'expired', 'rejected', 'revoked'];

            if (array_key_exists('vetting_type', $allInput) && $allInput['vetting_type'] !== null
                && !in_array($allInput['vetting_type'], $validTypes, true)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_vetting_type'), 'vetting_type');
            }
            if (array_key_exists('status', $allInput) && $allInput['status'] !== null
                && !in_array($allInput['status'], $validStatuses, true)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_status'), 'status');
            }

            // Validate dates
            if (array_key_exists('issue_date', $allInput) && $allInput['issue_date'] !== null && !strtotime($allInput['issue_date'])) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_issue_date_format'), 'issue_date');
            }
            if (array_key_exists('expiry_date', $allInput) && $allInput['expiry_date'] !== null && !strtotime($allInput['expiry_date'])) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_expiry_date_format'), 'expiry_date');
            }
            $effectiveIssue = $allInput['issue_date'] ?? $existing['issue_date'] ?? null;
            $effectiveExpiry = $allInput['expiry_date'] ?? $existing['expiry_date'] ?? null;
            if ($effectiveIssue && $effectiveExpiry && strtotime($effectiveExpiry) < strtotime($effectiveIssue)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.expiry_after_issue_date'), 'expiry_date');
            }

            $data = [];
            $allowed = ['vetting_type', 'status', 'reference_number', 'issue_date', 'expiry_date',
                         'notes', 'works_with_children', 'works_with_vulnerable_adults', 'requires_enhanced_check'];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $allInput)) {
                    if (in_array($field, ['works_with_children', 'works_with_vulnerable_adults', 'requires_enhanced_check'])) {
                        $data[$field] = $this->inputBool($field) ? 1 : 0;
                    } else {
                        $data[$field] = $allInput[$field];
                    }
                }
            }

            if (empty($data)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.no_valid_fields'));
            }

            $this->vettingService->update($id, $data);
            $changedFields = implode(', ', array_keys($data));
            ActivityLog::log($adminId, 'vetting_record_updated', "Updated vetting record #{$id} ({$changedFields})", false, null, 'admin', 'vetting_record', $id);

            $record = $this->vettingService->getById($id);
            return $this->respondWithData($record);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.vetting_update_failed'), null, 500);
        }
    }

    /** POST /api/v2/admin/vetting/{id}/verify */
    public function verify(int $id): JsonResponse
    {
        $adminId = $this->requireBrokerOrAdmin();

        try {
            $existing = $this->vettingService->getById($id);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', __('api.vetting_record_not_found'), null, 404);
            }
            if ($existing['status'] === 'verified') {
                return $this->respondWithError('INVALID_STATUS', __('api.vetting_already_verified'));
            }

            // Require reference number for verification (legal compliance)
            if (empty($existing['reference_number'])) {
                return $this->respondWithError(
                    'VALIDATION_ERROR',
                    __('api.vetting_reference_required'),
                    'reference_number',
                    422
                );
            }

            $this->vettingService->verify($id, $adminId);
            ActivityLog::log($adminId, 'vetting_record_verified', "Verified vetting record #{$id} for {$existing['first_name']} {$existing['last_name']}", false, null, 'admin', 'vetting_record', $id);

            // Notify the user: bell + email (rendered per-recipient locale)
            $this->sendVettingNotification(
                (int) $existing['user_id'],
                'svc_notifications.vetting_approved_title',
                '/dashboard',
                'svc_notifications.vetting_approved_heading',
                'svc_notifications.vetting_approved_body',
                '#22c55e',
                '#16a34a',
                'svc_notifications.vetting_go_to_dashboard',
                '/dashboard'
            );

            $record = $this->vettingService->getById($id);
            return $this->respondWithData($record);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.vetting_verify_failed'), null, 500);
        }
    }

    /** POST /api/v2/admin/vetting/{id}/reject */
    public function reject(int $id): JsonResponse
    {
        $adminId = $this->requireBrokerOrAdmin();
        $reason = $this->input('reason', '');

        if (empty($reason)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.vetting_reject_reason_required'), 'reason');
        }

        try {
            $existing = $this->vettingService->getById($id);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', __('api.vetting_record_not_found'), null, 404);
            }

            $this->vettingService->reject($id, $adminId, $reason);
            ActivityLog::log($adminId, 'vetting_record_rejected', "Rejected vetting record #{$id}: {$reason}", false, null, 'admin', 'vetting_record', $id);

            // Notify the user: bell + email (rendered per-recipient locale)
            $this->sendVettingNotification(
                (int) $existing['user_id'],
                'svc_notifications.vetting_rejected_title',
                '/help',
                'svc_notifications.vetting_rejected_heading',
                'svc_notifications.vetting_rejected_body',
                '#ef4444',
                '#dc2626',
                'svc_notifications.vetting_contact_support',
                '/help'
            );

            $record = $this->vettingService->getById($id);
            return $this->respondWithData($record);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.vetting_reject_failed'), null, 500);
        }
    }

    /** DELETE /api/v2/admin/vetting/{id} */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireBrokerOrAdmin();

        try {
            $existing = $this->vettingService->getById($id);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', __('api.vetting_record_not_found'), null, 404);
            }

            $this->vettingService->delete($id);
            ActivityLog::log($adminId, 'vetting_record_deleted', "Deleted vetting record #{$id} ({$existing['vetting_type']})", false, null, 'admin', 'vetting_record', $id);

            // Notify the user: bell only (no email for deletion)
            try {
                $userId = (int) $existing['user_id'];
                if ($userId) {
                    $recipient = DB::table('users')
                        ->where('id', $userId)
                        ->where('tenant_id', TenantContext::getId())
                        ->select(['preferred_language'])
                        ->first();
                    LocaleContext::withLocale($recipient, function () use ($userId) {
                        Notification::createNotification(
                            $userId,
                            __('api_controllers_3.admin_bells.vetting_removed'),
                            null,
                            'moderation',
                            false
                        );
                    });
                }
            } catch (\Throwable $e) {
                Log::warning("AdminVettingController::destroy notification failed: " . $e->getMessage());
            }

            return $this->respondWithData(['deleted' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.vetting_delete_failed'), null, 500);
        }
    }

    /** POST /api/v2/admin/vetting/bulk */
    public function bulk(): JsonResponse
    {
        $adminId = $this->requireBrokerOrAdmin();

        $ids = $this->input('ids');
        $action = $this->input('action');
        $reason = $this->input('reason', '');

        if (!is_array($ids) || empty($ids)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.ids_non_empty_array_required'), 'ids');
        }
        if (count($ids) > 100) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.bulk_max_100'), 'ids');
        }
        if (!in_array($action, ['verify', 'reject', 'delete'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.vetting_bulk_invalid_action'), 'action');
        }
        if ($action === 'reject' && empty($reason)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.vetting_bulk_reject_reason_required'), 'reason');
        }

        $processed = 0;
        $failed = 0;

        // Collect user IDs for deferred notification sending (avoid blocking the loop)
        $notifyVerified = [];
        $notifyRejected = [];
        $notifyDeleted = [];

        foreach ($ids as $id) {
            $id = (int) $id;
            try {
                $existing = $this->vettingService->getById($id);
                if (!$existing) { $failed++; continue; }

                switch ($action) {
                    case 'verify':
                        if (in_array($existing['status'], ['pending', 'submitted'])) {
                            if (empty($existing['reference_number'])) {
                                $failed++;
                                break;
                            }
                            $this->vettingService->verify($id, $adminId);
                            $notifyVerified[] = (int) $existing['user_id'];
                            $processed++;
                        } else {
                            $failed++;
                        }
                        break;
                    case 'reject':
                        $this->vettingService->reject($id, $adminId, $reason);
                        $notifyRejected[] = (int) $existing['user_id'];
                        $processed++;
                        break;
                    case 'delete':
                        $this->vettingService->delete($id);
                        $notifyDeleted[] = (int) $existing['user_id'];
                        $processed++;
                        break;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        // Send notifications after processing (bell only for bulk — emails would be too heavy).
        // Wrap each per-recipient to render in that user's preferred_language.
        try {
            $tenantId = TenantContext::getId();
            $allRecipients = array_unique(array_merge($notifyVerified, $notifyRejected, $notifyDeleted));
            $localeByUser = [];
            if (!empty($allRecipients)) {
                $rows = DB::table('users')
                    ->whereIn('id', $allRecipients)
                    ->where('tenant_id', $tenantId)
                    ->select(['id', 'preferred_language'])
                    ->get();
                foreach ($rows as $r) {
                    $localeByUser[(int) $r->id] = $r->preferred_language;
                }
            }

            foreach ($notifyVerified as $userId) {
                LocaleContext::withLocale($localeByUser[$userId] ?? null, function () use ($userId) {
                    Notification::createNotification(
                        $userId,
                        __('api_controllers_3.admin_bells.vetting_approved'),
                        '/dashboard',
                        'moderation',
                        true
                    );
                });
            }
            foreach ($notifyRejected as $userId) {
                LocaleContext::withLocale($localeByUser[$userId] ?? null, function () use ($userId) {
                    Notification::createNotification(
                        $userId,
                        __('api_controllers_3.admin_bells.vetting_rejected'),
                        '/help',
                        'moderation',
                        true
                    );
                });
            }
            foreach ($notifyDeleted as $userId) {
                Notification::createNotification(
                    $userId,
                    'Your verification record has been removed.',
                    null,
                    'moderation',
                    false
                );
            }
        } catch (\Throwable $e) {
            Log::warning("AdminVettingController::bulk notifications failed: " . $e->getMessage());
        }

        ActivityLog::log($adminId, "vetting_bulk_{$action}", "Bulk {$action}: {$processed} records processed, {$failed} failed", false, null, 'admin', 'vetting_record', null);

        return $this->respondWithData([
            'action' => $action,
            'processed' => $processed,
            'failed' => $failed,
            'total' => count($ids),
        ]);
    }

    /** GET /api/v2/admin/vetting/user/{userId} */
    public function getUserRecords(int $userId): JsonResponse
    {
        $this->requireBrokerOrAdmin();
        try {
            $records = $this->vettingService->getUserRecords($userId);
            return $this->respondWithData($records);
        } catch (\Exception $e) {
            return $this->respondWithData([]);
        }
    }

    /**
     * POST /api/v2/admin/vetting/{id}/document
     *
     * Upload a supporting document for a vetting record. Uses request()->file() (Laravel native).
     * Field name: 'file'. Allowed: PDF, JPEG, PNG, WebP. Max 10MB.
     */
    public function uploadDocument(int $id): JsonResponse
    {
        $adminId = $this->requireBrokerOrAdmin();

        try {
            $existing = $this->vettingService->getById($id);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', __('api.vetting_record_not_found'), null, 404);
            }

            $file = request()->file('file');
            if (!$file || !$file->isValid()) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.file_upload_failed'), 'file');
            }

            // Validate file type using file content
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file->getRealPath());
            if (!in_array($mimeType, $allowedMimes, true)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.file_type_pdf_jpeg_png_webp'), 'file');
            }

            // 10 MB limit
            if ($file->getSize() > 10 * 1024 * 1024) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.file_size_limit_10mb'), 'file');
            }

            // Build a $_FILES-compatible array for ImageUploader::upload()
            $fileArray = [
                'name'     => $file->getClientOriginalName(),
                'type'     => $mimeType,
                'tmp_name' => $file->getRealPath(),
                'error'    => UPLOAD_ERR_OK,
                'size'     => $file->getSize(),
            ];

            $url = \App\Core\ImageUploader::upload($fileArray, 'vetting/documents');
            $this->vettingService->updateDocumentUrl($id, $url);

            ActivityLog::log($adminId, 'vetting_document_uploaded', "Uploaded document for vetting record #{$id} ({$existing['first_name']} {$existing['last_name']})", false, null, 'admin', 'vetting_record', $id);

            $record = $this->vettingService->getById($id);
            return $this->respondWithData($record);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.document_upload_failed'), null, 500);
        }
    }

    /**
     * Send a vetting-related bell notification + email to a user.
     *
     * Takes translation KEYS (not pre-rendered strings) for the bell message,
     * email heading, body, and CTA label so they can be resolved under the
     * recipient's preferred_language via LocaleContext. If the recipient has
     * no preferred_language, falls back to the app default 'en'.
     *
     * Wrapped in try/catch so notification failures never break the admin action.
     */
    private function sendVettingNotification(
        int $userId,
        string $bellMessageKey,
        ?string $bellLink,
        string $emailHeadingKey,
        string $emailBodyKey,
        string $gradientFrom,
        string $gradientTo,
        string $ctaLabelKey,
        string $ctaPath
    ): void {
        try {
            $tenantId = TenantContext::getId();
            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'name', 'first_name', 'preferred_language'])
                ->first();

            LocaleContext::withLocale($user, function () use ($user, $userId, $bellMessageKey, $bellLink, $emailHeadingKey, $emailBodyKey, $gradientFrom, $gradientTo, $ctaLabelKey, $ctaPath) {
                // Bell notification
                Notification::createNotification(
                    $userId,
                    __($bellMessageKey),
                    $bellLink,
                    'moderation',
                    true
                );

                // Email notification
                if ($user && !empty($user->email)) {
                    $recipientName = htmlspecialchars($user->first_name ?? $user->name ?? __('emails.common.fallback_name'), ENT_QUOTES, 'UTF-8');
                    $tenantName = htmlspecialchars(TenantContext::getSetting('site_name', 'Project NEXUS'), ENT_QUOTES, 'UTF-8');
                    $baseUrl = TenantContext::getFrontendUrl();
                    $basePath = TenantContext::getSlugPrefix();
                    $fullCtaUrl = $baseUrl . $basePath . $ctaPath;
                    $emailHeading = __($emailHeadingKey);
                    $emailBody = __($emailBodyKey);
                    $ctaLabel = __($ctaLabelKey);
                    $safeEmailBody = htmlspecialchars($emailBody, ENT_QUOTES, 'UTF-8');
                    $greeting = __('emails.common.greeting', ['name' => $recipientName]);
                    $subject = __('emails.vetting.subject', ['heading' => $emailHeading, 'tenant' => $tenantName]);

                    $html = <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, {$gradientFrom}, {$gradientTo}); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$emailHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$greeting}</p>
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$safeEmailBody}</p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$fullCtaUrl}" style="display: inline-block; background: linear-gradient(135deg, {$gradientFrom}, {$gradientTo}); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$ctaLabel}</a>
        </div>
    </div>
</div>
HTML;
                    $mailer = Mailer::forCurrentTenant();
                    $mailer->send($user->email, $subject, $html);
                }
            });
        } catch (\Throwable $e) {
            Log::warning("AdminVettingController::sendVettingNotification failed for user {$userId}: " . $e->getMessage());
        }
    }
}
