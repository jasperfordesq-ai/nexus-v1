// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Requests
 * DataTable of GDPR requests with status filter, SLA badges, and row navigation.
 */

import { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Select,
  SelectItem,
} from '@heroui/react';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import Plus from 'lucide-react/icons/plus';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable, StatusBadge } from '../../components';
import type { Column } from '../../components';
import type { GdprRequest } from '../../api/types';

import { useTranslation } from 'react-i18next';
const STATUS_OPTION_KEYS = ['all', 'pending', 'processing', 'completed', 'rejected'] as const;

function SlaChip({ createdAt, t }: { createdAt: string; t: (key: string, opts?: Record<string, unknown>) => string }) {
  const created = new Date(createdAt);
  const deadline = new Date(created.getTime() + 30 * 24 * 60 * 60 * 1000);
  const now = new Date();
  const diffMs = deadline.getTime() - now.getTime();
  const daysRemaining = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

  if (daysRemaining < 0) {
    const days = Math.abs(daysRemaining);
    return (
      <Chip size="sm" variant="flat" color="danger">
        {days === 1 ? `SLA Overdue` : `SLA Overdue Plural`}
      </Chip>
    );
  }
  if (daysRemaining <= 7) {
    return (
      <Chip size="sm" variant="flat" color="warning">
        {daysRemaining === 1 ? `SLA Days Left` : `SLA Days Left Plural`}
      </Chip>
    );
  }
  return (
    <Chip size="sm" variant="flat" color="success">
      {`SLA Days Left Plural`}
    </Chip>
  );
}

export function GdprRequests() {
  const { t } = useTranslation('admin');
  usePageTitle("Enterprise");
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

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
      toast.error("Failed to load GDPR requests");
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, toast])


  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleStatusUpdate = async (id: number, newStatus: string) => {
    try {
      const res = await adminEnterprise.updateGdprRequest(id, { status: newStatus });

      if (res.success) {
        toast.success(`Request Updated`);
        loadData();
      } else {
        const error = (res as { error?: string }).error || "Update Failed";
        toast.error(error);
      }
    } catch (err) {
      toast.error("Failed to update request");
      console.error('GDPR request update error:', err);
    }
  };

  const columns: Column<GdprRequest>[] = [
    { key: 'id', label: "ID", sortable: true },
    { key: 'user_name', label: "User", sortable: true },
    {
      key: 'type',
      label: "Type",
      sortable: true,
      render: (r) => <span className="capitalize">{r.type}</span>,
    },
    {
      key: 'status',
      label: "Status",
      sortable: true,
      render: (r) => <StatusBadge status={r.status} />,
    },
    {
      key: 'sla',
      label: "SLA",
      render: (r) => <SlaChip createdAt={r.created_at} t={t} />,
    },
    {
      key: 'created_at',
      label: "Created",
      sortable: true,
      render: (r) => new Date(r.created_at).toLocaleDateString(),
    },
    {
      key: 'actions',
      label: "Actions",
      render: (r) => (
        <Dropdown>
          <DropdownTrigger>
            <Button isIconOnly size="sm" variant="light" aria-label={"Actions"}>
              <MoreVertical size={14} />
            </Button>
          </DropdownTrigger>
          <DropdownMenu aria-label={"Request Actions"}>
            <DropdownItem key="view" onPress={() => navigate(tenantPath(`/admin/enterprise/gdpr/requests/${r.id}`))}>
              {"View Details"}
            </DropdownItem>
            <DropdownItem key="processing" onPress={() => handleStatusUpdate(r.id, 'processing')}>
              {"Mark Processing"}
            </DropdownItem>
            <DropdownItem key="completed" onPress={() => handleStatusUpdate(r.id, 'completed')}>
              {"Mark Completed"}
            </DropdownItem>
            <DropdownItem key="rejected" className="text-danger" color="danger" onPress={() => handleStatusUpdate(r.id, 'rejected')}>
              {"Reject"}
            </DropdownItem>
          </DropdownMenu>
        </Dropdown>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={"GDPR Requests"}
        description={"View and manage GDPR data requests (access, deletion, portability)"}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              {"Refresh"}
            </Button>
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/enterprise/gdpr/requests/create'))}
              size="sm"
            >
              {"Create Request"}
            </Button>
          </div>
        }
      />

      <div className="mb-4">
        <Select
          label={"Filter by Status"}
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
        emptyContent={"No GDPR requests"}
      />
    </div>
  );
}

export default GdprRequests;
