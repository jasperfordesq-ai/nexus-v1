<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Integration;

use App\Core\AudioUploader;
use App\Core\TenantContext;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Laravel\TestCase;
use Throwable;

/** Real independent MariaDB connections prove erasure serializes message sends. */
final class MessageErasureConcurrencyTest extends TestCase
{
    private ?int $senderId = null;
    private ?int $recipientId = null;
    private ?string $voicePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('MySQL/MariaDB is required for erasure lock tests.');
        }

        foreach (['pcntl_fork', 'pcntl_waitpid', 'stream_socket_pair', 'posix_kill'] as $function) {
            if (!function_exists($function)) {
                self::markTestSkipped("{$function} is required for erasure lock tests.");
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            DB::purge();
            DB::reconnect();

            if ($this->voicePath !== null) {
                @unlink($this->voicePath);
            }

            $userIds = array_values(array_filter([
                $this->senderId,
                $this->recipientId,
            ], static fn (?int $id): bool => $id !== null));
            if ($userIds !== []) {
                DB::table('messages')
                    ->whereIn('sender_id', $userIds)
                    ->orWhereIn('receiver_id', $userIds)
                    ->delete();
                DB::table('gdpr_audit_log')->whereIn('user_id', $userIds)->delete();
                DB::table('revoked_tokens')->whereIn('user_id', $userIds)->delete();
                DB::table('refresh_token_sessions')->whereIn('user_id', $userIds)->delete();
                DB::table('users')->whereIn('id', $userIds)->delete();
            }

            TenantContext::reset();
            TenantContext::setById($this->testTenantId);
            app()->instance('tenant.id', $this->testTenantId);
        } finally {
            parent::tearDown();
        }
    }

    public function test_voice_send_that_started_during_erasure_cannot_commit_after_erasure(): void
    {
        [$senderId, $recipientId] = $this->createCommittedUsers();
        [$voiceUrl, $voicePath] = $this->createVoiceFixture();

        DB::disconnect();

        $erasureSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($erasureSockets === false) {
            throw new RuntimeException('message_erasure_socket_failed');
        }

        $erasurePid = pcntl_fork();
        if ($erasurePid === -1) {
            throw new RuntimeException('message_erasure_fork_failed');
        }
        if ($erasurePid === 0) {
            fclose($erasureSockets[0]);
            $this->runErasureWorker($erasureSockets[1], $senderId);
        }
        fclose($erasureSockets[1]);
        $erasureSocket = $erasureSockets[0];

        $sendPid = null;
        $sendSocket = null;
        $sendWasReaped = false;
        $sendExitStatus = null;

        try {
            self::assertSame(
                'L',
                $this->readProtocolLine($erasureSocket, 10.0),
                'Erasure did not reach generateDataExport while holding the tenant-user lock.',
            );

            $sendSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sendSockets === false) {
                throw new RuntimeException('message_send_socket_failed');
            }

            $sendPid = pcntl_fork();
            if ($sendPid === -1) {
                throw new RuntimeException('message_send_fork_failed');
            }
            if ($sendPid === 0) {
                fclose($sendSockets[0]);
                $this->runSendWorker($sendSockets[1], $senderId, $recipientId, $voiceUrl);
            }
            fclose($sendSockets[1]);
            $sendSocket = $sendSockets[0];

            self::assertSame('B', $this->readProtocolLine($sendSocket, 10.0));

            // The erasure worker is paused while its users-row lock is held.
            // The sender must not finish until that lock is released, even
            // though its earlier request authentication and preflight both saw
            // an active account.
            usleep(250_000);
            $prematureWait = pcntl_waitpid($sendPid, $status, WNOHANG);
            if ($prematureWait === $sendPid) {
                $sendWasReaped = true;
                $sendExitStatus = $status;
            }
            self::assertSame(0, $prematureWait, 'Message send bypassed the erasure users-row lock.');

            fwrite($erasureSocket, "C\n");

            $erasureResult = $this->decodeWorkerResult(
                $this->readProtocolLine($erasureSocket, 30.0)
            );
            $sendResult = $this->decodeWorkerResult(
                $this->readProtocolLine($sendSocket, 30.0)
            );

            self::assertSame('erased', $erasureResult['status'] ?? null);
            self::assertSame(0, $erasureResult['voice_rows_seen'] ?? null);
            self::assertSame('rejected', $sendResult['status'] ?? null);
            self::assertSame('FORBIDDEN', $sendResult['code'] ?? null);
            self::assertTrue($sendResult['staged_file_deleted'] ?? false);
        } finally {
            @fwrite($erasureSocket, "C\n");
            fclose($erasureSocket);
            if (is_resource($sendSocket)) {
                fclose($sendSocket);
            }

            $this->finishWorker($erasurePid);
            if ($sendPid !== null && !$sendWasReaped) {
                $this->finishWorker($sendPid);
            } elseif ($sendWasReaped && $sendExitStatus !== null) {
                self::assertTrue(pcntl_wifexited($sendExitStatus));
                self::assertSame(0, pcntl_wexitstatus($sendExitStatus));
            }
        }

        DB::purge();
        DB::reconnect();
        self::assertSame('inactive', DB::table('users')->where('id', $senderId)->value('status'));
        self::assertNotNull(DB::table('users')->where('id', $senderId)->value('deleted_at'));
        self::assertSame(0, DB::table('messages')->where('sender_id', $senderId)->count());
        self::assertFileDoesNotExist($voicePath);
    }

    /** @param resource $socket */
    private function runErasureWorker($socket, int $senderId): never
    {
        try {
            DB::purge();
            DB::reconnect();
            DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
            TenantContext::reset();
            TenantContext::setById($this->testTenantId);
            app()->instance('tenant.id', $this->testTenantId);
            Event::fake();

            DB::beginTransaction();
            $lockedSender = DB::table('users')
                ->where('id', $senderId)
                ->where('tenant_id', $this->testTenantId)
                ->lockForUpdate()
                ->first(['id']);
            if ($lockedSender === null) {
                throw new RuntimeException('message_erasure_sender_missing');
            }

            // Match GdprService's contract: acquire the tenant-user row first
            // and hold it across the inactive transition and voice-row scan.
            fwrite($socket, "L\n");
            $release = fgets($socket);
            if (!is_string($release) || trim($release) !== 'C') {
                throw new RuntimeException('message_erasure_release_signal_missing');
            }

            DB::table('users')
                ->where('id', $senderId)
                ->where('tenant_id', $this->testTenantId)
                ->update([
                    'status' => 'inactive',
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
            $voiceRowsSeen = DB::table('messages')
                ->where('tenant_id', $this->testTenantId)
                ->where('sender_id', $senderId)
                ->whereNotNull('audio_url')
                ->count();
            DB::commit();

            $this->writeWorkerResult($socket, [
                'status' => 'erased',
                'voice_rows_seen' => $voiceRowsSeen,
            ]);
            fclose($socket);
            exit(0);
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->writeWorkerError($socket, $exception);
            fclose($socket);
            exit(1);
        }
    }

    /** @param resource $socket */
    private function runSendWorker(
        $socket,
        int $senderId,
        int $recipientId,
        string $voiceUrl,
    ): never {
        try {
            DB::purge();
            DB::reconnect();
            DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
            TenantContext::reset();
            TenantContext::setById($this->testTenantId);
            app()->instance('tenant.id', $this->testTenantId);
            Event::fake();

            fwrite($socket, "B\n");
            $message = MessageService::sendVoice($senderId, $recipientId, $voiceUrl, 5);
            $errors = MessageService::getErrors();
            $stagedFileDeleted = $message === []
                && AudioUploader::deleteForTenant($voiceUrl, $this->testTenantId);

            $this->writeWorkerResult($socket, [
                'status' => $message === [] ? 'rejected' : 'sent',
                'code' => $errors[0]['code'] ?? null,
                'staged_file_deleted' => $stagedFileDeleted,
            ]);
            fclose($socket);
            exit(0);
        } catch (Throwable $exception) {
            $this->writeWorkerError($socket, $exception);
            fclose($socket);
            exit(1);
        }
    }

    /** @return array{int, int} */
    private function createCommittedUsers(): array
    {
        $suffix = bin2hex(random_bytes(8));
        $base = [
            'tenant_id' => $this->testTenantId,
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID),
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
            'is_active' => true,
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->senderId = (int) DB::table('users')->insertGetId([
            ...$base,
            'name' => 'Erasure Race Sender',
            'email' => "erasure-race-sender-{$suffix}@example.test",
        ]);
        $this->recipientId = (int) DB::table('users')->insertGetId([
            ...$base,
            'name' => 'Erasure Race Recipient',
            'email' => "erasure-race-recipient-{$suffix}@example.test",
        ]);

        return [$this->senderId, $this->recipientId];
    }

    /** @return array{string, string} */
    private function createVoiceFixture(): array
    {
        $directory = public_path("uploads/{$this->testTenantId}/voice_messages");
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('message_erasure_voice_directory_failed');
        }

        $filename = 'voice_' . bin2hex(random_bytes(16)) . '.webm';
        $this->voicePath = $directory . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($this->voicePath, 'voice bytes staged before erasure') === false) {
            throw new RuntimeException('message_erasure_voice_fixture_failed');
        }

        return [
            "/uploads/{$this->testTenantId}/voice_messages/{$filename}",
            $this->voicePath,
        ];
    }

    /** @param resource $socket */
    private function readProtocolLine($socket, float $timeoutSeconds): string
    {
        $seconds = (int) floor($timeoutSeconds);
        $microseconds = (int) (($timeoutSeconds - $seconds) * 1_000_000);
        stream_set_timeout($socket, $seconds, $microseconds);
        $line = fgets($socket);
        if (!is_string($line)) {
            throw new RuntimeException('message_erasure_worker_protocol_timeout');
        }

        return trim($line);
    }

    /** @return array<string, mixed> */
    private function decodeWorkerResult(string $line): array
    {
        if (!str_starts_with($line, 'R')) {
            throw new RuntimeException('message_erasure_worker_failed: ' . $line);
        }

        $decoded = json_decode(substr($line, 1), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('message_erasure_worker_result_invalid');
        }

        return $decoded;
    }

    /** @param resource $socket @param array<string, mixed> $result */
    private function writeWorkerResult($socket, array $result): void
    {
        fwrite($socket, 'R' . json_encode($result, JSON_THROW_ON_ERROR) . "\n");
    }

    /** @param resource $socket */
    private function writeWorkerError($socket, Throwable $exception): void
    {
        fwrite($socket, 'E' . json_encode([
            'message' => $exception->getMessage(),
            'type' => $exception::class,
        ], JSON_THROW_ON_ERROR) . "\n");
    }

    private function finishWorker(int $pid): void
    {
        $deadline = microtime(true) + 5.0;
        do {
            $waited = pcntl_waitpid($pid, $status, WNOHANG);
            if ($waited === $pid) {
                self::assertTrue(pcntl_wifexited($status));
                self::assertSame(0, pcntl_wexitstatus($status));

                return;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);

        posix_kill($pid, 9);
        pcntl_waitpid($pid, $status);
        self::fail('Message erasure worker exceeded the five-second exit deadline.');
    }
}
