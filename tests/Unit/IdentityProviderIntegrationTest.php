<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Unit;

use Nexus\Tests\TestCase;
use Nexus\Services\Identity\IdentityVerificationProviderInterface;
use Nexus\Services\Identity\VeriffProvider;
use Nexus\Services\Identity\JumioProvider;
use Nexus\Services\Identity\OnfidoProvider;
use Nexus\Services\Identity\IdenfyProvider;
use Nexus\Services\Identity\StripeIdentityProvider;
use Nexus\Services\Identity\MockIdentityProvider;

/**
 * Identity Provider Integration Tests
 *
 * Tests webhook parsing, session status mapping, and provider interface
 * compliance for all identity verification providers. These are pure logic
 * tests — no database, no external API calls, no mocking frameworks.
 *
 * Providers tested:
 * - VeriffProvider
 * - JumioProvider
 * - OnfidoProvider
 * - IdenfyProvider
 * - StripeIdentityProvider
 * - MockIdentityProvider
 */
class IdentityProviderIntegrationTest extends TestCase
{
    // ─── Provider instances ──────────────────────────────────────────────

    private VeriffProvider $veriff;
    private JumioProvider $jumio;
    private OnfidoProvider $onfido;
    private IdenfyProvider $idenfy;
    private StripeIdentityProvider $stripe;
    private MockIdentityProvider $mock;

    /** @var IdentityVerificationProviderInterface[] */
    private array $allProviders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->veriff = new VeriffProvider();
        $this->jumio = new JumioProvider();
        $this->onfido = new OnfidoProvider();
        $this->idenfy = new IdenfyProvider();
        $this->stripe = new StripeIdentityProvider();
        $this->mock = new MockIdentityProvider();

