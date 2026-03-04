<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Project NEXUS - Route Definitions
 * ---------------------------------
 * Routes are split into domain-specific files under routes/.
 * Each partial uses the $router instance from this scope.
 *
 * IMPORTANT: Order matters — literal routes must come before
 * wildcard {id} routes in the same path prefix.
 */

use Nexus\Core\Router;
use Nexus\Core\TenantContext;

$router = new Router();

// ============================================
// Route partials (loaded in dependency order)
// ============================================

require __DIR__ . '/routes/super-admin.php';
require __DIR__ . '/routes/federation-api-v1.php';
require __DIR__ . '/routes/legacy-api.php';
require __DIR__ . '/routes/tenant-bootstrap.php';
require __DIR__ . '/routes/listings.php';
require __DIR__ . '/routes/users.php';
require __DIR__ . '/routes/messages.php';
require __DIR__ . '/routes/exchanges.php';
require __DIR__ . '/routes/events.php';
require __DIR__ . '/routes/groups.php';
require __DIR__ . '/routes/social.php';
require __DIR__ . '/routes/content.php';
require __DIR__ . '/routes/admin-api.php';
require __DIR__ . '/routes/misc-api.php';

// DISPATCH
$router->dispatch();
