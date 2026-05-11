<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Services\AI\AIServiceFactory;
use App\Services\AI\AiModuleDocsService;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AiSupportContextService;
use Illuminate\Console\Command;

/**
 * Run the AI chat against golden-question fixtures and score the answers
 * with a judge model. Outputs a per-question pass/fail and a summary
 * score per category. Designed to be run pre-deploy to catch regressions
 * after prompt, model, or tool changes.
 *
 * Usage:
 *   php artisan ai:eval --tenant=2                   # run all 50 questions for tenant 2
 *   php artisan ai:eval --tenant=2 --filter=wallet   # only IDs starting with "wallet-"
 *   php artisan ai:eval --tenant=2 --limit=5         # smoke subset
 *   php artisan ai:eval --tenant=2 --user=1          # impersonate user 1 (needs wallet etc.)
 */
class AiEval extends Command
{
    protected $signature = 'ai:eval
                            {--tenant= : Tenant ID to run against (REQUIRED — tools need tenant scope).}
                            {--user=1 : User ID to impersonate (must belong to the tenant).}
                            {--filter= : Only run questions whose id starts with this prefix.}
                            {--limit=0 : Cap number of questions (0 = no cap).}
                            {--fixtures=tests/Ai/Golden/fixtures.json : Path to fixtures JSON, relative to base_path().}
                            {--judge-model=gpt-4o-mini : Judge model name.}';

    protected $description = 'Run golden-question evals against the AI chat and score with a judge model.';

    public function handle(
        AIServiceFactory $factory,
        AiSupportContextService $supportContext,
        ToolRegistry $tools,
        AiModuleDocsService $moduleDocs
    ): int {
        $tenantId = (int) ($this->option('tenant') ?? 0);
        if ($tenantId <= 0) {
            $this->error('--tenant is required.');
            return self::FAILURE;
        }
        $userId = (int) $this->option('user');
        $filter = (string) $this->option('filter');
        $limit = (int) $this->option('limit');
        $fixturesPath = base_path((string) $this->option('fixtures'));
        $judgeModel = (string) $this->option('judge-model');

        if (!file_exists($fixturesPath)) {
            $this->error("Fixtures file not found: $fixturesPath");
            return self::FAILURE;
        }
        $fixtures = json_decode((string) file_get_contents($fixturesPath), true);
        if (!is_array($fixtures['questions'] ?? null)) {
            $this->error('Fixtures JSON missing "questions" array.');
            return self::FAILURE;
        }

        if (!TenantContext::setById($tenantId)) {
            $this->error("Could not resolve tenant $tenantId");
            return self::FAILURE;
        }

        $questions = $fixtures['questions'];
        if ($filter !== '') {
            $questions = array_values(array_filter($questions, fn ($q) => str_starts_with($q['id'] ?? '', $filter)));
        }
        if ($limit > 0) {
            $questions = array_slice($questions, 0, $limit);
        }

        $this->info(sprintf('Running %d question(s) for tenant %d (user %d)…', count($questions), $tenantId, $userId));

        $toolSchemas = $tools->openAiSchemasFor($userId);
        $results = [];
        foreach ($questions as $q) {
            $row = $this->runOne($q, $userId, $supportContext, $moduleDocs, $tools, $toolSchemas);
            $verdict = $this->judge($row, $q['criteria'] ?? '', $judgeModel);
            $row['judge_verdict'] = $verdict;
            $results[] = $row;
            $this->line(sprintf(
                '[%s] tool=%s expected=%s judge=%s',
                $q['id'] ?? '?',
                $row['tool_called'] ?? '∅',
                $this->fmtExpected($q),
                $verdict['pass'] ? 'PASS' : 'FAIL'
            ));
        }

        $this->summary($results);
        return self::SUCCESS;
    }

    private function runOne(
        array $q,
        int $userId,
        AiSupportContextService $supportContext,
        AiModuleDocsService $moduleDocs,
        ToolRegistry $tools,
        array $toolSchemas
    ): array {
        $message = (string) ($q['prompt'] ?? '');
        $ctx = $supportContext->build($userId, $message);
        $moduleDocsPrompt = $moduleDocs->renderForPrompt(TenantContext::getId() ?? 0, $message);

        $chatMessages = [
            ['role' => 'system', 'content' => AIServiceFactory::getSystemPrompt() ?: 'You are a helpful community assistant for a timebanking platform.'],
            ['role' => 'system', 'content' => 'Use retrieval tools liberally for grounded answers.'],
            ['role' => 'system', 'content' => $ctx['content']],
        ];
        if ($moduleDocsPrompt !== '') {
            $chatMessages[] = ['role' => 'system', 'content' => $moduleDocsPrompt];
        }
        $chatMessages[] = ['role' => 'user', 'content' => $message];

        $started = microtime(true);
        $toolCalled = null;
        $finalContent = '';
        try {
            $hop = 0;
            while ($hop < 3) {
                $resp = AIServiceFactory::chatWithFallback($chatMessages, [
                    'temperature' => 0.2,
                    'max_tokens' => 800,
                    'tools' => $toolSchemas,
                    'tool_choice' => 'auto',
                ]);
                $calls = $resp['tool_calls'] ?? [];
                if ($calls === []) {
                    $finalContent = $resp['content'] ?? '';
                    break;
                }
                $toolCalled = $toolCalled ?? ($calls[0]['name'] ?? null);
                $chatMessages[] = ['role' => 'assistant', 'content' => $resp['content'] ?? '', 'tool_calls' => $calls];
                foreach ($calls as $call) {
                    $result = $tools->execute((string) $call['name'], is_array($call['arguments'] ?? null) ? $call['arguments'] : [], $userId);
                    $chatMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'] ?? '',
                        'content' => json_encode($result),
                    ];
                }
                $hop++;
            }
        } catch (\Throwable $e) {
            $finalContent = '[error: ' . $e->getMessage() . ']';
        }

