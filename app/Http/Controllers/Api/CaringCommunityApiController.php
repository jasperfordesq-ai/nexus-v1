<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CaringHourGiftService;
use App\Services\CaringCommunity\CaringHourTransferService;
use App\Services\CaringCommunity\CaringRegionalPointService;
use App\Services\CaringCommunity\SafeguardingService;
use App\Services\AhvPensionExportService;
use App\Services\CaringHelpRequestNlpService;
use App\Services\CaringInviteCodeService;
use App\Services\CaringLoyaltyService;
use App\Services\FutureCareFundService;
use App\Services\TranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Member-facing API for the Caring Community module.
 *
 * Exposes read-only views of data the authenticated member has a stake in,
 * scoped entirely to the current tenant.
 */
class CaringCommunityApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CaringInviteCodeService $inviteCodeService,
        private readonly CaringLoyaltyService $loyaltyService,
        private readonly FutureCareFundService $futureCareFundService,
        private readonly AhvPensionExportService $ahvPensionExportService,
        private readonly CaringHourTransferService $hourTransferService,
        private readonly SafeguardingService $safeguardingService,
        private readonly CaringHourGiftService $hourGiftService,
        private readonly CaringRegionalPointService $regionalPointService,
    ) {
    }

    // -------------------------------------------------------------------------
    // A1 - Regional points (isolated third-currency wallet)
    // -------------------------------------------------------------------------

    public function regionalPointsSummary(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            return $this->respondWithData($this->regionalPointService->memberSummary($userId));
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    public function regionalPointsHistory(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            return $this->respondWithData([
                'items' => $this->regionalPointService->memberHistory($userId, (int) ($this->query('limit') ?? 50)),
            ]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    public function regionalPointsTransfer(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();
        $recipientId = (int) ($input['recipient_user_id'] ?? 0);
        $points = (float) ($input['points'] ?? 0);
        $message = isset($input['message']) ? (string) $input['message'] : null;

        if ($recipientId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'recipient_user_id', 422);
        }

        try {
            return $this->respondWithData(
                $this->regionalPointService->transferBetweenMembers($userId, $recipientId, $points, $message),
                null,
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('REGIONAL_POINTS_TRANSFER_FAILED', $e->getMessage(), null, 422);
        }
    }

    // -------------------------------------------------------------------------
    // K5 — Time-credit gifting (member-to-member, same-tenant)
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/caring-community/regional-points/marketplace/quote
     *
     * Query: seller_id, listing_id?, order_total_chf
     */
    public function regionalPointsMarketplaceQuote(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $sellerId = (int) ($this->query('seller_id') ?? 0);
        $listingId = $this->query('listing_id') !== null ? (int) $this->query('listing_id') : null;
        $orderTotalChf = (float) ($this->query('order_total_chf') ?? 0);

        if ($sellerId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'seller_id', 422);
        }
        if ($orderTotalChf <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'order_total_chf', 422);
        }

        return $this->respondWithData(
            $this->regionalPointService->calculateMarketplaceDiscount($userId, $sellerId, $listingId, $orderTotalChf)
        );
    }

    public function regionalPointsMarketplaceRedeem(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();
        $sellerId = (int) ($input['seller_id'] ?? 0);
        $listingId = isset($input['listing_id']) && $input['listing_id'] !== '' ? (int) $input['listing_id'] : null;
        $pointsToUse = (float) ($input['points_to_use'] ?? 0);
        $orderTotalChf = (float) ($input['order_total_chf'] ?? 0);

        if ($sellerId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'seller_id', 422);
        }

        try {
            $result = $this->regionalPointService->redeemForMarketplaceDiscount(
                memberId: $userId,
                sellerId: $sellerId,
                listingId: $listingId,
                pointsToUse: $pointsToUse,
                orderTotalChf: $orderTotalChf,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            $code = str_contains(strtolower($e->getMessage()), 'not enough')
                ? 'INSUFFICIENT_REGIONAL_POINTS'
                : 'REGIONAL_POINTS_REDEMPTION_FAILED';
            return $this->respondWithError($code, $e->getMessage(), null, 422);
        }

        return $this->respondWithData($result + ['success' => true], null, 201);
    }

    /**
     * POST /api/v2/caring-community/hour-gifts/send
     *
     * Body: { recipient_user_id, hours, message? }
     */
    public function hourGiftSend(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();
        $recipientId = (int) ($input['recipient_user_id'] ?? 0);
        $hours = (float) ($input['hours'] ?? 0);
        $message = isset($input['message']) ? (string) $input['message'] : null;

        if ($recipientId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'recipient_user_id', 422);
        }
        if ($hours <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'hours', 422);
        }

        try {
            $result = $this->hourGiftService->send($userId, $recipientId, $hours, $message);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $code = str_contains($msg, 'Insufficient') ? 'INSUFFICIENT_HOURS' : 'GIFT_FAILED';
            return $this->respondWithError($code, $msg, null, 422);
        }

        return $this->respondWithData($result + ['success' => true], null, 201);
    }

    /**
     * POST /api/v2/caring-community/hour-gifts/{id}/accept
     */
    public function hourGiftAccept(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            $this->hourGiftService->accept($id, $userId);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('GIFT_ACCEPT_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData(['success' => true]);
    }

    /**
     * POST /api/v2/caring-community/hour-gifts/{id}/decline
     * Body: { reason? }
     */
    public function hourGiftDecline(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();
        $reason = isset($input['reason']) ? (string) $input['reason'] : null;

        try {
            $this->hourGiftService->decline($id, $userId, $reason);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('GIFT_DECLINE_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData(['success' => true]);
    }

    /**
     * POST /api/v2/caring-community/hour-gifts/{id}/revert
     */
    public function hourGiftRevert(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            $this->hourGiftService->revert($id, $userId);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('GIFT_REVERT_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData(['success' => true]);
    }

    /**
     * GET /api/v2/caring-community/hour-gifts/inbox
     */
    public function hourGiftInbox(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData([
            'items' => $this->hourGiftService->myInbox($userId),
        ]);
    }

    /**
     * GET /api/v2/caring-community/hour-gifts/sent
     */
    public function hourGiftSent(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData([
            'items' => $this->hourGiftService->mySent($userId),
        ]);
    }

    /**
     * POST /api/v2/caring-community/safeguarding/report
     *
     * Member submits a safeguarding concern about another member, coordinator,
     * or organisation. Body: { category, severity, description, subject_user_id?,
     * subject_organisation_id?, evidence_url? }
     */
    public function safeguardingReport(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            $result = $this->safeguardingService->submitReport($userId, $this->getAllInput());
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('REPORT_FAILED', $e->getMessage(), null, 422);
        }

        return $this->respondWithData($result + ['success' => true], null, 201);
    }

    /**
     * GET /api/v2/caring-community/safeguarding/my-reports
     *
     * Member's own submitted reports.
     */
    public function safeguardingMyReports(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData([
            'items' => $this->safeguardingService->myReports($userId),
        ]);
    }

    /**
     * POST /api/v2/caring-community/hour-transfer/initiate
     *
     * Member initiates a banked-hour transfer to themselves at another
     * cooperative tenant. Funds move only after a source-tenant admin approves.
     *
     * Body: { destination_tenant_slug, hours, reason? }
     */
    public function hourTransferInitiate(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();
        $destinationSlug = trim((string) ($input['destination_tenant_slug'] ?? ''));
        $hours = (float) ($input['hours'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));

        if ($destinationSlug === '') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'destination_tenant_slug', 422);
        }
        if ($hours <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'hours', 422);
        }

        try {
            $result = $this->hourTransferService->initiate($userId, $destinationSlug, $hours, $reason);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $code = match (true) {
                str_contains($msg, 'Insufficient')          => 'INSUFFICIENT_HOURS',
                str_contains($msg, 'No matching member')    => 'NO_MATCHING_EMAIL',
                str_contains($msg, 'Destination cooperative') => 'DESTINATION_NOT_FOUND',
                default                                       => 'TRANSFER_FAILED',
            };
            return $this->respondWithError($code, $msg, null, 422);
        }

        return $this->respondWithData($result + ['success' => true], null, 201);
    }

    /**
     * GET /api/v2/caring-community/hour-transfer/my-history
     *
     * Returns transfers the authenticated member has initiated.
     */
    public function hourTransferMyHistory(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData([
            'items' => $this->hourTransferService->memberHistory($userId),
        ]);
    }

    /**
     * GET /api/v2/caring-community/my-future-care-fund
     *
     * Returns the authenticated member's "Future Care Fund" (Zeitvorsorge)
     * summary — banked hours framed as a 4th-pillar pension provision.
     * Includes lifetime given/received, reciprocity ratio, by-year
     * breakdown, and a CHF estimate of the net banked balance.
     */
    public function myFutureCareFund(): JsonResponse
    {
        $userId   = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData(
            $this->futureCareFundService->summary($tenantId, $userId)
        );
    }

    public function myAhvPensionExport(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData(
            $this->ahvPensionExportService->build(
                $tenantId,
                $userId,
                $this->query('from'),
                $this->query('to')
            )
        );
    }

    /**
     * GET /api/v2/caring-community/invite/{code}  (PUBLIC — no auth)
     *
     * Look up a single invite code's status so the member-facing join page can
     * show the appropriate state (valid / expired / already_used / invalid).
     *
     * Intentionally always returns 200 (never 404) to prevent code enumeration.
     */
    public function lookupInvite(string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $result   = $this->inviteCodeService->lookup($tenantId, strtoupper(trim($code)));

        return $this->respondWithData($result);
    }

    /**
     * POST /api/v2/caring-community/request-help
     *
     * Submit a low-friction help request. Stored in caring_help_requests so
     * coordinators can see and act on it via the workflow console.
     */
    public function requestHelp(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();

        $what = trim((string) ($input['what'] ?? ''));
        $whenNeeded = trim((string) ($input['when'] ?? ''));
        $contactPref = (string) ($input['contact_preference'] ?? 'either');

        $errors = [];
        if ($what === '') {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.field_required'), 'field' => 'what'];
        } elseif (mb_strlen($what) > 500) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.field_too_long'), 'field' => 'what'];
        }
        if ($whenNeeded === '') {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.field_required'), 'field' => 'when'];
        } elseif (mb_strlen($whenNeeded) > 200) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.field_too_long'), 'field' => 'when'];
        }
        if (!in_array($contactPref, ['phone', 'message', 'either'], true)) {
            $contactPref = 'either';
        }

        if (!empty($errors)) {
            return $this->respondWithErrors($errors, 422);
        }

        try {
            DB::table('caring_help_requests')->insert([
                'tenant_id'          => $tenantId,
                'user_id'            => $userId,
                'what'               => $what,
                'when_needed'        => $whenNeeded,
                'contact_preference' => $contactPref,
                'status'             => 'pending',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[CaringCommunity] requestHelp insert failed', [
                'tenant_id' => $tenantId,
                'user_id'   => $userId,
                'error'     => $e->getMessage(),
            ]);
            return $this->respondWithError('SERVER_ERROR', __('api.server_error'), null, 500);
        }

        return $this->respondWithData([
            'success' => true,
            'message' => 'caring_community.requests.submitted',
        ], null, 201);
    }

    /**
     * POST /api/v2/caring-community/request-help/voice
     *
     * Audio-first help-request flow (AG36/AG37):
     *   1. Member uploads a short voice clip (multipart `audio` field).
     *   2. Server transcribes via Whisper.
     *   3. Server extracts intent (category / when / contact preference) via gpt-4o-mini.
     *   4. Returns suggestions for the React form to pre-fill before normal submit.
     *
     * Body (multipart): audio (required), locale (optional, ISO 639-1).
     */
    public function requestHelpVoice(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $file = request()->file('audio');
        if (!$file || !$file->isValid()) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'audio', 422);
        }

        // Cap at ~10 MB (≈ 60s of typical browser-recorded audio)
        if ((int) $file->getSize() > 10 * 1024 * 1024) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_too_long'), 'audio', 422);
        }

        $allowedMimePrefix = 'audio/';
        $mime = (string) $file->getMimeType();
        if (!str_starts_with($mime, $allowedMimePrefix) && $mime !== 'video/webm') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_file_type') ?? 'Invalid file type', 'audio', 422);
        }

        $locale = (string) (request()->input('locale') ?? app()->getLocale() ?? 'en');
        $locale = preg_replace('/[^a-zA-Z\-]/', '', $locale) ?: 'en';

        $tmpPath = $file->getRealPath();
        if (!is_string($tmpPath) || $tmpPath === '') {
            return $this->respondWithError('SERVER_ERROR', __('api.server_error'), null, 500);
        }

        try {
            $transcript = TranscriptionService::transcribe($tmpPath);
        } catch (\Throwable $e) {
            Log::error('[CaringCommunity] requestHelpVoice transcribe failed', [
                'tenant_id' => TenantContext::getId(),
                'user_id'   => $userId,
                'error'     => $e->getMessage(),
            ]);
            return $this->respondWithError('TRANSCRIPTION_FAILED', __('api.server_error'), null, 502);
        }

        if ($transcript === null || empty(trim((string) ($transcript['text'] ?? '')))) {
            return $this->respondWithError('TRANSCRIPTION_FAILED', __('api.server_error'), null, 502);
        }

        $detectedLocale = (string) ($transcript['language'] ?? $locale);
        $extracted = CaringHelpRequestNlpService::extract($transcript['text'], $detectedLocale);

        return $this->respondWithData([
            'transcript'                  => $transcript['text'],
            'detected_language'           => $detectedLocale,
            'suggested_category'          => $extracted['category'],
            'suggested_when'              => $extracted['when'],
            'suggested_contact_preference' => $extracted['contact_preference'],
            'raw_text'                    => $extracted['raw_text'],
        ]);
    }

    /**
     * POST /api/v2/caring-community/offer-favour
     *
     * Record a credit-free informal neighbourly favour. No wallet transaction —
     * purely a record of kindness for community insight and coordinator visibility.
     */
    public function offerFavour(): JsonResponse
    {
        $userId   = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();

        $description    = trim((string) ($input['description'] ?? ''));
        $category       = trim((string) ($input['category'] ?? ''));
        $receivedByName = trim((string) ($input['received_by_name'] ?? ''));
        $favourDate     = trim((string) ($input['favour_date'] ?? ''));
        $isAnonymous    = (bool) ($input['is_anonymous'] ?? false);

        $errors = [];

        if ($description === '') {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.field_required'), 'field' => 'description'];
        } elseif (mb_strlen($description) > 500) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.field_too_long'), 'field' => 'description'];
        }

        if ($favourDate === '') {
            $favourDate = now()->toDateString();
        } elseif (!\DateTime::createFromFormat('Y-m-d', $favourDate)) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.invalid_date'), 'field' => 'favour_date'];
        }

        if (!empty($errors)) {
            return $this->respondWithErrors($errors, 422);
        }

        $allowedCategories = ['companionship', 'shopping', 'transport', 'home_help', 'gardening', 'meals', 'other'];
        if ($category !== '' && !in_array($category, $allowedCategories, true)) {
            $category = 'other';
        }

        try {
            DB::table('caring_favours')->insert([
                'tenant_id'            => $tenantId,
                'offered_by_user_id'   => $userId,
                'received_by_user_id'  => null,
                'category'             => $category !== '' ? $category : null,
                'description'          => $description,
                'favour_date'          => $favourDate,
                'is_anonymous'         => $isAnonymous,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[CaringCommunity] offerFavour insert failed', [
                'tenant_id' => $tenantId,
                'user_id'   => $userId,
                'error'     => $e->getMessage(),
            ]);
            return $this->respondWithError('SERVER_ERROR', __('api.server_error'), null, 500);
        }

        return $this->respondWithData([
            'success' => true,
            'message' => 'caring_community.favour.recorded',
        ], null, 201);
    }

    /**
     * GET /api/v2/caring-community/my-relationships
     *
     * Returns the authenticated member's support relationships (as supporter
     * OR recipient), including partner info and the last 3 vol_logs per
     * relationship.  Limit 50, ordered by status priority then next check-in.
     */
    public function myRelationships(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        if (!Schema::hasTable('caring_support_relationships')) {
            return $this->respondWithData([]);
        }

        $hasDob = Schema::hasColumn('users', 'date_of_birth');
        $dobSelectSupporter = $hasDob ? 'supporter.date_of_birth AS supporter_dob,' : 'NULL AS supporter_dob,';
        $dobSelectRecipient = $hasDob ? 'recipient.date_of_birth AS recipient_dob,' : 'NULL AS recipient_dob,';

        $rows = DB::select(
            "SELECT
                csr.id,
                csr.title,
                csr.description,
                csr.frequency,
                csr.expected_hours,
                csr.status,
                csr.start_date,
                csr.end_date,
                csr.last_logged_at,
                csr.next_check_in_at,
                csr.supporter_id,
                csr.recipient_id,
                supporter.name            AS supporter_name,
                supporter.first_name      AS supporter_first_name,
                supporter.last_name       AS supporter_last_name,
                supporter.avatar_url      AS supporter_avatar,
                {$dobSelectSupporter}
                recipient.name            AS recipient_name,
                recipient.first_name      AS recipient_first_name,
                recipient.last_name       AS recipient_last_name,
                recipient.avatar_url      AS recipient_avatar,
                {$dobSelectRecipient}
                1 AS _ok
             FROM caring_support_relationships csr
             LEFT JOIN users supporter
                    ON supporter.id = csr.supporter_id
                   AND supporter.tenant_id = csr.tenant_id
             LEFT JOIN users recipient
                    ON recipient.id = csr.recipient_id
                   AND recipient.tenant_id = csr.tenant_id
             WHERE csr.tenant_id = ?
               AND (csr.supporter_id = ? OR csr.recipient_id = ?)
               AND csr.status IN ('active', 'paused')
             ORDER BY
                CASE csr.status WHEN 'active' THEN 0 ELSE 1 END,
                COALESCE(csr.next_check_in_at, csr.created_at) ASC,
                csr.id DESC
             LIMIT 50",
            [$tenantId, $userId, $userId]
        );

        if (empty($rows)) {
            return $this->respondWithData([]);
        }

        // Bulk-fetch the last 3 logs per relationship in one query.
        $relationshipIds = array_map(fn (object $r): int => (int) $r->id, $rows);
        $logsData = $this->fetchRecentLogs($tenantId, $relationshipIds);

        $items = array_map(
            fn (object $row): array => $this->formatRelationship($row, $userId, $logsData[(int) $row->id] ?? []),
            $rows
        );

        return $this->respondWithData($items);
    }

    /**
     * GET /api/v2/caring-community/markt
     *
     * Unified "Marktplatz" aggregator — combines active time-credit listings and
     * (when the marketplace feature is on) commercial marketplace items into a
     * single chronological feed.
     *
     * Query params:
     *   type      all|listings|marketplace  (default: all)
     *   page      int                        (default: 1)
     *   per_page  int                        (default: 20, max: 50)
     */
    public function markt(): JsonResponse
    {
        $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $type    = $this->query('type') ?? 'all';
        if (!in_array($type, ['all', 'listings', 'marketplace'], true)) {
            $type = 'all';
        }
        $page    = max(1, (int) ($this->query('page') ?? 1));
        $perPage = min(50, max(1, (int) ($this->query('per_page') ?? 20)));

        // ── Optional proximity filter ───────────────────────────────────────
        $lat      = is_numeric($this->query('lat'))       ? (float) $this->query('lat')       : null;
        $lng      = is_numeric($this->query('lng'))       ? (float) $this->query('lng')       : null;
        $radiusKm = is_numeric($this->query('radius_km')) ? (float) $this->query('radius_km') : null;

        $useProximity = ($lat !== null && $lng !== null && $radiusKm !== null && $radiusKm > 0);

        // Haversine SELECT expression (3 bindings: lat, lng, lat)
        $haversineSelect = "(6371 * acos(LEAST(1.0, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))) AS distance_km";

        // When combining both sources each gets half the page budget
        $sourceLimit = (int) ceil($perPage / 2);

        $items = [];

        // ── Time-credit listings ────────────────────────────────────────────
        if (in_array($type, ['all', 'listings'], true)) {
            $limit = $type === 'all' ? $sourceLimit : $perPage;

            if ($useProximity) {
                $listingRows = DB::select(
                    "SELECT
                        l.id,
                        l.title,
                        l.description,
                        l.type            AS listing_type,
                        l.image_url,
                        l.hours_estimate,
                        l.created_at,
                        u.name            AS user_name,
                        u.first_name      AS user_first_name,
                        u.last_name       AS user_last_name,
                        u.avatar_url      AS user_avatar,
                        c.name            AS category_name,
                        {$haversineSelect}
                     FROM listings l
                     LEFT JOIN users u
                            ON u.id = l.user_id AND u.tenant_id = l.tenant_id
                     LEFT JOIN categories c
                            ON c.id = l.category_id
                     WHERE l.tenant_id = ?
                       AND l.status = 'active'
                       AND (l.deleted_at IS NULL OR l.deleted_at > NOW())
                       AND l.latitude IS NOT NULL
                       AND l.longitude IS NOT NULL
                     HAVING distance_km <= ?
                     ORDER BY distance_km ASC, l.created_at DESC
                     LIMIT ?",
                    [$lat, $lng, $lat, $tenantId, $radiusKm, $limit]
                );
            } else {
                $listingRows = DB::select(
                    "SELECT
                        l.id,
                        l.title,
                        l.description,
                        l.type            AS listing_type,
                        l.image_url,
                        l.hours_estimate,
                        l.created_at,
                        u.name            AS user_name,
                        u.first_name      AS user_first_name,
                        u.last_name       AS user_last_name,
                        u.avatar_url      AS user_avatar,
                        c.name            AS category_name
                     FROM listings l
                     LEFT JOIN users u
                            ON u.id = l.user_id AND u.tenant_id = l.tenant_id
                     LEFT JOIN categories c
                            ON c.id = l.category_id
                     WHERE l.tenant_id = ?
                       AND l.status = 'active'
                       AND (l.deleted_at IS NULL OR l.deleted_at > NOW())
                     ORDER BY l.created_at DESC
                     LIMIT ?",
                    [$tenantId, $limit]
                );
            }

            foreach ($listingRows as $row) {
                $items[] = [
                    'source'         => 'listing',
                    'id'             => (int) $row->id,
                    'title'          => (string) $row->title,
                    'description'    => $row->description ? mb_substr((string) $row->description, 0, 200) : null,
                    'listing_type'   => (string) $row->listing_type, // offer|request
                    'image_url'      => $row->image_url ? (string) $row->image_url : null,
                    'hours_estimate' => $row->hours_estimate !== null ? round((float) $row->hours_estimate, 1) : null,
                    'price_cash'     => null,
                    'price_credits'  => null,
                    'price_type'     => null,
                    'price_currency' => null,
                    'category'       => $row->category_name ? (string) $row->category_name : null,
                    'user_name'      => $this->buildDisplayName($row),
                    'user_avatar'    => $row->user_avatar ? (string) $row->user_avatar : null,
                    'created_at'     => (string) $row->created_at,
                    'detail_path'    => '/listings/' . $row->id,
                ];
            }
        }

        // ── Commercial marketplace items ────────────────────────────────────
        $marketplaceAvailable = TenantContext::hasFeature('marketplace')
            && Schema::hasTable('marketplace_listings');

        if (in_array($type, ['all', 'marketplace'], true) && $marketplaceAvailable) {
            $limit = $type === 'all' ? $sourceLimit : $perPage;

            // Haversine SELECT expression for marketplace_listings (column names same)
            $haversineSelectMkt = "(6371 * acos(LEAST(1.0, cos(radians(?)) * cos(radians(ml.latitude)) * cos(radians(ml.longitude) - radians(?)) + sin(radians(?)) * sin(radians(ml.latitude))))) AS distance_km";

            if ($useProximity) {
                $mktRows = DB::select(
                    "SELECT
                        ml.id,
                        ml.title,
                        ml.description,
                        ml.price,
                        ml.price_type,
                        ml.price_currency,
                        ml.time_credit_price,
                        ml.created_at,
                        u.name            AS user_name,
                        u.first_name      AS user_first_name,
                        u.last_name       AS user_last_name,
                        u.avatar_url      AS user_avatar,
                        mc.name           AS category_name,
                        mi.image_url      AS primary_image_url,
                        {$haversineSelectMkt}
                     FROM marketplace_listings ml
                     LEFT JOIN users u
                            ON u.id = ml.user_id AND u.tenant_id = ml.tenant_id
                     LEFT JOIN marketplace_categories mc
                            ON mc.id = ml.category_id
                     LEFT JOIN marketplace_images mi
                            ON mi.marketplace_listing_id = ml.id AND mi.is_primary = 1
                     WHERE ml.tenant_id = ?
                       AND ml.status = 'active'
                       AND ml.moderation_status = 'approved'
                       AND ml.latitude IS NOT NULL
                       AND ml.longitude IS NOT NULL
                     HAVING distance_km <= ?
                     ORDER BY distance_km ASC, ml.created_at DESC
                     LIMIT ?",
                    [$lat, $lng, $lat, $tenantId, $radiusKm, $limit]
                );
            } else {
                $mktRows = DB::select(
                    "SELECT
                        ml.id,
                        ml.title,
                        ml.description,
                        ml.price,
                        ml.price_type,
                        ml.price_currency,
                        ml.time_credit_price,
                        ml.created_at,
                        u.name            AS user_name,
                        u.first_name      AS user_first_name,
                        u.last_name       AS user_last_name,
                        u.avatar_url      AS user_avatar,
                        mc.name           AS category_name,
                        mi.image_url      AS primary_image_url
                     FROM marketplace_listings ml
                     LEFT JOIN users u
                            ON u.id = ml.user_id AND u.tenant_id = ml.tenant_id
                     LEFT JOIN marketplace_categories mc
                            ON mc.id = ml.category_id
                     LEFT JOIN marketplace_images mi
                            ON mi.marketplace_listing_id = ml.id AND mi.is_primary = 1
                     WHERE ml.tenant_id = ?
                       AND ml.status = 'active'
                       AND ml.moderation_status = 'approved'
                     ORDER BY ml.created_at DESC
                     LIMIT ?",
                    [$tenantId, $limit]
                );
            }

            foreach ($mktRows as $row) {
                $priceCash = $row->price !== null ? (float) $row->price : null;
                if ($row->price_type === 'free') {
                    $priceCash = 0.0;
                }
                $items[] = [
                    'source'         => 'marketplace',
                    'id'             => (int) $row->id,
                    'title'          => (string) $row->title,
                    'description'    => $row->description ? mb_substr((string) $row->description, 0, 200) : null,
                    'listing_type'   => null,
                    'image_url'      => $row->primary_image_url ? (string) $row->primary_image_url : null,
                    'hours_estimate' => null,
                    'price_cash'     => $priceCash,
                    'price_credits'  => $row->time_credit_price !== null ? round((float) $row->time_credit_price, 1) : null,
                    'price_type'     => (string) $row->price_type,
                    'price_currency' => (string) $row->price_currency,
                    'category'       => $row->category_name ? (string) $row->category_name : null,
                    'user_name'      => $this->buildDisplayName($row),
                    'user_avatar'    => $row->user_avatar ? (string) $row->user_avatar : null,
                    'created_at'     => (string) $row->created_at,
                    'detail_path'    => '/marketplace/' . $row->id,
                ];
            }
        }

        // Merge and sort by created_at DESC
        usort($items, static fn (array $a, array $b): int => strcmp(
            (string) ($b['created_at'] ?? ''),
            (string) ($a['created_at'] ?? ''),
        ));

        // Pagination slice
        $offset = ($page - 1) * $perPage;
        $sliced = array_slice($items, $offset, $perPage);
        $total  = count($items);

        return $this->respondWithData($sliced, [
            'total'                  => $total,
            'page'                   => $page,
            'per_page'               => $perPage,
            'has_more'               => ($offset + $perPage) < $total,
            'marketplace_available'  => $marketplaceAvailable,
        ]);
    }

    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    // Loyalty bridge — time credits ↔ marketplace
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/caring-community/loyalty/quote
     *
     * Compute the maximum discount the authenticated member can apply at a
     * given seller for a given order total. Used by the checkout UI to render
     * the "use my time credits" card live before the buy.
     *
     * Query: seller_id, order_total_chf, listing_id (optional, for context).
     */
    public function loyaltyQuote(): JsonResponse
    {
        $userId = $this->requireAuth();
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $sellerId = (int) ($this->query('seller_id') ?? 0);
        $orderTotalChf = (float) ($this->query('order_total_chf') ?? 0);

        if ($sellerId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'seller_id', 422);
        }
        if ($orderTotalChf <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'order_total_chf', 422);
        }

        return $this->respondWithData(
            $this->loyaltyService->calculateAvailableDiscount($userId, $sellerId, $orderTotalChf)
        );
    }

    /**
     * POST /api/v2/caring-community/loyalty/redeem
     *
     * Body: { seller_id, listing_id?, credits_to_use, order_total_chf }
     *
     * Atomically debits the member's wallet and records a redemption. Returns
     * the new wallet balance and the discount applied (CHF). The frontend then
     * proceeds with the actual buy at order_total - discount_chf.
     */
    public function loyaltyRedeem(): JsonResponse
    {
        $userId = $this->requireAuth();
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();
        $sellerId      = (int) ($input['seller_id'] ?? 0);
        $listingId     = isset($input['listing_id']) && $input['listing_id'] !== '' ? (int) $input['listing_id'] : null;
        $creditsToUse  = (float) ($input['credits_to_use'] ?? 0);
        $orderTotalChf = (float) ($input['order_total_chf'] ?? 0);

        if ($sellerId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'seller_id', 422);
        }

        try {
            $result = $this->loyaltyService->redeem(
                memberId:      $userId,
                sellerId:      $sellerId,
                listingId:     $listingId,
                creditsToUse:  $creditsToUse,
                orderTotalChf: $orderTotalChf,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $code = str_contains(strtolower($msg), 'enough') ? 'INSUFFICIENT_CREDITS' : 'REDEMPTION_FAILED';
            return $this->respondWithError($code, $msg, null, 422);
        }

        return $this->respondWithData($result + ['success' => true]);
    }

    /**
     * GET /api/v2/caring-community/loyalty/my-history
     *
     * Returns the authenticated member's last 50 redemptions for the
     * "My Time Credit Redemptions" page.
     */
    public function loyaltyMyHistory(): JsonResponse
    {
        $userId = $this->requireAuth();
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData([
            'items' => $this->loyaltyService->listMemberHistory($userId, 50),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch the last 3 vol_logs for each of the given relationship IDs,
     * grouped by caring_support_relationship_id.
     *
     * Returns: [ relationship_id => [ log, log, log ], ... ]
     *
     * @param  int[]  $relationshipIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchRecentLogs(int $tenantId, array $relationshipIds): array
    {
        if (
            !Schema::hasTable('vol_logs')
            || !Schema::hasColumn('vol_logs', 'caring_support_relationship_id')
        ) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($relationshipIds), '?'));

        // Rank logs per relationship and take the top 3.
        $logRows = DB::select(
            "SELECT caring_support_relationship_id, date_logged, hours, status
             FROM (
                 SELECT
                     caring_support_relationship_id,
                     date_logged,
                     hours,
                     status,
                     ROW_NUMBER() OVER (
                         PARTITION BY caring_support_relationship_id
                         ORDER BY date_logged DESC, id DESC
                     ) AS rn
                 FROM vol_logs
                 WHERE tenant_id = ?
                   AND caring_support_relationship_id IN ({$placeholders})
             ) ranked
             WHERE rn <= 3",
            array_merge([$tenantId], $relationshipIds)
        );

        $grouped = [];
        foreach ($logRows as $log) {
            $rid = (int) $log->caring_support_relationship_id;
            $grouped[$rid][] = [
                'date'   => (string) $log->date_logged,
                'hours'  => round((float) $log->hours, 2),
                'status' => (string) $log->status,
            ];
        }

        return $grouped;
    }

    /**
     * Format a single relationship row for the member-facing API.
     *
     * @param array<int, array<string, mixed>> $recentLogs
     * @return array<string, mixed>
     */
    private function formatRelationship(object $row, int $authUserId, array $recentLogs): array
    {
        $supporterId = (int) $row->supporter_id;
        $role        = $supporterId === $authUserId ? 'supporter' : 'recipient';
        $partnerId   = $role === 'supporter' ? (int) $row->recipient_id : $supporterId;

        if ($role === 'supporter') {
            $partnerName   = $this->displayName($row, 'recipient');
            $partnerAvatar = $row->recipient_avatar ?? null;
        } else {
            $partnerName   = $this->displayName($row, 'supporter');
            $partnerAvatar = $row->supporter_avatar ?? null;
        }

        $supporterDob = $row->supporter_dob ?? null;
        $recipientDob = $row->recipient_dob ?? null;
        $intergenerational = $this->isIntergenerational(
            is_string($supporterDob) ? $supporterDob : null,
            is_string($recipientDob) ? $recipientDob : null,
        );

        return [
            'id'              => (int) $row->id,
            'title'           => (string) $row->title,
            'description'     => (string) ($row->description ?? ''),
            'frequency'       => (string) $row->frequency,
            'expected_hours'  => round((float) $row->expected_hours, 2),
            'status'          => (string) $row->status,
            'start_date'      => (string) $row->start_date,
            'end_date'        => $row->end_date ? (string) $row->end_date : null,
            'last_logged_at'  => $row->last_logged_at ? (string) $row->last_logged_at : null,
            'next_check_in_at' => $row->next_check_in_at ? (string) $row->next_check_in_at : null,
            'role'            => $role,
            'intergenerational' => $intergenerational,
            'partner'         => [
                'id'         => $partnerId,
                'name'       => $partnerName,
                'avatar_url' => $partnerAvatar ? (string) $partnerAvatar : null,
            ],
            'recent_logs'     => $recentLogs,
        ];
    }

    private function isIntergenerational(?string $dobA, ?string $dobB): bool
    {
        if ($dobA === null || $dobB === null || $dobA === '' || $dobB === '') {
            return false;
        }
        try {
            $tA = (new \DateTimeImmutable($dobA))->getTimestamp();
            $tB = (new \DateTimeImmutable($dobB))->getTimestamp();
        } catch (\Throwable) {
            return false;
        }
        $diffYears = abs($tA - $tB) / (365.25 * 24 * 3600);
        return $diffYears >= \App\Services\CaringTandemMatchingService::INTERGENERATIONAL_MIN_AGE_DIFF;
    }

    private function displayName(object $row, string $prefix): string
    {
        $full = trim(
            (string) ($row->{$prefix . '_first_name'} ?? '')
            . ' '
            . (string) ($row->{$prefix . '_last_name'} ?? '')
        );
        return $full !== '' ? $full : (string) ($row->{$prefix . '_name'} ?? '');
    }

    /**
     * Build a display name from a DB row that has user_first_name / user_last_name / user_name.
     */
    private function buildDisplayName(object $row): string
    {
        $full = trim(
            (string) ($row->user_first_name ?? '')
            . ' '
            . (string) ($row->user_last_name ?? '')
        );
        return $full !== '' ? $full : (string) ($row->user_name ?? '');
    }
}
