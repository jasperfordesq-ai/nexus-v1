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
import { Key, ArrowLeft, Copy } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';

const AVAILABLE_SCOPES = ['read:users', 'read:listings', 'read:transactions', 'write:messages', 'write:transactions'];

export function CreateApiKey() {
  usePageTitle('Admin - Create API Key');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const [name, setName] = useState('');
  const [scopes, setScopes] = useState<string[]>([]);
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
      const res = await adminFederation.createApiKey({ name, scopes });
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
    } catch { /* empty */ }
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
        <PageHeader title="API Key Created" description="Store this key securely - it will not be shown again" />
        <Card shadow="sm">
          <CardBody className="gap-4">
            <div className="rounded-lg bg-success-50 border border-success-200 p-4">
              <p className="text-sm font-medium text-success-700 mb-2">Your new API key:</p>
              <code className="block break-all text-sm bg-white p-3 rounded border">{createdKey}</code>
            </div>
            <div className="flex gap-2">
              <Button variant="flat" startContent={<Copy size={16} />} onPress={handleCopy}>{copied ? 'Copied!' : 'Copy Key'}</Button>
              <Button color="primary" onPress={() => navigate(tenantPath('/admin/federation/api-keys'))}>Done</Button>
            </div>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Create API Key"
        description="Generate a new federation API key"
        actions={<Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/federation/api-keys'))}>Back</Button>}
      />
      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Key size={20} /> New API Key</h3></CardHeader>
        <CardBody className="gap-4">
          <Input label="Key Name" placeholder="e.g., Production Integration" value={name} onValueChange={setName} isRequired variant="bordered" />
          <div>
            <p className="text-sm font-medium mb-2">Scopes</p>
            <div className="space-y-2">
              {AVAILABLE_SCOPES.map(scope => (
                <Checkbox key={scope} isSelected={scopes.includes(scope)} onValueChange={() => toggleScope(scope)}>
                  <code className="text-xs">{scope}</code>
                </Checkbox>
              ))}
            </div>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="flat" onPress={() => navigate(tenantPath('/admin/federation/api-keys'))}>Cancel</Button>
            <Button color="primary" onPress={handleSubmit} isLoading={saving} isDisabled={!name.trim()}>Create Key</Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CreateApiKey;
