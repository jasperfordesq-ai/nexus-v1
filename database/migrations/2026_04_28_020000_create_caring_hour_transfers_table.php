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
        if (Schema::hasTable('caring_hour_transfers')) {
            return;
        }

        Schema::create('caring_hour_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Tenant that owns this row — both source and destination get rows
            $table->unsignedInteger('tenant_id')->index();
            // Slug of the *other* tenant (counterpart)
            $table->string('counterpart_tenant_slug', 50);
            // Whether this row represents the source side or the destination side
            $table->enum('role', ['source', 'destination']);
            // The user this row belongs to (in `tenant_id`'s scope)
            $table->unsignedInteger('member_user_id');
            // Email of the matching user on the counterpart tenant (used to find them on delivery)
            $table->string('counterpart_member_email', 255);
            $table->decimal('hours_transferred', 8, 2);
            $table->enum('status', [
                'pending',
                'approved_by_source',
                'sent',
                'received',
                'completed',
                'rejected',
            ])->default('pending');
            $table->text('reason')->nullable();
            $table->string('signature', 128)->nullable();
            $table->json('payload_json')->nullable();
            // Cross-row link: when the destination row is inserted it stores the source row id, and vice versa
            $table->unsignedBigInteger('linked_transfer_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'role', 'status'], 'idx_caring_hour_xfer_tenant_role_status');
            $table->index(['tenant_id', 'member_user_id'], 'idx_caring_hour_xfer_tenant_member');
            $table->index('linked_transfer_id', 'idx_caring_hour_xfer_linked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_hour_transfers');
    }
};
