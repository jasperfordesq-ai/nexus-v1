/**
 * Federation Partnerships
 * Lists and manages federation partnerships with other communities.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button } from '@heroui/react';
import { Handshake, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminFederation } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, StatusBadge, type Column } from '../../components';

interface Partnership {
  id: number;
  partner_name: string;
  partner_slug: string;
  status: string;
  created_at: string;
}

export function Partnerships() {
  usePageTitle('Admin - Federation Partnerships');
  const [items, setItems] = useState<Partnership[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getPartnerships();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: Partnership[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<Partnership>[] = [
    { key: 'partner_name', label: 'Partner Community', sortable: true },
    { key: 'partner_slug', label: 'Slug' },
    {
      key: 'status', label: 'Status',
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'created_at', label: 'Since', sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title="Partnerships" description="Manage community partnerships" />
        <EmptyState icon={Handshake} title="No Partnerships" description="No federation partnerships have been established yet. Visit the Partner Directory to find communities." />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Partnerships"
        description="Manage community partnerships"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />
    </div>
  );
}

export default Partnerships;
