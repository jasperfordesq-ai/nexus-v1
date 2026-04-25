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
import { useSearchParams } from 'react-router-dom';
import {
  Tabs,
  Tab,
  Chip,
  Button,
  Textarea,
} from '@heroui/react';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Clock from 'lucide-react/icons/clock';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import FileCheck from 'lucide-react/icons/file-check';
import XCircle from 'lucide-react/icons/circle-x';
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
  const { t } = useTranslation('broker');
  usePageTitle(t('vetting.title'));
  const toast = useToast();
  const [searchParams] = useSearchParams();

  // ── Stats ────────────────────────────────────────────────────────────────
  const [stats, setStats] = useState<VettingStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // ── Table data ───────────────────────────────────────────────────────────
  const [records, setRecords] = useState<VettingRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [activeTab, setActiveTab] = useState<TabKey>('all');
  const [search, setSearch] = useState(() => searchParams.get('search') ?? '');
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

  const fetchRecords = useCallback(async (p: number, tab: TabKey, searchQuery: string) => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { ...tabToParams(tab), page: p, per_page: pageSize };
      if (searchQuery.trim()) params.search = searchQuery.trim();
      const res = await adminVetting.list(params as Parameters<typeof adminVetting.list>[0]);
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
    void fetchRecords(1, activeTab, search);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab, fetchRecords]);

  const handleSearch = useCallback((q: string) => {
    setSearch(q);
    setPage(1);
    void fetchRecords(1, activeTab, q);
  }, [activeTab, fetchRecords]);

  // ── Actions ──────────────────────────────────────────────────────────────

  const handleVerify = useCallback(async () => {
    if (!verifyTarget) return;
    setVerifyLoading(true);
    try {
      await adminVetting.verify(verifyTarget.id);
      toast.success(t('vetting.verified_success'));
      setVerifyTarget(null);
      void fetchRecords(page, activeTab, search);
      void fetchStats();
    } catch {
      toast.error(t('common.error'));
    } finally {
      setVerifyLoading(false);
    }
  }, [verifyTarget, page, activeTab, search, fetchRecords, fetchStats, toast, t]);

  const handleReject = useCallback(async () => {
    if (!rejectTarget) return;
    setRejectLoading(true);
    try {
      await adminVetting.reject(rejectTarget.id, rejectReason);
      toast.success(t('vetting.rejected_success'));
      setRejectTarget(null);
      setRejectReason('');
      void fetchRecords(page, activeTab, search);
      void fetchStats();
    } catch {
      toast.error(t('common.error'));
    } finally {
      setRejectLoading(false);
    }
  }, [rejectTarget, rejectReason, page, activeTab, search, fetchRecords, fetchStats, toast, t]);

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
          label={t('vetting.stat_total')}
          value={stats?.total ?? 0}
          icon={FileCheck}
          color="default"
          loading={statsLoading}
        />
        <StatCard
          label={t('vetting.stat_pending')}
          value={stats?.pending ?? 0}
          icon={Clock}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label={t('vetting.stat_verified')}
          value={stats?.verified ?? 0}
          icon={ShieldCheck}
          color="success"
          loading={statsLoading}
        />
        <StatCard
          label={t('vetting.stat_expiring')}
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
        aria-label={t('vetting.tabs_aria')}
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
          void fetchRecords(p, activeTab, search);
        }}
        onRefresh={() => void fetchRecords(page, activeTab, search)}
        searchable
        searchPlaceholder={t('vetting.search_placeholder')}
        onSearch={handleSearch}
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
        title={t('vetting.confirm_verify_title')}
        message={
          verifyTarget
            ? t('vetting.confirm_verify_message', {
                name: `${verifyTarget.first_name} ${verifyTarget.last_name}`,
                type: formatVettingType(verifyTarget.vetting_type),
              })
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
        title={t('vetting.confirm_reject_title')}
        message={
          rejectTarget
            ? t('vetting.confirm_reject_body', {
                name: `${rejectTarget.first_name} ${rejectTarget.last_name}`,
                type: formatVettingType(rejectTarget.vetting_type),
              })
            : ''
        }
        confirmLabel={t('vetting.reject')}
        confirmColor="danger"
        isLoading={rejectLoading}
      >
        <Textarea
          label={t('vetting.reject_reason_label')}
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
