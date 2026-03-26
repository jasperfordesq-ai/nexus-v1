// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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

import { useTranslation } from 'react-i18next';
const STATUS_OPTION_KEYS = ['all', 'pending', 'processing', 'completed', 'rejected'] as const;

export function GdprRequests() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
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
      toast.error(t('enterprise.failed_to_load_g_d_p_r_requests'));
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
        toast.success(t('enterprise.request_updated', { status: newStatus }));
        loadData();
      } else {
        const error = (res as { error?: string }).error || t('enterprise.update_failed');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('enterprise.failed_to_update_request'));
      console.error('GDPR request update error:', err);
    }
  };

  const columns: Column<GdprRequest>[] = [
    { key: 'id', label: t('enterprise.col_id'), sortable: true },
    { key: 'user_name', label: t('enterprise.col_user'), sortable: true },
    {
      key: 'type',
      label: t('enterprise.col_type'),
      sortable: true,
      render: (r) => <span className="capitalize">{r.type}</span>,
    },
    {
      key: 'status',
      label: t('enterprise.col_status'),
      sortable: true,
      render: (r) => <StatusBadge status={r.status} />,
    },
    {
      key: 'created_at',
      label: t('enterprise.col_created'),
      sortable: true,
      render: (r) => new Date(r.created_at).toLocaleDateString(),
    },
    {
      key: 'actions',
      label: t('enterprise.col_actions'),
      render: (r) => (
        <Dropdown>
          <DropdownTrigger>
            <Button isIconOnly size="sm" variant="light" aria-label={t('enterprise.label_actions')}>
              <MoreVertical size={14} />
            </Button>
          </DropdownTrigger>
          <DropdownMenu aria-label={t('enterprise.label_request_actions')}>
            <DropdownItem key="processing" onPress={() => handleStatusUpdate(r.id, 'processing')}>
              {t('enterprise.mark_processing')}
            </DropdownItem>
            <DropdownItem key="completed" onPress={() => handleStatusUpdate(r.id, 'completed')}>
              {t('enterprise.mark_completed')}
            </DropdownItem>
            <DropdownItem key="rejected" className="text-danger" color="danger" onPress={() => handleStatusUpdate(r.id, 'rejected')}>
              {t('enterprise.reject')}
            </DropdownItem>
          </DropdownMenu>
        </Dropdown>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('enterprise.gdpr_requests_title')}
        description={t('enterprise.gdpr_requests_desc')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            {t('common.refresh')}
          </Button>
        }
      />

      <div className="mb-4">
        <Select
          label={t('enterprise.label_filter_by_status')}
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
          {STATUS_OPTION_KEYS.map((key) => (
            <SelectItem key={key}>{t(`enterprise.status_${key}`)}</SelectItem>
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
        emptyContent={t('enterprise.no_gdpr_requests')}
      />
    </div>
  );
}

export default GdprRequests;