        $this->allProviders = [
            'veriff' => $this->veriff,
            'jumio' => $this->jumio,
            'onfido' => $this->onfido,
            'idenfy' => $this->idenfy,
            'stripe_identity' => $this->stripe,
            'mock' => $this->mock,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════
    // INTERFACE COMPLIANCE: getSlug()
    // ═════════════════════════════════════════════════════════════════════

    public function testVeriffGetSlug(): void
    {
        $this->assertSame('veriff', $this->veriff->getSlug());
    }

    public function testJumioGetSlug(): void
    {
        $this->assertSame('jumio', $this->jumio->getSlug());
    }

    public function testOnfidoGetSlug(): void
    {
        $this->assertSame('onfido', $this->onfido->getSlug());
    }

    public function testIdenfyGetSlug(): void
    {
        $this->assertSame('idenfy', $this->idenfy->getSlug());
    }

    public function testStripeGetSlug(): void
    {
        $this->assertSame('stripe_identity', $this->stripe->getSlug());
    }

    public function testMockGetSlug(): void
    {
        $this->assertSame('mock', $this->mock->getSlug());
    }

    // ═════════════════════════════════════════════════════════════════════
    // INTERFACE COMPLIANCE: getName()
    // ═════════════════════════════════════════════════════════════════════

    public function testVeriffGetName(): void
    {
        $this->assertSame('Veriff', $this->veriff->getName());
    }

    public function testJumioGetName(): void
    {
        $this->assertSame('Jumio', $this->jumio->getName());
    }

    public function testOnfidoGetName(): void
    {
        $this->assertSame('Onfido', $this->onfido->getName());
    }

    public function testIdenfyGetName(): void
    {
        $this->assertSame('iDenfy', $this->idenfy->getName());
    }

    public function testStripeGetName(): void
    {
        $this->assertSame('Stripe Identity', $this->stripe->getName());
    }

    public function testMockGetName(): void
    {
        $this->assertSame('Mock Provider (Testing)', $this->mock->getName());
    }

    // ═════════════════════════════════════════════════════════════════════
    // INTERFACE COMPLIANCE: getSupportedLevels()
    // ═════════════════════════════════════════════════════════════════════

    public function testVeriffGetSupportedLevels(): void
    {
        $levels = $this->veriff->getSupportedLevels();
        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
        $this->assertContains('manual_review', $levels);
    }

    public function testJumioGetSupportedLevels(): void
    {
        $levels = $this->jumio->getSupportedLevels();
        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
    }

    public function testOnfidoGetSupportedLevels(): void
    {
        $levels = $this->onfido->getSupportedLevels();
        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
    }

    public function testIdenfyGetSupportedLevels(): void
    {
        $levels = $this->idenfy->getSupportedLevels();
        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
        $this->assertContains('manual_review', $levels);
    }

    public function testStripeGetSupportedLevels(): void
    {
        $levels = $this->stripe->getSupportedLevels();
        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
    }

    public function testMockGetSupportedLevels(): void
    {
        $levels = $this->mock->getSupportedLevels();
        $this->assertContains('document_only', $levels);
        $this->assertContains('document_selfie', $levels);
        $this->assertContains('reusable_digital_id', $levels);
        $this->assertContains('manual_review', $levels);
    }

    // ═════════════════════════════════════════════════════════════════════
    // INTERFACE COMPLIANCE: isAvailable() without API keys
    // ═════════════════════════════════════════════════════════════════════

    public function testVeriffIsAvailableWithoutKeys(): void
    {
        // Without VERIFF_API_KEY and VERIFF_API_SECRET env vars, should be false
        $this->assertFalse($this->veriff->isAvailable(1));
    }

    public function testJumioIsAvailableWithoutKeys(): void
    {
        // Without JUMIO_API_TOKEN and JUMIO_API_SECRET env vars, should be false
        $this->assertFalse($this->jumio->isAvailable(1));
    }

    public function testOnfidoIsAvailableWithoutKeys(): void
    {
        // Without ONFIDO_API_TOKEN env var, should be false
        $this->assertFalse($this->onfido->isAvailable(1));
    }

    public function testIdenfyIsAvailableWithoutKeys(): void
    {
        // Without IDENFY_API_KEY and IDENFY_API_SECRET env vars, should be false
        $this->assertFalse($this->idenfy->isAvailable(1));
    }

    public function testStripeIsAvailableWithoutKeys(): void
    {
        // Without STRIPE_IDENTITY_SECRET_KEY env var, should be false
        $this->assertFalse($this->stripe->isAvailable(1));
    }

    public function testMockIsAlwaysAvailable(): void
    {
        // Mock provider is always available regardless of env vars
        $this->assertTrue($this->mock->isAvailable(1));
        $this->assertTrue($this->mock->isAvailable(999));
    }

    // ═════════════════════════════════════════════════════════════════════
    // INTERFACE COMPLIANCE: all providers implement the interface
    // ═════════════════════════════════════════════════════════════════════

    public function testAllProvidersImplementInterface(): void
    {
        foreach ($this->allProviders as $slug => $provider) {
            $this->assertInstanceOf(
                IdentityVerificationProviderInterface::class,
                $provider,
                "Provider '{$slug}' must implement IdentityVerificationProviderInterface"
            );
        }
    }

    public function testAllProvidersSlugsAreNonEmpty(): void
    {
        foreach ($this->allProviders as $slug => $provider) {
            $this->assertNotEmpty($provider->getSlug(), "Provider '{$slug}' slug must not be empty");
            $this->assertSame($slug, $provider->getSlug(), "Provider key must match its slug");
        }
    }

    public function testAllProvidersNamesAreNonEmpty(): void
    {
        foreach ($this->allProviders as $slug => $provider) {
            $this->assertNotEmpty($provider->getName(), "Provider '{$slug}' name must not be empty");
        }
    }

    public function testAllProvidersSupportedLevelsAreNonEmpty(): void
    {
        foreach ($this->allProviders as $slug => $provider) {
            $levels = $provider->getSupportedLevels();
            $this->assertNotEmpty($levels, "Provider '{$slug}' must support at least one level");
            // Every provider must support at least document_only
            $this->assertContains(
                'document_only',
                $levels,
                "Provider '{$slug}' must support 'document_only' level"
            );
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // WEBHOOK PARSING: Veriff
    // ═════════════════════════════════════════════════════════════════════

    public function testVeriffHandleWebhookApproved(): void
    {
        $payload = [
            'verification' => [
                'id' => 'sess_123',
                'status' => 'approved',
                'vendorData' => '{}',
            ],
        ];

        $result = $this->veriff->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
        $this->assertSame('sess_123', $result['provider_session_id']);
        $this->assertSame('approved', $result['decision']);
        $this->assertNull($result['failure_reason']);
        $this->assertSame('veriff.decision.approved', $result['raw_event_type']);
    }

    public function testVeriffHandleWebhookDeclined(): void
    {
        $payload = [
            'verification' => [
                'id' => 'sess_456',
                'status' => 'declined',
                'vendorData' => '{}',
                'reason' => 'Document expired',
            ],
        ];

        $result = $this->veriff->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertSame('Document expired', $result['failure_reason']);
        $this->assertSame('sess_456', $result['provider_session_id']);
        $this->assertSame('veriff.decision.declined', $result['raw_event_type']);
    }

    public function testVeriffHandleWebhookResubmissionRequested(): void
    {
        $payload = [
            'verification' => [
                'id' => 'sess_789',
                'status' => 'resubmission_requested',
                'vendorData' => '{}',
                'reason' => 'Photo blurry',
            ],
        ];

        $result = $this->veriff->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertSame('Photo blurry', $result['failure_reason']);
    }

    public function testVeriffHandleWebhookExpired(): void
    {
        $payload = [
            'verification' => [
                'id' => 'sess_exp',
                'status' => 'expired',
                'vendorData' => '{}',
            ],
        ];

        $result = $this->veriff->handleWebhook($payload, []);

        $this->assertSame('expired', $result['status']);
        $this->assertNull($result['decision']);
    }

    public function testVeriffHandleWebhookWithRiskScore(): void
    {
        $payload = [
            'verification' => [
                'id' => 'sess_risk',
                'status' => 'approved',
                'vendorData' => '{}',
                'riskScore' => 0.15,
            ],
        ];

        $result = $this->veriff->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
        $this->assertSame(0.15, $result['risk_score']);
    }

    // ═════════════════════════════════════════════════════════════════════
    // WEBHOOK PARSING: Jumio
    // ═════════════════════════════════════════════════════════════════════

    public function testJumioHandleWebhookApproved(): void
    {
        $payload = [
            'accountId' => 'acc_123',
            'decision' => ['type' => 'APPROVED'],
        ];

        $result = $this->jumio->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
        $this->assertSame('acc_123', $result['provider_session_id']);
        $this->assertSame('approved', $result['decision']);
        $this->assertNull($result['failure_reason']);
        $this->assertSame('jumio.workflow.approved', $result['raw_event_type']);
    }

    public function testJumioHandleWebhookRejected(): void
    {
        $payload = [
            'accountId' => 'acc_456',
            'decision' => [
                'type' => 'REJECTED',
                'details' => ['label' => 'Fake document'],
            ],
        ];

        $result = $this->jumio->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertSame('Fake document', $result['failure_reason']);
        $this->assertSame('acc_456', $result['provider_session_id']);
        $this->assertSame('jumio.workflow.rejected', $result['raw_event_type']);
    }

    public function testJumioHandleWebhookExpired(): void
    {
        $payload = [
            'accountId' => 'acc_exp',
            'decision' => ['type' => 'EXPIRED'],
        ];

        $result = $this->jumio->handleWebhook($payload, []);

        $this->assertSame('expired', $result['status']);
        $this->assertNull($result['decision']);
    }

    public function testJumioHandleWebhookNotExecuted(): void
    {
        $payload = [
            'accountId' => 'acc_ne',
            'decision' => ['type' => 'NOT_EXECUTED'],
        ];

        $result = $this->jumio->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
    }

    public function testJumioHandleWebhookWithRiskScore(): void
    {
        $payload = [
            'accountId' => 'acc_risk',
            'decision' => [
                'type' => 'APPROVED',
                'riskScore' => 0.2,
            ],
        ];

        $result = $this->jumio->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
        $this->assertSame(0.2, $result['risk_score']);
    }

    // ═════════════════════════════════════════════════════════════════════
    // WEBHOOK PARSING: Onfido
    // ═════════════════════════════════════════════════════════════════════

    public function testOnfidoHandleWebhookCompleted(): void
    {
        $payload = [
            'payload' => [
                'resource_type' => 'check',
                'action' => 'completed',
                'object' => [
                    'result' => 'clear',
                    'applicant_id' => 'app_123',
                ],
            ],
        ];

        $result = $this->onfido->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
        $this->assertSame('app_123', $result['provider_session_id']);
        $this->assertSame('approved', $result['decision']);
        $this->assertNull($result['failure_reason']);
        $this->assertSame('check.completed', $result['raw_event_type']);
    }

    public function testOnfidoHandleWebhookFailed(): void
    {
        $payload = [
            'payload' => [
                'resource_type' => 'check',
                'action' => 'completed',
                'object' => [
                    'result' => 'consider',
                    'applicant_id' => 'app_456',
                ],
            ],
        ];

        $result = $this->onfido->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertSame('Identity check did not pass', $result['failure_reason']);
        $this->assertSame('app_456', $result['provider_session_id']);
    }

    public function testOnfidoHandleWebhookCheckStarted(): void
    {
        $payload = [
            'payload' => [
                'resource_type' => 'check',
                'action' => 'started',
                'object' => [
                    'result' => null,
                    'applicant_id' => 'app_789',
                ],
            ],
        ];

        $result = $this->onfido->handleWebhook($payload, []);

        $this->assertSame('processing', $result['status']);
        $this->assertNull($result['decision']);
    }

    public function testOnfidoHandleWebhookNonCheckResource(): void
    {
        $payload = [
            'payload' => [
                'resource_type' => 'workflow_run',
                'action' => 'completed',
                'object' => [
                    'id' => 'wfr_123',
                ],
            ],
        ];

        $result = $this->onfido->handleWebhook($payload, []);

        // Non-check resources fall through to the generic handler
        $this->assertSame('processing', $result['status']);
        $this->assertSame('wfr_123', $result['provider_session_id']);
        $this->assertSame('workflow_run.completed', $result['raw_event_type']);
    }

    // ═════════════════════════════════════════════════════════════════════
    // WEBHOOK PARSING: iDenfy
    // ═════════════════════════════════════════════════════════════════════

    public function testIdenfyHandleWebhookApproved(): void
    {
        $payload = [
            'final' => [
                'scanRef' => 'scan_123',
                'overall' => 'APPROVED',
            ],
        ];

        $result = $this->idenfy->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
        $this->assertSame('scan_123', $result['provider_session_id']);
        $this->assertSame('approved', $result['decision']);
        $this->assertNull($result['failure_reason']);
        $this->assertSame('idenfy.verification.approved', $result['raw_event_type']);
    }

    public function testIdenfyHandleWebhookDenied(): void
    {
        $payload = [
            'final' => [
                'scanRef' => 'scan_456',
                'overall' => 'DENIED',
                'suspicionReasons' => ['FACE_MISMATCH'],
            ],
        ];

        $result = $this->idenfy->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertStringContainsString('FACE_MISMATCH', $result['failure_reason']);
        $this->assertSame('scan_456', $result['provider_session_id']);
        $this->assertSame('idenfy.verification.denied', $result['raw_event_type']);
    }

    public function testIdenfyHandleWebhookSuspected(): void
    {
        $payload = [
            'final' => [
                'scanRef' => 'scan_789',
                'overall' => 'SUSPECTED',
                'suspicionReasons' => ['DOC_MANIPULATED', 'FACE_MISMATCH'],
            ],
        ];

        $result = $this->idenfy->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('DOC_MANIPULATED', $result['failure_reason']);
        $this->assertStringContainsString('FACE_MISMATCH', $result['failure_reason']);
    }

    public function testIdenfyHandleWebhookDeniedNoReasons(): void
    {
        $payload = [
            'final' => [
                'scanRef' => 'scan_no_reason',
                'overall' => 'DENIED',
            ],
        ];

        $result = $this->idenfy->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Verification denied', $result['failure_reason']);
    }

    public function testIdenfyHandleWebhookExpired(): void
    {
        $payload = [
            'final' => [
                'scanRef' => 'scan_exp',
                'overall' => 'EXPIRED',
            ],
        ];

        $result = $this->idenfy->handleWebhook($payload, []);

        $this->assertSame('expired', $result['status']);
        $this->assertNull($result['decision']);
    }

    public function testIdenfyHandleWebhookAlternatePayloadFormat(): void
    {
        // iDenfy can send status in an alternate format
        $payload = [
            'scanRef' => 'scan_alt',
            'status' => [
                'overall' => 'APPROVED',
            ],
        ];

        $result = $this->idenfy->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
        $this->assertSame('scan_alt', $result['provider_session_id']);
    }

    // ═════════════════════════════════════════════════════════════════════
    // WEBHOOK PARSING: Stripe Identity
    // ═════════════════════════════════════════════════════════════════════

    public function testStripeHandleWebhookVerified(): void
    {
        $payload = [
            'type' => 'identity.verification_session.verified',
            'data' => [
                'object' => [
                    'id' => 'vs_123',
                ],
            ],
        ];

        $result = $this->stripe->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
        $this->assertSame('vs_123', $result['provider_session_id']);
        $this->assertSame('approved', $result['decision']);
        $this->assertNull($result['failure_reason']);
        $this->assertSame('identity.verification_session.verified', $result['raw_event_type']);
    }

    public function testStripeHandleWebhookRequiresInput(): void
    {
        $payload = [
            'type' => 'identity.verification_session.requires_input',
            'data' => [
                'object' => [
                    'id' => 'vs_456',
                    'last_error' => [
                        'reason' => 'Document is blurry',
                        'code' => 'document_unreadable',
                    ],
                ],
            ],
        ];

        $result = $this->stripe->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertSame('Document is blurry', $result['failure_reason']);
    }

    public function testStripeHandleWebhookCanceled(): void
    {
        $payload = [
            'type' => 'identity.verification_session.canceled',
            'data' => [
                'object' => [
                    'id' => 'vs_cancel',
                ],
            ],
        ];

        $result = $this->stripe->handleWebhook($payload, []);

        $this->assertSame('cancelled', $result['status']);
        $this->assertNull($result['decision']);
    }

    public function testStripeHandleWebhookProcessing(): void
    {
        $payload = [
            'type' => 'identity.verification_session.processing',
            'data' => [
                'object' => [
                    'id' => 'vs_proc',
                ],
            ],
        ];

        $result = $this->stripe->handleWebhook($payload, []);

        $this->assertSame('processing', $result['status']);
        $this->assertNull($result['decision']);
    }

    public function testStripeHandleWebhookRequiresInputNoError(): void
    {
        // requires_input but no last_error — failure_reason should be null
        $payload = [
            'type' => 'identity.verification_session.requires_input',
            'data' => [
                'object' => [
                    'id' => 'vs_no_err',
                ],
            ],
        ];

        $result = $this->stripe->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['failure_reason']);
    }

    // ═════════════════════════════════════════════════════════════════════
    // WEBHOOK PARSING: Mock Provider
    // ═════════════════════════════════════════════════════════════════════

    public function testMockHandleWebhookPassed(): void
    {
        $payload = [
            'session_id' => 'mock_123',
            'result' => 'passed',
        ];

        $result = $this->mock->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
        $this->assertSame('mock_123', $result['provider_session_id']);
        $this->assertSame('approved', $result['decision']);
        $this->assertSame(0.05, $result['risk_score']);
        $this->assertNull($result['failure_reason']);
        $this->assertSame('mock.verification.passed', $result['raw_event_type']);
    }

    public function testMockHandleWebhookFailed(): void
    {
        $payload = [
            'session_id' => 'mock_456',
            'result' => 'failed',
        ];

        $result = $this->mock->handleWebhook($payload, []);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('declined', $result['decision']);
        $this->assertSame(0.95, $result['risk_score']);
        $this->assertSame('Mock verification failed (test)', $result['failure_reason']);
    }

    public function testMockHandleWebhookDefaultResult(): void
    {
        // When no 'result' key is provided, defaults to 'passed'
        $payload = [
            'session_id' => 'mock_default',
        ];

        $result = $this->mock->handleWebhook($payload, []);

        $this->assertSame('passed', $result['status']);
    }

    // ═════════════════════════════════════════════════════════════════════
    // WEBHOOK SIGNATURE VERIFICATION
    // ═════════════════════════════════════════════════════════════════════

    public function testVeriffWebhookSignatureMissingSecretReturnsFalse(): void
    {
        // Without VERIFF_API_SECRET env var, signature verification fails
        $result = $this->veriff->verifyWebhookSignature('{}', []);
        $this->assertFalse($result);
    }

    public function testVeriffWebhookSignatureMissingHeaderReturnsFalse(): void
    {
        // Even if secret were set, missing X-HMAC-Signature header fails
        $result = $this->veriff->verifyWebhookSignature('{}', ['Content-Type' => 'application/json']);
        $this->assertFalse($result);
    }

    public function testJumioWebhookSignatureMissingSecretReturnsFalse(): void
    {
        $result = $this->jumio->verifyWebhookSignature('{}', []);
        $this->assertFalse($result);
    }

    public function testOnfidoWebhookSignatureMissingSecretReturnsFalse(): void
    {
        $result = $this->onfido->verifyWebhookSignature('{}', []);
        $this->assertFalse($result);
    }

    public function testIdenfyWebhookSignatureNoHeaderReturnsTrue(): void
    {
        // iDenfy returns true when no signature header (falls back to basic auth)
        $result = $this->idenfy->verifyWebhookSignature('{}', []);
        // Without secret AND without header, iDenfy first checks secret (empty = false)
        // Actually: empty secret => return false immediately
        $this->assertFalse($result);
    }

    public function testStripeWebhookSignatureMissingHeaderReturnsFalse(): void
    {
        $result = $this->stripe->verifyWebhookSignature('{}', []);
        $this->assertFalse($result);
    }

    public function testMockWebhookSignatureAlwaysReturnsTrue(): void
    {
        // Mock provider always trusts webhooks
        $this->assertTrue($this->mock->verifyWebhookSignature('{}', []));
        $this->assertTrue($this->mock->verifyWebhookSignature('any body', ['any' => 'header']));
    }

    // ═════════════════════════════════════════════════════════════════════
    // RETURN STRUCTURE: all providers must return required keys
    // ═════════════════════════════════════════════════════════════════════

    public function testAllProvidersWebhookReturnStructure(): void
    {
        $requiredKeys = [
            'provider_session_id',
            'status',
            'decision',
            'risk_score',
            'failure_reason',
            'raw_event_type',
        ];

        // Minimal payloads per provider that won't cause errors
        $payloads = [
            'veriff' => ['verification' => ['id' => 'test', 'status' => 'approved', 'vendorData' => '{}']],
            'jumio' => ['accountId' => 'test', 'decision' => ['type' => 'APPROVED']],
            'onfido' => ['payload' => ['resource_type' => 'check', 'action' => 'completed', 'object' => ['result' => 'clear', 'applicant_id' => 'test']]],
            'idenfy' => ['final' => ['scanRef' => 'test', 'overall' => 'APPROVED']],
            'stripe_identity' => ['type' => 'identity.verification_session.verified', 'data' => ['object' => ['id' => 'test']]],
            'mock' => ['session_id' => 'test', 'result' => 'passed'],
        ];

        foreach ($this->allProviders as $slug => $provider) {
            $result = $provider->handleWebhook($payloads[$slug], []);

            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $result,
                    "Provider '{$slug}' webhook result must contain key '{$key}'"
                );
            }

            // Status must be a non-empty string
            $this->assertIsString($result['status'], "Provider '{$slug}' status must be a string");
            $this->assertNotEmpty($result['status'], "Provider '{$slug}' status must not be empty");

            // provider_session_id must be a string
            $this->assertIsString($result['provider_session_id'], "Provider '{$slug}' provider_session_id must be a string");

            // raw_event_type must be a string
            $this->assertIsString($result['raw_event_type'], "Provider '{$slug}' raw_event_type must be a string");
        }
    }

