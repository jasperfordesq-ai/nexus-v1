<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Groups;

use App\Models\User;
use App\Services\GroupAnnouncementService;
use App\Services\GroupDataExportService;
use App\Support\CursorSigner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class GroupCompositeCursorAndExportTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // UserObserver queues search-index work. With PHPUnit's sync driver, the
        // worker-safety hooks intentionally clear TenantContext around that job.
        // Intercept unrelated indexing so this request-style test retains the
        // tenant context established by TestCase, as HTTP middleware would.
        Queue::fake();

        $this->owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($this->owner, ['*']);
    }

    public function test_directory_composite_cursor_returns_all_tied_rows_once_and_rejects_tampering(): void
    {
        $marker = 'cursor-directory-' . str_replace('.', '-', uniqid('', true));
        $expected = [];
        foreach ([1, 1, 1, 1, 0, 0, 0, 0] as $index => $featured) {
            $expected[] = $this->group($marker . '-' . $index, (bool) $featured);
        }

        $seen = [];
        $cursor = null;
        do {
            $query = '/v2/groups?q=' . rawurlencode($marker) . '&per_page=3';
            if ($cursor !== null) {
                $query .= '&cursor=' . rawurlencode($cursor);
            }

            $response = $this->apiGet($query)->assertStatus(200);
            foreach ($response->json('data') ?? [] as $row) {
                $seen[] = (int) $row['id'];
            }
            $cursor = $response->json('meta.cursor');
        } while (is_string($cursor) && $cursor !== '');

        $expectedOrder = DB::table('groups')
            ->whereIn('id', $expected)
            ->orderByDesc('is_featured')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        self::assertSame($expectedOrder, $seen);
        self::assertSame($seen, array_values(array_unique($seen)));

        $this->apiGet('/v2/groups?cursor=tampered')->assertStatus(422);
        $foreignTenantCursor = CursorSigner::encode([
            'kind' => 'group_directory',
            'tenant_id' => $this->testTenantId + 999,
            'featured' => 1,
            'id' => max($expected),
        ]);
        $this->apiGet('/v2/groups?cursor=' . rawurlencode($foreignTenantCursor))->assertStatus(422);
    }

    public function test_announcement_composite_cursor_handles_all_sort_ties_and_is_group_bound(): void
    {
        $groupId = $this->group('announcement-cursor');
        $otherGroupId = $this->group('announcement-cursor-other');
        $this->membership($groupId, (int) $this->owner->id, 'owner');
        $this->membership($otherGroupId, (int) $this->owner->id, 'owner');
        $timestamp = now()->subDay()->startOfSecond();

        $expectedIds = [];
        foreach ([
            [1, 3], [1, 3], [1, 2], [0, 3], [0, 3], [0, 1],
        ] as $index => [$pinned, $priority]) {
            $expectedIds[] = (int) DB::table('group_announcements')->insertGetId([
                'group_id' => $groupId,
                'tenant_id' => $this->testTenantId,
                'title' => 'Cursor announcement ' . $index,
                'content' => 'Tied announcement content ' . $index,
                'is_pinned' => $pinned,
                'priority' => $priority,
                'created_by' => $this->owner->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $service = new GroupAnnouncementService();
        $seen = [];
        $cursor = null;
        do {
            $page = $service->list($groupId, (int) $this->owner->id, [
                'limit' => 2,
                'cursor' => $cursor,
            ]);
            self::assertNotNull($page, var_export($service->getErrors(), true));
            foreach ($page['items'] as $item) {
                $seen[] = (int) $item['id'];
            }
            $cursor = $page['cursor'];
        } while ($cursor !== null);

        $expectedOrder = DB::table('group_announcements')
            ->whereIn('id', $expectedIds)
            ->orderByDesc('is_pinned')
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        self::assertSame($expectedOrder, $seen);

        self::assertNull($service->list($groupId, (int) $this->owner->id, ['cursor' => 'tampered']));
        self::assertSame('INVALID_CURSOR', $service->getErrors()[0]['code'] ?? null);

        $firstPage = $service->list($groupId, (int) $this->owner->id, ['limit' => 2]);
        self::assertIsString($firstPage['cursor'] ?? null);
        self::assertNull($service->list($otherGroupId, (int) $this->owner->id, [
            'cursor' => $firstPage['cursor'],
        ]));
        self::assertSame('INVALID_CURSOR', $service->getErrors()[0]['code'] ?? null);
    }

    public function test_full_export_has_versioned_complete_manifest_exact_settings_and_no_csv_formula(): void
    {
        $groupId = $this->group('complete-export');
        $this->membership($groupId, (int) $this->owner->id, 'owner');

        foreach ([
            'welcome_message_' . $groupId => '"Welcome"',
            'welcome_message_enabled_' . $groupId => 'true',
            'unrelated_policy_x' . $groupId => '"must not leak"',
        ] as $key => $value) {
            DB::table('group_policies')->insert([
                'tenant_id' => $this->testTenantId,
                'policy_key' => $key,
                'policy_value' => $value,
                'category' => 'notifications',
                'value_type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $export = GroupDataExportService::exportAll($groupId, (int) $this->owner->id);
        self::assertNotNull($export);
        self::assertSame(GroupDataExportService::SCHEMA_NAME, $export['schema']['name']);
        self::assertSame(GroupDataExportService::SCHEMA_VERSION, $export['schema']['version']);
        self::assertSame(GroupDataExportService::MANIFEST_SECTIONS, $export['schema']['sections']);
        foreach (GroupDataExportService::MANIFEST_SECTIONS as $section) {
            self::assertArrayHasKey($section, $export);
        }

        $settingKeys = array_column($export['settings'], 'policy_key');
        sort($settingKeys);
        self::assertSame([
            'welcome_message_' . $groupId,
            'welcome_message_enabled_' . $groupId,
        ], $settingKeys);

        $csv = GroupDataExportService::toCsv([
            ['name' => '=HYPERLINK("https://attacker.example")', 'role' => 'member'],
        ]);
        self::assertStringContainsString("'=" . 'HYPERLINK', $csv);
        self::assertStringNotContainsString("\n=HYPERLINK", $csv);
    }

    private function group(string $name, bool $featured = false): int
    {
        return (int) DB::table('groups')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_id' => $this->owner->id,
            'name' => $name,
            'description' => 'Deterministic Groups audit fixture description.',
            'visibility' => 'public',
            'status' => 'active',
            'is_active' => true,
            'is_featured' => $featured,
            'cached_member_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function membership(int $groupId, int $userId, string $role): void
    {
        DB::table('group_members')->updateOrInsert(
            [
                'tenant_id' => $this->testTenantId,
                'group_id' => $groupId,
                'user_id' => $userId,
            ],
            [
                'role' => $role,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
