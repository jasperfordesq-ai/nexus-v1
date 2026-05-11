<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Models\MarketplaceListing;
use App\Observers\Concerns\IndexesEmbeddings;

class MarketplaceListingObserver
{
    use IndexesEmbeddings;

    public function created(MarketplaceListing $item): void
    {
        $this->reindexEmbedding($item, 'marketplace');
    }

    public function updated(MarketplaceListing $item): void
    {
        $dirty = array_keys($item->getDirty());
        $searchable = ['title', 'tagline', 'description', 'condition', 'location', 'status'];
        if (empty(array_intersect($dirty, $searchable))) {
            return;
        }
        $this->reindexEmbedding($item, 'marketplace');
    }

    public function deleted(MarketplaceListing $item): void
    {
        $this->deleteEmbedding($item, 'marketplace');
    }
}
