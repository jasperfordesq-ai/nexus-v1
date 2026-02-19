<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Enterprise;

use Nexus\Services\Enterprise\LoggerService;
use Nexus\Tests\TestCase;

/**
 * Logger Service Tests
 *
 * Tests for the structured logging service including log levels,
 * context handling, and JSON formatting.
 */
class LoggerServiceTest extends TestCase
{
    private string $testLogPath;
    private LoggerService $logger;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a temporary directory for test logs
        $this->testLogPath = sys_get_temp_dir() . '/nexus_test_logs_' . uniqid();
        mkdir($this->testLogPath, 0755, true);

        putenv('LOG_PATH=' . $this->testLogPath);
        putenv('LOG_FORMAT=json');
        putenv('LOG_LEVEL=debug');

        $this->logger = LoggerService::channel('test');
    }

    protected function tearDown(): void
    {
        // Clean up test logs
        $this->cleanDirectory($this->testLogPath);

        parent::tearDown();
    }

    /**
     * Test logging an info message.
     */
    public function testLogInfoMessage(): void
    {
        $this->logger->info('Test info message');

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('Test info message', $content);
        $this->assertStringContainsString('INFO', $content);
    }

    /**
     * Test logging with context.
     */
    public function testLogWithContext(): void
    {
        $this->logger->info('User logged in', [
            'user_id' => 123,
            'ip' => '192.168.1.1'
        ]);

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        // Parse JSON log entry
        $logEntry = json_decode(trim($content), true);

        $this->assertIsArray($logEntry);
        $this->assertEquals('User logged in', $logEntry['message']);
        $this->assertArrayHasKey('context', $logEntry);
        $this->assertEquals(123, $logEntry['context']['user_id']);
    }

    /**
     * Test all log levels.
     */
    public function testAllLogLevels(): void
    {
        $this->logger->emergency('Emergency message');
        $this->logger->alert('Alert message');
        $this->logger->critical('Critical message');
        $this->logger->error('Error message');
        $this->logger->warning('Warning message');
        $this->logger->notice('Notice message');
        $this->logger->info('Info message');
        $this->logger->debug('Debug message');

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('EMERGENCY', $content);
        $this->assertStringContainsString('ALERT', $content);
        $this->assertStringContainsString('CRITICAL', $content);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('NOTICE', $content);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('DEBUG', $content);
    }

    /**
     * Test error logs go to error file.
     */
    public function testErrorLogsToErrorFile(): void
    {
        $this->logger->error('Test error message');

        $errorLogFile = $this->testLogPath . '/error-' . date('Y-m-d') . '.log';
        $this->assertFileExists($errorLogFile);

        $content = file_get_contents($errorLogFile);
        $this->assertStringContainsString('Test error message', $content);
    }

    /**
     * Test logging an exception.
     */
    public function testLogException(): void
    {
        $exception = new \RuntimeException('Test exception', 500);

        $this->logger->exception($exception);

        $errorLogFile = $this->testLogPath . '/error-' . date('Y-m-d') . '.log';
        $content = file_get_contents($errorLogFile);

        $logEntry = json_decode(trim($content), true);

        $this->assertArrayHasKey('context', $logEntry);
        $this->assertArrayHasKey('exception', $logEntry['context']);
        $this->assertEquals('RuntimeException', $logEntry['context']['exception']['class']);
        $this->assertEquals('Test exception', $logEntry['context']['exception']['message']);
        $this->assertEquals(500, $logEntry['context']['exception']['code']);
    }

    /**
     * Test message interpolation.
     */
    public function testMessageInterpolation(): void
    {
        $this->logger->info('User {username} performed {action}', [
            'username' => 'john_doe',
            'action' => 'login'
        ]);

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $logEntry = json_decode(trim($content), true);

        $this->assertEquals('User john_doe performed login', $logEntry['message']);
    }

    /**
     * Test adding persistent context.
     */
    public function testWithContext(): void
    {
        $this->logger->withContext(['request_id' => 'req_123']);

        $this->logger->info('First message');
        $this->logger->info('Second message');

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $line) {
            $logEntry = json_decode($line, true);
            $this->assertArrayHasKey('request_id', $logEntry);
            $this->assertEquals('req_123', $logEntry['request_id']);
        }
    }

    /**
     * Test clearing context.
     */
    public function testClearContext(): void
    {
        $this->logger->withContext(['temp_key' => 'temp_value']);
        $this->logger->clearContext();

        $this->logger->info('Message after clear');

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);
        $logEntry = json_decode(trim($content), true);

        $this->assertArrayNotHasKey('temp_key', $logEntry);
    }

    /**
     * Test log level filtering.
     */
    public function testLogLevelFiltering(): void
    {
        // Set minimum level to warning
        putenv('LOG_LEVEL=warning');
        $logger = LoggerService::channel('filtered');

        $logger->debug('Debug should not appear');
        $logger->info('Info should not appear');
        $logger->warning('Warning should appear');

        $logFile = $this->testLogPath . '/filtered-' . date('Y-m-d') . '.log';

        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $this->assertStringNotContainsString('Debug should not appear', $content);
            $this->assertStringNotContainsString('Info should not appear', $content);
            $this->assertStringContainsString('Warning should appear', $content);
        }
    }

    /**
     * Test JSON format structure.
     */
    public function testJsonFormatStructure(): void
    {
        $this->logger->info('Test message');

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);
        $logEntry = json_decode(trim($content), true);

        // Required fields
        $this->assertArrayHasKey('timestamp', $logEntry);
        $this->assertArrayHasKey('level', $logEntry);
        $this->assertArrayHasKey('channel', $logEntry);
        $this->assertArrayHasKey('message', $logEntry);

        // DataDog trace context
        $this->assertArrayHasKey('dd', $logEntry);
        $this->assertArrayHasKey('trace_id', $logEntry['dd']);
    }

    /**
     * Test channel naming.
     */
    public function testChannelNaming(): void
    {
        $logger1 = LoggerService::channel('auth');
        $logger2 = LoggerService::channel('api');

        $logger1->info('Auth log');
        $logger2->info('API log');

        $authLog = $this->testLogPath . '/auth-' . date('Y-m-d') . '.log';
        $apiLog = $this->testLogPath . '/api-' . date('Y-m-d') . '.log';

        $this->assertFileExists($authLog);
        $this->assertFileExists($apiLog);
    }

    /**
     * Test singleton pattern for same channel.
     */
    public function testSingletonForSameChannel(): void
    {
        $logger1 = LoggerService::getInstance('test');
        $logger2 = LoggerService::getInstance('test');

        $this->assertSame($logger1, $logger2);
    }

    /**
     * Helper: Clean up test directory.
     */
    private function cleanDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->cleanDirectory($file) : unlink($file);
        }
        rmdir($path);
    }
}
