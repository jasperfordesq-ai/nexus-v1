// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Dashboard Page
 * Overview of daily community management tasks with stat cards,
 * quick-action links, and a recent activity feed.
 */

import { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Card, CardBody, CardHeader, Spinner } from '@heroui/react';
import Users from 'lucide-react/icons/users';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import MessageSquareWarning from 'lucide-react/icons/message-square-warning';
import ArrowLeftRight from 'lucide-react/icons/arrow-left-right';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Eye from 'lucide-react/icons/eye';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import UserPlus from 'lucide-react/icons/user-plus';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminBroker, adminUsers } from '@/admin/api/adminApi';
import type { BrokerDashboardStats, BrokerActivityEntry } from '@/admin/api/types';
import { StatCard, PageHeader } from '@/admin/components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface QuickAction {
  label: string;
  description: string;
  path: string;
  icon: typeof Users;
  color: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function BrokerDashboardPage() {
  const { t } = useTranslation('broker');
  const { tenantPath } = useTenant();
  const navigate = useNavigate();
  usePageTitle(t('dashboard.title'));

  const [stats, setStats] = useState<BrokerDashboardStats | null>(null);
  const [pendingCount, setPendingCount] = useState<number>(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [dashRes, usersRes] = await Promise.all([
        adminBroker.getDashboard(),
        adminUsers.list({ status: 'pending', limit: 1 }),
      ]);

      if (dashRes.success && dashRes.data) {
        setStats(dashRes.data as BrokerDashboardStats);
      }

      if (usersRes.success && usersRes.data) {
        const payload = usersRes.data as unknown;
        if (Array.isArray(payload)) {
          // Flat array — count is length (capped at per_page=1, so no reliable total)
          setPendingCount(payload.length);
        } else if (payload && typeof payload === 'object') {
          const paged = payload as { data: unknown[]; meta?: { total: number } };
          setPendingCount(paged.meta?.total ?? paged.data?.length ?? 0);
        }
      }
    } catch {
      setError(t('common.error'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Quick action cards
  const quickActions: QuickAction[] = [
    {
      label: t('nav.members'),
      description: t('members.description'),
      path: '/broker/members',
      icon: Users,
      color: 'bg-primary/10 text-primary',
    },
    {
      label: t('nav.safeguarding'),
      description: t('safeguarding.description'),
      path: '/broker/safeguarding',
      icon: ShieldAlert,
      color: 'bg-danger/10 text-danger',
    },
    {
      label: t('nav.onboarding'),
      description: t('onboarding.description'),
      path: '/broker/onboarding',
      icon: UserPlus,
      color: 'bg-success/10 text-success',
    },
    {
      label: t('nav.exchanges'),
      description: t('exchanges.description'),
      path: '/broker/exchanges',
      icon: ArrowLeftRight,
      color: 'bg-warning/10 text-warning',
    },
  ];

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Spinner size="lg" label={t('common.loading')} />
      </div>
    );
  }

  if (error || !stats) {
    return (
      <div className="max-w-7xl mx-auto space-y-6">
        <PageHeader
          title={t('dashboard.title')}
          description={t('dashboard.description')}
        />
        <Card>
          <CardBody>
            <p className="text-danger">{error || t('common.error')}</p>
          </CardBody>
        </Card>
      </div>
    );
  }

  const recentActivity: BrokerActivityEntry[] = stats.recent_activity ?? [];

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <PageHeader
        title={t('dashboard.title')}
        description={t('dashboard.description')}
      />

      {/* Row 1: 4 stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label={t('dashboard.pending_members')}
          value={pendingCount}
          icon={Users}
          color="warning"
          loading={false}
        />
        <StatCard
          label={t('dashboard.safeguarding_alerts')}
          value={stats.safeguarding_alerts}
          icon={ShieldAlert}
          color="danger"
          loading={false}
        />
        <StatCard
          label={t('dashboard.unreviewed_messages')}
          value={stats.unreviewed_messages}
          icon={MessageSquareWarning}
          color="primary"
          loading={false}
        />
        <StatCard
          label={t('dashboard.pending_exchanges')}
          value={stats.pending_exchanges}
          icon={ArrowLeftRight}
          color="secondary"
          loading={false}
        />
      </div>

      {/* Row 2: 4 stat cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label={t('dashboard.vetting_expiring')}
          value={stats.vetting_expiring}
          icon={ShieldCheck}
          color="warning"
          loading={false}
        />
        <StatCard
          label={t('dashboard.monitored_users')}
          value={stats.monitored_users}
          icon={Eye}
          color="default"
          loading={false}
        />
        <StatCard
          label={t('dashboard.high_risk_listings')}
          value={stats.high_risk_listings}
          icon={AlertTriangle}
          color="danger"
          loading={false}
        />
        <StatCard
          label={t('dashboard.safeguarding_flags')}
          value={stats.onboarding_safeguarding_flags}
          icon={UserPlus}
          color="success"
          loading={false}
        />
      </div>

      {/* Quick Actions + Recent Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Quick Actions */}
        <div className="lg:col-span-1">
          <Card>
            <CardHeader>
              <h3 className="text-lg font-semibold">{t('dashboard.quick_actions')}</h3>
            </CardHeader>
            <CardBody className="gap-3">
              {quickActions.map((action) => {
                const Icon = action.icon;
                return (
                  <Button
                    key={action.path}
                    variant="light"
                    onPress={() => navigate(tenantPath(action.path))}
                    className="flex items-center gap-3 w-full p-3 rounded-lg hover:bg-default-100 transition-colors text-left h-auto min-w-0 justify-start"
                  >
                    <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${action.color}`}>
                      <Icon size={20} />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-foreground">{action.label}</p>
                      <p className="text-xs text-default-400 truncate">{action.description}</p>
                    </div>
                    <ChevronRight size={16} className="text-default-400 shrink-0" />
                  </Button>
                );
              })}
            </CardBody>
          </Card>
        </div>

        {/* Recent Activity */}
        <div className="lg:col-span-2">
          <Card>
            <CardHeader>
              <h3 className="text-lg font-semibold">{t('dashboard.recent_activity')}</h3>
            </CardHeader>
            <CardBody>
              {recentActivity.length === 0 ? (
                <p className="text-sm text-default-400 text-center py-8">
                  {t('dashboard.no_activity')}
                </p>
              ) : (
                <div className="space-y-3">
                  {recentActivity.map((entry) => (
                    <div
                      key={entry.id}
                      className="flex items-start gap-3 p-3 rounded-lg bg-default-50"
                    >
                      <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <Users size={14} className="text-primary" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm text-foreground">
                          <span className="font-medium">
                            {entry.first_name} {entry.last_name}
                          </span>{' '}
                          <span className="text-default-500">
                            {entry.action_type?.replace(/_/g, ' ')}
                          </span>
                        </p>
                        <p className="text-xs text-default-400 mt-0.5">
                          {new Date(entry.created_at).toLocaleString()}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardBody>
          </Card>
        </div>
      </div>
    </div>
  );
}
