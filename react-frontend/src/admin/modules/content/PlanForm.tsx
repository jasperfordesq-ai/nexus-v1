/**
 * Plan Create/Edit Form
 * Form for creating or editing a subscription plan.
 * Wired to adminPlans API for get/create/update.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Textarea, Switch, Button, Spinner } from '@heroui/react';
import { CreditCard, ArrowLeft, Save } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminPlans } from '../../api/adminApi';
import { PageHeader } from '../../components';

interface PlanFormData {
  name: string;
  description: string;
  price_monthly: string;
  price_yearly: string;
  tier_level: string;
  max_menus: string;
  max_menu_items: string;
  features: string;
  allowed_layouts: string;
  is_active: boolean;
}

export function PlanForm() {
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);
  usePageTitle(`Admin - ${isEdit ? 'Edit' : 'Create'} Plan`);
  const navigate = useNavigate();
  const toast = useToast();

  const [formData, setFormData] = useState<PlanFormData>({
    name: '',
    description: '',
    price_monthly: '',
    price_yearly: '',
    tier_level: '1',
    max_menus: '',
    max_menu_items: '',
    features: '',
    allowed_layouts: '',
    is_active: true,
  });
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (isEdit) {
      adminPlans.get(Number(id))
        .then((res) => {
          if (res.success && res.data) {
            const plan = res.data as Record<string, unknown>;
            setFormData({
              name: (plan.name as string) || '',
              description: (plan.description as string) || '',
              price_monthly: plan.price_monthly !== undefined && plan.price_monthly !== null ? String(plan.price_monthly) : '',
              price_yearly: plan.price_yearly !== undefined && plan.price_yearly !== null ? String(plan.price_yearly) : '',
              tier_level: plan.tier_level !== undefined ? String(plan.tier_level) : '1',
              max_menus: plan.max_menus !== undefined && plan.max_menus !== null ? String(plan.max_menus) : '',
              max_menu_items: plan.max_menu_items !== undefined && plan.max_menu_items !== null ? String(plan.max_menu_items) : '',
              features: Array.isArray(plan.features) ? (plan.features as string[]).join(', ') : '',
              allowed_layouts: Array.isArray(plan.allowed_layouts) ? (plan.allowed_layouts as string[]).join(', ') : '',
              is_active: plan.is_active !== false,
            });
          }
        })
        .catch(() => toast.error('Failed to load plan'))
        .finally(() => setLoading(false));
    }
  }, [id, isEdit]);

  const handleChange = (field: keyof PlanFormData, value: string | boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.warning('Plan name is required');
      return;
    }
    setSaving(true);

    // Parse comma-separated strings into arrays
    const featuresArr = formData.features.trim()
      ? formData.features.split(',').map((s) => s.trim()).filter(Boolean)
      : [];
    const layoutsArr = formData.allowed_layouts.trim()
      ? formData.allowed_layouts.split(',').map((s) => s.trim()).filter(Boolean)
      : [];

    const payload = {
      name: formData.name,
      description: formData.description || undefined,
      price_monthly: formData.price_monthly ? Number(formData.price_monthly) : undefined,
      price_yearly: formData.price_yearly ? Number(formData.price_yearly) : undefined,
      tier_level: formData.tier_level ? Number(formData.tier_level) : undefined,
      max_menus: formData.max_menus ? Number(formData.max_menus) : undefined,
      max_menu_items: formData.max_menu_items ? Number(formData.max_menu_items) : undefined,
      features: featuresArr,
      allowed_layouts: layoutsArr,
      is_active: formData.is_active,
    };
    try {
      if (isEdit) {
        const res = await adminPlans.update(Number(id), payload);
        if (res?.success) {
          toast.success('Plan updated successfully');
          navigate('../plans');
        } else {
          toast.error('Failed to update plan');
        }
      } else {
        const res = await adminPlans.create(payload);
        if (res?.success) {
          toast.success('Plan created successfully');
          navigate('../plans');
        } else {
          toast.error('Failed to create plan');
        }
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div>
        <PageHeader title={isEdit ? 'Edit Plan' : 'Create Plan'} description="Loading plan..." />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={isEdit ? 'Edit Plan' : 'Create Plan'}
        description={isEdit ? 'Update plan details' : 'Create a new subscription plan'}
        actions={<Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate('../plans')}>Back</Button>}
      />

      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><CreditCard size={20} /> Plan Details</h3></CardHeader>
        <CardBody className="gap-4">
          <Input
            label="Plan Name"
            placeholder="e.g., Pro"
            isRequired
            variant="bordered"
            value={formData.name}
            onValueChange={(v) => handleChange('name', v)}
          />
          <Textarea
            label="Description"
            placeholder="Plan features and benefits..."
            variant="bordered"
            minRows={3}
            value={formData.description}
            onValueChange={(v) => handleChange('description', v)}
          />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input
              label="Monthly Price"
              type="number"
              placeholder="9.99"
              variant="bordered"
              startContent={<span className="text-default-400 text-sm">EUR</span>}
              value={formData.price_monthly}
              onValueChange={(v) => handleChange('price_monthly', v)}
            />
            <Input
              label="Annual Price"
              type="number"
              placeholder="99.99"
              variant="bordered"
              startContent={<span className="text-default-400 text-sm">EUR</span>}
              value={formData.price_yearly}
              onValueChange={(v) => handleChange('price_yearly', v)}
            />
          </div>
          <Input
            label="Tier Level"
            type="number"
            placeholder="1"
            variant="bordered"
            description="Higher tier = more features (0 = free, 1 = basic, 2 = pro, etc.)"
            value={formData.tier_level}
            onValueChange={(v) => handleChange('tier_level', v)}
          />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input
              label="Max Menus"
              type="number"
              placeholder="e.g., 10"
              variant="bordered"
              description="Maximum navigation menus allowed"
              value={formData.max_menus}
              onValueChange={(v) => handleChange('max_menus', v)}
            />
            <Input
              label="Max Menu Items"
              type="number"
              placeholder="e.g., 50"
              variant="bordered"
              description="Maximum menu items allowed"
              value={formData.max_menu_items}
              onValueChange={(v) => handleChange('max_menu_items', v)}
            />
          </div>
          <Input
            label="Features"
            placeholder="e.g., events, groups, gamification"
            variant="bordered"
            description="Comma-separated list of feature slugs included in this plan"
            value={formData.features}
            onValueChange={(v) => handleChange('features', v)}
          />
          <Input
            label="Allowed Layouts"
            placeholder="e.g., modern, civicone"
            variant="bordered"
            description="Comma-separated list of layout/theme slugs available to this plan"
            value={formData.allowed_layouts}
            onValueChange={(v) => handleChange('allowed_layouts', v)}
          />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Active</p>
              <p className="text-sm text-default-500">Make this plan available for purchase</p>
            </div>
            <Switch
              isSelected={formData.is_active}
              onValueChange={(v) => handleChange('is_active', v)}
              aria-label="Active"
            />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="flat" onPress={() => navigate('../plans')}>Cancel</Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
            >
              {isEdit ? 'Update' : 'Create'} Plan
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default PlanForm;
