// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Consents
 * Read-only DataTable of consent records.
 */

import { useEffect, useState, useCallback } from 'react';
import { Button, Chip } from '@heroui/react';
import { RefreshCw, CheckCircle, XCircle } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable } from '../../components';
import type { Column } from '../../components';
import type { GdprConsent } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function GdprConsents() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const toast = useToast();

  const [consents, setConsents] = useState<GdprConsent[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprConsents();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setConsents(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error(t('enterprise.failed_to_load_consent_records'));
    } finally {
      setLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    loadData();
  }, [loadData]);

  const columns: Column<GdprConsent>[] = [
    { key: 'id', label: t('enterprise.col_id'), sortable: true },
    { key: 'user_name', label: t('enterprise.col_user'), sortable: true },
    {
      key: 'consent_type',
      label: t('enterprise.col_type'),
      sortable: true,
      render: (c) => (
        <Chip size="sm" variant="flat" color="primary" className="capitalize">
          {c.consent_type}
        </Chip>
      ),
    },
    {
      key: 'consented',
      label: t('enterprise.col_consented'),
      render: (c) =>
        c.consented ? (
          <div className="flex items-center gap-1 text-success">
            <CheckCircle size={14} />
            <span className="text-sm">{t('enterprise.yes')}</span>
          </div>
        ) : (
          <div className="flex items-center gap-1 text-danger">
            <XCircle size={14} />
            <span className="text-sm">{t('enterprise.no')}</span>
          </div>
        ),
    },
    {
      key: 'created_at',
      label: t('enterprise.col_date'),
      sortable: true,
      render: (c) => new Date(c.consented_at || c.created_at).toLocaleDateString(),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('enterprise.gdpr_consents_title')}
        description={t('enterprise.gdpr_consents_desc')}
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
        data={consents}
        isLoading={loading}
        searchable={false}
        emptyContent={t('enterprise.no_consent_records')}
      />
    </div>
  );
}

export default GdprConsents;
