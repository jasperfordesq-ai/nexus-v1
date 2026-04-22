// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create Federation API Key
 * Form for generating a new federation API key.
 */

import { useState } from 'react';
import { Card, CardBody, CardHeader, Input, Button, Checkbox } from '@heroui/react';
import Key from 'lucide-react/icons/key';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Copy from 'lucide-react/icons/copy';
import Calendar from 'lucide-react/icons/calendar';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';

import { useTranslation } from 'react-i18next';

/** Scopes must match the `fedAuth('...')` permission strings in FederationController.php */
const SCOPE_KEYS = [
  'timebanks:read',
  'members:read',
  'listings:read',
  'messages:read',
  'messages:write',
  'transactions:read',
  'transactions:write',
  'reviews:read',
  'reviews:write',
] as const;

export function CreateApiKey() {
  const { t } = useTranslation('admin');
  usePageTitle("Federation");
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const AVAILABLE_SCOPES = SCOPE_KEYS.map((key) => ({
    key,
    description: t(`federation.scope_${key.replace(':', '_')}`, key),
  }));
  const [name, setName] = useState('');
  const [scopes, setScopes] = useState<string[]>([]);
  const [expiresAt, setExpiresAt] = useState<string>('');
  const [saving, setSaving] = useState(false);
  const [createdKey, setCreatedKey] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const toggleScope = (scope: string) => {
    setScopes(prev => prev.includes(scope) ? prev.filter(s => s !== scope) : [...prev, scope]);
  };

  const handleSubmit = async () => {
    if (!name.trim()) return;
    setSaving(true);
    try {
      const res = await adminFederation.createApiKey({
        name,
        scopes,
        expires_at: expiresAt || undefined,
      });
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let d: { api_key?: string };
        if (payload && typeof payload === 'object' && 'data' in payload) {
          d = (payload as { data: typeof d }).data;
        } else {
          d = payload as typeof d;
        }
        if (d.api_key) {
          setCreatedKey(d.api_key);
        } else {
          navigate(tenantPath('/admin/federation/api-keys'));
        }
      }
    } catch (err) {
      logError('CreateApiKey: failed to create API key', err);
      toast.error("Failed to create API key. Please try again");
    }
    setSaving(false);
  };

  const handleCopy = () => {
    if (createdKey) {
      navigator.clipboard.writeText(createdKey);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  if (createdKey) {
    return (
      <div>
        <PageHeader title={"Create API Key"} description={"Create a new API key to enable external access to your federation data"} />
        <Card shadow="sm">
          <CardBody className="gap-4">
            <div className="rounded-lg bg-success-50 border border-success-200 p-4">
              <p className="text-sm font-medium text-success-700 mb-2">{"Your New API Key"}</p>
              <code className="block break-all text-sm bg-white p-3 rounded border">{createdKey}</code>
            </div>
            <div className="flex gap-2">
              <Button variant="flat" startContent={<Copy size={16} />} onPress={handleCopy}>{copied ? "Copied" : "Copy Key"}</Button>
              <Button color="primary" onPress={() => navigate(tenantPath('/admin/federation/api-keys'))}>{"Done"}</Button>
            </div>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={"Create API Key"}
        description={"Create a new API key to enable external access to your federation data"}
        actions={<Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/federation/api-keys'))}>{"Back"}</Button>}
      />
      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Key size={20} /> {"New API Key"}</h3></CardHeader>
        <CardBody className="gap-4">
          <Input label={"Key Name"} placeholder={"Key Name..."} value={name} onValueChange={setName} isRequired variant="bordered" />
          <div>
            <p className="text-sm font-medium mb-2">{"Scopes"}</p>
            <div className="space-y-2">
              {AVAILABLE_SCOPES.map(scope => (
                <Checkbox key={scope.key} isSelected={scopes.includes(scope.key)} onValueChange={() => toggleScope(scope.key)}>
                  <span className="flex items-center gap-2">
                    <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">{scope.key}</code>
                    <span className="text-xs text-default-500">{scope.description}</span>
                  </span>
                </Checkbox>
              ))}
            </div>
          </div>
          <div>
            <p className="text-sm font-medium mb-2 flex items-center gap-1.5">
              <Calendar size={14} />
              {t('federation.expiry_date', 'Expiry Date')}
              <span className="text-default-400 font-normal">({t('federation.optional', 'optional')})</span>
            </p>
            <Input
              type="date"
              variant="bordered"
              value={expiresAt}
              onValueChange={setExpiresAt}
              min={new Date(Date.now() + 86400000).toISOString().split('T')[0]}
              description={t('federation.expiry_description', 'Leave blank for a key that never expires. Expired keys are automatically deactivated.')}
            />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="flat" onPress={() => navigate(tenantPath('/admin/federation/api-keys'))}>{"Cancel"}</Button>
            <Button color="primary" onPress={handleSubmit} isLoading={saving} isDisabled={!name.trim()}>{"Create Key"}</Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CreateApiKey;
