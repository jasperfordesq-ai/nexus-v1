// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Organizations
 * Lists organizations participating in the volunteering program.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button } from '@heroui/react';
import { Building2, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';

import { useTranslation } from 'react-i18next';
interface VolOrg {
  id: number;
  org_id: number;
  org_name: string;
  balance: number;
  total_in: number;
  total_out: number;
  member_count: number;
  created_at: string;
}

export function VolunteerOrganizations() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.page_title'));
  const toast = useToast();
  const [items, setItems] = useState<VolOrg[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getOrganizations();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: VolOrg[] }).data || []);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_organizations'));
      setItems([]);
    }
    setLoading(false);
  }, [toast, t]);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<VolOrg>[] = [
    { key: 'org_name', label: t('volunteering.col_organization'), sortable: true },
    { key: 'member_count', label: t('volunteering.col_members'), sortable: true },
    {
      key: 'balance', label: t('volunteering.col_balance'),
      render: (item) => <span>{item.balance?.toLocaleString() ?? 0} hrs</span>,
    },
    {
      key: 'created_at', label: t('volunteering.col_created'), sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title={t('volunteering.volunteer_organizations_title')} description={t('volunteering.volunteer_organizations_desc')} />
        <EmptyState icon={Building2} title={t('volunteering.no_organizations')} description={t('volunteering.desc_no_volunteer_organizations_have_been_cre')} />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('volunteering.volunteer_organizations_title')}
        description={t('volunteering.volunteer_organizations_desc')}
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{t('common.refresh')}</Button>}
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />
    </div>
  );
}

export default VolunteerOrganizations;
