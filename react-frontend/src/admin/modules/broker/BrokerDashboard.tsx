/**
 * Broker Controls Dashboard
 * Overview with key metrics and quick links to sub-pages.
 * Parity: PHP BrokerControlsController::dashboard()
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, Button, Spinner } from '@heroui/react';
import {
  ArrowLeftRight,
  MessageSquareWarning,
  ShieldAlert,
  Eye,
  RefreshCw,
  ChevronRight,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { BrokerDashboardStats } from '../../api/types';

const quickLinks = [
  {
    title: 'Exchange Management',
    description: 'Review and manage exchange requests between members',
    icon: ArrowLeftRight,
    color: 'primary' as const,
    path: '/admin/broker-controls/exchanges',
  },
  {
    title: 'Risk Tags',
    description: 'View listings flagged with risk tags',
    icon: ShieldAlert,
    color: 'danger' as const,
    path: '/admin/broker-controls/risk-tags',
  },
  {
    title: 'Message Review',
    description: 'Review broker message copies and flagged conversations',
    icon: MessageSquareWarning,
    color: 'warning' as const,
    path: '/admin/broker-controls/messages',
  },
  {
    title: 'User Monitoring',
    description: 'View users under messaging monitoring',
    icon: Eye,
    color: 'secondary' as const,
    path: '/admin/broker-controls/monitoring',
  },
];

export function BrokerDashboard() {
  usePageTitle('Admin - Broker Controls');
  const { tenantPath } = useTenant();

  const [stats, setStats] = useState<BrokerDashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  const loadDashboard = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getDashboard();
      if (res.success && res.data) {
        setStats(res.data);
      }
    } catch {
      // Silently handle — stats will show as loading
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadDashboard();
  }, [loadDashboard]);

  return (
    <div>
      <PageHeader
        title="Broker Controls"
        description="Exchange management and monitoring"
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

      {/* Stats Grid */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Pending Exchanges"
          value={stats?.pending_exchanges ?? '—'}
          icon={ArrowLeftRight}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Unreviewed Messages"
          value={stats?.unreviewed_messages ?? '—'}
          icon={MessageSquareWarning}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="High Risk Listings"
          value={stats?.high_risk_listings ?? '—'}
          icon={ShieldAlert}
          color="danger"
          loading={loading}
        />
        <StatCard
          label="Monitored Users"
          value={stats?.monitored_users ?? '—'}
          icon={Eye}
          color="secondary"
          loading={loading}
        />
      </div>

      {/* Quick Links */}
      <h2 className="text-lg font-semibold text-foreground mb-4">Quick Access</h2>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {quickLinks.map((link) => {
          const Icon = link.icon;
          return (
            <Card key={link.path} shadow="sm" isPressable as={Link} to={tenantPath(link.path)}>
              <CardBody className="flex flex-row items-center gap-4 p-4">
                <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-${link.color}/10`}>
                  <Icon size={24} className={`text-${link.color}`} />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="font-semibold text-foreground">{link.title}</p>
                  <p className="text-sm text-default-500">{link.description}</p>
                </div>
                <ChevronRight size={20} className="text-default-400 shrink-0" />
              </CardBody>
            </Card>
          );
        })}
      </div>

      {loading && !stats && (
        <div className="flex items-center justify-center py-12">
          <Spinner size="lg" />
        </div>
      )}
    </div>
  );
}

export default BrokerDashboard;
