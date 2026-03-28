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
import { Key, ArrowLeft, Copy, Calendar } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';

import { useTranslation } from 'react-i18next';

/** Scopes must match the `fedAuth('...')` permission strings in FederationController.php */
const AVAILABLE_SCOPES = [
  { key: 'timebanks:read', description: 'View partner timebanks' },
  { key: 'members:read', description: 'Search and view member profiles' },
  { key: 'listings:read', description: 'Search and view listings' },
  { key: 'messages:read', description: 'Read cross-community messages' },
  { key: 'messages:write', description: 'Send cross-community messages' },
  { key: 'transactions:read', description: 'Read transaction status' },
  { key: 'transactions:write', description: 'Create time credit transfers' },
  { key: 'reviews:read', description: 'Read cross-community reviews' },
  { key: 'reviews:write', description: 'Write cross-community reviews' },
];

export function CreateApiKey() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation.page_title'));
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();
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
      toast.error(t('federation.failed_to_create_a_p_i_key_please_try_aga'));
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
        <PageHeader title={t('federation.create_api_key_title')} description={t('federation.create_api_key_desc')} />
        <Card shadow="sm">
          <CardBody className="gap-4">
            <div className="rounded-lg bg-success-50 border border-success-200 p-4">
              <p className="text-sm font-medium text-success-700 mb-2">{t('federation.your_new_api_key')}</p>
              <code className="block break-all text-sm bg-white p-3 rounded border">{createdKey}</code>
            </div>
            <div className="flex gap-2">
              <Button variant="flat" startContent={<Copy size={16} />} onPress={handleCopy}>{copied ? t('federation.copied') : t('federation.copy_key')}</Button>
              <Button color="primary" onPress={() => navigate(tenantPath('/admin/federation/api-keys'))}>{t('federation.done')}</Button>
            </div>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('federation.create_api_key_title')}
        description={t('federation.create_api_key_desc')}
        actions={<Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/federation/api-keys'))}>{t('federation.back')}</Button>}
      />
      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Key size={20} /> {t('federation.new_api_key')}</h3></CardHeader>
        <CardBody className="gap-4">
          <Input label={t('federation.label_key_name')} placeholder={t('federation.placeholder_key_name')} value={name} onValueChange={setName} isRequired variant="bordered" />
          <div>
            <p className="text-sm font-medium mb-2">{t('federation.scopes')}</p>
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
            <Button variant="flat" onPress={() => navigate(tenantPath('/admin/federation/api-keys'))}>{t('federation.cancel')}</Button>
            <Button color="primary" onPress={handleSubmit} isLoading={saving} isDisabled={!name.trim()}>{t('federation.create_key')}</Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CreateApiKey;
