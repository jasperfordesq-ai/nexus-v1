<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\EmailMonitorService;
use Illuminate\Http\JsonResponse;
use Nexus\Core\TenantContext;
use Nexus\Models\NewsletterBounce;

/**
 * SendGridWebhookController — Eloquent-powered SendGrid event webhook handler.
 *
 * Fully migrated from legacy delegation to native Laravel.
 * Receives event notifications from SendGrid (bounces, complaints, deliveries)
 * and feeds them into the existing bounce/suppression system.
 *
 * This is an unauthenticated endpoint — SendGrid sends events here.
 */
class SendGridWebhookController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly EmailMonitorService $emailMonitorService,
    ) {}

    /**
     * POST /api/v2/webhooks/sendgrid/events
     *
     * Process SendGrid event webhook payload.
     * Expects a JSON array of event objects from SendGrid.
     */
    public function events(): JsonResponse
    {
        $payload = request()->getContent();

        if (empty($payload)) {
            return $this->respondWithError('INVALID_PAYLOAD', 'Empty payload', null, 400);
        }

        $events = json_decode($payload, true);

        if (!is_array($events)) {
            return $this->respondWithError('INVALID_PAYLOAD', 'Invalid JSON payload', null, 400);
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
                    $this->emailMonitorService->recordEmailSend('sendgrid', true, $tenantId ?: null);
                    $processed++;
                    break;

                // Ignore open/click — we have custom tracking via NewsletterTrackingController
                default:
                    break;
            }
        }

        return response()->json([
            'received' => count($events),
            'processed' => $processed,
        ]);
    }

    /**
     * Handle bounce/dropped events.
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

        $this->emailMonitorService->recordEmailSend('sendgrid', false, $tenantId ?: null);
    }

    /**
     * Handle spam report/complaint events.
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

        $this->emailMonitorService->recordEmailSend('sendgrid', false, $tenantId ?: null);
    }
}
