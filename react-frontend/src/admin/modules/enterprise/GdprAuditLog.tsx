// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Audit Log
 * Read-only activity log for GDPR actions.
 */

import { useEffect, useState, useCallback } from 'react';
import { Button } from '@heroui/react';
import { RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable } from '../../components';
import type { Column } from '../../components';
import type { GdprAuditEntry } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function GdprAuditLog() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const toast = useToast();

  const [entries, setEntries] = useState<GdprAuditEntry[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprAudit();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setEntries(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error(t('enterprise.failed_to_load_g_d_p_r_audit_log'));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const columns: Column<GdprAuditEntry>[] = [
    { key: 'id', label: t('enterprise.col_id'), sortable: true },
    { key: 'user_name', label: t('enterprise.col_user'), sortable: true },
    { key: 'action', label: t('enterprise.col_action'), sortable: true },
    { key: 'description', label: t('enterprise.col_description') },
    {
      key: 'created_at',
      label: t('enterprise.col_date'),
      sortable: true,
      render: (e) => new Date(e.created_at).toLocaleString(),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('enterprise.gdpr_audit_log_title')}
        description={t('enterprise.gdpr_audit_log_desc')}
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
        data={entries}
        isLoading={loading}
        searchable={false}
        emptyContent={t('enterprise.no_gdpr_audit_entries')}
      />
    </div>
  );
}

export default GdprAuditLog;
