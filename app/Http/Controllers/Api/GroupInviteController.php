<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupInviteService;

/**
 * GroupInviteController — Invite links, email invites, acceptance, and revocation for groups.
 */
class GroupInviteController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GroupInviteService $inviteService,
    ) {}

    /**
     * GET /api/v2/groups/{id}/invites
     */
    public function index(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $result = $this->inviteService->getPendingInvites($id, $userId);

        if ($result === null) {
            $errors = $this->inviteService->getErrors();
            $code = ($errors[0]['code'] ?? '') === 'FORBIDDEN' ? 403 : 400;
            return $this->errorResponse($errors[0]['message'] ?? __('errors.group_invites.listing_failed'), $code);
        }

        return $this->successResponse($result);
    }

    /**
     * POST /api/v2/groups/{id}/invites/link
     */
    public function createLink(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $expiryDays = request()->input('expiry_days');
        $result = $this->inviteService->createLink($id, $userId, $expiryDays);

        if ($result === null) {
            $errors = $this->inviteService->getErrors();
            $code = ($errors[0]['code'] ?? '') === 'FORBIDDEN' ? 403 : 400;
            return $this->errorResponse($errors[0]['message'] ?? __('errors.group_invites.create_failed'), $code);
        }

        return $this->successResponse($result, 201);
    }

    /**
     * POST /api/v2/groups/{id}/invites/email
     */
    public function sendEmails(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        // Email fan-out is a reflection/DoS target — an attacker can use us as
        // a relay to spam external addresses. Cap per-user per-hour. The
        // per-invite batch is additionally capped inside sendEmailInvites.
        $this->rateLimit('group_invite_email', 10, 3600);

        $emails = request()->input('emails');

        if (!is_array($emails) || empty($emails)) {
            return $this->errorResponse(__('api.group_invites_emails_required'), 422);
        }
        // Hard ceiling on batch size so one call can't mail 10k addresses.
        if (count($emails) > 50) {
            return $this->errorResponse(__('api.group_invites_max_per_request'), 422);
        }

        $message = request()->input('message', '');
        $result = $this->inviteService->sendEmailInvites($id, $userId, $emails, $message);

        if ($result === null) {
            $errors = $this->inviteService->getErrors();
            $code = ($errors[0]['code'] ?? '') === 'FORBIDDEN' ? 403 : 400;
            return $this->errorResponse($errors[0]['message'] ?? __('errors.group_invites.send_failed'), $code);
        }

        return $this->successResponse($result);
    }

    /**
     * DELETE /api/v2/groups/{id}/invites/{inviteId}
     */
    public function revoke(int $id, int $inviteId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $success = $this->inviteService->revokeInvite($id, $inviteId, $userId);

        if (!$success) {
            $errors = $this->inviteService->getErrors();
            $code = match ($errors[0]['code'] ?? '') {
                'NOT_FOUND' => 404,
                'FORBIDDEN' => 403,
                default => 400,
            };
            return $this->errorResponse($errors[0]['message'] ?? __('errors.group_invites.revoke_failed'), $code);
        }

        return $this->successResponse(['message' => __('api_controllers_1.group_invite.invite_revoked')]);
    }

    /**
     * POST /api/v2/invites/{token}/accept
     */
    public function accept(string $token): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $result = $this->inviteService->acceptInvite($token, $userId);

        if ($result === null) {
            $errors = $this->inviteService->getErrors();
            $code = match ($errors[0]['code'] ?? '') {
                'NOT_FOUND' => 404,
                'EXPIRED' => 410,
                'ALREADY_MEMBER' => 409,
                'FORBIDDEN' => 403,
                default => 400,
            };
            return $this->errorResponse($errors[0]['message'] ?? __('errors.group_invites.accept_failed'), $code);
        }

        return $this->successResponse($result);
    }
}
