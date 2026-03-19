<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * EmailTemplateBuilder — Laravel DI wrapper for legacy \Nexus\Services\EmailTemplateBuilder.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class EmailTemplateBuilder
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy EmailTemplateBuilder::personalizeContent().
     */
    public function personalizeContent(string $content, array $recipient): string
    {
        return \Nexus\Services\EmailTemplateBuilder::personalizeContent($content, $recipient);
    }

    /**
     * Delegates to legacy EmailTemplateBuilder::processDynamicBlocks().
     */
    public function processDynamicBlocks(string $content, array $options = []): string
    {
        return \Nexus\Services\EmailTemplateBuilder::processDynamicBlocks($content, $options);
    }

    /**
     * Delegates to legacy EmailTemplateBuilder::getAvailableBlocks().
     */
    public function getAvailableBlocks(): array
    {
        return \Nexus\Services\EmailTemplateBuilder::getAvailableBlocks();
    }
}
