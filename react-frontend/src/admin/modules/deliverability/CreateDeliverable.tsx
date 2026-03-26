// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create Deliverable
 * Form for creating a new project deliverable.
 * Wired to adminDeliverability.create() API.
 */

import { useState } from 'react';
import { Card, CardBody, CardHeader, Input, Textarea, Select, SelectItem, Button } from '@heroui/react';
import { Target, ArrowLeft, Save } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminDeliverability } from '../../api/adminApi';
import { PageHeader } from '../../components';

interface DeliverableFormData {
  title: string;
  description: string;
  priority: string;
  status: string;
  due_date: string;
  assigned_to: string;
}

export function CreateDeliverable() {
  const { t } = useTranslation('admin');
  usePageTitle(t('deliverability.create_title'));
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [formData, setFormData] = useState<DeliverableFormData>({
    title: '',
    description: '',
    priority: 'medium',
    status: 'planned',
    due_date: '',
    assigned_to: '',
  });
  const [saving, setSaving] = useState(false);

  const handleChange = (field: keyof DeliverableFormData, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = async () => {
    if (!formData.title.trim()) {
      toast.warning(t('deliverability.title_required'));
      return;
    }
    setSaving(true);
    try {
      const res = await adminDeliverability.create({
        title: formData.title,
        description: formData.description || undefined,
        priority: formData.priority || undefined,
        status: formData.status || undefined,
        due_date: formData.due_date || undefined,
      });
      if (res?.success) {
        toast.success(t('deliverability.created_success'));
        navigate(tenantPath('/admin/deliverability/list'));
      } else {
        toast.error(t('deliverability.create_failed'));
      }
    } catch {
      toast.error(t('common.an_unexpected_error'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <PageHeader
        title={t("deliverability.create_title")}
        description={t("deliverability.create_description")}
        actions={<Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/deliverability/list'))}>{t("common.back")}</Button>}
      />

      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Target size={20} /> {t("deliverability.details_heading")}</h3></CardHeader>
        <CardBody className="gap-4">
          <Input
            label={t("deliverability.title_label")}
            placeholder={t("deliverability.title_placeholder")}
            isRequired
            variant="bordered"
            value={formData.title}
            onValueChange={(v) => handleChange('title', v)}
          />
          <Textarea
            label={t('deliverability.label_description')}
            placeholder={t('deliverability.placeholder_describe_the_deliverable')}
            variant="bordered"
            minRows={3}
            value={formData.description}
            onValueChange={(v) => handleChange('description', v)}
          />
          <Select
            label={t("deliverability.priority_label")}
            variant="bordered"
            selectedKeys={[formData.priority]}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0] as string;
              if (selected) handleChange('priority', selected);
            }}
          >
            <SelectItem key="low">{t("common.low")}</SelectItem>
            <SelectItem key="medium">{t("common.medium")}</SelectItem>
            <SelectItem key="high">{t("common.high")}</SelectItem>
            <SelectItem key="critical">{t("common.critical")}</SelectItem>
          </Select>
          <Select
            label={t("deliverability.status_label")}
            variant="bordered"
            selectedKeys={[formData.status]}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0] as string;
              if (selected) handleChange('status', selected);
            }}
          >
            <SelectItem key="planned">{t("common.planned")}</SelectItem>
            <SelectItem key="in_progress">{t("common.in_progress")}</SelectItem>
            <SelectItem key="review">{t("common.in_review")}</SelectItem>
            <SelectItem key="completed">{t("completed")}</SelectItem>
          </Select>
          <Input
            label={t("deliverability.due_date_label")}
            type="date"
            variant="bordered"
            value={formData.due_date}
            onValueChange={(v) => handleChange('due_date', v)}
          />
          <Input
            label={t("deliverability.assigned_to_label")}
            placeholder={t("deliverability.assigned_to_placeholder")}
            variant="bordered"
            value={formData.assigned_to}
            onValueChange={(v) => handleChange('assigned_to', v)}
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="flat" onPress={() => navigate(tenantPath('/admin/deliverability/list'))}>{t("common.cancel")}</Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
              isDisabled={saving}
            >
              {t('deliverability.save_deliverable')}
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CreateDeliverable;
