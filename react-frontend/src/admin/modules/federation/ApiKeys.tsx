/**
 * Federation API Keys
 * Manage API keys for federation integrations.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button, Chip } from '@heroui/react';
import { Key, Plus, RefreshCw } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { adminFederation } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';

interface ApiKey {
  id: number;
  name: string;
  key_prefix: string;
  status: string;
  scopes: string[];
  last_used_at: string | null;
  expires_at: string | null;
  created_at: string;
}

export function ApiKeys() {
  usePageTitle('Admin - Federation API Keys');
  const navigate = useNavigate();
  const [items, setItems] = useState<ApiKey[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getApiKeys();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: ApiKey[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<ApiKey>[] = [
    { key: 'name', label: 'Key Name', sortable: true },
    {
      key: 'key_prefix', label: 'Prefix',
      render: (item) => <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">{item.key_prefix}...</code>,
    },
    {
      key: 'status', label: 'Status',
      render: (item) => (
        <Chip size="sm" variant="flat" color={item.status === 'active' ? 'success' : 'danger'} className="capitalize">{item.status}</Chip>
      ),
    },
    {
      key: 'scopes', label: 'Scopes',
      render: (item) => <span className="text-sm text-default-500">{Array.isArray(item.scopes) ? item.scopes.join(', ') : '--'}</span>,
    },
    {
      key: 'last_used_at', label: 'Last Used',
      render: (item) => <span className="text-sm text-default-500">{item.last_used_at ? new Date(item.last_used_at).toLocaleDateString() : 'Never'}</span>,
    },
    {
      key: 'created_at', label: 'Created', sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader
          title="API Keys"
          description="Federation integration API keys"
          actions={<Button color="primary" startContent={<Plus size={16} />} onPress={() => navigate('../federation/api-keys/create')}>Create Key</Button>}
        />
        <EmptyState icon={Key} title="No API Keys" description="Create an API key to enable federation integrations." actionLabel="Create API Key" onAction={() => navigate('../federation/api-keys/create')} />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="API Keys"
        description="Federation integration API keys"
        actions={
          <div className="flex gap-2">
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>
            <Button color="primary" startContent={<Plus size={16} />} onPress={() => navigate('../federation/api-keys/create')}>Create Key</Button>
          </div>
        }
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />
    </div>
  );
}

export default ApiKeys;
