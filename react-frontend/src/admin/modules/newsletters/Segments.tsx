/**
 * Newsletter Segments
 * Manage audience segments for targeted newsletter campaigns.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button } from '@heroui/react';
import { Filter, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminNewsletters } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';

interface Segment {
  id: number;
  name: string;
  description: string;
  criteria: string;
  subscriber_count: number;
  created_at: string;
}

export function Segments() {
  usePageTitle('Admin - Segments');
  const [items, setItems] = useState<Segment[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getSegments();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: Segment[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<Segment>[] = [
    { key: 'name', label: 'Segment Name', sortable: true },
    { key: 'description', label: 'Description' },
    {
      key: 'subscriber_count', label: 'Subscribers',
      render: (item) => <span>{(item.subscriber_count || 0).toLocaleString()}</span>,
    },
    {
      key: 'created_at', label: 'Created', sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title="Segments" description="Audience segments for targeted campaigns" />
        <EmptyState
          icon={Filter}
          title="No Segments Created"
          description="Create audience segments to target specific groups of subscribers with tailored content."
        />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Segments"
        description="Audience segments for targeted campaigns"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />
    </div>
  );
}

export default Segments;
