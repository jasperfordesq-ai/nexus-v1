<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit;

use App\Core\TenantContext;
use App\Models\Connection;
use App\Models\Group;
use App\Models\Listing;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Laravel\TestCase;

/**
 * Unit tests for the User Eloquent model.
 *
 * Tests tenant scope, relationships, hidden attributes, and casts.
 */
class UserModelTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    //  Tenant Scope
    // ------------------------------------------------------------------

    /**
     * Test that the global TenantScope is applied to User queries.
     */
    public function test_tenant_scope_is_applied_to_queries(): void
    {
        // Create users on two tenants
        User::factory()->forTenant($this->testTenantId)->count(2)->create();
        User::factory()->forTenant(999)->count(3)->create();

        // TenantContext is set to testTenantId (2) in setUp()
        $users = User::all();

        // Should only return users from tenant 2
        foreach ($users as $user) {
            $this->assertEquals(
                $this->testTenantId,
                $user->tenant_id,
                'TenantScope should filter results to the current tenant'
            );
        }
    }

    /**
     * Test that removing the tenant scope returns all users.
     */
    public function test_without_tenant_scope_returns_all_users(): void
    {
        User::factory()->forTenant($this->testTenantId)->count(2)->create();
        User::factory()->forTenant(999)->count(3)->create();

        $allUsers = User::withoutGlobalScope(TenantScope::class)->get();

        // Should have users from both tenants
        $tenantIds = $allUsers->pluck('tenant_id')->unique()->sort()->values()->toArray();
        $this->assertContains($this->testTenantId, $tenantIds);
        $this->assertContains(999, $tenantIds);
    }

    /**
     * Test that tenant_id is automatically set on creation.
     */
    public function test_tenant_id_auto_set_on_creation(): void
    {
        // Create a user WITHOUT explicitly setting tenant_id.
        // The HasTenantScope trait should fill it from TenantContext.
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'name' => 'Test User',
            'email' => 'auto-tenant@example.com',
            'password_hash' => 'hashed',
            'role' => 'member',
            'status' => 'active',
        ]);

        $this->assertEquals(
            $this->testTenantId,
            $user->tenant_id,
            'tenant_id should be auto-set from TenantContext on creation'
        );
    }

    // ------------------------------------------------------------------
    //  Relationships
    // ------------------------------------------------------------------

    /**
     * Test that the listings relationship returns HasMany.
     */
    public function test_listings_relationship(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();

        $this->assertInstanceOf(HasMany::class, $user->listings());

        // Create some listings for this user
        Listing::factory()->forTenant($this->testTenantId)->count(2)->create([
            'user_id' => $user->id,
        ]);

        $this->assertCount(2, $user->listings);
    }

    /**
     * Test that the groups relationship returns BelongsToMany.
     */
    public function test_groups_relationship(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();

        $this->assertInstanceOf(BelongsToMany::class, $user->groups());
    }

    /**
     * Test that the connections relationship returns HasMany.
     */
    public function test_connections_relationship(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();

        $this->assertInstanceOf(HasMany::class, $user->connections());
    }

    /**
     * Test that the reviews relationships exist.
     */
    public function test_reviews_relationships(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();

        $this->assertInstanceOf(HasMany::class, $user->reviewsReceived());
        $this->assertInstanceOf(HasMany::class, $user->reviewsGiven());
    }

    /**
     * Test that the notifications relationship exists.
     */
    public function test_notifications_relationship(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();

        $this->assertInstanceOf(HasMany::class, $user->notifications());
    }

    /**
     * Test that the transactions relationships exist.
     */
    public function test_transactions_relationships(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();

        $this->assertInstanceOf(HasMany::class, $user->sentTransactions());
        $this->assertInstanceOf(HasMany::class, $user->receivedTransactions());
    }

    // ------------------------------------------------------------------
    //  Hidden Attributes
    // ------------------------------------------------------------------

    /**
     * Test that sensitive fields are hidden from serialization.
     */
    public function test_hidden_attributes_not_in_array(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'password_hash' => 'secret-hash-value',
        ]);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password_hash', $array, 'password_hash should be hidden');
        $this->assertArrayNotHasKey('totp_secret', $array, 'totp_secret should be hidden');
        $this->assertArrayNotHasKey('totp_backup_codes', $array, 'totp_backup_codes should be hidden');
    }

    /**
     * Test that hidden attributes are still accessible on the model instance.
     */
    public function test_hidden_attributes_are_accessible_on_model(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'password_hash' => 'accessible-hash',
        ]);

        // Hidden from JSON/array, but accessible as a property
        $this->assertEquals('accessible-hash', $user->password_hash);
    }

    // ------------------------------------------------------------------
    //  Casts
    // ------------------------------------------------------------------

    /**
     * Test that boolean fields are cast correctly.
     */
    public function test_boolean_casts(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'is_verified' => 1,
            'onboarding_completed' => 0,
        ]);

        $this->assertIsBool($user->is_verified);
        $this->assertTrue($user->is_verified);

        $this->assertIsBool($user->onboarding_completed);
        $this->assertFalse($user->onboarding_completed);
    }

    /**
     * Test that float fields are cast correctly.
     */
    public function test_float_casts(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'latitude' => '53.3498',
            'longitude' => '-6.2603',
        ]);

        $this->assertIsFloat($user->latitude);
        $this->assertIsFloat($user->longitude);
        $this->assertEqualsWithDelta(53.3498, $user->latitude, 0.0001);
        $this->assertEqualsWithDelta(-6.2603, $user->longitude, 0.0001);
    }

    /**
     * Test that the notification_preferences field is cast to array.
     */
    public function test_notification_preferences_cast_to_array(): void
    {
        $prefs = ['email_messages' => 1, 'push_enabled' => 0];

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'notification_preferences' => json_encode($prefs),
        ]);

        // Refresh to ensure cast happens on retrieval
        $user->refresh();

        $this->assertIsArray($user->notification_preferences);
        $this->assertEquals(1, $user->notification_preferences['email_messages']);
    }

    /**
     * Test that datetime fields are cast to Carbon instances.
     */
    public function test_datetime_casts(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email_verified_at' => '2026-01-15 10:30:00',
            'last_active_at' => '2026-03-01 14:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->last_active_at);
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    /**
     * Test the active scope filters by status.
     */
    public function test_active_scope(): void
    {
        User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        User::factory()->forTenant($this->testTenantId)->create(['status' => 'inactive']);
        User::factory()->forTenant($this->testTenantId)->create(['status' => 'suspended']);

        $activeUsers = User::active()->get();

        foreach ($activeUsers as $user) {
            $this->assertEquals('active', $user->status);
        }
    }

    /**
     * Test the verified scope filters by is_verified.
     */
    public function test_verified_scope(): void
    {
        User::factory()->forTenant($this->testTenantId)->create(['is_verified' => true]);
        User::factory()->forTenant($this->testTenantId)->create(['is_verified' => false]);

        $verifiedUsers = User::verified()->get();

        foreach ($verifiedUsers as $user) {
            $this->assertTrue($user->is_verified);
        }
    }

    /**
     * Test that getAuthPassword returns the password_hash field.
     */
    public function test_get_auth_password_returns_hash(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'password_hash' => 'my-hashed-password',
        ]);

        $this->assertEquals('my-hashed-password', $user->getAuthPassword());
    }
}
