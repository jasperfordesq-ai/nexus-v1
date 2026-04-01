// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AI Settings
 * Configure AI providers and integration settings for the platform.
 *
 * Backend keys (PUT /v2/admin/config/ai):
 *   ai_enabled, ai_provider,
 *   gemini_api_key, openai_api_key, anthropic_api_key,
 *   gemini_model, openai_model, claude_model,
 *   ollama_model, ollama_host,
 *   ai_chat_enabled, ai_content_gen_enabled,
 *   ai_recommendations_enabled, ai_analytics_enabled, ai_moderation_enabled,
 *   default_daily_limit, default_monthly_limit
 */

import { useState, useEffect, useCallback } from 'react';
import { Card, CardBody, CardHeader, Input, Switch, Button, Select, SelectItem, Spinner, Divider, Chip } from '@heroui/react';
import { Bot, Save, Key, Cpu, Sliders, Shield } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';
import { useTranslation } from 'react-i18next';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type ProviderId = 'gemini' | 'openai' | 'anthropic' | 'ollama';

interface AiConfigResponse {
  ai_enabled: boolean;
  ai_provider: string;
  models: Record<string, string>;
  api_keys: Record<string, string | null>;
  api_key_set: Record<string, boolean>;
  features: {
    chat: boolean;
    content_generation: boolean;
    recommendations: boolean;
    analytics: boolean;
    moderation: boolean;
  };
  limits: {
    default_daily: number;
    default_monthly: number;
  };
  ollama: {
    host: string;
  };
}

