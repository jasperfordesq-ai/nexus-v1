// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Email Settings
 * Configure email providers and delivery settings for the platform.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Button, Select, SelectItem, Spinner, Chip } from '@heroui/react';
import { Mail, Save, Send, Shield, Globe, Copy, Check } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';
import type { ApiResponse } from '@/lib/api';

const PROVIDERS = [
  { key: 'platform_default', label: 'Platform Default' },
  { key: 'sendgrid', label: 'SendGrid' },
  { key: 'gmail_api', label: 'Gmail API' },
  { key: 'smtp', label: 'Custom SMTP' },
];

interface EmailSettingsForm {
  provider: string;
  sendgrid_api_key: string;
  sendgrid_from_email: string;
  sendgrid_from_name: string;
  webhook_url: string;
  gmail_client_id: string;
  gmail_client_secret: string;
  gmail_refresh_token: string;
  gmail_sender_email: string;
  gmail_sender_name: string;
  smtp_host: string;
  smtp_port: string;
  smtp_user: string;
  smtp_password: string;
  smtp_encryption: string;
  smtp_from_email: string;
  smtp_from_name: string;
  platform_default: { provider: string };
  // Track which secrets are saved on the server
  _sendgrid_api_key_set: boolean;
  _gmail_client_secret_set: boolean;
  _gmail_refresh_token_set: boolean;
  _smtp_password_set: boolean;
}

const INITIAL_FORM: EmailSettingsForm = {
  provider: 'platform_default',
  sendgrid_api_key: '',
  sendgrid_from_email: '',
  sendgrid_from_name: '',
  webhook_url: '',
  gmail_client_id: '',
  gmail_client_secret: '',
  gmail_refresh_token: '',
  gmail_sender_email: '',
  gmail_sender_name: '',
  smtp_host: '',
  smtp_port: '587',
  smtp_user: '',
  smtp_password: '',
  smtp_encryption: 'tls',
  smtp_from_email: '',
  smtp_from_name: '',
  platform_default: { provider: 'unknown' },
  _sendgrid_api_key_set: false,
  _gmail_client_secret_set: false,
  _gmail_refresh_token_set: false,
  _smtp_password_set: false,
};

