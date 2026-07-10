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
 * Feature coverage for the accessible-frontend commerce parity module
 * (marketplace seller/buyer flows, courses learning, premium management).
 *
 * Mirrors GovukAlphaFrontendTest's base class, traits and helpers. Every
 * test method is prefixed test_commerce_ and globally unique.
 */
class CommerceParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        \Illuminate\Support\Facades\Cache::flush();
    }

    // ==================================================================
    //  Marketplace — create / edit / my-listings (seller)
    // ==================================================================

    public function test_commerce_create_listing_form_requires_auth(): void
    {
        $this->enableAlphaFeatures(['marketplace']);
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/create")
            ->assertRedirectContains('/accessible/login');
    }

    public function test_commerce_create_listing_gated_off_by_default(): void
    {
        $this->authenticatedUser();
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/create")->assertStatus(403);
    }

    public function test_commerce_create_listing_form_renders(): void
    {
        $this->authenticatedUser(['name' => 'Seller One']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/create");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.listing_form.title_create'));
        $res->assertSee('name="title"', false);
    }

    public function test_commerce_store_listing_persists_and_redirects(): void
    {
        $user = $this->authenticatedUser(['name' => 'Seller Store']);
        $this->enableAlphaFeatures(['marketplace']);
        $this->disableMeiliSearch();

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/create", [
            'title' => 'Hand-knitted scarf',
            'description' => 'A warm woollen scarf, barely used.',
            'price_type' => 'free',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('marketplace_listings', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Hand-knitted scarf',
        ]);
    }

    public function test_commerce_store_listing_validation_redirects_back(): void
    {
        $this->authenticatedUser(['name' => 'Seller Blank']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/create", [
            'title' => '',
            'description' => '',
            'price_type' => 'free',
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('commerceListingErrors');
    }

    public function test_commerce_edit_listing_owner_only(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Owner Edit']);
        $this->enableAlphaFeatures(['marketplace']);
        $id = $this->seedListing($owner->id);

        // Owner can open the edit form.
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/{$id}/edit")->assertOk();

        // Another member in the same tenant is forbidden.
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/{$id}/edit")->assertStatus(403);
    }

    public function test_commerce_update_listing_persists_changes(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Owner Update']);
        $this->enableAlphaFeatures(['marketplace']);
        $this->disableMeiliSearch();
        $id = $this->seedListing($owner->id);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/{$id}/update", [
            'title' => 'Updated title',
            'description' => 'Updated description text.',
            'price_type' => 'free',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('marketplace_listings', ['id' => $id, 'title' => 'Updated title']);
    }

    public function test_commerce_delete_listing_removes_it(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Owner Delete']);
        $this->enableAlphaFeatures(['marketplace']);
        $id = $this->seedListing($owner->id);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/{$id}/delete");
        $res->assertRedirectContains('status=deleted');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('marketplace_listings', ['id' => $id, 'status' => 'removed']);
    }

    public function test_commerce_my_listings_dashboard_renders(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Owner Mine']);
        $this->enableAlphaFeatures(['marketplace']);
        $this->disableMeiliSearch();
        $this->seedListing($owner->id, ['title' => 'My Active Item']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/mine");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.my_listings.title'));
        $res->assertSee('My Active Item');
    }

    // ==================================================================
    //  Marketplace — save / saved / free items / seller profile
    // ==================================================================

    public function test_commerce_save_and_saved_listings(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $buyer = $this->authenticatedUser(['name' => 'Saver']);
        $this->enableAlphaFeatures(['marketplace']);
        $this->disableMeiliSearch();
        $id = $this->seedListing($owner->id, ['title' => 'Saveable Item']);

        $this->post("/{$this->testTenantSlug}/accessible/marketplace/{$id}/save")
            ->assertRedirectContains("/marketplace/{$id}");

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('marketplace_saved_listings', [
            'user_id' => $buyer->id,
            'marketplace_listing_id' => $id,
        ]);

        $saved = $this->get("/{$this->testTenantSlug}/accessible/marketplace/saved");
        $saved->assertOk();
        $saved->assertSee('Saveable Item');
    }

    public function test_commerce_save_missing_listing_404(): void
    {
        $this->authenticatedUser(['name' => 'Saver 404']);
        $this->enableAlphaFeatures(['marketplace']);

        $this->post("/{$this->testTenantSlug}/accessible/marketplace/99999999/save")->assertStatus(404);
    }

    public function test_commerce_free_items_page_renders(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->authenticatedUser(['name' => 'Freebie Hunter']);
        $this->enableAlphaFeatures(['marketplace']);
        $this->disableMeiliSearch();
        $this->seedListing($owner->id, ['title' => 'Free Couch', 'price_type' => 'free']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/free");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.free_items.title'));
    }

    public function test_commerce_seller_profile_renders(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active', 'is_approved' => true, 'name' => 'Pat Seller',
            'first_name' => 'Pat', 'last_name' => 'Seller',
        ]);
        $this->authenticatedUser(['name' => 'Browser']);
        $this->enableAlphaFeatures(['marketplace']);
        $this->disableMeiliSearch();
        $this->seedListing($seller->id, ['title' => 'Sellers Item']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/seller/{$seller->id}");
        $res->assertOk();
        $res->assertSee('Pat Seller');
        $res->assertSee('Sellers Item');
    }

    public function test_commerce_seller_profile_unknown_404(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['marketplace']);
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/seller/99999999")->assertStatus(404);
    }

    // ==================================================================
    //  Marketplace — buy / offer / report
    // ==================================================================

    public function test_commerce_offer_form_renders_and_blocks_own(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Self Offer']);
        $this->enableAlphaFeatures(['marketplace']);
        $id = $this->seedListing($owner->id, ['title' => 'Own Item', 'price_type' => 'negotiable']);

        // Owner cannot offer on their own listing.
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/{$id}/offer")->assertStatus(403);
    }

    public function test_commerce_store_offer_creates_offer(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $buyer = $this->authenticatedUser(['name' => 'Offerer']);
        $this->enableAlphaFeatures(['marketplace']);
        $id = $this->seedListing($owner->id, [
            'title' => 'Negotiable Lamp',
            'price_type' => 'negotiable',
            'price' => 25.00,
        ]);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/{$id}/offer", [
            'amount' => '20',
            'message' => 'Would you take twenty?',
        ]);
        $res->assertRedirectContains('/marketplace/offers');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('marketplace_offers', [
            'marketplace_listing_id' => $id,
            'buyer_id' => $buyer->id,
            'status' => 'pending',
        ]);
    }

    public function test_commerce_store_offer_rejects_zero_amount(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->authenticatedUser(['name' => 'Zero Offerer']);
        $this->enableAlphaFeatures(['marketplace']);
        $id = $this->seedListing($owner->id, ['price_type' => 'negotiable', 'price' => 10.00]);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/{$id}/offer", ['amount' => '0']);
        $res->assertRedirect();
        $res->assertSessionHas('commerceOfferErrors');
    }

    public function test_commerce_buy_form_renders_for_fixed_price(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->authenticatedUser(['name' => 'Shopper']);
        $this->enableAlphaFeatures(['marketplace']);
        $id = $this->seedListing($owner->id, [
            'title' => 'Fixed Price Kettle',
            'price_type' => 'fixed',
            'price' => 15.00,
        ]);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/{$id}/buy");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.buy.title'));
        $res->assertSee('Fixed Price Kettle');
    }

    public function test_commerce_buy_form_404_for_free_listing(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->authenticatedUser(['name' => 'Free Buyer']);
        $this->enableAlphaFeatures(['marketplace']);
        $id = $this->seedListing($owner->id, ['price_type' => 'free']);

        // Free listings are not purchasable via buy-now.
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/{$id}/buy")->assertStatus(404);
    }

    public function test_commerce_report_form_and_submission(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->authenticatedUser(['name' => 'Reporter']);
        $this->enableAlphaFeatures(['marketplace']);
        $id = $this->seedListing($owner->id, ['title' => 'Dodgy Item']);

        $form = $this->get("/{$this->testTenantSlug}/accessible/marketplace/{$id}/report");
        $form->assertOk();
        $form->assertSee(__('govuk_alpha_commerce.report.title'));

        $submit = $this->post("/{$this->testTenantSlug}/accessible/marketplace/{$id}/report", [
            'reason' => 'misleading',
            'description' => 'The description does not match the item shown.',
        ]);
        $submit->assertRedirectContains("/marketplace/{$id}");
    }

    public function test_commerce_report_validation_errors(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->authenticatedUser(['name' => 'Empty Reporter']);
        $this->enableAlphaFeatures(['marketplace']);
        $id = $this->seedListing($owner->id);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/{$id}/report", [
            'reason' => '',
            'description' => '',
        ]);
        $res->assertRedirect();
        $res->assertSessionHasErrors(['reason', 'description']);
    }

    // ==================================================================
    //  Marketplace — offers + orders dashboards
    // ==================================================================

    public function test_commerce_my_offers_dashboard_renders(): void
    {
        $this->authenticatedUser(['name' => 'Offer Viewer']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/offers");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.offers.title'));
    }

    public function test_commerce_buyer_orders_dashboard_renders(): void
    {
        $this->authenticatedUser(['name' => 'Order Viewer']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/orders");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.orders_buyer.title'));
    }

    public function test_commerce_seller_orders_dashboard_renders(): void
    {
        $this->authenticatedUser(['name' => 'Sales Viewer']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/sales");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.orders_seller.title'));
    }

    public function test_commerce_order_action_forbidden_for_non_participant(): void
    {
        $buyer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->enableAlphaFeatures(['marketplace']);
        $listingId = $this->seedListing($seller->id, ['price_type' => 'fixed', 'price' => 12.00]);

        $orderId = DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'TEST-' . uniqid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'marketplace_listing_id' => $listingId,
            'quantity' => 1,
            'unit_price' => 12.00,
            'total_price' => 12.00,
            'currency' => 'EUR',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A stranger cannot confirm someone else's order.
        $stranger = $this->authenticatedUser(['name' => 'Stranger']);
        $this->post("/{$this->testTenantSlug}/accessible/marketplace/orders/{$orderId}/confirm")->assertStatus(403);
    }

    // ==================================================================
    //  Courses — my learning + lesson player
    // ==================================================================

    public function test_commerce_my_learning_renders_empty(): void
    {
        $this->authenticatedUser(['name' => 'Learner Empty']);
        $this->enableAlphaFeatures(['courses']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/courses/mine");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.my_learning.title'));
        $res->assertSee(__('govuk_alpha_commerce.my_learning.empty'));
    }

    public function test_commerce_course_learn_requires_enrolment(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->authenticatedUser(['name' => 'Unenrolled']);
        $this->enableAlphaFeatures(['courses']);
        $courseId = $this->seedCourse($author->id);

        // Not enrolled → redirected to the course detail page.
        $this->get("/{$this->testTenantSlug}/accessible/courses/{$courseId}/learn")
            ->assertRedirectContains("/courses/{$courseId}");
    }

    public function test_commerce_course_learn_renders_when_enrolled(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $learner = $this->authenticatedUser(['name' => 'Enrolled Learner']);
        $this->enableAlphaFeatures(['courses']);
        $courseId = $this->seedCourse($author->id, 'Course With Lesson');
        $lessonId = $this->seedLesson($courseId);
        $this->seedEnrolment($courseId, $learner->id);

        $res = $this->get("/{$this->testTenantSlug}/accessible/courses/{$courseId}/learn");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.learn.lessons_heading'));
    }

    public function test_commerce_complete_lesson_marks_progress(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $learner = $this->authenticatedUser(['name' => 'Completer']);
        $this->enableAlphaFeatures(['courses']);
        $courseId = $this->seedCourse($author->id, 'Completable Course');
        $lessonId = $this->seedLesson($courseId);
        $enrolmentId = $this->seedEnrolment($courseId, $learner->id);

        $res = $this->post("/{$this->testTenantSlug}/accessible/courses/{$courseId}/lessons/{$lessonId}/complete");
        $res->assertRedirectContains("/courses/{$courseId}/learn");

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('course_lesson_progress', [
            'enrollment_id' => $enrolmentId,
            'lesson_id' => $lessonId,
            'status' => 'completed',
        ]);
    }

    public function test_commerce_complete_lesson_foreign_lesson_404(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $learner = $this->authenticatedUser(['name' => 'Wrong Lesson']);
        $this->enableAlphaFeatures(['courses']);
        $courseId = $this->seedCourse($author->id);
        $this->seedEnrolment($courseId, $learner->id);

        // A lesson id that does not belong to this course → 404.
        $this->post("/{$this->testTenantSlug}/accessible/courses/{$courseId}/lessons/99999999/complete")
            ->assertStatus(404);
    }

    // ==================================================================
    //  Premium — manage subscription
    // ==================================================================

    public function test_commerce_donation_support_page_uses_equal_community_language(): void
    {
        $this->authenticatedUser(['name' => 'Supporter']);
        $this->enableAlphaFeatures(['member_premium']);

        DB::table('member_premium_tiers')->insert([
            'tenant_id' => $this->testTenantId,
            'name' => 'Community Supporter',
            'slug' => 'supporter-' . uniqid(),
            'monthly_price_cents' => 500,
            'yearly_price_cents' => 5000,
            'features' => json_encode(['Recognition in community thank-yous']),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->get("/{$this->testTenantSlug}/accessible/premium");

        $res->assertOk();
        $res->assertSee('Donate');
        $res->assertSee('Support this community');
        $res->assertDontSee('Premium');
        $res->assertDontSee('unlock extra features');
    }

    public function test_commerce_premium_manage_redirects_without_subscription(): void
    {
        $this->authenticatedUser(['name' => 'No Sub']);
        $this->enableAlphaFeatures(['member_premium']);

        $this->get("/{$this->testTenantSlug}/accessible/premium/manage")
            ->assertRedirectContains('status=no-subscription');
    }

    public function test_commerce_premium_manage_renders_with_subscription(): void
    {
        $user = $this->authenticatedUser(['name' => 'Subscriber']);
        $this->enableAlphaFeatures(['member_premium']);

        $tierId = DB::table('member_premium_tiers')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Gold',
            'slug' => 'gold-' . uniqid(),
            'monthly_price_cents' => 500,
            'yearly_price_cents' => 5000,
            'features' => json_encode(['priority_support']),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('member_subscriptions')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'tier_id' => $tierId,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->get("/{$this->testTenantSlug}/accessible/premium/manage");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.premium_manage.title'));
        $res->assertSee('Gold');
    }

    public function test_commerce_premium_gated_off_by_default(): void
    {
        $this->authenticatedUser();
        $this->get("/{$this->testTenantSlug}/accessible/premium/manage")->assertStatus(403);
    }

    // ==================================================================
    //  Courses — instructor / creator suite
    // ==================================================================

    public function test_commerce_instructor_courses_requires_auth(): void
    {
        $this->enableAlphaFeatures(['courses']);
        $this->get("/{$this->testTenantSlug}/accessible/courses/instructor")
            ->assertRedirectContains('/accessible/login');
    }

    public function test_commerce_instructor_courses_gated_off_by_default(): void
    {
        $this->authenticatedUser();
        $this->get("/{$this->testTenantSlug}/accessible/courses/instructor")->assertStatus(403);
    }

    public function test_commerce_instructor_courses_lists_authored(): void
    {
        $user = $this->authenticatedUser(['name' => 'Teacher One']);
        $this->enableAlphaFeatures(['courses']);
        $this->seedCourse($user->id, 'My Taught Course');

        $res = $this->get("/{$this->testTenantSlug}/accessible/courses/instructor");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.instructor.title'));
        $res->assertSee('My Taught Course');
    }

    public function test_commerce_create_course_form_renders(): void
    {
        $this->authenticatedUser(['name' => 'Teacher Form']);
        $this->enableAlphaFeatures(['courses']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/new");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.instructor.title_create'));
        $res->assertSee('name="title"', false);
    }

    public function test_commerce_store_course_persists_and_redirects(): void
    {
        $user = $this->authenticatedUser(['name' => 'Teacher Store']);
        $this->enableAlphaFeatures(['courses']);
        $this->disableMeiliSearch();

        $res = $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/new", [
            'title' => 'Intro to Timebanking',
            'summary' => 'A short course about timebanking.',
            'level' => 'beginner',
            'visibility' => 'members',
            'enrollment_type' => 'self_paced',
            'credit_cost' => '0',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('courses', [
            'tenant_id' => $this->testTenantId,
            'author_user_id' => $user->id,
            'title' => 'Intro to Timebanking',
            'status' => 'draft',
        ]);
    }

    public function test_commerce_store_course_validation_redirects_back(): void
    {
        $this->authenticatedUser(['name' => 'Teacher Blank']);
        $this->enableAlphaFeatures(['courses']);

        $res = $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/new", [
            'title' => '',
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('commerceCourseErrors');
    }

    public function test_commerce_edit_course_owner_only(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Course Owner']);
        $this->enableAlphaFeatures(['courses']);
        $id = $this->seedCourse($owner->id, 'Owned Course');

        // Owner can open the edit form.
        $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/edit")->assertOk();

        // Another member in the same tenant is forbidden.
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/edit")->assertStatus(403);
    }

    public function test_commerce_edit_course_cross_tenant_404(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Course Owner X']);
        $this->enableAlphaFeatures(['courses']);

        // A course belonging to a DIFFERENT tenant must resolve to 404 (tenant scope).
        $otherTenantId = $this->testTenantId + 9999;
        $foreignId = (int) DB::table('courses')->insertGetId([
            'tenant_id' => $otherTenantId,
            'author_user_id' => $owner->id,
            'title' => 'Foreign Course',
            'slug' => 'foreign-course-' . uniqid(),
            'level' => 'beginner',
            'visibility' => 'public',
            'status' => 'draft',
            'moderation_status' => 'pending',
            'credit_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/{$foreignId}/edit")->assertStatus(404);
    }

    public function test_commerce_update_course_persists_changes(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Course Updater']);
        $this->enableAlphaFeatures(['courses']);
        $this->disableMeiliSearch();
        $id = $this->seedCourse($owner->id, 'Before Title');

        $res = $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/update", [
            'title' => 'After Title',
            'summary' => 'Updated summary.',
            'level' => 'intermediate',
            'visibility' => 'public',
            'enrollment_type' => 'self_paced',
            'credit_cost' => '2',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('courses', [
            'id' => $id,
            'tenant_id' => $this->testTenantId,
            'title' => 'After Title',
            'level' => 'intermediate',
        ]);
    }

    public function test_commerce_publish_course_owner_only(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Publisher']);
        $this->enableAlphaFeatures(['courses']);
        $this->disableMeiliSearch();
        $id = (int) DB::table('courses')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'author_user_id' => $owner->id,
            'title' => 'Draft To Publish',
            'slug' => 'draft-to-publish-' . uniqid(),
            'level' => 'beginner',
            'visibility' => 'public',
            'status' => 'draft',
            'moderation_status' => 'pending',
            'credit_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/publish")->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('courses', [
            'id' => $id,
            'status' => 'published',
        ]);

        // A non-owner cannot publish.
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/unpublish")->assertStatus(403);
    }

    public function test_commerce_delete_course_owner_only(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Deleter']);
        $this->enableAlphaFeatures(['courses']);
        $this->disableMeiliSearch();
        $id = $this->seedCourse($owner->id, 'To Delete');

        $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/delete")
            ->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseMissing('courses', ['id' => $id]);
    }

    public function test_commerce_course_analytics_renders_for_owner(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Analyst']);
        $this->enableAlphaFeatures(['courses']);
        $id = $this->seedCourse($owner->id, 'Measured Course');
        $this->seedEnrolment($id, $owner->id);

        $res = $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/analytics");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.analytics.total_enrollments'));
        $res->assertSee('Measured Course');
    }

    public function test_commerce_course_analytics_owner_only(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Owner Analytics']);
        $this->enableAlphaFeatures(['courses']);
        $id = $this->seedCourse($owner->id, 'Private Analytics');

        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/analytics")->assertStatus(403);
    }

    // ==================================================================
    //  Courses — section + lesson builder
    // ==================================================================

    public function test_commerce_builder_renders_on_edit_page(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Builder Owner']);
        $this->enableAlphaFeatures(['courses']);
        $id = $this->seedCourse($owner->id, 'Buildable Course');

        $res = $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/edit");
        $res->assertOk();
        $res->assertSee('name="section_title"', false);
        $res->assertSee('name="lesson_title"', false);
    }

    public function test_commerce_store_section_persists(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Section Owner']);
        $this->enableAlphaFeatures(['courses']);
        $id = $this->seedCourse($owner->id, 'Course With Section');

        $res = $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/sections", [
            'section_title' => 'Week One',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('course_sections', [
            'tenant_id' => $this->testTenantId,
            'course_id' => $id,
            'title' => 'Week One',
        ]);
    }

    public function test_commerce_store_section_requires_title(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Blank Section']);
        $this->enableAlphaFeatures(['courses']);
        $id = $this->seedCourse($owner->id, 'No Title Section');

        $res = $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/sections", [
            'section_title' => '',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseMissing('course_sections', ['course_id' => $id]);
    }

    public function test_commerce_store_lesson_persists(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Lesson Owner']);
        $this->enableAlphaFeatures(['courses']);
        $id = $this->seedCourse($owner->id, 'Course With Lesson');

        $res = $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/lessons", [
            'lesson_title' => 'Introduction',
            'content_type' => 'text',
            'body' => 'Welcome to the course.',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('course_lessons', [
            'tenant_id' => $this->testTenantId,
            'course_id' => $id,
            'title' => 'Introduction',
            'content_type' => 'text',
        ]);
    }

    public function test_commerce_delete_lesson_removes_it(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Lesson Deleter']);
        $this->enableAlphaFeatures(['courses']);
        $id = $this->seedCourse($owner->id, 'Course Delete Lesson');
        $lessonId = $this->seedLesson($id);

        $res = $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/lessons/{$lessonId}/delete");
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseMissing('course_lessons', ['id' => $lessonId]);
    }

    public function test_commerce_store_section_owner_only(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Section Guard']);
        $this->enableAlphaFeatures(['courses']);
        $id = $this->seedCourse($owner->id, 'Guarded Course');

        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$id}/sections", [
            'section_title' => 'Sneaky',
        ])->assertStatus(403);
    }

    // ==================================================================
    //  Marketplace — category page
    // ==================================================================

    public function test_commerce_category_page_requires_auth(): void
    {
        $this->enableAlphaFeatures(['marketplace']);
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/category/some-slug")
            ->assertRedirectContains('/accessible/login');
    }

    public function test_commerce_category_unknown_slug_404(): void
    {
        $this->authenticatedUser(['name' => 'Category Browser']);
        $this->enableAlphaFeatures(['marketplace']);
        $this->disableMeiliSearch();

        $this->get("/{$this->testTenantSlug}/accessible/marketplace/category/no-such-category-xyz")
            ->assertStatus(404);
    }

    // ==================================================================
    //  Marketplace — buyer pickups
    // ==================================================================

    public function test_commerce_my_pickups_renders_empty(): void
    {
        $this->authenticatedUser(['name' => 'Pickup Buyer']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/pickups");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.pickups.title'));
    }

    public function test_commerce_my_pickups_requires_auth(): void
    {
        $this->enableAlphaFeatures(['marketplace']);
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/pickups")
            ->assertRedirectContains('/accessible/login');
    }

    // ==================================================================
    //  Marketplace — seller pickup-slot management
    // ==================================================================

    public function test_commerce_seller_pickup_slots_requires_auth(): void
    {
        $this->enableAlphaFeatures(['marketplace']);
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/slots")
            ->assertRedirectContains('/accessible/login');
    }

    public function test_commerce_seller_pickup_slots_gated_off_by_default(): void
    {
        $this->authenticatedUser(['name' => 'Slot Gated']);
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/slots")->assertStatus(403);
    }

    public function test_commerce_seller_pickup_slots_lists(): void
    {
        $user = $this->authenticatedUser(['name' => 'Slot Seller']);
        $this->enableAlphaFeatures(['marketplace']);
        $profileId = $this->seedSellerProfile($user->id);
        $this->seedPickupSlot($profileId);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/slots");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.slots.title'));
        $res->assertSee('name="capacity"', false);
    }

    public function test_commerce_store_pickup_slot_persists(): void
    {
        $user = $this->authenticatedUser(['name' => 'Slot Creator']);
        $this->enableAlphaFeatures(['marketplace']);

        $start = now()->addDay()->format('Y-m-d\TH:i');
        $end = now()->addDay()->addHour()->format('Y-m-d\TH:i');

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/slots", [
            'slot_start' => $start,
            'slot_end' => $end,
            'capacity' => '8',
            'is_recurring' => '1',
            'is_active' => '1',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $profileId = (int) DB::table('marketplace_seller_profiles')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->value('id');
        $this->assertDatabaseHas('marketplace_pickup_slots', [
            'tenant_id' => $this->testTenantId,
            'seller_id' => $profileId,
            'capacity' => 8,
            'is_recurring' => 1,
        ]);
    }

    public function test_commerce_store_pickup_slot_validates_times(): void
    {
        $this->authenticatedUser(['name' => 'Bad Slot']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/slots", [
            'slot_start' => '',
            'slot_end' => '',
            'capacity' => '5',
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('commercePickupSlotErrors');
    }

    public function test_commerce_edit_pickup_slot_renders_for_owner(): void
    {
        $user = $this->authenticatedUser(['name' => 'Slot Owner']);
        $this->enableAlphaFeatures(['marketplace']);
        $profileId = $this->seedSellerProfile($user->id);
        $slotId = $this->seedPickupSlot($profileId);

        $this->get("/{$this->testTenantSlug}/accessible/marketplace/slots/{$slotId}/edit")->assertOk();
    }

    public function test_commerce_edit_pickup_slot_cross_seller_404(): void
    {
        // The slot belongs to a DIFFERENT seller profile → the owning member
        // resolves their own (empty) profile, so the slot id is not found → 404.
        $foreignSellerUser = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $foreignProfileId = $this->seedSellerProfile($foreignSellerUser->id);
        $foreignSlotId = $this->seedPickupSlot($foreignProfileId);

        $this->authenticatedUser(['name' => 'Other Seller']);
        $this->enableAlphaFeatures(['marketplace']);

        $this->get("/{$this->testTenantSlug}/accessible/marketplace/slots/{$foreignSlotId}/edit")->assertStatus(404);
    }

    public function test_commerce_update_pickup_slot_persists_changes(): void
    {
        $user = $this->authenticatedUser(['name' => 'Slot Updater']);
        $this->enableAlphaFeatures(['marketplace']);
        $profileId = $this->seedSellerProfile($user->id);
        $slotId = $this->seedPickupSlot($profileId);

        $start = now()->addDays(2)->format('Y-m-d\TH:i');
        $end = now()->addDays(2)->addHours(2)->format('Y-m-d\TH:i');

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/slots/{$slotId}/update", [
            'slot_start' => $start,
            'slot_end' => $end,
            'capacity' => '12',
            'is_active' => '1',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('marketplace_pickup_slots', [
            'id' => $slotId,
            'tenant_id' => $this->testTenantId,
            'seller_id' => $profileId,
            'capacity' => 12,
        ]);
    }

    public function test_commerce_delete_pickup_slot_removes_it(): void
    {
        $user = $this->authenticatedUser(['name' => 'Slot Deleter']);
        $this->enableAlphaFeatures(['marketplace']);
        $profileId = $this->seedSellerProfile($user->id);
        $slotId = $this->seedPickupSlot($profileId);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/slots/{$slotId}/delete");
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseMissing('marketplace_pickup_slots', ['id' => $slotId]);
    }

    // ==================================================================
    //  Courses — instructor grading queue
    // ==================================================================

    public function test_commerce_course_grading_requires_auth(): void
    {
        $this->enableAlphaFeatures(['courses']);
        $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/1/grading")
            ->assertRedirectContains('/accessible/login');
    }

    public function test_commerce_course_grading_gated_off_by_default(): void
    {
        $this->authenticatedUser(['name' => 'Grading Gated']);
        $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/1/grading")->assertStatus(403);
    }

    public function test_commerce_course_grading_owner_only(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Grading Owner']);
        $this->enableAlphaFeatures(['courses']);
        $courseId = $this->seedCourse($owner->id, 'Gradable Course');

        $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/{$courseId}/grading")->assertOk();

        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/{$courseId}/grading")->assertStatus(403);
    }

    public function test_commerce_course_grading_lists_pending(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Grading Lister']);
        $this->enableAlphaFeatures(['courses']);
        $courseId = $this->seedCourse($owner->id, 'Quiz Course');
        $quizId = $this->seedQuiz($courseId);
        $learner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Learner Smith']);
        $this->seedQuizAttempt($quizId, $learner->id, 'pending_review');

        $res = $this->get("/{$this->testTenantSlug}/accessible/courses/instructor/{$courseId}/grading");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.grading.title'));
        $res->assertSee('Learner Smith');
    }

    public function test_commerce_grade_attempt_persists(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Grader']);
        $this->enableAlphaFeatures(['courses']);
        $courseId = $this->seedCourse($owner->id, 'Grade Me Course');
        $quizId = $this->seedQuiz($courseId);
        $learner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $attemptId = $this->seedQuizAttempt($quizId, $learner->id, 'pending_review');

        $res = $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$courseId}/grading/{$attemptId}", [
            'score_percent' => '85',
            'passed' => '1',
            'feedback' => 'Good work, minor improvements needed.',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('course_quiz_attempts', [
            'id' => $attemptId,
            'grading_status' => 'graded',
            'graded_by' => $owner->id,
            'passed' => 1,
        ]);
    }

    public function test_commerce_grade_attempt_wrong_course_404(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Grader Mismatch']);
        $this->enableAlphaFeatures(['courses']);
        $courseA = $this->seedCourse($owner->id, 'Course A');
        $courseB = $this->seedCourse($owner->id, 'Course B');
        $quizB = $this->seedQuiz($courseB);
        $learner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $attemptB = $this->seedQuizAttempt($quizB, $learner->id, 'pending_review');

        // Attempt belongs to Course B, but we grade it via Course A → 404.
        $this->post("/{$this->testTenantSlug}/accessible/courses/instructor/{$courseA}/grading/{$attemptB}", [
            'score_percent' => '50',
            'passed' => '0',
        ])->assertStatus(404);
    }

    // ==================================================================
    //  Marketplace — merchant onboarding
    // ==================================================================

    public function test_commerce_merchant_onboarding_form_renders(): void
    {
        $this->authenticatedUser(['name' => 'New Seller']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/onboarding");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.onboarding.title'));
        $res->assertSee('name="display_name"', false);
    }

    public function test_commerce_merchant_onboarding_persists_profile(): void
    {
        $user = $this->authenticatedUser(['name' => 'Onboarding Seller']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/onboarding", [
            'seller_type' => 'business',
            'business_name' => 'Acme Crafts',
            'display_name' => 'Acme',
            'bio' => 'We make handmade crafts.',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('marketplace_seller_profiles', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'business_name' => 'Acme Crafts',
        ]);
    }

    public function test_commerce_merchant_onboarding_requires_display_name(): void
    {
        $this->authenticatedUser(['name' => 'Blank Onboarding']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/onboarding", [
            'seller_type' => 'private',
            'display_name' => '',
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('commerceOnboardingErrors');
    }

    // ==================================================================
    //  Seller — merchant coupon management
    // ==================================================================

    public function test_commerce_seller_coupons_gated_off_by_default(): void
    {
        $this->authenticatedUser(['name' => 'Coupon Gated']);
        // marketplace on but merchant_coupons off → 403
        $this->enableAlphaFeatures(['marketplace']);
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/coupons")->assertStatus(403);
    }

    public function test_commerce_seller_coupons_requires_seller_profile(): void
    {
        $this->authenticatedUser(['name' => 'No Profile Coupon']);
        $this->enableAlphaFeatures(['marketplace', 'merchant_coupons']);
        // No seller profile → 403
        $this->get("/{$this->testTenantSlug}/accessible/marketplace/coupons")->assertStatus(403);
    }

    public function test_commerce_seller_coupons_lists_for_seller(): void
    {
        $user = $this->authenticatedUser(['name' => 'Coupon Seller']);
        $this->enableAlphaFeatures(['marketplace', 'merchant_coupons']);
        $this->seedSellerProfile($user->id);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/coupons");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.coupons.title'));
    }

    public function test_commerce_store_coupon_persists(): void
    {
        $user = $this->authenticatedUser(['name' => 'Coupon Creator']);
        $this->enableAlphaFeatures(['marketplace', 'merchant_coupons']);
        $profileId = $this->seedSellerProfile($user->id);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/coupons/new", [
            'title' => 'Spring Sale',
            'code' => 'SPRING10',
            'discount_type' => 'percent',
            'discount_value' => '10',
            'status' => 'active',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('merchant_coupons', [
            'tenant_id' => $this->testTenantId,
            'seller_id' => $profileId,
            'code' => 'SPRING10',
            'title' => 'Spring Sale',
        ]);
    }

    public function test_commerce_store_coupon_requires_title(): void
    {
        $user = $this->authenticatedUser(['name' => 'Blank Coupon']);
        $this->enableAlphaFeatures(['marketplace', 'merchant_coupons']);
        $this->seedSellerProfile($user->id);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/coupons/new", [
            'title' => '',
            'discount_type' => 'percent',
            'discount_value' => '10',
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('commerceCouponErrors');
    }

    public function test_commerce_edit_coupon_cross_seller_404(): void
    {
        $user = $this->authenticatedUser(['name' => 'Coupon Owner']);
        $this->enableAlphaFeatures(['marketplace', 'merchant_coupons']);
        $this->seedSellerProfile($user->id);

        // A coupon belonging to a different seller profile in this tenant.
        $otherProfileId = $this->seedSellerProfile(
            User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true])->id
        );
        $couponId = (int) DB::table('merchant_coupons')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'seller_id' => $otherProfileId,
            'code' => 'OTHER5',
            'title' => 'Other coupon',
            'discount_type' => 'percent',
            'discount_value' => 5,
            'status' => 'active',
            'applies_to' => 'all_listings',
            'max_uses_per_member' => 1,
            'usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get("/{$this->testTenantSlug}/accessible/marketplace/coupons/{$couponId}/edit")->assertStatus(404);
    }

    public function test_commerce_delete_coupon_removes_it(): void
    {
        $user = $this->authenticatedUser(['name' => 'Coupon Deleter']);
        $this->enableAlphaFeatures(['marketplace', 'merchant_coupons']);
        $profileId = $this->seedSellerProfile($user->id);
        $couponId = (int) DB::table('merchant_coupons')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'seller_id' => $profileId,
            'code' => 'KILLME',
            'title' => 'Delete me',
            'discount_type' => 'percent',
            'discount_value' => 5,
            'status' => 'active',
            'applies_to' => 'all_listings',
            'max_uses_per_member' => 1,
            'usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->post("/{$this->testTenantSlug}/accessible/marketplace/coupons/{$couponId}/delete");
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseMissing('merchant_coupons', ['id' => $couponId]);
    }

    // ==================================================================
    //  Podcasts — studio
    // ==================================================================

    public function test_commerce_podcast_studio_requires_auth(): void
    {
        $this->enableAlphaFeatures(['podcasts']);
        $this->get("/{$this->testTenantSlug}/accessible/podcasts/studio")
            ->assertRedirectContains('/accessible/login');
    }

    public function test_commerce_podcast_studio_renders_for_authoring_member(): void
    {
        // The test tenant has podcasts enabled AND member show-creation allowed
        // (PodcastConfigurationService::CONFIG_ALLOW_MEMBER_SHOW_CREATION), so an
        // authenticated member reaches the studio. (The feature/author gates
        // themselves — hasFeature('podcasts') + commerceCanAuthorPodcasts — are
        // exercised by the requires_auth test and the controller abort_unless.)
        $this->enableAlphaFeatures(['podcasts']);
        $this->authenticatedUser(['name' => 'Podcaster']);
        $this->get("/{$this->testTenantSlug}/accessible/podcasts/studio")->assertOk();
    }

    public function test_commerce_podcast_studio_renders(): void
    {
        $this->authenticatedUser(['name' => 'Podcaster']);
        $this->enableAlphaFeatures(['podcasts']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/podcasts/studio");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.podcast_studio.title'));
    }

    public function test_commerce_store_podcast_persists(): void
    {
        $user = $this->authenticatedUser(['name' => 'Show Creator']);
        $this->enableAlphaFeatures(['podcasts']);

        $res = $this->post("/{$this->testTenantSlug}/accessible/podcasts/studio/new", [
            'title' => 'Community Voices',
            'summary' => 'Stories from our timebank.',
            'visibility' => 'public',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('podcast_shows', [
            'tenant_id' => $this->testTenantId,
            'owner_user_id' => $user->id,
            'title' => 'Community Voices',
            'status' => 'draft',
        ]);
    }

    public function test_commerce_store_podcast_requires_title(): void
    {
        $this->authenticatedUser(['name' => 'Blank Show']);
        $this->enableAlphaFeatures(['podcasts']);

        $res = $this->post("/{$this->testTenantSlug}/accessible/podcasts/studio/new", [
            'title' => '',
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('commercePodcastErrors');
    }

    public function test_commerce_podcast_manage_owner_only(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Show Owner']);
        $this->enableAlphaFeatures(['podcasts']);
        $showId = $this->seedPodcastShow($owner->id, 'Owned Show');

        $this->get("/{$this->testTenantSlug}/accessible/podcasts/studio/{$showId}")->assertOk();

        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $this->get("/{$this->testTenantSlug}/accessible/podcasts/studio/{$showId}")->assertStatus(403);
    }

    public function test_commerce_store_podcast_episode_persists(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Episode Author']);
        $this->enableAlphaFeatures(['podcasts']);
        $showId = $this->seedPodcastShow($owner->id, 'Show With Episode');

        $res = $this->post("/{$this->testTenantSlug}/accessible/podcasts/studio/{$showId}/episodes", [
            'episode_title' => 'Episode One',
            'audio_url' => 'https://example.com/audio/ep1.mp3',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('podcast_episodes', [
            'tenant_id' => $this->testTenantId,
            'show_id' => $showId,
            'title' => 'Episode One',
        ]);
    }

    public function test_commerce_store_podcast_episode_requires_audio(): void
    {
        $owner = $this->authenticatedUser(['name' => 'No Audio Author']);
        $this->enableAlphaFeatures(['podcasts']);
        $showId = $this->seedPodcastShow($owner->id, 'Show No Audio');

        $res = $this->post("/{$this->testTenantSlug}/accessible/podcasts/studio/{$showId}/episodes", [
            'episode_title' => 'Episode Without Audio',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseMissing('podcast_episodes', [
            'show_id' => $showId,
            'title' => 'Episode Without Audio',
        ]);
    }

    public function test_commerce_delete_podcast_episode_cross_show_404(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Cross Show Owner']);
        $this->enableAlphaFeatures(['podcasts']);
        $showId = $this->seedPodcastShow($owner->id, 'Show A');
        $otherShowId = $this->seedPodcastShow($owner->id, 'Show B');
        $foreignEpisodeId = $this->seedPodcastEpisode($otherShowId, $owner->id);

        // Episode belongs to Show B, but we ask via Show A → 404.
        $this->post("/{$this->testTenantSlug}/accessible/podcasts/studio/{$showId}/episodes/{$foreignEpisodeId}/delete")
            ->assertStatus(404);
    }

    // ==================================================================
    //  Seed + base helpers (mirror GovukAlphaFrontendTest)
    // ==================================================================

    private function seedSellerProfile(int $userId): int
    {
        return (int) DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'seller_type' => 'business',
            'joined_marketplace_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPickupSlot(int $sellerProfileId): int
    {
        return (int) DB::table('marketplace_pickup_slots')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'seller_id' => $sellerProfileId,
            'slot_start' => now()->addDay(),
            'slot_end' => now()->addDay()->addHour(),
            'capacity' => 5,
            'booked_count' => 0,
            'is_recurring' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedQuiz(int $courseId): int
    {
        return (int) DB::table('course_quizzes')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'course_id' => $courseId,
            'title' => 'Seeded Quiz',
            'pass_mark_percent' => 70,
            'max_attempts' => 0,
            'shuffle_questions' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedQuizAttempt(int $quizId, int $userId, string $gradingStatus = 'pending_review'): int
    {
        return (int) DB::table('course_quiz_attempts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'quiz_id' => $quizId,
            'user_id' => $userId,
            'answers' => json_encode(['1' => 'A free-text answer from the learner.']),
            'score_percent' => 0,
            'passed' => 0,
            'grading_status' => $gradingStatus,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPodcastShow(int $ownerId, string $title = 'Seeded Show'): int
    {
        return (int) DB::table('podcast_shows')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_user_id' => $ownerId,
            'title' => $title,
            'slug' => 'seeded-show-' . uniqid(),
            'language' => 'en',
            'visibility' => 'public',
            'status' => 'draft',
            'moderation_status' => 'approved',
            'episode_count' => 0,
            'subscriber_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPodcastEpisode(int $showId, int $authorId): int
    {
        return (int) DB::table('podcast_episodes')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'show_id' => $showId,
            'author_user_id' => $authorId,
            'title' => 'Seeded Episode',
            'slug' => 'seeded-episode-' . uniqid(),
            'audio_url' => 'https://example.com/audio.mp3',
            'media_processing_status' => 'complete',
            'media_scan_status' => 'not_required',
            'visibility' => 'inherit',
            'status' => 'draft',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $overrides */
    private function seedListing(int $userId, array $overrides = []): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'title' => 'Seeded Item',
            'description' => 'A seeded marketplace listing for testing.',
            'price_type' => 'free',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function seedCourse(int $authorId, string $title = 'Seeded Course'): int
    {
        return (int) DB::table('courses')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'author_user_id' => $authorId,
            'title' => $title,
            'slug' => 'seeded-course-' . uniqid(),
            'level' => 'beginner',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'approved',
            'credit_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedLesson(int $courseId): int
    {
        $sectionId = DB::table('course_sections')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'course_id' => $courseId,
            'title' => 'Section 1',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('course_lessons')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'course_id' => $courseId,
            'section_id' => $sectionId,
            'title' => 'Lesson 1',
            'content_type' => 'text',
            'body' => 'Lesson content.',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedEnrolment(int $courseId, int $userId): int
    {
        return (int) DB::table('course_enrollments')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'course_id' => $courseId,
            'user_id' => $userId,
            'status' => 'active',
            'progress_percent' => 0,
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ==================================================================
    //  Seller pickup "confirm a collection" — no-JS QR-scan equivalent
    // ==================================================================

    public function test_commerce_pickup_scan_form_renders_on_slots_page(): void
    {
        $this->authenticatedUser(['name' => 'Slot Seller']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/marketplace/slots");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.slots.scan_heading'));
        $res->assertSee(route('govuk-alpha.marketplace.slots.scan', ['tenantSlug' => $this->testTenantSlug]), false);
        $res->assertSee('name="qr_code"', false);
    }

    public function test_commerce_pickup_scan_requires_auth(): void
    {
        $this->enableAlphaFeatures(['marketplace']);
        $this->post("/{$this->testTenantSlug}/accessible/marketplace/slots/scan", ['qr_code' => 'ABC123'])
            ->assertRedirectContains('/accessible/login');
    }

    public function test_commerce_pickup_scan_empty_code_redirects_with_failure(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['marketplace']);

        $this->post("/{$this->testTenantSlug}/accessible/marketplace/slots/scan", ['qr_code' => '  '])
            ->assertRedirect("/{$this->testTenantSlug}/accessible/marketplace/slots?status=pickup-scan-failed");
    }

    public function test_commerce_pickup_scan_invalid_code_redirects_with_failure(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['marketplace']);

        // An unknown code is rejected by MarketplacePickupSlotService::scanQr
        // (DomainException) and surfaced as the failure status, not a 500.
        $this->post("/{$this->testTenantSlug}/accessible/marketplace/slots/scan", ['qr_code' => 'NOPE-NOT-A-REAL-CODE'])
            ->assertRedirect("/{$this->testTenantSlug}/accessible/marketplace/slots?status=pickup-scan-failed");
    }

    private function enableAlphaFeatures(array $features): void
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

    private function disableMeiliSearch(): void
    {
        $prop = new \ReflectionProperty(\App\Services\SearchService::class, 'available');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
    }

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }
}
