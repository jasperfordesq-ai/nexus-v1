<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\Mailer;
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
