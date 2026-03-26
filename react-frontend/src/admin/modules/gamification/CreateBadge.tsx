// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Create Badge
 * Form to create a new custom badge.
 * Parity: PHP Admin\CustomBadgeController@store
 */

import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Input,
  Textarea,
  Select,
  SelectItem,
  Switch,
} from '@heroui/react';
import { ArrowLeft, Save, Award } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { PageHeader } from '../../components';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ICON_OPTIONS = [
  { key: 'award', label: 'Award' },
  { key: 'star', label: 'Star' },
  { key: 'trophy', label: 'Trophy' },
  { key: 'medal', label: 'Medal' },
  { key: 'shield', label: 'Shield' },
  { key: 'heart', label: 'Heart' },
  { key: 'zap', label: 'Lightning' },
  { key: 'flame', label: 'Flame' },
  { key: 'crown', label: 'Crown' },
  { key: 'gem', label: 'Gem' },
  { key: 'target', label: 'Target' },
  { key: 'rocket', label: 'Rocket' },
] as const;

const CATEGORY_OPTIONS = [
  { key: 'special', label: 'Special' },
  { key: 'achievement', label: 'Achievement' },
  { key: 'participation', label: 'Participation' },
  { key: 'milestone', label: 'Milestone' },
  { key: 'community', label: 'Community' },
  { key: 'expertise', label: 'Expertise' },
] as const;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

interface FormData {
  name: string;
  slug: string;
  description: string;
  icon: string;
  category: string;
  xp: number;
  is_active: boolean;
}

export function CreateBadge() {
  const { t } = useTranslation('admin');
  usePageTitle(t('gamification.page_title'));
  const toast = useToast();
  const navigate = useNavigate();

  const [formData, setFormData] = useState<FormData>({
    name: '',
    slug: '',
    description: '',
    icon: 'award',
    category: 'special',
    xp: 0,
    is_active: true,
  });
  const [saving, setSaving] = useState(false);

  const updateField = <K extends keyof FormData>(key: K, value: FormData[K]) => {
    setFormData((prev) => {
      const updated = { ...prev, [key]: value };
      // Auto-generate slug from name if slug hasn't been manually edited
      if (key === 'name' && typeof value === 'string') {
        const autoSlug = value
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, '_')
          .replace(/^_+|_+$/g, '');
        updated.slug = autoSlug;
      }
      return updated;
    });
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.error(t('gamification.badge_name_is_required'));
      return;
    }

    setSaving(true);

    const res = await adminGamification.createBadge({
      name: formData.name.trim(),
      slug: formData.slug.trim() || undefined,
      description: formData.description.trim(),
      icon: formData.icon,
      category: formData.category,
      xp: formData.xp,
      is_active: formData.is_active,
    });

    if (res.success) {
      toast.success(t('gamification.badge_created', { name: formData.name.trim() }));
      navigate('/admin/custom-badges');
    } else {
      const errorMsg = (res as { error?: string }).error
        || (res as { errors?: Array<{ message: string }> }).errors?.[0]?.message
        || t('gamification.failed_to_create_badge');
      toast.error(errorMsg);
    }

    setSaving(false);
  };

  return (
    <div>
      <PageHeader
        title={t('gamification.create_badge_title')}
        description={t('gamification.create_badge_desc')}
        actions={
          <Link to="/admin/custom-badges">
            <Button variant="flat" startContent={<ArrowLeft size={16} />}>
              {t('gamification.back_to_badges')}
            </Button>
          </Link>
        }
      />

      <Card shadow="sm" className="max-w-2xl">
        <CardHeader className="flex items-center gap-2 pb-0">
          <Award size={20} className="text-success" />
          <h3 className="text-lg font-semibold text-foreground">{t('gamification.badge_details')}</h3>
        </CardHeader>
        <CardBody className="gap-4">
          <Input
            label={t('gamification.label_name')}
            placeholder={t('gamification.placeholder_eg_community_champion')}
            value={formData.name}
            onValueChange={(v) => updateField('name', v)}
            isRequired
            variant="bordered"
            autoFocus
          />

          <Input
            label={t('gamification.label_slug')}
            placeholder={t('gamification.placeholder_auto_generated')}
            value={formData.slug}
            onValueChange={(v) => setFormData((prev) => ({ ...prev, slug: v }))}
            variant="bordered"
            description={t('gamification.desc_slug')}
            classNames={{ input: 'font-mono text-sm' }}
          />

          <Textarea
            label={t('gamification.label_description')}
            placeholder={t('gamification.placeholder_describe_what_this_badge_is_awarded_for')}
            value={formData.description}
            onValueChange={(v) => updateField('description', v)}
            variant="bordered"
            minRows={3}
          />

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Select
              label={t('gamification.label_icon')}
              selectedKeys={new Set([formData.icon])}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) updateField('icon', selected);
              }}
              variant="bordered"
            >
              {ICON_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{opt.label}</SelectItem>
              ))}
            </Select>

            <Select
              label={t('gamification.label_category')}
              selectedKeys={new Set([formData.category])}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) updateField('category', selected);
              }}
              variant="bordered"
            >
              {CATEGORY_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{opt.label}</SelectItem>
              ))}
            </Select>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label={t('gamification.label_x_p_value')}
              type="number"
              placeholder="0"
              value={String(formData.xp)}
              onValueChange={(v) => updateField('xp', parseInt(v) || 0 as never)}
              variant="bordered"
              min={0}
              max={10000}
              description={t('gamification.desc_x_p_awarded_when_this_badge_is_earned')}
            />
            <div className="flex items-center justify-between p-3 rounded-lg border border-default-200">
              <div>
                <p className="text-sm font-medium">{t('gamification.active')}</p>
                <p className="text-xs text-default-400">{t('gamification.active_badge_desc')}</p>
              </div>
              <Switch
                isSelected={formData.is_active}
                onValueChange={(v) => updateField('is_active', v as never)}
                size="sm"
              />
            </div>
          </div>

          {/* Preview */}
          <div className="rounded-lg border border-default-200 p-4">
            <p className="text-xs text-default-500 mb-2 uppercase tracking-wider font-semibold">{t('gamification.preview')}</p>
            <div className="flex items-center gap-3">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
                <Award size={24} />
              </div>
              <div>
                <p className="font-semibold text-foreground">{formData.name || t('gamification.badge_name_placeholder')}</p>
                <p className="text-sm text-default-500">{formData.description || t('gamification.badge_desc_placeholder')}</p>
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Link to="/admin/custom-badges">
              <Button variant="flat" isDisabled={saving}>{t('gamification.cancel')}</Button>
            </Link>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
            >
              {t('gamification.create_badge')}
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CreateBadge;
