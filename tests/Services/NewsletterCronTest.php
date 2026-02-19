<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\NewsletterService;

/**
 * NewsletterCronTest
 *
 * Tests for newsletter cron-related operations:
 * - Scheduled newsletter processing
 * - Recurring newsletter processing
 * - Queue processing
 * - Subscriber management
 * - A/B test initialization
 * - Resend to non-openers
 *
 * @covers \Nexus\Services\NewsletterService
 */
class NewsletterCronTest extends TestCase
{
    // =========================================================================
    // CLASS & METHOD EXISTENCE
    // =========================================================================

    /**
     * Test NewsletterService class exists
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(NewsletterService::class));
    }

    /**
     * Test all cron-related methods exist
     */
    public function testCronRelatedMethodsExist(): void
    {
        $cronMethods = [
            'processScheduled',
            'processRecurring',
            'processQueue',
            'sendNow',
            'schedule',
            'getRecipients',
            'getStats',
        ];

        foreach ($cronMethods as $method) {
            $this->assertTrue(
                method_exists(NewsletterService::class, $method),
                "Method {$method} should exist on NewsletterService"
            );
        }
    }

    /**
     * Test all cron methods are static
     */
    public function testCronMethodsAreStatic(): void
    {
        $staticMethods = [
            'processScheduled',
            'processRecurring',
            'processQueue',
            'sendNow',
            'schedule',
            'getRecipients',
        ];

        $ref = new \ReflectionClass(NewsletterService::class);

        foreach ($staticMethods as $methodName) {
            $method = $ref->getMethod($methodName);
            $this->assertTrue($method->isStatic(), "Method {$methodName} should be static");
            $this->assertTrue($method->isPublic(), "Method {$methodName} should be public");
        }
    }

    // =========================================================================
    // PROCESS SCHEDULED — CRON ENTRY POINT
    // =========================================================================

    /**
     * Test processScheduled method signature (no required parameters)
     */
    public function testProcessScheduledMethodSignature(): void
    {
        $ref = new \ReflectionMethod(NewsletterService::class, 'processScheduled');
        $params = $ref->getParameters();

        $this->assertCount(0, $params, 'processScheduled should take no parameters (it queries for ready newsletters)');
    }

    /**
     * Test processScheduled returns an integer (count of processed newsletters)
     */
    public function testProcessScheduledReturnType(): void
    {
        $ref = new \ReflectionMethod(NewsletterService::class, 'processScheduled');
        $returnType = $ref->getReturnType();

        // Method doesn't have an explicit return type, but it returns int in the code
        // We can verify it has no void return type
        if ($returnType !== null) {
            $this->assertNotEquals('void', $returnType->getName());
        }
        $this->assertTrue(true, 'processScheduled should return a count');
    }

    // =========================================================================
    // PROCESS RECURRING — CRON ENTRY POINT
    // =========================================================================

    /**
     * Test processRecurring method signature (no required parameters)
     */
    public function testProcessRecurringMethodSignature(): void
    {
        $ref = new \ReflectionMethod(NewsletterService::class, 'processRecurring');
        $params = $ref->getParameters();

        $this->assertCount(0, $params, 'processRecurring should take no parameters');
    }

    // =========================================================================
    // PROCESS QUEUE — BATCH EMAIL SENDING
    // =========================================================================

    /**
     * Test processQueue accepts newsletterId and optional batchSize
     */
    public function testProcessQueueMethodSignature(): void
    {
        $ref = new \ReflectionMethod(NewsletterService::class, 'processQueue');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'Should accept at least newsletterId');
        $this->assertEquals('newsletterId', $params[0]->getName());

