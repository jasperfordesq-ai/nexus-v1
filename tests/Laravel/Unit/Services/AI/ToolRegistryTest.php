<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI;

use App\Services\AI\Tools\AbstractTool;
use App\Services\AI\Tools\ToolRegistry;
use Tests\Laravel\TestCase;

class ToolRegistryTest extends TestCase
{
    public function test_unknown_tool_returns_err_envelope(): void
    {
        $registry = new ToolRegistry();
        $result = $registry->execute('this_tool_does_not_exist', [], 1);

        $this->assertFalse($result['ok']);
        $this->assertSame('unknown_tool', $result['error']);
        $this->assertSame([], $result['results']);
        $this->assertSame('error', $result['card_type']);
    }

    public function test_register_custom_tool_appears_in_schemas(): void
    {
        $registry = new ToolRegistry();
        $registry->register(new class extends AbstractTool {
            public function name(): string { return 'fixture_tool'; }
            public function description(): string { return 'Fixture'; }
            public function parametersSchema(): array {
                return ['type' => 'object', 'properties' => [], 'required' => []];
            }
            public function execute(array $arguments, int $userId): array {
                return $this->ok('done', [['id' => 1]], 'generic');
            }
        });

        $schemas = $registry->openAiSchemasFor(1);
        $names = array_map(fn ($s) => $s['function']['name'], $schemas);
        $this->assertContains('fixture_tool', $names);
    }

    public function test_execute_catches_thrown_exceptions(): void
    {
        $registry = new ToolRegistry();
        $registry->register(new class extends AbstractTool {
            public function name(): string { return 'boom_tool'; }
            public function description(): string { return 'Throws'; }
            public function parametersSchema(): array {
                return ['type' => 'object', 'properties' => [], 'required' => []];
            }
            public function execute(array $arguments, int $userId): array {
                throw new \RuntimeException('boom');
            }
        });

        $result = $registry->execute('boom_tool', [], 1);
        $this->assertFalse($result['ok']);
        $this->assertSame('execution_failed', $result['error']);
    }

    public function test_unavailable_tool_returns_not_available(): void
    {
        $registry = new ToolRegistry();
        $registry->register(new class extends AbstractTool {
            public function name(): string { return 'gated_tool'; }
            public function description(): string { return 'Gated'; }
            public function parametersSchema(): array {
                return ['type' => 'object', 'properties' => [], 'required' => []];
            }
            public function isAvailable(int $userId): bool { return false; }
            public function execute(array $arguments, int $userId): array {
                return $this->ok('done', [], 'generic');
            }
        });

        $result = $registry->execute('gated_tool', [], 1);
        $this->assertFalse($result['ok']);
        $this->assertSame('not_available', $result['error']);
    }
}