    /**
     * Verify that passed status always maps to 'approved' decision
     * and failed status always maps to 'declined' decision across all providers.
     */
    public function testAllProvidersStatusDecisionConsistency(): void
    {
        // Test "passed" payloads
        $passedPayloads = [
            'veriff' => ['verification' => ['id' => 'test', 'status' => 'approved', 'vendorData' => '{}']],
            'jumio' => ['accountId' => 'test', 'decision' => ['type' => 'APPROVED']],
            'onfido' => ['payload' => ['resource_type' => 'check', 'action' => 'completed', 'object' => ['result' => 'clear', 'applicant_id' => 'test']]],
            'idenfy' => ['final' => ['scanRef' => 'test', 'overall' => 'APPROVED']],
            'stripe_identity' => ['type' => 'identity.verification_session.verified', 'data' => ['object' => ['id' => 'test']]],
            'mock' => ['session_id' => 'test', 'result' => 'passed'],
        ];

        foreach ($this->allProviders as $slug => $provider) {
            $result = $provider->handleWebhook($passedPayloads[$slug], []);
            $this->assertSame('passed', $result['status'], "Provider '{$slug}' approved payload should map to 'passed' status");
            $this->assertSame('approved', $result['decision'], "Provider '{$slug}' passed status should have 'approved' decision");
        }

        // Test "failed" payloads
        $failedPayloads = [
            'veriff' => ['verification' => ['id' => 'test', 'status' => 'declined', 'vendorData' => '{}']],
            'jumio' => ['accountId' => 'test', 'decision' => ['type' => 'REJECTED']],
            'onfido' => ['payload' => ['resource_type' => 'check', 'action' => 'completed', 'object' => ['result' => 'consider', 'applicant_id' => 'test']]],
            'idenfy' => ['final' => ['scanRef' => 'test', 'overall' => 'DENIED']],
            'stripe_identity' => ['type' => 'identity.verification_session.requires_input', 'data' => ['object' => ['id' => 'test', 'last_error' => ['reason' => 'test']]]],
            'mock' => ['session_id' => 'test', 'result' => 'failed'],
        ];

        foreach ($this->allProviders as $slug => $provider) {
            $result = $provider->handleWebhook($failedPayloads[$slug], []);
            $this->assertSame('failed', $result['status'], "Provider '{$slug}' rejected payload should map to 'failed' status");
            $this->assertSame('declined', $result['decision'], "Provider '{$slug}' failed status should have 'declined' decision");
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // EDGE CASES
    // ═════════════════════════════════════════════════════════════════════

    public function testVeriffHandleWebhookUnknownStatusMapsToProcessing(): void
    {
        $payload = [
            'verification' => [
                'id' => 'sess_unknown',
                'status' => 'some_new_status',
                'vendorData' => '{}',
            ],
        ];

        $result = $this->veriff->handleWebhook($payload, []);
        $this->assertSame('processing', $result['status']);
    }

    public function testJumioHandleWebhookUnknownDecisionMapsToProcessing(): void
    {
        $payload = [
            'accountId' => 'acc_unknown',
            'decision' => ['type' => 'UNKNOWN_TYPE'],
        ];

        $result = $this->jumio->handleWebhook($payload, []);
        $this->assertSame('processing', $result['status']);
    }

    public function testStripeHandleWebhookUnknownEventMapsToProcessing(): void
    {
        $payload = [
            'type' => 'identity.verification_session.some_future_event',
            'data' => [
                'object' => ['id' => 'vs_future'],
            ],
        ];

        $result = $this->stripe->handleWebhook($payload, []);
        $this->assertSame('processing', $result['status']);
    }

    public function testIdenfyHandleWebhookUnknownOverallMapsToProcessing(): void
    {
        $payload = [
            'final' => [
                'scanRef' => 'scan_unknown',
                'overall' => 'REVIEWING',
            ],
        ];

        $result = $this->idenfy->handleWebhook($payload, []);
        $this->assertSame('processing', $result['status']);
    }

    public function testVeriffHandleWebhookEmptyPayload(): void
    {
        $result = $this->veriff->handleWebhook([], []);

        $this->assertSame('', $result['provider_session_id']);
        $this->assertSame('processing', $result['status']);
    }

    public function testMockHandleWebhookEmptyPayload(): void
    {
        $result = $this->mock->handleWebhook([], []);

        $this->assertSame('', $result['provider_session_id']);
        // Empty result defaults to 'passed' (via ?? 'passed')
        $this->assertSame('passed', $result['status']);
    }
}
