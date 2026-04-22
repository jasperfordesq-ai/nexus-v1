// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Plan Create/Edit Form
 * Super-admin form for creating or editing a subscription plan.
 * Includes Stripe sync status and one-click sync trigger.
 */

import { useState, useEffect } from 'react';
import {
  Card, CardBody, CardHeader,
  Input, Textarea, Switch, Button, Spinner, Chip, Divider,
} from '@heroui/react';
import CreditCard from 'lucide-react/icons/credit-card';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Save from 'lucide-react/icons/save';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import AlertCircle from 'lucide-react/icons/circle-alert';
import { useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminPlans, type PlanDetail } from '../../api/adminApi';
import { PageHeader } from '../../components';
import { useTranslation } from 'react-i18next';

interface PlanFormData {
  name: string;
  description: string;
  price_monthly: string;
  price_yearly: string;
  tier_level: string;
  max_users: string;
  max_menus: string;
  max_menu_items: string;
  /** One feature per line */
  features: string;
  allowed_layouts: string;
  is_active: boolean;
}

interface StripeStatus {
  stripe_product_id?: string | null;
  stripe_price_id_monthly?: string | null;
  stripe_price_id_yearly?: string | null;
}

export function PlanForm() {
  const { t } = useTranslation('admin');
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);
  usePageTitle(`Admin — ${isEdit ? t('breadcrumbs.edit', 'Edit') : t('breadcrumbs.create', 'Create')} Plan`);
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [formData, setFormData] = useState<PlanFormData>({
    name: '',
    description: '',
    price_monthly: '0',
    price_yearly: '0',
    tier_level: '1',
    max_users: '',
    max_menus: '',
    max_menu_items: '',
    features: '',
    allowed_layouts: '',
    is_active: true,
  });
  const [stripeStatus, setStripeStatus] = useState<StripeStatus>({});
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [syncing, setSyncing] = useState(false);

  useEffect(() => {
    if (!isEdit) return;
    adminPlans.get(Number(id))
      .then((res) => {
        if (res.success && res.data) {
          const plan = res.data as PlanDetail;
          setFormData({
            name: plan.name ?? '',
            description: plan.description ?? '',
            price_monthly: plan.price_monthly != null ? String(plan.price_monthly) : '0',
            price_yearly: plan.price_yearly != null ? String(plan.price_yearly) : '0',
            tier_level: plan.tier_level != null ? String(plan.tier_level) : '1',
            max_users: plan.max_users != null ? String(plan.max_users) : '',
            max_menus: plan.max_menus != null ? String(plan.max_menus) : '',
            max_menu_items: plan.max_menu_items != null ? String(plan.max_menu_items) : '',
            // One feature per line
            features: Array.isArray(plan.features) ? plan.features.join('\n') : '',
            allowed_layouts: Array.isArray(plan.allowed_layouts) ? plan.allowed_layouts.join('\n') : '',
            is_active: plan.is_active !== false,
          });
          setStripeStatus({
            stripe_product_id: plan.stripe_product_id,
            stripe_price_id_monthly: plan.stripe_price_id_monthly,
            stripe_price_id_yearly: plan.stripe_price_id_yearly,
          });
        }
      })
      .catch(() => toast.error(t('content.failed_to_load_plans', 'Failed to load plan')))
      .finally(() => setLoading(false));
  }, [id, isEdit, toast]);


  const handleChange = (field: keyof PlanFormData, value: string | boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const buildPayload = () => {
    const featuresArr = formData.features.trim()
      ? formData.features.split('\n').map((s) => s.trim()).filter(Boolean)
      : [];
    const layoutsArr = formData.allowed_layouts.trim()
      ? formData.allowed_layouts.split('\n').map((s) => s.trim()).filter(Boolean)
      : [];

    return {
      name: formData.name,
      description: formData.description || undefined,
      price_monthly: formData.price_monthly !== '' ? Number(formData.price_monthly) : 0,
      price_yearly: formData.price_yearly !== '' ? Number(formData.price_yearly) : 0,
      tier_level: formData.tier_level ? Number(formData.tier_level) : 0,
      max_users: formData.max_users !== '' ? Number(formData.max_users) : null,
      max_menus: formData.max_menus ? Number(formData.max_menus) : undefined,
      max_menu_items: formData.max_menu_items ? Number(formData.max_menu_items) : undefined,
      features: featuresArr,
      allowed_layouts: layoutsArr,
      is_active: formData.is_active,
    };
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.warning(t('content.plan_name_required', 'Plan name is required'));
      return;
    }

    const numericChecks: Array<{ label: string; value: string }> = [
      { label: t('content.monthly_price', 'Monthly Price'), value: formData.price_monthly },
      { label: t('content.annual_price', 'Annual Price'), value: formData.price_yearly },
      { label: t('content.tier_level', 'Tier Level'), value: formData.tier_level },
    ];
    for (const check of numericChecks) {
      if (!check.value) continue;
      const n = Number(check.value);
      if (Number.isNaN(n) || n < 0) {
        toast.error(`${check.label} must be a valid non-negative number`);
        return;
      }
    }

    setSaving(true);
    try {
      if (isEdit) {
        const res = await adminPlans.update(Number(id), buildPayload());
        if (res?.success) {
          toast.success(t('content.plan_updated', 'Plan updated'));
          navigate(tenantPath('/admin/plans'));
        } else {
          toast.error("Failed to update plan");
        }
      } else {
        const res = await adminPlans.create(buildPayload());
        if (res?.success) {
          toast.success(t('content.plan_created', 'Plan created'));
          navigate(tenantPath('/admin/plans'));
        } else {
          toast.error("Failed to create plan");
        }
      }
    } catch {
      toast.error(t('content.an_unexpected_error_occurred', 'An unexpected error occurred'));
    } finally {
      setSaving(false);
    }
  };

  const handleSyncStripe = async () => {
    if (!isEdit) return;
    setSyncing(true);
    try {
      const res = await adminPlans.syncStripe(Number(id));
      if (res.success && res.data) {
        const d = res.data as unknown as { stripe_product_id: string | null; stripe_price_id_monthly: string | null; stripe_price_id_yearly: string | null };
        setStripeStatus({
          stripe_product_id: d.stripe_product_id,
          stripe_price_id_monthly: d.stripe_price_id_monthly,
          stripe_price_id_yearly: d.stripe_price_id_yearly,
        });
        toast.success("Synced to Stripe");
      } else {
        toast.error("Stripe sync failed");
      }
    } catch {
      toast.error("Stripe sync failed — ensure STRIPE_SECRET_KEY is configured");
    } finally {
      setSyncing(false);
    }
  };

  if (loading) {
    return (
      <div>
        <PageHeader title={`${isEdit ? 'Edit' : 'Create'} Plan`} description="" />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  const stripeSynced = !!stripeStatus.stripe_product_id;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={isEdit ? `${t('breadcrumbs.edit', 'Edit')} Plan` : `${t('breadcrumbs.create', 'Create')} Plan`}
        description={isEdit
          ? t('content.plans_admin_desc', 'Update plan details, pricing, and features')
          : t('content.desc_create_subscription_plans_to_offer_diffe', 'Create a new subscription plan')}
        actions={
          <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/plans'))}>
            {t('common.back', 'Back')}
          </Button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main form */}
        <div className="lg:col-span-2 flex flex-col gap-6">
          <Card shadow="sm">
            <CardHeader>
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <CreditCard size={20} /> Plan Details
              </h3>
            </CardHeader>
            <CardBody className="gap-4">
              <Input
                label={t('content.label_name', 'Name')}
                placeholder="e.g., Community"
                isRequired
                variant="bordered"
                value={formData.name}
                onValueChange={(v) => handleChange('name', v)}
              />
              <Textarea
                label={t('content.label_description', 'Description')}
                placeholder="Describe who this plan is for and what makes it suitable for them."
                variant="bordered"
                minRows={3}
                value={formData.description}
                onValueChange={(v) => handleChange('description', v)}
              />
              <div className="grid grid-cols-2 gap-4">
                <Input
                  label={t('content.monthly_price', 'Monthly Price')}
                  type="number" min="0" step="0.01"
                  variant="bordered"
                  startContent={<span className="text-default-400 text-sm">EUR</span>}
                  value={formData.price_monthly}
                  onValueChange={(v) => handleChange('price_monthly', v)}
                />
                <Input
                  label={t('content.annual_price', 'Annual Price')}
                  type="number" min="0" step="0.01"
                  variant="bordered"
                  startContent={<span className="text-default-400 text-sm">EUR</span>}
                  description="Typically ~17% off the monthly rate"
                  value={formData.price_yearly}
                  onValueChange={(v) => handleChange('price_yearly', v)}
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <Input
                  label={t('content.tier_level', 'Tier Level')}
                  type="number" min="0" step="1"
                  variant="bordered"
                  description="0 = free, 1 = basic, 2 = partner, 3 = network"
                  value={formData.tier_level}
                  onValueChange={(v) => handleChange('tier_level', v)}
                />
                <Input
                  label={t('content.max_users', 'Max Members')}
                  type="number" min="1" step="1"
                  variant="bordered"
                  placeholder="Leave blank for unlimited"
                  description="Active members allowed on this plan"
                  value={formData.max_users}
                  onValueChange={(v) => handleChange('max_users', v)}
                />
              </div>
            </CardBody>
          </Card>

          <Card shadow="sm">
            <CardHeader>
              <h3 className="text-lg font-semibold">Selling Points</h3>
            </CardHeader>
            <CardBody className="gap-4">
              <Textarea
                label={t('content.features', 'Feature List')}
                placeholder={'Up to 500 community members\nFull listings, wallet & exchanges\nEvents & group coordination'}
                variant="bordered"
                minRows={6}
                description="One feature per line. These are displayed as bullet points on the pricing page."
                value={formData.features}
                onValueChange={(v) => handleChange('features', v)}
              />
              <Textarea
                label={t('content.allowed_layouts', 'Allowed Layouts')}
                placeholder={'modern\ncivicone'}
                variant="bordered"
                minRows={3}
                description="One layout slug per line. Leave blank to allow all layouts."
                value={formData.allowed_layouts}
                onValueChange={(v) => handleChange('allowed_layouts', v)}
              />
            </CardBody>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="flex flex-col gap-6">
          <Card shadow="sm">
            <CardHeader>
              <h3 className="text-base font-semibold">Status & Limits</h3>
            </CardHeader>
            <CardBody className="gap-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-sm">{t('content.label_active', 'Active')}</p>
                  <p className="text-xs text-default-500">Visible to tenants on the pricing page</p>
                </div>
                <Switch
                  isSelected={formData.is_active}
                  onValueChange={(v) => handleChange('is_active', v)}
                  aria-label={t('content.label_active', 'Active')}
                />
              </div>
              <Divider />
              <div className="grid grid-cols-2 gap-3">
                <Input
                  label="Max Menus"
                  type="number" min="1" step="1"
                  variant="bordered"
                  size="sm"
                  placeholder="e.g., 10"
                  value={formData.max_menus}
                  onValueChange={(v) => handleChange('max_menus', v)}
                />
                <Input
                  label="Max Menu Items"
                  type="number" min="1" step="1"
                  variant="bordered"
                  size="sm"
                  placeholder="e.g., 50"
                  value={formData.max_menu_items}
                  onValueChange={(v) => handleChange('max_menu_items', v)}
                />
              </div>
            </CardBody>
          </Card>

          {isEdit && (
            <Card shadow="sm">
              <CardHeader>
                <h3 className="text-base font-semibold flex items-center gap-2">
                  Stripe Sync
                </h3>
              </CardHeader>
              <CardBody className="gap-3">
                <div className="flex items-center gap-2">
                  {stripeSynced ? (
                    <CheckCircle size={16} className="text-success shrink-0" />
                  ) : (
                    <AlertCircle size={16} className="text-warning shrink-0" />
                  )}
                  <Chip size="sm" variant="flat" color={stripeSynced ? 'success' : 'warning'}>
                    {stripeSynced ? 'Synced' : 'Not synced'}
                  </Chip>
                </div>

                {stripeSynced && (
                  <div className="flex flex-col gap-1 text-xs text-default-500 break-all">
                    {stripeStatus.stripe_product_id && (
                      <span><span className="font-medium">Product:</span> {stripeStatus.stripe_product_id}</span>
                    )}
                    {stripeStatus.stripe_price_id_monthly && (
                      <span><span className="font-medium">Monthly:</span> {stripeStatus.stripe_price_id_monthly}</span>
                    )}
                    {stripeStatus.stripe_price_id_yearly && (
                      <span><span className="font-medium">Yearly:</span> {stripeStatus.stripe_price_id_yearly}</span>
                    )}
                  </div>
                )}

                <Button
                  color="secondary"
                  variant="flat"
                  startContent={<RefreshCw size={14} />}
                  onPress={handleSyncStripe}
                  isLoading={syncing}
                  size="sm"
                  fullWidth
                >
                  {stripeSynced ? 'Re-sync to Stripe' : 'Sync to Stripe'}
                </Button>
                <p className="text-xs text-default-400">
                  Creates or updates the Stripe product and prices for this plan. Requires STRIPE_SECRET_KEY.
                </p>
              </CardBody>
            </Card>
          )}

          <div className="flex flex-col gap-2">
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
              fullWidth
            >
              {isEdit ? t('federation.save_changes', 'Save Changes') : `${t('breadcrumbs.create', 'Create')} Plan`}
            </Button>
            <Button variant="flat" onPress={() => navigate(tenantPath('/admin/plans'))} fullWidth>
              {t('cancel', 'Cancel')}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default PlanForm;
