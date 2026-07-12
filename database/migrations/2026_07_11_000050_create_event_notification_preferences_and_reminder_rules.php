<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SCOPE_CHECK = 'chk_event_notification_preference_scope';
    private const CADENCE_CHECK = 'chk_event_notification_preference_cadence';
    private const RULE_OFFSET_CHECK = 'chk_event_reminder_rule_offset';

    public function up(): void
    {
        if (! Schema::hasTable('events')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('categories')) {
            return;
        }

        if (! Schema::hasTable('event_notification_preferences')) {
            Schema::create('event_notification_preferences', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('user_id');
                $table->integer('event_id')->nullable();
                $table->integer('category_id')->nullable();
                $table->boolean('email_enabled')->nullable();
                $table->boolean('in_app_enabled')->nullable();
                $table->boolean('web_push_enabled')->nullable();
                $table->boolean('fcm_enabled')->nullable();
                $table->boolean('realtime_enabled')->nullable();
                $table->string('cadence', 16)->nullable();
                $table->boolean('reminders_enabled')->nullable();
                $table->unsignedBigInteger('preference_version')->default(1);
                $table->timestamps();

                $table->unique(
                    ['tenant_id', 'user_id', 'event_id'],
                    'uq_event_notification_preference_event',
                );
                $table->unique(
                    ['tenant_id', 'user_id', 'category_id'],
                    'uq_event_notification_preference_category',
                );
                $table->index(
                    ['tenant_id', 'event_id', 'user_id'],
                    'idx_event_notification_preference_event',
                );
                $table->index(
                    ['tenant_id', 'category_id', 'user_id'],
                    'idx_event_notification_preference_category',
                );

                $table->foreign('tenant_id', 'fk_event_notification_preference_tenant')
                    ->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('user_id', 'fk_event_notification_preference_user')
                    ->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('event_id', 'fk_event_notification_preference_event')
                    ->references('id')->on('events')->cascadeOnDelete();
                $table->foreign('category_id', 'fk_event_notification_preference_category')
                    ->references('id')->on('categories')->cascadeOnDelete();
            });

        }

        if (! Schema::hasTable('event_reminder_rules')) {
            Schema::create('event_reminder_rules', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('event_id');
                $table->integer('user_id');
                $table->unsignedInteger('offset_minutes');
                $table->boolean('email_enabled')->nullable();
                $table->boolean('in_app_enabled')->nullable();
                $table->boolean('web_push_enabled')->nullable();
                $table->boolean('fcm_enabled')->nullable();
                $table->boolean('realtime_enabled')->nullable();
                $table->boolean('enabled')->default(true);
                $table->unsignedBigInteger('rule_version')->default(1);
                $table->timestamps();

                $table->unique(
                    ['tenant_id', 'event_id', 'user_id', 'offset_minutes'],
                    'uq_event_reminder_rule_offset',
                );
                $table->index(
                    ['tenant_id', 'event_id', 'user_id', 'enabled', 'offset_minutes'],
                    'idx_event_reminder_rule_subject',
                );
                $table->index(
                    ['tenant_id', 'user_id', 'enabled', 'event_id'],
                    'idx_event_reminder_rule_user',
                );

                $table->foreign('tenant_id', 'fk_event_reminder_rule_tenant')
                    ->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('event_id', 'fk_event_reminder_rule_event')
                    ->references('id')->on('events')->cascadeOnDelete();
                $table->foreign('user_id', 'fk_event_reminder_rule_user')
                    ->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $this->addCheckIfMissing(
                'event_notification_preferences',
                self::SCOPE_CHECK,
                '((`event_id` IS NOT NULL AND `category_id` IS NULL)'
                    . ' OR (`event_id` IS NULL AND `category_id` IS NOT NULL))',
            );
            $this->addCheckIfMissing(
                'event_notification_preferences',
                self::CADENCE_CHECK,
                '(`cadence` IS NULL OR `cadence` IN (\'instant\', \'daily\', \'monthly\', \'off\'))',
            );
            $this->addCheckIfMissing(
                'event_reminder_rules',
                self::RULE_OFFSET_CHECK,
                '(`offset_minutes` > 0)',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminder_rules');
        Schema::dropIfExists('event_notification_preferences');
    }

    private function addCheckIfMissing(string $table, string $name, string $expression): void
    {
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->exists();
        if (! $exists) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK {$expression}");
        }
    }
};
