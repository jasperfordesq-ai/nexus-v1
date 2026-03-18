<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Project NEXUS - Legacy Route Definitions (DEPRECATED)
 * -----------------------------------------------------
 * All API routes have been migrated to Laravel (routes/api.php).
 * The Laravel bridge in index.php handles routing first.
 * This file exists only as a fallback for any routes not yet
 * matched by Laravel — it should match nothing in normal operation.
 *
 * @deprecated All routes migrated to routes/api.php
 */

use Nexus\Core\Router;

$router = new Router();

// All route partials have been removed — routes are in routes/api.php
// Legacy route files (httpdocs/routes/*.php) deleted as of 2026-03-18

// DISPATCH (catches any unmatched request — should be a no-op)
$router->dispatch();
