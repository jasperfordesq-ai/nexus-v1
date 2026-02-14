/**
 * Newsletter Templates
 * Manage reusable email templates for newsletter campaigns.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button } from '@heroui/react';
import { FileText, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminNewsletters } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';

interface Template {
  id: number;
  name: string;
  subject: string;
  preview_text: string;
  created_at: string;
  updated_at: string;
}

export function Templates() {
  usePageTitle('Admin - Templates');
  const [items, setItems] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getTemplates();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: Template[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<Template>[] = [
    { key: 'name', label: 'Template Name', sortable: true },
    { key: 'subject', label: 'Default Subject' },
    {
      key: 'created_at', label: 'Created', sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title="Templates" description="Reusable email templates" />
        <EmptyState
          icon={FileText}
          title="No Templates Created"
          description="Create reusable email templates to speed up newsletter creation."
        />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Templates"
        description="Reusable email templates"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />
    </div>
  );
}

export default Templates;
