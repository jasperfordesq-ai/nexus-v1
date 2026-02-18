/**
 * Error Logs
 * DataTable of recent error log entries.
 */

import { useEffect, useState, useCallback } from 'react';
import { Button, Chip } from '@heroui/react';
import { RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable } from '../../components';
import type { Column } from '../../components';
import type { ErrorLogEntry } from '../../api/types';

export function ErrorLogs() {
  usePageTitle('Admin - Error Logs');
  const toast = useToast();

  const [logs, setLogs] = useState<ErrorLogEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getLogs({ page });
      if (res.success && res.data) {
        const result = res.data as unknown;
        if (Array.isArray(result)) {
          setLogs(result);
          setTotal(result.length);
        } else if (result && typeof result === 'object') {
          const pd = result as { data?: ErrorLogEntry[]; meta?: { total?: number } };
          setLogs(pd.data || []);
          setTotal(pd.meta?.total ?? pd.data?.length ?? 0);
        }
      }
    } catch {
      toast.error('Failed to load error logs');
    } finally {
      setLoading(false);
    }
  }, [page, toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const columns: Column<ErrorLogEntry>[] = [
    { key: 'id', label: 'ID', sortable: true },
    {
      key: 'action',
      label: 'Action',
      sortable: true,
      render: (entry) => (
        <Chip size="sm" variant="flat" color="danger">
          {entry.action}
        </Chip>
      ),
    },
    { key: 'description', label: 'Description' },
    {
      key: 'user_name',
      label: 'User',
      render: (entry) => entry.user_name || '---',
    },
    {
      key: 'ip_address',
      label: 'IP',
      render: (entry) => (
        <span className="text-xs font-mono">{entry.ip_address || '---'}</span>
      ),
    },
    {
      key: 'created_at',
      label: 'Date',
      sortable: true,
      render: (entry) => new Date(entry.created_at).toLocaleString(),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Error Logs"
        description="Recent error-level activity log entries"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      <DataTable
        columns={columns}
        data={logs}
        isLoading={loading}
        totalItems={total}
        page={page}
        onPageChange={setPage}
        searchable={false}
        emptyContent="No error logs found"
      />
    </div>
  );
}

export default ErrorLogs;
