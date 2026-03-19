<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * EmailTemplateService — Laravel DI wrapper for legacy \Nexus\Services\EmailTemplateService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class EmailTemplateService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy EmailTemplateService::wrap().
     */
    public function wrap(string $body, string $tenantName = ''): string
    {
        return \Nexus\Services\EmailTemplateService::wrap($body, $tenantName);
    }
}
