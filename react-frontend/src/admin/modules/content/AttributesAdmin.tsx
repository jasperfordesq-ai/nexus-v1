/**
 * Attributes Admin
 * Manage custom listing attributes and their options.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button } from '@heroui/react';
import { Tags, Plus, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminAttributes } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';
import type { AdminAttribute } from '../../api/types';

export function AttributesAdmin() {
  usePageTitle('Admin - Attributes');
  const [items, setItems] = useState<AdminAttribute[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminAttributes.list();
      if (res.success && res.data) {
        if (Array.isArray(res.data)) {
          setItems(res.data as AdminAttribute[]);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<AdminAttribute>[] = [
    { key: 'name', label: 'Attribute Name', sortable: true },
    { key: 'slug', label: 'Slug' },
    { key: 'type', label: 'Type', sortable: true },
    {
      key: 'options', label: 'Options',
      render: (item) => <span className="text-sm text-default-500">{Array.isArray(item.options) ? item.options.join(', ') : '--'}</span>,
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader
          title="Attributes"
          description="Custom listing attributes"
          actions={<Button color="primary" startContent={<Plus size={16} />}>Create Attribute</Button>}
        />
        <EmptyState icon={Tags} title="No Attributes" description="Create custom attributes to add extra fields to listings (e.g., location type, skill level)." />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Attributes"
        description="Custom listing attributes"
        actions={
          <div className="flex gap-2">
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>
            <Button color="primary" startContent={<Plus size={16} />}>Create Attribute</Button>
          </div>
        }
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />
    </div>
  );
}

export default AttributesAdmin;
