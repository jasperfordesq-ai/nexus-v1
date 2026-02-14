/**
 * Volunteer Approvals
 * Lists pending volunteer applications requiring admin review.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button, Avatar } from '@heroui/react';
import { ClipboardCheck, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, StatusBadge, type Column } from '../../components';

interface VolApplication {
  id: number;
  user_id: number;
  first_name: string;
  last_name: string;
  email: string;
  opportunity_title: string;
  status: string;
  created_at: string;
}

export function VolunteerApprovals() {
  usePageTitle('Admin - Volunteer Approvals');
  const [items, setItems] = useState<VolApplication[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getApprovals();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: VolApplication[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<VolApplication>[] = [
    {
      key: 'applicant', label: 'Applicant', sortable: true,
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
    { key: 'opportunity_title', label: 'Opportunity', sortable: true },
    {
      key: 'status', label: 'Status',
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'created_at', label: 'Applied', sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title="Volunteer Approvals" description="Review pending volunteer applications" />
        <EmptyState icon={ClipboardCheck} title="No Pending Approvals" description="All volunteer applications have been reviewed." />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Volunteer Approvals"
        description="Review pending volunteer applications"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />
    </div>
  );
}

export default VolunteerApprovals;
