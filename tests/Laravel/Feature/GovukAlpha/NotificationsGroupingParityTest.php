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
 * Feature tests for the accessible (GOV.UK) grouped notifications inbox +
 * mark-group-read.
 */
class NotificationsGroupingParityTest extends TestCase
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

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function seedNotification(int $userId, string $type, ?string $link, int $actorId, string $message, bool $read = false): void
    {
        DB::table('notifications')->insert([
            'tenant_id'  => $this->testTenantId,
            'user_id'    => $userId,
            'type'       => $type,
            'message'    => $message,
            'link'       => $link,
            'actor_id'   => $actorId,
            'is_read'    => $read ? 1 : 0,
            'created_at' => now(),
        ]);
    }

    public function test_same_target_notifications_collapse_into_one_group(): void
    {
        $owner = $this->authenticatedUser();
        $a1 = $this->authenticatedUser();
        $a2 = $this->authenticatedUser();
        $a3 = $this->authenticatedUser();
        Sanctum::actingAs($owner, ['*']);

        // Three likes on the same post → one group.
        $this->seedNotification($owner->id, 'like', '/posts/5', $a1->id, 'Alice liked your post');
        $this->seedNotification($owner->id, 'like', '/posts/5', $a2->id, 'Bob liked your post');
        $this->seedNotification($owner->id, 'like', '/posts/5', $a3->id, 'Carol liked your post');
        // A different target → its own single row.
        $this->seedNotification($owner->id, 'message', '/messages/9', $a1->id, 'Alice sent you a message');

        $res = $this->get("/{$this->testTenantSlug}/alpha/notifications");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.notifications.group_tag', ['count' => 3]));
        $res->assertSee(__('govuk_alpha.notifications.mark_group_read'));
        $res->assertSee('name="group_key"', false);
        $res->assertSee('Alice sent you a message'); // the ungrouped single still shows
    }

    public function test_mark_group_read_marks_all_in_group(): void
    {
        $owner = $this->authenticatedUser();
        $a1 = $this->authenticatedUser();
        $a2 = $this->authenticatedUser();
        Sanctum::actingAs($owner, ['*']);

        $this->seedNotification($owner->id, 'like', '/posts/7', $a1->id, 'Alice liked your post');
        $this->seedNotification($owner->id, 'like', '/posts/7', $a2->id, 'Bob liked your post');

        $res = $this->post("/{$this->testTenantSlug}/alpha/notifications/group/read", [
            'group_key' => 'like:/posts/7',
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=group-marked-read');

        $unread = DB::table('notifications')
            ->where('user_id', $owner->id)
            ->where('type', 'like')
            ->where('link', '/posts/7')
            ->where('is_read', 0)
            ->count();
        $this->assertSame(0, $unread);
    }
}
