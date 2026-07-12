<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\User;
use App\Services\GroupMentionService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

class GroupMentionServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_parse_mentions_returns_empty_without_tokens(): void
    {
        $this->assertSame([], GroupMentionService::parseMentions('No mentions here'));
    }

    public function test_parse_mentions_resolves_tenant_users_and_deduplicates_tokens(): void
    {
        $alice = User::factory()->forTenant($this->testTenantId)->create(['username' => 'mention_alice']);
        $bob = User::factory()->forTenant($this->testTenantId)->create(['username' => 'mention_bob']);
        User::factory()->forTenant(999)->create(['username' => 'mention_foreign']);
        TenantContext::setById($this->testTenantId);

        $result = GroupMentionService::parseMentions(
            '@mention_alice @mention_alice @mention_bob @mention_foreign @missing',
        );

        $this->assertEqualsCanonicalizing(
            [(int) $alice->id, (int) $bob->id],
            array_column($result, 'user_id'),
        );
    }

    public function test_member_suggestion_contract_requires_a_viewer_id(): void
    {
        $method = new \ReflectionMethod(GroupMentionService::class, 'getMemberSuggestions');

        $this->assertSame(['groupId', 'viewerId', 'query', 'limit'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $method->getParameters(),
        ));
    }
}
