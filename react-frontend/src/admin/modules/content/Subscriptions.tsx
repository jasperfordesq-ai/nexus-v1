// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Subscriptions
 * View and manage active subscriptions.
 * Wired to adminPlans.getSubscriptions() API.
 */

import { useState, useEffect, useCallback } from 'react';import CreditCard from 'lucide-react/icons/credit-card';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminPlans } from '../../api/adminApi';
import { PageHeader } from '../../components/PageHeader';
import { DataTable, StatusBadge, type Column } from '../../components/DataTable';
import { EmptyState } from '../../components/EmptyState';

import { useTranslation } from 'react-i18next';
import { Spinner } from '@/components/ui';
interface SubscriptionItem {
  id: number;
  tenant_name: string;
  plan_name: string;
  plan_tier_level: number;
  status: string;
  starts_at: string;
  expires_at: string;
  trial_ends_at: string | null;
  stripe_subscription_id: string | null;
}

export function Subscriptions() {
  const { t } = useTranslation('admin_content');
  usePageTitle("Content");
  const toast = useToast();

  const [data, setData] = useState<SubscriptionItem[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminPlans.getSubscriptions();
      if (res.success && res.data) {
        const result = res.data as unknown;
        if (Array.isArray(result)) {
          setData(result);
        } else if (result && typeof result === 'object') {
          const pd = result as { data?: SubscriptionItem[] };
          setData(pd.data || []);
        }
      }
    } catch {
      toast.error("Failed to load subscriptions");
    } finally {
      setLoading(false);
    }
  }, [toast])


  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const columns: Column<SubscriptionItem>[] = [
    {
      key: 'tenant_name',
      label: t('content.tenant'),
      sortable: true,
      render: (item) => <span className="font-medium">{item.tenant_name || '--'}</span>,
    },
    {
      key: 'plan_name',
      label: "Plans",
      sortable: true,
      render: (item) => <span className="text-sm text-muted">{item.plan_name || '--'}</span>,
    },
    {
      key: 'status',
      label: "Status",
      sortable: true,
      render: (item) => <StatusBadge status={item.status || 'inactive'} />,
    },
    {
      key: 'plan_tier_level',
      label: t('content.tier'),
      sortable: true,
      render: (item) => <span className="text-sm text-muted">{item.plan_tier_level ?? '--'}</span>,
    },
    {
      key: 'starts_at',
      label: t('content.started'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-muted">
          {item.starts_at ? new Date(item.starts_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'expires_at',
      label: t('users.expires'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-muted">
          {item.expires_at ? new Date(item.expires_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'trial_ends_at',
      label: t('content.trial_ends'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-muted">
          {item.trial_ends_at ? new Date(item.trial_ends_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'stripe_subscription_id',
      label: t('content.stripe_subscription_id'),
      sortable: true,
      render: (item) => (
        <span className="text-xs text-muted font-mono">
          {item.stripe_subscription_id || '--'}
        </span>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title={"Subscriptions"} description={"View and manage active member subscriptions"} />
        <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={"Subscriptions"} description={"View and manage active member subscriptions"} />

      {data.length === 0 ? (
        <EmptyState
          icon={CreditCard}
          title={"No data available"}
          description={"Subscriptions will appear here once members start joining plans"}
        />
      ) : (
        <DataTable
          columns={columns}
          data={data}
          searchPlaceholder={t('data_table.search')}
          onRefresh={fetchData}
        />
      )}
    </div>
  );
}

export default Subscriptions;
