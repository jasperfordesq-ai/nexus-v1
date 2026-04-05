// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GroupMarketplaceTab — Marketplace listings scoped to a group.
 *
 * Displays marketplace listings from group members with stats bar,
 * category filtering, and a "Sell to Group" CTA. Designed to be
 * added as a tab within GroupDetailPage.
 */

import { useState, useEffect, useCallback } from 'react';
import { Button, Spinner, Chip } from '@heroui/react';
import { ShoppingBag, Plus, Package, Users, Tag } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { MarketplaceListingGrid } from './MarketplaceListingGrid';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useTenant } from '@/contexts';
import type { MarketplaceListingItem } from '@/types/marketplace';

interface GroupMarketplaceStats {
  active_listings: number;
  total_listed: number;
  total_sellers: number;
  categories: Array<{
    id: number;
    name: string;
    slug: string;
    icon?: string;
    listing_count: number;
  }>;
}

interface GroupMarketplaceTabProps {
  groupId: number;
}

export function GroupMarketplaceTab({ groupId }: GroupMarketplaceTabProps) {
  const { t } = useTranslation('marketplace');
  const { tenantPath } = useTenant();
  const navigate = useNavigate();

  const [listings, setListings] = useState<MarketplaceListingItem[]>([]);
  const [stats, setStats] = useState<GroupMarketplaceStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);

  const loadListings = useCallback(async (append = false, categoryId?: number | null) => {
    if (append) {
      setLoadingMore(true);
    } else {
      setLoading(true);
    }

    try {
      const qs = new URLSearchParams({ limit: '20' });
      if (append && cursor) {
        qs.set('cursor', cursor);
      }
      if (categoryId != null) {
        qs.set('category_id', String(categoryId));
      }

      const response = await api.get(`/v2/marketplace/groups/${groupId}/listings?${qs.toString()}`);
      const data = response.data as any;
      const items = data.data ?? data.items ?? [];

      if (append) {
        setListings(prev => [...prev, ...items]);
      } else {
        setListings(items);
      }
      setCursor(data.meta?.cursor ?? data.cursor ?? null);
      setHasMore(data.meta?.has_more ?? data.has_more ?? false);
    } catch (err) {
      logError('Failed to load group marketplace listings', err);
    } finally {
      setLoading(false);
      setLoadingMore(false);
    }
  }, [groupId, cursor]);

  const loadStats = useCallback(async () => {
    try {
      const response = await api.get(`/v2/marketplace/groups/${groupId}/stats`);
      const raw = response.data as any;
      setStats(raw.data ?? raw);
    } catch (err) {
      logError('Failed to load group marketplace stats', err);
    }
  }, [groupId]);

  useEffect(() => {
    loadListings(false, selectedCategory);
    loadStats();
  }, [groupId]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleCategoryFilter = (categoryId: number | null) => {
    setSelectedCategory(categoryId);
    setCursor(null);
    loadListings(false, categoryId);
  };

  const handleSave = useCallback(async (id: number) => {
    try {
      await api.post(`/v2/marketplace/listings/${id}/save`);
      setListings(prev => prev.map(l => l.id === id ? { ...l, is_saved: true } : l));
    } catch (err) {
      logError('Failed to save listing', err);
    }
  }, []);

  const handleUnsave = useCallback(async (id: number) => {
    try {
      await api.delete(`/v2/marketplace/listings/${id}/save`);
      setListings(prev => prev.map(l => l.id === id ? { ...l, is_saved: false } : l));
    } catch (err) {
      logError('Failed to unsave listing', err);
    }
  }, []);

  return (
    <div className="space-y-6">
      {/* Stats Bar */}
      {stats && (
        <div className="flex flex-wrap gap-4">
          <div className="flex items-center gap-2 px-4 py-2 rounded-xl bg-[var(--color-surface)] border border-[var(--color-border)]">
            <Package className="w-4 h-4 text-theme-primary" />
            <span className="text-sm font-medium">
              {stats.active_listings} {t('group_marketplace.active_listings', 'active listings')}
            </span>
          </div>
          <div className="flex items-center gap-2 px-4 py-2 rounded-xl bg-[var(--color-surface)] border border-[var(--color-border)]">
            <Tag className="w-4 h-4 text-theme-secondary" />
            <span className="text-sm font-medium">
              {stats.total_listed} {t('group_marketplace.total_listed', 'total listed')}
            </span>
          </div>
          <div className="flex items-center gap-2 px-4 py-2 rounded-xl bg-[var(--color-surface)] border border-[var(--color-border)]">
            <Users className="w-4 h-4 text-theme-muted" />
            <span className="text-sm font-medium">
              {stats.total_sellers} {t('group_marketplace.sellers', 'sellers')}
            </span>
          </div>
        </div>
      )}

      {/* CTA + Category Filter Row */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="flex flex-wrap gap-2">
          <Chip
            variant={selectedCategory === null ? 'solid' : 'flat'}
            color={selectedCategory === null ? 'primary' : 'default'}
            className="cursor-pointer"
            onClick={() => handleCategoryFilter(null)}
          >
            {t('categories.all', 'All')}
          </Chip>
          {stats?.categories.map(cat => (
            <Chip
              key={cat.id}
              variant={selectedCategory === cat.id ? 'solid' : 'flat'}
              color={selectedCategory === cat.id ? 'primary' : 'default'}
              className="cursor-pointer"
              onClick={() => handleCategoryFilter(cat.id)}
            >
              {cat.name} ({cat.listing_count})
            </Chip>
          ))}
        </div>

        <Button
          color="primary"
          startContent={<Plus className="w-4 h-4" />}
          onPress={() => navigate(tenantPath('/marketplace/sell'))}
        >
          {t('group_marketplace.sell_to_group', 'Sell to Group')}
        </Button>
      </div>

      {/* Listings Grid */}
      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : listings.length === 0 ? (
        <div className="text-center py-12">
          <ShoppingBag className="w-12 h-12 mx-auto mb-4 text-theme-muted opacity-40" />
          <h3 className="text-lg font-semibold text-theme-primary mb-2">
            {t('group_marketplace.empty_title', 'No Group Listings Yet')}
          </h3>
          <p className="text-sm text-theme-muted mb-4">
            {t('group_marketplace.empty_description', 'Be the first group member to list something for sale!')}
          </p>
          <Button
            color="primary"
            variant="flat"
            startContent={<Plus className="w-4 h-4" />}
            onPress={() => navigate(tenantPath('/marketplace/sell'))}
          >
            {t('group_marketplace.sell_to_group', 'Sell to Group')}
          </Button>
        </div>
      ) : (
        <>
          <MarketplaceListingGrid
            listings={listings}
            onSave={handleSave}
            onUnsave={handleUnsave}
          />

          {hasMore && (
            <div className="flex justify-center pt-4">
              <Button
                variant="flat"
                isLoading={loadingMore}
                onPress={() => loadListings(true, selectedCategory)}
              >
                {t('common.load_more', 'Load More')}
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}

export default GroupMarketplaceTab;
