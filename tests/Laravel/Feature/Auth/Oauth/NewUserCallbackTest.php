<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Auth\Oauth;

use App\Services\Auth\SocialAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * SOC13 — first-time OAuth login creates a new user + identity row.
 *
 * Uses a stub Socialite user so the test is hermetic.
 */
class NewUserCallbackTest extends TestCase
{
    use DatabaseTransactions;

    public function test_new_user_is_created_with_identity_row(): void
    {
        $service = app(SocialAuthService::class);
        $email = 'oauth_new_' . uniqid() . '@example.com';
        $providerUserId = 'g_' . uniqid();

        $providerUser = new StubSocialiteUser($providerUserId, $email, 'Ada Lovelace');
        $result = $service->findOrCreateFromOauth('google', $providerUser, $this->testTenantId);

        $this->assertTrue($result['is_new']);
        $this->assertNotNull($result['user']);
        $this->assertSame($email, $result['user']->email);

        // Identity row exists
        $row = DB::selectOne(
            'SELECT * FROM oauth_identities WHERE provider = ? AND provider_user_id = ?',
            ['google', $providerUserId]
        );
        $this->assertNotNull($row);
        $this->assertSame((int) $result['user']->id, (int) $row->user_id);
    }
}

/** Hermetic stand-in for \Laravel\Socialite\Two\User. */
class StubSocialiteUser
{
    public function __construct(private string $id, private ?string $email, private ?string $name) {}
    public function getId(): string { return $this->id; }
    public function getEmail(): ?string { return $this->email; }
    public function getName(): ?string { return $this->name; }
    public function getAvatar(): ?string { return null; }
    public function getRaw(): array { return ['sub' => $this->id, 'email' => $this->email]; }
}
