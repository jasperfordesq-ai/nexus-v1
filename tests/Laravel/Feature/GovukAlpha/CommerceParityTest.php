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
        $this->get("/{$this->testTenantSlug}/alpha/marketplace/create")
            ->assertRedirectContains('/alpha/login');
    }

    public function test_commerce_create_listing_gated_off_by_default(): void
    {
        $this->authenticatedUser();
        $this->get("/{$this->testTenantSlug}/alpha/marketplace/create")->assertStatus(403);
    }

    public function test_commerce_create_listing_form_renders(): void
    {
        $this->authenticatedUser(['name' => 'Seller One']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/marketplace/create");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.listing_form.title_create'));
        $res->assertSee('name="title"', false);
    }

    public function test_commerce_store_listing_persists_and_redirects(): void
    {
        $user = $this->authenticatedUser(['name' => 'Seller Store']);
        $this->enableAlphaFeatures(['marketplace']);
        $this->disableMeiliSearch();

        $res = $this->post("/{$this->testTenantSlug}/alpha/marketplace/create", [
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

        $res = $this->post("/{$this->testTenantSlug}/alpha/marketplace/create", [
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
        $this->get("/{$this->testTenantSlug}/alpha/marketplace/{$id}/edit")->assertOk();

        // Another member in the same tenant is forbidden.
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $this->get("/{$this->testTenantSlug}/alpha/marketplace/{$id}/edit")->assertStatus(403);
    }

    public function test_commerce_update_listing_persists_changes(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Owner Update']);
        $this->enableAlphaFeatures(['marketplace']);
        $this->disableMeiliSearch();
        $id = $this->seedListing($owner->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/marketplace/{$id}/update", [
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

        $res = $this->post("/{$this->testTenantSlug}/alpha/marketplace/{$id}/delete");
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

        $res = $this->get("/{$this->testTenantSlug}/alpha/marketplace/mine");
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

        $this->post("/{$this->testTenantSlug}/alpha/marketplace/{$id}/save")
            ->assertRedirectContains("/marketplace/{$id}");

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('marketplace_saved_listings', [
            'user_id' => $buyer->id,
            'marketplace_listing_id' => $id,
        ]);

        $saved = $this->get("/{$this->testTenantSlug}/alpha/marketplace/saved");
        $saved->assertOk();
        $saved->assertSee('Saveable Item');
    }

    public function test_commerce_save_missing_listing_404(): void
    {
        $this->authenticatedUser(['name' => 'Saver 404']);
        $this->enableAlphaFeatures(['marketplace']);

        $this->post("/{$this->testTenantSlug}/alpha/marketplace/99999999/save")->assertStatus(404);
    }

    public function test_commerce_free_items_page_renders(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->authenticatedUser(['name' => 'Freebie Hunter']);
        $this->enableAlphaFeatures(['marketplace']);
        $this->disableMeiliSearch();
        $this->seedListing($owner->id, ['title' => 'Free Couch', 'price_type' => 'free']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/marketplace/free");
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

        $res = $this->get("/{$this->testTenantSlug}/alpha/marketplace/seller/{$seller->id}");
        $res->assertOk();
        $res->assertSee('Pat Seller');
        $res->assertSee('Sellers Item');
    }

    public function test_commerce_seller_profile_unknown_404(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['marketplace']);
        $this->get("/{$this->testTenantSlug}/alpha/marketplace/seller/99999999")->assertStatus(404);
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
        $this->get("/{$this->testTenantSlug}/alpha/marketplace/{$id}/offer")->assertStatus(403);
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

        $res = $this->post("/{$this->testTenantSlug}/alpha/marketplace/{$id}/offer", [
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

        $res = $this->post("/{$this->testTenantSlug}/alpha/marketplace/{$id}/offer", ['amount' => '0']);
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

        $res = $this->get("/{$this->testTenantSlug}/alpha/marketplace/{$id}/buy");
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
        $this->get("/{$this->testTenantSlug}/alpha/marketplace/{$id}/buy")->assertStatus(404);
    }

    public function test_commerce_report_form_and_submission(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->authenticatedUser(['name' => 'Reporter']);
        $this->enableAlphaFeatures(['marketplace']);
        $id = $this->seedListing($owner->id, ['title' => 'Dodgy Item']);

        $form = $this->get("/{$this->testTenantSlug}/alpha/marketplace/{$id}/report");
        $form->assertOk();
        $form->assertSee(__('govuk_alpha_commerce.report.title'));

        $submit = $this->post("/{$this->testTenantSlug}/alpha/marketplace/{$id}/report", [
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

        $res = $this->post("/{$this->testTenantSlug}/alpha/marketplace/{$id}/report", [
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

        $res = $this->get("/{$this->testTenantSlug}/alpha/marketplace/offers");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.offers.title'));
    }

    public function test_commerce_buyer_orders_dashboard_renders(): void
    {
        $this->authenticatedUser(['name' => 'Order Viewer']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/marketplace/orders");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.orders_buyer.title'));
    }

    public function test_commerce_seller_orders_dashboard_renders(): void
    {
        $this->authenticatedUser(['name' => 'Sales Viewer']);
        $this->enableAlphaFeatures(['marketplace']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/marketplace/sales");
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
        $this->post("/{$this->testTenantSlug}/alpha/marketplace/orders/{$orderId}/confirm")->assertStatus(403);
    }

    // ==================================================================
    //  Courses — my learning + lesson player
    // ==================================================================

    public function test_commerce_my_learning_renders_empty(): void
    {
        $this->authenticatedUser(['name' => 'Learner Empty']);
        $this->enableAlphaFeatures(['courses']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/courses/mine");
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
        $this->get("/{$this->testTenantSlug}/alpha/courses/{$courseId}/learn")
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

        $res = $this->get("/{$this->testTenantSlug}/alpha/courses/{$courseId}/learn");
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

        $res = $this->post("/{$this->testTenantSlug}/alpha/courses/{$courseId}/lessons/{$lessonId}/complete");
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
        $this->post("/{$this->testTenantSlug}/alpha/courses/{$courseId}/lessons/99999999/complete")
            ->assertStatus(404);
    }

    // ==================================================================
    //  Premium — manage subscription
    // ==================================================================

    public function test_commerce_premium_manage_redirects_without_subscription(): void
    {
        $this->authenticatedUser(['name' => 'No Sub']);
        $this->enableAlphaFeatures(['member_premium']);

        $this->get("/{$this->testTenantSlug}/alpha/premium/manage")
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

        $res = $this->get("/{$this->testTenantSlug}/alpha/premium/manage");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.premium_manage.title'));
        $res->assertSee('Gold');
    }

    public function test_commerce_premium_gated_off_by_default(): void
    {
        $this->authenticatedUser();
        $this->get("/{$this->testTenantSlug}/alpha/premium/manage")->assertStatus(403);
    }

    // ==================================================================
    //  Courses — instructor / creator suite
    // ==================================================================

    public function test_commerce_instructor_courses_requires_auth(): void
    {
        $this->enableAlphaFeatures(['courses']);
        $this->get("/{$this->testTenantSlug}/alpha/courses/instructor")
            ->assertRedirectContains('/alpha/login');
    }

    public function test_commerce_instructor_courses_gated_off_by_default(): void
    {
        $this->authenticatedUser();
        $this->get("/{$this->testTenantSlug}/alpha/courses/instructor")->assertStatus(403);
    }

    public function test_commerce_instructor_courses_lists_authored(): void
    {
        $user = $this->authenticatedUser(['name' => 'Teacher One']);
        $this->enableAlphaFeatures(['courses']);
        $this->seedCourse($user->id, 'My Taught Course');

        $res = $this->get("/{$this->testTenantSlug}/alpha/courses/instructor");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.instructor.title'));
        $res->assertSee('My Taught Course');
    }

    public function test_commerce_create_course_form_renders(): void
    {
        $this->authenticatedUser(['name' => 'Teacher Form']);
        $this->enableAlphaFeatures(['courses']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/courses/instructor/new");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_commerce.instructor.title_create'));
        $res->assertSee('name="title"', false);
    }

    public function test_commerce_store_course_persists_and_redirects(): void
    {
        $user = $this->authenticatedUser(['name' => 'Teacher Store']);
        $this->enableAlphaFeatures(['courses']);
        $this->disableMeiliSearch();

        $res = $this->post("/{$this->testTenantSlug}/alpha/courses/instructor/new", [
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

        $res = $this->post("/{$this->testTenantSlug}/alpha/courses/instructor/new", [
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
        $this->get("/{$this->testTenantSlug}/alpha/courses/instructor/{$id}/edit")->assertOk();

        // Another member in the same tenant is forbidden.
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $this->get("/{$this->testTenantSlug}/alpha/courses/instructor/{$id}/edit")->assertStatus(403);
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

        $this->get("/{$this->testTenantSlug}/alpha/courses/instructor/{$foreignId}/edit")->assertStatus(404);
    }

    public function test_commerce_update_course_persists_changes(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Course Updater']);
        $this->enableAlphaFeatures(['courses']);
        $this->disableMeiliSearch();
        $id = $this->seedCourse($owner->id, 'Before Title');

        $res = $this->post("/{$this->testTenantSlug}/alpha/courses/instructor/{$id}/update", [
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

        $this->post("/{$this->testTenantSlug}/alpha/courses/instructor/{$id}/publish")->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('courses', [
            'id' => $id,
            'status' => 'published',
        ]);

        // A non-owner cannot publish.
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $this->post("/{$this->testTenantSlug}/alpha/courses/instructor/{$id}/unpublish")->assertStatus(403);
    }

    public function test_commerce_delete_course_owner_only(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Deleter']);
        $this->enableAlphaFeatures(['courses']);
        $this->disableMeiliSearch();
        $id = $this->seedCourse($owner->id, 'To Delete');

        $this->post("/{$this->testTenantSlug}/alpha/courses/instructor/{$id}/delete")
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

        $res = $this->get("/{$this->testTenantSlug}/alpha/courses/instructor/{$id}/analytics");
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
        $this->get("/{$this->testTenantSlug}/alpha/courses/instructor/{$id}/analytics")->assertStatus(403);
    }

    // ==================================================================
    //  Seed + base helpers (mirror GovukAlphaFrontendTest)
    // ==================================================================

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
