// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Guardian Consents
 * Admin read-only view of guardian consent records for minor volunteers.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button, Chip } from '@heroui/react';
import { ShieldCheck, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

interface GuardianConsent {
  id: number;
  minor_name: string;
  guardian_name: string;
  guardian_email: string;
  relationship: string;
  opportunity_title: string;
  status: 'pending' | 'active' | 'expired' | 'withdrawn';
  consent_date: string;
  expires_date: string | null;
}

const statusColorMap: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  active: 'success',
  pending: 'warning',
  expired: 'danger',
  withdrawn: 'default',
};

export default function VolunteerConsents() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.consents_title', 'Guardian Consents'));
  const toast = useToast();

  const [consents, setConsents] = useState<GuardianConsent[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getGuardianConsents();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setConsents(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setConsents((payload as { data: GuardianConsent[] }).data || []);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_consents', 'Failed to load guardian consents'));
      setConsents([]);
    }
    setLoading(false);
  }, [toast, t]);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<GuardianConsent>[] = [
    {
      key: 'minor_name',
      label: t('volunteering.col_minor_name', 'Minor Name'),
      sortable: true,
    },
    {
      key: 'guardian_name',
      label: t('volunteering.col_guardian_name', 'Guardian Name'),
      sortable: true,
    },
    {
      key: 'guardian_email',
      label: t('volunteering.col_guardian_email', 'Guardian Email'),
      sortable: true,
    },
    {
      key: 'relationship',
      label: t('volunteering.col_relationship', 'Relationship'),
    },
    {
      key: 'opportunity_title',
      label: t('volunteering.col_opportunity', 'Opportunity'),
      sortable: true,
    },
    {
      key: 'status',
      label: t('volunteering.col_status', 'Status'),
      sortable: true,
      render: (row) => (
        <Chip size="sm" color={statusColorMap[row.status] || 'default'} variant="flat">
          {t(`volunteering.status_${row.status}`, row.status)}
        </Chip>
      ),
    },
    {
      key: 'consent_date',
      label: t('volunteering.col_consent_date', 'Consent Date'),
      sortable: true,
      render: (row) => (
        <span>{row.consent_date ? new Date(row.consent_date).toLocaleDateString() : '-'}</span>
      ),
    },
    {
      key: 'expires_date',
      label: t('volunteering.col_expires_date', 'Expires'),
      sortable: true,
      render: (row) => (
        <span>{row.expires_date ? new Date(row.expires_date).toLocaleDateString() : '-'}</span>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('volunteering.consents_title', 'Guardian Consents')}
        description={t('volunteering.consents_desc', 'Monitor guardian consent records for minor volunteers. This is a read-only view.')}
        actions={
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
            {t('common.refresh', 'Refresh')}
          </Button>
        }
      />

      {consents.length === 0 && !loading ? (
        <EmptyState
          icon={ShieldCheck}
          title={t('volunteering.no_consents', 'No guardian consents')}
          description={t('volunteering.no_consents_desc', 'No guardian consent records have been created yet.')}
        />
      ) : (
        <DataTable columns={columns} data={consents} isLoading={loading} />
      )}
    </div>
  );
}
