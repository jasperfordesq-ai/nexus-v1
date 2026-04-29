<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Verein\VereinFederationService;
use Illuminate\Console\Command;

/**
 * AG55 — Daily command to expire pending Verein cross-invitations
 * whose expires_at is in the past.
 */
class ExpireVereinFederationInvitations extends Command
{
    protected $signature = 'verein-federation:expire-invitations';
    protected $description = 'Expire pending Verein cross-invitations past their expires_at timestamp';

    public function handle(VereinFederationService $service): int
    {
        $expired = $service->expireOldInvitations();
        if ($expired > 0) {
            $this->info("Expired {$expired} Verein cross-invitation(s).");
        }
        return self::SUCCESS;
    }
}
