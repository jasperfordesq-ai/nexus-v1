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

        $result = $this->inviteService->getPendingInvites($id);

        if ($result === null) {
            $errors = $this->inviteService->getErrors();
            $code = ($errors[0]['code'] ?? '') === 'FORBIDDEN' ? 403 : 400;
            return $this->errorResponse($errors[0]['message'] ?? 'Error listing invites', $code);
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
            return $this->errorResponse($errors[0]['message'] ?? 'Error creating invite link', $code);
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

        $emails = request()->input('emails');

        if (!is_array($emails) || empty($emails)) {
            return $this->errorResponse('A valid emails array is required', 422);
        }

        $message = request()->input('message', '');
        $result = $this->inviteService->sendEmailInvites($id, $userId, $emails, $message);

        if ($result === null) {
            $errors = $this->inviteService->getErrors();
            $code = ($errors[0]['code'] ?? '') === 'FORBIDDEN' ? 403 : 400;
            return $this->errorResponse($errors[0]['message'] ?? 'Error sending invites', $code);
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

        $success = $this->inviteService->revokeInvite($inviteId, $userId);

        if (!$success) {
            $errors = $this->inviteService->getErrors();
            $code = match ($errors[0]['code'] ?? '') {
                'NOT_FOUND' => 404,
                'FORBIDDEN' => 403,
                default => 400,
            };
            return $this->errorResponse($errors[0]['message'] ?? 'Error revoking invite', $code);
        }

        return $this->successResponse(['message' => 'Invite revoked']);
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
                default => 400,
            };
            return $this->errorResponse($errors[0]['message'] ?? 'Error accepting invite', $code);
        }

        return $this->successResponse($result);
    }
}
