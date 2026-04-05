// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Marketplace Seller Management
 * View, filter, verify, and suspend marketplace seller profiles.
 */

import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Tabs,
  Tab,
  Button,
  Chip,
  Tooltip,
} from '@heroui/react';
import {
  Store,
  ShieldCheck,
  UserX,
  Eye,
  RefreshCw,
  BadgeCheck,
  Star,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, DataTable, ConfirmModal, EmptyState, type Column } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Seller {
  id: number;
  name: string;
  type: 'private' | 'business';
  is_verified: boolean;
  active_listings: number;
  total_sales: number;
  avg_rating: number;
  joined_at: string;
  status: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const SELLER_FILTER_TABS = ['all', 'private', 'business', 'verified', 'unverified'] as const;

const typeColors: Record<string, 'primary' | 'secondary' | 'default'> = {
  private: 'default',
  business: 'primary',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function MarketplaceSellerAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('marketplace.sellers_page_title'));
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [sellers, setSellers] = useState<Seller[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');

  // Action states
  const [actionLoading, setActionLoading] = useState(false);
  const [confirmVerify, setConfirmVerify] = useState<Seller | null>(null);
  const [confirmSuspend, setConfirmSuspend] = useState<Seller | null>(null);

  const loadSellers = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page), limit: '20' });
      if (search) params.set('search', search);
      if (filter !== 'all') {
        // Map UI filter tabs to API parameters
        if (filter === 'private' || filter === 'business') {
          params.set('type', filter);
        } else if (filter === 'verified') {
          params.set('verified', '1');
        } else if (filter === 'unverified') {
          params.set('verified', '0');
        }
      }

      const res = await api.get<Seller[]>(
        `/v2/admin/marketplace/sellers?${params.toString()}`
      );

      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setSellers(data);
          const metaTotal = (res.meta as Record<string, unknown> | undefined)?.total;
          setTotal(typeof metaTotal === 'number' ? metaTotal : data.length);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: Seller[]; meta?: { total: number } };
          setSellers(pd.data || []);
          setTotal(pd.meta?.total || 0);
        }
      }
    } catch {
      toast.error(t('marketplace.failed_load_sellers'));
    } finally {
      setLoading(false);
    }
  }, [page, filter, search, toast]);

  useEffect(() => {
    loadSellers();
  }, [loadSellers]);

  // ─── Actions ────────────────────────────────────────────────────────────────

  const handleVerify = async () => {
    if (!confirmVerify) return;
    setActionLoading(true);
    try {
      const res = await api.post(`/v2/admin/marketplace/sellers/${confirmVerify.id}/verify`);
      if (res?.success) {
        toast.success(`${confirmVerify.name} has been verified`);
        loadSellers();
      } else {
        toast.error((res as { error?: string }).error || 'Failed to verify seller');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmVerify(null);
    }
  };

  const handleSuspend = async () => {
    if (!confirmSuspend) return;
    setActionLoading(true);
    try {
      const res = await api.post(`/v2/admin/marketplace/sellers/${confirmSuspend.id}/suspend`);
      if (res?.success) {
        toast.success(`${confirmSuspend.name} has been suspended`);
        loadSellers();
      } else {
        toast.error((res as { error?: string }).error || 'Failed to suspend seller');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmSuspend(null);
    }
  };

  // ─── Render star rating ────────────────────────────────────────────────────

  function renderRating(rating: number) {
    if (!rating || rating === 0) {
      return <span className="text-sm text-default-400">--</span>;
    }
    return (
      <div className="flex items-center gap-1">
        <Star size={14} className="text-warning fill-warning" />
        <span className="text-sm text-default-600">{rating.toFixed(1)}</span>
      </div>
    );
  }

  // ─── Table columns ─────────────────────────────────────────────────────────

  const columns: Column<Seller>[] = [
    {
      key: 'name',
      label: 'Seller Name',
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-2">
          <span className="font-medium text-foreground">{item.name}</span>
          {item.is_verified && (
            <Tooltip content="Verified seller">
              <BadgeCheck size={16} className="text-success shrink-0" />
            </Tooltip>
          )}
        </div>
      ),
    },
    {
      key: 'type',
      label: 'Type',
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={typeColors[item.type] || 'default'}
          className="capitalize"
        >
          {item.type}
        </Chip>
      ),
    },
    {
      key: 'active_listings',
      label: 'Active Listings',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.active_listings}</span>
      ),
    },
    {
      key: 'total_sales',
      label: 'Total Sales',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.total_sales}</span>
      ),
    },
    {
      key: 'avg_rating',
      label: 'Avg Rating',
      sortable: true,
      render: (item) => renderRating(item.avg_rating),
    },
    {
      key: 'joined_at',
      label: 'Joined',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.joined_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <div className="flex gap-1">
          {item.type === 'business' && !item.is_verified && (
            <Tooltip content="Verify business">
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="success"
                onPress={() => setConfirmVerify(item)}
                isDisabled={actionLoading}
                aria-label="Verify seller"
              >
                <ShieldCheck size={14} />
              </Button>
            </Tooltip>
          )}
          <Tooltip content="Suspend seller">
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="danger"
              onPress={() => setConfirmSuspend(item)}
              isDisabled={actionLoading}
              aria-label="Suspend seller"
            >
              <UserX size={14} />
            </Button>
          </Tooltip>
          <Tooltip content="View profile">
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="primary"
              as="a"
              href={tenantPath(`/profile/${item.id}`)}
              target="_blank"
              rel="noopener noreferrer"
              aria-label="View seller profile"
            >
              <Eye size={14} />
            </Button>
          </Tooltip>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Marketplace Sellers"
        description="Manage seller profiles, verify businesses, and handle suspensions"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadSellers}
          >
            Refresh
          </Button>
        }
      />

      {/* Filter tabs */}
      <div className="mb-4">
        <Tabs
          selectedKey={filter}
          onSelectionChange={(key) => {
            setFilter(key as string);
            setPage(1);
          }}
          variant="underlined"
          size="sm"
        >
          {SELLER_FILTER_TABS.map((tab) => (
            <Tab key={tab} title={<span className="capitalize">{tab}</span>} />
          ))}
        </Tabs>
      </div>

      {/* Data table */}
      <DataTable
        columns={columns}
        data={sellers}
        isLoading={loading}
        searchPlaceholder="Search sellers..."
        onSearch={(q) => {
          setSearch(q);
          setPage(1);
        }}
        onRefresh={loadSellers}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        emptyContent={
          <EmptyState
            icon={Store}
            title="No sellers found"
            description={
              search || filter !== 'all'
                ? 'Try adjusting your filters or search query'
                : 'No marketplace sellers have registered yet'
            }
          />
        }
      />

      {/* Verify confirmation */}
      {confirmVerify && (
        <ConfirmModal
          isOpen={!!confirmVerify}
          onClose={() => setConfirmVerify(null)}
          onConfirm={handleVerify}
          title="Verify Business Seller"
          message={`Are you sure you want to verify "${confirmVerify.name}" as a trusted business seller? This will display a verified badge on their profile and listings.`}
          confirmLabel="Verify"
          confirmColor="primary"
          isLoading={actionLoading}
        />
      )}

      {/* Suspend confirmation */}
      {confirmSuspend && (
        <ConfirmModal
          isOpen={!!confirmSuspend}
          onClose={() => setConfirmSuspend(null)}
          onConfirm={handleSuspend}
          title="Suspend Seller"
          message={`Are you sure you want to suspend "${confirmSuspend.name}"? Their active listings will be hidden and they will be unable to create new listings.`}
          confirmLabel="Suspend"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default MarketplaceSellerAdmin;
