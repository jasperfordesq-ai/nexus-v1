<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Models\NewsletterBounce;
use Nexus\Services\EmailMonitorService;

/**
 * SendGrid Event Webhook Controller
 *
 * Receives event notifications from SendGrid (bounces, complaints, deliveries)
 * and feeds them into the existing bounce/suppression system.
 *
 * Endpoint: POST /api/webhooks/sendgrid/events
 * This is an unauthenticated endpoint — SendGrid sends events here.
 */
class SendGridWebhookController extends BaseApiController
{
    /**
     * POST /api/webhooks/sendgrid/events
     * Process SendGrid event webhook payload
     */
    public function events(): void
    {
        $payload = file_get_contents('php://input');

        if (empty($payload)) {
            $this->jsonResponse(['error' => 'Empty payload'], 400);
            return;
        }

        $events = json_decode($payload, true);

        if (!is_array($events)) {
            $this->jsonResponse(['error' => 'Invalid JSON payload'], 400);
            return;
        }

        $processed = 0;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $type = $event['event'] ?? '';
            $email = $event['email'] ?? '';
            $tenantId = (int) ($event['tenant_id'] ?? 0);

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Set tenant context for scoped queries
            if ($tenantId > 0) {
                TenantContext::setById($tenantId);
            }

            switch ($type) {
                case 'bounce':
                case 'dropped':
                    $this->handleBounce($event, $tenantId);
                    $processed++;
                    break;

                case 'spamreport':
                    $this->handleComplaint($event, $tenantId);
                    $processed++;
                    break;

                case 'delivered':
                    EmailMonitorService::recordEmailSend('sendgrid', true, $tenantId ?: null);
                    $processed++;
                    break;

                // Ignore open/click — we have custom tracking via NewsletterTrackingController
                default:
                    break;
            }
        }

        $this->jsonResponse(['received' => count($events), 'processed' => $processed]);
    }

    /**
     * Handle bounce/dropped events
     */
    private function handleBounce(array $event, int $tenantId): void
    {
        $email = $event['email'] ?? '';
        $sgType = $event['type'] ?? '';
        $reason = $event['reason'] ?? null;
        $status = $event['status'] ?? null;

        // SendGrid bounce types: 'bounce' (hard) or 'blocked' (soft/temporary)
        $bounceType = ($sgType === 'blocked')
            ? NewsletterBounce::BOUNCE_SOFT
            : NewsletterBounce::BOUNCE_HARD;

        NewsletterBounce::record(
            $email,
            $bounceType,
            null, // newsletter_id — not always available from transactional emails
            null, // queue_id
            $reason,
            $status
        );

        EmailMonitorService::recordEmailSend('sendgrid', false, $tenantId ?: null);
    }

    /**
     * Handle spam report/complaint events
     */
    private function handleComplaint(array $event, int $tenantId): void
    {
        $email = $event['email'] ?? '';

        NewsletterBounce::record(
            $email,
            NewsletterBounce::BOUNCE_COMPLAINT,
            null,
            null,
            'SendGrid spam report',
            null
        );

        EmailMonitorService::recordEmailSend('sendgrid', false, $tenantId ?: null);
    }
}
