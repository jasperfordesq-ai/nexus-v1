<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Models\User;
use App\Services\Agent\AgentExecutor;
use App\Services\ContentModerationService;
use App\Services\JobVacancyService;
use App\Services\KiAgentService;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Real-database regression tests for schema-write bugs found in the
 * 2026-06-12 Fable hunt. MariaDB runs strict=false, so writes that
 * reference phantom tables/columns or invalid enum literals either throw
 * (swallowed by catch-alls) or silently truncate to '' — these tests hit
 * the real tables so any drift fails loudly.
 *
 * Covers:
 *  - MatchingService::getPreferences / savePreferences vs the phantom
 *    match_preference_categories table (saved row must not be discarded).
 *  - JobApplicationHistory: table has NO tenant_id column; model must not
 *    use HasTenantScope and inserts must not include tenant_id.
 *  - ContentModerationService event approval: events.status enum is
 *    ('active','cancelled','completed','draft') — 'published' truncated to ''.
 *  - AgentExecutor / KiAgentService tandem creation: enum is
 *    ('active','paused','completed','cancelled') — 'pending' truncated to ''.
 *  - transactions.status enum is ('pending','completed','cancelled') —
 *    the federation compensating-refund literal must stay valid.
 *  - AdminBlogController::bulkPublish: posts has no published_at column.
 */
class SchemaWriteRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $emailPrefix = 'schema'): int
    {
        $email = $emailPrefix . '.' . uniqid() . '@example.test';
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => $email,
            'username'   => 'u_' . substr(md5($email . microtime(true)), 0, 8),
            'password'   => password_hash('password', PASSWORD_BCRYPT),
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =====================================================================
    // FIX 1 — MatchingService vs phantom match_preference_categories table
    // =====================================================================

    public function test_get_preferences_returns_saved_row_not_defaults(): void
    {
        $userId = $this->makeUser('matchprefs');

        DB::table('match_preferences')->insert([
            'user_id'                => $userId,
            'tenant_id'              => $this->testTenantId,
            'max_distance_km'        => 99,
            'min_match_score'        => 70,
            'notification_frequency' => 'never',
            'notify_hot_matches'     => 0,
            'notify_mutual_matches'  => 0,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $prefs = MatchingService::getPreferences($userId);

        // Before the fix the phantom match_preference_categories read threw
        // inside the same try block and the catch returned DEFAULT_PREFERENCES,
        // discarding the row above ('monthly', 25km, opt-ins back on).
        $this->assertSame('never', $prefs['notification_frequency']);
        $this->assertSame(99, $prefs['max_distance_km']);
        $this->assertSame(70, $prefs['min_match_score']);
        $this->assertFalse($prefs['notify_hot_matches']);
        $this->assertFalse($prefs['notify_mutual_matches']);
        $this->assertSame([], $prefs['categories']);
    }

    public function test_save_preferences_with_categories_still_saves_main_row(): void
    {
        $userId = $this->makeUser('matchsave');

        $ok = MatchingService::savePreferences($userId, [
            'notification_frequency' => 'weekly',
            'categories'             => [1, 2],
        ]);

        // The categories sync against the phantom table must not fail the save.
        $this->assertTrue($ok);
        $this->assertSame(
            'weekly',
            DB::table('match_preferences')
                ->where('user_id', $userId)
                ->where('tenant_id', $this->testTenantId)
                ->value('notification_frequency')
        );
    }

    // =====================================================================
    // FIX 2 — job_application_history has no tenant_id column
    // =====================================================================

    public function test_log_application_history_inserts_row(): void
    {
        $ownerId = $this->makeUser('jobowner');
        $applicantId = $this->makeUser('jobapplicant');

        $vacancyId = (int) DB::table('job_vacancies')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $ownerId,
            'title'       => 'Schema Regression Vacancy',
            'description' => 'Test vacancy for history logging.',
            'status'      => 'open',
            'created_at'  => now(),
        ]);

        $applicationId = (int) DB::table('job_vacancy_applications')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'vacancy_id' => $vacancyId,
            'user_id'    => $applicantId,
            'status'     => 'applied',
            'created_at' => now(),
        ]);

        // Private method — invoke directly so the test exercises the exact
        // insert that silently failed (tenant_id phantom column) in prod.
        $service = app(JobVacancyService::class);
        $method = new \ReflectionMethod(JobVacancyService::class, 'logApplicationHistory');
        $method->setAccessible(true);
        $method->invoke($service, $applicationId, 'applied', 'shortlisted', $ownerId, 'regression test');

        $row = DB::table('job_application_history')
            ->where('application_id', $applicationId)
            ->where('to_status', 'shortlisted')
            ->first();

        $this->assertNotNull($row, 'history row was not inserted — phantom tenant_id column regression?');
        $this->assertSame('applied', $row->from_status);
        $this->assertSame('regression test', $row->notes);
    }

    public function test_job_application_history_model_queries_without_tenant_scope(): void
    {
        // HasTenantScope would inject WHERE tenant_id (nonexistent column)
        // and make every read 500. A plain count must not throw.
        $count = \App\Models\JobApplicationHistory::query()->count();
        $this->assertIsInt($count);
    }

    // =====================================================================
    // FIX 3 — event moderation approval must use 'active' (valid enum)
    // =====================================================================

    public function test_event_moderation_approval_sets_status_active(): void
    {
        $authorId = $this->makeUser('eventauthor');
        $adminId = $this->makeUser('eventadmin');
        DB::table('users')->where('id', $adminId)->update(['role' => 'admin']);

        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $authorId,
            'title'       => 'Schema Regression Event',
            'description' => 'Awaiting moderation.',
            'start_time'  => now()->addDay(),
            'status'      => 'draft',
            'publication_status' => 'pending_review',
            'operational_status' => 'scheduled',
            'created_at'  => now(),
        ]);

        $queueId = (int) DB::table('content_moderation_queue')->insertGetId([
            'tenant_id'    => $this->testTenantId,
            'content_type' => 'event',
            'content_id'   => $eventId,
            'author_id'    => $authorId,
            'status'       => 'pending',
            'created_at'   => now(),
        ]);

        $result = ContentModerationService::review($queueId, $this->testTenantId, $adminId, 'approved');

        $this->assertTrue($result['success']);
        // The authoritative publication transition must also maintain the
        // legacy compatibility mirror with the valid enum value `active`.
        $this->assertSame(
            'active',
            DB::table('events')->where('id', $eventId)->value('status')
        );
    }

    // =====================================================================
    // FIX 6 — tandem creation must write 'active' (valid enum)
    // =====================================================================

    public function test_agent_executor_tandem_insert_has_active_status(): void
    {
        $supporterId = $this->makeUser('tandemsup');
        $recipientId = $this->makeUser('tandemrec');

        $method = new \ReflectionMethod(AgentExecutor::class, 'dispatchAction');
        $method->setAccessible(true);
        $method->invoke(null, 'create_tandem', [
            'supporter_id' => $supporterId,
            'recipient_id' => $recipientId,
        ], [], $this->testTenantId);

        $status = DB::table('caring_support_relationships')
            ->where('tenant_id', $this->testTenantId)
            ->where('supporter_id', $supporterId)
            ->where('recipient_id', $recipientId)
            ->value('status');

        // 'pending' is not in the enum ('active','paused','completed','cancelled')
        // and truncated to '' — making approved tandems invisible everywhere.
        $this->assertSame('active', $status);
    }

    public function test_ki_agent_apply_proposal_tandem_insert_has_active_status(): void
    {
        $supporterId = $this->makeUser('kitandemsup');
        $recipientId = $this->makeUser('kitandemrec');

        $method = new \ReflectionMethod(KiAgentService::class, 'applyProposal');
        $method->setAccessible(true);
        $method->invoke(null, [
            'proposal_type' => 'create_tandem',
            'proposal_data' => [
                'supporter_id' => $supporterId,
                'recipient_id' => $recipientId,
            ],
        ], $this->testTenantId);

        $status = DB::table('caring_support_relationships')
            ->where('tenant_id', $this->testTenantId)
            ->where('supporter_id', $supporterId)
            ->where('recipient_id', $recipientId)
            ->value('status');

        $this->assertSame('active', $status);
    }

    // =====================================================================
    // FIX 5 — transactions.status compensating-refund literal
    // =====================================================================

    public function test_transactions_compensating_refund_literal_is_valid_enum(): void
    {
        $senderId = $this->makeUser('fedsender');

        $txId = (int) DB::table('transactions')->insertGetId([
            'tenant_id'        => $this->testTenantId,
            'sender_id'        => $senderId,
            'amount'           => 1,
            'description'      => 'schema regression tx',
            'status'           => 'pending',
            'transaction_type' => 'transfer',
            'created_at'       => now(),
        ]);

        // Same statement FederationV2Controller now runs on a definitive
        // partner rejection. The old literal 'failed' is not in the enum
        // ('pending','completed','cancelled') and truncated to ''.
        DB::update(
            "UPDATE transactions SET status = 'cancelled' WHERE id = ? AND tenant_id = ? AND sender_id = ?",
            [$txId, $this->testTenantId, $senderId]
        );

        $this->assertSame(
            'cancelled',
            DB::table('transactions')->where('id', $txId)->value('status')
        );
    }

    // =====================================================================
    // FIX 4 — bulk-publish must not write nonexistent posts.published_at
    // =====================================================================

    public function test_bulk_publish_publishes_draft_posts(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $postId = (int) DB::table('posts')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'author_id'  => $admin->id,
            'title'      => 'Bulk Publish Regression Post',
            'slug'       => 'bulk-publish-regression-' . uniqid(),
            'content'    => 'Draft content.',
            'status'     => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPost('/v2/admin/blog/bulk-publish', ['post_ids' => [$postId]]);

        $response->assertStatus(200);
        // Before the fix the UPDATE referenced the nonexistent published_at
        // column, threw, and every post in the batch reported failed.
        $response->assertJsonPath('data.success', 1);
        $response->assertJsonPath('data.failed', 0);
        $this->assertSame(
            'published',
            DB::table('posts')->where('id', $postId)->value('status')
        );
    }
}
