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
import { Button, Spinner } from '@heroui/react';
import { Target, Plus, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminDeliverability } from '../../api/adminApi';
import { PageHeader, DataTable, StatusBadge, EmptyState, ConfirmModal, type Column } from '../../components';

import { useTranslation } from 'react-i18next';
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
  const { t } = useTranslation('admin');
  usePageTitle(t('deliverability.page_title'));
  const { tenantPath } = useTenant();
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
      toast.error(t('deliverability.failed_to_load_deliverables'));
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
        toast.success(t('deliverability.deliverable_deleted_successfully'));
        fetchData();
      } else {
        toast.error(t('deliverability.failed_to_delete_deliverable'));
      }
    } catch {
      toast.error(t('deliverability.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const columns: Column<DeliverableItem>[] = [
    {
      key: 'title',
      label: t('deliverability.col_title'),
      sortable: true,
      render: (item) => <span className="font-medium">{item.title}</span>,
    },
    {
      key: 'priority',
      label: t('deliverability.col_priority'),
      sortable: true,
      render: (item) => (
        <StatusBadge status={item.priority || 'medium'} />
      ),
    },
    {
      key: 'status',
      label: t('deliverability.col_status'),
      sortable: true,
      render: (item) => <StatusBadge status={item.status || 'planned'} />,
    },
    {
      key: 'assigned_to',
      label: t('deliverability.col_assigned_to'),
      render: (item) => <span className="text-sm text-default-500">{item.assigned_to || '--'}</span>,
    },
    {
      key: 'due_date',
      label: t('deliverability.col_due_date'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.due_date ? new Date(item.due_date).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: t('deliverability.col_actions'),
      render: (item) => (
        <div className="flex gap-1">
          {/* TODO: Edit deliverable page not yet implemented (backend PUT /v2/admin/deliverability/{id} exists) */}
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label={t('deliverability.label_delete_deliverable')}
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
        <PageHeader title={t('deliverability.deliverables_list_title')} description={t('deliverability.deliverables_list_desc')} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('deliverability.deliverables_list_title')}
        description={t('deliverability.deliverables_list_desc')}
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate(tenantPath('/admin/deliverability/create'))}
          >
            {t('deliverability.create_deliverable')}
          </Button>
        }
      />

      {data.length === 0 ? (
        <EmptyState
          icon={Target}
          title={t('deliverability.no_deliverables')}
          description={t('deliverability.desc_create_deliverables_to_track_project_mil')}
          actionLabel={t('deliverability.create_deliverable')}
          onAction={() => navigate(tenantPath('/admin/deliverability/create'))}
        />
      ) : (
        <DataTable
          columns={columns}
          data={data}
          searchPlaceholder={t('deliverability.search_deliverables')}
          onRefresh={fetchData}
        />
      )}

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={t('deliverability.delete_deliverable_title')}
          message={t('deliverability.delete_deliverable_message', { title: confirmDelete.title })}
          confirmLabel={t('advanced.delete')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default DeliverablesList;
