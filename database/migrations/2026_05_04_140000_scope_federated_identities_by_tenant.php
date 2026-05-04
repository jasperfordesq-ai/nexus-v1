<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('federated_identities')) {
            return;
        }

        if (!Schema::hasColumn('federated_identities', 'tenant_id')) {
            Schema::table('federated_identities', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index('idx_fed_identities_tenant');
            });
        }

        DB::statement("
            UPDATE federated_identities fi
            INNER JOIN users u ON u.id = fi.local_user_id
            SET fi.tenant_id = u.tenant_id
            WHERE fi.tenant_id IS NULL
        ");

        if (Schema::hasTable('federation_external_partners')) {
            DB::statement("
                UPDATE federated_identities fi
                INNER JOIN federation_external_partners p ON p.id = fi.partner_id
                SET fi.tenant_id = p.tenant_id
                WHERE fi.tenant_id IS NULL
            ");
        }

        DB::statement("
            DELETE fi FROM federated_identities fi
            LEFT JOIN users u ON u.id = fi.local_user_id AND u.tenant_id = fi.tenant_id
            WHERE fi.tenant_id IS NULL OR u.id IS NULL
        ");

        DB::statement("
            DELETE fi FROM federated_identities fi
            INNER JOIN federated_identities keep
                ON keep.tenant_id = fi.tenant_id
               AND keep.partner_id = fi.partner_id
               AND keep.external_user_id = fi.external_user_id
               AND keep.id < fi.id
            WHERE fi.external_user_id IS NOT NULL
        ");

        DB::statement("
            DELETE fi FROM federated_identities fi
            INNER JOIN federated_identities keep
                ON keep.tenant_id = fi.tenant_id
               AND keep.local_user_id = fi.local_user_id
               AND keep.partner_id = fi.partner_id
               AND keep.id < fi.id
        ");

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE federated_identities MODIFY tenant_id BIGINT UNSIGNED NOT NULL');
        }

        if ($this->indexExists('federated_identities', 'uniq_fed_identity_partner_ext')) {
            Schema::table('federated_identities', function (Blueprint $table): void {
                $table->dropUnique('uniq_fed_identity_partner_ext');
            });
        }

        if (!$this->indexExists('federated_identities', 'uniq_fed_identities_tenant_partner_external')) {
            Schema::table('federated_identities', function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'partner_id', 'external_user_id'],
                    'uniq_fed_identities_tenant_partner_external'
                );
            });
        }

        if (!$this->indexExists('federated_identities', 'uniq_fed_identities_tenant_user_partner')) {
            Schema::table('federated_identities', function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'local_user_id', 'partner_id'],
                    'uniq_fed_identities_tenant_user_partner'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('federated_identities')) {
            return;
        }

        Schema::table('federated_identities', function (Blueprint $table): void {
            if ($this->indexExists('federated_identities', 'uniq_fed_identities_tenant_partner_external')) {
                $table->dropUnique('uniq_fed_identities_tenant_partner_external');
            }
            if ($this->indexExists('federated_identities', 'uniq_fed_identities_tenant_user_partner')) {
                $table->dropUnique('uniq_fed_identities_tenant_user_partner');
            }
            if ($this->indexExists('federated_identities', 'idx_fed_identities_tenant')) {
                $table->dropIndex('idx_fed_identities_tenant');
            }
            if (!$this->indexExists('federated_identities', 'uniq_fed_identity_partner_ext')) {
                $table->unique(['partner_id', 'external_user_id'], 'uniq_fed_identity_partner_ext');
            }
        });

        if (Schema::hasColumn('federated_identities', 'tenant_id')) {
            Schema::table('federated_identities', function (Blueprint $table): void {
                $table->dropColumn('tenant_id');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($index);
    }
};
