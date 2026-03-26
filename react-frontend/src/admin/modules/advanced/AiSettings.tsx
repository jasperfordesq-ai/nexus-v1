// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AI Settings
 * Configure AI providers and integration settings for the platform.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Switch, Button, Select, SelectItem, Spinner } from '@heroui/react';
import { Bot, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

import { useTranslation } from 'react-i18next';
const FEATURE_KEYS = ['smart_matching', 'content_moderation', 'chat_assistant', 'auto_categorization'];
const FEATURE_I18N_KEYS = ['advanced.feature_smart_matching', 'advanced.feature_content_moderation', 'advanced.feature_chat_assistant', 'advanced.feature_auto_categorization'];

export function AiSettings() {
  const { t } = useTranslation('admin');
  usePageTitle(t('advanced.page_title'));
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<Record<string, unknown>>({
    enabled: true,
    provider: 'openai',
    api_key: '',
    model: '',
    max_tokens: '',
    smart_matching: true,
    content_moderation: true,
    chat_assistant: true,
    auto_categorization: true,
  });

  useEffect(() => {
    adminSettings.getAiConfig()
      .then(res => {
        if (res.data) {
          setFormData(prev => ({ ...prev, ...res.data }));
        }
      })
      .catch(() => toast.error(t('advanced.failed_to_load_a_i_settings')))
      .finally(() => setLoading(false));
  }, [toast]);

  const handleSave = async () => {
    setSaving(true);
    try {
      const res = await adminSettings.updateAiConfig(formData);

      if (res.success) {
        toast.success(t('advanced.a_i_settings_saved_successfully'));
      } else {
        const error = (res as { error?: string }).error || t('advanced.save_failed');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('advanced.failed_to_save_a_i_settings'));
      console.error('AI settings save error:', err);
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
      <PageHeader title={t('advanced.ai_settings_title')} description={t('advanced.ai_settings_desc')} />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Bot size={20} /> {t('advanced.ai_integration_heading')}</h3></CardHeader>
          <CardBody className="gap-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('advanced.enable_ai_features')}</p>
                <p className="text-sm text-default-500">{t('advanced.enable_ai_features_desc')}</p>
              </div>
              <Switch isSelected={!!formData.enabled} onValueChange={(v) => updateField('enabled', v)} aria-label={t('advanced.label_enable_a_i')} />
            </div>
            <Select
              label={t('advanced.label_a_i_provider')}
              selectedKeys={[String(formData.provider || 'openai')]}
              onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) updateField('provider', String(v)); }}
              variant="bordered"
            >
              <SelectItem key="openai">OpenAI (GPT-4)</SelectItem>
              <SelectItem key="anthropic">Anthropic (Claude)</SelectItem>
              <SelectItem key="local">Local Model</SelectItem>
            </Select>
            <Input
              label={t('advanced.label_a_p_i_key')}
              type="password"
              placeholder="sk-..."
              variant="bordered"
              description={t('advanced.desc_your_a_i_provider_a_p_i_key_stored_encrypt')}
              value={String(formData.api_key || '')}
              onValueChange={(v) => updateField('api_key', v)}
            />
            <Input
              label={t('advanced.label_model')}
              placeholder="gpt-4"
              variant="bordered"
              value={String(formData.model || '')}
              onValueChange={(v) => updateField('model', v)}
            />
            <Input
              label={t('advanced.label_max_tokens')}
              type="number"
              placeholder="2048"
              variant="bordered"
              value={String(formData.max_tokens || '')}
              onValueChange={(v) => updateField('max_tokens', v)}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{t('advanced.ai_features_heading')}</h3></CardHeader>
          <CardBody className="space-y-3">
            {FEATURE_KEYS.map((featureKey, index) => (
              <div key={featureKey} className="flex items-center justify-between py-1">
                <p className="text-sm">{t(FEATURE_I18N_KEYS[index])}</p>
                <Switch
                  size="sm"
                  isSelected={!!formData[featureKey]}
                  onValueChange={(v) => updateField(featureKey, v)}
                  aria-label={t(FEATURE_I18N_KEYS[index])}
                />
              </div>
            ))}
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving} isDisabled={saving}>{t('advanced.save_settings')}</Button>
        </div>
      </div>
    </div>
  );
}

export default AiSettings;
