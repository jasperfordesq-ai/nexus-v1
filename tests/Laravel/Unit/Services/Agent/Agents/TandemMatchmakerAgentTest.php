<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\Agent\Agents;

use App\Core\TenantContext;
use App\Services\Agent\Agents\TandemMatchmakerAgent;
use App\Services\CaringTandemMatchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for TandemMatchmakerAgent (AG61 tandem matchmaker).
 *
 * The agent delegates candidate-pair discovery to CaringTandemMatchingService::suggestTandems()
 * and calls the LLM to generate a "why this pair" reasoning string for each proposal.
 *
 * We stub CaringTandemMatchingService via app()->bind() so tests never need to seed
 * the complex multi-table caring_* fixture set. The LLM is stubbed via Http::fake().
 */
class TandemMatchmakerAgentTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        putenv('OPENAI_API_KEY=');
    }

    protected function tearDown(): void
    {
        putenv('OPENAI_API_KEY=');
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAgent(array $config = [], int $tenantId = self::TENANT_ID): TandemMatchmakerAgent
    {
        $runId = DB::table('agent_runs')->insertGetId([
            'tenant_id'           => $tenantId,
            'agent_type'          => 'tandem_matching',
            'status'              => 'running',
            'triggered_by'        => 'schedule',
            'proposals_generated' => 0,
            'proposals_applied'   => 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        return new TandemMatchmakerAgent($tenantId, $runId, 0, $config);
    }

    /**
     * Build a minimal suggestTandems()-style pair array.
     *
     * @return array<string,mixed>
     */
    private function makePair(int $supporterId, int $recipientId, float $score = 0.75): array
    {
        return [
            'supporter' => ['id' => $supporterId, 'name' => 'Alice'],
            'recipient' => ['id' => $recipientId, 'name' => 'Bob'],
            'score'     => $score,
            'signals'   => ['shared_language' => true],
            'reason'    => 'Good match by profile.',
        ];
    }

    /**
     * Bind a mock CaringTandemMatchingService that returns $suggestions.
     *
     * @param list<array<string,mixed>> $suggestions
     */
    private function bindMatchingService(array $suggestions): void
    {
        $mock = Mockery::mock(CaringTandemMatchingService::class);
        $mock->shouldReceive('suggestTandems')
             ->once()
             ->andReturn($suggestions);

        app()->bind(CaringTandemMatchingService::class, fn () => $mock);
    }

    private function insertUser(string $name = 'Test'): int
    {
        $uid = uniqid('tm_', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => $name . ' ' . $uid,
            'first_name' => $name,
            'last_name'  => 'User',
            'email'      => $uid . '@example.test',
            'role'       => 'member',
            'status'     => 'active',
            'balance'    => 0,
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    public function test_agent_is_instantiable(): void
    {
        $agent = $this->makeAgent();
        $this->assertInstanceOf(TandemMatchmakerAgent::class, $agent);
    }

    public function test_run_returns_required_keys(): void
    {
        $this->bindMatchingService([]);
        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertArrayHasKey('proposals_created', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('llm_input_tokens', $result);
        $this->assertArrayHasKey('llm_output_tokens', $result);
        $this->assertArrayHasKey('cost_cents', $result);
    }

    // -------------------------------------------------------------------------
    // No suggestions from matching service
    // -------------------------------------------------------------------------

    public function test_zero_proposals_when_matching_service_returns_empty(): void
    {
        $this->bindMatchingService([]);
        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertSame(0, $result['proposals_created']);
        $this->assertStringContainsString('0', $result['summary']);
    }

    // -------------------------------------------------------------------------
    // Score filtering
    // -------------------------------------------------------------------------

    public function test_pairs_below_min_score_are_skipped(): void
    {
        $s = $this->insertUser('Supporter');
        $r = $this->insertUser('Recipient');

        // Score 0.3 < default min_score 0.4 → skipped.
        $this->bindMatchingService([$this->makePair($s, $r, 0.3)]);

        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertSame(0, $result['proposals_created']);
    }

    public function test_pairs_above_min_score_produce_proposals(): void
    {
        putenv('OPENAI_API_KEY=');

        $s = $this->insertUser('Supporter');
        $r = $this->insertUser('Recipient');

        $this->bindMatchingService([$this->makePair($s, $r, 0.8)]);

        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertSame(1, $result['proposals_created']);
    }

    // -------------------------------------------------------------------------
    // Proposal row shape
    // -------------------------------------------------------------------------

    public function test_proposal_has_correct_type_and_status(): void
    {
        $s = $this->insertUser('Sup');
        $r = $this->insertUser('Rec');

        $this->bindMatchingService([$this->makePair($s, $r, 0.9)]);

        $agent = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $s)
            ->where('target_user_id', $r)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $this->assertSame('create_tandem', $proposal->proposal_type);
        $this->assertSame('pending_review', $proposal->status);
    }

    public function test_proposal_data_contains_supporter_and_recipient_ids(): void
    {
        $s = $this->insertUser('Sup2');
        $r = $this->insertUser('Rec2');

        $this->bindMatchingService([$this->makePair($s, $r, 0.85)]);

        $agent = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $s)
            ->where('target_user_id', $r)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $data = json_decode($proposal->proposal_data, true);
        $this->assertSame($s, $data['supporter_id']);
        $this->assertSame($r, $data['recipient_id']);
    }

    public function test_confidence_score_matches_pair_score(): void
    {
        $s = $this->insertUser('Sup3');
        $r = $this->insertUser('Rec3');

        $this->bindMatchingService([$this->makePair($s, $r, 0.72)]);

        $agent = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $s)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $this->assertEqualsWithDelta(0.72, (float) $proposal->confidence_score, 0.001);
    }

    // -------------------------------------------------------------------------
    // Fallback reasoning when LLM unavailable (no API key)
    // -------------------------------------------------------------------------

    public function test_fallback_reason_used_when_no_api_key(): void
    {
        // NOTE: OpenAIProvider uses raw curl_exec, not Laravel's Http facade.
        // Http::fake does not intercept curl. When OPENAI_API_KEY is absent,
        // BaseAgent::callLlm() returns '' immediately, and generateReasoning()
        // uses the pair's 'reason' string as the fallback.
        putenv('OPENAI_API_KEY=');

        $s = $this->insertUser('SupFb');
        $r = $this->insertUser('RecFb');

        $pair = $this->makePair($s, $r, 0.6);
        $pair['reason'] = 'High compatibility based on profile signals.';

        $this->bindMatchingService([$pair]);

        $agent = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $s)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $this->assertSame('High compatibility based on profile signals.', $proposal->reasoning);
    }

    // -------------------------------------------------------------------------
    // Reasoning is non-empty even when pair has no 'reason' key
    // -------------------------------------------------------------------------

    public function test_generic_fallback_reasoning_used_when_pair_has_no_reason_key(): void
    {
        putenv('OPENAI_API_KEY=');

        $s = $this->insertUser('SupNoReason');
        $r = $this->insertUser('RecNoReason');

        $pair = $this->makePair($s, $r, 0.65);
        unset($pair['reason']); // no 'reason' key → uses generic string in generateReasoning

        $this->bindMatchingService([$pair]);

        $agent = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $s)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        // The hardcoded fallback in generateReasoning: 'High compatibility based on profile signals.'
        $this->assertNotEmpty($proposal->reasoning);
        $this->assertSame('High compatibility based on profile signals.', $proposal->reasoning);
    }

    // -------------------------------------------------------------------------
    // Custom min_score config
    // -------------------------------------------------------------------------

    public function test_custom_min_score_config_is_respected(): void
    {
        $s = $this->insertUser('SupCfg');
        $r = $this->insertUser('RecCfg');

        // Score 0.5, but we raise min_score to 0.6 → should be skipped.
        $this->bindMatchingService([$this->makePair($s, $r, 0.5)]);

        $agent  = $this->makeAgent(['min_score' => 0.6]);
        $result = $agent->run();

        $this->assertSame(0, $result['proposals_created']);
    }

    // -------------------------------------------------------------------------
    // Summary
    // -------------------------------------------------------------------------

    public function test_summary_mentions_proposals_created(): void
    {
        $s = $this->insertUser('SupSum');
        $r = $this->insertUser('RecSum');

        $this->bindMatchingService([$this->makePair($s, $r, 0.9)]);

        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertStringContainsString('1', $result['summary']);
    }
}
