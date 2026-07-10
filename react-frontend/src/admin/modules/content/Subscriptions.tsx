// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Subscriptions
 * View and manage active subscriptions.
 * Wired to adminPlans.getSubscriptions() API.
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useCallback, useEffect, useRef, useState } from 'react';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import CreditCard from 'lucide-react/icons/credit-card';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { usePageTitle } from '@/hooks';
import { logError } from '@/lib/logger';
import { adminPlans } from '../../api/adminApi';
import { PageHeader } from '../../components/PageHeader';
import { DataTable, StatusBadge, type Column } from '../../components/DataTable';
import { EmptyState } from '../../components/EmptyState';

import { useTranslation } from 'react-i18next';
import { Button, Spinner } from '@/components/ui';
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

function parseSubscriptions(value: unknown): SubscriptionItem[] | null {
  if (Array.isArray(value)) return value as SubscriptionItem[];
  if (typeof value !== 'object' || value === null) return null;

  const page = value as { data?: unknown };
  return Array.isArray(page.data) ? page.data as SubscriptionItem[] : null;
}

export function Subscriptions() {
  const { t } = useTranslation('admin_content');
  usePageTitle(t('content.page_title'));

  const [data, setData] = useState<SubscriptionItem[] | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const requestIdRef = useRef(0);

  const fetchData = useCallback(async () => {
    const requestId = ++requestIdRef.current;
    setLoading(true);

    try {
      const res = await adminPlans.getSubscriptions();
      if (requestId !== requestIdRef.current) return;

      const parsed = res.success ? parseSubscriptions(res.data) : null;
      if (parsed) {
        setData(parsed);
        setLoadFailed(false);
      } else {
        setLoadFailed(true);
      }
    } catch (err) {
      if (requestId !== requestIdRef.current) return;
      logError('Failed to load subscriptions', err);
      setLoadFailed(true);
    } finally {
      if (requestId === requestIdRef.current) {
        setLoading(false);
      }
    }
  }, []);


  useEffect(() => {
    void fetchData();
    return () => {
      requestIdRef.current += 1;
    };
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
      label: t('content.plan'),
      sortable: true,
      render: (item) => <span className="text-sm text-muted">{item.plan_name || '--'}</span>,
    },
    {
      key: 'status',
      label: t('content.label_status'),
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
          {item.starts_at ? new Date(item.starts_at).toLocaleDateString(getFormattingLocale()) : '--'}
        </span>
      ),
    },
    {
      key: 'expires_at',
      label: t('users.expires'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-muted">
          {item.expires_at ? new Date(item.expires_at).toLocaleDateString(getFormattingLocale()) : '--'}
        </span>
      ),
    },
    {
      key: 'trial_ends_at',
      label: t('content.trial_ends'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-muted">
          {item.trial_ends_at ? new Date(item.trial_ends_at).toLocaleDateString(getFormattingLocale()) : '--'}
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

  const refreshAction = (
    <Button variant="secondary" onPress={fetchData} isLoading={loading} startContent={<RefreshCw size={16} aria-hidden="true" />}>
      {t('common.refresh')}
    </Button>
  );

  const staleDataWarning = loadFailed && data !== null ? (
    <div className="mb-4 flex flex-col gap-3 rounded-xl border border-danger/30 bg-danger/5 p-4 text-danger sm:flex-row sm:items-center sm:justify-between" role="alert">
      <div className="flex items-center gap-2">
        <AlertTriangle size={18} className="shrink-0" aria-hidden="true" />
        <p className="text-sm">{t('content.failed_to_load_subscriptions')}</p>
      </div>
      <Button variant="outline" onPress={fetchData} isLoading={loading}>{t('common.retry')}</Button>
    </div>
  ) : null;

  return (
    <div>
      <PageHeader title={t('content.subscriptions_title')} description={t('content.subscriptions_desc')} actions={refreshAction} />

      {loading && data === null ? (
        <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex justify-center py-12"><Spinner size="lg" /></div>
      ) : loadFailed && data === null ? (
        <div role="alert">
          <EmptyState icon={AlertTriangle} title={t('content.failed_to_load_subscriptions')} actionLabel={t('common.retry')} onAction={fetchData} />
        </div>
      ) : (
        <>
          {staleDataWarning}
          {(data ?? []).length === 0 ? (
            <EmptyState
              icon={CreditCard}
              title={t('content.no_data_available')}
              description={t('content.desc_subscriptions_will_appear_here_once_memb')}
              actionLabel={t('common.refresh')}
              onAction={fetchData}
            />
          ) : (
            <DataTable columns={columns} data={data ?? []} searchPlaceholder={t('shared.search')} onRefresh={fetchData} />
          )}
        </>
      )}
    </div>
  );
}

export default Subscriptions;