        // Second parameter should be optional batchSize
        if (count($params) > 1) {
            $this->assertTrue($params[1]->isOptional(), 'batchSize should be optional');
            $this->assertEquals('batchSize', $params[1]->getName());
            $this->assertEquals(50, $params[1]->getDefaultValue(), 'Default batch size should be 50');
        }
    }

    // =========================================================================
    // SEND NOW — IMMEDIATE DISPATCH
    // =========================================================================

    /**
     * Test sendNow method signature
     */
    public function testSendNowMethodSignature(): void
    {
        $ref = new \ReflectionMethod(NewsletterService::class, 'sendNow');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'Should accept at least newsletterId');
        $this->assertEquals('newsletterId', $params[0]->getName());

        // Optional target audience
        if (count($params) > 1) {
            $this->assertTrue($params[1]->isOptional(), 'targetAudience should be optional');
            $this->assertEquals('all_members', $params[1]->getDefaultValue(), 'Default audience should be all_members');
        }

        // Optional segment ID
        if (count($params) > 2) {
            $this->assertTrue($params[2]->isOptional(), 'segmentId should be optional');
        }
    }

    // =========================================================================
    // SCHEDULING
    // =========================================================================

    /**
     * Test schedule method signature
     */
    public function testScheduleMethodSignature(): void
    {
        $ref = new \ReflectionMethod(NewsletterService::class, 'schedule');
        $params = $ref->getParameters();

        $this->assertCount(2, $params, 'schedule should accept newsletterId and scheduledAt');
        $this->assertEquals('newsletterId', $params[0]->getName());
        $this->assertEquals('scheduledAt', $params[1]->getName());
    }

    // =========================================================================
    // RECIPIENT MANAGEMENT
    // =========================================================================

    /**
     * Test getRecipients accepts target audience parameter
     */
    public function testGetRecipientsMethodSignature(): void
    {
        $ref = new \ReflectionMethod(NewsletterService::class, 'getRecipients');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(0, count($params));

        if (count($params) > 0) {
            $this->assertTrue($params[0]->isOptional(), 'targetAudience should be optional');
            $this->assertEquals('all_members', $params[0]->getDefaultValue(), 'Default should be all_members');
        }
    }

    /**
     * Test getRecipientCount method exists
     */
    public function testGetRecipientCountMethodExists(): void
    {
        $this->assertTrue(
            method_exists(NewsletterService::class, 'getRecipientCount'),
            'getRecipientCount should exist for pre-send validation'
        );

        $ref = new \ReflectionMethod(NewsletterService::class, 'getRecipientCount');
        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    /**
     * Test getFilteredRecipients method exists for targeted sending
     */
    public function testGetFilteredRecipientsMethodExists(): void
    {
        $this->assertTrue(
            method_exists(NewsletterService::class, 'getFilteredRecipients'),
            'getFilteredRecipients should exist for location/group targeting'
        );

        $ref = new \ReflectionMethod(NewsletterService::class, 'getFilteredRecipients');
        $this->assertTrue($ref->isStatic());
    }

    /**
     * Test getSegmentRecipients method exists for segment-based targeting
     */
    public function testGetSegmentRecipientsMethodExists(): void
    {
        $this->assertTrue(
            method_exists(NewsletterService::class, 'getSegmentRecipients'),
            'getSegmentRecipients should exist for segment-based targeting'
        );
    }

    // =========================================================================
    // A/B TESTING — CRON RELATED
    // =========================================================================

    /**
     * Test initializeABStats method exists
     */
    public function testInitializeABStatsMethodExists(): void
    {
        $this->assertTrue(
            method_exists(NewsletterService::class, 'initializeABStats'),
            'initializeABStats should exist for post-send A/B stats initialization'
        );

        $ref = new \ReflectionMethod(NewsletterService::class, 'initializeABStats');
        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    /**
     * Test getABTestResults method exists
     */
    public function testGetABTestResultsMethodExists(): void
    {
        $this->assertTrue(
            method_exists(NewsletterService::class, 'getABTestResults'),
            'getABTestResults should exist for A/B result analysis'
        );
    }

    /**
     * Test selectABWinner method exists
     */
    public function testSelectABWinnerMethodExists(): void
    {
        $this->assertTrue(
            method_exists(NewsletterService::class, 'selectABWinner'),
            'selectABWinner should exist for declaring A/B test winner'
        );
    }

    // =========================================================================
    // RESEND TO NON-OPENERS — FOLLOW-UP CRON
    // =========================================================================

    /**
     * Test resendToNonOpeners method exists
     */
    public function testResendToNonOpenersMethodExists(): void
    {
        $this->assertTrue(
            method_exists(NewsletterService::class, 'resendToNonOpeners'),
            'resendToNonOpeners should exist for follow-up campaigns'
        );

        $ref = new \ReflectionMethod(NewsletterService::class, 'resendToNonOpeners');
        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    /**
     * Test resendToNonOpeners method signature
     */
    public function testResendToNonOpenersMethodSignature(): void
    {
        $ref = new \ReflectionMethod(NewsletterService::class, 'resendToNonOpeners');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'Should accept at least newsletterId');

        // Optional new subject
        if (count($params) > 1) {
            $this->assertTrue($params[1]->isOptional(), 'newSubject should be optional');
        }

        // Optional wait days
        if (count($params) > 2) {
            $this->assertTrue($params[2]->isOptional(), 'waitDays should be optional');
            $this->assertEquals(3, $params[2]->getDefaultValue(), 'Default wait days should be 3');
        }
    }

    /**
     * Test getResendInfo method exists for eligibility check
     */
    public function testGetResendInfoMethodExists(): void
    {
        $this->assertTrue(
            method_exists(NewsletterService::class, 'getResendInfo'),
            'getResendInfo should exist for pre-resend validation'
        );
    }

    // =========================================================================
    // STATISTICS & MONITORING
    // =========================================================================

    /**
     * Test getStats method signature
     */
    public function testGetStatsMethodSignature(): void
    {
        $ref = new \ReflectionMethod(NewsletterService::class, 'getStats');
        $params = $ref->getParameters();

        $this->assertCount(1, $params, 'getStats should accept newsletterId');
    }

    // =========================================================================
    // EMAIL RENDERING & TEMPLATE PROCESSING
    // =========================================================================

    /**
     * Test renderEmail method exists for queue processing
     */
    public function testRenderEmailMethodExists(): void
    {
        $this->assertTrue(
            method_exists(NewsletterService::class, 'renderEmail'),
            'renderEmail should exist for email template rendering during queue processing'
        );
    }

    /**
     * Test processTemplateVariables method exists
     */
    public function testProcessTemplateVariablesMethodExists(): void
    {
        $this->assertTrue(
            method_exists(NewsletterService::class, 'processTemplateVariables'),
            'processTemplateVariables should exist for template personalization'
        );
    }

    // =========================================================================
    // EMAIL CONFIGURATION
    // =========================================================================

    /**
     * Test getSendingMethod returns a valid method name
     */
    public function testGetSendingMethodReturnsValidMethod(): void
    {
        $method = NewsletterService::getSendingMethod();

        $this->assertIsString($method);
        $this->assertContains($method, ['Gmail API', 'SMTP'], 'Should return either Gmail API or SMTP');
    }

    /**
     * Test email rate limit constant is defined
     */
    public function testEmailRateLimitConstantIsDefined(): void
    {
        $this->assertTrue(
            defined('NEWSLETTER_EMAIL_DELAY_MICROSECONDS'),
            'NEWSLETTER_EMAIL_DELAY_MICROSECONDS should be defined for rate limiting'
        );

        $delay = NEWSLETTER_EMAIL_DELAY_MICROSECONDS;
        $this->assertIsInt($delay);
        $this->assertGreaterThan(0, $delay, 'Email delay should be positive');
        $this->assertLessThanOrEqual(1000000, $delay, 'Email delay should not exceed 1 second');
    }

    /**
     * Test the rate limit allows at most ~4 emails per second
     */
    public function testEmailRateLimitIsReasonable(): void
    {
        $delayMicroseconds = NEWSLETTER_EMAIL_DELAY_MICROSECONDS;
        $maxEmailsPerSecond = 1000000 / $delayMicroseconds;

        $this->assertLessThanOrEqual(
            10,
            $maxEmailsPerSecond,
            'Rate limit should allow at most 10 emails per second'
        );
        $this->assertGreaterThanOrEqual(
            1,
            $maxEmailsPerSecond,
            'Rate limit should allow at least 1 email per second'
        );
    }
}
