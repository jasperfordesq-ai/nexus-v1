// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MyListingsPage — Seller dashboard showing all of the current user's listings.
 *
 * Features:
 * - Tabs: Active, Draft, Sold, Expired
 * - Stats summary from seller dashboard endpoint
 * - Per-listing actions: Edit, Renew (expired), Remove
 * - "Sell Something" CTA
 * - Cursor-based pagination
 * - Requires authentication
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  Button,
  Spinner,
  Tab,
  Tabs,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  Plus,
  ShoppingBag,
  Package,
  Edit3,
  RefreshCw,
  Trash2,
  Eye,
  DollarSign,
  BarChart3,
  Clock,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { MarketplaceListingGrid } from '@/components/marketplace';
import type { MarketplaceListingItem } from '@/types/marketplace';
import { mapApiToListingItem } from '@/lib/marketplace-utils';
import type { ApiMarketplaceListing } from '@/lib/marketplace-utils';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type ListingTab = 'active' | 'draft' | 'sold' | 'expired';

interface SellerDashboardStats {
  active_listings: number;
  draft_listings: number;
  sold_listings: number;
  expired_listings: number;
  total_views: number;
  total_revenue: number;
  revenue_currency: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ITEMS_PER_PAGE = 24;

const TABS: { key: ListingTab; tKey: string; label: string; icon: typeof Package }[] = [
  { key: 'active', tKey: 'my_listings.tab_active', label: 'Active', icon: Package },
  { key: 'draft', tKey: 'my_listings.tab_draft', label: 'Drafts', icon: Edit3 },
  { key: 'sold', tKey: 'my_listings.tab_sold', label: 'Sold', icon: DollarSign },
  { key: 'expired', tKey: 'my_listings.tab_expired', label: 'Expired', icon: Clock },
];

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function formatCurrency(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(amount);
  } catch {
    return `${currency} ${amount}`;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MyListingsPage() {
  const navigate = useNavigate();
  const { t } = useTranslation('marketplace');
  usePageTitle(t('my_listings.page_title', 'My Listings - Marketplace'));
  const { isAuthenticated, user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  // State
  const [activeTab, setActiveTab] = useState<ListingTab>('active');
  const [listings, setListings] = useState<MarketplaceListingItem[]>([]);
  const [stats, setStats] = useState<SellerDashboardStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [isLoadingStats, setIsLoadingStats] = useState(true);
  const [hasMore, setHasMore] = useState(true);
  const cursorRef = useRef<string | null>(null);

  // Remove modal
  const { isOpen: isRemoveOpen, onOpen: onRemoveOpen, onClose: onRemoveClose } = useDisclosure();
  const [removeTargetId, setRemoveTargetId] = useState<number | null>(null);
  const [isRemoving, setIsRemoving] = useState(false);

  // Redirect if not authenticated
  useEffect(() => {
    if (!isAuthenticated) {
      navigate(tenantPath('/auth/login'), { replace: true });
    }
  }, [isAuthenticated, navigate, tenantPath]);

  // Load seller dashboard stats
  useEffect(() => {
    if (!isAuthenticated) return;
    let cancelled = false;

    const load = async () => {
      setIsLoadingStats(true);
      try {
        const response = await api.get<SellerDashboardStats>('/v2/marketplace/seller/dashboard');
        if (!cancelled && response.success && response.data) {
          setStats(response.data);
        }
      } catch (err) {
        logError('Failed to load seller dashboard stats', err);
      } finally {
        if (!cancelled) setIsLoadingStats(false);
      }
    };

    load();
    return () => { cancelled = true; };
  }, [isAuthenticated]);

  // Load listings for the active tab
  const loadListings = useCallback(async (append = false) => {
    if (!user?.id) return;

    try {
      if (!append) {
        setIsLoading(true);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      params.set('user_id', String(user.id));
      params.set('status', activeTab);
      params.set('limit', String(ITEMS_PER_PAGE));
      if (append && cursorRef.current) {
        params.set('cursor', cursorRef.current);
      }

      const response = await api.get<ApiMarketplaceListing[]>(
        `/v2/marketplace/listings?${params}`
      );

      if (response.success && response.data) {
        const mapped = response.data.map((item) => ({
          ...mapApiToListingItem(item),
          is_own: true,
          status: activeTab,
        }));
        if (append) {
          setListings((prev) => [...prev, ...mapped]);
        } else {
          setListings(mapped);
        }
        cursorRef.current = response.meta?.cursor ?? response.meta?.next_cursor ?? null;
        setHasMore(response.meta?.has_more ?? response.data.length >= ITEMS_PER_PAGE);
      } else if (!append) {
        setListings([]);
      }
    } catch (err) {
      logError('Failed to load my listings', err);
      if (!append) {
        toast.error(t('my_listings.load_error', 'Failed to load your listings'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [user?.id, activeTab, toast]);

  // Reload on tab change
  useEffect(() => {
    if (!isAuthenticated) return;
    cursorRef.current = null;
    setHasMore(true);
    loadListings();
  }, [activeTab, isAuthenticated]); // eslint-disable-line react-hooks/exhaustive-deps

  // Renew listing
  const handleRenew = useCallback(async (listingId: number) => {
    try {
      const response = await api.post(`/v2/marketplace/listings/${listingId}/renew`);
      if (response.success) {
        toast.success(t('my_listings.renewed_success', 'Listing renewed successfully'));
        // Refresh listings
        cursorRef.current = null;
        loadListings();
      } else {
        toast.error(response.error || t('my_listings.renewed_error', 'Failed to renew listing'));
      }
    } catch (err) {
      logError('Failed to renew listing', err);
      toast.error(t('my_listings.renewed_error', 'Failed to renew listing'));
    }
  }, [toast, loadListings]);

  // Remove listing
  const confirmRemove = useCallback((listingId: number) => {
    setRemoveTargetId(listingId);
    onRemoveOpen();
  }, [onRemoveOpen]);

  const handleRemove = useCallback(async () => {
    if (removeTargetId == null) return;
    setIsRemoving(true);
    try {
      const response = await api.delete(`/v2/marketplace/listings/${removeTargetId}`);
      if (response.success) {
        toast.success(t('my_listings.removed_success', 'Listing removed'));
        setListings((prev) => prev.filter((l) => l.id !== removeTargetId));
        // Update stats
        if (stats) {
          setStats((prev) => prev ? { ...prev, [`${activeTab}_listings`]: Math.max(0, (prev as Record<string, number>)[`${activeTab}_listings`] - 1) } : prev);
        }
        onRemoveClose();
      } else {
        toast.error(response.error || t('my_listings.removed_error', 'Failed to remove listing'));
      }
    } catch (err) {
      logError('Failed to remove listing', err);
      toast.error(t('my_listings.removed_error', 'Failed to remove listing'));
    } finally {
      setIsRemoving(false);
    }
  }, [removeTargetId, activeTab, stats, toast, onRemoveClose]);

  if (!isAuthenticated) return null;

  return (
    <>
      <PageMeta title={t('my_listings.page_title', 'My Listings - Marketplace')} />

      <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between flex-wrap gap-4">
          <div>
            <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
              <ShoppingBag className="w-7 h-7 text-primary" />
              {t('my_listings.title', 'My Listings')}
            </h1>
            <p className="text-default-500 text-sm mt-1">
              {t('my_listings.subtitle', 'Manage your marketplace listings')}
            </p>
          </div>
          <Button
            as={Link}
            to={tenantPath('/marketplace/sell')}
            color="primary"
            startContent={<Plus className="w-4 h-4" />}
          >
            {t('hub.sell_something', 'Sell Something')}
          </Button>
        </div>

        {/* Stats summary */}
        {!isLoadingStats && stats && (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <GlassCard className="p-4 text-center">
              <Package className="w-5 h-5 text-primary mx-auto mb-1" />
              <p className="text-2xl font-bold text-foreground">{stats.active_listings}</p>
              <p className="text-xs text-default-500">{t('my_listings.stat_active', 'Active')}</p>
            </GlassCard>
            <GlassCard className="p-4 text-center">
              <DollarSign className="w-5 h-5 text-success mx-auto mb-1" />
              <p className="text-2xl font-bold text-foreground">{stats.sold_listings}</p>
              <p className="text-xs text-default-500">{t('my_listings.stat_sold', 'Sold')}</p>
            </GlassCard>
            <GlassCard className="p-4 text-center">
              <Eye className="w-5 h-5 text-secondary mx-auto mb-1" />
              <p className="text-2xl font-bold text-foreground">{stats.total_views}</p>
              <p className="text-xs text-default-500">{t('my_listings.stat_views', 'Total Views')}</p>
            </GlassCard>
            <GlassCard className="p-4 text-center">
              <BarChart3 className="w-5 h-5 text-warning mx-auto mb-1" />
              <p className="text-2xl font-bold text-foreground">
                {stats.total_revenue > 0
                  ? formatCurrency(stats.total_revenue, stats.revenue_currency || 'EUR')
                  : '0'}
              </p>
              <p className="text-xs text-default-500">{t('my_listings.stat_revenue', 'Revenue')}</p>
            </GlassCard>
          </div>
        )}

        {/* Tabs */}
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as ListingTab)}
          color="primary"
          variant="underlined"
          classNames={{ tabList: 'gap-4' }}
        >
          {TABS.map((tab) => (
            <Tab
              key={tab.key}
              title={
                <div className="flex items-center gap-1.5">
                  <tab.icon className="w-4 h-4" />
                  <span>{t(tab.tKey, tab.label)}</span>
                  {stats && (
                    <span className="text-xs text-default-400">
                      ({(stats as Record<string, number>)[`${tab.key}_listings`] ?? 0})
                    </span>
                  )}
                </div>
              }
            />
          ))}
        </Tabs>

        {/* Listings */}
        {isLoading ? (
          <div className="flex justify-center py-16">
            <Spinner size="lg" color="primary" />
          </div>
        ) : listings.length === 0 ? (
          <EmptyState
            icon={Package}
            title={t(`my_listings.empty_${activeTab}_title`, 'No Listings')}
            description={t(`my_listings.empty_${activeTab}_description`, 'You don\'t have any listings in this category yet.')}
            action={
              activeTab === 'active' || activeTab === 'draft'
                ? {
                    label: t('hub.sell_something', 'Sell Something'),
                    onClick: () => navigate(tenantPath('/marketplace/sell')),
                  }
                : undefined
            }
          />
        ) : (
          <>
            {/* Listing cards with action overlays */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
              {listings.map((listing) => (
                <GlassCard key={listing.id} className="overflow-hidden">
                  {/* Image */}
                  <Link to={tenantPath(`/marketplace/${listing.id}`)}>
                    <div className="aspect-square bg-default-100 relative overflow-hidden">
                      {listing.image?.url ? (
                        <img
                          src={listing.image.thumbnail_url || listing.image.url}
                          alt={listing.title}
                          className="w-full h-full object-cover"
                          loading="lazy"
                        />
                      ) : (
                        <div className="w-full h-full flex items-center justify-center">
                          <Package className="w-10 h-10 text-default-300" />
                        </div>
                      )}
                    </div>
                  </Link>

                  {/* Info */}
                  <div className="p-3">
                    <Link to={tenantPath(`/marketplace/${listing.id}`)}>
                      <p className="text-sm font-semibold text-foreground truncate hover:text-primary transition-colors">
                        {listing.title}
                      </p>
                    </Link>

                    {listing.price != null && listing.price > 0 ? (
                      <p className="text-sm font-bold text-primary mt-1">
                        {formatCurrency(listing.price, listing.price_currency)}
                      </p>
                    ) : (
                      <p className="text-sm font-bold text-success mt-1">
                        {t('common.free', 'Free')}
                      </p>
                    )}

                    <div className="flex items-center gap-1 text-xs text-default-400 mt-1">
                      <Eye className="w-3 h-3" />
                      <span>{listing.views_count}</span>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-2 mt-3">
                      <Button
                        as={Link}
                        to={tenantPath(`/marketplace/${listing.id}/edit`)}
                        size="sm"
                        variant="flat"
                        color="primary"
                        startContent={<Edit3 className="w-3.5 h-3.5" />}
                        className="flex-1"
                      >
                        {t('my_listings.action_edit', 'Edit')}
                      </Button>

                      {activeTab === 'expired' && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="success"
                          startContent={<RefreshCw className="w-3.5 h-3.5" />}
                          onPress={() => handleRenew(listing.id)}
                        >
                          {t('my_listings.action_renew', 'Renew')}
                        </Button>
                      )}

                      <Button
                        size="sm"
                        variant="flat"
                        color="danger"
                        isIconOnly
                        onPress={() => confirmRemove(listing.id)}
                        aria-label={t('my_listings.action_remove', 'Remove')}
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </Button>
                    </div>
                  </div>
                </GlassCard>
              ))}
            </div>

            {/* Load more */}
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

      {/* Remove confirmation modal */}
      <Modal isOpen={isRemoveOpen} onClose={onRemoveClose} size="sm">
        <ModalContent>
          <ModalHeader>{t('my_listings.remove_confirm_title', 'Remove Listing')}</ModalHeader>
          <ModalBody>
            <p className="text-default-600">
              {t('my_listings.remove_confirm_description', 'Are you sure you want to remove this listing? This action cannot be undone.')}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onRemoveClose}>
              {t('common.cancel', 'Cancel')}
            </Button>
            <Button
              color="danger"
              onPress={handleRemove}
              isLoading={isRemoving}
            >
              {t('my_listings.action_remove', 'Remove')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

export default MyListingsPage;
