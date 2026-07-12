<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

/** Fail-closed guard for MariaDB's non-transactional recurrence revision DDL. */
final class EventRecurrenceRevisionSchemaGuard
{
    public static function assertFresh(
        bool $revisionTableExists,
        bool $ledgerTableExists,
        bool $ruleVersionExists,
        bool $setVersionExists,
    ): void {
        if ($revisionTableExists
            || $ledgerTableExists
            || $ruleVersionExists
            || $setVersionExists) {
            throw new \LogicException('event_recurrence_revision_partial_schema_exists');
        }
    }
}
