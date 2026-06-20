<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature coverage for the v2 member self-service surface — the account a member
 * manages for themselves: read profile, edit profile, change password, manage
 * notification preferences, and delete (anonymize) the account.
 *
 * This is security/privacy-sensitive (password change requires the current
 * password; account deletion requires re-auth and must actually anonymize PII).
 * It was the real-coverage gap left when the reflection-only Migrated
 * UsersApiControllerTest was deleted (commit ffa90d82a) — see docs/TEST-DEBT.md.
 *
 * Routes exercised (routes/api.php):
 *   GET    /v2/users/me                  -> UsersController::me
 *   PUT    /v2/users/me                  -> UsersController::update
 *   POST   /v2/users/me/password         -> UsersController::updatePassword
 *   GET    /v2/users/me/notifications    -> UsersController::notificationPreferences
 *   PUT    /v2/users/me/notifications    -> UsersController::updateNotificationPreferences
 *   DELETE /v2/users/me                  -> UsersController::deleteAccount
 */
class MemberSelfServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function makeMember(string $password = 'OldPassword123!'): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status'        => 'active',
            'is_approved'   => true,
            'password_hash' => Hash::make($password),
        ]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------ profile

    public function test_me_returns_the_authenticated_members_profile(): void
    {
        $user = $this->makeMember();

        $response = $this->apiGet('/v2/users/me');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame((int) $user->id, (int) $response->json('data.id'));
        $this->assertSame($user->email, $response->json('data.email'));
    }

    public function test_update_profile_persists_the_change(): void
    {
        $user = $this->makeMember();
        $newBio = 'I help neighbours with gardening and small repairs.';

        $response = $this->apiPut('/v2/users/me', ['bio' => $newBio]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($newBio, $response->json('data.bio'));
        // And it is durably written, not just echoed back.
        $this->assertSame($newBio, DB::table('users')->where('id', $user->id)->value('bio'));
    }

    // ----------------------------------------------------------------- password

    public function test_update_password_rotates_the_hash_with_the_correct_current_password(): void
    {
        $user = $this->makeMember('OldPassword123!');
        $oldHash = DB::table('users')->where('id', $user->id)->value('password_hash');

        $response = $this->apiPost('/v2/users/me/password', [
            'current_password' => 'OldPassword123!',
            'new_password'     => 'BrandNewPassw0rd!', // >= 12 chars, not a reuse
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $newHash = DB::table('users')->where('id', $user->id)->value('password_hash');
        $this->assertNotSame($oldHash, $newHash, 'password_hash must change');
        $this->assertTrue(Hash::check('BrandNewPassw0rd!', $newHash), 'new password must verify against the stored hash');
    }

    public function test_update_password_rejects_a_wrong_current_password(): void
    {
        $user = $this->makeMember('OldPassword123!');
        $oldHash = DB::table('users')->where('id', $user->id)->value('password_hash');

        $response = $this->apiPost('/v2/users/me/password', [
            'current_password' => 'definitely-not-it',
            'new_password'     => 'BrandNewPassw0rd!',
        ]);

        $this->assertSame(400, $response->getStatusCode());
        // The hash must be untouched.
        $this->assertSame($oldHash, DB::table('users')->where('id', $user->id)->value('password_hash'));
    }

    // ------------------------------------------------------------- notifications

    public function test_notification_preferences_returns_the_flag_set(): void
    {
        $this->makeMember();

        $response = $this->apiGet('/v2/users/me/notifications');

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->json('data');
        $this->assertIsArray($data);
        foreach (['email_messages', 'email_digest', 'push_enabled'] as $key) {
            $this->assertArrayHasKey($key, $data);
            $this->assertIsBool($data[$key]);
        }
    }

    public function test_update_notification_preferences_persists_a_toggle(): void
    {
        $this->makeMember();

        $update = $this->apiPut('/v2/users/me/notifications', ['email_messages' => false]);
        $this->assertSame(200, $update->getStatusCode());

        $after = $this->apiGet('/v2/users/me/notifications');
        $this->assertSame(200, $after->getStatusCode());
        $this->assertFalse($after->json('data.email_messages'), 'the toggle must persist and read back false');
    }

    public function test_update_notification_preferences_rejects_an_empty_payload(): void
    {
        $this->makeMember();

        $response = $this->apiPut('/v2/users/me/notifications', ['not_a_real_pref' => true]);

        $this->assertSame(400, $response->getStatusCode());
    }

    // --------------------------------------------------------------- delete acct

    public function test_delete_account_rejects_a_wrong_password(): void
    {
        $user = $this->makeMember('OldPassword123!');

        // delete_account is rate-limited to 1/min, so this is the only delete call here.
        $response = $this->apiDelete('/v2/users/me', ['password' => 'wrong-password']);

        $this->assertSame(403, $response->getStatusCode());
        // The account must remain active and un-anonymized.
        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame('active', $row->status);
        $this->assertSame($user->email, $row->email);
    }

    public function test_delete_account_with_correct_password_anonymizes_the_account(): void
    {
        $user = $this->makeMember('OldPassword123!');

        $response = $this->apiDelete('/v2/users/me', ['password' => 'OldPassword123!']);

        $this->assertSame(200, $response->getStatusCode());

        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame('inactive', $row->status, 'deleted account must be soft-deleted to inactive');
        $this->assertNotNull($row->anonymized_at, 'anonymized_at must be stamped');
        $this->assertStringStartsWith('deleted_', (string) $row->email, 'email must be anonymized');
        $this->assertNotSame($user->email, $row->email);
    }
}
