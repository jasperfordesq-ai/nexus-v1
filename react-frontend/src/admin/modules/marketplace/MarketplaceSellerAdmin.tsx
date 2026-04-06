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
  user_id: number;
  display_name: string;
  seller_type: 'private' | 'business';
  business_name: string | null;
  business_verified: boolean;
  is_community_endorsed: boolean;
  active_listings: number;
  total_sales: number;
  avg_rating: number;
  total_ratings: number;
  joined_marketplace_at: string | null;
  user: { id: number; name: string; email: string; avatar_url: string | null } | null;
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
      const params = new URLSearchParams({ page: String(page), per_page: '20' });
      if (search) params.set('search', search);
      if (filter !== 'all') {
        // Map UI filter tabs to API parameters
        if (filter === 'private' || filter === 'business') {
          params.set('seller_type', filter);
        } else if (filter === 'verified') {
          params.set('verified', 'true');
        } else if (filter === 'unverified') {
          params.set('verified', 'false');
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
        toast.success(t('marketplace.seller_verified', { name: confirmVerify.display_name }));
        loadSellers();
      } else {
        toast.error((res as { error?: string }).error || t('marketplace.failed_verify_seller'));
      }
    } catch {
      toast.error(t('marketplace.unexpected_error'));
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
        toast.success(t('marketplace.seller_suspended', { name: confirmSuspend.display_name }));
        loadSellers();
      } else {
        toast.error((res as { error?: string }).error || t('marketplace.failed_suspend_seller'));
      }
    } catch {
      toast.error(t('marketplace.unexpected_error'));
    } finally {
      setActionLoading(false);
      setConfirmSuspend(null);
    }
  };

  // ─── Render star rating ────────────────────────────────────────────────────

  function renderRating(rating: number) {
    const num = Number(rating);
    if (!num || num === 0) {
      return <span className="text-sm text-default-400">--</span>;
    }
    return (
      <div className="flex items-center gap-1">
        <Star size={14} className="text-warning fill-warning" />
        <span className="text-sm text-default-600">{num.toFixed(1)}</span>
      </div>
    );
  }

  // ─── Table columns ─────────────────────────────────────────────────────────

  const columns: Column<Seller>[] = [
    {
      key: 'display_name',
      label: t('marketplace.col_seller_name'),
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-2">
          <span className="font-medium text-foreground">{item.display_name}</span>
          {item.business_verified && (
            <Tooltip content={t('marketplace.verified_seller')}>
              <BadgeCheck size={16} className="text-success shrink-0" />
            </Tooltip>
          )}
        </div>
      ),
    },
    {
      key: 'seller_type',
      label: t('marketplace.col_type'),
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={typeColors[item.seller_type] || 'default'}
          className="capitalize"
        >
          {item.seller_type}
        </Chip>
      ),
    },
    {
      key: 'active_listings',
      label: t('marketplace.col_active_listings'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.active_listings}</span>
      ),
    },
    {
      key: 'total_sales',
      label: t('marketplace.col_total_sales'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.total_sales}</span>
      ),
    },
    {
      key: 'avg_rating',
      label: t('marketplace.col_avg_rating'),
      sortable: true,
      render: (item) => renderRating(item.avg_rating),
    },
    {
      key: 'joined_marketplace_at',
      label: t('marketplace.col_joined'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.joined_marketplace_at
            ? new Date(item.joined_marketplace_at).toLocaleDateString()
            : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('marketplace.col_actions'),
      render: (item) => (
        <div className="flex gap-1">
          {item.seller_type === 'business' && !item.business_verified && (
            <Tooltip content={t('marketplace.action_verify_business')}>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="success"
                onPress={() => setConfirmVerify(item)}
                isDisabled={actionLoading}
                aria-label={t('marketplace.action_verify_seller')}
              >
                <ShieldCheck size={14} />
              </Button>
            </Tooltip>
          )}
          <Tooltip content={t('marketplace.action_suspend_seller')}>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="danger"
              onPress={() => setConfirmSuspend(item)}
              isDisabled={actionLoading}
              aria-label={t('marketplace.action_suspend_seller')}
            >
              <UserX size={14} />
            </Button>
          </Tooltip>
          <Tooltip content={t('marketplace.action_view_profile')}>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="primary"
              as="a"
              href={tenantPath(`/profile/${item.user_id}`)}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={t('marketplace.action_view_seller_profile')}
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
        title={t('marketplace.sellers_title')}
        description={t('marketplace.sellers_description')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadSellers}
          >
            {t('marketplace.refresh')}
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
        searchPlaceholder={t('marketplace.search_sellers')}
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
            title={t('marketplace.no_sellers_found')}
            description={
              search || filter !== 'all'
                ? t('marketplace.try_adjusting_filters')
                : t('marketplace.no_sellers_registered_yet')
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
          title={t('marketplace.verify_seller_title')}
          message={t('marketplace.verify_seller_message', { name: confirmVerify.display_name })}
          confirmLabel={t('marketplace.verify_btn')}
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
          title={t('marketplace.suspend_seller_title')}
          message={t('marketplace.suspend_seller_message', { name: confirmSuspend.display_name })}
          confirmLabel={t('marketplace.suspend_btn')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default MarketplaceSellerAdmin;
