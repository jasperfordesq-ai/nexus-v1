<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventHealthService;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class HealthEvents extends Command
{
    protected $signature = 'events:health
        {--tenant= : Restrict the health snapshot to one tenant id}
        {--max-overdue=600 : Maximum deliverable backlog age in seconds (60-86400)}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Run payload-free Events integrity, delivery, reminder and waitlist health gates';

    public function handle(EventHealthService $health): int
    {
        $tenantId = $this->positiveIntegerOption('tenant', false);
        if ($tenantId === false) {
            return self::INVALID;
        }
        $maxOverdue = $this->positiveIntegerOption('max-overdue', true);
        if ($maxOverdue === false || $maxOverdue === null || $maxOverdue < 60 || $maxOverdue > 86_400) {
            $this->error('The --max-overdue option must be an integer between 60 and 86400.');
            return self::INVALID;
        }

        try {
            $snapshot = $health->snapshot($tenantId === null ? null : (int) $tenantId, $maxOverdue);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::INVALID;
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('healthy=' . var_export($snapshot['healthy'], true));
            $this->line('tenant_id=' . var_export($snapshot['tenant_id'], true));
            $this->line('integrity=' . json_encode($snapshot['integrity'], JSON_THROW_ON_ERROR));
            $this->line('notifications=' . json_encode($snapshot['notifications'], JSON_THROW_ON_ERROR));
            $this->line('domain_outbox=' . json_encode($snapshot['domain_outbox'], JSON_THROW_ON_ERROR));
            $this->line('reminders=' . json_encode($snapshot['reminders'], JSON_THROW_ON_ERROR));
            $this->line('waitlist=' . json_encode($snapshot['waitlist'], JSON_THROW_ON_ERROR));
            $this->line('recurrence=' . json_encode($snapshot['recurrence'], JSON_THROW_ON_ERROR));
            $this->line('schema=' . json_encode($snapshot['schema'], JSON_THROW_ON_ERROR));
        }

        return $snapshot['healthy'] ? self::SUCCESS : self::FAILURE;
    }

    private function positiveIntegerOption(string $name, bool $required): int|false|null
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
}
