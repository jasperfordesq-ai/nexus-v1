<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Admin SEO Dispatcher
// Modern layout (default)
$modernPath = dirname(__DIR__, 2) . '/modern/admin-legacy/seo/index.php';
if (file_exists($modernPath)) {
    require $modernPath;
    return;
}

echo "View not found: Admin SEO";
