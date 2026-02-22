<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Models\NewsletterSubscriber;

/**
 * Public newsletter API endpoints (no auth required).
 * Handles unsubscribe actions initiated from email links.
 */
class NewsletterApiController
{
    /**
     * POST /api/v2/newsletter/unsubscribe
     *
     * Processes a newsletter unsubscribe using a token from an email link.
     * No authentication required — token acts as the credential.
     *
     * Body: { "token": "..." }
     * Returns: { "success": true } or { "success": false, "message": "..." }
     */
    public function unsubscribe(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $token = trim($input['token'] ?? $_GET['token'] ?? '');

        if (empty($token)) {
            http_response_code(400);
            $this->json(['success' => false, 'message' => 'Unsubscribe token is required.']);
            return;
        }

        $subscriber = NewsletterSubscriber::findByUnsubscribeToken($token);

        if (!$subscriber) {
            http_response_code(404);
            $this->json(['success' => false, 'message' => 'This unsubscribe link is invalid or has already been used.']);
            return;
        }

        if ($subscriber['status'] === 'unsubscribed') {
            $this->json(['success' => true, 'message' => 'You are already unsubscribed.', 'already_done' => true]);
            return;
        }

        $result = NewsletterSubscriber::unsubscribe($token, 'email_link');

        if ($result) {
            $this->json(['success' => true]);
        } else {
            http_response_code(500);
            $this->json(['success' => false, 'message' => 'Unable to process your request. Please try again.']);
        }
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
