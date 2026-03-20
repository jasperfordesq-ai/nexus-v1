<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
    public static function getTemplates(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy NewsletterTemplates::getTemplate().
     */
    public static function getTemplate(string $templateId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy NewsletterTemplates::processTemplate().
     */
    public static function processTemplate(array $template, string $tenantName): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
