/**
 * Admin Activity Log
 * Read-only audit trail of admin actions with server-side pagination.
 * Parity: PHP AdminController::activityLogs()
 */

import { useState, useCallback, useEffect } from 'react';
import { Chip, Avatar, Button } from '@heroui/react';
import { Activity, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminSystem } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { ActivityLogEntry } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Action colour mapping
// ─────────────────────────────────────────────────────────────────────────────

const actionColorMap: Record<string, 'success' | 'warning' | 'danger' | 'primary' | 'default' | 'secondary'> = {
  login: 'success',
  logout: 'default',
  create: 'primary',
  update: 'secondary',
  delete: 'danger',
  approve: 'success',
  reject: 'danger',
  suspend: 'warning',
  ban: 'danger',
  reactivate: 'success',
  import: 'primary',
  export: 'primary',
  reset: 'warning',
  transfer: 'secondary',
};

function getActionColor(action: string): 'success' | 'warning' | 'danger' | 'primary' | 'default' | 'secondary' {
  const lower = action.toLowerCase();
  for (const [key, color] of Object.entries(actionColorMap)) {
    if (lower.includes(key)) return color;
  }
  return 'default';
}

// ─────────────────────────────────────────────────────────────────────────────
// Date formatter
// ─────────────────────────────────────────────────────────────────────────────

function formatDate(dateStr: string): string {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function ActivityLog() {
  usePageTitle('Admin - Activity Log');

  const [entries, setEntries] = useState<ActivityLogEntry[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminSystem.getActivityLog({ page, limit: 20 });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setEntries(data);
          setTotal(data.length);
        } else if (data && typeof data === 'object') {
          const paginatedData = data as { data: ActivityLogEntry[]; meta?: { total: number } };
          setEntries(paginatedData.data || []);
          setTotal(paginatedData.meta?.total || 0);
        }
      }
    } catch {
      // API may not be available yet
      setEntries([]);
      setTotal(0);
    }
    setLoading(false);
  }, [page]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Client-side search filter (API doesn't support search param)
  const filteredEntries = search
    ? entries.filter(
        (e) =>
          e.action.toLowerCase().includes(search.toLowerCase()) ||
          e.description.toLowerCase().includes(search.toLowerCase()) ||
          e.user_name.toLowerCase().includes(search.toLowerCase())
      )
    : entries;

  const columns: Column<ActivityLogEntry>[] = [
    {
      key: 'user_name',
      label: 'User',
      sortable: true,
      render: (entry) => (
        <div className="flex items-center gap-3">
          <Avatar
            src={entry.user_avatar || undefined}
            name={entry.user_name}
            size="sm"
          />
          <div>
            <p className="font-medium text-foreground">{entry.user_name}</p>
            {entry.user_email && (
              <p className="text-xs text-default-400">{entry.user_email}</p>
            )}
          </div>
        </div>
      ),
    },
    {
      key: 'action',
      label: 'Action',
      sortable: true,
      render: (entry) => (
        <Chip size="sm" variant="flat" color={getActionColor(entry.action)}>
          {entry.action}
        </Chip>
      ),
    },
    {
      key: 'description',
      label: 'Description',
      render: (entry) => (
        <span className="text-sm text-default-600 line-clamp-2">
          {entry.description || '—'}
        </span>
      ),
    },
    {
      key: 'ip_address',
      label: 'IP Address',
      render: (entry) => (
        <code className="text-xs text-default-500 bg-default-100 px-1.5 py-0.5 rounded">
          {entry.ip_address || '—'}
        </code>
      ),
    },
    {
      key: 'created_at',
      label: 'Date',
      sortable: true,
      render: (entry) => (
        <span className="text-sm text-default-500">
          {formatDate(entry.created_at)}
        </span>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Activity Log"
        description="Admin action audit trail"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
          >
            Refresh
          </Button>
        }
      />

      {/* Empty state icon hint */}
      <DataTable
        columns={columns}
        data={filteredEntries}
        isLoading={loading}
        searchPlaceholder="Filter by action, description, or user..."
        onSearch={(q) => setSearch(q)}
        onRefresh={loadData}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        emptyContent={
          <div className="flex flex-col items-center gap-2 py-8 text-default-400">
            <Activity size={40} />
            <p>No activity log entries found</p>
          </div>
        }
      />
    </div>
  );
}

export default ActivityLog;
