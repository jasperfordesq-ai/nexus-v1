<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Controllers;

use Tests\Laravel\TestCase;
use App\Http\Controllers\Api\AdminAuditLogController;

class AdminAuditLogControllerTest extends TestCase
{
    /** @return array{0: string, 1: array<int, mixed>} */
    private function buildWhere(string $alias, int $tenantId, array $filters): array
    {
        $controller = app(AdminAuditLogController::class);
        $method = new \ReflectionMethod($controller, 'buildWhere');

        return $method->invoke($controller, $alias, $tenantId, $filters);
    }

    public function test_buildWhere_always_scopes_by_tenant(): void
    {
        [$where, $params] = $this->buildWhere('al', 7, []);

        $this->assertSame('al.tenant_id = ?', $where);
        $this->assertSame([7], $params);
    }

    public function test_buildWhere_applies_all_filters_parameterised(): void
    {
        [$where, $params] = $this->buildWhere('al', 7, [
            'action' => 'login',
            'user_id' => '42',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $this->assertSame(
            'al.tenant_id = ? AND al.action = ? AND al.user_id = ? AND al.created_at >= ? AND al.created_at <= ?',
            $where
        );
        $this->assertSame([7, 'login', 42, '2026-01-01 00:00:00', '2026-01-31 23:59:59'], $params);
    }

    public function test_buildWhere_rejects_malformed_dates_and_non_numeric_user(): void
    {
        [$where, $params] = $this->buildWhere('al', 7, [
            'user_id' => 'abc; DROP TABLE users',
            'date_from' => "2026-01-01' OR '1'='1",
            'date_to' => 'not-a-date',
        ]);

        $this->assertSame('al.tenant_id = ?', $where);
        $this->assertSame([7], $params);
    }
}
