// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Dashboard
 * Overview with key metrics, activity log, monthly trends, and quick actions.
 * Parity: PHP AdminController::index() / views/modern/admin/dashboard.php
 *
 * Parity notes (vs legacy PHP dashboard):
 * - Stats: Total Users, Active Listings, Transactions, Hours Exchanged -- DONE
 * - Pending alerts: Users, Listings -- DONE
 * - Quick Actions sidebar: Manage Users, View Listings, Newsletters, Blog, Gamification, Settings -- DONE
 * - Activity Log -- DONE
 * - Monthly Trends -- DONE (text bars; legacy uses Chart.js line chart)
 *
 * NOT yet implemented (parity gaps):
 * - Users Online / Active Sessions (LIVE stats) -- backend has no API for this
 * - Pending Organizations alert -- backend stats endpoint does not return pending_orgs
 * - Platform Modules grid (15 module cards) -- low priority, sidebar already provides navigation
 * - System Status panel (Database/Cache/Cron/Email) -- available at /admin/enterprise/monitoring
 * - Enterprise Suite sidebar links -- available at /admin/enterprise
 * - Transaction Volume chart (Chart.js line chart) -- current text bars are functional
 * - Onboarding Tour -- low priority
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Spinner, Button, Chip } from '@heroui/react';
import {
  Users,
  ListChecks,
  ArrowLeftRight,
  Clock,
  UserCheck,
  UserPlus,
  FileCheck,
  TrendingUp,
  Activity,
  RefreshCw,
  Send,
  PenSquare,
  Trophy,
  Settings,
  Rocket,
  ChevronRight,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminDashboard } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { AdminDashboardStats, ActivityLogEntry, MonthlyTrend } from '../../api/types';

/** Quick action items matching the legacy PHP dashboard sidebar */
const QUICK_ACTIONS = [
  { label: 'Manage Users', path: '/admin/users', icon: UserPlus, color: 'text-primary bg-primary/10' },
  { label: 'View Listings', path: '/admin/listings', icon: ListChecks, color: 'text-success bg-success/10' },
  { label: 'Send Newsletter', path: '/admin/newsletters', icon: Send, color: 'text-secondary bg-secondary/10' },
  { label: 'New Blog Post', path: '/admin/blog/create', icon: PenSquare, color: 'text-danger bg-danger/10' },
  { label: 'Gamification', path: '/admin/gamification', icon: Trophy, color: 'text-warning bg-warning/10' },
  { label: 'Settings', path: '/admin/settings', icon: Settings, color: 'text-default-600 bg-default/20' },
] as const;

