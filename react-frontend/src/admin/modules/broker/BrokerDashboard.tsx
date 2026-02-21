// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Controls Dashboard
 * Overview with key metrics and quick links to sub-pages.
 * Parity: PHP BrokerControlsController::dashboard()
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Button, Spinner, Chip, Divider } from '@heroui/react';
import {
  ArrowLeftRight,
  MessageSquareWarning,
  ShieldAlert,
  Eye,
  RefreshCw,
  ChevronRight,
  ShieldCheck,
  Clock,
  AlertTriangle,
  Activity,
  Settings,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { BrokerDashboardStats, BrokerActivityEntry } from '../../api/types';

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
  {
    title: 'Vetting Records',
    description: 'Manage DBS checks, insurance, and member vetting',
    icon: ShieldCheck,
    color: 'success' as const,
    path: '/admin/broker-controls/vetting',
  },
  {
    title: 'Configuration',
    description: 'Configure broker controls, messaging oversight, and risk settings',
    icon: Settings,
    color: 'default' as const,
    path: '/admin/broker-controls/configuration',
  },
];

// Tailwind JIT needs full class names at build time — dynamic `bg-${color}/10` won't work
const quickLinkBgClass: Record<string, string> = {
  primary: 'bg-primary/10',
  danger: 'bg-danger/10',
  warning: 'bg-warning/10',
  secondary: 'bg-secondary/10',
  success: 'bg-success/10',
  default: 'bg-default/10',
};
const quickLinkTextClass: Record<string, string> = {
  primary: 'text-primary',
  danger: 'text-danger',
  warning: 'text-warning',
  secondary: 'text-secondary',
  success: 'text-success',
  default: 'text-default-500',
};

export function BrokerDashboard() {
  usePageTitle('Admin - Broker Controls');
  const { tenantPath } = useTenant();
  const toast = useToast();

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
      toast.error('Failed to load broker dashboard');
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
        <StatCard
          label="Vetting Pending"
          value={stats?.vetting_pending ?? '—'}
          icon={ShieldCheck}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Expiring Soon"
          value={stats?.vetting_expiring ?? '—'}
          icon={Clock}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="Safeguarding Alerts"
          value={stats?.safeguarding_alerts ?? '—'}
          icon={AlertTriangle}
          color="danger"
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
                <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${quickLinkBgClass[link.color]}`}>
                  <Icon size={24} className={quickLinkTextClass[link.color]} />
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

      {/* Recent Activity */}
      <h2 className="text-lg font-semibold text-foreground mb-4 mt-8">Recent Activity</h2>
      {loading && !stats ? (
        <div className="flex items-center justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : stats?.recent_activity && stats.recent_activity.length > 0 ? (
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 pb-0">
            <Activity size={18} className="text-default-500" />
            <span className="text-sm font-semibold text-foreground">Broker Actions</span>
          </CardHeader>
          <Divider className="my-2" />
          <CardBody className="p-0">
            <ul className="divide-y divide-divider">
              {stats.recent_activity.map((entry: BrokerActivityEntry) => (
                <li key={entry.id} className="flex items-center gap-3 px-4 py-3">
                  <ActivityChip actionType={entry.action_type} />
                  <div className="min-w-0 flex-1">
                    <p className="text-sm text-foreground">
                      <span className="font-medium">{entry.first_name} {entry.last_name}</span>
                      {' '}{formatActionLabel(entry.action_type)}
                    </p>
                    {entry.details && (
                      <p className="text-xs text-default-400 truncate">{entry.details}</p>
                    )}
                  </div>
                  <span className="shrink-0 text-xs text-default-400">
                    {formatTimeAgo(entry.created_at)}
                  </span>
                </li>
              ))}
            </ul>
          </CardBody>
        </Card>
      ) : (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center justify-center py-10 text-center">
            <Activity size={40} className="text-default-300 mb-3" />
            <p className="text-default-500 font-medium">No recent broker activity</p>
            <p className="text-sm text-default-400 mt-1">Actions will appear here as brokers review exchanges and messages</p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}

function ActivityChip({ actionType }: { actionType: string }) {
  const config = actionChipConfig[actionType] || { label: actionType, color: 'default' as const };
  return (
    <Chip size="sm" variant="flat" color={config.color} className="shrink-0">
      {config.label}
    </Chip>
  );
}

const actionChipConfig: Record<string, { label: string; color: 'success' | 'danger' | 'primary' | 'warning' | 'secondary' | 'default' }> = {
  exchange_approved: { label: 'Approved', color: 'success' },
  exchange_rejected: { label: 'Rejected', color: 'danger' },
  message_reviewed: { label: 'Reviewed', color: 'primary' },
  risk_tag_added: { label: 'Risk Tag', color: 'warning' },
  user_monitored: { label: 'Monitored', color: 'secondary' },
  vetting_verified: { label: 'Verified', color: 'success' },
  vetting_rejected: { label: 'Vet Rejected', color: 'danger' },
  user_banned: { label: 'Banned', color: 'danger' },
  user_unbanned: { label: 'Unbanned', color: 'success' },
  balance_adjusted: { label: 'Balance', color: 'primary' },
};

function formatActionLabel(actionType: string): string {
  const labels: Record<string, string> = {
    exchange_approved: 'approved an exchange',
    exchange_rejected: 'rejected an exchange',
    message_reviewed: 'reviewed a message',
    risk_tag_added: 'added a risk tag',
    user_monitored: 'placed a user under monitoring',
    vetting_verified: 'verified a vetting record',
    vetting_rejected: 'rejected a vetting record',
    user_banned: 'banned a user',
    user_unbanned: 'unbanned a user',
    balance_adjusted: 'adjusted a balance',
  };
  return labels[actionType] || actionType.replace(/_/g, ' ');
}

function formatTimeAgo(dateStr: string): string {
  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHrs = Math.floor(diffMins / 60);
  if (diffHrs < 24) return `${diffHrs}h ago`;
  const diffDays = Math.floor(diffHrs / 24);
  if (diffDays < 7) return `${diffDays}d ago`;
  return date.toLocaleDateString();
}

export default BrokerDashboard;
