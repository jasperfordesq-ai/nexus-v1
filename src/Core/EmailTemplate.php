<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards every call to App\Core\EmailTemplate.
 *
 * @deprecated Use App\Core\EmailTemplate directly. Kept for backward compatibility.
 */
class EmailTemplate
{
    /**
     * Renders a themed HTML email.
     *
     * @param string $title      Main heading
     * @param string $subtitle   Subheading
     * @param string $body       Main content (supports HTML)
     * @param string $btnText    CTA button text (optional)
     * @param string $btnUrl     CTA button URL (optional)
     * @param string $tenantName Name of the Timebank
     * @return string Valid HTML
     */
    public static function render($title, $subtitle, $body, $btnText = null, $btnUrl = null, $tenantName = 'Project NEXUS')
    {
        return \App\Core\EmailTemplate::render($title, $subtitle, $body, $btnText, $btnUrl, $tenantName);
    }
}
