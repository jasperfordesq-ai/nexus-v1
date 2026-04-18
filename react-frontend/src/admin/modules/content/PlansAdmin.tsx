// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Plans Admin
 * Super-admin CRUD for subscription plans and pricing tiers.
 * Shows Stripe sync status, tenant count, and one-click Stripe sync.
 */

import { useState, useEffect, useCallback } from 'react';
import { Button, Spinner, Chip, Tooltip } from '@heroui/react';
import { CreditCard, Plus, Pencil, Trash2, RefreshCw, CheckCircle, AlertCircle, Users } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast, useAuth } from '@/contexts';
import { adminPlans, type PlanListItem } from '../../api/adminApi';
import { PageHeader, DataTable, EmptyState, ConfirmModal, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

export function PlansAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('content.plans_admin_title', 'Plans'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();
  const { user } = useAuth();
  const userRecord = user as Record<string, unknown> | null;
  const isSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true;

  const [data, setData] = useState<PlanListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState<PlanListItem | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [syncingId, setSyncingId] = useState<number | null>(null);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminPlans.list();
      if (res.success && res.data) {
        const result = res.data as unknown;
        setData(Array.isArray(result) ? result as PlanListItem[] : []);
      }
    } catch {
      toast.error(t('content.failed_to_load_plans', 'Failed to load plans'));
    } finally {
      setLoading(false);
    }
  }, [toast, t]);

  useEffect(() => { fetchData(); }, [fetchData]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await adminPlans.delete(confirmDelete.id);
      if (res?.success) {
        toast.success(t('content.plan_deleted_successfully', 'Plan deleted'));
        fetchData();
      } else {
        toast.error(t('content.failed_to_delete_plan', 'Failed to delete plan'));
      }
    } catch {
      toast.error(t('content.an_unexpected_error_occurred', 'An unexpected error occurred'));
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const handleSyncStripe = async (plan: PlanListItem) => {
    setSyncingId(plan.id);
    try {
      const res = await adminPlans.syncStripe(plan.id);
      if (res.success) {
        toast.success(`${plan.name} synced to Stripe`);
        fetchData();
      } else {
        toast.error('Stripe sync failed');
      }
    } catch {
      toast.error('Stripe sync failed — check STRIPE_SECRET_KEY is configured');
    } finally {
      setSyncingId(null);
    }
  };

  const formatPrice = (price: number) => {
    if (!price) return t('content.free', 'Free');
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' }).format(Number(price));
  };

  const TIER_COLORS: Record<number, 'default' | 'primary' | 'secondary' | 'success' | 'warning'> = {
    0: 'default',
    1: 'primary',
    2: 'secondary',
    3: 'warning',
  };

  const columns: Column<PlanListItem>[] = [
    {
      key: 'name',
      label: t('content.label_name', 'Name'),
      sortable: true,
      render: (item) => (
        <div className="flex flex-col gap-0.5">
          {isSuperAdmin ? (
            <Button
              type="button"
              variant="light"
              onPress={() => navigate(tenantPath(`/admin/plans/edit/${item.id}`))}
              className="text-left font-semibold text-primary hover:underline min-w-0 h-auto p-0 justify-start"
            >
              {item.name}
            </Button>
          ) : (
            <span className="font-semibold">{item.name}</span>
          )}
          {item.description && (
            <span className="text-xs text-default-400 line-clamp-1">{item.description}</span>
          )}
        </div>
      ),
    },
    {
      key: 'tier_level',
      label: t('content.tier', 'Tier'),
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={TIER_COLORS[item.tier_level] ?? 'default'}
        >
          {item.tier_level === 0 ? 'Free' : `Tier ${item.tier_level}`}
        </Chip>
      ),
    },
    {
      key: 'price_monthly',
      label: t('content.monthly_price', 'Monthly'),
      sortable: true,
      render: (item) => (
        <div className="flex flex-col gap-0.5">
          <span className="text-sm font-medium">{formatPrice(item.price_monthly)}</span>
          <span className="text-xs text-default-400">{formatPrice(item.price_yearly)}/yr</span>
        </div>
      ),
    },
    {
      key: 'tenant_count',
      label: (
        <span className="flex items-center gap-1"><Users size={13} /> Tenants</span>
      ) as unknown as string,
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.tenant_count}</span>
      ),
    },
    {
      key: 'stripe_synced',
      label: 'Stripe',
      render: (item) => (
        <Tooltip
          content={
            item.stripe_synced
              ? `Product: ${item.stripe_product_id}`
              : 'Not synced to Stripe — click Sync'
          }
        >
          <span className="flex items-center gap-1 text-sm">
            {item.stripe_synced ? (
              <CheckCircle size={15} className="text-success" />
            ) : (
              <AlertCircle size={15} className="text-warning" />
            )}
            <span className={item.stripe_synced ? 'text-success' : 'text-warning'}>
              {item.stripe_synced ? 'Synced' : 'Unsynced'}
            </span>
          </span>
        </Tooltip>
      ),
    },
    {
      key: 'is_active',
      label: t('content.label_active', 'Status'),
      render: (item) => (
        <Chip size="sm" variant="flat" color={item.is_active ? 'success' : 'default'}>
          {item.is_active ? t('content.label_active', 'Active') : t('reports.label_inactive', 'Inactive')}
        </Chip>
      ),
    },
    ...(isSuperAdmin ? [{
      key: 'actions',
      label: t('listings.actions', 'Actions'),
      render: (item: PlanListItem) => (
        <div className="flex gap-1">
          <Tooltip content="Edit plan">
            <Button
              isIconOnly size="sm" variant="flat" color="primary"
              onPress={() => navigate(tenantPath(`/admin/plans/edit/${item.id}`))}
              aria-label={t('content.label_edit_plan', 'Edit')}
            >
              <Pencil size={14} />
            </Button>
          </Tooltip>
          <Tooltip content="Sync to Stripe">
            <Button
              isIconOnly size="sm" variant="flat" color="secondary"
              onPress={() => handleSyncStripe(item)}
              isLoading={syncingId === item.id}
              aria-label="Sync to Stripe"
            >
              <RefreshCw size={14} />
            </Button>
          </Tooltip>
          <Tooltip content={item.tenant_count > 0 ? `${item.tenant_count} active tenant(s) — cannot delete` : 'Delete plan'}>
            <Button
              isIconOnly size="sm" variant="flat" color="danger"
              onPress={() => setConfirmDelete(item)}
              isDisabled={item.tenant_count > 0}
              aria-label={t('content.label_delete_plan', 'Delete')}
            >
              <Trash2 size={14} />
            </Button>
          </Tooltip>
        </div>
      ),
    }] : []),
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title={t('content.plans_admin_title', 'Subscription Plans')} description={t('content.plans_admin_desc', 'Manage pricing tiers and feature sets')} />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('content.plans_admin_title', 'Subscription Plans')}
        description={t('content.plans_admin_desc', 'Manage pricing tiers, feature sets, and Stripe product sync')}
        actions={
          isSuperAdmin ? (
            <Button
              color="primary"
              startContent={<Plus size={16} />}
              onPress={() => navigate(tenantPath('/admin/plans/create'))}
            >
              {t('breadcrumbs.create', 'Create')} {t('breadcrumbs.plans', 'Plan')}
            </Button>
          ) : undefined
        }
      />

      {data.length === 0 ? (
        <EmptyState
          icon={CreditCard}
          title={t('no_data', 'No plans')}
          description={t('content.desc_create_subscription_plans_to_offer_diffe', 'Create plans to offer different tiers to your tenants')}
          {...(isSuperAdmin ? {
            actionLabel: `${t('breadcrumbs.create', 'Create')} ${t('breadcrumbs.plans', 'Plan')}`,
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
          title={`${t('common.delete', 'Delete')} ${t('breadcrumbs.plans', 'Plan')}`}
          message={t('content.confirm_delete_plan', { name: confirmDelete.name }, `Delete plan "${confirmDelete.name}"? This cannot be undone.`)}
          confirmLabel={t('common.delete', 'Delete')}
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default PlansAdmin;
