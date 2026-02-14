/**
 * User Monitoring
 * View users currently under messaging monitoring restrictions.
 * Parity: PHP BrokerControlsController::monitoring()
 */

import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Button, Chip } from '@heroui/react';
import { ArrowLeft, Eye } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';
import type { MonitoredUser } from '../../api/types';

export function UserMonitoring() {
  usePageTitle('Admin - User Monitoring');
  const { tenantPath } = useTenant();

  const [items, setItems] = useState<MonitoredUser[]>([]);
  const [loading, setLoading] = useState(true);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getMonitoring();
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setItems(data);
        } else if (data && typeof data === 'object' && 'data' in (data as Record<string, unknown>)) {
          setItems((data as { data: MonitoredUser[] }).data || []);
        }
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const columns: Column<MonitoredUser>[] = [
    {
      key: 'user_name',
      label: 'User',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.user_name}</span>
      ),
    },
    {
      key: 'under_monitoring',
      label: 'Status',
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={item.under_monitoring ? 'warning' : 'default'}
          startContent={<Eye size={12} />}
        >
          {item.under_monitoring ? 'Under Monitoring' : 'Not Monitored'}
        </Chip>
      ),
    },
    {
      key: 'monitoring_reason',
      label: 'Reason',
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.monitoring_reason || '—'}
        </span>
      ),
    },
    {
      key: 'monitoring_started_at',
      label: 'Started',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.monitoring_started_at
            ? new Date(item.monitoring_started_at).toLocaleDateString()
            : '—'
          }
        </span>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="User Monitoring"
        description="Users under messaging monitoring restrictions"
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/broker-controls')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            Back
          </Button>
        }
      />

      {!loading && items.length === 0 ? (
        <EmptyState
          icon={Eye}
          title="No Monitored Users"
          description="No users are currently under monitoring restrictions."
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          searchable={false}
          onRefresh={loadItems}
        />
      )}
    </div>
  );
}

export default UserMonitoring;
