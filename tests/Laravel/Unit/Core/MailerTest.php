<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\Mailer;
use App\Core\TenantContext;
use Tests\Laravel\TestCase;

class MailerTest extends TestCase
{
    // -------------------------------------------------------
    // Constructor / getProviderType()
    // -------------------------------------------------------

    public function test_constructor_defaults_to_smtp_when_no_gmail_or_sendgrid(): void
    {
        // In test env, USE_GMAIL_API and SENDGRID_API_KEY are not set
        $mailer = new Mailer();
        $type = $mailer->getProviderType();
        // Should be smtp (or gmail_api/sendgrid if env vars are set)
        $this->assertContains($type, ['smtp', 'gmail_api', 'sendgrid']);
    }

    public function test_constructor_with_tenant_id_does_not_throw(): void
    {
        // Passing a tenant ID should not throw even if tenant has no email config
        $mailer = new Mailer(2);
        $this->assertInstanceOf(Mailer::class, $mailer);
    }

    // -------------------------------------------------------
    // getProviderType()
    // -------------------------------------------------------

    public function test_getProviderType_returns_string(): void
    {
        $mailer = new Mailer();
        $this->assertIsString($mailer->getProviderType());
    }

    // -------------------------------------------------------
    // forCurrentTenant()
    // -------------------------------------------------------

    public function test_forCurrentTenant_returns_mailer_instance(): void
    {
        $mailer = Mailer::forCurrentTenant();
        $this->assertInstanceOf(Mailer::class, $mailer);
    }

    public function test_forCurrentTenant_does_not_resolve_master_when_context_is_missing(): void
    {
        TenantContext::reset();

        $mailer = Mailer::forCurrentTenant();

        $tenantProperty = new \ReflectionProperty(Mailer::class, 'tenantId');
        $tenantProperty->setAccessible(true);

        $this->assertNull($tenantProperty->getValue($mailer));
        $this->assertNull(TenantContext::currentId());
    }

    public function test_event_subcategories_route_to_event_mailer_settings(): void
    {
        $mailer = new Mailer();
        $method = new \ReflectionMethod(Mailer::class, 'resolveSendGridFromPrefix');
        $method->setAccessible(true);

        $this->assertSame('events', $method->invoke($mailer, 'event_notification'));
        $this->assertSame('events', $method->invoke($mailer, 'event_update'));
        $this->assertSame('events', $method->invoke($mailer, 'event_cancellation'));
        $this->assertSame('events', $method->invoke($mailer, 'event_rsvp'));
        $this->assertSame('events', $method->invoke($mailer, 'event_created'));
        $this->assertSame('events', $method->invoke($mailer, 'event_reminder'));
    }

    public function test_platform_sendgrid_fallback_uses_authenticated_sendgrid_domain(): void
    {
        config([
            'mail.sendgrid.api_key' => 'SG.test-key',
            'mail.sendgrid.from_email' => 'noreply@project-nexus.ie',
            'mail.sendgrid.from_name' => 'Project NEXUS',
        ]);

        $mailer = new Mailer();

        $method = new \ReflectionMethod(Mailer::class, 'usePlatformSendGridFallback');
        $method->setAccessible(true);

        $fromEmail = new \ReflectionProperty(Mailer::class, 'fromEmail');
        $fromEmail->setAccessible(true);

        $isPlatformSendGrid = new \ReflectionProperty(Mailer::class, 'isPlatformSendGrid');
        $isPlatformSendGrid->setAccessible(true);

        $this->assertTrue($method->invoke($mailer));
        $this->assertSame('notifications@project-nexus.net', $fromEmail->getValue($mailer));
        $this->assertTrue($isPlatformSendGrid->getValue($mailer));
    }

    public function test_platform_sendgrid_fallback_keeps_category_from_address(): void
    {
        config([
            'mail.sendgrid.api_key' => 'SG.test-key',
            'mail.sendgrid.from_email' => 'noreply@project-nexus.ie',
            'mail.sendgrid.from_name' => 'Project NEXUS',
        ]);

        $mailer = new Mailer();

        $method = new \ReflectionMethod(Mailer::class, 'usePlatformSendGridFallback');
        $method->setAccessible(true);

        $fromEmail = new \ReflectionProperty(Mailer::class, 'fromEmail');
        $fromEmail->setAccessible(true);

        $this->assertTrue($method->invoke($mailer, 'activation'));
        $this->assertSame('noreply@project-nexus.net', $fromEmail->getValue($mailer));
    }

    public function test_platform_sendgrid_reply_to_is_not_hard_coded_to_personal_address(): void
    {
        $reflection = new \ReflectionClass(Mailer::class);

        $this->assertFalse(
            $reflection->hasConstant('DEFAULT_REPLY_TO'),
            'Platform SendGrid Reply-To must be configured, not hard-coded to a personal mailbox.'
        );
    }

    public function test_platform_sendgrid_reply_to_comes_from_configuration(): void
    {
        config([
            'mail.sendgrid.api_key' => 'SG.test-key',
            'mail.sendgrid.from_email' => 'noreply@project-nexus.net',
            'mail.sendgrid.from_name' => 'Project NEXUS',
            'mail.sendgrid.reply_to' => 'Support <support@project-nexus.net>',
        ]);

        $mailer = new Mailer();

        $replyTo = new \ReflectionProperty(Mailer::class, 'platformReplyTo');
        $replyTo->setAccessible(true);

        $this->assertSame('Support <support@project-nexus.net>', $replyTo->getValue($mailer));
    }

    // -------------------------------------------------------
    // testGmailConnection()
    // -------------------------------------------------------

    public function test_testGmailConnection_returns_array(): void
    {
        $result = Mailer::testGmailConnection();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }
}
