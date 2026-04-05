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
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Chip,
  Spinner,
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
  usePageTitle('Marketplace Admin');
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
      toast.error('Failed to load marketplace dashboard');
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
        title="Marketplace"
        description="Manage marketplace listings, sellers, and orders"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadDashboard}
          >
            Refresh
          </Button>
        }
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6">
        <StatCard
          label="Total Listings"
          value={stats?.total_listings ?? 0}
          icon={ShoppingBag}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Active Listings"
          value={stats?.active_listings ?? 0}
          icon={PackageCheck}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Total Sellers"
          value={stats?.total_sellers ?? 0}
          icon={Store}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label="Pending Moderation"
          value={stats?.pending_moderation ?? 0}
          icon={Clock}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="Total Orders"
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
                <p className="font-semibold text-foreground">Moderation Queue</p>
                <p className="text-sm text-default-500">
                  Review and approve pending marketplace listings
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
                <p className="font-semibold text-foreground">Seller Management</p>
                <p className="text-sm text-default-500">
                  Manage seller profiles, verify businesses, handle suspensions
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
          <h3 className="text-lg font-semibold text-foreground">Recent Listings</h3>
          <Link to={tenantPath('/admin/marketplace/moderation')}>
            <Button size="sm" variant="flat" color="primary">
              View All
            </Button>
          </Link>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          {loading ? (
            <div className="flex items-center justify-center py-12">
              <Spinner label="Loading recent listings..." />
            </div>
          ) : recentListings.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <ShoppingBag size={32} className="text-default-300 mb-2" />
              <p className="text-sm text-default-500">No marketplace listings yet</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-divider">
                    <th className="py-2 px-3 text-left text-xs font-medium text-default-500 uppercase">Title</th>
                    <th className="py-2 px-3 text-left text-xs font-medium text-default-500 uppercase">Seller</th>
                    <th className="py-2 px-3 text-left text-xs font-medium text-default-500 uppercase">Price</th>
                    <th className="py-2 px-3 text-left text-xs font-medium text-default-500 uppercase">Status</th>
                    <th className="py-2 px-3 text-left text-xs font-medium text-default-500 uppercase">Moderation</th>
                    <th className="py-2 px-3 text-left text-xs font-medium text-default-500 uppercase">Created</th>
                  </tr>
                </thead>
                <tbody>
                  {recentListings.map((listing) => (
                    <tr key={listing.id} className="border-b border-divider last:border-0">
                      <td className="py-2.5 px-3">
                        <span className="font-medium text-foreground">{listing.title}</span>
                      </td>
                      <td className="py-2.5 px-3 text-default-600">{listing.seller_name}</td>
                      <td className="py-2.5 px-3 text-default-600">
                        {listing.currency ?? ''}{listing.price?.toFixed(2) ?? '0.00'}
                      </td>
                      <td className="py-2.5 px-3">
                        <Chip
                          size="sm"
                          variant="flat"
                          color={statusColors[listing.status] || 'default'}
                          className="capitalize"
                        >
                          {listing.status}
                        </Chip>
                      </td>
                      <td className="py-2.5 px-3">
                        <Chip
                          size="sm"
                          variant="flat"
                          color={moderationColors[listing.moderation_status] || 'default'}
                          className="capitalize"
                        >
                          {listing.moderation_status}
                        </Chip>
                      </td>
                      <td className="py-2.5 px-3 text-default-500">
                        {new Date(listing.created_at).toLocaleDateString()}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default MarketplaceAdmin;
