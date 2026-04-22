// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FreeItemsPage — Grid of free marketplace listings (price_type = 'free').
 *
 * Same layout as MarketplacePage but pre-filtered to free items.
 * Includes a "Give something away" CTA for authenticated users.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { Button, Spinner } from '@heroui/react';
import Gift from 'lucide-react/icons/gift';
import Plus from 'lucide-react/icons/plus';
import ShoppingBag from 'lucide-react/icons/shopping-bag';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { MarketplaceListingGrid } from '@/components/marketplace';
import type { MarketplaceListingItem } from '@/types/marketplace';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

const ITEMS_PER_PAGE = 24;

export function FreeItemsPage() {
  const { t } = useTranslation('marketplace');
  usePageTitle(t('free.page_title', 'Free Items'));
  const { isAuthenticated } = useAuth();
  const { tenantPath, hasFeature } = useTenant();
  const toast = useToast();
  const featureEnabled = hasFeature('marketplace');

  const [listings, setListings] = useState<MarketplaceListingItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const cursorRef = useRef<string | null>(null);

  const loadListings = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      params.set('price_type', 'free');
      params.set('limit', String(ITEMS_PER_PAGE));
      if (append && cursorRef.current) {
        params.set('cursor', cursorRef.current);
      }

      const response = await api.get<MarketplaceListingItem[]>(
        `/v2/marketplace/listings?${params}`,
      );

      if (response.success && response.data) {
        const mapped = response.data as MarketplaceListingItem[];
        if (append) {
          setListings((prev) => [...prev, ...mapped]);
        } else {
          setListings(mapped);
        }
        cursorRef.current = response.meta?.cursor ?? response.meta?.next_cursor ?? null;
        setHasMore(response.meta?.has_more ?? response.data.length >= ITEMS_PER_PAGE);
      } else if (!append) {
        setError(t('free.unable_to_load', 'Unable to load free items'));
      }
    } catch (err) {
      logError('Failed to load free items', err);
      if (!append) {
        setError(t('free.unable_to_load', 'Unable to load free items'));
      } else {
        toast.error(t('free.load_more_failed', 'Failed to load more items'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [toast, t]);

  useEffect(() => {
    if (!featureEnabled) return;
    cursorRef.current = null;
    setHasMore(true);
    loadListings();
  }, [featureEnabled]); // eslint-disable-line react-hooks/exhaustive-deps

  // Save / unsave handlers
  const handleSave = async (id: number) => {
    if (!isAuthenticated) {
      toast.error(t('common.sign_in_to_save', 'Please sign in to save listings'));
      return;
    }
    try {
      await api.post(`/v2/marketplace/listings/${id}/save`);
      setListings((prev) => prev.map((l) => (l.id === id ? { ...l, is_saved: true } : l)));
      toast.success(t('common.saved_for_later', 'Saved for later'));
    } catch (err) {
      logError('Failed to save listing', err);
      toast.error(t('common.save_failed', 'Failed to update saved status'));
    }
  };

  const handleUnsave = async (id: number) => {
    if (!isAuthenticated) return;
    try {
      await api.delete(`/v2/marketplace/listings/${id}/save`);
      setListings((prev) => prev.map((l) => (l.id === id ? { ...l, is_saved: false } : l)));
      toast.success(t('common.removed_from_saved', 'Removed from saved'));
    } catch (err) {
      logError('Failed to unsave listing', err);
      toast.error(t('common.save_failed', 'Failed to update saved status'));
    }
  };

  if (!featureEnabled) {
    return (
      <div className="max-w-5xl mx-auto px-4 py-12">
        <EmptyState
          icon={<ShoppingBag className="w-8 h-8" />}
          title={t('hub_feature_gate.title', 'Marketplace Not Available')}
          description={t('hub_feature_gate.description', 'The marketplace feature is not enabled for this community.')}
        />
      </div>
    );
  }

  return (
    <>
      <PageMeta
        title={t('free.page_title', 'Free Items')}
        description={t('free.meta_description', 'Browse free items available in your community marketplace.')}
      />

      <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between flex-wrap gap-4">
          <div>
            <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
              <Gift className="w-7 h-7 text-success" />
              {t('free.page_title', 'Free Items')}
            </h1>
            <p className="text-default-500 text-sm mt-1">
              {t('free.subtitle', 'Items available for free from your community')}
            </p>
          </div>
          {isAuthenticated && (
            <Button
              as={Link}
              to={tenantPath('/marketplace/sell')}
              color="success"
              variant="flat"
              startContent={<Plus className="w-4 h-4" />}
            >
              {t('free.give_away', 'Give Something Away')}
            </Button>
          )}
        </div>

        {/* CTA banner */}
        {isAuthenticated && (
          <GlassCard className="p-5 flex items-center gap-4 border border-success/30 bg-success/5">
            <Gift className="w-10 h-10 text-success shrink-0" />
            <div className="flex-1">
              <h3 className="font-semibold text-foreground">
                {t('free.cta_title', 'Declutter and help your community')}
              </h3>
              <p className="text-sm text-default-500">
                {t('free.cta_description', 'List items you no longer need as free and give them a new home.')}
              </p>
            </div>
            <Button
              as={Link}
              to={tenantPath('/marketplace/sell')}
              color="success"
              size="sm"
            >
              {t('free.give_away', 'Give Something Away')}
            </Button>
          </GlassCard>
        )}

        {/* Listings */}
        {isLoading ? (
          <div className="flex justify-center py-16">
            <Spinner size="lg" color="primary" />
          </div>
        ) : error ? (
          <GlassCard className="p-8 text-center">
            <p className="text-danger mb-4">{error}</p>
            <Button color="primary" variant="flat" onPress={() => loadListings()}>
              {t('common.try_again', 'Try Again')}
            </Button>
          </GlassCard>
        ) : listings.length === 0 ? (
          <EmptyState
            icon={<Gift className="w-8 h-8" />}
            title={t('free.no_items_title', 'No Free Items')}
            description={t('free.no_items_description', 'There are no free items available right now. Check back later or be the first to give something away!')}
            action={
              isAuthenticated
                ? {
                    label: t('free.give_away', 'Give Something Away'),
                    onClick: () => { window.location.href = tenantPath('/marketplace/sell'); },
                  }
                : undefined
            }
          />
        ) : (
          <>
            <MarketplaceListingGrid
              listings={listings}
              onSave={handleSave}
              onUnsave={handleUnsave}
            />

            {hasMore && (
              <div className="flex justify-center mt-8">
                <Button
                  variant="flat"
                  color="primary"
                  onPress={() => loadListings(true)}
                  isLoading={isLoadingMore}
                >
                  {t('common.load_more', 'Load More')}
                </Button>
              </div>
            )}
          </>
        )}
      </div>
    </>
  );
}

export default FreeItemsPage;
