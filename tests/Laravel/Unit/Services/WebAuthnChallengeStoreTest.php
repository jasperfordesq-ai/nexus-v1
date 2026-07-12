<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

/** Test-only hook for deterministically reproducing a glob/read race. */
final class WebAuthnChallengeStoreFileReadTestHook
{
    public static ?string $disappearOnRead = null;
}

/** @return string|false */
function file_get_contents(
    string $filename,
    bool $useIncludePath = false,
    mixed $context = null,
    int $offset = 0,
    ?int $length = null
): string|false {
    if (WebAuthnChallengeStoreFileReadTestHook::$disappearOnRead === $filename) {
        WebAuthnChallengeStoreFileReadTestHook::$disappearOnRead = null;
        @unlink($filename);

        return false;
    }

    if ($length === null) {
        return \file_get_contents($filename, $useIncludePath, $context, $offset);
    }

    return \file_get_contents($filename, $useIncludePath, $context, $offset, $length);
}

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\WebAuthnChallengeStore;
use App\Services\WebAuthnChallengeStoreFileReadTestHook;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Redis;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class WebAuthnChallengeStoreTest extends TestCase
{
    private int $testTenantId = 2;

    private string $fileDirectory;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        // Boot only Laravel's container/config helpers. This service is
        // independent of the database, so its focused unit suite must not wait
        // on unrelated integration tests holding database locks.
        $this->application = new Application(dirname(__DIR__, 4));
        $this->application->instance('config', new Repository());
        Container::setInstance($this->application);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->application);

        TenantContext::reset();
        $tenantContext = new ReflectionClass(TenantContext::class);
        $tenant = $tenantContext->getProperty('tenant');
        $tenant->setAccessible(true);
        $tenant->setValue(null, ['id' => $this->testTenantId]);
        $cachedId = $tenantContext->getProperty('cachedId');
        $cachedId->setAccessible(true);
        $cachedId->setValue(null, $this->testTenantId);

        $this->fileDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'nexus-webauthn-challenges-'
            . bin2hex(random_bytes(8));

        config([
            'webauthn.challenge_store.driver' => 'file',
            'webauthn.challenge_store.file_path' => $this->fileDirectory,
            'webauthn.challenge_store.redis_connection' => 'webauthn-test',
            'webauthn.challenge_store.file_cleanup_every' => PHP_INT_MAX,
        ]);

        WebAuthnChallengeStoreFileReadTestHook::$disappearOnRead = null;
    }

    public function test_create_returns_a_strict_64_character_hex_identifier(): void
    {
        $challengeId = WebAuthnChallengeStore::create('test-challenge', 1, 'register');

        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/D', $challengeId);
    }

    public function test_get_reads_without_consuming_the_challenge(): void
    {
        $challengeId = WebAuthnChallengeStore::create(
            'my-challenge',
            42,
            'authenticate',
            ['email' => 'member@example.test']
        );

        $firstRead = WebAuthnChallengeStore::get($challengeId);
        $secondRead = WebAuthnChallengeStore::get($challengeId);

        $this->assertSame($firstRead, $secondRead);
        $this->assertSame('my-challenge', $firstRead['challenge']);
        $this->assertSame(42, $firstRead['user_id']);
        $this->assertSame('authenticate', $firstRead['type']);
        $this->assertSame('member@example.test', $firstRead['metadata']['email']);
        $this->assertSame($this->testTenantId, $firstRead['tenant_id']);
    }

    public function test_pull_atomically_returns_the_challenge_only_once(): void
    {
        $challengeId = WebAuthnChallengeStore::create('single-use', 7);

        $firstPull = WebAuthnChallengeStore::pull($challengeId);
        $secondPull = WebAuthnChallengeStore::pull($challengeId);

        $this->assertSame('single-use', $firstPull['challenge']);
        $this->assertNull($secondPull);
        $this->assertNull(WebAuthnChallengeStore::get($challengeId));
    }

    public function test_concurrent_file_claim_allows_exactly_one_consumer(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            $this->markTestSkipped('pcntl is required for the concurrent file-claim test.');
        }

        $challengeId = WebAuthnChallengeStore::create('concurrent-file-claim', 7);
        $startFile = $this->fileDirectory . DIRECTORY_SEPARATOR . 'start';
        $pids = [];

        for ($worker = 0; $worker < 2; $worker++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Unable to fork a WebAuthn challenge consumer.');
            }

            if ($pid === 0) {
                $deadline = microtime(true) + 5;
                while (!is_file($startFile) && microtime(true) < $deadline) {
                    usleep(1_000);
                }

                $result = WebAuthnChallengeStore::pull($challengeId);
                file_put_contents(
                    $this->fileDirectory . DIRECTORY_SEPARATOR . "result-{$worker}.txt",
                    $result === null ? '0' : '1',
                    LOCK_EX
                );
                exit(0);
            }

            $pids[] = $pid;
        }

        file_put_contents($startFile, 'go', LOCK_EX);
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        $results = [
            trim((string) file_get_contents($this->fileDirectory . DIRECTORY_SEPARATOR . 'result-0.txt')),
            trim((string) file_get_contents($this->fileDirectory . DIRECTORY_SEPARATOR . 'result-1.txt')),
        ];
        sort($results);

        $this->assertSame(['0', '1'], $results);
    }

    public function test_consume_preserves_the_historical_boolean_api(): void
    {
        $challengeId = WebAuthnChallengeStore::create('consume-test', 1);

        $this->assertTrue(WebAuthnChallengeStore::consume($challengeId));
        $this->assertFalse(WebAuthnChallengeStore::consume($challengeId));
    }

    public function test_delete_reports_whether_a_record_was_removed(): void
    {
        $challengeId = WebAuthnChallengeStore::create('delete-test', 1);

        $this->assertTrue(WebAuthnChallengeStore::delete($challengeId));
        $this->assertFalse(WebAuthnChallengeStore::delete($challengeId));
    }

    public function test_verify_consumes_a_matching_challenge_atomically(): void
    {
        $challengeId = WebAuthnChallengeStore::create('verify-test', 10, 'register');

        $result = WebAuthnChallengeStore::verify($challengeId, 'verify-test', 10, 'register');
        $replay = WebAuthnChallengeStore::verify($challengeId, 'verify-test', 10, 'register');

        $this->assertTrue($result['valid']);
        $this->assertSame('verify-test', $result['data']['challenge']);
        $this->assertFalse($replay['valid']);
        $this->assertSame('Challenge not found or expired', $replay['error']);
    }

    public function test_verify_consumes_a_challenge_even_when_validation_fails(): void
    {
        $challengeId = WebAuthnChallengeStore::create('correct', 1, 'register');

        $result = WebAuthnChallengeStore::verify($challengeId, 'wrong', 1, 'register');

        $this->assertFalse($result['valid']);
        $this->assertSame('Challenge mismatch', $result['error']);
        $this->assertNull(WebAuthnChallengeStore::get($challengeId));
    }

    public function test_verify_rejects_wrong_user_and_type(): void
    {
        $wrongUserId = WebAuthnChallengeStore::create('user-test', 1, 'register');
        $wrongTypeId = WebAuthnChallengeStore::create('type-test', 1, 'register');

        $wrongUser = WebAuthnChallengeStore::verify($wrongUserId, 'user-test', 999, 'register');
        $wrongType = WebAuthnChallengeStore::verify($wrongTypeId, 'type-test', 1, 'authenticate');

        $this->assertFalse($wrongUser['valid']);
        $this->assertSame('User mismatch', $wrongUser['error']);
        $this->assertFalse($wrongType['valid']);
        $this->assertSame('Challenge type mismatch', $wrongType['error']);
        $this->assertNull(WebAuthnChallengeStore::get($wrongUserId));
        $this->assertNull(WebAuthnChallengeStore::get($wrongTypeId));
    }

    public function test_expired_file_challenge_is_consumed_and_rejected(): void
    {
        $challengeId = WebAuthnChallengeStore::create('expired', 1);
        $file = $this->onlyChallengeFile();
        $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $data['expires_at'] = time() - 1;
        file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);

        $this->assertNull(WebAuthnChallengeStore::pull($challengeId));
        $this->assertFileDoesNotExist($file);
    }

    public function test_cleanup_tolerates_a_challenge_claimed_after_directory_scan(): void
    {
        WebAuthnChallengeStore::create('cleanup-claim-race', 1);
        $file = $this->onlyChallengeFile();
        WebAuthnChallengeStoreFileReadTestHook::$disappearOnRead = $file;

        WebAuthnChallengeStore::cleanup();

        $this->assertNull(WebAuthnChallengeStoreFileReadTestHook::$disappearOnRead);
        $this->assertFileDoesNotExist($file);
    }

    public function test_malformed_storage_record_fails_closed(): void
    {
        $challengeId = WebAuthnChallengeStore::create('corrupt', 1);
        file_put_contents($this->onlyChallengeFile(), '{not-json', LOCK_EX);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid JSON');

        WebAuthnChallengeStore::get($challengeId);
    }

    public function test_file_storage_error_fails_closed(): void
    {
        mkdir($this->fileDirectory, 0700, true);
        $blockedPath = $this->fileDirectory . DIRECTORY_SEPARATOR . 'not-a-directory';
        file_put_contents($blockedPath, 'blocking file', LOCK_EX);
        config(['webauthn.challenge_store.file_path' => $blockedPath]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create the WebAuthn challenge directory');

        WebAuthnChallengeStore::create('must-fail-closed', 1);
    }

    public function test_invalid_challenge_identifiers_never_reach_storage(): void
    {
        config(['webauthn.challenge_store.driver' => 'redis']);
        Redis::shouldReceive('connection')->never();

        foreach ([
            '',
            'not-a-challenge-id',
            str_repeat('a', 63),
            str_repeat('a', 65),
            str_repeat('g', 64),
            strtoupper(str_repeat('ab', 32)),
        ] as $invalidId) {
            $this->assertNull(WebAuthnChallengeStore::get($invalidId));
            $this->assertNull(WebAuthnChallengeStore::pull($invalidId));
            $this->assertFalse(WebAuthnChallengeStore::delete($invalidId));
            $this->assertFalse(WebAuthnChallengeStore::consume($invalidId));
        }
    }

    public function test_unknown_driver_fails_closed(): void
    {
        config(['webauthn.challenge_store.driver' => 'automatic-fallback']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported WebAuthn challenge store driver');

        WebAuthnChallengeStore::create('test', 1);
    }

    public function test_redis_pull_uses_getdel_and_returns_a_record_only_once(): void
    {
        config(['webauthn.challenge_store.driver' => 'redis']);
        $records = [];
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('setex')
            ->once()
            ->andReturnUsing(static function (string $key, int $ttl, string $value) use (&$records): bool {
                $records[$key] = $value;
                return $ttl === WebAuthnChallengeStore::CHALLENGE_TTL;
            });
        $connection->shouldReceive('command')
            ->twice()
            ->with('getdel', Mockery::type('array'))
            ->andReturnUsing(static function (string $command, array $arguments) use (&$records): string|false {
                $key = $arguments[0];
                $value = $records[$key] ?? false;
                unset($records[$key]);
                return $value;
            });
        Redis::shouldReceive('connection')
            ->with('webauthn-test')
            ->andReturn($connection);

        $challengeId = WebAuthnChallengeStore::create('redis-getdel', 1);

        $this->assertSame('redis-getdel', WebAuthnChallengeStore::pull($challengeId)['challenge']);
        $this->assertNull(WebAuthnChallengeStore::pull($challengeId));
    }

    public function test_redis_pull_falls_back_to_atomic_lua_when_getdel_is_unsupported(): void
    {
        config(['webauthn.challenge_store.driver' => 'redis']);
        $records = [];
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('setex')
            ->once()
            ->andReturnUsing(static function (string $key, int $ttl, string $value) use (&$records): bool {
                $records[$key] = $value;
                return true;
            });
        $connection->shouldReceive('command')
            ->twice()
            ->with('getdel', Mockery::type('array'))
            ->andThrow(new RuntimeException("ERR unknown command 'GETDEL'"));
        $connection->shouldReceive('eval')
            ->twice()
            ->with(Mockery::type('string'), 1, Mockery::type('string'))
            ->andReturnUsing(static function (string $script, int $keyCount, string $key) use (&$records): string|false {
                $value = $records[$key] ?? false;
                unset($records[$key]);
                return $value;
            });
        Redis::shouldReceive('connection')
            ->with('webauthn-test')
            ->andReturn($connection);

        $challengeId = WebAuthnChallengeStore::create('redis-lua', 1);

        $this->assertSame('redis-lua', WebAuthnChallengeStore::pull($challengeId)['challenge']);
        $this->assertNull(WebAuthnChallengeStore::pull($challengeId));
    }

    public function test_redis_failure_does_not_switch_to_the_file_driver(): void
    {
        config(['webauthn.challenge_store.driver' => 'redis']);
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('setex')
            ->once()
            ->andThrow(new RuntimeException('Connection lost'));
        Redis::shouldReceive('connection')
            ->with('webauthn-test')
            ->andReturn($connection);

        try {
            WebAuthnChallengeStore::create('must-fail-closed', 1);
            $this->fail('A Redis write failure must fail the ceremony.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('write the WebAuthn challenge in Redis', $exception->getMessage());
        }

        $this->assertDirectoryDoesNotExist($this->fileDirectory);
    }

    public function test_redis_getdel_runtime_failure_does_not_invoke_lua_or_file_fallback(): void
    {
        config(['webauthn.challenge_store.driver' => 'redis']);
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('command')
            ->once()
            ->andThrow(new RuntimeException('Connection lost'));
        $connection->shouldReceive('eval')->never();
        Redis::shouldReceive('connection')
            ->with('webauthn-test')
            ->andReturn($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('atomically consume the WebAuthn challenge in Redis');

        WebAuthnChallengeStore::pull(str_repeat('a', 64));
    }

    public function test_challenge_ttl_constant(): void
    {
        $this->assertSame(120, WebAuthnChallengeStore::CHALLENGE_TTL);
    }

    protected function tearDown(): void
    {
        WebAuthnChallengeStoreFileReadTestHook::$disappearOnRead = null;
        (new Filesystem())->deleteDirectory($this->fileDirectory);
        TenantContext::reset();
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    private function onlyChallengeFile(): string
    {
        $files = glob($this->fileDirectory . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $this->assertCount(1, $files);

        return $files[0];
    }
}
