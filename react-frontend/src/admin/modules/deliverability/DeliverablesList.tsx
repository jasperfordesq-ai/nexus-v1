/**
 * Deliverables List
 * View all project deliverables with status and progress tracking.
 * Wired to adminDeliverability.list() API.
 */

import { useState, useEffect, useCallback } from 'react';
import { Button, Spinner } from '@heroui/react';
import { Target, Plus, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminDeliverability } from '../../api/adminApi';
import { PageHeader, DataTable, StatusBadge, EmptyState, ConfirmModal, type Column } from '../../components';

interface DeliverableItem {
  id: number;
  title: string;
  description: string;
  priority: string;
  status: string;
  assigned_to: string;
  due_date: string;
  created_at: string;
}

export function DeliverablesList() {
  usePageTitle('Admin - All Deliverables');
  const toast = useToast();
  const navigate = useNavigate();

  const [data, setData] = useState<DeliverableItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState<DeliverableItem | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminDeliverability.list();
      if (res.success && res.data) {
        const result = res.data as unknown;
        if (Array.isArray(result)) {
          setData(result);
        } else if (result && typeof result === 'object') {
          const pd = result as { data?: DeliverableItem[] };
          setData(pd.data || []);
        }
      }
    } catch {
      toast.error('Failed to load deliverables');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await adminDeliverability.delete(confirmDelete.id);
      if (res?.success) {
        toast.success('Deliverable deleted successfully');
        fetchData();
      } else {
        toast.error('Failed to delete deliverable');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const columns: Column<DeliverableItem>[] = [
    {
      key: 'title',
      label: 'Title',
      sortable: true,
      render: (item) => <span className="font-medium">{item.title}</span>,
    },
    {
      key: 'priority',
      label: 'Priority',
      sortable: true,
      render: (item) => (
        <StatusBadge status={item.priority || 'medium'} />
      ),
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (item) => <StatusBadge status={item.status || 'planned'} />,
    },
    {
      key: 'assigned_to',
      label: 'Assigned To',
      render: (item) => <span className="text-sm text-default-500">{item.assigned_to || '--'}</span>,
    },
    {
      key: 'due_date',
      label: 'Due Date',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.due_date ? new Date(item.due_date).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <Button
          isIconOnly
          size="sm"
          variant="flat"
          color="danger"
          onPress={() => setConfirmDelete(item)}
          aria-label="Delete deliverable"
        >
          <Trash2 size={14} />
        </Button>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title="All Deliverables" description="Project deliverables and milestone tracking" />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="All Deliverables"
        description="Project deliverables and milestone tracking"
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate('../deliverability/create')}
          >
            Create Deliverable
          </Button>
        }
      />

      {data.length === 0 ? (
        <EmptyState
          icon={Target}
          title="No Deliverables"
          description="Create deliverables to track project milestones and progress."
          actionLabel="Create Deliverable"
          onAction={() => navigate('../deliverability/create')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={data}
          searchPlaceholder="Search deliverables..."
          onRefresh={fetchData}
        />
      )}

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title="Delete Deliverable"
          message={`Are you sure you want to delete "${confirmDelete.title}"? This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default DeliverablesList;
