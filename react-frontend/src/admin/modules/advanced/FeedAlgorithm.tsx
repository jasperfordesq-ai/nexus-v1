// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Feed Algorithm
 * Configure the social feed ranking algorithm parameters.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Slider, Switch, Button, Spinner } from '@heroui/react';
import { Rss, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

import { useTranslation } from 'react-i18next';
export function FeedAlgorithm() {
  const { t } = useTranslation('admin');
  usePageTitle(t('advanced.page_title'));
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<Record<string, unknown>>({
    recency_weight: 70,
    engagement_weight: 50,
    connection_weight: 40,
    diversity_factor: 30,
    chronological_mode: false,
    include_polls: true,
    include_events: true,
  });

  useEffect(() => {
    adminSettings.getFeedAlgorithm()
      .then(res => {
        if (res.data) {
          setFormData(prev => ({ ...prev, ...res.data }));
        }
      })
      .catch(() => toast.error(t('advanced.failed_to_load_feed_algorithm_settings')))
      .finally(() => setLoading(false));
  }, [toast, t])

  const handleSave = async () => {
    // Validate numeric weights before sending to API
    const weightFields = ['recency_weight', 'engagement_weight', 'connection_weight', 'diversity_factor'] as const;
    for (const field of weightFields) {
      const val = Number(formData[field]);
      if (!Number.isFinite(val) || val < 0 || val > 100) {
        toast.error(t('advanced.invalid_weight', { field }));
        return;
      }
    }

    setSaving(true);
    try {
      const res = await adminSettings.updateFeedAlgorithm(formData);

      if (res.success) {
        toast.success(t('advanced.feed_algorithm_settings_saved_successful'));
      } else {
        const error = (res as { error?: string }).error || t('advanced.save_failed');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('advanced.failed_to_save_feed_algorithm_settings'));
    } finally {
      setSaving(false);
    }
  };

  const updateField = (key: string, value: unknown) => {
    setFormData(prev => ({ ...prev, [key]: value }));
  };

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={t('advanced.feed_algorithm_title')} description={t('advanced.feed_algorithm_desc')} />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Rss size={20} /> {t('advanced.feed_ranking_weights')}</h3></CardHeader>
          <CardBody className="gap-6">
            <div>
              <p className="text-sm font-medium mb-2">{t('advanced.recency_weight')}</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.recency_weight)} onChange={(v) => updateField('recency_weight', v)} step={5} label={t('advanced.label_how_much_to_prioritize_newer_content')} className="max-w-md" />
            </div>
            <div>
              <p className="text-sm font-medium mb-2">{t('advanced.engagement_weight')}</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.engagement_weight)} onChange={(v) => updateField('engagement_weight', v)} step={5} label={t('advanced.label_how_much_to_prioritize_likedcommented_content')} className="max-w-md" />
            </div>
            <div>
              <p className="text-sm font-medium mb-2">{t('advanced.connection_weight')}</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.connection_weight)} onChange={(v) => updateField('connection_weight', v)} step={5} label={t('advanced.label_boost_content_from_connected_users')} className="max-w-md" />
            </div>
            <div>
              <p className="text-sm font-medium mb-2">{t('advanced.diversity_factor')}</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.diversity_factor)} onChange={(v) => updateField('diversity_factor', v)} step={5} label={t('advanced.label_vary_content_types_in_the_feed')} className="max-w-md" />
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{t('advanced.feed_options')}</h3></CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('advanced.chronological_mode')}</p>
                <p className="text-sm text-default-500">{t('advanced.chronological_mode_desc')}</p>
              </div>
              <Switch isSelected={!!formData.chronological_mode} onValueChange={(v) => updateField('chronological_mode', v)} aria-label={t('advanced.label_chronological_mode')} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('advanced.include_polls')}</p>
                <p className="text-sm text-default-500">{t('advanced.include_polls_desc')}</p>
              </div>
              <Switch isSelected={!!formData.include_polls} onValueChange={(v) => updateField('include_polls', v)} aria-label={t('advanced.label_include_polls')} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('advanced.include_events')}</p>
                <p className="text-sm text-default-500">{t('advanced.include_events_desc')}</p>
              </div>
              <Switch isSelected={!!formData.include_events} onValueChange={(v) => updateField('include_events', v)} aria-label={t('advanced.label_include_events')} />
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving} isDisabled={saving}>{t('advanced.save_algorithm')}</Button>
        </div>
      </div>
    </div>
  );
}

export default FeedAlgorithm;
