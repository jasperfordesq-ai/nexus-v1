// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Fraud Alerts Management
 * View and manage abuse detection alerts with status filtering and actions.
 * Parity: PHP Admin\TimebankingController::alerts()
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import {
  Chip,
  Tabs,
  Tab,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Button,
} from '@heroui/react';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminTimebanking } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { FraudAlert } from '../../api/types';

import { useTranslation } from 'react-i18next';
const SEVERITY_COLOR_MAP: Record<string, 'default' | 'primary' | 'warning' | 'danger'> = {
  low: 'default',
  medium: 'primary',
  high: 'warning',
  critical: 'danger',
};

const STATUS_COLOR_MAP: Record<string, 'default' | 'primary' | 'success' | 'warning'> = {
  new: 'warning',
  reviewing: 'primary',
  resolved: 'success',
  dismissed: 'default',
};

const STATUS_TAB_KEYS = ['all', 'new', 'reviewing', 'resolved', 'dismissed'] as const;

export function FraudAlerts() {
  const { t } = useTranslation('admin');
  usePageTitle("Timebanking");
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [alerts, setAlerts] = useState<FraudAlert[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('all');

  const loadAlerts = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTimebanking.getAlerts({
        status: statusFilter === 'all' ? undefined : statusFilter,
        page,
      });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setAlerts(data);
          const metaTotal = (res.meta as Record<string, unknown> | undefined)?.total;
          setTotal(typeof metaTotal === 'number' ? metaTotal : data.length);
        } else if (data && typeof data === 'object') {
          const paginatedData = data as { data: FraudAlert[]; meta?: { total: number } };
          setAlerts(paginatedData.data || []);
          setTotal(paginatedData.meta?.total || 0);
        }
      }
    } catch {
      toast.error("Failed to load alerts");
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, toast])


  useEffect(() => {
    loadAlerts();
  }, [loadAlerts]);

  const handleStatusChange = useCallback(
    async (alertId: number, newStatus: string) => {
      try {
        const res = await adminTimebanking.updateAlertStatus(alertId, newStatus);
        if (res.success) {
          toast.success(`Alert status updated`);
          loadAlerts();
        } else {
          toast.error("Failed to update alert status");
        }
      } catch {
        toast.error("Failed to update alert status");
      }
    },
    [loadAlerts, toast
  );

  const handleTabChange = useCallback((key: React.Key) => {
    setStatusFilter(String(key));
    setPage(1);
  }, [])

  const columns: Column<FraudAlert>[] = useMemo(
    () => [
      {
        key: 'user_name',
        label: "User",
        render: (alert) => (
          <Link
            to={tenantPath(`/admin/users/${alert.user_id}/edit`)}
            className="text-sm font-medium hover:text-primary transition-colors"
          >
            {alert.user_name}
          </Link>
        ),
      },
      {
        key: 'alert_type',
        label: "Alert Type",
        sortable: true,
        render: (alert) => (
          <span className="text-sm capitalize">
            {alert.alert_type.replace(/_/g, ' ')}
          </span>
        ),
      },
      {
        key: 'severity',
        label: "Severity",
        sortable: true,
        render: (alert) => (
          <Chip
            size="sm"
            variant="flat"
            color={SEVERITY_COLOR_MAP[alert.severity] || 'default'}
            className="capitalize"
          >
            {alert.severity}
          </Chip>
        ),
      },
      {
        key: 'status',
        label: "Status",
        sortable: true,
        render: (alert) => (
          <Chip
            size="sm"
            variant="flat"
            color={STATUS_COLOR_MAP[alert.status] || 'default'}
            className="capitalize"
          >
            {alert.status}
          </Chip>
        ),
      },
      {
        key: 'created_at',
        label: "Date",
        sortable: true,
        render: (alert) => (
          <span className="text-sm text-default-500">
            {new Date(alert.created_at).toLocaleDateString()}
          </span>
        ),
      },
      {
        key: 'actions',
        label: "Actions",
        render: (alert) => (
          <Dropdown>
            <DropdownTrigger>
              <Button isIconOnly size="sm" variant="light" aria-label={"Actions"}>
                <MoreVertical size={16} />
              </Button>
            </DropdownTrigger>
            <DropdownMenu
              aria-label={"Alert Actions"}
              onAction={(key) => handleStatusChange(alert.id, String(key))}
              disabledKeys={[alert.status]}
            >
              <DropdownItem key="reviewing" description={"Mark as Under Investigation"}>
                {"Investigate"}
              </DropdownItem>
              <DropdownItem key="resolved" description={"Mark as Resolved"} className="text-success">
                {"Resolve"}
              </DropdownItem>
              <DropdownItem key="dismissed" description={"Dismiss This Alert"} className="text-default-400">
                {"Dismiss"}
              </DropdownItem>
            </DropdownMenu>
          </Dropdown>
        ),
      },
    ],
    [tenantPath, handleStatusChange
  );

  return (
    <div>
      <PageHeader
        title={"Fraud Alerts"}
        description={"View and manage fraud alerts generated by suspicious transaction patterns"}
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/timebanking')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            {"Back to Timebanking"}
          </Button>
        }
      />

      {/* Status Tabs */}
      <div className="mb-4">
        <Tabs
          selectedKey={statusFilter}
          onSelectionChange={handleTabChange}
          size="sm"
          variant="underlined"
          aria-label={"Filter by Status"}
        >
          {STATUS_TAB_KEYS.map((key) => (
            <Tab key={key} title={t(`timebanking.tab_${key}`)} />
          ))}
        </Tabs>
      </div>

      {/* Alerts Table */}
      <DataTable<FraudAlert>
        columns={columns}
        data={alerts}
        isLoading={loading}
        searchable={false}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        onRefresh={loadAlerts}
        emptyContent={
          <div className="flex flex-col items-center gap-2 py-8">
            <AlertTriangle size={32} className="text-default-300" />
            <p className="text-sm text-default-400">{"No fraud alerts"}</p>
          </div>
        }
      />
    </div>
  );
}

export default FraudAlerts;