export function AdminDashboard() {
  usePageTitle('Admin Dashboard');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [stats, setStats] = useState<AdminDashboardStats | null>(null);
  const [activity, setActivity] = useState<ActivityLogEntry[]>([]);
  const [trends, setTrends] = useState<MonthlyTrend[]>([]);
  const [loading, setLoading] = useState(true);

  const loadDashboard = useCallback(async () => {
    setLoading(true);
    try {
      const [statsRes, activityRes, trendsRes] = await Promise.all([
        adminDashboard.getStats(),
        adminDashboard.getActivity(1, 10),
        adminDashboard.getTrends(6),
      ]);

      if (statsRes.success && statsRes.data) {
        setStats(statsRes.data);
      }
      if (activityRes.success) {
        // After api.ts unwrapping, activityRes.data is already ActivityLogEntry[]
        const items = activityRes.data;
        if (Array.isArray(items)) {
          setActivity(items);
        }
      }
      if (trendsRes.success && trendsRes.data) {
        setTrends(Array.isArray(trendsRes.data) ? trendsRes.data : []);
      }
    } catch {
      toast.error('Failed to load dashboard data');
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
        title="Dashboard"
        description="Overview of your community platform"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadDashboard}
            isLoading={loading}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      {/* Stats Grid - Row 1: Core Metrics */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-4">
        <StatCard
          label="Total Users"
          value={stats?.total_users ?? '—'}
          icon={Users}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Active Listings"
          value={stats?.active_listings ?? '—'}
          icon={FileCheck}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Transactions"
          value={stats?.total_transactions ?? '—'}
          icon={ArrowLeftRight}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label="Hours Exchanged"
          value={stats?.total_hours_exchanged ?? '—'}
          icon={Clock}
          color="warning"
          loading={loading}
        />
      </div>

      {/* Stats Grid - Row 2: This Month */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="New Users This Month"
          value={stats?.new_users_this_month ?? '—'}
          icon={UserPlus}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Active Users"
          value={stats?.active_users ?? '—'}
          icon={UserCheck}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Total Listings"
          value={stats?.total_listings ?? '—'}
          icon={ListChecks}
          color="default"
          loading={loading}
        />
        <StatCard
          label="New Listings This Month"
          value={stats?.new_listings_this_month ?? '—'}
          icon={ListChecks}
          color="default"
          loading={loading}
        />
      </div>

      {/* Actionable Alerts: Pending Users + Pending Listings */}
      {((stats?.pending_users ?? 0) > 0 || (stats?.pending_listings ?? 0) > 0) && (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3 mb-6">
          {stats?.pending_users !== undefined && stats.pending_users > 0 && (
            <Card shadow="sm">
              <CardBody className="flex flex-row items-center gap-4 p-4">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                  <UserCheck size={20} className="text-warning" />
                </div>
                <div className="flex-1">
                  <p className="text-sm text-default-500">Pending Approvals</p>
                  <p className="text-lg font-bold">{stats.pending_users}</p>
                </div>
                <Button
                  as={Link}
                  to={tenantPath('/admin/users?filter=pending')}
                  size="sm"
                  color="warning"
                  variant="flat"
                >
                  Review
                </Button>
              </CardBody>
            </Card>
          )}

          {stats?.pending_listings !== undefined && stats.pending_listings > 0 && (
            <Card shadow="sm">
              <CardBody className="flex flex-row items-center gap-4 p-4">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                  <ListChecks size={20} className="text-primary" />
                </div>
                <div className="flex-1">
                  <p className="text-sm text-default-500">Pending Listings</p>
                  <p className="text-lg font-bold">{stats.pending_listings}</p>
                </div>
                <Button
                  as={Link}
                  to={tenantPath('/admin/listings?status=pending')}
                  size="sm"
                  color="primary"
                  variant="flat"
                >
                  Review
                </Button>
              </CardBody>
            </Card>
          )}
        </div>
      )}

      {/* Quick Actions + Trends + Activity -- 3-column layout on large screens */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">

        {/* Quick Actions (matches legacy sidebar) */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Rocket size={18} className="text-primary" />
            <h3 className="font-semibold">Quick Actions</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <div className="grid grid-cols-2 gap-2">
              {QUICK_ACTIONS.map((action) => {
                const Icon = action.icon;
                return (
                  <Link
                    key={action.path}
                    to={tenantPath(action.path)}
                    className="flex flex-col items-center gap-2 rounded-xl border border-divider p-3 transition-colors hover:bg-default-100"
                  >
                    <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${action.color}`}>
                      <Icon size={20} />
                    </div>
                    <span className="text-xs font-medium text-center text-default-700">{action.label}</span>
                  </Link>
                );
              })}
            </div>
            <div className="mt-3 pt-3 border-t border-divider">
              <Link
                to={tenantPath('/admin/enterprise')}
                className="flex items-center justify-between text-sm text-primary hover:underline"
              >
                <span className="flex items-center gap-1.5">
                  <Chip size="sm" color="secondary" variant="flat">Enterprise</Chip>
                  Advanced Controls
                </span>
                <ChevronRight size={14} />
              </Link>
            </div>
          </CardBody>
        </Card>

        {/* Monthly Trends */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <TrendingUp size={18} className="text-primary" />
            <h3 className="font-semibold">Monthly Trends</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-48 items-center justify-center">
                <Spinner />
              </div>
            ) : trends.length > 0 ? (
              <div className="space-y-3">
                {trends.map((t) => (
                  <div key={t.month} className="flex items-center justify-between">
                    <span className="text-sm text-default-600">{t.month}</span>
                    <div className="flex items-center gap-4">
                      <span className="text-sm font-medium">{t.hours} hrs</span>
                      <div
                        className="h-2 rounded-full bg-primary"
                        style={{ width: `${Math.min(100, (t.hours / Math.max(...trends.map((x) => x.hours || 1))) * 100)}px` }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-default-400">
                No trend data available yet
              </p>
            )}
          </CardBody>
        </Card>

        {/* Recent Activity */}
        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between px-4 pt-4 pb-0">
            <div className="flex items-center gap-2">
              <Activity size={18} className="text-primary" />
              <h3 className="font-semibold">Recent Activity</h3>
            </div>
            <Button
              as={Link}
              to={tenantPath('/admin/activity-log')}
              size="sm"
              variant="light"
            >
              View all
            </Button>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-48 items-center justify-center">
                <Spinner />
              </div>
            ) : activity.length > 0 ? (
              <div className="space-y-3">
                {activity.map((entry) => (
                  <div key={entry.id} className="flex items-start gap-3 border-b border-divider pb-3 last:border-0 last:pb-0">
                    <div className="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-primary" />
                    <div className="min-w-0 flex-1">
                      <p className="text-sm">
                        <span className="font-medium">{entry.user_name}</span>{' '}
                        <span className="text-default-500">{entry.description}</span>
                      </p>
                      <p className="text-xs text-default-400">
                        {new Date(entry.created_at).toLocaleString()}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-default-400">
                No recent activity
              </p>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default AdminDashboard;
