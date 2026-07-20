<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('webauthn_credentials')) {
            return;
        }

        Schema::create('webauthn_credentials', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tenant_id');
            // Unpadded base64url credential ID – widened to full WebAuthn maximum
            // by the follow-on hardening migration (000045).
            $table->string('credential_id', 1364)->charset('ascii')->collation('ascii_bin')->unique();
            $table->text('public_key')->comment('PEM-format public key from lbuchs/WebAuthn library');
            $table->unsignedBigInteger('sign_count')->default(0)->comment('WebAuthn signature counter for clone detection');
            $table->string('transports', 512)->nullable()->comment('JSON-encoded array of authenticator transports');
            $table->string('attestation_type', 64)->nullable()->comment('Attestation statement format identifier');
            $table->string('device_name', 100)->nullable()->comment('User-supplied device label for this passkey');
            $table->string('authenticator_type', 30)->nullable()->comment('platform | cross-platform');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'tenant_id'], 'idx_webauthn_user_tenant');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};