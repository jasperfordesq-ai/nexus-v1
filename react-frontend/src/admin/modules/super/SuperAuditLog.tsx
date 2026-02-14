/**
 * Super Audit Log
 * Cross-tenant action history with date range filtering, action/target type
 * filters, search, pagination, and CSV export.
 */

import { useState, useCallback, useEffect } from 'react';
import { Input, Select, SelectItem, Button } from '@heroui/react';
import { Download, X } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminSuper } from '../../api/adminApi';
import { DataTable, PageHeader, StatusBadge, type Column } from '../../components';
import type { SuperAuditEntry } from '../../api/types';

const PAGE_SIZE = 25;

export function SuperAuditLog() {
  usePageTitle('Super Admin - Audit Log');

  const [logs, setLogs] = useState<SuperAuditEntry[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [actionType, setActionType] = useState('');
  const [targetType, setTargetType] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [page, setPage] = useState(1);

  const loadLogs = useCallback(async () => {
    setLoading(true);
    const res = await adminSuper.getAudit({
      search: search || undefined,
      action_type: actionType || undefined,
      target_type: targetType || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      limit: PAGE_SIZE,
      offset: (page - 1) * PAGE_SIZE,
    });
    if (res.success && res.data) {
      const entries = Array.isArray(res.data) ? res.data : [];
      setLogs(entries);
      // If the API returns a full page, assume there are more; otherwise we're on the last page
      // The total is an estimate for pagination controls
      if (entries.length < PAGE_SIZE) {
        setTotalItems((page - 1) * PAGE_SIZE + entries.length);
      } else {
        // Signal there's at least one more page
        setTotalItems(page * PAGE_SIZE + 1);
      }
    }
    setLoading(false);
  }, [search, actionType, targetType, dateFrom, dateTo, page]);

  useEffect(() => { loadLogs(); }, [loadLogs]);

  // Reset to page 1 when filters change
  const resetAndFilter = () => {
    if (page !== 1) setPage(1);
  };

  const clearFilters = () => {
    setSearch('');
    setActionType('');
    setTargetType('');
    setDateFrom('');
    setDateTo('');
    setPage(1);
  };

  const hasFilters = !!(search || actionType || targetType || dateFrom || dateTo);

  const exportCsv = () => {
    if (logs.length === 0) return;
    const headers = ['ID', 'Action Type', 'Target Type', 'Target', 'Actor', 'Description', 'Date'];
    const rows = logs.map((entry) => [
      entry.id,
      entry.action_type,
      entry.target_type,
      entry.target_label,
      entry.actor_name || `User #${entry.actor_id}`,
      `"${(entry.description || '').replace(/"/g, '""')}"`,
      entry.created_at,
    ]);
    const csv = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `audit-log-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  };

  const columns: Column<SuperAuditEntry>[] = [
    {
      key: 'action_type', label: 'Action', sortable: true,
      render: (entry) => <StatusBadge status={entry.action_type} />,
    },
    {
      key: 'target_label', label: 'Target', sortable: true,
      render: (entry) => (
        <div>
          <span className="font-medium">{entry.target_label}</span>
          <span className="text-xs text-default-400 ml-2">({entry.target_type})</span>
        </div>
      ),
    },
    {
      key: 'actor', label: 'Actor',
      render: (entry) => <span>{entry.actor_name || `User #${entry.actor_id}`}</span>,
    },
    {
      key: 'description', label: 'Description',
      render: (entry) => <span className="text-sm text-default-500">{entry.description}</span>,
    },
    {
      key: 'created_at', label: 'Date', sortable: true,
      render: (entry) => (
        <span className="text-sm text-default-500">
          {new Date(entry.created_at).toLocaleString()}
        </span>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Audit Log"
        description="Cross-tenant action history"
        actions={
          <Button
            variant="flat"
            size="sm"
            startContent={<Download size={16} />}
            onPress={exportCsv}
            isDisabled={logs.length === 0}
          >
            Export CSV
          </Button>
        }
      />

      {/* Filters */}
      <div className="flex flex-wrap gap-3 mb-4 items-end">
        <Select
          label="Action Type"
          size="sm"
          className="max-w-[180px]"
          selectedKeys={actionType ? [actionType] : []}
          onSelectionChange={(keys) => {
            setActionType(String(Array.from(keys)[0] || ''));
            resetAndFilter();
          }}
        >
          <SelectItem key="user_created">User Created</SelectItem>
          <SelectItem key="user_moved">User Moved</SelectItem>
          <SelectItem key="tenant_created">Tenant Created</SelectItem>
          <SelectItem key="tenant_updated">Tenant Updated</SelectItem>
          <SelectItem key="bulk_users_moved">Bulk Users Moved</SelectItem>
          <SelectItem key="bulk_tenants_updated">Bulk Tenants Updated</SelectItem>
          <SelectItem key="federation_lockdown">Federation Lockdown</SelectItem>
          <SelectItem key="federation_updated">Federation Updated</SelectItem>
        </Select>

        <Select
          label="Target Type"
          size="sm"
          className="max-w-[160px]"
          selectedKeys={targetType ? [targetType] : []}
          onSelectionChange={(keys) => {
            setTargetType(String(Array.from(keys)[0] || ''));
            resetAndFilter();
          }}
        >
          <SelectItem key="user">User</SelectItem>
          <SelectItem key="tenant">Tenant</SelectItem>
          <SelectItem key="bulk">Bulk</SelectItem>
          <SelectItem key="federation">Federation</SelectItem>
        </Select>

        <Input
          label="From Date"
          type="date"
          size="sm"
          className="max-w-[170px]"
          value={dateFrom}
          onValueChange={(v) => { setDateFrom(v); resetAndFilter(); }}
        />

        <Input
          label="To Date"
          type="date"
          size="sm"
          className="max-w-[170px]"
          value={dateTo}
          onValueChange={(v) => { setDateTo(v); resetAndFilter(); }}
        />

        <Input
          label="Search"
          size="sm"
          className="max-w-[200px]"
          value={search}
          onValueChange={(v) => { setSearch(v); resetAndFilter(); }}
          isClearable
          onClear={() => { setSearch(''); resetAndFilter(); }}
        />

        {hasFilters && (
          <Button
            size="sm"
            variant="light"
            color="danger"
            startContent={<X size={14} />}
            onPress={clearFilters}
          >
            Clear
          </Button>
        )}
      </div>

      <DataTable
        columns={columns}
        data={logs}
        isLoading={loading}
        onRefresh={loadLogs}
        totalItems={totalItems}
        page={page}
        pageSize={PAGE_SIZE}
        onPageChange={setPage}
      />
    </div>
  );
}
export default SuperAuditLog;
