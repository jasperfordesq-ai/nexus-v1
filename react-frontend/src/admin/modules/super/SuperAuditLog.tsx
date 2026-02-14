import { useState, useCallback, useEffect } from 'react';
import { Input, Select, SelectItem } from '@heroui/react';
import { usePageTitle } from '@/hooks';
import { adminSuper } from '../../api/adminApi';
import { DataTable, PageHeader, StatusBadge, type Column } from '../../components';
import type { SuperAuditEntry } from '../../api/types';

export function SuperAuditLog() {
  usePageTitle('Super Admin - Audit Log');

  const [logs, setLogs] = useState<SuperAuditEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [actionType, setActionType] = useState('');
  const [targetType, setTargetType] = useState('');

  const loadLogs = useCallback(async () => {
    setLoading(true);
    const res = await adminSuper.getAudit({
      search: search || undefined,
      action_type: actionType || undefined,
      target_type: targetType || undefined,
      limit: 50,
    });
    if (res.success && res.data) {
      setLogs(Array.isArray(res.data) ? res.data : []);
    }
    setLoading(false);
  }, [search, actionType, targetType]);

  useEffect(() => { loadLogs(); }, [loadLogs]);

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
      render: (entry) => <span className="text-sm text-default-500">{new Date(entry.created_at).toLocaleString()}</span>,
    },
  ];

  return (
    <div>
      <PageHeader title="Audit Log" description="Cross-tenant action history" />
      <div className="flex gap-3 mb-4">
        <Select label="Action Type" size="sm" className="max-w-xs"
          selectedKeys={actionType ? [actionType] : []}
          onSelectionChange={(keys) => setActionType(String(Array.from(keys)[0] || ''))}>
          <SelectItem key="user_created">User Created</SelectItem>
          <SelectItem key="user_moved">User Moved</SelectItem>
          <SelectItem key="tenant_created">Tenant Created</SelectItem>
          <SelectItem key="tenant_updated">Tenant Updated</SelectItem>
          <SelectItem key="bulk_users_moved">Bulk Users Moved</SelectItem>
          <SelectItem key="bulk_tenants_updated">Bulk Tenants Updated</SelectItem>
        </Select>
        <Select label="Target Type" size="sm" className="max-w-xs"
          selectedKeys={targetType ? [targetType] : []}
          onSelectionChange={(keys) => setTargetType(String(Array.from(keys)[0] || ''))}>
          <SelectItem key="user">User</SelectItem>
          <SelectItem key="tenant">Tenant</SelectItem>
          <SelectItem key="bulk">Bulk</SelectItem>
        </Select>
        <Input label="Search" size="sm" className="max-w-xs" value={search}
          onValueChange={setSearch} isClearable onClear={() => setSearch('')} />
      </div>
      <DataTable
        columns={columns}
        data={logs}
        isLoading={loading}
        onRefresh={loadLogs}
        totalItems={logs.length}
        page={1}
        pageSize={50}
        onPageChange={() => {}}
      />
    </div>
  );
}
export default SuperAuditLog;
