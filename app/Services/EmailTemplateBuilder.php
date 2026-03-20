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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy EmailTemplateBuilder::processDynamicBlocks().
     */
    public function processDynamicBlocks(string $content, array $options = []): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy EmailTemplateBuilder::getAvailableBlocks().
     */
    public function getAvailableBlocks(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
