// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Campaign Form
 * Create or edit a gamification campaign.
 * Detects edit mode via useParams().
 * Parity: PHP Admin\GamificationController@createCampaign / @editCampaign
 */

import { useState, useCallback, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Input,
  Textarea,
  Select,
  SelectItem,
  Spinner,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Save from 'lucide-react/icons/save';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { PageHeader } from '../../components';
import { useTranslation } from 'react-i18next';
import type { Campaign, BadgeDefinition } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const STATUS_OPTIONS = [
  { key: 'draft', labelKey: 'gamification.status_draft' },
  { key: 'active', labelKey: 'gamification.status_active' },
  { key: 'paused', labelKey: 'gamification.status_paused' },
  { key: 'completed', labelKey: 'gamification.status_completed' },
] as const;

const AUDIENCE_OPTIONS = [
  { key: 'all_users', labelKey: 'gamification.audience_all_users' },
  { key: 'new_users', labelKey: 'gamification.audience_new_users' },
  { key: 'active_users', labelKey: 'gamification.audience_active_users' },
  { key: 'inactive_users', labelKey: 'gamification.audience_inactive_users' },
  { key: 'level_range', labelKey: 'gamification.audience_level_range' },
  { key: 'badge_holders', labelKey: 'gamification.audience_badge_holders' },
] as const;

const TYPE_OPTIONS = [
  { key: 'one_time', labelKey: 'gamification.type_one_time' },
  { key: 'recurring', labelKey: 'gamification.type_recurring' },
  { key: 'triggered', labelKey: 'gamification.type_triggered' },
] as const;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

interface FormData {
  name: string;
  description: string;
  status: string;
  type: string;
  badge_key: string;
  xp_amount: string;
  target_audience: string;
  schedule: string;
}

const INITIAL_FORM: FormData = {
  name: '',
  description: '',
  status: 'draft',
  type: 'one_time',
  badge_key: '',
  xp_amount: '0',
  target_audience: 'all_users',
  schedule: '',
};

export function CampaignForm() {
  const { t } = useTranslation('admin');
  const { id } = useParams<{ id: string }>();
  const isEdit = !!id;
  usePageTitle(t(isEdit ? 'gamification.edit_campaign' : 'gamification.create_campaign'));
  const toast = useToast();
  const { tenantPath } = useTenant();
  const navigate = useNavigate();

  const [formData, setFormData] = useState<FormData>(INITIAL_FORM);
  const [badges, setBadges] = useState<BadgeDefinition[]>([]);
  const [loadingBadges, setLoadingBadges] = useState(true);
  const [loadingCampaign, setLoadingCampaign] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  // Load available badges
  useEffect(() => {
    (async () => {
      setLoadingBadges(true);
      const res = await adminGamification.listBadges();
      if (res.success && res.data) {
        // res.data is already unwrapped by the API client — never double-unwrap
        setBadges(Array.isArray(res.data) ? res.data : []);
      }
      setLoadingBadges(false);
    })();
  }, []);

  // Load campaign data for edit mode
  const loadCampaign = useCallback(async () => {
    if (!id) return;
    setLoadingCampaign(true);

    const res = await adminGamification.listCampaigns();
    if (res.success && res.data) {
      // res.data is already unwrapped by the API client — never double-unwrap
      const campaigns: Campaign[] = Array.isArray(res.data) ? res.data : [];

      const campaign = campaigns.find((c) => c.id === Number(id));
      if (campaign) {
        setFormData({
          name: campaign.name || '',
          description: campaign.description || '',
          status: campaign.status || 'draft',
          type: 'one_time',
          badge_key: campaign.badge_key || campaign.badge_name || '',
          xp_amount: '0',
          target_audience: campaign.target_audience || 'all_users',
          schedule: '',
        });
      } else {
        toast.error(t('gamification.campaign_not_found'));
        navigate(tenantPath('/admin/gamification/campaigns'));
      }
    }
    setLoadingCampaign(false);
  }, [id, toast, navigate, tenantPath, t]);

  useEffect(() => {
    if (isEdit) {
      loadCampaign();
    }
  }, [isEdit, loadCampaign]);

  const updateField = <K extends keyof FormData>(key: K, value: FormData[K]) => {
    setFormData((prev) => ({ ...prev, [key]: value }));
  };

  /** Extract field-level errors from API response errors object */
  function applyApiErrors(
    resErrors: Record<string, string | string[]> | Array<{ message: string }> | undefined,
  ): string | null {
    if (!resErrors) return null;
    if (Array.isArray(resErrors)) {
      // Generic array of messages — no field to bind
      return resErrors[0]?.message ?? null;
    }
    // Record<field, message> — bind per-field and return null (no generic toast)
    const mapped: Record<string, string> = {};
    for (const [field, msg] of Object.entries(resErrors)) {
      mapped[field] = Array.isArray(msg) ? (msg[0] ?? '') : (msg ?? '');
    }
    setFieldErrors(mapped);
    return null;
  }

  const handleSave = async () => {
    setFieldErrors({});

    if (!formData.name.trim()) {
      setFieldErrors({ name: t('gamification.campaign_name_required') });
      return;
    }

    setSaving(true);

    const payload = {
      name: formData.name.trim(),
      description: formData.description.trim(),
      status: formData.status as Campaign['status'],
      type: formData.type,
      badge_key: formData.badge_key,
      xp_amount: parseInt(formData.xp_amount, 10) || 0,
      target_audience: formData.target_audience,
      schedule: formData.schedule || undefined,
    };

    if (isEdit) {
      const res = await adminGamification.updateCampaign(Number(id), payload);
      if (res.success) {
        toast.success(t('gamification.campaign_updated'));
        navigate(tenantPath('/admin/gamification/campaigns'));
      } else {
        const resAny = res as { error?: string; errors?: Record<string, string | string[]> | Array<{ message: string }> };
        const genericMsg = applyApiErrors(resAny.errors) ?? resAny.error ?? t('gamification.failed_to_update_campaign');
        if (genericMsg) toast.error(genericMsg);
      }
    } else {
      const res = await adminGamification.createCampaign(payload);
      if (res.success) {
        toast.success(t('gamification.campaign_created'));
        navigate(tenantPath('/admin/gamification/campaigns'));
      } else {
        const resAny = res as { error?: string; errors?: Record<string, string | string[]> | Array<{ message: string }> };
        const genericMsg = applyApiErrors(resAny.errors) ?? resAny.error ?? t('gamification.failed_to_create_campaign');
        if (genericMsg) toast.error(genericMsg);
      }
    }

    setSaving(false);
  };

  if (loadingCampaign) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t(isEdit ? 'gamification.edit_campaign' : 'gamification.create_campaign')}
        description={t(isEdit ? 'gamification.edit_campaign_desc' : 'gamification.create_campaign_desc')}
        actions={
          <Link to={tenantPath("/admin/gamification/campaigns")}>
            <Button variant="flat" startContent={<ArrowLeft size={16} />}>
              {t('gamification.back_to_campaigns')}
            </Button>
          </Link>
        }
      />

      <Card shadow="sm" className="max-w-2xl">
        <CardHeader className="pb-0">
          <h3 className="text-lg font-semibold text-foreground">{t('gamification.campaign_details')}</h3>
        </CardHeader>
        <CardBody className="gap-4">
          <Input
            label={t('gamification.name')}
            placeholder={t('gamification.campaign_name_placeholder')}
            value={formData.name}
            onValueChange={(v) => { updateField('name', v); setFieldErrors((prev) => ({ ...prev, name: '' })); }}
            isRequired
            variant="bordered"
            autoFocus
            isInvalid={!!fieldErrors.name}
            errorMessage={fieldErrors.name}
          />

          <Textarea
            label={t('gamification.description')}
            placeholder={t('gamification.campaign_description_placeholder')}
            value={formData.description}
            onValueChange={(v) => updateField('description', v)}
            variant="bordered"
            minRows={3}
            isInvalid={!!fieldErrors.description}
            errorMessage={fieldErrors.description}
          />

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Select
              label={t('gamification.status')}
              selectedKeys={new Set([formData.status])}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) updateField('status', selected);
              }}
              variant="bordered"
            >
              {STATUS_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{t(opt.labelKey)}</SelectItem>
              ))}
            </Select>

            <Select
              label={t('gamification.campaign_type')}
              selectedKeys={new Set([formData.type])}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) updateField('type', selected);
              }}
              variant="bordered"
            >
              {TYPE_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{t(opt.labelKey)}</SelectItem>
              ))}
            </Select>
          </div>

          <Select
            label={t('gamification.badge_to_award')}
            selectedKeys={formData.badge_key ? new Set([formData.badge_key]) : new Set()}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0] as string;
              updateField('badge_key', selected || '');
            }}
            variant="bordered"
            isLoading={loadingBadges}
            placeholder={t('gamification.select_badge')}
          >
            {badges.map((badge) => (
              <SelectItem key={badge.key} textValue={badge.name}>
                <div className="flex items-center gap-2">
                  <span>{badge.name}</span>
                  <span className="text-xs text-default-400">({badge.type})</span>
                </div>
              </SelectItem>
            ))}
          </Select>

          <Input
            label={t('gamification.xp_amount')}
            type="number"
            placeholder="0"
            value={formData.xp_amount}
            onValueChange={(v) => updateField('xp_amount', v)}
            variant="bordered"
            description={t('gamification.bonus_xp')}
            isInvalid={!!fieldErrors.xp_amount}
            errorMessage={fieldErrors.xp_amount}
          />

          <Select
            label={t('gamification.target_audience')}
            selectedKeys={new Set([formData.target_audience])}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0] as string;
              if (selected) updateField('target_audience', selected);
            }}
            variant="bordered"
          >
            {AUDIENCE_OPTIONS.map((opt) => (
              <SelectItem key={opt.key}>{t(opt.labelKey)}</SelectItem>
            ))}
          </Select>

          {formData.type === 'recurring' && (
            <Select
              label={t('gamification.schedule')}
              selectedKeys={formData.schedule ? new Set([formData.schedule]) : new Set()}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                updateField('schedule', selected || '');
              }}
              variant="bordered"
              placeholder={t('gamification.select_frequency')}
            >
              <SelectItem key="daily">{t('gamification.schedule_daily')}</SelectItem>
              <SelectItem key="weekly">{t('gamification.schedule_weekly')}</SelectItem>
              <SelectItem key="monthly">{t('gamification.schedule_monthly')}</SelectItem>
            </Select>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <Link to={tenantPath("/admin/gamification/campaigns")}>
              <Button variant="flat" isDisabled={saving}>{t('cancel')}</Button>
            </Link>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
            >
              {isEdit ? t('federation.save_changes', 'Save Changes') : t('gamification.create_campaign')}
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CampaignForm;
