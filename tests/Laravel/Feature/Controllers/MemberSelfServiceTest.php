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

    protected function setUp(): void
    {
        parent::setUp();
        // Re-pin the tenant before each test: factory create() + the GDPR erasure
        // flow drift TenantContext, and a prior test's drift would otherwise make
        // a later test's tenant-scoped DELETE (e.g. the notifications purge) miss.
        \App\Core\TenantContext::setById($this->testTenantId);
    }

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

    public function test_delete_account_rejects_a_missing_password(): void
    {
        // H1: the primary React UI must send the re-auth password (SettingsPage
        // sends { body: { password } }); a passwordless request must be refused
        // with a 400 BEFORE any erasure, and the account must be left intact.
        $user = $this->makeMember('OldPassword123!');

        $response = $this->apiDelete('/v2/users/me', []);

        $this->assertSame(400, $response->getStatusCode());
        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame('active', $row->status, 'account must remain active when no password is supplied');
        $this->assertSame($user->email, $row->email, 'account must not be anonymized without re-auth');
    }

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

    /**
     * H2 regression: DELETE /v2/users/me must run the full GDPR Article 17
     * erasure (GdprService::executeAccountDeletion), not the shallow PII-column
     * anonymize. The shallow path left related personal records and the
     * password hash behind; full erasure purges them.
     */
    public function test_delete_account_with_correct_password_purges_related_records(): void
    {
        $user = $this->makeMember('OldPassword123!');

        // A personal record the full erasure must DELETE (the shallow path left
        // these behind — that was the H2 bug).
        DB::table('notifications')->insert([
            'user_id'    => $user->id,
            'tenant_id'  => $this->testTenantId,
            'message'    => 'You have a new message',
            'type'       => 'system',
            'created_at' => now(),
        ]);

        $response = $this->apiDelete('/v2/users/me', ['password' => 'OldPassword123!']);

        $this->assertSame(200, $response->getStatusCode());

        // Related personal data is purged, not merely anonymized.
        $this->assertSame(
            0,
            DB::table('notifications')->where('user_id', $user->id)->count(),
            'full GDPR erasure must delete the user\'s notifications'
        );

        // Credentials are destroyed by the full purge (the shallow path left
        // password_hash intact); the account is anonymized + deactivated, with
        // the GdprService-style @anonymized.local address.
        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame('', (string) $row->password_hash, 'password_hash must be wiped by full erasure');
        $this->assertSame('inactive', $row->status);
        $this->assertNotNull($row->anonymized_at);
        $this->assertStringEndsWith('@anonymized.local', (string) $row->email);
    }

    /**
     * The pre-deletion "legal retention" data export must SUCCEED end-to-end,
     * not silently fall through to the best-effort failure path.
     *
     * GdprService::collectUserData() historically referenced columns that don't
     * exist in the real schema (listings.time_credits / views_count,
     * messages.content, transactions.from_user_id/to_user_id, events.end_date,
     * reviews.listing_id, vol_applications.reviewed_by, ...), so
     * generateDataExport() threw on the FIRST sub-query and the best-effort
     * wrapper in executeAccountDeletion() swallowed it — logging "GDPR
     * pre-deletion export failed" and never producing the retention ZIP.
     *
     * This locks the export step: it runs to completion (recording the
     * 'data_exported' audit row that is only written AFTER the ZIP is built),
     * and the "export failed" warning is NOT emitted. MySQL validates every
     * selected column at execute time even with zero matching rows, so a fresh
     * member is enough to catch any remaining drifted reference.
     */
    public function test_delete_account_produces_the_legal_retention_export(): void
    {
        $user = $this->makeMember('OldPassword123!');

        // Snapshot the gdpr warning log so we can assert the specific
        // "export failed" breadcrumb is not appended by this request.
        [$logFile, $before] = $this->snapshotGdprLog();

        $response = $this->apiDelete('/v2/users/me', ['password' => 'OldPassword123!']);
        $this->assertSame(200, $response->getStatusCode());

        // generateDataExport() writes a 'data_exported' audit row only AFTER the
        // export ZIP is successfully built — its presence proves collectUserData()
        // resolved every column against the real schema.
        $exported = DB::table('gdpr_audit_log')
            ->where('user_id', $user->id)
            ->where('action', 'data_exported')
            ->exists();
        $this->assertTrue(
            $exported,
            'the pre-deletion legal-retention export must complete and be audited'
        );

        // The best-effort failure breadcrumb must NOT have fired for this request.
        $after = is_file($logFile) ? (string) file_get_contents($logFile) : '';
        $delta = substr($after, strlen($before));
        $this->assertStringNotContainsString(
            'GDPR pre-deletion export failed',
            $delta,
            'the retention export must not fall back to the best-effort failure path'
        );

        // Workspace hygiene: remove the retention ZIP this test produced.
        $this->cleanupExportArtifacts($user->id);
    }

    /**
     * Resolve the gdpr-channel warning log file and snapshot its current
     * contents. The channel log path is private to the LoggerService singleton,
     * so read it via reflection to stay correct regardless of LOG_PATH config.
     *
     * @return array{0:string,1:string} [absolute log file path, contents-before]
     */
    private function snapshotGdprLog(): array
    {
        $logger = \App\Services\Enterprise\LoggerService::getInstance('gdpr');
        $prop = (new \ReflectionClass($logger))->getProperty('logPath');
        $prop->setAccessible(true);
        $logPath = rtrim((string) $prop->getValue($logger), '/\\');

        // WARNING-level records land in "{channel}-{date}.log" (only <=ERROR
        // goes to the error log).
        $file = $logPath . '/gdpr-' . date('Y-m-d') . '.log';
        $before = is_file($file) ? (string) file_get_contents($file) : '';

        return [$file, $before];
    }

    /**
     * Best-effort removal of the retention export ZIP(s) a deletion test wrote
     * to storage/exports, so the suite does not accumulate artifacts on disk.
     */
    private function cleanupExportArtifacts(int $userId): void
    {
        $base = getenv('STORAGE_PATH') ?: dirname(__DIR__, 4) . '/storage';
        foreach (glob(rtrim($base, '/\\') . "/exports/nexus_data_export_{$userId}_*.zip") ?: [] as $zip) {
            @unlink($zip);
        }
    }
}
