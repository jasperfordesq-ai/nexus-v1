/**
 * Plan Create/Edit Form
 * Form for creating or editing a subscription plan.
 * Wired to adminPlans API for get/create/update.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Textarea, Select, SelectItem, Switch, Button, Spinner } from '@heroui/react';
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
  billing_period: string;
  max_members: string;
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
    billing_period: 'monthly',
    max_members: '',
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
              price_monthly: plan.price_monthly !== undefined ? String(plan.price_monthly) : '',
              price_yearly: plan.price_yearly !== undefined ? String(plan.price_yearly) : '',
              tier_level: plan.tier_level !== undefined ? String(plan.tier_level) : '1',
              billing_period: (plan.billing_period as string) || 'monthly',
              max_members: plan.max_members !== undefined ? String(plan.max_members) : '',
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
    const payload = {
      name: formData.name,
      description: formData.description || undefined,
      price_monthly: formData.price_monthly ? Number(formData.price_monthly) : undefined,
      price_yearly: formData.price_yearly ? Number(formData.price_yearly) : undefined,
      tier_level: formData.tier_level ? Number(formData.tier_level) : undefined,
      billing_period: formData.billing_period,
      max_members: formData.max_members ? Number(formData.max_members) : undefined,
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
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input
              label="Tier Level"
              type="number"
              placeholder="1"
              variant="bordered"
              value={formData.tier_level}
              onValueChange={(v) => handleChange('tier_level', v)}
            />
            <Select
              label="Billing Period"
              variant="bordered"
              selectedKeys={[formData.billing_period]}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) handleChange('billing_period', selected);
              }}
            >
              <SelectItem key="monthly">Monthly</SelectItem>
              <SelectItem key="annual">Annual</SelectItem>
              <SelectItem key="lifetime">Lifetime</SelectItem>
            </Select>
          </div>
          <Input
            label="Max Members"
            type="number"
            placeholder="Unlimited"
            variant="bordered"
            value={formData.max_members}
            onValueChange={(v) => handleChange('max_members', v)}
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
