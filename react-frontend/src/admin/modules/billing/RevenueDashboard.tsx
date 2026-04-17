// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * RevenueDashboard
 * God-only page: platform-wide billing overview at /admin/super/billing/revenue
 */

import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Chip,
  Spinner,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Button,
  Divider,
} from '@heroui/react';
import { TrendingUp, Users, DollarSign, Building2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant } from '@/contexts';
import { api } from '@/lib/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface RevenueDashboardData {
  active_tenants: number;
  paused_tenants: number;
  free_tenants: number;
  over_limit_tenants: number;
  in_grace_period: number;
  mrr: number;
  arr: number;
  total_platform_users: number;
  plan_breakdown: Array<{
    plan: string;
    count: number;
    mrr_contribution: number;
  }>;
  recent_changes: Array<{
    tenant_name: string;
    action: string;
    created_at: string;
    acted_by: string | null;
  }>;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-IE', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

function actionChipColor(
  action: string
): 'primary' | 'warning' | 'danger' | 'secondary' | 'default' | 'success' {
  switch (action) {
    case 'plan_assigned':
      return 'primary';
    case 'grace_period_set':
      return 'warning';
    case 'plan_paused':
      return 'danger';
    case 'plan_resumed':
      return 'success';
    case 'delegate_granted':
      return 'secondary';
    case 'delegate_revoked':
      return 'danger';
    default:
      return 'default';
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Stat Card
// ─────────────────────────────────────────────────────────────────────────────

interface StatCardProps {
  label: string;
  value: string | number;
  color?: 'primary' | 'success' | 'warning' | 'danger' | 'default';
  icon?: React.ReactNode;
}

function StatCard({ label, value, color = 'default', icon }: StatCardProps) {
  const colorClass: Record<string, string> = {
    primary: 'text-primary',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    default: 'text-foreground',
  };

  return (
    <Card className="p-1">
      <CardBody className="gap-2">
        <div className="flex items-center gap-2 text-default-500 text-sm">
          {icon}
          <span>{label}</span>
        </div>
        <p className={`text-2xl font-bold ${colorClass[color]}`}>{value}</p>
      </CardBody>
    </Card>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function RevenueDashboard() {
  const { t } = useTranslation('admin');
  usePageTitle(t('billing.revenue_title'));
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const navigate = useNavigate();

  const [data, setData] = useState<RevenueDashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Redirect non-god users silently
  useEffect(() => {
    if (user && !user.is_god) {
      navigate(tenantPath('/admin'), { replace: true });
    }
  }, [user, navigate, tenantPath]);

  useEffect(() => {
    if (!user?.is_god) return;

    setLoading(true);
    api
      .get<RevenueDashboardData>('/v2/admin/super/billing/revenue')
      .then((res) => {
        if (res.success && res.data) {
          setData(res.data as unknown as RevenueDashboardData);
        } else {
          setError(t('billing.failed_to_load'));
        }
      })
      .catch(() => setError(t('billing.failed_to_load')))
      .finally(() => setLoading(false));
  }, [user, t]);

  if (!user?.is_god) return null;

  // Compute total MRR for percentage calculation
  const totalMrr = data?.plan_breakdown.reduce((sum, p) => sum + p.mrr_contribution, 0) ?? 0;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('billing.revenue_title')}</h1>
          <p className="text-default-500 text-sm mt-1">{t('billing.revenue_desc')}</p>
        </div>
        <Button
          as={Link}
          to={tenantPath('/admin/super/billing')}
          variant="flat"
          size="sm"
        >
          {t('billing.back_to_billing')}
        </Button>
      </div>

      {loading ? (
        <div className="flex justify-center py-20">
          <Spinner size="lg" />
        </div>
      ) : error ? (
        <Card>
          <CardBody>
            <p className="text-danger text-center py-8">{error}</p>
          </CardBody>
        </Card>
      ) : data ? (
        <>
          {/* ── Row 1: Revenue & users ── */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <StatCard
              label={t('billing.mrr')}
              value={`${formatCurrency(data.mrr)}/mo`}
              color="primary"
              icon={<DollarSign className="w-4 h-4" />}
            />
            <StatCard
              label={t('billing.arr')}
              value={`${formatCurrency(data.arr)}/yr`}
              color="success"
              icon={<TrendingUp className="w-4 h-4" />}
            />
            <StatCard
              label={t('billing.active_tenants')}
              value={data.active_tenants}
              icon={<Building2 className="w-4 h-4" />}
            />
            <StatCard
              label={t('billing.total_users')}
              value={data.total_platform_users.toLocaleString()}
              icon={<Users className="w-4 h-4" />}
            />
          </div>

          {/* ── Row 2: Status indicators ── */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <StatCard
              label={t('billing.free_tenants')}
              value={data.free_tenants}
            />
            <StatCard
              label={t('billing.over_limit_tenants')}
              value={data.over_limit_tenants}
              color={data.over_limit_tenants > 0 ? 'danger' : 'default'}
            />
            <StatCard
              label={t('billing.in_grace')}
              value={data.in_grace_period}
              color={data.in_grace_period > 0 ? 'warning' : 'default'}
            />
            <StatCard
              label={t('billing.paused_tenants')}
              value={data.paused_tenants}
              color={data.paused_tenants > 0 ? 'warning' : 'default'}
            />
          </div>

          {/* ── Plan Breakdown ── */}
          <Card>
            <CardHeader>
              <h2 className="text-lg font-semibold">{t('billing.plan_breakdown')}</h2>
            </CardHeader>
            <Divider />
            <CardBody className="p-0">
              <Table removeWrapper aria-label={t('billing.plan_breakdown')}>
                <TableHeader>
                  <TableColumn>{t('billing.col_current_plan')}</TableColumn>
                  <TableColumn>{t('billing.col_users')}</TableColumn>
                  <TableColumn>{t('billing.mrr_contribution')}</TableColumn>
                  <TableColumn>% MRR</TableColumn>
                </TableHeader>
                <TableBody emptyContent={t('billing.no_plan')}>
                  {data.plan_breakdown.map((row) => {
                    const pct = totalMrr > 0 ? Math.round((row.mrr_contribution / totalMrr) * 100) : 0;
                    return (
                      <TableRow key={row.plan}>
                        <TableCell className="font-medium">{row.plan}</TableCell>
                        <TableCell>{row.count}</TableCell>
                        <TableCell>{formatCurrency(row.mrr_contribution)}</TableCell>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <div className="flex-1 h-2 bg-default-100 rounded-full overflow-hidden">
                              <div
                                className="h-full bg-primary rounded-full"
                                style={{ width: `${pct}%` }}
                              />
                            </div>
                            <span className="text-sm text-default-500 w-9 text-right">{pct}%</span>
                          </div>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            </CardBody>
          </Card>

          {/* ── Recent Changes ── */}
          <Card>
            <CardHeader>
              <h2 className="text-lg font-semibold">{t('billing.recent_changes')}</h2>
            </CardHeader>
            <Divider />
            <CardBody className="p-0">
              <Table removeWrapper aria-label={t('billing.recent_changes')}>
                <TableHeader>
                  <TableColumn>{t('billing.col_tenant')}</TableColumn>
                  <TableColumn>{t('billing.assign_plan')}</TableColumn>
                  <TableColumn>{t('billing.changed_by')}</TableColumn>
                  <TableColumn>{t('billing.expiry_date')}</TableColumn>
                </TableHeader>
                <TableBody emptyContent="-">
                  {data.recent_changes.slice(0, 10).map((change, idx) => (
                    <TableRow key={idx}>
                      <TableCell className="font-medium">{change.tenant_name}</TableCell>
                      <TableCell>
                        <Chip
                          size="sm"
                          variant="flat"
                          color={actionChipColor(change.action)}
                        >
                          {t(`billing.action_${change.action}`, change.action)}
                        </Chip>
                      </TableCell>
                      <TableCell>{change.acted_by ?? '—'}</TableCell>
                      <TableCell>
                        {new Date(change.created_at).toLocaleDateString()}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardBody>
          </Card>
        </>
      ) : null}
    </div>
  );
}

export default RevenueDashboard;
