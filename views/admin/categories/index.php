<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Layout Switcher for Admin Categories
// FIXED: Use consistent session variable order (active_layout first)
$layout = layout(); // Fixed: centralized detection


if ($layout === 'modern' || $layout === 'high-contrast') {
    $view = __DIR__ . '/../../modern/admin-legacy/categories/index.php';
    if (file_exists($view)) {
        require $view;
        return;
    }
}

// Fallback or Error
echo "Error: Modern Categories View not found.";
