<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Members Grid Renderer
 *
 * Legacy member-grid block retained as a fail-closed compatibility shim.
 */

namespace App\PageBuilder\Renderers;

class MembersGridRenderer implements BlockRendererInterface
{
    /**
     * Member-account data must not be rendered into public CMS output.
     */
    public function render(array $data): string
    {
        return '';
    }

    public function validate(array $data): bool
    {
        return false;
    }
}
