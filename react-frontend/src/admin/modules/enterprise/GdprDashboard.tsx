// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Dashboard
 * Overview with compliance score, stats, breach alerts, and links to GDPR sub-pages.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Card, CardBody, Chip, Button } from '@heroui/react';
import {
  FileWarning,
  UserCheck,
  AlertTriangle,
  ClipboardList,
  ArrowRight,
  RefreshCw,
  Plus,
  ShieldAlert,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { GdprDashboardStats, GdprStatistics } from '../../api/types';

import { useTranslation } from 'react-i18next';

function ComplianceScoreRing({ score, size = 120 }: { score: number; size?: number }) {
  const radius = (size - 16) / 2;
  const circumference = 2 * Math.PI * radius;
  const progress = (score / 100) * circumference;
  const color = score >= 80 ? 'text-success' : score >= 50 ? 'text-warning' : 'text-danger';
  const strokeColor = score >= 80 ? '#17c964' : score >= 50 ? '#f5a524' : '#f31260';

  return (
    <div className="relative inline-flex items-center justify-center" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          stroke="currentColor"
          strokeWidth={8}
          className="text-default-200"
        />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          stroke={strokeColor}
          strokeWidth={8}
          strokeDasharray={circumference}
          strokeDashoffset={circumference - progress}
          strokeLinecap="round"
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <span className={`text-2xl font-bold ${color}`}>{score}%</span>
        <span className="text-xs text-default-500">Score</span>
      </div>
    </div>
  );
}

export function GdprDashboard() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const { tenantPath } = useTenant();
  const navigate = useNavigate();

  const [stats, setStats] = useState<GdprDashboardStats | null>(null);
  const [statistics, setStatistics] = useState<GdprStatistics | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [dashRes, statsRes] = await Promise.all([
        adminEnterprise.getGdprDashboard(),
        adminEnterprise.getGdprStatistics(),
      ]);
      if (dashRes.success && dashRes.data) {
        setStats(dashRes.data as unknown as GdprDashboardStats);
      }
      if (statsRes.success && statsRes.data) {
        setStatistics(statsRes.data as unknown as GdprStatistics);
      }
    } catch (err) {
      console.error('Failed to load GDPR dashboard data', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const activeBreaches = statistics?.active_breaches ?? 0;
  const overdueCount = statistics?.overdue_count ?? 0;
  const complianceScore = statistics?.compliance_score ?? 0;
  const consentCoverage = statistics?.consent_coverage_percent ?? 0;

  const links = [
    { label: t('enterprise.link_data_requests'), href: tenantPath('/admin/enterprise/gdpr/requests'), icon: FileWarning, description: t('enterprise.link_data_requests_desc') },
    { label: t('enterprise.link_consent_records'), href: tenantPath('/admin/enterprise/gdpr/consents'), icon: UserCheck, description: t('enterprise.link_consent_records_desc') },
    { label: t('enterprise.link_data_breaches'), href: tenantPath('/admin/enterprise/gdpr/breaches'), icon: AlertTriangle, description: t('enterprise.link_data_breaches_desc') },
    { label: t('enterprise.link_gdpr_audit_log'), href: tenantPath('/admin/enterprise/gdpr/audit'), icon: ClipboardList, description: t('enterprise.link_gdpr_audit_log_desc') },
  ];

  return (
    <div>
      <PageHeader
        title={t('enterprise.gdpr_dashboard_title')}
        description={t('enterprise.gdpr_dashboard_desc')}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              {t('common.refresh')}
            </Button>
          </div>
        }
      />

      {/* Active Breach Alert */}
      {activeBreaches > 0 && (
        <div className="mb-6 p-4 rounded-xl border-2 border-danger bg-danger-50 animate-pulse flex items-center gap-3">
          <ShieldAlert size={24} className="text-danger shrink-0" />
          <div className="flex-1">
            <p className="font-semibold text-danger">
              {activeBreaches} active data breach{activeBreaches > 1 ? 'es' : ''} require{activeBreaches === 1 ? 's' : ''} attention
            </p>
            <p className="text-sm text-danger-600">Review and respond to open breaches immediately.</p>
          </div>
          <Button
            size="sm"
            color="danger"
            variant="flat"
            onPress={() => navigate(tenantPath('/admin/enterprise/gdpr/breaches'))}
          >
            View Breaches
          </Button>
        </div>
      )}

      {/* Compliance Score + Mini Stats */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-5 mb-6">
        {/* Compliance Score */}
        <Card shadow="sm" className="lg:col-span-1">
          <CardBody className="flex flex-col items-center justify-center p-4 gap-2">
            <p className="text-sm font-medium text-default-500">Compliance Score</p>
            <ComplianceScoreRing score={complianceScore} />
          </CardBody>
        </Card>

        {/* Mini Stats Grid */}
        <div className="lg:col-span-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            label={t('enterprise.label_pending_requests')}
            value={stats?.pending_requests ?? 0}
            icon={FileWarning}
            color="warning"
            loading={loading}
          />
          <StatCard
            label="Completed This Month"
            value={statistics?.requests_by_status?.completed ?? 0}
            icon={UserCheck}
            color="success"
            loading={loading}
          />
          <StatCard
            label="Consent Coverage"
            value={`${consentCoverage.toFixed(0)}%`}
            icon={UserCheck}
            color="primary"
            loading={loading}
          />
          <StatCard
            label={t('enterprise.label_data_breaches')}
            value={activeBreaches}
            icon={AlertTriangle}
            color="danger"
            loading={loading}
          />
        </div>
      </div>

      {/* Request Type Breakdown + Overdue Alert */}
      {statistics && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
          {/* Request Type Breakdown */}
          <Card shadow="sm">
            <CardBody className="p-4">
              <p className="text-sm font-semibold text-default-700 mb-3">Requests by Type</p>
              <div className="flex flex-wrap gap-2">
                {Object.entries(statistics.requests_by_type || {}).map(([type, count]) => (
                  <Chip key={type} size="sm" variant="flat" color="primary" className="capitalize">
                    {type}: {count}
                  </Chip>
                ))}
                {Object.keys(statistics.requests_by_type || {}).length === 0 && (
                  <span className="text-sm text-default-400">No requests yet</span>
                )}
              </div>
            </CardBody>
          </Card>

          {/* Overdue Alert + Quick Actions */}
          <Card shadow="sm">
            <CardBody className="p-4 space-y-3">
              {overdueCount > 0 && (
                <div className="p-3 rounded-lg bg-danger-50 border border-danger-200 flex items-center gap-2">
                  <AlertTriangle size={16} className="text-danger shrink-0" />
                  <span className="text-sm text-danger font-medium">
                    {overdueCount} overdue request{overdueCount > 1 ? 's' : ''}
                  </span>
                </div>
              )}
              <p className="text-sm font-semibold text-default-700">Quick Actions</p>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  color="primary"
                  variant="flat"
                  startContent={<Plus size={14} />}
                  as={Link}
                  to={tenantPath('/admin/enterprise/gdpr/requests/create')}
                >
                  New Request
                </Button>
                <Button
                  size="sm"
                  color="danger"
                  variant="flat"
                  startContent={<AlertTriangle size={14} />}
                  as={Link}
                  to={tenantPath('/admin/enterprise/gdpr/breaches')}
                >
                  Report Breach
                </Button>
              </div>
            </CardBody>
          </Card>
        </div>
      )}

      {/* Quick Links */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {links.map((link) => (
          <Card key={link.href} shadow="sm" isPressable as={Link} to={link.href}>
            <CardBody className="flex flex-row items-center gap-4 p-4">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <link.icon size={20} className="text-primary" />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-foreground">{link.label}</p>
                <p className="text-sm text-default-500">{link.description}</p>
              </div>
              <ArrowRight size={16} className="text-default-400 shrink-0" />
            </CardBody>
          </Card>
        ))}
      </div>
    </div>
  );
}

export default GdprDashboard;
