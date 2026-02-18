/**
 * Volunteer Organizations
 * Lists organizations participating in the volunteering program.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button } from '@heroui/react';
import { Building2, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';

interface VolOrg {
  id: number;
  org_id: number;
  org_name: string;
  balance: number;
  total_in: number;
  total_out: number;
  member_count: number;
  created_at: string;
}

export function VolunteerOrganizations() {
  usePageTitle('Admin - Volunteer Organizations');
  const toast = useToast();
  const [items, setItems] = useState<VolOrg[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getOrganizations();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: VolOrg[] }).data || []);
        }
      }
    } catch {
      toast.error('Failed to load organizations');
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<VolOrg>[] = [
    { key: 'org_name', label: 'Organization', sortable: true },
    { key: 'member_count', label: 'Members', sortable: true },
    {
      key: 'balance', label: 'Balance',
      render: (item) => <span>{item.balance?.toLocaleString() ?? 0} hrs</span>,
    },
    {
      key: 'created_at', label: 'Created', sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title="Volunteer Organizations" description="Organizations in the volunteering program" />
        <EmptyState icon={Building2} title="No Organizations" description="No volunteer organizations have been created yet." />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Volunteer Organizations"
        description="Organizations in the volunteering program"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />
    </div>
  );
}

export default VolunteerOrganizations;
