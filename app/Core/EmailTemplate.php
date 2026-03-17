<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Core\EmailTemplate as LegacyEmailTemplate;

/**
 * App-namespace wrapper for Nexus\Core\EmailTemplate.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Blade/Mailable templates.
 */
class EmailTemplate
{
    /**
     * Render a themed HTML email.
     *
     * @param string      $title      Main heading
     * @param string      $subtitle   Subheading
     * @param string      $body       Main content (supports HTML)
     * @param string|null $btnText    CTA button text (optional)
     * @param string|null $btnUrl     CTA button URL (optional)
     * @param string      $tenantName Name of the Timebank
     * @return string Valid HTML
     */
    public static function render(
        $title,
        $subtitle,
        $body,
        $btnText = null,
        $btnUrl = null,
        $tenantName = 'Project NEXUS'
    ): string {
        return LegacyEmailTemplate::render($title, $subtitle, $body, $btnText, $btnUrl, $tenantName);
    }
}
