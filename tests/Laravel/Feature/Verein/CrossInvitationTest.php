<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Verein;

use App\Core\TenantContext;
use App\Services\Verein\VereinFederationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class CrossInvitationTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = is_array(json_decode((string) ($tenant->features ?? '[]'), true)) ? json_decode((string) $tenant->features, true) : [];
        $features['caring_community'] = true;
        DB::table('tenants')->where('id', self::TENANT_ID)->update(['features' => json_encode($features)]);
        TenantContext::setById(self::TENANT_ID);
    }

    private function makeUser(string $lang = 'en'): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'first_name' => 'Cross',
            'last_name' => 'User',
            'email' => 'ci' . uniqid() . '@x.test',
            'username' => 'ci_' . substr(md5(uniqid()), 0, 8),
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'preferred_language' => $lang,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVerein(string $name): int
    {
        return (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $this->makeUser(),
            'name' => $name,
            'slug' => strtolower($name) . '-' . uniqid(),
            'org_type' => 'club',
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    private function joinOrg(int $orgId, int $userId, string $status = 'active'): void
    {
        DB::table('org_members')->insert([
            'tenant_id' => self::TENANT_ID,
            'organization_id' => $orgId,
            'user_id' => $userId,
            'role' => 'member',
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    public function test_send_invite_creates_in_app_notification_for_invitee(): void
    {
        $svc = app(VereinFederationService::class);

        $source = $this->makeVerein('Verein I-Source');
        $target = $this->makeVerein('Verein I-Target');
        $svc->setConsent($source, 'both', '8001');
        $svc->setConsent($target, 'both', '8001');

        $inviter = $this->makeUser('en');
        $invitee = $this->makeUser('de');
        $this->joinOrg($source, $inviter);
        $this->joinOrg($source, $invitee);

        $inv = $svc->sendCrossInvitation($source, $target, $inviter, $invitee, 'Komm doch dazu!');
        $this->assertSame('sent', $inv['status']);

        $notif = DB::table('notifications')
            ->where('user_id', $invitee)
            ->where('type', 'verein_cross_invitation')
            ->first();
        $this->assertNotNull($notif);
    }

    public function test_invitee_must_be_member_of_source(): void
    {
        $svc = app(VereinFederationService::class);

        $source = $this->makeVerein('Src');
        $target = $this->makeVerein('Tgt');
        $svc->setConsent($source, 'both', '8001');
        $svc->setConsent($target, 'both', '8001');

        $inviter = $this->makeUser();
        $stranger = $this->makeUser();

        $this->expectException(\InvalidArgumentException::class);
        $svc->sendCrossInvitation($source, $target, $inviter, $stranger, null);
    }

    public function test_accept_records_responded_at_and_notifies_inviter(): void
    {
        $svc = app(VereinFederationService::class);

        $source = $this->makeVerein('Src2');
        $target = $this->makeVerein('Tgt2');
        $svc->setConsent($source, 'both', '8001');
        $svc->setConsent($target, 'both', '8001');

        $inviter = $this->makeUser();
        $invitee = $this->makeUser();
        $this->joinOrg($source, $inviter);
        $this->joinOrg($source, $invitee);

        $inv = $svc->sendCrossInvitation($source, $target, $inviter, $invitee, null);
        $accepted = $svc->respondToInvitation($inv['id'], $invitee, 'accept');

        $this->assertSame('accepted', $accepted['status']);
        $this->assertNotNull($accepted['responded_at']);

        $accNotif = DB::table('notifications')
            ->where('user_id', $inviter)
            ->where('type', 'verein_cross_invitation_accepted')
            ->first();
        $this->assertNotNull($accNotif);
    }

    public function test_decline_records_responded_at(): void
    {
        $svc = app(VereinFederationService::class);

        $source = $this->makeVerein('Src3');
        $target = $this->makeVerein('Tgt3');
        $svc->setConsent($source, 'both', '8001');
        $svc->setConsent($target, 'both', '8001');

        $inviter = $this->makeUser();
        $invitee = $this->makeUser();
        $this->joinOrg($source, $inviter);
        $this->joinOrg($source, $invitee);

        $inv = $svc->sendCrossInvitation($source, $target, $inviter, $invitee, null);
        $declined = $svc->respondToInvitation($inv['id'], $invitee, 'decline');

        $this->assertSame('declined', $declined['status']);
        $this->assertNotNull($declined['responded_at']);
    }

    public function test_expire_old_invitations_marks_past_expiry_as_expired(): void
    {
        $svc = app(VereinFederationService::class);

        $source = $this->makeVerein('SrcE');
        $target = $this->makeVerein('TgtE');
        $svc->setConsent($source, 'both', '8001');
        $svc->setConsent($target, 'both', '8001');

        $inviter = $this->makeUser();
        $invitee = $this->makeUser();
        $this->joinOrg($source, $inviter);
        $this->joinOrg($source, $invitee);

        $inv = $svc->sendCrossInvitation($source, $target, $inviter, $invitee, null);

        // Backdate expires_at
        DB::table('verein_cross_invitations')
            ->where('id', $inv['id'])
            ->update(['expires_at' => now()->subDay()]);

        $count = $svc->expireOldInvitations();
        $this->assertGreaterThanOrEqual(1, $count);

        $row = DB::table('verein_cross_invitations')->where('id', $inv['id'])->first();
        $this->assertSame('expired', $row->status);
    }
}
