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
import { useToast } from '@/contexts';
import { adminPlans } from '../../api/adminApi';
import { PageHeader, DataTable, EmptyState, ConfirmModal, type Column } from '../../components';

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
  usePageTitle('Admin - Plans & Pricing');
  const toast = useToast();
  const navigate = useNavigate();

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
      toast.error('Failed to load plans');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await adminPlans.delete(confirmDelete.id);
      if (res?.success) {
        toast.success('Plan deleted successfully');
        fetchData();
      } else {
        toast.error('Failed to delete plan');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  const formatPrice = (price: number) => {
    if (price === 0 || price === null || price === undefined) return 'Free';
    return `EUR ${Number(price).toFixed(2)}`;
  };

  const columns: Column<PlanItem>[] = [
    {
      key: 'name',
      label: 'Name',
      sortable: true,
      render: (item) => (
        <button
          type="button"
          className="text-left font-medium text-primary hover:underline"
          onClick={() => navigate(`../plans/edit/${item.id}`)}
        >
          {item.name}
        </button>
      ),
    },
    {
      key: 'tier_level',
      label: 'Tier',
      sortable: true,
      render: (item) => <span className="text-sm text-default-600">{item.tier_level ?? '--'}</span>,
    },
    {
      key: 'price_monthly',
      label: 'Monthly Price',
      sortable: true,
      render: (item) => <span className="text-sm text-default-600">{formatPrice(item.price_monthly)}</span>,
    },
    {
      key: 'price_yearly',
      label: 'Annual Price',
      sortable: true,
      render: (item) => <span className="text-sm text-default-600">{formatPrice(item.price_yearly)}</span>,
    },
    {
      key: 'is_active',
      label: 'Active',
      render: (item) => (
        <Chip size="sm" variant="flat" color={item.is_active ? 'success' : 'default'}>
          {item.is_active ? 'Active' : 'Inactive'}
        </Chip>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <div className="flex gap-1">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="primary"
            onPress={() => navigate(`../plans/edit/${item.id}`)}
            aria-label="Edit plan"
          >
            <Pencil size={14} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="danger"
            onPress={() => setConfirmDelete(item)}
            aria-label="Delete plan"
          >
            <Trash2 size={14} />
          </Button>
        </div>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title="Plans & Pricing" description="Manage subscription plans and pricing tiers" />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Plans & Pricing"
        description="Manage subscription plans and pricing tiers"
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => navigate('../plans/create')}
          >
            Create Plan
          </Button>
        }
      />

      {data.length === 0 ? (
        <EmptyState
          icon={CreditCard}
          title="No Plans Created"
          description="Create subscription plans to offer different tiers of access to your community platform."
          actionLabel="Create Plan"
          onAction={() => navigate('../plans/create')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={data}
          searchPlaceholder="Search plans..."
          onRefresh={fetchData}
        />
      )}

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title="Delete Plan"
          message={`Are you sure you want to delete "${confirmDelete.name}"? This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default PlansAdmin;
