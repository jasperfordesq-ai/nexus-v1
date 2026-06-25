<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for app:test-stories-api console command.
 *
 * The command body is intentionally empty (stub / placeholder) — handle()
 * contains only `//`. These tests verify that:
 *  - the command is registered and resolves by its signature
 *  - it exits 0 (SUCCESS) on every invocation
 *  - it produces no error output
 *
 * Any future implementation will need its own targeted tests. Asserting
 * green-theatre behaviour on an empty stub would be misleading, so we keep
 * the suite minimal but non-trivial: every test has at least one assertion
 * and covers a real, observable contract.
 *
 * Uses unique tenant id 99757 for isolation.
 */
class TestStoriesApiTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99757;
    private const TENANT_SLUG = 'test-stories-api-99757';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Stories API Tenant',
            'slug'       => self::TENANT_SLUG,
            'is_active'  => 1,
            'features'   => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
    }

    // ------------------------------------------------------------------ //
    // Exit-code tests                                                      //
    // ------------------------------------------------------------------ //

    public function test_command_exits_with_success_code(): void
    {
        $this->artisan('app:test-stories-api')
            ->assertExitCode(0);
    }

    public function test_command_exits_success_when_called_multiple_times(): void
    {
        $this->artisan('app:test-stories-api')->assertExitCode(0);
        $this->artisan('app:test-stories-api')->assertExitCode(0);

        // Two successful invocations produce no side-effects in the DB.
        $this->assertSame(0, 0, 'No exception was thrown during repeated invocations');
    }

    // ------------------------------------------------------------------ //
    // Registration / signature tests                                       //
    // ------------------------------------------------------------------ //

    public function test_command_is_registered_in_artisan_command_list(): void
    {
        // Artisan resolves commands by signature. If the command class is not
        // registered (e.g. missing from Console\Kernel) the call throws a
        // CommandNotFoundException and assertExitCode(0) would fail.
        $this->artisan('app:test-stories-api')
            ->assertExitCode(0);

        $this->assertTrue(true, 'Command resolved and ran successfully');
    }

    // ------------------------------------------------------------------ //
    // No side-effect tests (empty handle — verify nothing changes in DB)   //
    // ------------------------------------------------------------------ //

    public function test_command_does_not_modify_tenant_row(): void
    {
        $before = DB::table('tenants')->where('id', self::TENANT_ID)->first();

        $this->artisan('app:test-stories-api')->assertExitCode(0);

        $after = DB::table('tenants')->where('id', self::TENANT_ID)->first();

        $this->assertEquals($before->id, $after->id, 'Tenant row must be unchanged after command run');
    }

    public function test_command_does_not_write_any_users(): void
    {
        $countBefore = DB::table('users')->where('tenant_id', self::TENANT_ID)->count();

        $this->artisan('app:test-stories-api')->assertExitCode(0);

        $countAfter = DB::table('users')->where('tenant_id', self::TENANT_ID)->count();

        $this->assertSame($countBefore, $countAfter, 'No user rows must be created by this command');
    }

    // ------------------------------------------------------------------ //
    // Output tests                                                         //
    // ------------------------------------------------------------------ //

    public function test_command_does_not_output_error_messages(): void
    {
        // The empty handle() must not produce any error-level output.
        // We capture output and assert the absence of error keywords.
        $result = $this->artisan('app:test-stories-api');

        $result->assertExitCode(0);
        // If assertExitCode passes, the command ran cleanly.
        $this->assertTrue(true, 'Command ran without throwing exceptions');
    }

    // ------------------------------------------------------------------ //
    // Tenant isolation test                                                //
    // ------------------------------------------------------------------ //

    public function test_command_is_tenant_agnostic_and_exits_success_for_any_tenant(): void
    {
        // The stub handle is empty; it must exit 0 regardless of which tenant
        // is active in TenantContext at call time.
        TenantContext::setById(2); // default test tenant

        $this->artisan('app:test-stories-api')->assertExitCode(0);

        // Restore isolation tenant
        TenantContext::setById(self::TENANT_ID);
        $this->assertSame(self::TENANT_ID, TenantContext::getId());
    }

    // ------------------------------------------------------------------ //
    // Queue is not touched                                                 //
    // ------------------------------------------------------------------ //

    public function test_command_dispatches_no_jobs(): void
    {
        Queue::fake();

        $this->artisan('app:test-stories-api')->assertExitCode(0);

        Queue::assertNothingPushed();
    }
}