interface FormState {
  ai_enabled: boolean;
  ai_provider: ProviderId;
  // Per-provider API keys — only populated when user types a NEW key
  gemini_api_key: string;
  openai_api_key: string;
  anthropic_api_key: string;
  // Per-provider models
  gemini_model: string;
  openai_model: string;
  claude_model: string;
  ollama_model: string;
  // Ollama host
  ollama_host: string;
  // Feature toggles
  ai_chat_enabled: boolean;
  ai_content_gen_enabled: boolean;
  ai_recommendations_enabled: boolean;
  ai_analytics_enabled: boolean;
  ai_moderation_enabled: boolean;
  // Usage limits
  default_daily_limit: string;
  default_monthly_limit: string;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const PROVIDERS: { key: ProviderId; label: string; keyField: keyof FormState; modelField: keyof FormState }[] = [
  { key: 'openai', label: 'OpenAI', keyField: 'openai_api_key', modelField: 'openai_model' },
  { key: 'anthropic', label: 'Anthropic', keyField: 'anthropic_api_key', modelField: 'claude_model' },
  { key: 'gemini', label: 'Google Gemini', keyField: 'gemini_api_key', modelField: 'gemini_model' },
  { key: 'ollama', label: 'Ollama (Self-hosted)', keyField: 'ollama_host' as keyof FormState, modelField: 'ollama_model' },
];

const MODEL_SUGGESTIONS: Record<ProviderId, string[]> = {
  openai: ['gpt-4o', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo'],
  anthropic: ['claude-sonnet-4-20250514', 'claude-3-5-sonnet-20241022', 'claude-3-haiku-20240307'],
  gemini: ['gemini-pro', 'gemini-1.5-pro', 'gemini-1.5-flash'],
  ollama: ['llama2', 'llama3', 'mistral', 'codellama'],
};

const FEATURE_TOGGLES: { key: keyof FormState; label: string; description: string }[] = [
  { key: 'ai_chat_enabled', label: 'AI Chat Assistant', description: 'Allow members to chat with an AI assistant' },
  { key: 'ai_content_gen_enabled', label: 'Content Generation', description: 'AI-powered content suggestions and generation' },
  { key: 'ai_recommendations_enabled', label: 'Smart Recommendations', description: 'AI-driven listing and member recommendations' },
  { key: 'ai_analytics_enabled', label: 'AI Analytics', description: 'AI-powered insights and analytics dashboards' },
  { key: 'ai_moderation_enabled', label: 'Content Moderation', description: 'Automatic AI content moderation and flagging' },
];

const DEFAULT_FORM: FormState = {
  ai_enabled: false,
  ai_provider: 'openai',
  gemini_api_key: '',
  openai_api_key: '',
  anthropic_api_key: '',
  gemini_model: 'gemini-pro',
  openai_model: 'gpt-4-turbo',
  claude_model: 'claude-sonnet-4-20250514',
  ollama_model: 'llama2',
  ollama_host: 'http://localhost:11434',
  ai_chat_enabled: false,
  ai_content_gen_enabled: false,
  ai_recommendations_enabled: false,
  ai_analytics_enabled: false,
  ai_moderation_enabled: false,
  default_daily_limit: '50',
  default_monthly_limit: '1000',
};

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function AiSettings() {
  const { t } = useTranslation('admin');
  usePageTitle(t('advanced.page_title'));
  const toast = useToast();

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState<FormState>({ ...DEFAULT_FORM });

  // Track which provider keys are already set on the server (for masked display)
  const [apiKeySet, setApiKeySet] = useState<Record<string, boolean>>({});
  // Track masked display values from the server
  const [maskedKeys, setMaskedKeys] = useState<Record<string, string>>({});
  // Track which key fields the user has actively edited (so we only send changed keys)
  const [dirtyKeys, setDirtyKeys] = useState<Set<string>>(new Set());

  // Map backend GET response → form state
  const mapResponseToForm = useCallback((data: AiConfigResponse): FormState => {
    return {
      ai_enabled: !!data.ai_enabled,
      ai_provider: (data.ai_provider || 'openai') as ProviderId,
      // API key fields start empty — we never put masked values in editable fields
      gemini_api_key: '',
      openai_api_key: '',
      anthropic_api_key: '',
      // Models
      gemini_model: data.models?.gemini || DEFAULT_FORM.gemini_model,
      openai_model: data.models?.openai || DEFAULT_FORM.openai_model,
      claude_model: data.models?.anthropic || DEFAULT_FORM.claude_model,
      ollama_model: data.models?.ollama || DEFAULT_FORM.ollama_model,
      // Ollama
      ollama_host: data.ollama?.host || DEFAULT_FORM.ollama_host,
      // Features
      ai_chat_enabled: !!data.features?.chat,
      ai_content_gen_enabled: !!data.features?.content_generation,
      ai_recommendations_enabled: !!data.features?.recommendations,
      ai_analytics_enabled: !!data.features?.analytics,
      ai_moderation_enabled: !!data.features?.moderation,
      // Limits
      default_daily_limit: String(data.limits?.default_daily ?? 50),
      default_monthly_limit: String(data.limits?.default_monthly ?? 1000),
    };
  }, []);

  useEffect(() => {
    adminSettings.getAiConfig()
      .then(res => {
        if (res.data) {
          const data = res.data as unknown as AiConfigResponse;
          setForm(mapResponseToForm(data));
          setApiKeySet(data.api_key_set || {});
          setMaskedKeys(
            Object.fromEntries(
              Object.entries(data.api_keys || {}).filter(([, v]) => v != null) as [string, string][]
            )
          );
        }
      })
      .catch(() => toast.error(t('advanced.failed_to_load_a_i_settings')))
      .finally(() => setLoading(false));
  }, [toast, t, mapResponseToForm]);

  const updateField = (key: keyof FormState, value: unknown) => {
    setForm(prev => ({ ...prev, [key]: value }));
  };

  const updateApiKey = (field: keyof FormState, value: string) => {
    setForm(prev => ({ ...prev, [field]: value }));
    setDirtyKeys(prev => new Set(prev).add(field));
  };

  // Build payload with ONLY the backend-recognized keys
  const buildPayload = (): Record<string, string | boolean> => {
    const payload: Record<string, string | boolean> = {
      ai_enabled: form.ai_enabled,
      ai_provider: form.ai_provider,
      // Models for all providers
      gemini_model: form.gemini_model,
      openai_model: form.openai_model,
      claude_model: form.claude_model,
      ollama_model: form.ollama_model,
      ollama_host: form.ollama_host,
      // Features
      ai_chat_enabled: form.ai_chat_enabled,
      ai_content_gen_enabled: form.ai_content_gen_enabled,
      ai_recommendations_enabled: form.ai_recommendations_enabled,
      ai_analytics_enabled: form.ai_analytics_enabled,
      ai_moderation_enabled: form.ai_moderation_enabled,
      // Limits
      default_daily_limit: form.default_daily_limit,
      default_monthly_limit: form.default_monthly_limit,
    };

    // Only send API keys that the user actually typed (non-empty, dirty)
    const keyFields = ['gemini_api_key', 'openai_api_key', 'anthropic_api_key'] as const;
    for (const field of keyFields) {
      if (dirtyKeys.has(field) && form[field].trim()) {
        payload[field] = form[field].trim();
      }
    }

    return payload;
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload = buildPayload();
      const res = await adminSettings.updateAiConfig(payload);

      if (res.success) {
        toast.success(t('advanced.a_i_settings_saved_successfully'));
        // Refresh to get updated masked keys & key-set status
        const refreshed = await adminSettings.getAiConfig();
        if (refreshed.data) {
          const data = refreshed.data as unknown as AiConfigResponse;
          setForm(mapResponseToForm(data));
          setApiKeySet(data.api_key_set || {});
          setMaskedKeys(
            Object.fromEntries(
              Object.entries(data.api_keys || {}).filter(([, v]) => v != null) as [string, string][]
            )
          );
          setDirtyKeys(new Set());
        }
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
        {/* Master Enable + Provider Selection */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Bot size={20} /> {t('advanced.ai_integration_heading')}
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('advanced.enable_ai_features')}</p>
                <p className="text-sm text-default-500">{t('advanced.enable_ai_features_desc')}</p>
              </div>
              <Switch
                isSelected={form.ai_enabled}
                onValueChange={(v) => updateField('ai_enabled', v)}
                aria-label={t('advanced.label_enable_a_i')}
              />
            </div>

            <Select
              label={t('advanced.label_a_i_provider')}
              selectedKeys={[form.ai_provider]}
              onSelectionChange={(keys) => {
                const v = Array.from(keys)[0];
                if (v) updateField('ai_provider', String(v));
              }}
              variant="bordered"
              description="Select the primary AI provider for this tenant"
            >
              {PROVIDERS.map(p => (
                <SelectItem key={p.key}>{p.label}</SelectItem>
              ))}
            </Select>
          </CardBody>
        </Card>

        {/* Provider Configuration */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Key size={20} /> Provider Configuration
            </h3>
          </CardHeader>
          <CardBody className="gap-6">
            {PROVIDERS.map(provider => {
              const isActive = form.ai_provider === provider.key;
              const isOllama = provider.key === 'ollama';
              const apiKeyField = (provider.key + '_api_key') as keyof FormState;
              const hasKeySet = apiKeySet[provider.key] ?? false;
              const masked = maskedKeys[provider.key] ?? '';
              const userTyped = dirtyKeys.has(apiKeyField) && (form[apiKeyField] as string).trim() !== '';

              return (
                <div key={provider.key} className="space-y-3">
                  <div className="flex items-center gap-2">
                    <p className="font-medium">{provider.label}</p>
                    {isActive && <Chip size="sm" color="primary" variant="flat">Active</Chip>}
                    {!isOllama && hasKeySet && !userTyped && (
                      <Chip size="sm" color="success" variant="flat">Key configured</Chip>
                    )}
                  </div>

                  {!isOllama ? (
                    <Input
                      label="API Key"
                      type="password"
                      placeholder={hasKeySet ? `Current: ${masked}` : 'Enter API key...'}
                      variant="bordered"
                      description={
                        hasKeySet
                          ? 'A key is already saved. Enter a new value to replace it, or leave empty to keep the current key.'
                          : 'Enter your API key. It will be stored securely.'
                      }
                      value={form[apiKeyField] as string}
                      onValueChange={(v) => updateApiKey(apiKeyField, v)}
                    />
                  ) : (
                    <Input
                      label="Ollama Host URL"
                      placeholder="http://localhost:11434"
                      variant="bordered"
                      description="The URL of your self-hosted Ollama instance"
                      value={form.ollama_host}
                      onValueChange={(v) => updateField('ollama_host', v)}
                    />
                  )}

                  <Select
                    label="Model"
                    selectedKeys={[form[provider.modelField] as string]}
                    onSelectionChange={(keys) => {
                      const v = Array.from(keys)[0];
                      if (v) updateField(provider.modelField, String(v));
                    }}
                    variant="bordered"
                    description="Select or type a model name"
                  >
                    {MODEL_SUGGESTIONS[provider.key].map(m => (
                      <SelectItem key={m}>{m}</SelectItem>
                    ))}
                  </Select>

                  {provider.key !== PROVIDERS[PROVIDERS.length - 1]!.key && <Divider />}
                </div>
              );
            })}
          </CardBody>
        </Card>

        {/* Feature Toggles */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Cpu size={20} /> AI Features
            </h3>
          </CardHeader>
          <CardBody className="space-y-3">
            {FEATURE_TOGGLES.map(feat => (
              <div key={feat.key} className="flex items-center justify-between py-1">
                <div>
                  <p className="text-sm font-medium">{feat.label}</p>
                  <p className="text-xs text-default-400">{feat.description}</p>
                </div>
                <Switch
                  size="sm"
                  isSelected={!!form[feat.key]}
                  onValueChange={(v) => updateField(feat.key, v)}
                  aria-label={feat.label}
                />
              </div>
            ))}
          </CardBody>
        </Card>

        {/* Usage Limits */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Sliders size={20} /> Usage Limits
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label="Default Daily Limit"
              type="number"
              placeholder="50"
              variant="bordered"
              description="Maximum AI requests per user per day"
              value={form.default_daily_limit}
              onValueChange={(v) => updateField('default_daily_limit', v)}
            />
            <Input
              label="Default Monthly Limit"
              type="number"
              placeholder="1000"
              variant="bordered"
              description="Maximum AI requests per user per month"
              value={form.default_monthly_limit}
              onValueChange={(v) => updateField('default_monthly_limit', v)}
            />
          </CardBody>
        </Card>

        {/* Security Note */}
        <Card shadow="sm">
          <CardBody>
            <div className="flex items-start gap-3">
              <Shield size={20} className="text-default-400 mt-0.5 shrink-0" />
              <div>
                <p className="text-sm font-medium">Security</p>
                <p className="text-xs text-default-400">
                  API keys are stored in the database per-tenant. Existing keys are masked when displayed.
                  Empty key fields on save will not overwrite existing keys — only enter a value when you want to change it.
                </p>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Save Button */}
        <div className="flex justify-end">
          <Button
            color="primary"
            startContent={<Save size={16} />}
            onPress={handleSave}
            isLoading={saving}
            isDisabled={saving}
          >
            {t('advanced.save_settings')}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default AiSettings;
