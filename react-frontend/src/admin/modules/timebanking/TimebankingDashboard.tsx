/**
 * Timebanking Dashboard
 * Transaction analytics overview with top earners/spenders and quick links.
 * Parity: PHP Admin\TimebankingController::index()
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Button, Spinner } from '@heroui/react';
import {
  ArrowLeftRight,
  AlertTriangle,
  TrendingUp,
  Wallet,
  Users,
  Building2,
  RefreshCw,
  ChevronRight,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminTimebanking } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { TimebankingStats } from '../../api/types';

export function TimebankingDashboard() {
  usePageTitle('Admin - Timebanking');
  const { tenantPath } = useTenant();

  const [stats, setStats] = useState<TimebankingStats | null>(null);
  const [loading, setLoading] = useState(true);

  const loadStats = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTimebanking.getStats();
      if (res.success && res.data) {
        setStats(res.data as unknown as TimebankingStats);
      }
    } catch {
      // Silently handle — stats will show loading state
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadStats();
  }, [loadStats]);

  return (
    <div>
      <PageHeader
        title="Timebanking"
        description="Transaction analytics and oversight"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadStats}
            isLoading={loading}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      {/* Stats Grid */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Total Transactions"
          value={stats?.total_transactions ?? '—'}
          icon={ArrowLeftRight}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Total Volume (hrs)"
          value={stats?.total_volume ?? '—'}
          icon={TrendingUp}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Avg Transaction (hrs)"
          value={stats?.avg_transaction ?? '—'}
          icon={Wallet}
          color="secondary"
          loading={loading}
        />
        <StatCard
          label="Active Alerts"
          value={stats?.active_alerts ?? '—'}
          icon={AlertTriangle}
          color={stats?.active_alerts && stats.active_alerts > 0 ? 'danger' : 'warning'}
          loading={loading}
        />
      </div>

      {/* Top Earners & Top Spenders */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
        {/* Top Earners */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <TrendingUp size={18} className="text-success" />
            <h3 className="font-semibold">Top Earners</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-40 items-center justify-center">
                <Spinner />
              </div>
            ) : stats?.top_earners && stats.top_earners.length > 0 ? (
              <div className="space-y-3">
                {stats.top_earners.map((earner, idx) => (
                  <div
                    key={earner.user_id}
                    className="flex items-center justify-between border-b border-divider pb-2 last:border-0 last:pb-0"
                  >
                    <div className="flex items-center gap-3">
                      <span className="flex h-6 w-6 items-center justify-center rounded-full bg-success/10 text-xs font-bold text-success">
                        {idx + 1}
                      </span>
                      <Link
                        to={tenantPath(`/admin/users/${earner.user_id}/edit`)}
                        className="text-sm font-medium hover:text-primary transition-colors"
                      >
                        {earner.user_name}
                      </Link>
                    </div>
                    <span className="text-sm font-semibold text-success">
                      {earner.amount}h
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-default-400">
                No transaction data available
              </p>
            )}
          </CardBody>
        </Card>

        {/* Top Spenders */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Wallet size={18} className="text-warning" />
            <h3 className="font-semibold">Top Spenders</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            {loading ? (
              <div className="flex h-40 items-center justify-center">
                <Spinner />
              </div>
            ) : stats?.top_spenders && stats.top_spenders.length > 0 ? (
              <div className="space-y-3">
                {stats.top_spenders.map((spender, idx) => (
                  <div
                    key={spender.user_id}
                    className="flex items-center justify-between border-b border-divider pb-2 last:border-0 last:pb-0"
                  >
                    <div className="flex items-center gap-3">
                      <span className="flex h-6 w-6 items-center justify-center rounded-full bg-warning/10 text-xs font-bold text-warning">
                        {idx + 1}
                      </span>
                      <Link
                        to={tenantPath(`/admin/users/${spender.user_id}/edit`)}
                        className="text-sm font-medium hover:text-primary transition-colors"
                      >
                        {spender.user_name}
                      </Link>
                    </div>
                    <span className="text-sm font-semibold text-warning">
                      {spender.amount}h
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="py-8 text-center text-sm text-default-400">
                No transaction data available
              </p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Quick Links */}
      <Card shadow="sm">
        <CardHeader className="px-4 pt-4 pb-0">
          <h3 className="font-semibold">Quick Links</h3>
        </CardHeader>
        <CardBody className="px-4 pb-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <Button
              as={Link}
              to={tenantPath('/admin/timebanking/alerts')}
              variant="flat"
              color="danger"
              className="justify-between h-auto py-3"
              endContent={<ChevronRight size={16} />}
            >
              <div className="flex items-center gap-2">
                <AlertTriangle size={18} />
                <span>Fraud Alerts</span>
              </div>
            </Button>

            <Button
              as={Link}
              to={tenantPath('/admin/timebanking/user-report')}
              variant="flat"
              color="primary"
              className="justify-between h-auto py-3"
              endContent={<ChevronRight size={16} />}
            >
              <div className="flex items-center gap-2">
                <Users size={18} />
                <span>User Report</span>
              </div>
            </Button>

            <Button
              as={Link}
              to={tenantPath('/admin/timebanking/org-wallets')}
              variant="flat"
              color="secondary"
              className="justify-between h-auto py-3"
              endContent={<ChevronRight size={16} />}
            >
              <div className="flex items-center gap-2">
                <Building2 size={18} />
                <span>Organization Wallets</span>
              </div>
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default TimebankingDashboard;
