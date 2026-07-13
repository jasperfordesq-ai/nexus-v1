<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Integration;

use App\Core\ApiErrorCodes;
use App\Core\TenantContext;
use App\Services\EmailDispatchService;
use App\Services\TotpService;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Laravel\TestCase;
use Throwable;

/** Real independent MySQL connections prove credential mutations are single-use. */
final class PasswordMutationConcurrencyTest extends TestCase
{
    /** @var list<array{id: int, email: string}> */
    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('MySQL/MariaDB is required for password lock tests.');
        }

        foreach (['pcntl_fork', 'pcntl_waitpid', 'stream_socket_pair', 'posix_kill'] as $function) {
            if (!function_exists($function)) {
                self::markTestSkipped("{$function} is required for password lock tests.");
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            DB::purge();
            DB::reconnect();

            foreach ($this->fixtures as $fixture) {
                DB::table('password_resets')->where('email', $fixture['email'])->delete();
                DB::table('totp_verification_attempts')->where('user_id', $fixture['id'])->delete();
                DB::table('user_backup_codes')->where('user_id', $fixture['id'])->delete();
                DB::table('user_password_history')->where('user_id', $fixture['id'])->delete();
                DB::table('refresh_token_sessions')->where('user_id', $fixture['id'])->delete();
                DB::table('revoked_tokens')->where('user_id', $fixture['id'])->delete();
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', \App\Models\User::class)
                    ->where('tokenable_id', $fixture['id'])
                    ->delete();
                DB::table('user_trusted_devices')->where('user_id', $fixture['id'])->delete();
                DB::table('notifications')->where('user_id', $fixture['id'])->delete();
                DB::table('activity_log')->where('user_id', $fixture['id'])->delete();
                DB::table('push_log')->where('user_id', $fixture['id'])->delete();
                DB::table('email_log')->where('recipient_email', $fixture['email'])->delete();
                DB::table('users')->where('id', $fixture['id'])->delete();
            }

            TenantContext::reset();
            TenantContext::setById($this->testTenantId);
        } finally {
            parent::tearDown();
        }
    }

    public function test_concurrent_reset_submissions_change_the_password_at_most_once(): void
    {
        $oldPassword = 'ResetOldPassword123!';
        [$userId, $email, $oldHash] = $this->createCommittedUser($oldPassword, 'reset');
        $plainToken = bin2hex(random_bytes(32));
        DB::table('password_resets')->insert([
            'email' => $email,
            'tenant_id' => $this->testTenantId,
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $newPasswords = ['ResetWinnerAlpha123!', 'ResetWinnerBravo123!'];
        $results = $this->runWorkers(function (int $index) use ($plainToken, $newPasswords): array {
            Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
            app()->instance(EmailDispatchService::class, new PasswordConcurrencyDiscardingEmailDispatchService());

            $response = $this->apiPost('/auth/reset-password', [
                'token' => $plainToken,
                'password' => $newPasswords[$index],
                'password_confirmation' => $newPasswords[$index],
            ]);

            return [
                'status' => $response->getStatusCode(),
                'code' => $response->json('errors.0.code'),
            ];
        });

        $statuses = array_column($results, 'status');
        sort($statuses);
        self::assertSame([200, 400], $statuses);
        $rejectedReset = current(array_filter(
            $results,
            static fn (array $result): bool => $result['status'] === 400
        ));
        self::assertIsArray($rejectedReset);
        self::assertSame(ApiErrorCodes::AUTH_TOKEN_INVALID, $rejectedReset['code']);

        $finalHash = (string) DB::table('users')->where('id', $userId)->value('password_hash');
        self::assertTrue(
            password_verify($newPasswords[0], $finalHash) || password_verify($newPasswords[1], $finalHash)
        );
        self::assertFalse(password_verify($oldPassword, $finalHash));
        self::assertSame(0, DB::table('password_resets')->where('email', $email)->count());
        self::assertSame(1, DB::table('user_password_history')->where('user_id', $userId)->count());
        self::assertSame($oldHash, DB::table('user_password_history')
            ->where('user_id', $userId)
            ->value('password_hash'));
    }

    public function test_concurrent_current_password_changes_cannot_both_use_the_old_password(): void
    {
        $oldPassword = 'CurrentOldPassword123!';
        [$userId, , $oldHash] = $this->createCommittedUser($oldPassword, 'change');
        $newPasswords = ['CurrentWinnerAlpha123!', 'CurrentWinnerBravo123!'];

        $results = $this->runWorkers(function (int $index) use (
            $userId,
            $oldPassword,
            $newPasswords
        ): array {
            app()->instance(EmailDispatchService::class, new PasswordConcurrencyDiscardingEmailDispatchService());
            $updated = UserService::updatePassword($userId, $oldPassword, $newPasswords[$index]);

            return [
                'status' => $updated ? 'updated' : 'rejected',
                'errors' => UserService::getErrors(),
            ];
        });

        $statuses = array_column($results, 'status');
        sort($statuses);
        self::assertSame(['rejected', 'updated'], $statuses);
        $rejectedChange = current(array_filter(
            $results,
            static fn (array $result): bool => $result['status'] === 'rejected'
        ));
        self::assertIsArray($rejectedChange);
        self::assertIsArray($rejectedChange['errors'] ?? null);
        self::assertContains('INVALID_PASSWORD', array_column($rejectedChange['errors'], 'code'));

        $finalHash = (string) DB::table('users')->where('id', $userId)->value('password_hash');
        self::assertTrue(
            password_verify($newPasswords[0], $finalHash) || password_verify($newPasswords[1], $finalHash)
        );
        self::assertFalse(password_verify($oldPassword, $finalHash));
        self::assertSame(1, DB::table('user_password_history')->where('user_id', $userId)->count());
        self::assertSame($oldHash, DB::table('user_password_history')
            ->where('user_id', $userId)
            ->value('password_hash'));
    }

    public function test_concurrent_backup_code_verification_has_exactly_one_winner(): void
    {
        [$userId] = $this->createCommittedUser('BackupCodePassword123!', 'backup-code');
        $codeHash = password_hash('ABCD1234', PASSWORD_DEFAULT);
        DB::table('user_backup_codes')->insert([
            [
                'user_id' => $userId,
                'tenant_id' => $this->testTenantId,
                'code_hash' => $codeHash,
                'is_used' => 0,
            ],
            [
                'user_id' => $userId,
                'tenant_id' => 999,
                'code_hash' => $codeHash,
                'is_used' => 0,
            ],
        ]);

        $results = $this->runWorkers(function (int $_index) use ($userId): array {
            $result = TotpService::verifyBackupCode($userId, 'ABCD-1234', $this->testTenantId);

            return [
                'status' => ($result['success'] ?? false) ? 'consumed' : 'rejected',
                'error' => $result['error'] ?? null,
            ];
        });

        $statuses = array_column($results, 'status');
        sort($statuses);
        self::assertSame(['consumed', 'rejected'], $statuses);
        self::assertSame(1, DB::table('user_backup_codes')
            ->where('user_id', $userId)
            ->where('tenant_id', $this->testTenantId)
            ->where('is_used', 1)
            ->count());
        self::assertSame(1, DB::table('user_backup_codes')
            ->where('user_id', $userId)
            ->where('tenant_id', 999)
            ->where('is_used', 0)
            ->count());
    }

    /**
     * @param callable(int):array<string, mixed> $worker
     * @return list<array<string, mixed>>
     */
    private function runWorkers(callable $worker): array
    {
        DB::disconnect();
        $workers = [];

        for ($index = 0; $index < 2; $index++) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sockets === false) {
                throw new RuntimeException('password_mutation_concurrency_socket_failed');
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('password_mutation_concurrency_fork_failed');
            }

            if ($pid === 0) {
                fclose($sockets[0]);
                fread($sockets[1], 1);

                try {
                    DB::purge();
                    DB::reconnect();
                    DB::statement('SET SESSION innodb_lock_wait_timeout = 10');
                    TenantContext::reset();
                    TenantContext::setById($this->testTenantId);

                    $message = $worker($index);
                    fwrite($sockets[1], json_encode($message, JSON_THROW_ON_ERROR));
                    fclose($sockets[1]);
                    exit(0);
                } catch (Throwable $exception) {
                    fwrite($sockets[1], json_encode([
                        'status' => 'error',
                        'message' => $exception->getMessage(),
                    ], JSON_THROW_ON_ERROR));
                    fclose($sockets[1]);
                    exit(1);
                }
            }

            fclose($sockets[1]);
            $workers[] = [
                'pid' => $pid,
                'socket' => $sockets[0],
                'buffer' => '',
            ];
        }

        foreach ($workers as $child) {
            fwrite($child['socket'], '1');
        }

        $results = $this->awaitWorkers($workers, 25.0);
        DB::purge();
        DB::reconnect();
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        return $results;
    }

    /**
     * @param list<array{pid: int, socket: resource, buffer: string}> $workers
     * @return list<array<string, mixed>>
     */
    private function awaitWorkers(array $workers, float $timeoutSeconds): array
    {
        foreach ($workers as &$worker) {
            stream_set_blocking($worker['socket'], false);
        }
        unset($worker);

        $deadline = microtime(true) + $timeoutSeconds;
        $pending = array_keys($workers);
        $completed = [];

        while ($pending !== [] && microtime(true) < $deadline) {
            foreach ($pending as $offset => $index) {
                $chunk = stream_get_contents($workers[$index]['socket']);
                if (is_string($chunk) && $chunk !== '') {
                    $workers[$index]['buffer'] .= $chunk;
                }

                $waited = pcntl_waitpid($workers[$index]['pid'], $status, WNOHANG);
                if ($waited !== $workers[$index]['pid']) {
                    continue;
                }

                $tail = stream_get_contents($workers[$index]['socket']);
                if (is_string($tail) && $tail !== '') {
                    $workers[$index]['buffer'] .= $tail;
                }
                fclose($workers[$index]['socket']);

                $exitStatus = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
                self::assertSame(0, $exitStatus, $workers[$index]['buffer']);
                $decoded = json_decode($workers[$index]['buffer'], true, 512, JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);
                $completed[] = $decoded;
                unset($pending[$offset]);
            }

            $pending = array_values($pending);
            if ($pending !== []) {
                usleep(20_000);
            }
        }

        if ($pending !== []) {
            foreach ($pending as $index) {
                posix_kill($workers[$index]['pid'], 9);
                pcntl_waitpid($workers[$index]['pid'], $status);
                fclose($workers[$index]['socket']);
            }
            self::fail('Password mutation workers exceeded the 25-second deadline.');
        }

        return $completed;
    }

    /** @return array{int, string, string} */
    private function createCommittedUser(string $password, string $kind): array
    {
        $suffix = bin2hex(random_bytes(8));
        $email = "password-concurrency-{$kind}-{$suffix}@example.test";
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Password Concurrency Member',
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
            'is_active' => true,
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->fixtures[] = ['id' => $userId, 'email' => $email];

        return [$userId, $email, $passwordHash];
    }
}

final class PasswordConcurrencyDiscardingEmailDispatchService extends EmailDispatchService
{
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        return false;
    }
}
