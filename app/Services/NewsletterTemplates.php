<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * NewsletterTemplates — Laravel DI wrapper for legacy \Nexus\Services\NewsletterTemplates.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class NewsletterTemplates
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy NewsletterTemplates::getTemplates().
     */
    public function getTemplates(): array
    {
        return \Nexus\Services\NewsletterTemplates::getTemplates();
    }

    /**
     * Delegates to legacy NewsletterTemplates::getTemplate().
     */
    public function getTemplate(string $templateId): array
    {
        return \Nexus\Services\NewsletterTemplates::getTemplate($templateId);
    }

    /**
     * Delegates to legacy NewsletterTemplates::processTemplate().
     */
    public function processTemplate(array $template, string $tenantName): array
    {
        return \Nexus\Services\NewsletterTemplates::processTemplate($template, $tenantName);
    }
}
