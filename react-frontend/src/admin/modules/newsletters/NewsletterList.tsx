/**
 * Newsletter List
 * Displays all newsletters with status filtering and CRUD actions.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button } from '@heroui/react';
import { Mail, Plus, RefreshCw } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { adminNewsletters } from '../../api/adminApi';
import { DataTable, PageHeader, StatusBadge, type Column } from '../../components';

interface NewsletterItem {
  id: number;
  name: string;
  subject: string;
  status: string;
  recipients_count: number;
  open_rate: number;
  click_rate: number;
  sent_at: string | null;
  created_at: string;
}

export function NewsletterList() {
  usePageTitle('Admin - Newsletters');
  const navigate = useNavigate();
  const [items, setItems] = useState<NewsletterItem[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.list({ page });
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
          setTotal(payload.length);
        } else if (payload && typeof payload === 'object') {
          const p = payload as { data?: NewsletterItem[]; meta?: { total?: number } };
          setItems(p.data || []);
          setTotal(p.meta?.total || 0);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, [page]);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<NewsletterItem>[] = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'subject', label: 'Subject', sortable: true },
    {
      key: 'status', label: 'Status', sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'recipients_count', label: 'Recipients',
      render: (item) => <span>{(item.recipients_count || 0).toLocaleString()}</span>,
    },
    {
      key: 'open_rate', label: 'Open Rate',
      render: (item) => <span>{item.open_rate ? `${item.open_rate}%` : '--'}</span>,
    },
    {
      key: 'created_at', label: 'Created', sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
  ];

  return (
    <div>
      <PageHeader
        title="Newsletters"
        description="Email campaign management"
        actions={
          <div className="flex gap-2">
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>
            <Button color="primary" startContent={<Plus size={16} />} onPress={() => navigate('../newsletters/create')}>Create Newsletter</Button>
          </div>
        }
      />
      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder="Search newsletters..."
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        onRefresh={loadData}
        emptyContent={
          <div className="flex flex-col items-center gap-2 py-8 text-default-400">
            <Mail size={40} />
            <p>No newsletters found</p>
            <p className="text-xs">Create your first newsletter to get started</p>
          </div>
        }
      />
    </div>
  );
}

export default NewsletterList;
