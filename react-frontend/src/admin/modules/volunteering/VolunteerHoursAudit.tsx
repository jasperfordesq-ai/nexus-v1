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
  Input,
  Card,
  CardBody,
  CardHeader,
  Tab,
  Tabs,
} from '@heroui/react';
import Clock from 'lucide-react/icons/clock';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Hourglass from 'lucide-react/icons/hourglass';
import CreditCard from 'lucide-react/icons/credit-card';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ThumbsUp from 'lucide-react/icons/thumbs-up';
import ThumbsDown from 'lucide-react/icons/thumbs-down';
import Download from 'lucide-react/icons/download';
import Building2 from 'lucide-react/icons/building-2';
import Banknote from 'lucide-react/icons/banknote';
import CalendarRange from 'lucide-react/icons/calendar-range';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { PageHeader, StatCard, DataTable, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

// ── Helpers ───────────────────────────────────────────────────────────────────

function exportToCsv(data: Array<Record<string, unknown>>, filename: string) {
  if (data.length === 0) return;
  const first = data[0];
  if (!first) return;
  const headers = Object.keys(first);
  const csv = [
    headers.join(','),
    ...data.map((r) => headers.map((h) => JSON.stringify(r[h] ?? '')).join(',')),
  ].join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

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

  // Date range filter
  const [dateFrom, setDateFrom] = useState<string>('');
  const [dateTo, setDateTo] = useState<string>('');

  // Active tab
  const [activeTab, setActiveTab] = useState<string>('hours');

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

  // ── Filtered data (by date range) ─────────────────────────────────────────
  const filteredItems = useMemo(() => {
    return items.filter((item) => {
      if (!item.created_at) return true;
      const itemDate = new Date(item.created_at);
      if (dateFrom) {
        const from = new Date(dateFrom);
        from.setHours(0, 0, 0, 0);
        if (itemDate < from) return false;
      }
      if (dateTo) {
        const to = new Date(dateTo);
        to.setHours(23, 59, 59, 999);
        if (itemDate > to) return false;
      }
      return true;
    });
  }, [items, dateFrom, dateTo]);

  // ── Per-org breakdown ───────────────────────────────────────────────────────
  const orgBreakdown = useMemo(() => {
    const map = new Map<string, { approved: number; pending: number }>();
    filteredItems.forEach((item) => {
      const orgName = item.org_name || t('volunteering.unknown_org', 'Unknown');
      if (!map.has(orgName)) map.set(orgName, { approved: 0, pending: 0 });
      const entry = map.get(orgName)!;
      const hours = Number.parseFloat(String(item.hours)) || 0;
      if (item.status === 'approved') entry.approved += hours;
      else if (item.status === 'pending') entry.pending += hours;
    });
    return Array.from(map.entries())
      .map(([name, data]) => ({
        name,
        approved: Number(data.approved.toFixed(2)),
        pending: Number(data.pending.toFixed(2)),
      }))
      .sort((a, b) => (b.approved + b.pending) - (a.approved + a.pending));
  }, [filteredItems, t]);


  // ── Payment reconciliation data ─────────────────────────────────────────────
  const paidEntries = useMemo(() => {
    return filteredItems.filter((item) => item.paid === 1 || item.paid === true);
  }, [filteredItems]);

  const handleExportCsv = useCallback(() => {
    const exportData = filteredItems.map((item) => ({
      volunteer: `${item.first_name} ${item.last_name}`,
      organization: item.org_name || '',
      hours: item.hours,
      status: item.status,
      date: item.created_at ? new Date(item.created_at).toLocaleDateString() : '',
      paid: (item.paid === 1 || item.paid === true) ? t('common.yes', 'Yes') : t('common.no', 'No'),
      paid_amount: item.paid_amount || 0,
    }));
    exportToCsv(exportData, `volunteer-hours-${new Date().toISOString().split('T')[0]}.csv`);
  }, [filteredItems, t]);

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
  ], [actionInProgress, handleVerify, t]);

  const topContent = useMemo(() => (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-end gap-3">
        <Select
          className="w-48"
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
        <Input
          type="date"
          label={t('volunteering.date_from', 'From')}
          size="sm"
          className="w-44"
          value={dateFrom}
          onValueChange={setDateFrom}
          startContent={<CalendarRange size={14} className="text-default-400" />}
        />
        <Input
          type="date"
          label={t('volunteering.date_to', 'To')}
          size="sm"
          className="w-44"
          value={dateTo}
          onValueChange={setDateTo}
          startContent={<CalendarRange size={14} className="text-default-400" />}
        />
        {(dateFrom || dateTo) && (
          <Button
            size="sm"
            variant="light"
            onPress={() => { setDateFrom(''); setDateTo(''); }}
          >
            {t('common.clear_filters', 'Clear dates')}
          </Button>
        )}
        <Button
          size="sm"
          variant="flat"
          startContent={<Download size={14} />}
          onPress={handleExportCsv}
          isDisabled={filteredItems.length === 0}
        >
          {t('volunteering.export_csv', 'Export CSV')}
        </Button>
        {hasMore && (
          <Button size="sm" variant="flat" onPress={() => loadData(cursor ?? undefined)} isLoading={loading}>
            {t('common.load_more', 'Load More')}
          </Button>
        )}
      </div>
    </div>
  ), [statusFilter, hasMore, cursor, loading, t, loadData, dateFrom, dateTo, handleExportCsv, filteredItems.length]);

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

      {/* Per-org breakdown */}
      {orgBreakdown.length > 0 && (
        <Card className="mb-6">
          <CardHeader>
            <div className="flex items-center gap-2">
              <Building2 size={18} />
              <span className="font-semibold">
                {t('volunteering.org_breakdown_title', 'Hours by Organization')}
              </span>
            </div>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {orgBreakdown.map((org) => (
                <div
                  key={org.name}
                  className="flex items-center justify-between p-3 rounded-lg bg-default-50"
                >
                  <span className="font-medium text-sm truncate mr-2">{org.name}</span>
                  <div className="flex gap-3 text-xs shrink-0">
                    <span className="text-success">
                      {org.approved} {t('volunteering.approved_abbr', 'approved')}
                    </span>
                    {org.pending > 0 && (
                      <span className="text-warning">
                        {org.pending} {t('volunteering.pending_abbr', 'pending')}
                      </span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Tabs: Hours table + Payment reconciliation */}
      <Tabs
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(key as string)}
        variant="underlined"
        className="mb-4"
      >
        <Tab key="hours" title={t('volunteering.tab_hours', 'Hours Log')} />
        <Tab
          key="payments"
          title={
            <div className="flex items-center gap-1.5">
              <Banknote size={14} />
              {t('volunteering.tab_payments', 'Payment Reconciliation')}
            </div>
          }
        />
      </Tabs>

      {activeTab === 'hours' && (
        <>
          {!loading && filteredItems.length === 0 ? (
            <EmptyState
              icon={Clock}
              title={t('volunteering.no_hours', 'No hours logged')}
              description={t('volunteering.no_hours_desc', 'No volunteer hours have been logged yet.')}
            />
          ) : (
            <DataTable
              columns={columns}
              data={filteredItems}
              isLoading={loading}
              onRefresh={() => loadData()}
              searchable={false}
              topContent={topContent}
            />
          )}
        </>
      )}

      {activeTab === 'payments' && (
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Banknote size={18} />
              <span className="font-semibold">
                {t('volunteering.payment_reconciliation_title', 'Payment Reconciliation')}
              </span>
            </div>
          </CardHeader>
          <CardBody>
            {paidEntries.length === 0 ? (
              <div className="text-center py-8">
                <CreditCard size={40} className="mx-auto mb-3 text-default-300" />
                <p className="text-default-500 text-sm">
                  {t('volunteering.no_paid_entries', 'No paid entries found.')}
                </p>
                <p className="text-default-400 text-xs mt-1">
                  {t('volunteering.payment_tracking_note', 'Payment tracking is available when auto-pay is enabled and hours are marked as paid.')}
                </p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-divider text-left">
                      <th className="py-2 px-3 font-medium text-default-500">
                        {t('volunteering.col_volunteer', 'Volunteer')}
                      </th>
                      <th className="py-2 px-3 font-medium text-default-500">
                        {t('volunteering.col_organization', 'Organization')}
                      </th>
                      <th className="py-2 px-3 font-medium text-default-500">
                        {t('volunteering.col_hours', 'Hours')}
                      </th>
                      <th className="py-2 px-3 font-medium text-default-500">
                        {t('volunteering.col_amount_paid', 'Amount Paid')}
                      </th>
                      <th className="py-2 px-3 font-medium text-default-500">
                        {t('volunteering.col_date', 'Date')}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {paidEntries.map((entry) => (
                      <tr key={entry.id} className="border-b border-divider/50 hover:bg-default-50">
                        <td className="py-2 px-3 font-medium">
                          {entry.first_name} {entry.last_name}
                        </td>
                        <td className="py-2 px-3">{entry.org_name || '--'}</td>
                        <td className="py-2 px-3 font-mono">{entry.hours}</td>
                        <td className="py-2 px-3 font-mono text-success">
                          {entry.paid_amount > 0 ? entry.paid_amount.toFixed(2) : '--'}
                        </td>
                        <td className="py-2 px-3 text-default-500">
                          {entry.created_at ? new Date(entry.created_at).toLocaleDateString() : '--'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot>
                    <tr className="border-t-2 border-divider font-semibold">
                      <td className="py-2 px-3" colSpan={2}>
                        {t('volunteering.total', 'Total')}
                      </td>
                      <td className="py-2 px-3 font-mono">
                        {paidEntries.reduce((sum, e) => sum + e.hours, 0)}
                      </td>
                      <td className="py-2 px-3 font-mono text-success">
                        {paidEntries.reduce((sum, e) => sum + (e.paid_amount || 0), 0).toFixed(2)}
                      </td>
                      <td />
                    </tr>
                  </tfoot>
                </table>
              </div>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export default VolunteerHoursAudit;
