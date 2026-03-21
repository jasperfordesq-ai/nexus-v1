<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Tests\Laravel\TestCase;

class UserTest extends TestCase
{
    private User $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new User();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('users', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $fillable = $this->model->getFillable();
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('name', $fillable);
        $this->assertContains('first_name', $fillable);
        $this->assertContains('last_name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('username', $fillable);
        $this->assertContains('password_hash', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('avatar_url', $fillable);
        $this->assertContains('bio', $fillable);
        $this->assertContains('location', $fillable);
        $this->assertContains('latitude', $fillable);
        $this->assertContains('longitude', $fillable);
        $this->assertContains('phone', $fillable);
        $this->assertContains('is_verified', $fillable);
        $this->assertContains('is_approved', $fillable);
        $this->assertContains('balance', $fillable);
        $this->assertContains('onboarding_completed', $fillable);
        $this->assertContains('profile_type', $fillable);
        $this->assertContains('organization_name', $fillable);
        $this->assertContains('totp_enabled', $fillable);
        $this->assertContains('notification_preferences', $fillable);
        $this->assertContains('email_verified_at', $fillable);
        $this->assertContains('last_active_at', $fillable);
    }

    public function test_hidden_contains_sensitive_fields(): void
    {
        $hidden = $this->model->getHidden();
        $this->assertContains('password_hash', $hidden);
        $this->assertContains('totp_secret', $hidden);
        $this->assertContains('totp_backup_codes', $hidden);
    }

    public function test_appends_contains_expected_attributes(): void
    {
        $appends = $this->model->getAppends();
        $this->assertContains('avatar', $appends);
        $this->assertContains('tagline', $appends);
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('float', $casts['latitude']);
        $this->assertEquals('float', $casts['longitude']);
        $this->assertEquals('decimal:2', $casts['balance']);
        $this->assertEquals('boolean', $casts['is_verified']);
        $this->assertEquals('boolean', $casts['is_admin']);
        $this->assertEquals('boolean', $casts['is_super_admin']);
        $this->assertEquals('boolean', $casts['is_god']);
        $this->assertEquals('boolean', $casts['is_tenant_super_admin']);
        $this->assertEquals('boolean', $casts['is_approved']);
        $this->assertEquals('boolean', $casts['onboarding_completed']);
        $this->assertEquals('boolean', $casts['totp_enabled']);
        $this->assertEquals('datetime', $casts['email_verified_at']);
        $this->assertEquals('datetime', $casts['last_active_at']);
        $this->assertEquals('array', $casts['notification_preferences']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(User::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_uses_has_api_tokens(): void
    {
        $traits = class_uses_recursive(User::class);
        $this->assertContains(HasApiTokens::class, $traits);
    }

    public function test_avatar_accessor_returns_avatar_url(): void
    {
        $this->model->avatar_url = 'https://example.com/avatar.jpg';
        $this->assertEquals('https://example.com/avatar.jpg', $this->model->avatar);
    }

    public function test_tagline_accessor_returns_bio(): void
    {
        $this->model->bio = 'Hello world';
        $this->assertEquals('Hello world', $this->model->tagline);
    }

    public function test_listings_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->listings());
    }

    public function test_groups_relationship(): void
    {
        $this->assertInstanceOf(BelongsToMany::class, $this->model->groups());
    }

    public function test_connections_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->connections());
    }

    public function test_reviews_received_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->reviewsReceived());
    }

    public function test_reviews_given_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->reviewsGiven());
    }

    public function test_notifications_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->notifications());
    }

    public function test_sent_transactions_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->sentTransactions());
    }

    public function test_received_transactions_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->receivedTransactions());
    }

    public function test_badges_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->badges());
    }

    public function test_scope_active(): void
    {
        $builder = User::query()->active();
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function test_scope_admins(): void
    {
        $builder = User::query()->admins();
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function test_scope_verified(): void
    {
        $builder = User::query()->verified();
        $this->assertInstanceOf(Builder::class, $builder);
    }
}
