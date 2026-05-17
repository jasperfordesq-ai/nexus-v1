<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email observability: per-send audit trail + SendGrid suppression cache.
 *
 * `email_log` answers the "did Joe Bloggs actually get his welcome email?"
 * question without hand-grepping production logs. Every Mailer::send() writes
 * one row. SendGrid's optional event webhook can later update `delivered_at`
 * / `bounced_at` / `opened_at` so we can chart real deliverability per tenant.
 *
 * `email_suppression` is a local cache of SendGrid's suppression lists
 * (bounces, blocks, invalid emails, spam reports). When SendGrid blocks an
 * address we now know about it locally — the Mailer can skip future sends
 * to the same address (saves SendGrid quota and produces a useful "this
 * member's email is invalid" indicator in the admin UI).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_log', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('tenant_id')->nullable()->index();
            $t->unsignedInteger('user_id')->nullable()->index();
            $t->string('recipient_email', 255)->index();
            $t->string('category', 64)->nullable()->index()
                ->comment('Free-form category supplied by the caller (welcome, password_reset, transaction, digest, etc.)');
            $t->string('subject', 255)->nullable();
            $t->enum('provider', ['sendgrid', 'gmail_api', 'smtp'])->nullable();
            $t->enum('status', ['queued', 'sent', 'failed', 'suppressed', 'bounced', 'delivered'])
                ->default('queued')->index();
            $t->string('provider_message_id', 255)->nullable()->index();
            $t->text('error')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('bounced_at')->nullable();
            $t->timestamp('opened_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'created_at']);
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('email_suppression', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('email', 255);
            $t->enum('reason', ['bounce', 'block', 'invalid', 'spam_report', 'unsubscribe'])->index();
            $t->text('detail')->nullable();
            $t->timestamp('suppressed_at');
            $t->timestamps();

            $t->unique(['email', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppression');
        Schema::dropIfExists('email_log');
    }
};
