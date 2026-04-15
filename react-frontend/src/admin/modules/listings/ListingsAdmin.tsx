// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Listings / Content Directory
 * Unified view of all user-generated content with moderation actions.
 * Includes Featured Listings management tab (L4).
 * Parity: PHP Admin\ListingController::index()
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Tabs,
  Tab,
  Button,
  Chip,
  Card,
  CardBody,
  Input,
  Spinner,
  Tooltip,
} from '@heroui/react';
import { CheckCircle, XCircle, Trash2, Star, StarOff, Search, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminListings } from '../../api/adminApi';
import { DataTable, StatusBadge, PageHeader, ConfirmModal, type Column } from '../../components';
import type { AdminListing, FeaturedListing } from '../../api/types';

const typeColors: Record<string, 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'default'> = {
  listing: 'primary',
  event: 'secondary',
  poll: 'warning',
  goal: 'success',
  resource: 'default',
  volunteer: 'danger',
};

// ─────────────────────────────────────────────────────────────────────────────
// Featured Listings Sub-Component
// ─────────────────────────────────────────────────────────────────────────────

function FeaturedListingsPanel() {
  const { t } = useTranslation('admin');
  const toast = useToast();

  const [featured, setFeatured] = useState<FeaturedListing[]>([]);
  const [featuredLoading, setFeaturedLoading] = useState(true);
  const [searchResults, setSearchResults] = useState<AdminListing[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [searching, setSearching] = useState(false);
  const [featureLoading, setFeatureLoading] = useState<number | null>(null);
  const [unfeatureConfirm, setUnfeatureConfirm] = useState<FeaturedListing | null>(null);
  const [unfeatureLoading, setUnfeatureLoading] = useState(false);

  const loadFeatured = useCallback(async () => {
    setFeaturedLoading(true);
    try {
      const res = await adminListings.getFeatured();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setFeatured(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error(t('listings.failed_load_content'));
    } finally {
      setFeaturedLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    loadFeatured();
  }, [loadFeatured]);

  const handleSearch = useCallback(async (query: string) => {
    setSearchQuery(query);
    if (!query || query.length < 2) {
      setSearchResults([]);
      return;
    }
    setSearching(true);
    try {
      const res = await adminListings.list({ search: query, status: 'active' });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setSearchResults(data);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: AdminListing[] };
          setSearchResults(pd.data || []);
        }
      }
    } catch {
      // Silently handle search errors
    } finally {
      setSearching(false);
    }
  }, []);

  const handleFeature = async (listingId: number) => {
    setFeatureLoading(listingId);
    try {
      const res = await adminListings.feature(listingId);
      if (res?.success) {
        toast.success(t('listings.featured_success'));
        setSearchQuery('');
        setSearchResults([]);
        loadFeatured();
      } else {
        toast.error(res?.error || t('listings.failed_feature'));
      }
    } catch {
      toast.error(t('common.unexpected_error'));
    } finally {
      setFeatureLoading(null);
    }
  };

  const handleUnfeature = async () => {
    if (!unfeatureConfirm) return;
    setUnfeatureLoading(true);
    try {
      const res = await adminListings.unfeature(unfeatureConfirm.listing_id);
      if (res?.success) {
        toast.success(t('listings.unfeatured_success'));
        loadFeatured();
      } else {
        toast.error(res?.error || t('listings.failed_unfeature'));
      }
    } catch {
      toast.error(t('common.unexpected_error'));
    } finally {
      setUnfeatureLoading(false);
      setUnfeatureConfirm(null);
    }
  };

  // Filter out already-featured listings from search results
  const featuredIds = new Set(featured.map((f) => f.listing_id));
  const filteredResults = searchResults.filter((r) => !featuredIds.has(r.id));

  const featuredColumns: Column<FeaturedListing>[] = [
    {
      key: 'title',
      label: t('listings.col_listing_title'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'type',
      label: t('listings.type'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color={typeColors[item.type] || 'default'} className="capitalize">
          {item.type}
        </Chip>
      ),
    },
    {
      key: 'user_name',
      label: t('listings.author'),
      sortable: true,
    },
    {
      key: 'featured_at',
      label: t('listings.featured_date'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.featured_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'featured_by',
      label: t('listings.featured_by'),
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.featured_by || '—'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('listings.actions'),
      render: (item) => (
        <Button
          size="sm"
          variant="flat"
          color="warning"
          startContent={<StarOff size={14} />}
          onPress={() => setUnfeatureConfirm(item)}
        >
          {t('listings.unfeature')}
        </Button>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      {/* Feature new listing search */}
      <Card shadow="sm">
        <CardBody className="p-4">
          <h3 className="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
            <Plus size={16} />
            {t('listings.feature_listing')}
          </h3>
          <Input
            placeholder={t('listings.placeholder_search_active_listings_to_feature')}
            aria-label={t('listings.label_search_listings')}
            startContent={<Search size={16} className="text-default-400" />}
            value={searchQuery}
            onValueChange={handleSearch}
            size="sm"
            variant="bordered"
            className="mb-3"
          />
          {searching && (
            <div className="flex items-center justify-center py-4">
              <Spinner size="sm" />
            </div>
          )}
          {filteredResults.length > 0 && (
            <div className="space-y-2 max-h-60 overflow-y-auto">
              {filteredResults.map((listing) => (
                <div
                  key={listing.id}
                  className="flex items-center justify-between border border-divider rounded-lg p-3"
                >
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-foreground truncate">
                      {listing.title}
                    </p>
                    <p className="text-xs text-default-500">
                      {t('listings.by_author', { name: listing.user_name })} &middot;{' '}
                      <span className="capitalize">{listing.type}</span>
                    </p>
                  </div>
                  <Button
                    size="sm"
                    color="warning"
                    variant="flat"
                    startContent={<Star size={14} />}
                    isLoading={featureLoading === listing.id}
                    onPress={() => handleFeature(listing.id)}
                  >
                    {t('listings.feature')}
                  </Button>
                </div>
              ))}
            </div>
          )}
          {searchQuery.length >= 2 && !searching && filteredResults.length === 0 && searchResults.length === 0 && (
            <p className="text-sm text-default-400 text-center py-2">
              {t('listings.no_active_listings_found', { query: searchQuery })}
            </p>
          )}
        </CardBody>
      </Card>

      {/* Currently featured listings table */}
      <div>
        <h3 className="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
          <Star size={16} className="text-warning" />
          {t('listings.currently_featured', { count: featured.length })}
        </h3>
        <DataTable
          columns={featuredColumns}
          data={featured}
          keyField="listing_id"
          isLoading={featuredLoading}
          searchable={false}
          onRefresh={loadFeatured}
          emptyContent={t('listings.no_featured_listings')}
        />
      </div>

      {/* Unfeature confirmation modal */}
      {unfeatureConfirm && (
        <ConfirmModal
          isOpen={!!unfeatureConfirm}
          onClose={() => setUnfeatureConfirm(null)}
          onConfirm={handleUnfeature}
          title={t('listings.remove_from_featured')}
          message={t('listings.remove_from_featured_message', { title: unfeatureConfirm.title })}
          confirmLabel={t('listings.unfeature')}
          confirmColor="warning"
          isLoading={unfeatureLoading}
        />
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main ListingsAdmin Component
// ─────────────────────────────────────────────────────────────────────────────

export function ListingsAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('listings.page_title'));
  const toast = useToast();

  const [activeTab, setActiveTab] = useState('content');
  const [items, setItems] = useState<AdminListing[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [confirmAction, setConfirmAction] = useState<{
    type: 'approve' | 'reject' | 'delete';
    item: AdminListing;
  } | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminListings.list({
        page,
        status: status === 'all' ? undefined : status,
        search: search || undefined,
      });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setItems(data);
          // Use meta.total from the paginated response envelope for correct pagination.
          // Falling back to data.length only if meta is unavailable (non-paginated response).
          const metaTotal = (res.meta as Record<string, unknown> | undefined)?.total;
          setTotal(typeof metaTotal === 'number' ? metaTotal : data.length);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: AdminListing[]; meta?: { total: number } };
          setItems(pd.data || []);
          setTotal(pd.meta?.total || 0);
        }
      }
    } catch {
      toast.error(t('listings.failed_to_load_content'));
    } finally {
      setLoading(false);
    }
  }, [page, status, search, toast, t])

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleAction = async () => {
    if (!confirmAction) return;
    setActionLoading(true);
    try {
      const { type, item } = confirmAction;
      let res;
      if (type === 'approve') {
        res = await adminListings.approve(item.id);
      } else if (type === 'reject') {
        res = await adminListings.reject(item.id);
      } else {
        res = await adminListings.delete(item.id);
      }

      if (res?.success) {
        toast.success(t(`listings.content_${type}_success`));
        loadItems();
      } else {
        toast.error(res?.error || t(`listings.content_${type}_failed`));
      }
    } catch {
      toast.error(t('listings.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(false);
      setConfirmAction(null);
    }
  };

  const handleFeatureToggle = async (item: AdminListing) => {
    try {
      const res = item.is_featured
        ? await adminListings.unfeature(item.id)
        : await adminListings.feature(item.id);
      if (res?.success) {
        toast.success(
          item.is_featured
            ? t('listings.removed_from_featured', { title: item.title })
            : t('listings.now_featured', { title: item.title })
        );
        loadItems();
      } else {
        toast.error(res?.error || t('listings.failed_update_featured'));
      }
    } catch {
      toast.error(t('listings.an_unexpected_error_occurred'));
    }
  };

  const columns: Column<AdminListing>[] = [
    {
      key: 'title',
      label: t('listings.col_listing_title'),
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'type',
      label: t('listings.type'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color={typeColors[item.type] || 'default'} className="capitalize">
          {item.type}
        </Chip>
      ),
    },
    {
      key: 'user_name',
      label: t('listings.author'),
      sortable: true,
    },
    {
      key: 'status',
      label: t('listings.status'),
      sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'created_at',
      label: t('listings.created'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('listings.actions'),
      render: (item) => (
        <div className="flex gap-1">
          {item.status === 'pending' && (
            <>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="success"
                onPress={() => setConfirmAction({ type: 'approve', item })}
                aria-label={t('listings.label_approve')}
              >
                <CheckCircle size={14} />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="danger"
                onPress={() => setConfirmAction({ type: 'reject', item })}
                aria-label={t('listings.label_reject')}
              >
                <XCircle size={14} />
              </Button>
            </>
          )}
          <Tooltip content={item.is_featured ? t('listings.unfeature') : t('listings.feature')}>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="warning"
              onPress={() => handleFeatureToggle(item)}
              aria-label={item.is_featured ? t('listings.unfeature_listing') : t('listings.feature_listing')}
            >
              {item.is_featured ? <Star size={14} className="fill-warning" /> : <StarOff size={14} />}
            </Button>
          </Tooltip>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmAction({ type: 'delete', item })}
            aria-label={t('listings.label_delete')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('listings.listings_admin_title')}
        description={t('listings.listings_admin_desc')}
      />

      {/* Top-level tabs: Content | Featured */}
      <div className="mb-4">
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as string)}
          variant="solid"
          color="primary"
          size="sm"
          classNames={{ tabList: 'mb-4' }}
        >
          <Tab key="content" title={t('listings.tab_content')} />
          <Tab
            key="featured"
            title={
              <div className="flex items-center gap-1.5">
                <Star size={14} />
                {t('listings.tab_featured')}
              </div>
            }
          />
        </Tabs>
      </div>

      {activeTab === 'content' && (
        <>
          {/* Status filter sub-tabs */}
          <div className="mb-4">
            <Tabs
              selectedKey={status}
              onSelectionChange={(key) => { setStatus(key as string); setPage(1); }}
              variant="underlined"
              size="sm"
            >
              <Tab key="all" title={t('listings.filter_all')} />
              <Tab key="pending" title={t('listings.filter_pending')} />
              <Tab key="active" title={t('listings.filter_active')} />
              <Tab key="inactive" title={t('listings.filter_inactive')} />
            </Tabs>
          </div>

          <DataTable
            columns={columns}
            data={items}
            isLoading={loading}
            searchPlaceholder={t('listings.search_content')}
            onSearch={(q) => { setSearch(q); setPage(1); }}
            onRefresh={loadItems}
            totalItems={total}
            page={page}
            pageSize={20}
            onPageChange={setPage}
          />

          {confirmAction && (
            <ConfirmModal
              isOpen={!!confirmAction}
              onClose={() => setConfirmAction(null)}
              onConfirm={handleAction}
              title={confirmAction.type === 'approve' ? t('listings.approve_content') : confirmAction.type === 'reject' ? t('listings.reject_content') : t('listings.delete_content')}
              message={
                confirmAction.type === 'approve'
                  ? t('listings.approve_content_message', { title: confirmAction.item.title })
                  : confirmAction.type === 'reject'
                    ? t('listings.reject_content_message', { title: confirmAction.item.title })
                    : t('listings.delete_content_message', { title: confirmAction.item.title })
              }
              confirmLabel={confirmAction.type === 'approve' ? t('listings.approve') : confirmAction.type === 'reject' ? t('listings.reject') : t('listings.delete')}
              confirmColor={confirmAction.type === 'approve' ? 'primary' : 'danger'}
              isLoading={actionLoading}
            />
          )}
        </>
      )}

      {activeTab === 'featured' && <FeaturedListingsPanel />}
    </div>
  );
}

export default ListingsAdmin;
