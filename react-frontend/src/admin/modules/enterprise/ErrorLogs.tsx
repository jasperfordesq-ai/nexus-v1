// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Error Logs
 * DataTable of recent error log entries.
 */

import { useEffect, useState, useCallback } from 'react';
import { Button, Chip } from '@heroui/react';
import { RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable } from '../../components';
import type { Column } from '../../components';
import type { ErrorLogEntry } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function ErrorLogs() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const toast = useToast();

  const [logs, setLogs] = useState<ErrorLogEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getLogs({ page });
      if (res.success && res.data) {
        const result = res.data as unknown;
        if (Array.isArray(result)) {
          setLogs(result);
          setTotal(result.length);
        } else if (result && typeof result === 'object') {
          const pd = result as { data?: ErrorLogEntry[]; meta?: { total?: number } };
          setLogs(pd.data || []);
          setTotal(pd.meta?.total ?? pd.data?.length ?? 0);
        }
      }
    } catch {
      toast.error(t('enterprise.failed_to_load_error_logs'));
    } finally {
      setLoading(false);
    }
  }, [page, toast, t])

  useEffect(() => {
    loadData();
  }, [loadData]);

  const columns: Column<ErrorLogEntry>[] = [
    { key: 'id', label: t('enterprise.col_id'), sortable: true },
    {
      key: 'action',
      label: t('enterprise.col_action'),
      sortable: true,
      render: (entry) => (
        <Chip size="sm" variant="flat" color="danger">
          {entry.action}
        </Chip>
      ),
    },
    { key: 'description', label: t('enterprise.col_description') },
    {
      key: 'user_name',
      label: t('enterprise.col_user'),
      render: (entry) => entry.user_name || '---',
    },
    {
      key: 'ip_address',
      label: t('enterprise.col_ip'),
      render: (entry) => (
        <span className="text-xs font-mono">{entry.ip_address || '---'}</span>
      ),
    },
    {
      key: 'created_at',
      label: t('enterprise.col_date'),
      sortable: true,
      render: (entry) => new Date(entry.created_at).toLocaleString(),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('enterprise.error_logs_title')}
        description={t('enterprise.error_logs_desc')}
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

      <DataTable
        columns={columns}
        data={logs}
        isLoading={loading}
        totalItems={total}
        page={page}
        onPageChange={setPage}
        searchable={false}
        emptyContent={t('enterprise.no_error_logs')}
      />
    </div>
  );
}

export default ErrorLogs;
