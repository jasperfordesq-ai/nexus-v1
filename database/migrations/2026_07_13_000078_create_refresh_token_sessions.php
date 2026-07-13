<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Persist only hashed state for rotating refresh-token families. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_token_sessions', function (Blueprint $table): void {
            $table->id();
            $table->integer('tenant_id');
            $table->integer('user_id');
            $table->char('family_hash', 64);
            $table->char('jti_hash', 64);
            $table->char('parent_jti_hash', 64)->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('family_expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 40)->nullable();
            $table->timestamps();

            $table->unique('jti_hash', 'uq_refresh_sessions_jti');
            // Each single-use token can have at most one direct successor.
            // MariaDB permits multiple NULL values in this unique index, so
            // initial family rows remain valid while branching is rejected.
            $table->unique('parent_jti_hash', 'uq_refresh_sessions_parent');
            $table->index(
                ['tenant_id', 'user_id', 'revoked_at'],
                'idx_refresh_sessions_tenant_user'
            );
            $table->index(
                ['user_id', 'tenant_id'],
                'idx_refresh_sessions_user_tenant'
            );
            $table->index(
                ['family_hash', 'revoked_at'],
                'idx_refresh_sessions_family'
            );
            $table->index('expires_at', 'idx_refresh_sessions_expiry');
            $table->index(
                'family_expires_at',
                'idx_refresh_sessions_family_expiry'
            );

            $table->foreign('tenant_id', 'fk_refresh_sessions_tenant')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            // Enforce that the stored tenant is the user's actual tenant, not
            // merely that both independent identifiers happen to exist.
            $table->foreign(
                ['user_id', 'tenant_id'],
                'fk_refresh_sessions_user_tenant'
            )
                ->references(['id', 'tenant_id'])
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_token_sessions');
    }
};
