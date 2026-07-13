<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

class MarketplaceBrowseSchemaTest extends TestCase
{
    public function test_marketplace_browse_indexes_and_delivery_tenant_type_are_canonical(): void
    {
        $this->assertTrue(Schema::hasIndex('marketplace_listings', 'mpl_browse_price_idx'));
        $this->assertTrue(Schema::hasIndex('marketplace_listings', 'mpl_browse_promotion_idx'));

        $columnType = DB::table('information_schema.COLUMNS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'marketplace_delivery_offers')
            ->where('COLUMN_NAME', 'tenant_id')
            ->value('COLUMN_TYPE');

        $this->assertSame('bigint(20) unsigned', strtolower((string) $columnType));
    }
}
