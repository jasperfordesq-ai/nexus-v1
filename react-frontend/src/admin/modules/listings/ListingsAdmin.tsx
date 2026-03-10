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
import { CheckCircle, Trash2, Star, StarOff, Search, Plus } from 'lucide-react';
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
      toast.error('Failed to load featured listings');
    } finally {
      setFeaturedLoading(false);
    }
  }, []);

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
        toast.success('Listing featured successfully');
        setSearchQuery('');
        setSearchResults([]);
        loadFeatured();
      } else {
        toast.error(res?.error || 'Failed to feature listing');
      }
    } catch {
      toast.error('An unexpected error occurred');
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
        toast.success('Listing unfeatured successfully');
        loadFeatured();
      } else {
        toast.error(res?.error || 'Failed to unfeature listing');
      }
    } catch {
      toast.error('An unexpected error occurred');
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
      label: 'Listing Title',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'type',
      label: 'Type',
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color={typeColors[item.type] || 'default'} className="capitalize">
          {item.type}
        </Chip>
      ),
    },
    {
      key: 'user_name',
      label: 'Author',
      sortable: true,
    },
    {
      key: 'featured_at',
      label: 'Featured Date',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.featured_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'featured_by',
      label: 'Featured By',
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.featured_by || '—'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <Button
          size="sm"
          variant="flat"
          color="warning"
          startContent={<StarOff size={14} />}
          onPress={() => setUnfeatureConfirm(item)}
        >
          Unfeature
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
            Feature a Listing
          </h3>
          <Input
            placeholder="Search active listings to feature..."
            aria-label="Search listings"
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
                      by {listing.user_name} &middot;{' '}
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
                    Feature
                  </Button>
                </div>
              ))}
            </div>
          )}
          {searchQuery.length >= 2 && !searching && filteredResults.length === 0 && searchResults.length === 0 && (
            <p className="text-sm text-default-400 text-center py-2">
              No active listings found matching &ldquo;{searchQuery}&rdquo;
            </p>
          )}
        </CardBody>
      </Card>

      {/* Currently featured listings table */}
      <div>
        <h3 className="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
          <Star size={16} className="text-warning" />
          Currently Featured ({featured.length})
        </h3>
        <DataTable
          columns={featuredColumns}
          data={featured}
          keyField="listing_id"
          isLoading={featuredLoading}
          searchable={false}
          onRefresh={loadFeatured}
          emptyContent="No featured listings. Use the search above to feature a listing."
        />
      </div>

      {/* Unfeature confirmation modal */}
      {unfeatureConfirm && (
        <ConfirmModal
          isOpen={!!unfeatureConfirm}
          onClose={() => setUnfeatureConfirm(null)}
          onConfirm={handleUnfeature}
          title="Remove from Featured"
          message={`Remove "${unfeatureConfirm.title}" from featured listings? It will no longer appear in the featured section.`}
          confirmLabel="Unfeature"
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
  usePageTitle('Admin - Content');
  const toast = useToast();

  const [activeTab, setActiveTab] = useState('content');
  const [items, setItems] = useState<AdminListing[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [confirmAction, setConfirmAction] = useState<{
    type: 'approve' | 'delete';
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
          setTotal(data.length);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: AdminListing[]; meta?: { total: number } };
          setItems(pd.data || []);
          setTotal(pd.meta?.total || 0);
        }
      }
    } catch {
      toast.error('Failed to load content');
    } finally {
      setLoading(false);
    }
  }, [page, status, search]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleAction = async () => {
    if (!confirmAction) return;
    setActionLoading(true);
    try {
      const { type, item } = confirmAction;
      const res = type === 'approve'
        ? await adminListings.approve(item.id)
        : await adminListings.delete(item.id);

      if (res?.success) {
        toast.success(`Content ${type}d successfully`);
        loadItems();
      } else {
        toast.error(res?.error || `Failed to ${type} content`);
      }
    } catch {
      toast.error('An unexpected error occurred');
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
            ? `"${item.title}" removed from featured`
            : `"${item.title}" is now featured`
        );
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to update featured status');
      }
    } catch {
      toast.error('An unexpected error occurred');
    }
  };

  const columns: Column<AdminListing>[] = [
    {
      key: 'title',
      label: 'Title',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'type',
      label: 'Type',
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color={typeColors[item.type] || 'default'} className="capitalize">
          {item.type}
        </Chip>
      ),
    },
    {
      key: 'user_name',
      label: 'Author',
      sortable: true,
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'created_at',
      label: 'Created',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <div className="flex gap-1">
          {item.status === 'pending' && (
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="success"
              onPress={() => setConfirmAction({ type: 'approve', item })}
              aria-label="Approve"
            >
              <CheckCircle size={14} />
            </Button>
          )}
          <Tooltip content={item.is_featured ? 'Unfeature' : 'Feature'}>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="warning"
              onPress={() => handleFeatureToggle(item)}
              aria-label={item.is_featured ? 'Unfeature listing' : 'Feature listing'}
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
            aria-label="Delete"
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
        title="Content Directory"
        description="View and moderate all user-generated content"
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
          <Tab key="content" title="Content" />
          <Tab
            key="featured"
            title={
              <div className="flex items-center gap-1.5">
                <Star size={14} />
                Featured
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
              <Tab key="all" title="All" />
              <Tab key="pending" title="Pending" />
              <Tab key="active" title="Active" />
              <Tab key="inactive" title="Inactive" />
            </Tabs>
          </div>

          <DataTable
            columns={columns}
            data={items}
            isLoading={loading}
            searchPlaceholder="Search content..."
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
              title={confirmAction.type === 'approve' ? 'Approve Content' : 'Delete Content'}
              message={
                confirmAction.type === 'approve'
                  ? `Approve "${confirmAction.item.title}"? It will become visible to all users.`
                  : `Delete "${confirmAction.item.title}"? This cannot be undone.`
              }
              confirmLabel={confirmAction.type === 'approve' ? 'Approve' : 'Delete'}
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
