/**
 * Newsletter Subscribers
 * Lists all newsletter subscribers (users opted in to email campaigns).
 */

import { useState, useCallback, useEffect } from 'react';
import { Button, Avatar } from '@heroui/react';
import { Users, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminNewsletters } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';

interface Subscriber {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  status: string;
  created_at: string;
}

export function Subscribers() {
  usePageTitle('Admin - Subscribers');
  const [items, setItems] = useState<Subscriber[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getSubscribers();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: Subscriber[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<Subscriber>[] = [
    {
      key: 'name', label: 'Subscriber', sortable: true,
      render: (item) => (
        <div className="flex items-center gap-3">
          <Avatar name={`${item.first_name} ${item.last_name}`} size="sm" />
          <div>
            <p className="font-medium">{item.first_name} {item.last_name}</p>
            <p className="text-xs text-default-400">{item.email}</p>
          </div>
        </div>
      ),
    },
    { key: 'status', label: 'Status', sortable: true },
    {
      key: 'created_at', label: 'Joined', sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
  ];

  return (
    <div>
      <PageHeader
        title="Subscribers"
        description="Newsletter subscriber management"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />
      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder="Search subscribers..."
        onRefresh={loadData}
        emptyContent={
          <div className="flex flex-col items-center gap-2 py-8 text-default-400">
            <Users size={40} />
            <p>No subscribers found</p>
          </div>
        }
      />
    </div>
  );
}

export default Subscribers;
