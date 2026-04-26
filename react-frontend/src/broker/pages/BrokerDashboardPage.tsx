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
import { useTranslation } from 'react-i18next';
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
import { BrokerControlsHelp } from './BrokerHelpPage';

// Quick-link metadata. Title + description are translated at render time
// using the broker.dashboard.links.* namespace so the dashboard respects
// the user's preferred language. Path/icon/color are presentation-only
// and stay defined here.
const QUICK_LINKS = [
  { key: 'exchanges',     icon: ArrowLeftRight,        color: 'primary'   as const, path: '/broker/exchanges' },
  { key: 'risk_tags',     icon: ShieldAlert,           color: 'danger'    as const, path: '/broker/risk-tags' },
  { key: 'messages',      icon: MessageSquareWarning,  color: 'warning'   as const, path: '/broker/messages' },
  { key: 'monitoring',    icon: Eye,                   color: 'secondary' as const, path: '/broker/monitoring' },
  { key: 'vetting',       icon: ShieldCheck,           color: 'success'   as const, path: '/broker/vetting' },
  { key: 'configuration', icon: Settings,              color: 'default'   as const, path: '/broker/configuration' },
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
  const { t } = useTranslation('broker');
  usePageTitle(t('dashboard.title'));
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
      toast.error(t('dashboard.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [toast, t])


  useEffect(() => {
    loadDashboard();
  }, [loadDashboard]);

  return (
    <div>
      <PageHeader
        title={t('dashboard.title')}
        description={t('dashboard.description')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadDashboard}
            isLoading={loading}
            size="sm"
          >
            {t('dashboard.refresh')}
          </Button>
        }
      />

      {/* Stats Grid — each tile deep-links into the relevant management page
          with the filter already applied, so admins triage in one click. */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={t('dashboard.pending_exchanges')}
          value={stats?.pending_exchanges ?? '—'}
          icon={ArrowLeftRight}
          color="primary"
          loading={loading}
          to={tenantPath('/broker/exchanges?status=pending_broker')}
        />
        <StatCard
          label={t('dashboard.unreviewed_messages')}
          value={stats?.unreviewed_messages ?? '—'}
          icon={MessageSquareWarning}
          color="warning"
          loading={loading}
          to={tenantPath('/broker/messages?status=unreviewed')}
        />
        <StatCard
          label={t('dashboard.high_risk_listings')}
          value={stats?.high_risk_listings ?? '—'}
          icon={ShieldAlert}
          color="danger"
          loading={loading}
          to={tenantPath('/broker/risk-tags?level=high')}
        />
        <StatCard
          label={t('dashboard.monitored_users')}
          value={stats?.monitored_users ?? '—'}
          icon={Eye}
          color="secondary"
          loading={loading}
          to={tenantPath('/broker/monitoring')}
        />
        <StatCard
          label={t('dashboard.vetting_pending')}
          value={stats?.vetting_pending ?? '—'}
          icon={ShieldCheck}
          color="success"
          loading={loading}
          to={tenantPath('/broker/vetting?status=pending')}
        />
        <StatCard
          label={t('dashboard.vetting_expiring')}
          value={stats?.vetting_expiring ?? '—'}
          icon={Clock}
          color="warning"
          loading={loading}
          to={tenantPath('/broker/vetting?status=expiring_soon')}
        />
        <StatCard
          label={t('dashboard.safeguarding_alerts')}
          value={stats?.safeguarding_alerts ?? '—'}
          icon={AlertTriangle}
          color="danger"
          loading={loading}
          to={tenantPath('/broker/safeguarding?filter=critical')}
        />
        <StatCard
          label={t('dashboard.safeguarding_flags')}
          value={stats?.onboarding_safeguarding_flags ?? '—'}
          icon={ShieldAlert}
          color="warning"
          loading={loading}
          to={tenantPath('/broker/safeguarding?tab=preferences')}
        />
      </div>

      {/* Quick Links */}
      <h2 className="text-lg font-semibold text-foreground mb-4">{t('dashboard.quick_access')}</h2>
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
                  <p className="font-semibold text-foreground">{t(`dashboard.links.${link.key}_title`)}</p>
                  <p className="text-sm text-default-500">{t(`dashboard.links.${link.key}_desc`)}</p>
                </div>
                <ChevronRight size={20} className="text-default-400 shrink-0" />
              </CardBody>
            </Card>
          );
        })}
      </div>

      {/* Recent Activity */}
      <h2 className="text-lg font-semibold text-foreground mb-4 mt-8">{t('dashboard.recent_activity')}</h2>
      {loading && !stats ? (
        <div className="flex items-center justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : stats?.recent_activity && stats.recent_activity.length > 0 ? (
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 pb-0">
            <Activity size={18} className="text-default-500" />
            <span className="text-sm font-semibold text-foreground">{t('dashboard.broker_actions_heading')}</span>
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
                      {' '}{formatActionLabel(entry.action_type, t)}
                    </p>
                    {entry.details && (
                      <p className="text-xs text-default-400 truncate">{entry.details}</p>
                    )}
                  </div>
                  <span className="shrink-0 text-xs text-default-400">
                    {formatTimeAgo(entry.created_at, t)}
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
            <p className="text-default-500 font-medium">{t('dashboard.no_recent_activity')}</p>
            <p className="text-sm text-default-400 mt-1">
              {t('dashboard.no_recent_activity_hint')}
            </p>
          </CardBody>
        </Card>
      )}

      {/* Collapsible guidance panel — title visible, body tucked into accordion sections */}
      <BrokerControlsHelp />
    </div>
  );
}

