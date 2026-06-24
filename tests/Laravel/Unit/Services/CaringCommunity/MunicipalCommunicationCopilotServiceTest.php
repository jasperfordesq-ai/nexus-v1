<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\CaringCommunity;

use App\Services\CaringCommunity\MunicipalCommunicationCopilotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * MunicipalCommunicationCopilotServiceTest
 *
 * Strategy: The service is storage-only (rolling JSON in tenant_settings) plus
 * an optional LLM call. All tests use DatabaseTransactions so they roll back.
 *
 * Coverage plan:
 *   (a) Constants / enum values.
 *   (b) generateProposal — no-API-key fallback (stub path, deterministic).
 *   (c) generateProposal — Http::fake AI path, response parsing.
 *   (d) generateProposal — invalid LLM enum values are normalised.
 *   (e) generateProposal — HTTP 500 from OpenAI → fallback to stub.
 *   (f) listProposals — empty tenant, limit clamping.
 *   (g) getProposal — found / not-found.
 *   (h) acceptProposal — status transition, editedFields applied.
 *   (i) rejectProposal — status transition + rejection_reason stored.
 *   (j) markPublished — status transition + source_announcement_id stored.
 *   (k) MAX_PROPOSALS cap — oldest proposals are evicted.
 *   (l) acceptAndPublish idempotency — already-published returns unchanged.
 *   (m) tenancy isolation — tenant A cannot see tenant B proposals.
 *   (n) publishAcceptedProposal — non-accepted proposals are returned untouched.
 */
class MunicipalCommunicationCopilotServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;
    private const ADMIN_ID  = 1;

    private MunicipalCommunicationCopilotService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->svc = new MunicipalCommunicationCopilotService();

        // Start each test with a clean slate for this tenant's copilot key.
        DB::table('tenant_settings')
            ->where('tenant_id', self::TENANT_ID)
            ->where('setting_key', MunicipalCommunicationCopilotService::SETTING_KEY)
            ->delete();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Inject a fake OPENAI_API_KEY into the Laravel env system.
     *
     * Laravel's Env uses a MultiReader that checks $_SERVER (ServerConstAdapter)
     * FIRST, then $_ENV. The .env.testing file has OPENAI_API_KEY= (empty), so
     * both superglobals carry ''. We must override both and reset the static
     * repository (Env::enablePutenv() nulls the cached instance so env()
     * rebuilds on the next call). Returns a callable to restore the prior state.
     */
    private function injectOpenAiKey(string $key = 'sk-test-key'): \Closure
    {
        $priorServer = $_SERVER['OPENAI_API_KEY'] ?? null;
        $priorEnv    = $_ENV['OPENAI_API_KEY'] ?? null;
        $_SERVER['OPENAI_API_KEY'] = $key;
        $_ENV['OPENAI_API_KEY']    = $key;
        \Illuminate\Support\Env::enablePutenv(); // reset static repo

        return function () use ($priorServer, $priorEnv): void {
            if ($priorServer === null) {
                unset($_SERVER['OPENAI_API_KEY']);
            } else {
                $_SERVER['OPENAI_API_KEY'] = $priorServer;
            }
            if ($priorEnv === null) {
                unset($_ENV['OPENAI_API_KEY']);
            } else {
                $_ENV['OPENAI_API_KEY'] = $priorEnv;
            }
            \Illuminate\Support\Env::enablePutenv();
        };
    }

    /**
     * Register a fake OpenAI chat-completions response.
     * The service expects choices[0].message.content to be a JSON string.
     *
     * @param array<string, mixed> $payload
     */
    private function fakeOpenAi(array $payload): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode($payload),
                    ],
                ]],
            ], 200),
        ]);
    }

    /**
     * Generate a proposal via the stub (no API key).
     *
     * @return array<string, mixed>
     */
    private function makeProposal(string $draft = 'Hello community!'): array
    {
        // Ensure no API key so we always use the deterministic stub in helpers.
        putenv('OPENAI_API_KEY=');
        return $this->svc->generateProposal(
            self::TENANT_ID,
            self::ADMIN_ID,
            $draft,
            null,
            null,
        );
    }

    // =========================================================================
    // (a) Constants / enums
    // =========================================================================

    public function test_constants_are_well_formed(): void
    {
        $this->assertSame('caring.municipal_copilot.proposals', MunicipalCommunicationCopilotService::SETTING_KEY);
        $this->assertSame(50, MunicipalCommunicationCopilotService::MAX_PROPOSALS);
        $this->assertContains('proposed', MunicipalCommunicationCopilotService::STATUSES);
        $this->assertContains('accepted', MunicipalCommunicationCopilotService::STATUSES);
        $this->assertContains('rejected', MunicipalCommunicationCopilotService::STATUSES);
        $this->assertContains('published', MunicipalCommunicationCopilotService::STATUSES);
        $this->assertContains('ok', MunicipalCommunicationCopilotService::TONE_VALUES);
        $this->assertContains('all_members', MunicipalCommunicationCopilotService::AUDIENCES);
        $this->assertContains('volunteers', MunicipalCommunicationCopilotService::AUDIENCES);
        $this->assertCount(4, MunicipalCommunicationCopilotService::TONE_VALUES);
        $this->assertCount(7, MunicipalCommunicationCopilotService::AUDIENCES);
    }

    // =========================================================================
    // (b) generateProposal — stub path (no API key)
    // =========================================================================

    public function test_generate_proposal_stub_returns_expected_shape(): void
    {
        putenv('OPENAI_API_KEY=');
        Http::fake(); // nothing should be sent

        $draft = 'Dear residents, the community garden opens Monday.';
        $proposal = $this->svc->generateProposal(self::TENANT_ID, self::ADMIN_ID, $draft, 'volunteers', null);

        // Shape
        $this->assertArrayHasKey('id', $proposal);
        $this->assertStringStartsWith('prop_', $proposal['id']);
        $this->assertSame($draft, $proposal['draft_text']);
        $this->assertSame($draft, $proposal['polished_text']); // stub returns draft unchanged
        $this->assertSame('ok', $proposal['tone_assessment']);
        $this->assertIsArray($proposal['clarity_warnings']);
        $this->assertIsArray($proposal['moderation_flags']);
        $this->assertSame('stub', $proposal['model_used']);
        $this->assertSame('proposed', $proposal['status']);
        $this->assertNull($proposal['accepted_at']);
        $this->assertNull($proposal['rejected_at']);
        $this->assertNull($proposal['source_announcement_id']);
        $this->assertSame(self::ADMIN_ID, $proposal['created_by']);

        // NOTE: stub always returns 'all_members' explicitly — the audienceHint
        // fallback only fires when $analysis['audience_suggestion'] is absent/null.
        // The stub path populates it with 'all_members', so the hint is not used.
        $this->assertSame('all_members', $proposal['audience_suggestion']);

        Http::assertNothingSent();
    }

    public function test_generate_proposal_persists_to_tenant_settings(): void
    {
        $this->makeProposal('Test persistence');

        $row = DB::table('tenant_settings')
            ->where('tenant_id', self::TENANT_ID)
            ->where('setting_key', MunicipalCommunicationCopilotService::SETTING_KEY)
            ->first();

        $this->assertNotNull($row);
        $decoded = json_decode((string) $row->setting_value, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('items', $decoded);
        $this->assertCount(1, $decoded['items']);
        $this->assertSame('Test persistence', $decoded['items'][0]['draft_text']);
    }

    public function test_audience_hint_null_falls_back_to_all_members(): void
    {
        putenv('OPENAI_API_KEY=');
        $proposal = $this->svc->generateProposal(self::TENANT_ID, self::ADMIN_ID, 'Hi', null, null);

        $this->assertSame('all_members', $proposal['audience_suggestion']);
    }

    // =========================================================================
    // (c) generateProposal — Http::fake AI path, response parsing
    // =========================================================================

    public function test_generate_proposal_uses_ai_response_when_key_present(): void
    {
        $restore = $this->injectOpenAiKey();

        $this->fakeOpenAi([
            'polished_text'       => 'Dear community, the garden opens Monday at 9 am.',
            'tone_assessment'     => 'ok',
            'clarity_warnings'    => ['Missing closing time'],
            'audience_suggestion' => 'volunteers',
            'moderation_flags'    => [],
        ]);

        try {
            $proposal = $this->svc->generateProposal(
                self::TENANT_ID,
                self::ADMIN_ID,
                'garden opens monday',
                null,
                null,
            );

            $this->assertSame('Dear community, the garden opens Monday at 9 am.', $proposal['polished_text']);
            $this->assertSame('ok', $proposal['tone_assessment']);
            $this->assertSame(['Missing closing time'], $proposal['clarity_warnings']);
            $this->assertSame('volunteers', $proposal['audience_suggestion']);
            $this->assertSame('gpt-4o-mini', $proposal['model_used']);

            Http::assertSentCount(1);
        } finally {
            $restore();
        }
    }

    // =========================================================================
    // (d) generateProposal — invalid LLM enum values are normalised
    // =========================================================================

    public function test_invalid_tone_and_audience_from_ai_are_normalised(): void
    {
        $restore = $this->injectOpenAiKey();

        $this->fakeOpenAi([
            'polished_text'       => 'Good morning.',
            'tone_assessment'     => 'aggressive', // not in TONE_VALUES
            'clarity_warnings'    => [],
            'audience_suggestion' => 'aliens',     // not in AUDIENCES
            'moderation_flags'    => [],
        ]);

        try {
            $proposal = $this->svc->generateProposal(
                self::TENANT_ID,
                self::ADMIN_ID,
                'Good morning.',
                null,
                null,
            );

            $this->assertSame('ok', $proposal['tone_assessment']);              // normalised
            $this->assertSame('all_members', $proposal['audience_suggestion']); // normalised
        } finally {
            $restore();
        }
    }

    // =========================================================================
    // (e) generateProposal — HTTP 500 from OpenAI → fallback to stub
    // =========================================================================

    public function test_openai_500_falls_back_to_stub(): void
    {
        $restore = $this->injectOpenAiKey();

        Http::fake([
            'api.openai.com/*' => Http::response('Internal Server Error', 500),
        ]);

        try {
            $draft = 'Announcement during outage.';
            $proposal = $this->svc->generateProposal(
                self::TENANT_ID,
                self::ADMIN_ID,
                $draft,
                null,
                null,
            );

            // Should fall back: polished_text = draft, model_used = stub
            $this->assertSame($draft, $proposal['polished_text']);
            $this->assertSame('stub', $proposal['model_used']);
            $this->assertSame('proposed', $proposal['status']);
        } finally {
            $restore();
        }
    }

    // =========================================================================
    // (f) listProposals — empty tenant, limit clamping
    // =========================================================================

    public function test_list_proposals_returns_empty_for_fresh_tenant(): void
    {
        $result = $this->svc->listProposals(self::TENANT_ID);
        $this->assertSame([], $result);
    }

    public function test_list_proposals_returns_newest_first(): void
    {
        $this->makeProposal('First draft');
        $this->makeProposal('Second draft');
        $this->makeProposal('Third draft');

        $items = $this->svc->listProposals(self::TENANT_ID);
        $this->assertCount(3, $items);
        // generateProposal prepends — newest is first
        $this->assertSame('Third draft', $items[0]['draft_text']);
        $this->assertSame('Second draft', $items[1]['draft_text']);
        $this->assertSame('First draft', $items[2]['draft_text']);
    }

    public function test_list_proposals_limit_is_respected(): void
    {
        $this->makeProposal('A');
        $this->makeProposal('B');
        $this->makeProposal('C');

        $items = $this->svc->listProposals(self::TENANT_ID, 2);
        $this->assertCount(2, $items);
    }

    public function test_list_proposals_limit_zero_is_clamped_to_one(): void
    {
        $this->makeProposal('Only');

        $items = $this->svc->listProposals(self::TENANT_ID, 0);
        $this->assertCount(1, $items);
    }

    // =========================================================================
    // (g) getProposal — found / not-found
    // =========================================================================

    public function test_get_proposal_returns_null_when_not_found(): void
    {
        $result = $this->svc->getProposal(self::TENANT_ID, 'prop_doesnotexist');
        $this->assertNull($result);
    }

    public function test_get_proposal_returns_the_correct_proposal(): void
    {
        $p1 = $this->makeProposal('Alpha');
        $p2 = $this->makeProposal('Beta');

        $found = $this->svc->getProposal(self::TENANT_ID, $p1['id']);

        $this->assertNotNull($found);
        $this->assertSame($p1['id'], $found['id']);
        $this->assertSame('Alpha', $found['draft_text']);
        // Confirm p2 is not returned
        $this->assertNotSame($p2['id'], $found['id']);
    }

    // =========================================================================
    // (h) acceptProposal — status transition, editedFields applied
    // =========================================================================

    public function test_accept_proposal_transitions_status_to_accepted(): void
    {
        $p = $this->makeProposal('Please come to the meeting.');

        $accepted = $this->svc->acceptProposal(self::TENANT_ID, $p['id'], null, self::ADMIN_ID);

        $this->assertNotNull($accepted);
        $this->assertSame('accepted', $accepted['status']);
        $this->assertNotNull($accepted['accepted_at']);
        $this->assertNull($accepted['rejected_at']);
        $this->assertSame(self::ADMIN_ID, $accepted['accepted_by']);

        // Persisted
        $reloaded = $this->svc->getProposal(self::TENANT_ID, $p['id']);
        $this->assertSame('accepted', $reloaded['status'] ?? null);
    }

    public function test_accept_proposal_with_edited_fields_overrides_polished_text_and_audience(): void
    {
        $p = $this->makeProposal('Original text');

        $accepted = $this->svc->acceptProposal(
            self::TENANT_ID,
            $p['id'],
            [
                'edited_polished_text' => 'Revised text for the community.',
                'edited_audience'      => 'caregivers',
            ],
            self::ADMIN_ID,
        );

        $this->assertNotNull($accepted);
        $this->assertSame('Revised text for the community.', $accepted['polished_text']);
        $this->assertSame('caregivers', $accepted['audience_suggestion']);
    }

    public function test_accept_proposal_returns_null_for_unknown_id(): void
    {
        $result = $this->svc->acceptProposal(self::TENANT_ID, 'prop_ghost', null, self::ADMIN_ID);
        $this->assertNull($result);
    }

    // =========================================================================
    // (i) rejectProposal — status transition + rejection_reason stored
    // =========================================================================

    public function test_reject_proposal_transitions_status_and_stores_reason(): void
    {
        $p = $this->makeProposal('Event cancelled due to weather.');

        $rejected = $this->svc->rejectProposal(
            self::TENANT_ID,
            $p['id'],
            'Too alarmist — needs a gentler rewrite.',
            self::ADMIN_ID,
        );

        $this->assertNotNull($rejected);
        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame('Too alarmist — needs a gentler rewrite.', $rejected['rejection_reason']);
        $this->assertNotNull($rejected['rejected_at']);
        $this->assertNull($rejected['accepted_at']);

        // Persisted
        $reloaded = $this->svc->getProposal(self::TENANT_ID, $p['id']);
        $this->assertSame('rejected', $reloaded['status'] ?? null);
    }

    public function test_reject_proposal_returns_null_for_unknown_id(): void
    {
        $result = $this->svc->rejectProposal(self::TENANT_ID, 'prop_nope', 'reason', self::ADMIN_ID);
        $this->assertNull($result);
    }

    // =========================================================================
    // (j) markPublished — status transition + source_announcement_id stored
    // =========================================================================

    public function test_mark_published_stamps_proposal_correctly(): void
    {
        $p = $this->makeProposal('Announcement ready to go live.');
        $this->svc->acceptProposal(self::TENANT_ID, $p['id'], null, self::ADMIN_ID);

        $published = $this->svc->markPublished(self::TENANT_ID, $p['id'], 42, self::ADMIN_ID);

        $this->assertNotNull($published);
        $this->assertSame('published', $published['status']);
        $this->assertSame(42, $published['source_announcement_id']);
        $this->assertNotNull($published['published_at']);
        $this->assertSame(self::ADMIN_ID, $published['published_by']);

        // Persisted
        $reloaded = $this->svc->getProposal(self::TENANT_ID, $p['id']);
        $this->assertSame('published', $reloaded['status'] ?? null);
        $this->assertSame(42, $reloaded['source_announcement_id'] ?? null);
    }

    public function test_mark_published_returns_null_for_unknown_id(): void
    {
        $result = $this->svc->markPublished(self::TENANT_ID, 'prop_missing', 99);
        $this->assertNull($result);
    }

    // =========================================================================
    // (k) MAX_PROPOSALS cap — oldest proposals are evicted
    // =========================================================================

    public function test_proposals_are_capped_at_max_proposals(): void
    {
        putenv('OPENAI_API_KEY=');
        $max = MunicipalCommunicationCopilotService::MAX_PROPOSALS;

        // Insert exactly MAX + 3 proposals
        for ($i = 1; $i <= $max + 3; $i++) {
            $this->svc->generateProposal(self::TENANT_ID, self::ADMIN_ID, "Draft $i", null, null);
        }

        $items = $this->svc->listProposals(self::TENANT_ID, $max);
        $this->assertCount($max, $items);

        // Newest should be "Draft N+3", oldest kept is "Draft 4"
        $this->assertSame("Draft " . ($max + 3), $items[0]['draft_text']);
        $this->assertSame("Draft 4", $items[$max - 1]['draft_text']);
    }

    // =========================================================================
    // (l) acceptAndPublish idempotency — already-published returns unchanged
    // =========================================================================

    public function test_accept_and_publish_idempotent_for_published_proposal(): void
    {
        $p = $this->makeProposal('Already published proposal.');
        $this->svc->acceptProposal(self::TENANT_ID, $p['id'], null, self::ADMIN_ID);
        $this->svc->markPublished(self::TENANT_ID, $p['id'], 77, self::ADMIN_ID);

        // acceptAndPublish on an already-published proposal must return it unchanged
        $result = $this->svc->acceptAndPublish(self::TENANT_ID, $p['id'], null, self::ADMIN_ID);

        $this->assertNotNull($result);
        $this->assertSame('published', $result['status']);
        $this->assertSame(77, $result['source_announcement_id']);
    }

    public function test_accept_and_publish_returns_null_for_unknown_proposal(): void
    {
        $result = $this->svc->acceptAndPublish(self::TENANT_ID, 'prop_phantom', null, self::ADMIN_ID);
        $this->assertNull($result);
    }

    // =========================================================================
    // (m) Tenancy isolation — tenant A cannot see tenant B proposals
    // =========================================================================

    public function test_tenant_isolation_proposals_not_visible_across_tenants(): void
    {
        $tenantA = self::TENANT_ID;
        $tenantB = 999; // seeded in TestCase::setUpTenantContext

        // Clean slate for tenant B too
        DB::table('tenant_settings')
            ->where('tenant_id', $tenantB)
            ->where('setting_key', MunicipalCommunicationCopilotService::SETTING_KEY)
            ->delete();

        putenv('OPENAI_API_KEY=');

        $p = $this->svc->generateProposal($tenantA, self::ADMIN_ID, 'Tenant A only', null, null);

        // Tenant B should see nothing
        $this->assertSame([], $this->svc->listProposals($tenantB));
        $this->assertNull($this->svc->getProposal($tenantB, $p['id']));
    }

    // =========================================================================
    // (n) publishAcceptedProposal — non-accepted proposals returned untouched
    // =========================================================================

    public function test_publish_accepted_proposal_skips_proposed_status(): void
    {
        $p = $this->makeProposal('Not yet accepted.');
        // status is 'proposed' — publishAcceptedProposal must NOT change it

        $result = $this->svc->publishAcceptedProposal(self::TENANT_ID, $p['id'], self::ADMIN_ID);

        $this->assertNotNull($result);
        $this->assertSame('proposed', $result['status']);
    }

    public function test_publish_accepted_proposal_skips_rejected_status(): void
    {
        $p = $this->makeProposal('Rejected proposal.');
        $this->svc->rejectProposal(self::TENANT_ID, $p['id'], 'no good', self::ADMIN_ID);

        $result = $this->svc->publishAcceptedProposal(self::TENANT_ID, $p['id'], self::ADMIN_ID);

        $this->assertNotNull($result);
        $this->assertSame('rejected', $result['status']);
    }

    public function test_publish_accepted_proposal_returns_null_for_unknown_id(): void
    {
        $result = $this->svc->publishAcceptedProposal(self::TENANT_ID, 'prop_xyz', self::ADMIN_ID);
        $this->assertNull($result);
    }
}
