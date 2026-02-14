/**
 * GDPR Breaches
 * DataTable of data breaches.
 */

import { useEffect, useState, useCallback } from 'react';
import { Button, Chip } from '@heroui/react';
import { RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable, StatusBadge } from '../../components';
import type { Column } from '../../components';
import type { GdprBreach } from '../../api/types';

const severityColorMap: Record<string, 'default' | 'primary' | 'warning' | 'danger'> = {
  low: 'default',
  medium: 'primary',
  high: 'warning',
  critical: 'danger',
};

export function GdprBreaches() {
  usePageTitle('Admin - Data Breaches');
  const toast = useToast();

  const [breaches, setBreaches] = useState<GdprBreach[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprBreaches();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setBreaches(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error('Failed to load breaches');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const columns: Column<GdprBreach>[] = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'title', label: 'Title', sortable: true },
    {
      key: 'severity',
      label: 'Severity',
      sortable: true,
      render: (b) => (
        <Chip size="sm" variant="flat" color={severityColorMap[b.severity] || 'default'} className="capitalize">
          {b.severity}
        </Chip>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (b) => <StatusBadge status={b.status} />,
    },
    { key: 'description', label: 'Description' },
    {
      key: 'reported_at',
      label: 'Reported',
      sortable: true,
      render: (b) => b.reported_at ? new Date(b.reported_at).toLocaleDateString() : '---',
    },
  ];

  return (
    <div>
      <PageHeader
        title="Data Breaches"
        description="Track and manage data breach incidents"
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
        data={breaches}
        isLoading={loading}
        searchable={false}
        emptyContent="No data breaches recorded"
      />
    </div>
  );
}

export default GdprBreaches;