        return [
            'id' => $q['id'] ?? '?',
            'prompt' => $message,
            'tool_called' => $toolCalled,
            'expected_tool' => $q['expected_tool'] ?? null,
            'expected_tools' => $q['expected_tools'] ?? null,
            'answer' => $finalContent,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private function judge(array $row, string $criteria, string $judgeModel): array
    {
        $expectedOk = $this->expectedToolMet($row);

        if ($criteria === '') {
            return ['pass' => $expectedOk, 'reason' => 'no criteria — tool selection only', 'score' => $expectedOk ? 1 : 0];
        }

        $prompt = <<<TXT
You are scoring an AI chat assistant's response.

User question: {$row['prompt']}
Assistant answer: {$row['answer']}
Tool the assistant called (if any): {$row['tool_called']}
Pass criteria: {$criteria}

Reply with strictly valid JSON only: {"pass": true|false, "score": 0-1, "reason": "short explanation"}.
TXT;

        try {
            $resp = AIServiceFactory::chatWithFallback([
                ['role' => 'system', 'content' => 'You are a strict eval judge. Reply with JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ], ['model' => $judgeModel, 'temperature' => 0.0, 'max_tokens' => 200]);
            $content = (string) ($resp['content'] ?? '');
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
            $parsed = json_decode($content, true);
            if (!is_array($parsed)) {
                return ['pass' => false, 'reason' => 'judge returned unparseable response', 'score' => 0];
            }
            $pass = (bool) ($parsed['pass'] ?? false) && $expectedOk;
            return [
                'pass' => $pass,
                'reason' => (string) ($parsed['reason'] ?? ''),
                'score' => (float) ($parsed['score'] ?? 0),
                'tool_ok' => $expectedOk,
            ];
        } catch (\Throwable $e) {
            return ['pass' => false, 'reason' => 'judge call failed: ' . $e->getMessage(), 'score' => 0];
        }
    }

    private function expectedToolMet(array $row): bool
    {
        $called = $row['tool_called'];
        $expected = $row['expected_tool'];
        $expectedAny = $row['expected_tools'];
        if ($expected === null && empty($expectedAny)) {
            return true; // no expectation
        }
        if ($expected !== null && $called === $expected) {
            return true;
        }
        if (is_array($expectedAny) && in_array($called, $expectedAny, true)) {
            return true;
        }
        // If expected is "null" (no tool should be called) and called is null
        if (array_key_exists('expected_tool', $row) && $row['expected_tool'] === null && empty($expectedAny) && $called === null) {
            return true;
        }
        return false;
    }

    private function fmtExpected(array $q): string
    {
        if (array_key_exists('expected_tool', $q) && $q['expected_tool'] !== null) {
            return $q['expected_tool'];
        }
        if (!empty($q['expected_tools'])) {
            return 'one of [' . implode(',', $q['expected_tools']) . ']';
        }
        return '∅';
    }

    private function summary(array $results): void
    {
        $total = count($results);
        $passes = count(array_filter($results, fn ($r) => $r['judge_verdict']['pass'] ?? false));
        $avgLatency = $total > 0 ? array_sum(array_column($results, 'latency_ms')) / $total : 0;
        $this->info('');
        $this->info(sprintf('Summary: %d/%d passed (%.0f%%), avg latency %dms', $passes, $total, $total > 0 ? 100 * $passes / $total : 0, $avgLatency));

        $byPrefix = [];
        foreach ($results as $r) {
            $prefix = explode('-', (string) $r['id'])[0] ?? '?';
            $byPrefix[$prefix][] = $r['judge_verdict']['pass'] ?? false;
        }
        ksort($byPrefix);
        $this->info('By category:');
        foreach ($byPrefix as $prefix => $passList) {
            $p = count(array_filter($passList));
            $n = count($passList);
            $this->line(sprintf('  %-14s %d/%d', $prefix, $p, $n));
        }
    }
}