type ChipColor = 'success' | 'danger' | 'primary' | 'warning' | 'secondary' | 'default';

// Action keys MUST match the action strings emitted by the backend dashboard
// query in AdminBrokerController::dashboard (UNION of activity_log +
// org_audit_log). Any mismatch causes the row to render with a default-grey
// chip and a snake_case label like "vetting record verified".
const actionChipColorMap: Record<string, ChipColor> = {
  // org_audit_log entries (broker controller writes via AuditLogService::log)
  exchange_approved: 'success',
  exchange_rejected: 'danger',
  broker_message_reviewed: 'primary',
  broker_message_approved: 'success',
  broker_message_flagged: 'warning',
  listing_risk_tag_created: 'warning',
  listing_risk_tag_updated: 'warning',
  listing_risk_tag_removed: 'default',
  user_monitoring_added: 'secondary',
  user_monitoring_removed: 'default',
  broker_config_updated: 'primary',
  // activity_log entries (vetting/insurance controllers write via ActivityLog::log)
  vetting_record_verified: 'success',
  vetting_record_rejected: 'danger',
  vetting_record_created: 'primary',
  vetting_record_updated: 'primary',
  vetting_record_deleted: 'default',
  vetting_document_uploaded: 'primary',
  vetting_bulk_verify: 'success',
  vetting_bulk_reject: 'danger',
  vetting_bulk_delete: 'default',
  insurance_cert_created: 'primary',
  insurance_cert_updated: 'primary',
  insurance_cert_verified: 'success',
  insurance_cert_rejected: 'danger',
  insurance_cert_deleted: 'default',
};

// Action keys → broker.json sub-key suffix. The full path is
// `dashboard.activity.chip_${suffix}` and `dashboard.activity.verb_${suffix}`.
// Mapping is needed because the backend emits keys like
// 'broker_message_reviewed' but i18n keys are kept short ('message_reviewed').
const actionI18nKeySuffix: Record<string, string> = {
  exchange_approved: 'exchange_approved',
  exchange_rejected: 'exchange_rejected',
  broker_message_reviewed: 'message_reviewed',
  broker_message_approved: 'message_approved',
  broker_message_flagged: 'message_flagged',
  listing_risk_tag_created: 'risk_tag_created',
  listing_risk_tag_updated: 'risk_tag_updated',
  listing_risk_tag_removed: 'risk_tag_removed',
  user_monitoring_added: 'monitoring_added',
  user_monitoring_removed: 'monitoring_removed',
  broker_config_updated: 'config_updated',
  vetting_record_verified: 'vetting_verified',
  vetting_record_rejected: 'vetting_rejected',
  vetting_record_created: 'vetting_created',
  vetting_record_updated: 'vetting_updated',
  vetting_record_deleted: 'vetting_deleted',
  vetting_document_uploaded: 'vetting_document',
  vetting_bulk_verify: 'vetting_bulk_verify',
  vetting_bulk_reject: 'vetting_bulk_reject',
  vetting_bulk_delete: 'vetting_bulk_delete',
  insurance_cert_created: 'insurance_created',
  insurance_cert_updated: 'insurance_updated',
  insurance_cert_verified: 'insurance_verified',
  insurance_cert_rejected: 'insurance_rejected',
  insurance_cert_deleted: 'insurance_deleted',
};

function ActivityChip({ actionType }: { actionType: string }) {
  const { t } = useTranslation('broker');
  const color: ChipColor = actionChipColorMap[actionType] ?? 'default';
  const suffix = actionI18nKeySuffix[actionType];
  const label = suffix
    ? t(`dashboard.activity.chip_${suffix}`)
    : actionType.replace(/_/g, ' ');
  return (
    <Chip size="sm" variant="flat" color={color} className="shrink-0">
      {label}
    </Chip>
  );
}

type TFunc = (key: string, options?: Record<string, unknown>) => string;

function formatActionLabel(actionType: string, t: TFunc): string {
  const suffix = actionI18nKeySuffix[actionType];
  return suffix
    ? t(`dashboard.activity.verb_${suffix}`)
    : actionType.replace(/_/g, ' ');
}

function formatTimeAgo(dateStr: string, t: TFunc): string {
  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return t('dashboard.time_just_now');
  if (diffMins < 60) return t('dashboard.time_minutes_ago', { count: diffMins });
  const diffHrs = Math.floor(diffMins / 60);
  if (diffHrs < 24) return t('dashboard.time_hours_ago', { count: diffHrs });
  const diffDays = Math.floor(diffHrs / 24);
  if (diffDays < 7) return t('dashboard.time_days_ago', { count: diffDays });
  return date.toLocaleDateString();
}

export default BrokerDashboard;
