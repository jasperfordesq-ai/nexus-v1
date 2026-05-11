<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI\Tools;

use App\Core\TenantContext;

/**
 * Shared scaffolding for tools — argument coercion, tenant guard helpers,
 * standard result envelopes.
 */
abstract class AbstractTool implements ToolInterface
{
    public function isAvailable(int $userId): bool
    {
        return true;
    }

    /**
     * Convert this tool to the OpenAI function-calling schema.
     */
    public function toOpenAiFunction(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->parametersSchema(),
            ],
        ];
    }

    protected function tenantId(): int
    {
        $id = TenantContext::getId();
        if (!$id) {
            throw new \RuntimeException('Tool invoked without tenant context');
        }
        return (int) $id;
    }

    protected function ok(string $summary, array $results, string $cardType = 'generic'): array
    {
        return [
            'ok' => true,
            'summary' => $summary,
            'results' => $results,
            'card_type' => $cardType,
            'error' => null,
        ];
    }

    protected function err(string $message): array
    {
        return [
            'ok' => false,
            'summary' => $message,
            'results' => [],
            'card_type' => 'error',
            'error' => $message,
        ];
    }

    protected function stringArg(array $args, string $key, string $default = ''): string
    {
        $v = $args[$key] ?? $default;
        if (!is_string($v)) {
            $v = is_scalar($v) ? (string) $v : $default;
        }
        return trim($v);
    }

    protected function intArg(array $args, string $key, int $default = 0, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        $v = $args[$key] ?? $default;
        if (!is_numeric($v)) {
            return $default;
        }
        return (int) max($min, min($max, (int) $v));
    }

    protected function arrayArg(array $args, string $key): array
    {
        $v = $args[$key] ?? [];
        return is_array($v) ? array_values($v) : [];
    }
}
