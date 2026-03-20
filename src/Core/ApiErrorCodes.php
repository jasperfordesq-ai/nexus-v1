<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — extends \App\Core\ApiErrorCodes which holds
 * the real constant definitions and helper methods.
 *
 * Because PHP constants are inherited, all public constants from
 * \App\Core\ApiErrorCodes are accessible via this class without
 * explicit re-declaration. Static methods are inherited too.
 *
 * This class is kept for backward compatibility: legacy Nexus\ namespace
 * code references it. The public API is identical.
 *
 * @see \App\Core\ApiErrorCodes  The authoritative implementation.
 * @deprecated Use \App\Core\ApiErrorCodes instead.
 */
class ApiErrorCodes extends \App\Core\ApiErrorCodes
{
    // All constants and methods inherited from \App\Core\ApiErrorCodes.
}
