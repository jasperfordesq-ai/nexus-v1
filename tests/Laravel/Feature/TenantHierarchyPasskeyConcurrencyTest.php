<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature;

use App\Core\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantHierarchyService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Laravel\TestCase;
use Throwable;

/** Real independent connections cover the registration-versus-move lock boundary. */
final class TenantHierarchyPasskeyConcurrencyTest extends TestCase
{
    /** @var array{int,int,int,int}|array{} */
    private array $fixtureIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            self::markTestSkipped('pcntl is required for passkey hierarchy concurrency tests.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->fixtureIds !== []) {
            try {
                DB::purge();
                DB::reconnect();
                $this->cleanupFixture(...$this->fixtureIds);
            } catch (Throwable) {
                // Preserve the original test failure; the test database is
                // disposable and the random fixture remains isolated.
            }
        }

        parent::tearDown();
    }

    public function test_registration_winning_the_lock_forces_move_to_recheck_rp_impact(): void
    {
        [$oldParentId, $newParentId, $movedTenantId, $userId] = $this->fixture();
        DB::disconnect();

        $registrationSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($registrationSockets === false) {
            throw new RuntimeException('passkey_registration_concurrency_socket_failed');
        }
        $registrationPid = pcntl_fork();
        if ($registrationPid === -1) {
            throw new RuntimeException('passkey_registration_concurrency_fork_failed');
        }
        if ($registrationPid === 0) {
            fclose($registrationSockets[0]);
            try {
                DB::purge();
                DB::reconnect();
                DB::transaction(function () use (
                    $registrationSockets,
                    $movedTenantId,
                    $userId,
                    $oldParentId
                ): void {
                    DB::table('tenants')->where('id', $movedTenantId)->lockForUpdate()->first();
                    DB::table('users')->where('id', $userId)->lockForUpdate()->first();
                    fwrite($registrationSockets[1], "locked\n");
                    fflush($registrationSockets[1]);
                    fgets($registrationSockets[1]);

                    $oldRpId = (string) DB::table('tenants')
                        ->where('id', $oldParentId)
                        ->value('domain');
                    DB::table('webauthn_credentials')->insert([
                        'user_id' => $userId,
                        'tenant_id' => $movedTenantId,
                        'credential_id' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
                        'public_key' => 'concurrency-test-public-key',
                        'sign_count' => 0,
                        'transports' => json_encode(['internal'], JSON_THROW_ON_ERROR),
                        'device_name' => 'Concurrency passkey',
                        'authenticator_type' => 'platform',
                        'attestation_type' => 'none',
                        'rp_id' => $oldRpId,
                        'registration_origin' => 'https://' . $oldRpId,
                        'user_handle' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
                        'backup_eligible' => 0,
                        'backup_state' => 0,
                        'user_verified' => 1,
                        'credential_discoverable' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
                fwrite($registrationSockets[1], 'committed');
                fclose($registrationSockets[1]);
                exit(0);
            } catch (Throwable $exception) {
                fwrite($registrationSockets[1], 'error:' . $exception->getMessage());
                fclose($registrationSockets[1]);
                exit(1);
            }
        }

        fclose($registrationSockets[1]);
        stream_set_timeout($registrationSockets[0], 10);
        self::assertSame("locked\n", fgets($registrationSockets[0]));

        $moveSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($moveSockets === false) {
            throw new RuntimeException('passkey_move_concurrency_socket_failed');
        }
        $movePid = pcntl_fork();
        if ($movePid === -1) {
            throw new RuntimeException('passkey_move_concurrency_fork_failed');
        }
        if ($movePid === 0) {
            fclose($moveSockets[0]);
            fclose($registrationSockets[0]);
            try {
                DB::purge();
                DB::reconnect();
                fwrite($moveSockets[1], "started\n");
                fflush($moveSockets[1]);
                $result = TenantHierarchyService::moveTenant($movedTenantId, $newParentId);
                fwrite($moveSockets[1], json_encode($result, JSON_THROW_ON_ERROR));
                fclose($moveSockets[1]);
                exit(0);
            } catch (Throwable $exception) {
                fwrite($moveSockets[1], 'error:' . $exception->getMessage());
                fclose($moveSockets[1]);
                exit(1);
            }
        }

        fclose($moveSockets[1]);
        stream_set_timeout($moveSockets[0], 10);
        self::assertSame("started\n", fgets($moveSockets[0]));
        usleep(200_000);
        fwrite($registrationSockets[0], "release\n");
        fflush($registrationSockets[0]);

        $registrationMessage = stream_get_contents($registrationSockets[0]);
        fclose($registrationSockets[0]);
        pcntl_waitpid($registrationPid, $registrationStatus);
        $moveMessage = stream_get_contents($moveSockets[0]);
        fclose($moveSockets[0]);
        pcntl_waitpid($movePid, $moveStatus);

        DB::purge();
        DB::reconnect();

        self::assertTrue(pcntl_wifexited($registrationStatus));
        self::assertSame(0, pcntl_wexitstatus($registrationStatus), $registrationMessage);
        self::assertSame('committed', $registrationMessage);
        self::assertTrue(pcntl_wifexited($moveStatus));
        self::assertSame(0, pcntl_wexitstatus($moveStatus), $moveMessage);
        $moveResult = json_decode($moveMessage, true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($moveResult['success']);
        self::assertSame('PASSKEY_RP_CHANGE_BLOCKED', $moveResult['code']);
        self::assertSame(1, $moveResult['security_impact']['credential_count']);
        self::assertSame(
            $oldParentId,
            (int) DB::table('tenants')->where('id', $movedTenantId)->value('parent_id')
        );

        $this->cleanupFixture($oldParentId, $newParentId, $movedTenantId, $userId);
    }

    /** @return array{int,int,int,int} */
    private function fixture(): array
    {
        $suffix = bin2hex(random_bytes(6));
        $oldParent = Tenant::factory()->create([
            'domain' => "old-{$suffix}.example.test",
            'parent_id' => null,
            'path' => "/old-{$suffix}/",
            'depth' => 0,
            'allows_subtenants' => 1,
        ]);
        $newParent = Tenant::factory()->create([
            'domain' => "new-{$suffix}.example.test",
            'parent_id' => null,
            'path' => "/new-{$suffix}/",
            'depth' => 0,
            'allows_subtenants' => 1,
        ]);
        $moved = Tenant::factory()->create([
            'domain' => null,
            'accessible_domain' => null,
            'parent_id' => $oldParent->id,
            'path' => "/old-{$suffix}/moved/",
            'depth' => 1,
        ]);
        DB::table('tenants')->where('id', $moved->id)->update([
            'path' => "/old-{$suffix}/{$moved->id}/",
        ]);
        $user = User::factory()->forTenant((int) $moved->id)->create();

        $this->fixtureIds = [
            (int) $oldParent->id,
            (int) $newParent->id,
            (int) $moved->id,
            (int) $user->id,
        ];

        return $this->fixtureIds;
    }

    private function cleanupFixture(
        int $oldParentId,
        int $newParentId,
        int $movedTenantId,
        int $userId
    ): void {
        DB::table('webauthn_credentials')->where('user_id', $userId)->delete();
        DB::table('users')->where('id', $userId)->delete();
        DB::table('tenants')->where('id', $movedTenantId)->delete();
        DB::table('tenants')->where('id', $newParentId)->delete();
        DB::table('tenants')->where('id', $oldParentId)->delete();
        $this->fixtureIds = [];
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }
}
