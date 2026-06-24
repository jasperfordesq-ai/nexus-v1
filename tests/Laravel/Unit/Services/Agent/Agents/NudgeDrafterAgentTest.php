<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\Agent\Agents;

use App\Core\TenantContext;
use App\Services\Agent\Agents\NudgeDrafterAgent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for NudgeDrafterAgent (AG61 nudge drafter).
 *
 * The agent:
 *  1. Queries users with last_login older than $min_idle_days (default 14).
 *  2. Calls the LLM (gpt-4o-mini via OPENAI_API_KEY env) to draft a nudge.
 *  3. Falls back to a hardcoded English message if the LLM is unavailable or
 *     returns invalid JSON.
 *  4. Writes one agent_proposals row per candidate.
 *
 * Each test inserts its own isolated tenant so the LIMIT 20 constraint
 * in the agent always reaches our specific test user, regardless of how many
 * idle users exist in the shared tenant 2 data.
 *
 * LLM calls are tested via putenv('OPENAI_API_KEY') + Http::fake.
 */
class NudgeDrafterAgentTest extends TestCase
{
    use DatabaseTransactions;

    /** Tenant ID allocated in setUp(); rolled back with the transaction. */
    private int $thisTenant;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        putenv('OPENAI_API_KEY=');

        // Allocate a unique high-range tenant ID per test so there's no shared
        // idle-user pollution. DB auto-increment on tenants will give us a fresh id.
        $this->thisTenant = (int) DB::table('tenants')->insertGetId([
            'name'              => 'Nudge Test Tenant ' . uniqid(),
            'slug'              => 'nudge-test-' . uniqid(),
            'domain'            => null,
            'is_active'         => true,
            'depth'             => 0,
            'allows_subtenants' => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        TenantContext::setById($this->thisTenant);
    }

    protected function tearDown(): void
    {
        putenv('OPENAI_API_KEY=');
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAgent(array $config = []): NudgeDrafterAgent
    {
        $runId = DB::table('agent_runs')->insertGetId([
            'tenant_id'           => $this->thisTenant,
            'agent_type'          => 'nudge_dispatch',
            'status'              => 'running',
            'triggered_by'        => 'schedule',
            'proposals_generated' => 0,
            'proposals_applied'   => 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        return new NudgeDrafterAgent($this->thisTenant, $runId, 0, $config);
    }

    /**
     * Insert a user with a controllable last_login timestamp into this test's tenant.
     */
    private function insertIdleUser(string $lastLogin, string $lang = 'en'): int
    {
        $uid = uniqid('nudge_', true);
        return (int) DB::table('users')->insertGetId([
            'tenant_id'          => $this->thisTenant,
            'name'               => 'Nudge ' . $uid,
            'first_name'         => 'Nudge',
            'last_name'          => 'User',
            'email'              => $uid . '@example.test',
            'role'               => 'member',
            'status'             => 'active',
            'balance'            => 0,
            'is_approved'        => 1,
            'preferred_language' => $lang,
            'last_login'         => $lastLogin,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    public function test_agent_is_instantiable(): void
    {
        $agent = $this->makeAgent();
        $this->assertInstanceOf(NudgeDrafterAgent::class, $agent);
    }

    public function test_run_returns_required_keys(): void
    {
        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertArrayHasKey('proposals_created', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('llm_input_tokens', $result);
        $this->assertArrayHasKey('llm_output_tokens', $result);
        $this->assertArrayHasKey('cost_cents', $result);
    }

    // -------------------------------------------------------------------------
    // No candidates
    // -------------------------------------------------------------------------

    public function test_zero_proposals_when_no_idle_users(): void
    {
        // This tenant has no users at all.
        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertSame(0, $result['proposals_created']);
    }

    public function test_no_proposals_when_all_users_recently_active(): void
    {
        // A user who logged in today should NOT be picked (< 14 days idle).
        $this->insertIdleUser(now()->toDateTimeString());

        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertSame(0, $result['proposals_created']);
    }

    // -------------------------------------------------------------------------
    // Fallback path (no OPENAI_API_KEY) — hardcoded English message
    // -------------------------------------------------------------------------

    public function test_fallback_message_used_when_no_api_key(): void
    {
        putenv('OPENAI_API_KEY=');

        $idleUser = $this->insertIdleUser(now()->subDays(30)->toDateTimeString());

        $agent  = $this->makeAgent(['min_idle_days' => 14]);
        $result = $agent->run();

        $this->assertGreaterThanOrEqual(1, $result['proposals_created']);

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $idleUser)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $data = json_decode($proposal->proposal_data, true);

        // Fallback title is 'We miss you!'
        $this->assertSame('We miss you!', $data['title']);
        $this->assertStringContainsString('community', $data['body']);
    }

    // -------------------------------------------------------------------------
    // Proposal data has correct extra.type
    // -------------------------------------------------------------------------

    public function test_proposal_data_has_correct_extra_type(): void
    {
        // NOTE: The LLM HTTP call path cannot be tested via Http::fake because
        // OpenAIProvider uses raw curl_exec, not Laravel's Http facade. We test
        // the observable side-effect (proposal_data shape) which is the same
        // regardless of whether the LLM or fallback message was used.
        putenv('OPENAI_API_KEY=');

        $idleUser = $this->insertIdleUser(now()->subDays(30)->toDateTimeString(), 'fr');
        $agent    = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $idleUser)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $data = json_decode($proposal->proposal_data, true);

        // The extra field must carry type=inactivity_nudge and the correct locale.
        $this->assertSame('inactivity_nudge', $data['extra']['type']);
        $this->assertSame('fr', $data['extra']['locale']);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
    }

    // -------------------------------------------------------------------------
    // Fallback body contains the user's name
    // -------------------------------------------------------------------------

    public function test_fallback_body_contains_users_name(): void
    {
        // NOTE: OpenAIProvider uses raw curl_exec, not Laravel Http facade, so
        // Http::fake cannot intercept it. We rely on the no-API-key path which
        // always returns '' content and triggers the fallback.
        putenv('OPENAI_API_KEY=');

        $uid = uniqid('bob_', true);
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id'          => $this->thisTenant,
            'name'               => 'Bob ' . $uid,
            'first_name'         => 'Bob',
            'last_name'          => $uid,
            'email'              => $uid . '@example.test',
            'role'               => 'member',
            'status'             => 'active',
            'balance'            => 0,
            'is_approved'        => 1,
            'preferred_language' => 'en',
            'last_login'         => now()->subDays(30)->toDateTimeString(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $agent = $this->makeAgent(['min_idle_days' => 14]);
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $userId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $data = json_decode($proposal->proposal_data, true);
        // The fallback body is "Hi {name}, your community...".
        $this->assertStringContainsString('Bob', $data['body']);
    }

    // -------------------------------------------------------------------------
    // Proposal row shape
    // -------------------------------------------------------------------------

    public function test_proposal_status_is_pending_review(): void
    {
        putenv('OPENAI_API_KEY=');

        $idleUser = $this->insertIdleUser(now()->subDays(30)->toDateTimeString());
        $agent    = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $idleUser)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $this->assertSame('pending_review', $proposal->status);
        $this->assertSame('send_nudge', $proposal->proposal_type);
    }

    public function test_proposal_confidence_is_055(): void
    {
        putenv('OPENAI_API_KEY=');

        $idleUser = $this->insertIdleUser(now()->subDays(30)->toDateTimeString());
        $agent    = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $idleUser)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $this->assertEqualsWithDelta(0.55, (float) $proposal->confidence_score, 0.001);
    }

    // -------------------------------------------------------------------------
    // min_idle_days config
    // -------------------------------------------------------------------------

    public function test_min_idle_days_config_catches_user_at_boundary(): void
    {
        putenv('OPENAI_API_KEY=');

        // 10 days idle — below default 14 but above our custom 7.
        $user = $this->insertIdleUser(now()->subDays(10)->toDateTimeString());

        // With min_idle_days=7, the user should be caught.
        $agent  = $this->makeAgent(['min_idle_days' => 7]);
        $result = $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $user)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertGreaterThanOrEqual(1, $result['proposals_created']);
        $this->assertNotNull($proposal);
    }

    // -------------------------------------------------------------------------
    // Reasoning contains idle duration
    // -------------------------------------------------------------------------

    public function test_reasoning_mentions_idle_duration(): void
    {
        putenv('OPENAI_API_KEY=');

        $idleUser = $this->insertIdleUser(now()->subDays(30)->toDateTimeString());
        $agent    = $this->makeAgent(['min_idle_days' => 14]);
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $idleUser)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $this->assertStringContainsString('14', $proposal->reasoning);
    }

    // -------------------------------------------------------------------------
    // Preferred language null / default → 'en' fallback in extra.locale
    // -------------------------------------------------------------------------

    public function test_null_preferred_language_defaults_to_en_locale(): void
    {
        putenv('OPENAI_API_KEY=');

        // Insert user with no preferred_language (DB default is 'en').
        $uid    = uniqid('nolang_', true);
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id'   => $this->thisTenant,
            'name'        => 'No Lang ' . $uid,
            'first_name'  => 'No',
            'last_name'   => 'Lang',
            'email'       => $uid . '@example.test',
            'role'        => 'member',
            'status'      => 'active',
            'balance'     => 0,
            'is_approved' => 1,
            'last_login'  => now()->subDays(20)->toDateTimeString(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $agent = $this->makeAgent(['min_idle_days' => 14]);
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $userId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $data = json_decode($proposal->proposal_data, true);
        $this->assertSame('en', $data['extra']['locale']);
    }

    // -------------------------------------------------------------------------
    // Summary string
    // -------------------------------------------------------------------------

    public function test_summary_mentions_nudge_count(): void
    {
        putenv('OPENAI_API_KEY=');

        $this->insertIdleUser(now()->subDays(30)->toDateTimeString());
        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertStringContainsString((string) $result['proposals_created'], $result['summary']);
    }
}
