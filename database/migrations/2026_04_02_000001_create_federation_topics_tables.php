<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Master list of federation topics / interest tags
        Schema::create('federation_topics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('icon', 50)->nullable();        // lucide icon name
            $table->string('category', 50)->nullable();     // grouping: care, skills, creative, etc.
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Junction: which topics a tenant has selected
        Schema::create('federation_tenant_topics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('topic_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'topic_id']);
            $table->index('topic_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('topic_id')->references('id')->on('federation_topics')->cascadeOnDelete();
        });

        // Seed predefined topics
        $now = now();
        $topics = [
            // Care
            ['name' => 'Elder Care', 'slug' => 'elder-care', 'icon' => 'heart-handshake', 'category' => 'care', 'sort_order' => 1],
            ['name' => 'Childcare', 'slug' => 'childcare', 'icon' => 'baby', 'category' => 'care', 'sort_order' => 2],
            ['name' => 'Pet Care', 'slug' => 'pet-care', 'icon' => 'paw-print', 'category' => 'care', 'sort_order' => 3],
            ['name' => 'Mental Health', 'slug' => 'mental-health', 'icon' => 'brain', 'category' => 'care', 'sort_order' => 4],
            ['name' => 'Disability Support', 'slug' => 'disability-support', 'icon' => 'accessibility', 'category' => 'care', 'sort_order' => 5],

            // Skills
            ['name' => 'Education & Tutoring', 'slug' => 'education-tutoring', 'icon' => 'graduation-cap', 'category' => 'skills', 'sort_order' => 10],
            ['name' => 'Technology & Digital', 'slug' => 'technology-digital', 'icon' => 'laptop', 'category' => 'skills', 'sort_order' => 11],
            ['name' => 'Language Exchange', 'slug' => 'language-exchange', 'icon' => 'languages', 'category' => 'skills', 'sort_order' => 12],
            ['name' => 'Professional Skills', 'slug' => 'professional-skills', 'icon' => 'briefcase', 'category' => 'skills', 'sort_order' => 13],

            // Creative
            ['name' => 'Arts & Crafts', 'slug' => 'arts-crafts', 'icon' => 'palette', 'category' => 'creative', 'sort_order' => 20],
            ['name' => 'Music', 'slug' => 'music', 'icon' => 'music', 'category' => 'creative', 'sort_order' => 21],
            ['name' => 'Writing & Storytelling', 'slug' => 'writing-storytelling', 'icon' => 'pen-tool', 'category' => 'creative', 'sort_order' => 22],

            // Home & Garden
            ['name' => 'Home Repair & DIY', 'slug' => 'home-repair-diy', 'icon' => 'wrench', 'category' => 'home', 'sort_order' => 30],
            ['name' => 'Gardening & Agriculture', 'slug' => 'gardening-agriculture', 'icon' => 'sprout', 'category' => 'home', 'sort_order' => 31],
            ['name' => 'Cooking & Food', 'slug' => 'cooking-food', 'icon' => 'chef-hat', 'category' => 'home', 'sort_order' => 32],

            // Health & Fitness
            ['name' => 'Health & Wellness', 'slug' => 'health-wellness', 'icon' => 'heart-pulse', 'category' => 'health', 'sort_order' => 40],
            ['name' => 'Sports & Fitness', 'slug' => 'sports-fitness', 'icon' => 'dumbbell', 'category' => 'health', 'sort_order' => 41],

            // Community
            ['name' => 'Community Events', 'slug' => 'community-events', 'icon' => 'calendar-heart', 'category' => 'community', 'sort_order' => 50],
            ['name' => 'Social Inclusion', 'slug' => 'social-inclusion', 'icon' => 'users', 'category' => 'community', 'sort_order' => 51],
            ['name' => 'Environmental', 'slug' => 'environmental', 'icon' => 'leaf', 'category' => 'community', 'sort_order' => 52],
            ['name' => 'Neighbourhood Watch', 'slug' => 'neighbourhood-watch', 'icon' => 'shield', 'category' => 'community', 'sort_order' => 53],

            // Services
            ['name' => 'Transportation', 'slug' => 'transportation', 'icon' => 'car', 'category' => 'services', 'sort_order' => 60],
            ['name' => 'Admin & Paperwork', 'slug' => 'admin-paperwork', 'icon' => 'file-text', 'category' => 'services', 'sort_order' => 61],
            ['name' => 'Shopping & Errands', 'slug' => 'shopping-errands', 'icon' => 'shopping-bag', 'category' => 'services', 'sort_order' => 62],
        ];

        foreach ($topics as $topic) {
            DB::table('federation_topics')->insert(array_merge($topic, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_tenant_topics');
        Schema::dropIfExists('federation_topics');
    }
};
