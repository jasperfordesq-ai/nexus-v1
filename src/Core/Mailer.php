<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards every call to App\Core\Mailer.
 *
 * @deprecated Use App\Core\Mailer directly. Kept for backward compatibility.
 */
class Mailer
{
    /** @var \App\Core\Mailer */
    private $delegate;

    /**
     * @param int|null $tenantId When provided, loads per-tenant email config.
     *                           When null, uses platform-wide .env config.
     */
    public function __construct(?int $tenantId = null)
    {
        $this->delegate = new \App\Core\Mailer($tenantId);
    }

    /**
     * Factory: create a Mailer configured for the current tenant context.
     */
    public static function forCurrentTenant(): self
    {
        return new self(TenantContext::getId());
    }

    /**
     * Send an email.
     */
    public function send($to, $subject, $body, $cc = null, $replyTo = null)
    {
        return $this->delegate->send($to, $subject, $body, $cc, $replyTo);
    }

    /**
     * Test Gmail API connection.
     *
     * @return array{success: bool, message: string}
     */
    public static function testGmailConnection()
    {
        return \App\Core\Mailer::testGmailConnection();
    }

    /**
     * Get current email provider type.
     */
    public function getProviderType(): string
    {
        return $this->delegate->getProviderType();
    }
}
