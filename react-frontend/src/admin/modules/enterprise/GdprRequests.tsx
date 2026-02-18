/**
 * GDPR Requests
 * DataTable of GDPR requests with status filter and update action.
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Button,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Select,
  SelectItem,
} from '@heroui/react';
import { RefreshCw, MoreVertical } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable, StatusBadge } from '../../components';
import type { Column } from '../../components';
import type { GdprRequest } from '../../api/types';

const STATUS_OPTIONS = [
  { value: 'all', label: 'All Statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'processing', label: 'Processing' },
  { value: 'completed', label: 'Completed' },
  { value: 'rejected', label: 'Rejected' },
];

export function GdprRequests() {
  usePageTitle('Admin - GDPR Data Requests');
  const toast = useToast();

  const [requests, setRequests] = useState<GdprRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [statusFilter, setStatusFilter] = useState('all');

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprRequests({
        page,
        status: statusFilter !== 'all' ? statusFilter : undefined,
      });
      if (res.success && res.data) {
        const result = res.data as unknown;
        if (Array.isArray(result)) {
          setRequests(result);
          setTotal(result.length);
        } else if (result && typeof result === 'object') {
          const pd = result as { data?: GdprRequest[]; meta?: { total?: number } };
          setRequests(pd.data || []);
          setTotal(pd.meta?.total ?? pd.data?.length ?? 0);
        }
      }
    } catch {
      toast.error('Failed to load GDPR requests');
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleStatusUpdate = async (id: number, newStatus: string) => {
    try {
      const res = await adminEnterprise.updateGdprRequest(id, { status: newStatus });

      if (res.success) {
        toast.success(`Request updated to ${newStatus}`);
        loadData();
      } else {
        const error = (res as { error?: string }).error || 'Update failed';
        toast.error(error);
      }
    } catch (err) {
      toast.error('Failed to update request');
      console.error('GDPR request update error:', err);
    }
  };

  const columns: Column<GdprRequest>[] = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'user_name', label: 'User', sortable: true },
    {
      key: 'type',
      label: 'Type',
      sortable: true,
      render: (r) => <span className="capitalize">{r.type}</span>,
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (r) => <StatusBadge status={r.status} />,
    },
    {
      key: 'created_at',
      label: 'Created',
      sortable: true,
      render: (r) => new Date(r.created_at).toLocaleDateString(),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (r) => (
        <Dropdown>
          <DropdownTrigger>
            <Button isIconOnly size="sm" variant="light" aria-label="Actions">
              <MoreVertical size={14} />
            </Button>
          </DropdownTrigger>
          <DropdownMenu aria-label="Request actions">
            <DropdownItem key="processing" onPress={() => handleStatusUpdate(r.id, 'processing')}>
              Mark Processing
            </DropdownItem>
            <DropdownItem key="completed" onPress={() => handleStatusUpdate(r.id, 'completed')}>
              Mark Completed
            </DropdownItem>
            <DropdownItem key="rejected" className="text-danger" color="danger" onPress={() => handleStatusUpdate(r.id, 'rejected')}>
              Reject
            </DropdownItem>
          </DropdownMenu>
        </Dropdown>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Data Requests"
        description="GDPR data subject requests (access, deletion, portability, rectification)"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      <div className="mb-4">
        <Select
          label="Filter by status"
          selectedKeys={new Set([statusFilter])}
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0] as string;
            setStatusFilter(selected || 'all');
            setPage(1);
          }}
          className="max-w-xs"
          size="sm"
          variant="bordered"
        >
          {STATUS_OPTIONS.map((opt) => (
            <SelectItem key={opt.value}>{opt.label}</SelectItem>
          ))}
        </Select>
      </div>

      <DataTable
        columns={columns}
        data={requests}
        isLoading={loading}
        totalItems={total}
        page={page}
        onPageChange={setPage}
        searchable={false}
        emptyContent="No GDPR requests found"
      />
    </div>
  );
}

export default GdprRequests;
