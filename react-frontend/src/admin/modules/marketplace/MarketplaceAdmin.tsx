// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Marketplace Dashboard
 * Overview with key metrics, recent listings, and quick navigation to
 * the moderation queue and seller management sub-pages.
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Chip,
  Spinner,
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableColumn,
  TableCell,
} from '@heroui/react';
import {
  ShoppingBag,
  Store,
  PackageCheck,
  Clock,
  DollarSign,
  RefreshCw,
  ChevronRight,
  Shield,
  Users,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { StatCard, PageHeader } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface DashboardStats {
  total_listings: number;
  active_listings: number;
  total_sellers: number;
  pending_moderation: number;
  total_orders: number;
  revenue: number;
}

interface RecentListing {
  id: number;
  title: string;
  seller_name: string;
  status: string;
  moderation_status: string;
  price: number;
  currency: string;
  created_at: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Status colors
// ─────────────────────────────────────────────────────────────────────────────

const moderationColors: Record<string, 'success' | 'warning' | 'danger' | 'default' | 'primary'> = {
  approved: 'success',
  pending: 'warning',
  rejected: 'danger',
  flagged: 'danger',
  draft: 'default',
};

const statusColors: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  active: 'success',
  inactive: 'default',
  sold: 'primary' as 'success',
  expired: 'warning',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function MarketplaceAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('marketplace.page_title'));
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [recentListings, setRecentListings] = useState<RecentListing[]>([]);
  const [loading, setLoading] = useState(true);

  const loadDashboard = useCallback(async () => {
    setLoading(true);
    try {
      const [statsRes, listingsRes] = await Promise.all([
        api.get<DashboardStats>('/v2/admin/marketplace/dashboard'),
        api.get<RecentListing[]>('/v2/admin/marketplace/listings?limit=10'),
      ]);

      if (statsRes.success && statsRes.data) {
        const data = statsRes.data as unknown as DashboardStats;
        setStats(data);
      }

      if (listingsRes.success && listingsRes.data) {
        const data = listingsRes.data as unknown;
        if (Array.isArray(data)) {
          setRecentListings(data);
        } else if (data && typeof data === 'object') {
          const pd = data as { data: RecentListing[] };
          setRecentListings(pd.data || []);
        }
      }
    } catch {
      toast.error(t('marketplace.failed_load_dashboard'));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadDashboard();
  }, [loadDashboard]);

  return (
    <div>
      <PageHeader
        title={t('marketplace.title')}
        description={t('marketplace.description')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadDashboard}
          >
            {t('marketplace.refresh')}
          </Button>
        }
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6">
        <StatCard
          label={t('marketplace.stat_total_listings')}
          value={stats?.total_listings ?? 0}
          icon={ShoppingBag}
          color="primary"
          loading={loading}
        />
        <StatCard
          label={t('marketplace.stat_active_listings')}
          value={stats?.active_listings ?? 0}
          icon={PackageCheck}
          color="success"
          loading={loading}
        />
        <StatCard
          label={t('marketplace.stat_total_sellers')}
          value={stats?.total_sellers ?? 0}
          icon={Store}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label={t('marketplace.stat_pending_moderation')}
          value={stats?.pending_moderation ?? 0}
          icon={Clock}
          color="warning"
          loading={loading}
        />
        <StatCard
          label={t('marketplace.stat_total_orders')}
          value={stats?.total_orders ?? 0}
          icon={DollarSign}
          color="default"
          loading={loading}
        />
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
        <Link to={tenantPath('/admin/marketplace/moderation')}>
          <Card shadow="sm" isPressable className="w-full">
            <CardBody className="flex flex-row items-center gap-4 p-4">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-warning bg-warning/10">
                <Shield size={24} />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-foreground">{t('marketplace.moderation_queue')}</p>
                <p className="text-sm text-default-500">
                  {t('marketplace.moderation_queue_desc')}
                </p>
              </div>
              <ChevronRight size={20} className="text-default-400" />
            </CardBody>
          </Card>
        </Link>
        <Link to={tenantPath('/admin/marketplace/sellers')}>
          <Card shadow="sm" isPressable className="w-full">
            <CardBody className="flex flex-row items-center gap-4 p-4">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-primary bg-primary/10">
                <Users size={24} />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-foreground">{t('marketplace.seller_management')}</p>
                <p className="text-sm text-default-500">
                  {t('marketplace.seller_management_desc')}
                </p>
              </div>
              <ChevronRight size={20} className="text-default-400" />
            </CardBody>
          </Card>
        </Link>
      </div>

      {/* Recent Listings Table */}
      <Card shadow="sm">
        <CardHeader className="flex items-center justify-between px-4 pt-4">
          <h3 className="text-lg font-semibold text-foreground">{t('marketplace.recent_listings')}</h3>
          <Link to={tenantPath('/admin/marketplace/moderation')}>
            <Button size="sm" variant="flat" color="primary">
              {t('marketplace.view_all')}
            </Button>
          </Link>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          {loading ? (
            <div className="flex items-center justify-center py-12">
              <Spinner label={t('marketplace.loading_recent_listings')} />
            </div>
          ) : recentListings.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <ShoppingBag size={32} className="text-default-300 mb-2" />
              <p className="text-sm text-default-500">{t('marketplace.no_listings_yet')}</p>
            </div>
          ) : (
            <Table aria-label={t('marketplace.recent_listings')} removeWrapper>
              <TableHeader>
                <TableColumn>{t('marketplace.col_title')}</TableColumn>
                <TableColumn>{t('marketplace.col_seller')}</TableColumn>
                <TableColumn>{t('marketplace.col_price')}</TableColumn>
                <TableColumn>{t('marketplace.col_status')}</TableColumn>
                <TableColumn>{t('marketplace.col_moderation')}</TableColumn>
                <TableColumn>{t('marketplace.col_created')}</TableColumn>
              </TableHeader>
              <TableBody>
                {recentListings.map((listing) => (
                  <TableRow key={listing.id}>
                    <TableCell>
                      <span className="font-medium text-foreground">{listing.title}</span>
                    </TableCell>
                    <TableCell className="text-default-600">{listing.seller_name}</TableCell>
                    <TableCell className="text-default-600">
                      {listing.currency ?? ''}{listing.price?.toFixed(2) ?? '0.00'}
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={statusColors[listing.status] || 'default'}
                        className="capitalize"
                      >
                        {listing.status}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={moderationColors[listing.moderation_status] || 'default'}
                        className="capitalize"
                      >
                        {listing.moderation_status}
                      </Chip>
                    </TableCell>
                    <TableCell className="text-default-500">
                      {new Date(listing.created_at).toLocaleDateString()}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default MarketplaceAdmin;
