// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Vetting Page
 * Manage vetting records, DBS checks, and expiry tracking.
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Tabs,
  Tab,
  Chip,
  Button,
  Textarea,
} from '@heroui/react';
import {
  ShieldCheck,
  Clock,
  CheckCircle,
  AlertTriangle,
  FileCheck,
  XCircle,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVetting } from '@/admin/api/adminApi';
import { PageHeader, DataTable, StatCard, ConfirmModal, EmptyState } from '@/admin/components';
import type { Column } from '@/admin/components';
import type { VettingRecord, VettingStats } from '@/admin/api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Format vetting type slugs into human-readable labels */
function formatVettingType(type: string): string {
  const map: Record<string, string> = {
    dbs_basic: 'DBS Basic',
    dbs_standard: 'DBS Standard',
    dbs_enhanced: 'DBS Enhanced',
    garda_vetting: 'Garda Vetting',
    access_ni: 'Access NI',
    pvg_scotland: 'PVG Scotland',
    international: 'International',
    other: 'Other',
  };
  return map[type] || type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

/** Status chip color mapping */
function statusColor(status: string): 'warning' | 'success' | 'danger' | 'default' | 'primary' {
  const map: Record<string, 'warning' | 'success' | 'danger' | 'default' | 'primary'> = {
    pending: 'warning',
    submitted: 'primary',
    verified: 'success',
    expired: 'danger',
    rejected: 'default',
    revoked: 'danger',
  };
  return map[status] || 'default';
}

// Tab key → API params mapping
type TabKey = 'all' | 'pending' | 'verified' | 'expiring';

function tabToParams(tab: TabKey): { status?: string; expiring_soon?: boolean } {
  switch (tab) {
    case 'pending':
      return { status: 'pending' };
    case 'verified':
      return { status: 'verified' };
    case 'expiring':
      return { expiring_soon: true };
    default:
      return {};
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function VettingPage() {
  usePageTitle('Vetting & DBS - Broker');
  const { t } = useTranslation('broker');
  const toast = useToast();

  // ── Stats ────────────────────────────────────────────────────────────────
  const [stats, setStats] = useState<VettingStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // ── Table data ───────────────────────────────────────────────────────────
  const [records, setRecords] = useState<VettingRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [activeTab, setActiveTab] = useState<TabKey>('all');
  const pageSize = 20;

  // ── Verify / Reject modals ───────────────────────────────────────────────
  const [verifyTarget, setVerifyTarget] = useState<VettingRecord | null>(null);
  const [verifyLoading, setVerifyLoading] = useState(false);
  const [rejectTarget, setRejectTarget] = useState<VettingRecord | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejectLoading, setRejectLoading] = useState(false);

  // ── Data fetchers ────────────────────────────────────────────────────────

  const fetchStats = useCallback(async () => {
    setStatsLoading(true);
    try {
      const res = await adminVetting.stats();
      const payload = res.data as unknown;
      if (payload && typeof payload === 'object') {
        setStats(payload as VettingStats);
      }
    } catch {
      // non-critical
    } finally {
      setStatsLoading(false);
    }
  }, []);

  const fetchRecords = useCallback(async (p: number, tab: TabKey) => {
    setLoading(true);
    try {
      const params = { ...tabToParams(tab), page: p, per_page: pageSize };
      const res = await adminVetting.list(params);
      const payload = res.data as unknown;
      if (Array.isArray(payload)) {
        setRecords(payload as VettingRecord[]);
        setTotal(payload.length);
      } else if (payload && typeof payload === 'object') {
        const paged = payload as { data: VettingRecord[]; meta?: { total: number } };
        setRecords(paged.data || []);
        setTotal(paged.meta?.total ?? 0);
      }
    } catch {
      setRecords([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  }, []);

  // ── Effects ──────────────────────────────────────────────────────────────

  useEffect(() => {
    void fetchStats();
  }, [fetchStats]);

  useEffect(() => {
    setPage(1);
    void fetchRecords(1, activeTab);
  }, [activeTab, fetchRecords]);

  // ── Actions ──────────────────────────────────────────────────────────────

  const handleVerify = useCallback(async () => {
    if (!verifyTarget) return;
    setVerifyLoading(true);
    try {
      await adminVetting.verify(verifyTarget.id);
      toast.success(t('vetting.verified_success'));
      setVerifyTarget(null);
      void fetchRecords(page, activeTab);
      void fetchStats();
    } catch {
      toast.error(t('common.error'));
    } finally {
      setVerifyLoading(false);
    }
  }, [verifyTarget, page, activeTab, fetchRecords, fetchStats, toast, t]);

  const handleReject = useCallback(async () => {
    if (!rejectTarget) return;
    setRejectLoading(true);
    try {
      await adminVetting.reject(rejectTarget.id, rejectReason);
      toast.success(t('vetting.rejected_success'));
      setRejectTarget(null);
      setRejectReason('');
      void fetchRecords(page, activeTab);
      void fetchStats();
    } catch {
      toast.error(t('common.error'));
    } finally {
      setRejectLoading(false);
    }
  }, [rejectTarget, rejectReason, page, activeTab, fetchRecords, fetchStats, toast, t]);

  // ── Column definitions ───────────────────────────────────────────────────

  const columns: Column<VettingRecord>[] = useMemo(() => [
    {
      key: 'user_name',
      label: t('vetting.col_member'),
      render: (item) => `${item.first_name} ${item.last_name}`,
    },
    {
      key: 'vetting_type',
      label: t('vetting.col_type'),
      render: (item) => formatVettingType(item.vetting_type),
    },
    {
      key: 'reference_number',
      label: t('vetting.col_reference'),
      render: (item) => item.reference_number || '—',
    },
    {
      key: 'status',
      label: t('vetting.col_status'),
      render: (item) => (
        <Chip size="sm" color={statusColor(item.status)} variant="flat" className="capitalize">
          {t(`status.${item.status}`)}
        </Chip>
      ),
    },
    {
      key: 'expiry_date',
      label: t('vetting.col_expiry'),
      render: (item) =>
        item.expiry_date ? new Date(item.expiry_date).toLocaleDateString() : '—',
    },
    {
      key: 'actions',
      label: t('vetting.col_actions'),
      render: (item) => {
        if (item.status !== 'pending' && item.status !== 'submitted') return null;
        return (
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              color="success"
              variant="flat"
              startContent={<CheckCircle size={14} />}
              onPress={() => setVerifyTarget(item)}
            >
              {t('vetting.verify')}
            </Button>
            <Button
              size="sm"
              color="danger"
              variant="flat"
              startContent={<XCircle size={14} />}
              onPress={() => setRejectTarget(item)}
            >
              {t('vetting.reject')}
            </Button>
          </div>
        );
      },
    },
  ], [t]);

  // ── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('vetting.title')}
        description={t('vetting.description')}
      />

      {/* Stat cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Total"
          value={stats?.total ?? 0}
          icon={FileCheck}
          color="default"
          loading={statsLoading}
        />
        <StatCard
          label="Pending"
          value={stats?.pending ?? 0}
          icon={Clock}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label="Verified"
          value={stats?.verified ?? 0}
          icon={ShieldCheck}
          color="success"
          loading={statsLoading}
        />
        <StatCard
          label="Expiring Soon"
          value={stats?.expiring_soon ?? 0}
          icon={AlertTriangle}
          color="danger"
          loading={statsLoading}
        />
      </div>

      {/* Tab bar */}
      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(String(key) as TabKey)}
        aria-label="Vetting status tabs"
        color="primary"
        variant="underlined"
      >
        <Tab key="all" title={t('vetting.tab_all')} />
        <Tab key="pending" title={t('vetting.tab_pending')} />
        <Tab key="verified" title={t('vetting.tab_verified')} />
        <Tab key="expiring" title={t('vetting.tab_expiring')} />
      </Tabs>

      {/* Data table */}
      <DataTable
        columns={columns}
        data={records}
        isLoading={loading}
        totalItems={total}
        page={page}
        pageSize={pageSize}
        onPageChange={(p) => {
          setPage(p);
          void fetchRecords(p, activeTab);
        }}
        onRefresh={() => void fetchRecords(page, activeTab)}
        searchable={false}
        emptyContent={
          <EmptyState
            icon={FileCheck}
            title={t('vetting.no_records')}
          />
        }
      />

      {/* Verify Confirm Modal */}
      <ConfirmModal
        isOpen={!!verifyTarget}
        onClose={() => setVerifyTarget(null)}
        onConfirm={() => void handleVerify()}
        title={t('vetting.verify')}
        message={
          verifyTarget
            ? `Verify ${verifyTarget.first_name} ${verifyTarget.last_name}'s ${formatVettingType(verifyTarget.vetting_type)} record?`
            : ''
        }
        confirmLabel={t('vetting.verify')}
        confirmColor="primary"
        isLoading={verifyLoading}
      />

      {/* Reject Confirm Modal */}
      <ConfirmModal
        isOpen={!!rejectTarget}
        onClose={() => {
          setRejectTarget(null);
          setRejectReason('');
        }}
        onConfirm={() => void handleReject()}
        title={t('vetting.reject')}
        message={
          rejectTarget
            ? `Reject ${rejectTarget.first_name} ${rejectTarget.last_name}'s ${formatVettingType(rejectTarget.vetting_type)} record?`
            : ''
        }
        confirmLabel={t('vetting.reject')}
        confirmColor="danger"
        isLoading={rejectLoading}
      >
        <Textarea
          label="Reason"
          placeholder={t('vetting.reject_reason_placeholder')}
          value={rejectReason}
          onValueChange={setRejectReason}
          minRows={2}
          variant="bordered"
          className="mt-2"
        />
      </ConfirmModal>
    </div>
  );
}
