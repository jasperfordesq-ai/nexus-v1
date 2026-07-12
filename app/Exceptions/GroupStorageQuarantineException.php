<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Raised when an asset cannot be moved into or out of deletion quarantine. */
final class GroupStorageQuarantineException extends RuntimeException
{
}
