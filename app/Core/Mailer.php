<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Core\Mailer as LegacyMailer;

/**
 * App-namespace wrapper for Nexus\Core\Mailer.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's Mail facade internally.
 */
class Mailer
{
    private LegacyMailer $legacy;

    /**
     * @param int|null $tenantId When provided, loads per-tenant email config.
     *                           When null, uses platform-wide .env config.
     */
    public function __construct(?int $tenantId = null)
    {
        $this->legacy = new LegacyMailer($tenantId);
    }

    /**
     * Factory: create a Mailer configured for the current tenant context.
     */
    public static function forCurrentTenant(): self
    {
        $instance = new self();
        $instance->legacy = LegacyMailer::forCurrentTenant();
        return $instance;
    }

    /**
     * Send an email.
     *
     * @param string      $to      Recipient email address
     * @param string      $subject Email subject
     * @param string      $body    HTML body
     * @param string|null $cc      CC recipient (optional)
     * @param string|null $replyTo Reply-To address (optional)
     * @return bool
     */
    public function send($to, $subject, $body, $cc = null, $replyTo = null): bool
    {
        return (bool) $this->legacy->send($to, $subject, $body, $cc, $replyTo);
    }

    /**
     * Test Gmail API connection.
     *
     * @return array{success: bool, message: string}
     */
    public static function testGmailConnection(): array
    {
        return LegacyMailer::testGmailConnection();
    }

    /**
     * Get current email provider type ('smtp', 'gmail_api', or 'sendgrid').
     */
    public function getProviderType(): string
    {
        return $this->legacy->getProviderType();
    }
}
