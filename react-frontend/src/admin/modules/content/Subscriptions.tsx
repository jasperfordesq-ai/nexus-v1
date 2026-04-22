// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Subscriptions
 * View and manage active subscriptions.
 * Wired to adminPlans.getSubscriptions() API.
 */

import { useState, useEffect, useCallback } from 'react';
import { Spinner } from '@heroui/react';
import { CreditCard } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminPlans } from '../../api/adminApi';
import { PageHeader, DataTable, StatusBadge, EmptyState, type Column } from '../../components';

import { useTranslation } from 'react-i18next';
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
  const { t } = useTranslation('admin');
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
      label: t('content.tenant', 'Tenant'),
      sortable: true,
      render: (item) => <span className="font-medium">{item.tenant_name || '--'}</span>,
    },
    {
      key: 'plan_name',
      label: "Plans",
      sortable: true,
      render: (item) => <span className="text-sm text-default-600">{item.plan_name || '--'}</span>,
    },
    {
      key: 'status',
      label: "Status",
      sortable: true,
      render: (item) => <StatusBadge status={item.status || 'inactive'} />,
    },
    {
      key: 'plan_tier_level',
      label: t('content.tier', 'Tier'),
      sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.plan_tier_level ?? '--'}</span>,
    },
    {
      key: 'starts_at',
      label: t('content.started', 'Started'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.starts_at ? new Date(item.starts_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'expires_at',
      label: t('users.expires', 'Expires'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.expires_at ? new Date(item.expires_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'trial_ends_at',
      label: t('content.trial_ends', 'Trial Ends'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.trial_ends_at ? new Date(item.trial_ends_at).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'stripe_subscription_id',
      label: t('content.stripe_subscription_id', 'Stripe ID'),
      sortable: true,
      render: (item) => (
        <span className="text-xs text-default-400 font-mono">
          {item.stripe_subscription_id || '--'}
        </span>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title={"Subscriptions"} description={"View and manage active member subscriptions"} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
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
          searchPlaceholder={t('data_table.search', 'Search subscriptions...')}
          onRefresh={fetchData}
        />
      )}
    </div>
  );
}

export default Subscriptions;
