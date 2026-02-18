/**
 * Admin Dashboard
 * Overview with key metrics, activity log, and monthly trends.
 * Parity: PHP AdminController::index()
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Spinner, Button } from '@heroui/react';
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
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminDashboard } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { AdminDashboardStats, ActivityLogEntry, MonthlyTrend } from '../../api/types';

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
      if (activityRes.success && activityRes.data) {
        const actData = activityRes.data as unknown;
        if (Array.isArray(actData)) {
          setActivity(actData);
        } else if (actData && typeof actData === 'object' && 'data' in (actData as Record<string, unknown>)) {
          setActivity((actData as { data: ActivityLogEntry[] }).data);
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

      {/* Quick Actions + Pending */}
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

      {/* Trends Chart + Activity Log */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
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
