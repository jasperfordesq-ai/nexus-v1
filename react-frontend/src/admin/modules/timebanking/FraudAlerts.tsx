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
import { useAdminPageMeta } from '../../AdminMetaContext';
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
  const { t: tNav } = useTranslation('admin_nav');
  const { t } = useTranslation('admin');
  useAdminPageMeta({ title: tNav('timebanking') });
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
      toast.error(t('timebanking.failed_to_load_alerts'));
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, t, toast])


  useEffect(() => {
    loadAlerts();
  }, [loadAlerts]);

  const handleStatusChange = useCallback(
    async (alertId: number, newStatus: string) => {
      try {
        const res = await adminTimebanking.updateAlertStatus(alertId, newStatus);
        if (res.success) {
          toast.success(t('timebanking.alert_status_updated'));
          loadAlerts();
        } else {
          toast.error(t('timebanking.failed_to_update_alert_status'));
        }
      } catch {
        toast.error(t('timebanking.failed_to_update_alert_status'));
      }
    },
    [loadAlerts, t, toast]
  );

  const handleTabChange = useCallback((key: React.Key) => {
    setStatusFilter(String(key));
    setPage(1);
  }, [])

  const columns: Column<FraudAlert>[] = useMemo(
    () => [
      {
        key: 'user_name',
        label: t('timebanking.col_user'),
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
        label: t('timebanking.col_alert_type'),
        sortable: true,
        render: (alert) => (
          <span className="text-sm capitalize">
            {alert.alert_type.replace(/_/g, ' ')}
          </span>
        ),
      },
      {
        key: 'severity',
        label: t('timebanking.col_severity'),
        sortable: true,
        render: (alert) => (
          <Chip
            size="sm"
            variant="flat"
            color={SEVERITY_COLOR_MAP[alert.severity] || 'default'}
            className="capitalize"
          >
            {t(`timebanking.severity_${alert.severity}`)}
          </Chip>
        ),
      },
      {
        key: 'status',
        label: t('timebanking.col_status'),
        sortable: true,
        render: (alert) => (
          <Chip
            size="sm"
            variant="flat"
            color={STATUS_COLOR_MAP[alert.status] || 'default'}
            className="capitalize"
          >
            {t(`timebanking.status_${alert.status}`)}
          </Chip>
        ),
      },
      {
        key: 'created_at',
        label: t('timebanking.col_date'),
        sortable: true,
        render: (alert) => (
          <span className="text-sm text-default-500">
            {new Date(alert.created_at).toLocaleDateString()}
          </span>
        ),
      },
      {
        key: 'actions',
        label: t('timebanking.label_actions'),
        render: (alert) => (
          <Dropdown>
            <DropdownTrigger>
              <Button isIconOnly size="sm" variant="light" aria-label={t('timebanking.label_actions')}>
                <MoreVertical size={16} />
              </Button>
            </DropdownTrigger>
            <DropdownMenu
              aria-label={t('timebanking.label_alert_actions')}
              onAction={(key) => handleStatusChange(alert.id, String(key))}
              disabledKeys={[alert.status]}
            >
              <DropdownItem key="reviewing" description={t('timebanking.desc_mark_as_under_investigation')}>
                {t('timebanking.action_investigate')}
              </DropdownItem>
              <DropdownItem key="resolved" description={t('timebanking.desc_mark_as_resolved')} className="text-success">
                {t('timebanking.action_resolve')}
              </DropdownItem>
              <DropdownItem key="dismissed" description={t('timebanking.desc_dismiss_this_alert')} className="text-default-400">
                {t('timebanking.action_dismiss')}
              </DropdownItem>
            </DropdownMenu>
          </Dropdown>
        ),
      },
    ],
    [tenantPath, handleStatusChange, t]
  );

  return (
    <div>
      <PageHeader
        title={t('timebanking.fraud_alerts_title')}
        description={t('timebanking.fraud_alerts_desc')}
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/timebanking')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            {t('timebanking.back_to_timebanking')}
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
          aria-label={t('timebanking.label_filter_by_status')}
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
            <p className="text-sm text-default-400">{t('timebanking.no_fraud_alerts')}</p>
          </div>
        }
      />
    </div>
  );
}

export default FraudAlerts;
