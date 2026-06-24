<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Tests for AiEval Artisan command (ai:eval).
 *
 * Uses unique tenant ID 99735 to avoid collisions with other test files.
 *
 * Strategy: All AI provider API keys are cleared so that
 * AIServiceFactory::chatWithFallback() throws "No AI providers available".
 * The command catches the exception per-question and stores an [error:…]
 * answer. For questions with no `criteria` AND `expected_tool: null`,
 * the judge skips the LLM call and returns pass=true (tool check passes).
 * The command always exits SUCCESS (0) even if individual questions fail.
 */
class AiEvalTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99735;

    /** @var string Absolute path to temp fixtures file written by this test */
    private string $fixturesAbsPath;

    /** @var string Relative path (from base_path()) passed to --fixtures option */
    private string $fixturesRelPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert isolated test tenant
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'AiEval Test Tenant',
                'slug'              => 'aival-test-99735',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        \App\Core\TenantContext::setById(self::TENANT_ID);

        // Clear ALL provider API keys and flush cached provider instances so
        // chatWithFallback() finds no configured provider and throws immediately.
        $this->clearAiProviderKeys();

        // Fixtures must live INSIDE base_path() because the command calls
        // base_path($fixturesOption). Write into storage/framework/testing/
        // which is guaranteed writable and gitignored.
        $dir = base_path('storage/framework/testing');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = 'nexus_aival_test_99735_' . getmypid() . '.json';
        $this->fixturesAbsPath = $dir . '/' . $filename;
        $this->fixturesRelPath = 'storage/framework/testing/' . $filename;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->fixturesAbsPath)) {
            @unlink($this->fixturesAbsPath);
        }
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function clearAiProviderKeys(): void
    {
        // Blank out env + config for every known provider so isConfigured()=false
        foreach (['OPENAI_API_KEY', 'ANTHROPIC_API_KEY', 'GEMINI_API_KEY'] as $env) {
            putenv("$env=");
            $_ENV[$env] = '';
        }

        config([
            'ai.providers.openai.api_key'    => '',
            'ai.providers.anthropic.api_key' => '',
            'ai.providers.gemini.api_key'    => '',
            'ai.providers.ollama.api_key'    => '',
        ]);

        // Flush any cached provider instances in the factory
        $ref = new \ReflectionClass(\App\Services\AI\AIServiceFactory::class);
        $instances = $ref->getProperty('instances');
        $instances->setAccessible(true);
        $instances->setValue(null, []);

        $configProp = $ref->getProperty('config');
        $configProp->setAccessible(true);
        $configProp->setValue(null, null);
    }

    /**
     * Write a temporary fixtures JSON file and return the relative path for --fixtures.
     *
     * The command calls base_path($option), so this must be relative to base_path().
     *
     * @param array<int, array<string, mixed>> $questions
     */
    private function writeFixtures(array $questions): string
    {
        file_put_contents($this->fixturesAbsPath, json_encode(['questions' => $questions]));
        return $this->fixturesRelPath;
    }

    // ------------------------------------------------------------------
    // Guard: missing --tenant option → failure
    // ------------------------------------------------------------------

    public function test_missing_tenant_option_returns_failure(): void
    {
        $path = $this->writeFixtures([]);

        $this->artisan('ai:eval', ['--fixtures' => $path])
            ->assertExitCode(1);
    }

    // ------------------------------------------------------------------
    // Guard: fixtures file does not exist → failure
    // ------------------------------------------------------------------

    public function test_missing_fixtures_file_returns_failure(): void
    {
        $this->artisan('ai:eval', [
            '--tenant'   => (string) self::TENANT_ID,
            '--fixtures' => 'nonexistent/path/to/fixtures_99735.json',
        ])->assertExitCode(1);
    }

    // ------------------------------------------------------------------
    // Guard: fixtures JSON missing 'questions' key → failure
    // ------------------------------------------------------------------

    public function test_fixtures_missing_questions_key_returns_failure(): void
    {
        file_put_contents($this->fixturesAbsPath, json_encode(['not_questions' => []]));

        $this->artisan('ai:eval', [
            '--tenant'   => (string) self::TENANT_ID,
            '--fixtures' => $this->fixturesRelPath,
        ])->assertExitCode(1);
    }

    // ------------------------------------------------------------------
    // Guard: tenant that does not exist in DB → failure
    // ------------------------------------------------------------------

    public function test_nonexistent_tenant_returns_failure(): void
    {
        $path = $this->writeFixtures([]);

        $this->artisan('ai:eval', [
            '--tenant'   => '999999',
            '--fixtures' => $path,
        ])->assertExitCode(1);
    }

    // ------------------------------------------------------------------
    // Empty questions list → success (nothing to run)
    // ------------------------------------------------------------------

    public function test_empty_questions_list_exits_success(): void
    {
        $path = $this->writeFixtures([]);

        $this->artisan('ai:eval', [
            '--tenant'   => (string) self::TENANT_ID,
            '--fixtures' => $path,
        ])->assertExitCode(0);
    }

    // ------------------------------------------------------------------
    // Single question, no criteria, no expected_tool → pass=true, exit 0
    //
    // When no providers are configured, chatWithFallback throws.
    // The command catches it and stores answer='[error: ...]'.
    // No-criteria judge path: passes if expectedToolMet() is true.
    // With expected_tool=null AND tool_called=null, expectedToolMet()=true.
    // ------------------------------------------------------------------

    public function test_single_no_criteria_question_exits_success(): void
    {
        $path = $this->writeFixtures([
            [
                'id'       => 'smoke-1',
                'prompt'   => 'Hello, who are you?',
                // No expected_tool, no criteria — judge returns pass=true regardless
            ],
        ]);

        $this->artisan('ai:eval', [
            '--tenant'   => (string) self::TENANT_ID,
            '--fixtures' => $path,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('smoke-1');
    }

    // ------------------------------------------------------------------
    // --filter option: only questions matching prefix are run
    // ------------------------------------------------------------------

    public function test_filter_option_limits_to_matching_questions(): void
    {
        $path = $this->writeFixtures([
            ['id' => 'wallet-1', 'prompt' => 'What is my balance?'],
            ['id' => 'wallet-2', 'prompt' => 'How do I send credits?'],
            ['id' => 'events-1', 'prompt' => 'What events are on?'],
        ]);

        $this->artisan('ai:eval', [
            '--tenant'   => (string) self::TENANT_ID,
            '--fixtures' => $path,
            '--filter'   => 'wallet',
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Running 2 question(s)');
    }

    // ------------------------------------------------------------------
    // --limit option: caps questions
    // ------------------------------------------------------------------

    public function test_limit_option_caps_questions_run(): void
    {
        $questions = [];
        for ($i = 1; $i <= 5; $i++) {
            $questions[] = ['id' => "q-$i", 'prompt' => "Question $i"];
        }
        $path = $this->writeFixtures($questions);

        $this->artisan('ai:eval', [
            '--tenant'   => (string) self::TENANT_ID,
            '--fixtures' => $path,
            '--limit'    => '2',
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Running 2 question(s)');
    }

    // ------------------------------------------------------------------
    // Summary output includes pass/fail counts
    // ------------------------------------------------------------------

    public function test_summary_line_is_printed(): void
    {
        $path = $this->writeFixtures([
            ['id' => 'test-1', 'prompt' => 'Test prompt'],
        ]);

        $this->artisan('ai:eval', [
            '--tenant'   => (string) self::TENANT_ID,
            '--fixtures' => $path,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Summary:');
    }

    // ------------------------------------------------------------------
    // Multiple questions, all no-criteria → all appear in output
    // ------------------------------------------------------------------

    public function test_multiple_questions_all_appear_in_output(): void
    {
        $path = $this->writeFixtures([
            ['id' => 'alpha-1', 'prompt' => 'First question'],
            ['id' => 'beta-1',  'prompt' => 'Second question'],
        ]);

        $this->artisan('ai:eval', [
            '--tenant'   => (string) self::TENANT_ID,
            '--fixtures' => $path,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('alpha-1')
            ->expectsOutputToContain('beta-1');
    }
}
