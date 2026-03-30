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
import { ArrowLeft, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { PageHeader } from '../../components';
import { useTranslation } from 'react-i18next';
import type { Campaign, BadgeDefinition } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const STATUS_OPTIONS = [
  { key: 'draft', label: 'Draft' },
  { key: 'active', label: 'Active' },
  { key: 'paused', label: 'Paused' },
  { key: 'completed', label: 'Completed' },
] as const;

const AUDIENCE_OPTIONS = [
  { key: 'all_users', label: 'All Active Users' },
  { key: 'new_users', label: 'New Users (last 30 days)' },
  { key: 'active_users', label: 'Active Users (logged in this week)' },
  { key: 'inactive_users', label: 'Inactive Users (30+ days)' },
  { key: 'level_range', label: 'Users at specific level range' },
  { key: 'badge_holders', label: 'Users with specific badge' },
] as const;

const TYPE_OPTIONS = [
  { key: 'one_time', label: 'One Time' },
  { key: 'recurring', label: 'Recurring' },
  { key: 'triggered', label: 'Triggered' },
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
  usePageTitle(isEdit ? t('gamification.edit_campaign_title') : t('gamification.create_campaign_title'));
  const toast = useToast();
  const navigate = useNavigate();

  const [formData, setFormData] = useState<FormData>(INITIAL_FORM);
  const [badges, setBadges] = useState<BadgeDefinition[]>([]);
  const [loadingBadges, setLoadingBadges] = useState(true);
  const [loadingCampaign, setLoadingCampaign] = useState(isEdit);
  const [saving, setSaving] = useState(false);

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
        navigate('/admin/gamification/campaigns');
      }
    }
    setLoadingCampaign(false);
  }, [id, toast, navigate]);

  useEffect(() => {
    if (isEdit) {
      loadCampaign();
    }
  }, [isEdit, loadCampaign]);

  const updateField = <K extends keyof FormData>(key: K, value: FormData[K]) => {
    setFormData((prev) => ({ ...prev, [key]: value }));
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.error(t('gamification.campaign_name_required'));
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
        navigate('/admin/gamification/campaigns');
      } else {
        const errorMsg = (res as { error?: string }).error
          || (res as { errors?: Array<{ message: string }> }).errors?.[0]?.message
          || t('gamification.failed_to_update_campaign');
        toast.error(errorMsg);
      }
    } else {
      const res = await adminGamification.createCampaign(payload);
      if (res.success) {
        toast.success(t('gamification.campaign_created'));
        navigate('/admin/gamification/campaigns');
      } else {
        const errorMsg = (res as { error?: string }).error
          || (res as { errors?: Array<{ message: string }> }).errors?.[0]?.message
          || t('gamification.failed_to_create_campaign');
        toast.error(errorMsg);
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
        title={isEdit ? t('gamification.edit_campaign') : t('gamification.create_campaign')}
        description={isEdit ? t('gamification.edit_campaign_desc') : t('gamification.create_campaign_desc')}
        actions={
          <Link to="/admin/gamification/campaigns">
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
            label={t('gamification.label_name')}
            placeholder={t('gamification.placeholder_campaign_name')}
            value={formData.name}
            onValueChange={(v) => updateField('name', v)}
            isRequired
            variant="bordered"
            autoFocus
          />

          <Textarea
            label={t('gamification.label_description')}
            placeholder={t('gamification.placeholder_campaign_desc')}
            value={formData.description}
            onValueChange={(v) => updateField('description', v)}
            variant="bordered"
            minRows={3}
          />

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Select
              label={t('gamification.label_status')}
              selectedKeys={new Set([formData.status])}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) updateField('status', selected);
              }}
              variant="bordered"
            >
              {STATUS_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{t(`gamification.status_${opt.key}`, { defaultValue: opt.label })}</SelectItem>
              ))}
            </Select>

            <Select
              label={t('gamification.label_campaign_type')}
              selectedKeys={new Set([formData.type])}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                if (selected) updateField('type', selected);
              }}
              variant="bordered"
            >
              {TYPE_OPTIONS.map((opt) => (
                <SelectItem key={opt.key}>{t(`gamification.type_${opt.key}`, { defaultValue: opt.label })}</SelectItem>
              ))}
            </Select>
          </div>

          <Select
            label={t('gamification.label_badge_to_award')}
            selectedKeys={formData.badge_key ? new Set([formData.badge_key]) : new Set()}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0] as string;
              updateField('badge_key', selected || '');
            }}
            variant="bordered"
            isLoading={loadingBadges}
            placeholder={t('gamification.placeholder_select_badge')}
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
            label={t('gamification.label_xp_amount')}
            type="number"
            placeholder="0"
            value={formData.xp_amount}
            onValueChange={(v) => updateField('xp_amount', v)}
            variant="bordered"
            description={t('gamification.desc_bonus_xp')}
          />

          <Select
            label={t('gamification.label_target_audience')}
            selectedKeys={new Set([formData.target_audience])}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0] as string;
              if (selected) updateField('target_audience', selected);
            }}
            variant="bordered"
          >
            {AUDIENCE_OPTIONS.map((opt) => (
              <SelectItem key={opt.key}>{t(`gamification.audience_${opt.key}`, { defaultValue: opt.label })}</SelectItem>
            ))}
          </Select>

          {formData.type === 'recurring' && (
            <Select
              label={t('gamification.label_schedule')}
              selectedKeys={formData.schedule ? new Set([formData.schedule]) : new Set()}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0] as string;
                updateField('schedule', selected || '');
              }}
              variant="bordered"
              placeholder={t('gamification.placeholder_select_frequency')}
            >
              <SelectItem key="daily">{t('gamification.schedule_daily')}</SelectItem>
              <SelectItem key="weekly">{t('gamification.schedule_weekly')}</SelectItem>
              <SelectItem key="monthly">{t('gamification.schedule_monthly')}</SelectItem>
            </Select>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <Link to="/admin/gamification/campaigns">
              <Button variant="flat" isDisabled={saving}>{t('gamification.cancel')}</Button>
            </Link>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
            >
              {isEdit ? t('gamification.save_changes') : t('gamification.create_campaign')}
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CampaignForm;
