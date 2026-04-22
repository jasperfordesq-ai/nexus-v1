// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Deliverables List
 * View all project deliverables with status and progress tracking.
 * Wired to adminDeliverability.list() API.
 */

import { useState, useEffect, useCallback } from 'react';
import { Button, Spinner, Card, CardBody } from '@heroui/react';
import { Target, Plus, Trash2, Pencil, AlertTriangle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
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
  usePageTitle("Deliverability");
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [data, setData] = useState<DeliverableItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [confirmDelete, setConfirmDelete] = useState<DeliverableItem | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
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
      } else {
        setLoadError("Failed to load deliverables");
      }
    } catch {
      setLoadError("Failed to load deliverables");
      toast.error("Failed to load deliverables");
    } finally {
      setLoading(false);
    }
  }, [toast]);


  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await adminDeliverability.delete(confirmDelete.id);
      if (res?.success) {
        toast.success("Deliverable deleted successfully");
        fetchData();
      } else {
        toast.error("Failed to delete deliverable");
      }
    } catch {
      toast.error("An unexpected error occurred");
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const columns: Column<DeliverableItem>[] = [
    {
      key: 'title',
      label: "Title",
      sortable: true,
      render: (item) => <span className="font-medium">{item.title}</span>,
    },
    {
      key: 'priority',
      label: "Priority",
      sortable: true,
      render: (item) => (
        <StatusBadge status={item.priority || 'medium'} />
      ),
    },
    {
      key: 'status',
      label: "Status",
      sortable: true,
      render: (item) => <StatusBadge status={item.status || 'planned'} />,
    },
    {
      key: 'assigned_to',
      label: "Assigned to",
      render: (item) => <span className="text-sm text-default-500">{item.assigned_to || '--'}</span>,
    },
    {
      key: 'due_date',
      label: "Due Date",
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.due_date ? new Date(item.due_date).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: "Actions",
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            onPress={() => navigate(tenantPath(`/admin/deliverability/edit/${item.id}`))}
            aria-label={"Edit Deliverable"}
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label={"Delete Deliverable"}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title={"Deliverables List"} description={"Create and track deliverables for your project milestones"} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={"Deliverables List"}
        description={"Create and track deliverables for your project milestones"}
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/deliverability/create'))}
          >
            {"Create Deliverable"}
          </Button>
        }
      />

      {loadError ? (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center gap-3 py-10 text-center">
            <AlertTriangle size={32} className="text-danger" />
            <div className="text-base font-semibold">{"Loading Data error"}</div>
            <div className="text-sm text-default-500">{loadError}</div>
            <Button color="primary" variant="flat" onPress={fetchData}>{"Retry"}</Button>
          </CardBody>
        </Card>
      ) : data.length === 0 ? (
        <EmptyState
          icon={Target}
          title={"No deliverables"}
          description={"Create deliverables to track project milestones and progress"}
          actionLabel={"Create Deliverable"}
          onAction={() => navigate(tenantPath('/admin/deliverability/create'))}
        />
      ) : (
        <DataTable
          columns={columns}
          data={data}
          searchPlaceholder={"Search Deliverables"}
          onRefresh={fetchData}
        />
      )}

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={"Delete Deliverable"}
          message={`Are you sure you want to delete this deliverable? This cannot be undone.`}
          confirmLabel={"Delete"}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default DeliverablesList;
