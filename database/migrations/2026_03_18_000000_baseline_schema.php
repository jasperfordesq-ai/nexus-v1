<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline schema migration for the Project NEXUS database.
 *
 * This migration represents the current production schema as of March 2026.
 * On existing databases (production), every table already exists so every
 * Schema::create() call is guarded by Schema::hasTable() and will be skipped.
 *
 * On fresh databases, this creates all ~35 core tables so that
 * `php artisan migrate` produces a working schema from scratch.
 *
 * The down() method is intentionally empty — this baseline is irreversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // 1. TENANTS — root table for multi-tenancy
        // ------------------------------------------------------------------
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->string('slug', 100)->unique();
                $table->string('domain', 255)->nullable();
                $table->string('subdomain', 100)->nullable()->unique();
                $table->text('description')->nullable();
                $table->string('tagline', 500)->nullable();
                $table->string('logo', 500)->nullable();
                $table->string('favicon', 500)->nullable();
                $table->string('timezone', 50)->default('UTC');
                $table->string('currency', 10)->default('hours');
                $table->string('locale', 10)->default('en');
                $table->json('features')->nullable()->comment('JSON feature flags');
                $table->json('settings')->nullable()->comment('JSON tenant settings');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_federation_enabled')->default(false);
                $table->boolean('allows_subtenants')->default(false);
                $table->unsignedInteger('depth')->default(0);
                $table->json('configuration')->nullable();
                $table->string('plan', 50)->default('free');
                $table->unsignedInteger('max_users')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index('is_active');
                $table->index('plan');
            });
        }

        // ------------------------------------------------------------------
        // 2. USERS — multi-tenant user accounts
        // ------------------------------------------------------------------
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('name', 255);
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('username', 100)->nullable();
                $table->string('email', 255);
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password_hash', 255)->default('');
                $table->string('remember_token', 100)->nullable();
                $table->string('phone', 50)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->text('bio')->nullable();
                $table->string('location', 255)->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->string('avatar', 500)->nullable();
                $table->string('avatar_url', 500)->nullable();
                $table->string('cover_photo', 500)->nullable();
                $table->string('profile_type', 50)->default('individual');
                $table->string('organization_name', 255)->nullable();
                $table->string('tagline', 255)->nullable();
                $table->string('role', 50)->default('member');
                $table->boolean('is_admin')->default(false);
                $table->boolean('is_super_admin')->default(false);
                $table->boolean('is_god')->default(false);
                $table->boolean('is_tenant_super_admin')->default(false);
                $table->boolean('is_verified')->default(false);
                $table->boolean('is_approved')->default(true);
                $table->boolean('is_active')->default(true);
                $table->string('status', 50)->default('active');
                $table->string('preferred_language', 10)->default('en');
                $table->decimal('balance', 10, 2)->default(0);
                $table->unsignedInteger('xp_points')->default(0);
                $table->unsignedInteger('xp')->default(0);
                $table->unsignedInteger('level')->default(1);
                $table->text('skills')->nullable();
                $table->boolean('onboarding_completed')->default(false);
                $table->string('privacy_profile', 50)->default('public');
                $table->string('privacy_search', 50)->default('public');
                $table->string('privacy_contact', 50)->default('public');
                $table->boolean('totp_enabled')->default(false);
                $table->text('totp_secret')->nullable();
                $table->text('totp_backup_codes')->nullable();
                $table->json('notification_preferences')->nullable();
                $table->json('email_preferences')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->string('reset_token', 255)->nullable();
                $table->timestamp('reset_token_expiry')->nullable();
                $table->timestamp('last_login')->nullable();
                $table->timestamp('last_active')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->unique(['tenant_id', 'email']);
                $table->unique(['tenant_id', 'username']);
                $table->index('tenant_id');
                $table->index('role');
                $table->index('status');
                $table->index('email_verified_at');
                $table->index(['tenant_id', 'is_active']);
                $table->index(['tenant_id', 'role']);
            });
        }

        // ------------------------------------------------------------------
        // 3. CATEGORIES
        // ------------------------------------------------------------------
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->text('description')->nullable();
                $table->string('icon', 100)->nullable();
                $table->string('color', 20)->nullable();
                $table->unsignedInteger('parent_id')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->unique(['tenant_id', 'slug']);
                $table->index('tenant_id');
                $table->index('parent_id');
            });
        }

        // ------------------------------------------------------------------
        // 4. LISTINGS — service offers/requests
        // ------------------------------------------------------------------
        if (! Schema::hasTable('listings')) {
            Schema::create('listings', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('category_id')->nullable();
                $table->string('title', 255);
                $table->string('slug', 255)->nullable();
                $table->text('description')->nullable();
                $table->enum('type', ['offer', 'request'])->default('offer');
                $table->string('status', 50)->default('active');
                $table->decimal('time_credits', 10, 2)->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->decimal('hours_estimate', 10, 2)->nullable();
                $table->string('service_type', 50)->default('in-person');
                $table->string('location', 255)->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->boolean('is_virtual')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->timestamp('featured_until')->nullable();
                $table->string('image_url', 500)->nullable();
                $table->unsignedInteger('subcategory_id')->nullable();
                $table->text('sdg_goals')->nullable();
                $table->string('federated_visibility', 50)->default('none');
                $table->boolean('direct_messaging_disabled')->default(false);
                $table->boolean('exchange_workflow_required')->default(false);
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedInteger('contact_count')->default(0);
                $table->unsignedInteger('save_count')->default(0);
                $table->unsignedInteger('views')->default(0);
                $table->timestamp('renewed_at')->nullable();
                $table->unsignedInteger('renewal_count')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'category_id']);
                $table->index(['tenant_id', 'type']);
            });
        }

        // ------------------------------------------------------------------
        // 5. EVENTS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('title', 255);
                $table->string('slug', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('location', 255)->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->boolean('is_virtual')->default(false);
                $table->string('virtual_link', 500)->nullable();
                $table->dateTime('start_date');
                $table->dateTime('end_date')->nullable();
                $table->unsignedInteger('max_attendees')->nullable();
                $table->string('image', 500)->nullable();
                $table->unsignedInteger('category_id')->nullable();
                $table->enum('status', ['active', 'cancelled', 'completed', 'draft'])->default('active');
                $table->boolean('is_featured')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'start_date']);
                $table->index(['tenant_id', 'user_id']);
            });
        }

        // ------------------------------------------------------------------
        // 6. EVENT_RSVPS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('event_rsvps')) {
            Schema::create('event_rsvps', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('event_id');
                $table->unsignedInteger('user_id');
                $table->enum('status', ['going', 'interested', 'not_going'])->default('going');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['tenant_id', 'event_id', 'user_id']);
                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // 7. GROUPS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('groups')) {
            Schema::create('groups', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('created_by');
                $table->string('name', 255);
                $table->string('slug', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('image', 500)->nullable();
                $table->string('cover_image', 500)->nullable();
                $table->enum('privacy', ['public', 'private', 'secret'])->default('public');
                $table->unsignedInteger('category_id')->nullable();
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('member_count')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'privacy']);
                $table->index(['tenant_id', 'is_active']);
                $table->index(['tenant_id', 'created_by']);
            });
        }

        // ------------------------------------------------------------------
        // 8. GROUP_MEMBERS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('group_members')) {
            Schema::create('group_members', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('group_id');
                $table->unsignedInteger('user_id');
                $table->enum('role', ['member', 'admin', 'moderator'])->default('member');
                $table->enum('status', ['active', 'pending', 'banned'])->default('active');
                $table->timestamp('joined_at')->useCurrent();

                $table->unique(['tenant_id', 'group_id', 'user_id']);
                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // 9. TRANSACTIONS — time credit exchanges
        // ------------------------------------------------------------------
        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('sender_id');
                $table->unsignedInteger('receiver_id');
                $table->unsignedInteger('listing_id')->nullable();
                $table->decimal('amount', 10, 2);
                $table->text('description')->nullable();
                $table->enum('status', ['pending', 'completed', 'cancelled', 'disputed'])->default('pending');
                $table->enum('type', ['exchange', 'reward', 'adjustment', 'transfer'])->default('exchange');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'sender_id']);
                $table->index(['tenant_id', 'receiver_id']);
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'listing_id']);
            });
        }

        // ------------------------------------------------------------------
        // 10. REVIEWS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('reviewer_id');
                $table->unsignedInteger('reviewed_id');
                $table->unsignedInteger('transaction_id')->nullable();
                $table->unsignedInteger('listing_id')->nullable();
                $table->tinyInteger('rating')->unsigned();
                $table->text('comment')->nullable();
                $table->enum('status', ['active', 'flagged', 'removed'])->default('active');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'reviewed_id']);
                $table->index(['tenant_id', 'reviewer_id']);
                $table->index(['tenant_id', 'listing_id']);
            });
        }

        // ------------------------------------------------------------------
        // 11. MESSAGES
        // ------------------------------------------------------------------
        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('sender_id');
                $table->unsignedInteger('receiver_id');
                $table->unsignedInteger('listing_id')->nullable();
                $table->text('content');
                $table->boolean('is_read')->default(false);
                $table->boolean('is_deleted_sender')->default(false);
                $table->boolean('is_deleted_receiver')->default(false);
                $table->boolean('is_edited')->default(false);
                $table->timestamp('edited_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'sender_id']);
                $table->index(['tenant_id', 'receiver_id']);
                $table->index(['tenant_id', 'is_read']);
            });
        }

        // ------------------------------------------------------------------
        // 12. CONNECTIONS — user-to-user relationships
        // ------------------------------------------------------------------
        if (! Schema::hasTable('connections')) {
            Schema::create('connections', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('connected_user_id');
                $table->enum('status', ['pending', 'accepted', 'rejected', 'blocked'])->default('pending');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->unique(['tenant_id', 'user_id', 'connected_user_id']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // ------------------------------------------------------------------
        // 13. NOTIFICATIONS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('title', 255)->nullable();
                $table->string('type', 50);
                $table->text('message');
                $table->string('link', 500)->nullable();
                $table->boolean('is_read')->default(false);
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'user_id', 'is_read']);
                $table->index(['tenant_id', 'user_id', 'created_at']);
            });
        }

        // ------------------------------------------------------------------
        // 14. FEED_POSTS — community feed
        // ------------------------------------------------------------------
        if (! Schema::hasTable('feed_posts')) {
            Schema::create('feed_posts', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->text('content')->nullable();
                $table->string('type', 50)->default('post');
                $table->string('image', 500)->nullable();
                $table->unsignedInteger('group_id')->nullable();
                $table->unsignedInteger('likes_count')->default(0);
                $table->unsignedInteger('comments_count')->default(0);
                $table->unsignedInteger('shares_count')->default(0);
                $table->boolean('is_pinned')->default(false);
                $table->enum('visibility', ['public', 'connections', 'group', 'private'])->default('public');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'group_id']);
                $table->index(['tenant_id', 'created_at']);
                $table->index(['tenant_id', 'visibility']);
            });
        }

        // ------------------------------------------------------------------
        // 15. FEED_ACTIVITIES — activity stream
        // ------------------------------------------------------------------
        if (! Schema::hasTable('feed_activities')) {
            Schema::create('feed_activities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('activity_type', 50);
                $table->string('entity_type', 50)->nullable();
                $table->unsignedInteger('entity_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'activity_type']);
                $table->index(['tenant_id', 'created_at']);
            });
        }

        // ------------------------------------------------------------------
        // 16. FEED_LIKES
        // ------------------------------------------------------------------
        if (! Schema::hasTable('feed_likes')) {
            Schema::create('feed_likes', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('post_id');
                $table->unsignedInteger('user_id');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['tenant_id', 'post_id', 'user_id']);
            });
        }

        // ------------------------------------------------------------------
        // 17. FEED_COMMENTS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('feed_comments')) {
            Schema::create('feed_comments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('post_id');
                $table->unsignedInteger('user_id');
                $table->text('content');
                $table->unsignedInteger('parent_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'post_id']);
                $table->index('parent_id');
            });
        }

        // ------------------------------------------------------------------
        // 18. PAGES — CMS pages
        // ------------------------------------------------------------------
        if (! Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('title', 255);
                $table->string('slug', 255);
                $table->longText('content')->nullable();
                $table->text('meta_description')->nullable();
                $table->enum('status', ['published', 'draft', 'archived'])->default('draft');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->unique(['tenant_id', 'slug']);
                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // 19. MENU_ITEMS — navigation menus
        // ------------------------------------------------------------------
        if (! Schema::hasTable('menu_items')) {
            Schema::create('menu_items', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('label', 255);
                $table->string('url', 500)->nullable();
                $table->string('icon', 100)->nullable();
                $table->unsignedInteger('parent_id')->nullable();
                $table->string('menu_location', 50)->default('main');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('target', 20)->default('_self');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'menu_location']);
                $table->index('parent_id');
            });
        }

        // ------------------------------------------------------------------
        // 20. SKILLS — user skills/competencies
        // ------------------------------------------------------------------
        if (! Schema::hasTable('skills')) {
            Schema::create('skills', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('name', 255);
                $table->string('slug', 255)->nullable();
                $table->unsignedInteger('category_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['tenant_id', 'name']);
                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // 21. USER_SKILLS — many-to-many pivot
        // ------------------------------------------------------------------
        if (! Schema::hasTable('user_skills')) {
            Schema::create('user_skills', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('skill_id');
                $table->enum('type', ['offer', 'request'])->default('offer');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['tenant_id', 'user_id', 'skill_id', 'type']);
                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // 22. BADGES — gamification
        // ------------------------------------------------------------------
        if (! Schema::hasTable('badges')) {
            Schema::create('badges', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('name', 255);
                $table->string('slug', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('icon', 500)->nullable();
                $table->string('criteria_type', 50)->nullable();
                $table->unsignedInteger('criteria_value')->nullable();
                $table->unsignedInteger('xp_reward')->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // 23. USER_BADGES — earned badges
        // ------------------------------------------------------------------
        if (! Schema::hasTable('user_badges')) {
            Schema::create('user_badges', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('badge_id');
                $table->timestamp('earned_at')->useCurrent();

                $table->unique(['tenant_id', 'user_id', 'badge_id']);
                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // 24. GOALS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('goals')) {
            Schema::create('goals', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
                $table->decimal('target_value', 10, 2)->default(0);
                $table->decimal('current_value', 10, 2)->default(0);
                $table->date('deadline')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // ------------------------------------------------------------------
        // 25. POLLS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('polls')) {
            Schema::create('polls', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('question', 500);
                $table->enum('status', ['active', 'closed'])->default('active');
                $table->boolean('allow_multiple')->default(false);
                $table->dateTime('closes_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'status']);
            });
        }

        // ------------------------------------------------------------------
        // 26. POLL_OPTIONS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('poll_options')) {
            Schema::create('poll_options', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('poll_id');
                $table->string('text', 255);
                $table->unsignedInteger('votes')->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->index('poll_id');
            });
        }

        // ------------------------------------------------------------------
        // 27. POLL_VOTES
        // ------------------------------------------------------------------
        if (! Schema::hasTable('poll_votes')) {
            Schema::create('poll_votes', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('poll_id');
                $table->unsignedInteger('option_id');
                $table->unsignedInteger('user_id');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['tenant_id', 'poll_id', 'user_id', 'option_id']);
            });
        }

        // ------------------------------------------------------------------
        // 28. NEWSLETTERS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('newsletters')) {
            Schema::create('newsletters', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('name', 255)->nullable();
                $table->string('subject', 255);
                $table->longText('content');
                $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed'])->default('draft');
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('open_count')->default(0);
                $table->unsignedInteger('click_count')->default(0);
                $table->dateTime('scheduled_at')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'status']);
            });
        }

        // ------------------------------------------------------------------
        // 29. NEWSLETTER_SUBSCRIBERS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('newsletter_subscribers')) {
            Schema::create('newsletter_subscribers', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('email', 255);
                $table->string('name', 255)->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->enum('status', ['subscribed', 'unsubscribed', 'bounced'])->default('subscribed');
                $table->timestamp('subscribed_at')->useCurrent();
                $table->timestamp('unsubscribed_at')->nullable();

                $table->unique(['tenant_id', 'email']);
                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // 30. REPORTS — content moderation
        // ------------------------------------------------------------------
        if (! Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('reporter_id');
                $table->string('entity_type', 50);
                $table->unsignedInteger('entity_id');
                $table->string('reason', 100);
                $table->text('description')->nullable();
                $table->enum('status', ['open', 'investigating', 'resolved', 'dismissed'])->default('open');
                $table->unsignedInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'entity_type', 'entity_id']);
            });
        }

        // ------------------------------------------------------------------
        // 31. RESOURCE_ITEMS — shared resources/documents
        // ------------------------------------------------------------------
        if (! Schema::hasTable('resource_items')) {
            Schema::create('resource_items', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('file_path', 500)->nullable();
                $table->string('url', 500)->nullable();
                $table->string('type', 50)->nullable();
                $table->unsignedInteger('category_id')->nullable();
                $table->unsignedInteger('download_count')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'category_id']);
            });
        }

        // ------------------------------------------------------------------
        // 32. SESSIONS — user session tracking
        // ------------------------------------------------------------------
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id', 255)->primary();
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('tenant_id')->nullable();
                $table->text('session_data')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('last_activity')->useCurrent()->useCurrentOnUpdate();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_authenticated')->default(false);
                $table->enum('device_type', ['desktop', 'mobile', 'tablet', 'unknown'])->default('unknown');

                $table->index('user_id');
                $table->index('tenant_id');
                $table->index('last_activity');
                $table->index('expires_at');
                $table->index(['user_id', 'tenant_id']);
            });
        }

        // ------------------------------------------------------------------
        // 33. CONTACT_SUBMISSIONS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('contact_submissions')) {
            Schema::create('contact_submissions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('name', 255);
                $table->string('email', 255);
                $table->string('subject', 255)->nullable();
                $table->text('message');
                $table->enum('status', ['new', 'read', 'replied', 'archived'])->default('new');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->dateTime('replied_at')->nullable();
                $table->unsignedInteger('replied_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'status']);
            });
        }

        // ------------------------------------------------------------------
        // 34. ACTIVITY_LOG — admin audit trail
        // ------------------------------------------------------------------
        if (! Schema::hasTable('activity_log')) {
            Schema::create('activity_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('action', 100);
                $table->string('entity_type', 50)->nullable();
                $table->unsignedInteger('entity_id')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'action']);
                $table->index('created_at');
            });
        }

        // ------------------------------------------------------------------
        // 35. TENANT_SETTINGS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('tenant_settings')) {
            Schema::create('tenant_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('setting_key', 255);
                $table->text('setting_value')->nullable();
                $table->string('setting_type', 50)->default('string');
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->unique(['tenant_id', 'setting_key']);
                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // 36. BLOG_POSTS
        // ------------------------------------------------------------------
        if (! Schema::hasTable('blog_posts')) {
            Schema::create('blog_posts', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('author_id');
                $table->string('title', 255);
                $table->string('slug', 255);
                $table->text('excerpt')->nullable();
                $table->longText('content');
                $table->string('featured_image', 500)->nullable();
                $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
                $table->dateTime('published_at')->nullable();
                $table->unsignedInteger('views')->default(0);
                $table->string('meta_title', 255)->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'status']);
                $table->index('published_at');
                $table->index('author_id');
            });
        }

        // ------------------------------------------------------------------
        // 37. MATCHES — smart matching
        // ------------------------------------------------------------------
        if (! Schema::hasTable('matches')) {
            Schema::create('matches', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('matched_user_id');
                $table->unsignedInteger('listing_id')->nullable();
                $table->decimal('score', 5, 2)->default(0);
                $table->string('match_type', 50)->nullable();
                $table->enum('status', ['pending', 'accepted', 'rejected', 'expired'])->default('pending');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // ------------------------------------------------------------------
        // 38. VOLUNTEERING tables
        // ------------------------------------------------------------------
        if (! Schema::hasTable('vol_opportunities')) {
            Schema::create('vol_opportunities', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('organization_id')->nullable();
                $table->unsignedInteger('created_by');
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('location', 255)->nullable();
                $table->boolean('is_virtual')->default(false);
                $table->dateTime('start_date')->nullable();
                $table->dateTime('end_date')->nullable();
                $table->unsignedInteger('spots_available')->nullable();
                $table->enum('status', ['active', 'filled', 'completed', 'cancelled'])->default('active');
                $table->decimal('hours_credit', 10, 2)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'organization_id']);
            });
        }

        if (! Schema::hasTable('vol_applications')) {
            Schema::create('vol_applications', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('opportunity_id');
                $table->unsignedInteger('user_id');
                $table->text('message')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'withdrawn'])->default('pending');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->unique(['tenant_id', 'opportunity_id', 'user_id']);
                $table->index('tenant_id');
            });
        }

        if (! Schema::hasTable('vol_logs')) {
            Schema::create('vol_logs', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('opportunity_id')->nullable();
                $table->decimal('hours', 10, 2);
                $table->text('description')->nullable();
                $table->date('date');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->unsignedInteger('approved_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // ------------------------------------------------------------------
        // FEDERATION TENANT FEATURES
        // ------------------------------------------------------------------
        if (! Schema::hasTable('federation_tenant_features')) {
            Schema::create('federation_tenant_features', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('feature_key', 100);
                $table->boolean('is_enabled')->default(false);
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
                $table->unsignedInteger('updated_by')->nullable();

                $table->unique(['tenant_id', 'feature_key'], 'unique_tenant_feature');
                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // LIKES — generic likes (listings, feed posts, etc.)
        // ------------------------------------------------------------------
        if (! Schema::hasTable('likes')) {
            Schema::create('likes', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('target_type', 50);
                $table->unsignedInteger('target_id');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['user_id', 'target_type', 'target_id']);
                $table->index(['tenant_id', 'target_type', 'target_id']);
            });
        }

        // ------------------------------------------------------------------
        // LISTING_SKILL_TAGS — skill tags on listings
        // ------------------------------------------------------------------
        if (! Schema::hasTable('listing_skill_tags')) {
            Schema::create('listing_skill_tags', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('listing_id');
                $table->unsignedInteger('skill_id')->nullable();
                $table->string('tag_name', 255);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['listing_id', 'tenant_id']);
                $table->index('tenant_id');
            });
        }

        // ------------------------------------------------------------------
        // SANCTUM — Personal Access Tokens
        // ------------------------------------------------------------------
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // ------------------------------------------------------------------
        // USER_SAVED_LISTINGS — Favourited listings
        // ------------------------------------------------------------------
        if (! Schema::hasTable('user_saved_listings')) {
            Schema::create('user_saved_listings', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('listing_id');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['user_id', 'listing_id']);
                $table->index('tenant_id');
                $table->index('listing_id');
            });
        }

        // ------------------------------------------------------------------
        // LOGIN_ATTEMPTS — Rate limiting for brute force protection
        // ------------------------------------------------------------------
        if (! Schema::hasTable('login_attempts')) {
            Schema::create('login_attempts', function (Blueprint $table) {
                $table->id();
                $table->string('identifier', 255);
                $table->enum('type', ['email', 'ip'])->default('email');
                $table->string('ip_address', 45);
                $table->boolean('success')->default(false);
                $table->timestamp('attempted_at');

                $table->index(['identifier', 'type']);
                $table->index('attempted_at');
            });
        }
    }

    /**
     * Baseline migration is irreversible — the database existed before Laravel.
     */
    public function down(): void
    {
        // Intentionally empty. This baseline represents an existing production
        // database and cannot be safely reversed.
    }
};