export function EmailSettings() {
  usePageTitle('Admin - Email Settings');
  const toast = useToast();
  const { tenant } = useTenant();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testEmail, setTestEmail] = useState('');
  const [copied, setCopied] = useState(false);
  const [formData, setFormData] = useState<EmailSettingsForm>(INITIAL_FORM);

  useEffect(() => {
    adminSettings.getEmailConfig()
      .then((res: ApiResponse<Record<string, unknown>>) => {
        const data = res.data;
        if (data) {
          const sg = data.sendgrid as Record<string, unknown> | undefined;
          const smtp = data.smtp as Record<string, unknown> | undefined;
          const gmail = data.gmail_api as Record<string, unknown> | undefined;
          setFormData({
            ...INITIAL_FORM,
            provider: String(data.provider ?? 'platform_default'),
            webhook_url: String(data.webhook_url ?? ''),
            platform_default: (data.platform_default as { provider: string }) ?? { provider: 'unknown' },
            // SendGrid
            sendgrid_api_key: sg?.api_key_set ? '********' : '',
            sendgrid_from_email: String(sg?.from_email ?? ''),
            sendgrid_from_name: String(sg?.from_name ?? ''),
            _sendgrid_api_key_set: !!sg?.api_key_set,
            // Gmail API
            gmail_client_id: String(gmail?.client_id ?? ''),
            gmail_client_secret: gmail?.client_secret_set ? '********' : '',
            gmail_refresh_token: gmail?.refresh_token_set ? '********' : '',
            gmail_sender_email: String(gmail?.sender_email ?? ''),
            gmail_sender_name: String(gmail?.sender_name ?? ''),
            _gmail_client_secret_set: !!gmail?.client_secret_set,
            _gmail_refresh_token_set: !!gmail?.refresh_token_set,
            // SMTP
            smtp_host: String(smtp?.host ?? ''),
            smtp_port: String(smtp?.port ?? '587'),
            smtp_user: String(smtp?.user ?? ''),
            smtp_password: smtp?.password_set ? '********' : '',
            smtp_encryption: String(smtp?.encryption ?? 'tls'),
            smtp_from_email: String(smtp?.from_email ?? ''),
            smtp_from_name: String(smtp?.from_name ?? ''),
            _smtp_password_set: !!smtp?.password_set,
          });
        }
      })
      .catch(() => toast.error('Failed to load email settings'))
      .finally(() => setLoading(false));
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        email_provider: formData.provider,
      };

      if (formData.provider === 'sendgrid') {
        payload.sendgrid_api_key = formData.sendgrid_api_key;
        payload.sendgrid_from_email = formData.sendgrid_from_email;
        payload.sendgrid_from_name = formData.sendgrid_from_name;
      } else if (formData.provider === 'gmail_api') {
        payload.gmail_client_id = formData.gmail_client_id;
        payload.gmail_client_secret = formData.gmail_client_secret;
        payload.gmail_refresh_token = formData.gmail_refresh_token;
        payload.gmail_sender_email = formData.gmail_sender_email;
        payload.gmail_sender_name = formData.gmail_sender_name;
      } else if (formData.provider === 'smtp') {
        payload.smtp_host = formData.smtp_host;
        payload.smtp_port = formData.smtp_port;
        payload.smtp_user = formData.smtp_user;
        payload.smtp_password = formData.smtp_password;
        payload.smtp_encryption = formData.smtp_encryption;
        payload.smtp_from_email = formData.smtp_from_email;
        payload.smtp_from_name = formData.smtp_from_name;
      }

      const res = await adminSettings.updateEmailConfig(payload) as ApiResponse<Record<string, unknown>>;

      if (res.data?.success) {
        toast.success('Email settings saved successfully');
      } else {
        toast.error(res.error || 'Save failed');
      }
    } catch {
      toast.error('Failed to save email settings');
    } finally {
      setSaving(false);
    }
  };

  const handleTestEmail = async () => {
    if (!testEmail) {
      toast.error('Please enter a test email address');
      return;
    }
    setTesting(true);
    try {
      const res = await adminSettings.testEmailProvider({ to: testEmail }) as ApiResponse<Record<string, unknown>>;

      if (res.data?.success) {
        const provider = res.data.provider ? String(res.data.provider).toUpperCase() : '';
        toast.success(provider ? `Test email sent via ${provider}` : 'Test email sent successfully');
      } else {
        toast.error(res.error || 'Test email failed');
      }
    } catch {
      toast.error('Failed to send test email');
    } finally {
      setTesting(false);
    }
  };

  const handleCopyWebhook = async () => {
    try {
      await navigator.clipboard.writeText(formData.webhook_url);
      setCopied(true);
      toast.success('Webhook URL copied');
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast.error('Failed to copy');
    }
  };

  const updateField = <K extends keyof EmailSettingsForm>(key: K, value: EmailSettingsForm[K]) => {
    setFormData(prev => ({ ...prev, [key]: value }));
  };

  const secretHint = (isSet: boolean) =>
    isSet ? 'Already saved — leave unchanged to keep current value' : undefined;

  const { provider } = formData;

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Email Settings" description={`Configure email delivery providers and settings for ${tenant?.name || 'this tenant'}`} />

      <div className="space-y-4">
        {/* Provider Selection */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Mail size={20} /> Email Provider
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <div className="flex items-center gap-3">
              <span className="text-sm text-default-500">Active Provider:</span>
              <Chip color="primary" variant="flat">
                {PROVIDERS.find(p => p.key === provider)?.label || provider}
              </Chip>
            </div>
            <Select
              label="Email Provider"
              selectedKeys={[provider]}
              onSelectionChange={(keys) => {
                const v = Array.from(keys)[0];
                if (v) updateField('provider', String(v));
              }}
              variant="bordered"
            >
              {PROVIDERS.map(p => (
                <SelectItem key={p.key}>{p.label}</SelectItem>
              ))}
            </Select>
          </CardBody>
        </Card>

        {/* SendGrid Config */}
        {provider === 'sendgrid' && (
          <Card shadow="sm">
            <CardHeader>
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Shield size={20} /> SendGrid Configuration
              </h3>
            </CardHeader>
            <CardBody className="gap-4">
              <Input
                label="API Key"
                type="password"
                placeholder="SG.xxxxx"
                variant="bordered"
                description={secretHint(formData._sendgrid_api_key_set) || 'Your SendGrid API key (stored encrypted)'}
                value={formData.sendgrid_api_key}
                onValueChange={(v) => updateField('sendgrid_api_key', v)}
              />
              <Input
                label="From Email"
                placeholder="noreply@example.com"
                variant="bordered"
                value={formData.sendgrid_from_email}
                onValueChange={(v) => updateField('sendgrid_from_email', v)}
              />
              <Input
                label="From Name"
                placeholder="My Timebank"
                variant="bordered"
                value={formData.sendgrid_from_name}
                onValueChange={(v) => updateField('sendgrid_from_name', v)}
              />
              {formData.webhook_url && (
                <div className="space-y-1">
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-medium text-default-700">
                      Webhook URL <span className="text-xs text-default-400 ml-1">(paste into SendGrid Event Webhook settings)</span>
                    </p>
                    <Button
                      size="sm"
                      variant="flat"
                      startContent={copied ? <Check size={14} /> : <Copy size={14} />}
                      onPress={handleCopyWebhook}
                      color={copied ? 'success' : 'default'}
                    >
                      {copied ? 'Copied' : 'Copy'}
                    </Button>
                  </div>
                  <p className="text-sm text-default-500 bg-default-100 rounded-lg px-3 py-2 font-mono break-all select-all">
                    {formData.webhook_url}
                  </p>
                </div>
              )}
            </CardBody>
          </Card>
        )}

        {/* Gmail API Config */}
        {provider === 'gmail_api' && (
          <Card shadow="sm">
            <CardHeader>
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Globe size={20} /> Gmail API Configuration
              </h3>
            </CardHeader>
            <CardBody className="gap-4">
              <Input
                label="Client ID"
                placeholder="xxxx.apps.googleusercontent.com"
                variant="bordered"
                value={formData.gmail_client_id}
                onValueChange={(v) => updateField('gmail_client_id', v)}
              />
              <Input
                label="Client Secret"
                type="password"
                placeholder="GOCSPX-xxxxx"
                variant="bordered"
                description={secretHint(formData._gmail_client_secret_set)}
                value={formData.gmail_client_secret}
                onValueChange={(v) => updateField('gmail_client_secret', v)}
              />
              <Input
                label="Refresh Token"
                type="password"
                variant="bordered"
                description={secretHint(formData._gmail_refresh_token_set)}
                value={formData.gmail_refresh_token}
                onValueChange={(v) => updateField('gmail_refresh_token', v)}
              />
              <Input
                label="Sender Email"
                placeholder="noreply@example.com"
                variant="bordered"
                value={formData.gmail_sender_email}
                onValueChange={(v) => updateField('gmail_sender_email', v)}
              />
              <Input
                label="Sender Name"
                placeholder="My Timebank"
                variant="bordered"
                value={formData.gmail_sender_name}
                onValueChange={(v) => updateField('gmail_sender_name', v)}
              />
            </CardBody>
          </Card>
        )}

        {/* SMTP Config */}
        {provider === 'smtp' && (
          <Card shadow="sm">
            <CardHeader>
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Globe size={20} /> SMTP Configuration
              </h3>
            </CardHeader>
            <CardBody className="gap-4">
              <Input
                label="Host"
                placeholder="smtp.example.com"
                variant="bordered"
                value={formData.smtp_host}
                onValueChange={(v) => updateField('smtp_host', v)}
              />
              <Input
                label="Port"
                placeholder="587"
                variant="bordered"
                value={formData.smtp_port}
                onValueChange={(v) => updateField('smtp_port', v)}
              />
              <Input
                label="Username"
                placeholder="user@example.com"
                variant="bordered"
                value={formData.smtp_user}
                onValueChange={(v) => updateField('smtp_user', v)}
              />
              <Input
                label="Password"
                type="password"
                variant="bordered"
                description={secretHint(formData._smtp_password_set)}
                value={formData.smtp_password}
                onValueChange={(v) => updateField('smtp_password', v)}
              />
              <Select
                label="Encryption"
                selectedKeys={[formData.smtp_encryption]}
                onSelectionChange={(keys) => {
                  const v = Array.from(keys)[0];
                  if (v) updateField('smtp_encryption', String(v));
                }}
                variant="bordered"
              >
                <SelectItem key="tls">TLS</SelectItem>
                <SelectItem key="ssl">SSL</SelectItem>
                <SelectItem key="none">None</SelectItem>
              </Select>
              <Input
                label="From Email"
                placeholder="noreply@example.com"
                variant="bordered"
                value={formData.smtp_from_email}
                onValueChange={(v) => updateField('smtp_from_email', v)}
              />
              <Input
                label="From Name"
                placeholder="My Timebank"
                variant="bordered"
                value={formData.smtp_from_name}
                onValueChange={(v) => updateField('smtp_from_name', v)}
              />
            </CardBody>
          </Card>
        )}

        {/* Platform Default Info */}
        {provider === 'platform_default' && (
          <Card shadow="sm">
            <CardHeader>
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Globe size={20} /> Platform Default
              </h3>
            </CardHeader>
            <CardBody>
              <p className="text-sm text-default-500">
                Using the platform default email provider:{' '}
                <Chip size="sm" variant="flat" color="secondary">
                  {formData.platform_default.provider || 'Not configured'}
                </Chip>
              </p>
              <p className="text-sm text-default-400 mt-2">
                The platform default provider is configured at the server level. Select a different provider above to use a custom configuration for this tenant.
              </p>
            </CardBody>
          </Card>
        )}

        {/* Test Email */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Send size={20} /> Test Email
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <p className="text-sm text-default-400">
              Save your settings first, then send a test email to verify delivery.
            </p>
            <Input
              label="Test Email Address"
              placeholder="test@example.com"
              variant="bordered"
              value={testEmail}
              onValueChange={setTestEmail}
            />
            <Button
              color="secondary"
              startContent={<Send size={16} />}
              onPress={handleTestEmail}
              isLoading={testing}
            >
              Send Test Email
            </Button>
          </CardBody>
        </Card>

        {/* Save Button */}
        <div className="flex justify-end">
          <Button
            color="primary"
            startContent={<Save size={16} />}
            onPress={handleSave}
            isLoading={saving}
          >
            Save Settings
          </Button>
        </div>
      </div>
    </div>
  );
}

export default EmailSettings;
