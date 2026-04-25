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
import ArrowLeftRight from 'lucide-react/icons/arrow-left-right';
import MessageSquareWarning from 'lucide-react/icons/message-square-warning';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Eye from 'lucide-react/icons/eye';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ChevronRight from 'lucide-react/icons/chevron-right';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Clock from 'lucide-react/icons/clock';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Activity from 'lucide-react/icons/activity';
import Settings from 'lucide-react/icons/settings';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker } from '@/admin/api/adminApi';
import { StatCard, PageHeader } from '@/admin/components';
import type { BrokerDashboardStats, BrokerActivityEntry } from '@/admin/api/types';
import BrokerControlsHelp from './BrokerHelpPage';

const QUICK_LINKS = [
  {
    title: 'Exchange Management',
    description: 'Review and approve exchange requests flagged for broker attention.',
    icon: ArrowLeftRight,
    color: 'primary' as const,
    path: '/broker/exchanges',
  },
  {
    title: 'Risk Tags',
    description: 'Manage risk classifications on listings.',
    icon: ShieldAlert,
    color: 'danger' as const,
    path: '/broker/risk-tags',
  },
  {
    title: 'Message Review',
    description: 'Review broker copies of flagged conversations.',
    icon: MessageSquareWarning,
    color: 'warning' as const,
    path: '/broker/messages',
  },
  {
    title: 'User Monitoring',
    description: 'Track members under broker oversight.',
    icon: Eye,
    color: 'secondary' as const,
    path: '/broker/monitoring',
  },
  {
    title: 'Vetting Records',
    description: 'Manage DBS / Garda vetting records.',
    icon: ShieldCheck,
    color: 'success' as const,
    path: '/broker/vetting',
  },
  {
    title: 'Configuration',
    description: 'Configure broker control settings.',
    icon: Settings,
    color: 'default' as const,
    path: '/broker/configuration',
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
  usePageTitle("Broker Dashboard");
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
      toast.error("Failed to load broker dashboard");
    } finally {
      setLoading(false);
    }
  }, [toast])


  useEffect(() => {
    loadDashboard();
  }, [loadDashboard]);

  return (
    <div>
      <PageHeader
        title={"Broker Dashboard"}
        description={"Overview of pending exchanges, insurance, vetting, and monitored users"}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadDashboard}
            isLoading={loading}
            size="sm"
          >
            {"Refresh"}
          </Button>
        }
      />

      {/* Stats Grid — each tile deep-links into the relevant management page
          with the filter already applied, so admins triage in one click. */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={"Pending Exchanges"}
          value={stats?.pending_exchanges ?? '—'}
          icon={ArrowLeftRight}
          color="primary"
          loading={loading}
          to={tenantPath('/broker/exchanges?status=pending_broker')}
        />
        <StatCard
          label={"Unreviewed Messages"}
          value={stats?.unreviewed_messages ?? '—'}
          icon={MessageSquareWarning}
          color="warning"
          loading={loading}
          to={tenantPath('/broker/messages?status=unreviewed')}
        />
        <StatCard
          label={"High Risk Listings"}
          value={stats?.high_risk_listings ?? '—'}
          icon={ShieldAlert}
          color="danger"
          loading={loading}
          to={tenantPath('/broker/risk-tags?level=high')}
        />
        <StatCard
          label={"Monitored Users"}
          value={stats?.monitored_users ?? '—'}
          icon={Eye}
          color="secondary"
          loading={loading}
          to={tenantPath('/broker/monitoring')}
        />
        <StatCard
          label={"Vetting Pending"}
          value={stats?.vetting_pending ?? '—'}
          icon={ShieldCheck}
          color="success"
          loading={loading}
          to={tenantPath('/broker/vetting?status=pending')}
        />
        <StatCard
          label={"Expiring Soon"}
          value={stats?.vetting_expiring ?? '—'}
          icon={Clock}
          color="warning"
          loading={loading}
          to={tenantPath('/broker/vetting?status=expiring_soon')}
        />
        <StatCard
          label={"Safeguarding Alerts"}
          value={stats?.safeguarding_alerts ?? '—'}
          icon={AlertTriangle}
          color="danger"
          loading={loading}
          to={tenantPath('/broker/safeguarding?filter=critical')}
        />
        <StatCard
          label={"Onboarding Flags"}
          value={stats?.onboarding_safeguarding_flags ?? '—'}
          icon={ShieldAlert}
          color="warning"
          loading={loading}
          to={tenantPath('/broker/safeguarding?tab=preferences')}
        />
      </div>

      {/* Quick Links */}
      <h2 className="text-lg font-semibold text-foreground mb-4">{"Quick Access"}</h2>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {QUICK_LINKS.map((link) => {
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
      <h2 className="text-lg font-semibold text-foreground mb-4 mt-8">{"Recent Activity"}</h2>
      {loading && !stats ? (
        <div className="flex items-center justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : stats?.recent_activity && stats.recent_activity.length > 0 ? (
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 pb-0">
            <Activity size={18} className="text-default-500" />
            <span className="text-sm font-semibold text-foreground">{"Broker Actions"}</span>
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
            <p className="text-default-500 font-medium">{"No recent broker activity found"}</p>
            <p className="text-sm text-default-400 mt-1">{"Activity Empty."}</p>
          </CardBody>
        </Card>
      )}

      {/* Collapsible guidance panel — title visible, body tucked into accordion sections */}
      <BrokerControlsHelp />
    </div>
  );
}

type ChipColor = 'success' | 'danger' | 'primary' | 'warning' | 'secondary' | 'default';

const actionChipColorMap: Record<string, ChipColor> = {
  exchange_approved: 'success',
  exchange_rejected: 'danger',
  message_reviewed: 'primary',
  risk_tag_added: 'warning',
  user_monitored: 'secondary',
  vetting_verified: 'success',
  vetting_rejected: 'danger',
  user_banned: 'danger',
  user_unbanned: 'success',
  balance_adjusted: 'primary',
};

const actionChipLabel: Record<string, string> = {
  exchange_approved: 'Approved',
  exchange_rejected: 'Rejected',
  message_reviewed: 'Reviewed',
  risk_tag_added: 'Risk Tagged',
  user_monitored: 'Monitored',
  vetting_verified: 'Verified',
  vetting_rejected: 'Vetting Rejected',
  user_banned: 'Banned',
  user_unbanned: 'Unbanned',
  balance_adjusted: 'Balance',
};

const actionVerbLabel: Record<string, string> = {
  exchange_approved: 'approved an exchange',
  exchange_rejected: 'rejected an exchange',
  message_reviewed: 'reviewed a message',
  risk_tag_added: 'tagged a listing',
  user_monitored: 'placed a user under monitoring',
  vetting_verified: 'verified a vetting record',
  vetting_rejected: 'rejected a vetting record',
  user_banned: 'banned a user',
  user_unbanned: 'unbanned a user',
  balance_adjusted: 'adjusted a balance',
};

function ActivityChip({ actionType }: { actionType: string }) {
  const color: ChipColor = actionChipColorMap[actionType] ?? 'default';
  const label = actionChipLabel[actionType] ?? actionType.replace(/_/g, ' ');
  return (
    <Chip size="sm" variant="flat" color={color} className="shrink-0">
      {label}
    </Chip>
  );
}

function formatActionLabel(actionType: string): string {
  return actionVerbLabel[actionType] ?? actionType.replace(/_/g, ' ');
}

function formatTimeAgo(dateStr: string): string {
  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHrs = Math.floor(diffMins / 60);
  if (diffHrs < 24) return `${diffHrs}h ago`;
  const diffDays = Math.floor(diffHrs / 24);
  if (diffDays < 7) return `${diffDays}d ago`;
  return date.toLocaleDateString();
}

export default BrokerDashboard;
