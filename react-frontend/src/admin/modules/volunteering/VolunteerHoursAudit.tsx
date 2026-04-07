// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Hours Audit
 * Admin page for auditing volunteer hours, approving/declining pending entries,
 * and viewing payment trail.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Button,
  Chip,
  Select,
  SelectItem,
} from '@heroui/react';
import {
  Clock,
  CheckCircle,
  Hourglass,
  CreditCard,
  RefreshCw,
  ThumbsUp,
  ThumbsDown,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { PageHeader, StatCard, DataTable, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

interface HoursStats {
  total_hours: number;
  approved_hours: number;
  pending_hours: number;
  total_paid: number;
}

interface HourLog {
  id: number;
  hours: number;
  status: string;
  created_at: string;
  paid: number | boolean;
  paid_amount: number;
  first_name: string;
  last_name: string;
  org_name: string;
}

const STATUS_COLORS: Record<string, 'success' | 'danger' | 'warning' | 'default'> = {
  approved: 'success',
  declined: 'danger',
  pending: 'warning',
};

export function VolunteerHoursAudit() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.hours_audit_title', 'Hours Audit'));
  const toast = useToast();

  const [stats, setStats] = useState<HoursStats>({
    total_hours: 0,
    approved_hours: 0,
    pending_hours: 0,
    total_paid: 0,
  });
  const [items, setItems] = useState<HourLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [actionInProgress, setActionInProgress] = useState<number | null>(null);

  const loadData = useCallback(async (appendCursor?: string) => {
    if (!appendCursor) setLoading(true);
    try {
      const params: { status?: string; cursor?: string } = {};
      if (statusFilter !== 'all') params.status = statusFilter;
      if (appendCursor) params.cursor = appendCursor;

      const res = await adminVolunteering.listHours(params);
      if (res.success) {
        const payload = res.data as unknown;

        // The endpoint returns { data: [...], stats: {...}, meta: {...} }
        // But it may also be double-wrapped as { data: { data: [...], stats: {...}, meta: {...} } }
        let hours: HourLog[] = [];
        let newStats: HoursStats | null = null;
        let meta: { next_cursor?: string; has_more?: boolean } | null = null;

        if (payload && typeof payload === 'object') {
          // Check for double-wrap
          const p = payload as Record<string, unknown>;
          const inner = ('data' in p && typeof p.data === 'object' && p.data !== null && 'stats' in (p.data as Record<string, unknown>))
            ? p.data as Record<string, unknown>
            : p;

          if (Array.isArray(inner.data)) {
            hours = inner.data as HourLog[];
          } else if (Array.isArray(inner)) {
            hours = inner as unknown as HourLog[];
          }

          if (inner.stats && typeof inner.stats === 'object') {
            newStats = inner.stats as HoursStats;
          }
          if (inner.meta && typeof inner.meta === 'object') {
            meta = inner.meta as { next_cursor?: string; has_more?: boolean };
          }
        }

        if (appendCursor) {
          setItems((prev) => [...prev, ...hours]);
        } else {
          setItems(hours);
          if (newStats) setStats(newStats);
        }

        setCursor(meta?.next_cursor || null);
        setHasMore(meta?.has_more || false);
      }
    } catch {
      toast.error(t('volunteering.failed_load_hours', 'Failed to load hours'));
      if (!appendCursor) {
        setItems([]);
      }
    }
    setLoading(false);
  }, [statusFilter, toast, t]);

  useEffect(() => { loadData(); }, [loadData]);

  const handleVerify = useCallback(async (logId: number, action: 'approve' | 'decline') => {
    setActionInProgress(logId);
    try {
      const res = await adminVolunteering.verifyHours(logId, action);
      if (res.success) {
        toast.success(
          action === 'approve'
            ? t('volunteering.hours_approved', 'Hours approved')
            : t('volunteering.hours_declined', 'Hours declined')
        );
        // Refresh all data to update stats
        loadData();
      } else {
        toast.error((res as { message?: string }).message || t('volunteering.verify_failed', 'Verification failed'));
      }
    } catch {
      toast.error(t('volunteering.verify_failed', 'Verification failed'));
    }
    setActionInProgress(null);
  }, [toast, t, loadData]);

  const columns: Column<HourLog>[] = useMemo(() => [
    {
      key: 'volunteer',
      label: t('volunteering.col_volunteer', 'Volunteer'),
      sortable: true,
      render: (item) => (
        <span className="font-medium">
          {item.first_name} {item.last_name}
        </span>
      ),
    },
    {
      key: 'org_name',
      label: t('volunteering.col_organization', 'Organization'),
      sortable: true,
      render: (item) => <span>{item.org_name || '--'}</span>,
    },
    {
      key: 'hours',
      label: t('volunteering.col_hours', 'Hours'),
      sortable: true,
      render: (item) => <span className="font-mono">{item.hours}</span>,
    },
    {
      key: 'created_at',
      label: t('volunteering.col_date', 'Date'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'status',
      label: t('volunteering.col_status', 'Status'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color={STATUS_COLORS[item.status] || 'default'} className="capitalize">
          {item.status}
        </Chip>
      ),
    },
    {
      key: 'paid',
      label: t('volunteering.col_paid', 'Paid?'),
      render: (item) => {
        const isPaid = item.paid === 1 || item.paid === true;
        return (
          <div className="flex items-center gap-1">
            <Chip size="sm" variant="flat" color={isPaid ? 'success' : 'default'}>
              {isPaid ? t('common.yes', 'Yes') : t('common.no', 'No')}
            </Chip>
            {isPaid && item.paid_amount > 0 && (
              <span className="text-xs text-default-400 font-mono">{item.paid_amount}</span>
            )}
          </div>
        );
      },
    },
    {
      key: 'actions',
      label: t('common.actions', 'Actions'),
      render: (item) => {
        if (item.status !== 'pending') return null;
        const isThisItem = actionInProgress === item.id;
        return (
          <div className="flex gap-1">
            <Button
              size="sm"
              variant="flat"
              color="success"
              startContent={<ThumbsUp size={14} />}
              isLoading={isThisItem}
              onPress={() => handleVerify(item.id, 'approve')}
            >
              {t('volunteering.approve', 'Approve')}
            </Button>
            <Button
              size="sm"
              variant="flat"
              color="danger"
              startContent={<ThumbsDown size={14} />}
              isLoading={isThisItem}
              onPress={() => handleVerify(item.id, 'decline')}
            >
              {t('volunteering.decline', 'Decline')}
            </Button>
          </div>
        );
      },
    },
  ], [t, actionInProgress, handleVerify]);

  const topContent = useMemo(() => (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <Select
        className="max-w-[200px]"
        label={t('volunteering.filter_by_status', 'Filter by status')}
        size="sm"
        selectedKeys={new Set([statusFilter])}
        onSelectionChange={(keys) => {
          const val = Array.from(keys)[0] as string;
          setStatusFilter(val || 'all');
        }}
      >
        <SelectItem key="all">{t('common.all', 'All')}</SelectItem>
        <SelectItem key="pending">{t('common.pending', 'Pending')}</SelectItem>
        <SelectItem key="approved">{t('common.approved', 'Approved')}</SelectItem>
        <SelectItem key="declined">{t('common.declined', 'Declined')}</SelectItem>
      </Select>
      {hasMore && (
        <Button size="sm" variant="flat" onPress={() => loadData(cursor ?? undefined)} isLoading={loading}>
          {t('common.load_more', 'Load More')}
        </Button>
      )}
    </div>
  ), [statusFilter, hasMore, cursor, loading, t, loadData]);

  return (
    <div>
      <PageHeader
        title={t('volunteering.hours_audit_title', 'Volunteer Hours Audit')}
        description={t('volunteering.hours_audit_desc', 'Audit logged hours, approve/decline pending entries, and review payments')}
        actions={
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={() => loadData()} isLoading={loading}>
            {t('common.refresh', 'Refresh')}
          </Button>
        }
      />

      {/* Stats row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={t('volunteering.stat_total_hours', 'Total Hours Logged')}
          value={stats.total_hours}
          icon={Clock}
          color="primary"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_approved_hours', 'Hours Approved')}
          value={stats.approved_hours}
          icon={CheckCircle}
          color="success"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_pending_hours', 'Hours Pending')}
          value={stats.pending_hours}
          icon={Hourglass}
          color="warning"
          loading={loading}
        />
        <StatCard
          label={t('volunteering.stat_total_paid', 'Total Credits Paid')}
          value={stats.total_paid}
          icon={CreditCard}
          color="secondary"
          loading={loading}
        />
      </div>

      {/* Hours table */}
      {!loading && items.length === 0 ? (
        <EmptyState
          icon={Clock}
          title={t('volunteering.no_hours', 'No hours logged')}
          description={t('volunteering.no_hours_desc', 'No volunteer hours have been logged yet.')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          onRefresh={() => loadData()}
          searchable={false}
          topContent={topContent}
        />
      )}
    </div>
  );
}

export default VolunteerHoursAudit;
