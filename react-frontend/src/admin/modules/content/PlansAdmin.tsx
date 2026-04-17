// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Plans Admin
 * Manage subscription plans and pricing tiers.
 * Wired to adminPlans API for full CRUD.
 */

import { useState, useEffect, useCallback } from 'react';
import { Button, Spinner, Chip } from '@heroui/react';
import { CreditCard, Plus, Pencil, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast, useAuth } from '@/contexts';
import { adminPlans } from '../../api/adminApi';
import { PageHeader, DataTable, EmptyState, ConfirmModal, type Column } from '../../components';

import { useTranslation } from 'react-i18next';
interface PlanItem {
  id: number;
  name: string;
  slug: string;
  tier_level: number;
  price_monthly: number;
  price_yearly: number;
  is_active: boolean;
}

export function PlansAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('content.page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();
  const { user } = useAuth();
  const userRecord = user as Record<string, unknown> | null;
  const isSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true;

  const [data, setData] = useState<PlanItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState<PlanItem | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminPlans.list();
      if (res.success && res.data) {
        const result = res.data as unknown;
        if (Array.isArray(result)) {
          setData(result);
        } else if (result && typeof result === 'object') {
          const pd = result as { data?: PlanItem[] };
          setData(pd.data || []);
        }
      }
    } catch {
      toast.error(t('content.failed_to_load_plans'));
    } finally {
      setLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await adminPlans.delete(confirmDelete.id);
      if (res?.success) {
        toast.success(t('content.plan_deleted_successfully'));
        fetchData();
      } else {
        toast.error(t('content.failed_to_delete_plan'));
      }
    } catch {
      toast.error(t('content.an_unexpected_error_occurred'));
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  // Platform subscription billing is EUR (Stripe Billing)
  const formatPrice = (price: number) => {
    if (price === 0 || price === null || price === undefined) return t('content.free', 'Free');
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' }).format(Number(price));
  };

  const columns: Column<PlanItem>[] = [
    {
      key: 'name',
      label: t('content.label_name'),
      sortable: true,
      render: (item) =>
        isSuperAdmin ? (
          <Button
            type="button"
            variant="light"
            onPress={() => navigate(tenantPath(`/admin/plans/edit/${item.id}`))}
            className="text-left font-medium text-primary hover:underline min-w-0 h-auto p-0 justify-start"
          >
            {item.name}
          </Button>
        ) : (
          <span className="text-sm font-medium">{item.name}</span>
        ),
    },
    {
      key: 'tier_level',
      label: t('content.tier', 'Tier'),
      sortable: true,
      render: (item) => <span className="text-sm text-default-600">{item.tier_level ?? '--'}</span>,
    },
    {
      key: 'price_monthly',
      label: t('content.monthly_price', 'Monthly Price'),
      sortable: true,
      render: (item) => <span className="text-sm text-default-600">{formatPrice(item.price_monthly)}</span>,
    },
    {
      key: 'price_yearly',
      label: t('content.annual_price', 'Annual Price'),
      sortable: true,
      render: (item) => <span className="text-sm text-default-600">{formatPrice(item.price_yearly)}</span>,
    },
    {
      key: 'is_active',
      label: t('content.label_active'),
      render: (item) => (
        <Chip size="sm" variant="flat" color={item.is_active ? 'success' : 'default'}>
          {item.is_active ? t('content.label_active') : t('reports.label_inactive', 'Inactive')}
        </Chip>
      ),
    },
    ...(isSuperAdmin ? [{
      key: 'actions',
      label: t('listings.actions'),
      render: (item: PlanItem) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="primary"
            onPress={() => navigate(tenantPath(`/admin/plans/edit/${item.id}`))}
            aria-label={t('content.label_edit_plan')}
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label={t('content.label_delete_plan')}
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    }] : []),
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title={t('content.plans_admin_title')} description={t('content.plans_admin_desc')} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('content.plans_admin_title')}
        description={t('content.plans_admin_desc')}
        actions={
          isSuperAdmin ? (
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/plans/create'))}
            >
              {t('breadcrumbs.create')} {t('breadcrumbs.plans')}
            </Button>
          ) : undefined
        }
      />

      {data.length === 0 ? (
        <EmptyState
          icon={CreditCard}
          title={t('no_data')}
          description={t('content.desc_create_subscription_plans_to_offer_diffe')}
          {...(isSuperAdmin ? {
            actionLabel: `${t('breadcrumbs.create')} ${t('breadcrumbs.plans')}`,
            onAction: () => navigate(tenantPath('/admin/plans/create')),
          } : {})}
        />
      ) : (
        <DataTable
          columns={columns}
          data={data}
          searchPlaceholder={t('data_table.search', 'Search plans...')}
          onRefresh={fetchData}
        />
      )}

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title={`${t('common.delete')} ${t('breadcrumbs.plans')}`}
          message={t('content.confirm_delete_plan', { name: confirmDelete.name })}
          confirmLabel={t('common.delete')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default PlansAdmin;
