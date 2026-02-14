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

const AI_FEATURES = ['Smart Matching Suggestions', 'Content Moderation', 'Chat Assistant', 'Auto-Categorization'];
const FEATURE_KEYS = ['smart_matching', 'content_moderation', 'chat_assistant', 'auto_categorization'];

export function AiSettings() {
  usePageTitle('Admin - AI Settings');
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
      .catch(() => toast.error('Failed to load AI settings'))
      .finally(() => setLoading(false));
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      await adminSettings.updateAiConfig(formData);
      toast.success('AI settings saved successfully');
    } catch {
      toast.error('Failed to save AI settings');
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
      <PageHeader title="AI Settings" description="Configure AI providers and integration options" />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Bot size={20} /> AI Integration</h3></CardHeader>
          <CardBody className="gap-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Enable AI Features</p>
                <p className="text-sm text-default-500">Enable AI-powered features across the platform</p>
              </div>
              <Switch isSelected={!!formData.enabled} onValueChange={(v) => updateField('enabled', v)} aria-label="Enable AI" />
            </div>
            <Select
              label="AI Provider"
              selectedKeys={[String(formData.provider || 'openai')]}
              onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) updateField('provider', String(v)); }}
              variant="bordered"
            >
              <SelectItem key="openai">OpenAI (GPT-4)</SelectItem>
              <SelectItem key="anthropic">Anthropic (Claude)</SelectItem>
              <SelectItem key="local">Local Model</SelectItem>
            </Select>
            <Input
              label="API Key"
              type="password"
              placeholder="sk-..."
              variant="bordered"
              description="Your AI provider API key (stored encrypted)"
              value={String(formData.api_key || '')}
              onValueChange={(v) => updateField('api_key', v)}
            />
            <Input
              label="Model"
              placeholder="gpt-4"
              variant="bordered"
              value={String(formData.model || '')}
              onValueChange={(v) => updateField('model', v)}
            />
            <Input
              label="Max Tokens"
              type="number"
              placeholder="2048"
              variant="bordered"
              value={String(formData.max_tokens || '')}
              onValueChange={(v) => updateField('max_tokens', v)}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">AI Features</h3></CardHeader>
          <CardBody className="space-y-3">
            {AI_FEATURES.map((feature, index) => (
              <div key={feature} className="flex items-center justify-between py-1">
                <p className="text-sm">{feature}</p>
                <Switch
                  size="sm"
                  isSelected={!!formData[FEATURE_KEYS[index]]}
                  onValueChange={(v) => updateField(FEATURE_KEYS[index], v)}
                  aria-label={feature}
                />
              </div>
            ))}
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving}>Save Settings</Button>
        </div>
      </div>
    </div>
  );
}

export default AiSettings;
