<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventNotificationOutboxConsumer;
use App\Services\EventNotificationOutboxDiagnostics;
use App\Services\EventNotificationOutboxProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class ProcessEventNotificationOutbox extends Command
{
    protected $signature = 'events:process-notification-outbox
        {--tenant= : Process or inspect one tenant only}
        {--limit=50 : Maximum facts claimed in this run}
        {--status : Print a payload-free health snapshot}
        {--json : Emit machine-readable output}
        {--replay= : Explicitly replay one dead-letter outbox id}
        {--actor= : Operator identity required for replay}
        {--reason= : Replay reason required for audit}';

    protected $description = 'Process or inspect the authoritative Event notification outbox.';

    public function handle(
        EventNotificationOutboxProcessor $processor,
        EventNotificationOutboxDiagnostics $diagnostics,
        EventNotificationOutboxConsumer $consumer,
    ): int {
        if (! Schema::hasTable('event_domain_outbox')) {
            $this->warn('Event notification outbox schema is unavailable.');
            return self::SUCCESS;
        }

        $tenantId = $this->positiveOption('tenant', false);
        if ($tenantId === false) {
            return self::INVALID;
        }
        if ((bool) $this->option('status')) {
            return $this->output($diagnostics->snapshot($tenantId === null ? null : (int) $tenantId));
        }

        $replay = $this->positiveOption('replay', false);
        if ($replay === false) {
            return self::INVALID;
        }
        if ($replay !== null) {
            $actor = trim((string) $this->option('actor'));
            $reason = trim((string) $this->option('reason'));
            if ($actor === '' || $reason === '') {
                $this->error('Replay requires both --actor and --reason.');
                return self::INVALID;
            }
            if (! Schema::hasTable('event_notification_outbox_replays')) {
                $this->error('Event outbox replay audit schema is unavailable.');
                return self::FAILURE;
            }
            $ok = $consumer->replayDeadLetter((int) $replay, $actor, $reason, $tenantId === null ? null : (int) $tenantId);
            return $this->output(['replayed' => $ok, 'outbox_id' => (int) $replay], $ok ? self::SUCCESS : self::FAILURE);
        }

        $limit = $this->positiveOption('limit', true);
        if ($limit === false || $limit === null || $limit > 100) {
            $this->error('The --limit option must be an integer between 1 and 100.');
            return self::INVALID;
        }

        return $this->output($processor->processBatch((int) $limit, $tenantId === null ? null : (int) $tenantId));
    }

    private function positiveOption(string $name, bool $required): int|false|null
    {
        $value = $this->option($name);
        if (($value === null || $value === '') && ! $required) {
            return null;
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            $this->error("The --{$name} option must be a positive integer.");
            return false;
        }

        return (int) $validated;
    }

    /** @param array<string,mixed> $payload */
    private function output(array $payload, int $exit = self::SUCCESS): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($payload as $key => $value) {
                $this->line($key . '=' . (is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : var_export($value, true)));
            }
        }

        return $exit;
    }
}
