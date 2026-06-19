<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Smoke + behaviour tests for "Batch A" accessible parity mediums:
 * groups visibility/joined filter, polls my-polls + category filter,
 * group-exchanges status tabs, and the wallet page (pending-in stat).
 */
class MediumGapsBatchAParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();
        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            unset($_SERVER[$k]);
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function enableFeatures(array $features): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        foreach ($features as $f) {
            $current[$f] = true;
        }
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function enableModule(string $module): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('configuration');
        $config = $row ? (json_decode($row, true) ?: []) : [];
        $config['modules'] = $config['modules'] ?? [];
        $config['modules'][$module] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['configuration' => json_encode($config)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active', 'is_approved' => true,
        ], $overrides));
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function seedGroup(int $ownerId, string $name, string $visibility): int
    {
        return (int) DB::table('groups')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'owner_id'   => $ownerId,
            'name'       => $name,
            'slug'       => 'grp-' . uniqid(),
            'visibility' => $visibility,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPoll(int $userId, string $question, ?string $category): int
    {
        return (int) DB::table('polls')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'user_id'    => $userId,
            'question'   => $question,
            'is_active'  => 1,
            'end_date'   => null,
            'category'   => $category,
            'poll_type'  => 'standard',
            'created_at' => now(),
        ]);
    }

    public function test_groups_visibility_filter_excludes_non_matching(): void
    {
        $this->enableFeatures(['groups']);
        $owner = $this->authenticatedUser();
        $this->seedGroup($owner->id, 'Public Garden Club', 'public');
        $this->seedGroup($owner->id, 'Private Inner Circle', 'private');

        $res = $this->get("/{$this->testTenantSlug}/alpha/groups?filter=private");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.groups.filter_label'));
        $res->assertSee('Private Inner Circle');
        $res->assertDontSee('Public Garden Club');
    }

    public function test_polls_mine_filter_excludes_other_users_polls(): void
    {
        $this->enableFeatures(['polls']);
        $viewer = $this->authenticatedUser();
        $other = $this->authenticatedUser();
        Sanctum::actingAs($viewer, ['*']);

        $this->seedPoll($viewer->id, 'My Own Poll Question', 'governance');
        $this->seedPoll($other->id, 'Someone Elses Poll', 'social');

        $res = $this->get("/{$this->testTenantSlug}/alpha/polls?mine=1");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.polls.my_polls_label'));
        $res->assertSee('My Own Poll Question');
        $res->assertDontSee('Someone Elses Poll');
    }

    public function test_polls_category_filter_shows_category_options(): void
    {
        $this->enableFeatures(['polls']);
        $viewer = $this->authenticatedUser();
        $this->seedPoll($viewer->id, 'Governance Poll', 'governance');

        $res = $this->get("/{$this->testTenantSlug}/alpha/polls");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.polls.category_label'));
        $res->assertSee('governance');
    }

    public function test_group_exchanges_status_tabs_render(): void
    {
        $this->enableFeatures(['group_exchanges']);
        $this->authenticatedUser();

        $res = $this->get("/{$this->testTenantSlug}/alpha/group-exchanges");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.group_exchanges.filter_all'));
        $res->assertSee('state=active', false);
    }

    public function test_wallet_page_renders_with_stat_grid(): void
    {
        $this->enableModule('wallet');
        $this->authenticatedUser();

        $res = $this->get("/{$this->testTenantSlug}/alpha/wallet");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.wallet.balance_label'));
    }
}
