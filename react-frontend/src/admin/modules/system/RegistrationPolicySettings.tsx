// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * RegistrationPolicySettings
 * Admin UI for configuring the tenant's registration policy and identity verification.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card, CardBody, CardHeader, Select, SelectItem, Switch, Button, Spinner, Chip,
  Divider,
} from '@heroui/react';
import { ShieldCheck, Save, Info, AlertTriangle } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

interface RegistrationPolicy {
  registration_mode: string;
  verification_provider: string | null;
  verification_level: string;
  post_verification: string;
  fallback_mode: string;
  require_email_verify: boolean;
  has_policy: boolean;
}

interface ProviderInfo {
  slug: string;
  name: string;
  levels: string[];
  available: boolean;
}

const REGISTRATION_MODES = [
  { key: 'open', label: 'Standard Registration', description: 'Anyone can register and access the platform immediately.' },
  { key: 'open_with_approval', label: 'Standard Registration + Admin Approval', description: 'Users register but must be approved by an admin before accessing the platform.' },
  { key: 'verified_identity', label: 'Verified Identity via Provider', description: 'Users must complete identity verification (document check, selfie, etc.) before activation.' },
  { key: 'government_id', label: 'Government / Digital ID (Future)', description: 'Users authenticate via government-issued digital identity. Coming soon.' },
  { key: 'invite_only', label: 'Invite Only / Closed', description: 'Registration is closed. Only invited users can join.' },
];

const VERIFICATION_LEVELS = [
  { key: 'none', label: 'None' },
  { key: 'document_only', label: 'Document Only', description: 'ID document scan (passport, driving licence, etc.)' },
  { key: 'document_selfie', label: 'Document + Selfie', description: 'ID document plus a live selfie for facial comparison' },
  { key: 'reusable_digital_id', label: 'Reusable Digital ID', description: 'One-time verification that can be reused across services' },
  { key: 'manual_review', label: 'Manual Review Fallback', description: 'Documents submitted for manual admin review' },
];

const POST_VERIFICATION_ACTIONS = [
  { key: 'activate', label: 'Activate Automatically', description: 'User is activated as soon as verification passes.' },
  { key: 'admin_approval', label: 'Send to Admin for Approval', description: 'Even after verification, an admin must approve the user.' },
  { key: 'limited_access', label: 'Grant Limited Access Pending Approval', description: 'User gets basic access while waiting for full admin approval.' },
  { key: 'reject_on_fail', label: 'Reject if Verification Fails', description: 'User account is deactivated if verification fails.' },
];

const FALLBACK_MODES = [
  { key: 'none', label: 'No Fallback', description: 'If the provider is unavailable, registration is paused.' },
  { key: 'admin_review', label: 'Admin Review', description: 'Fall back to manual admin approval if the provider is down.' },
  { key: 'native_registration', label: 'Standard Registration', description: 'Allow standard registration without verification as a fallback.' },
];

export function RegistrationPolicySettings() {
  usePageTitle('Admin - Registration Policy');
  const toast = useToast();
  const { tenant } = useTenant();

  const [policy, setPolicy] = useState<RegistrationPolicy | null>(null);
  const [providers, setProviders] = useState<ProviderInfo[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const [policyRes, providersRes] = await Promise.all([
        api.get<RegistrationPolicy>('/v2/admin/config/registration-policy'),
        api.get<ProviderInfo[]>('/v2/admin/identity/providers'),
      ]);
      if (policyRes.data) setPolicy(policyRes.data);
      if (providersRes.data) setProviders(Array.isArray(providersRes.data) ? providersRes.data : []);
    } catch {
      toast.error('Failed to load registration policy');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => { fetchData(); }, [fetchData]);

  const handleSave = async () => {
    if (!policy) return;
    setSaving(true);
    try {
      const res = await api.put('/v2/admin/config/registration-policy', {
        registration_mode: policy.registration_mode,
        verification_provider: policy.verification_provider,
        verification_level: policy.verification_level,
        post_verification: policy.post_verification,
        fallback_mode: policy.fallback_mode,
        require_email_verify: policy.require_email_verify,
      });
      if (res.data) {
        toast.success('Registration policy saved');
        fetchData();
      }
    } catch {
      toast.error('Failed to save registration policy');
    } finally {
      setSaving(false);
    }
  };

  const showVerificationOptions = policy?.registration_mode === 'verified_identity' || policy?.registration_mode === 'government_id';
  const selectedMode = REGISTRATION_MODES.find(m => m.key === policy?.registration_mode);

  if (loading || !policy) {
    return (
      <div>
        <PageHeader title="Registration & Identity Verification" description={`Registration policy for ${tenant?.name || 'your community'}`} />
        <div className="flex justify-center py-16"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Registration & Identity Verification" description={`Registration policy for ${tenant?.name || 'your community'}`} />

      <div className="space-y-4">
        {/* Registration Method */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <ShieldCheck size={20} /> Registration Method
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Select
              label="Registration Method"
              selectedKeys={[policy.registration_mode]}
              onSelectionChange={(keys) => {
                const key = Array.from(keys)[0] as string;
                if (key) setPolicy(prev => prev ? { ...prev, registration_mode: key } : prev);
              }}
              variant="bordered"
              description={selectedMode?.description}
            >
              {REGISTRATION_MODES.map((mode) => (
                <SelectItem key={mode.key}>
                  {mode.label}
                </SelectItem>
              ))}
            </Select>

            {policy.registration_mode === 'government_id' && (
              <div className="flex items-center gap-2 p-3 rounded-lg bg-warning-50 text-warning-700 dark:bg-warning-900/20 dark:text-warning-400">
                <AlertTriangle size={16} />
                <span className="text-sm">Government/Digital ID integration is a future feature. Select a different mode for now, or it will behave like admin approval.</span>
              </div>
            )}
          </CardBody>
        </Card>

        {/* Identity Verification Provider (conditional) */}
        {showVerificationOptions && (
          <Card shadow="sm">
            <CardHeader>
              <h3 className="text-lg font-semibold">Identity Verification Settings</h3>
            </CardHeader>
            <CardBody className="gap-4">
              <Select
                label="Verification Provider"
                selectedKeys={policy.verification_provider ? [policy.verification_provider] : []}
                onSelectionChange={(keys) => {
                  const key = Array.from(keys)[0] as string;
                  setPolicy(prev => prev ? { ...prev, verification_provider: key || null } : prev);
                }}
                variant="bordered"
                description="Choose the identity verification service to use"
              >
                {providers.map((p) => (
                  <SelectItem key={p.slug} textValue={p.name}>
                    <div className="flex items-center gap-2">
                      <span>{p.name}</span>
                      {p.available ? (
                        <Chip size="sm" color="success" variant="flat">Available</Chip>
                      ) : (
                        <Chip size="sm" color="danger" variant="flat">Unavailable</Chip>
                      )}
                    </div>
                  </SelectItem>
                ))}
              </Select>

              <Select
                label="Verification Level"
                selectedKeys={[policy.verification_level]}
                onSelectionChange={(keys) => {
                  const key = Array.from(keys)[0] as string;
                  if (key) setPolicy(prev => prev ? { ...prev, verification_level: key } : prev);
                }}
                variant="bordered"
                description={VERIFICATION_LEVELS.find(l => l.key === policy.verification_level)?.description}
              >
                {VERIFICATION_LEVELS.map((level) => (
                  <SelectItem key={level.key}>
                    {level.label}
                  </SelectItem>
                ))}
              </Select>

              <Divider />

              <Select
                label="After Verification Passes"
                selectedKeys={[policy.post_verification]}
                onSelectionChange={(keys) => {
                  const key = Array.from(keys)[0] as string;
                  if (key) setPolicy(prev => prev ? { ...prev, post_verification: key } : prev);
                }}
                variant="bordered"
                description={POST_VERIFICATION_ACTIONS.find(a => a.key === policy.post_verification)?.description}
              >
                {POST_VERIFICATION_ACTIONS.map((action) => (
                  <SelectItem key={action.key}>
                    {action.label}
                  </SelectItem>
                ))}
              </Select>

              <Select
                label="Fallback if Provider Unavailable"
                selectedKeys={[policy.fallback_mode]}
                onSelectionChange={(keys) => {
                  const key = Array.from(keys)[0] as string;
                  if (key) setPolicy(prev => prev ? { ...prev, fallback_mode: key } : prev);
                }}
                variant="bordered"
                description={FALLBACK_MODES.find(f => f.key === policy.fallback_mode)?.description}
              >
                {FALLBACK_MODES.map((fb) => (
                  <SelectItem key={fb.key}>
                    {fb.label}
                  </SelectItem>
                ))}
              </Select>
            </CardBody>
          </Card>
        )}

        {/* Email Verification */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold">Email Verification</h3>
          </CardHeader>
          <CardBody>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Require Email Verification</p>
                <p className="text-sm text-default-500">Users must verify their email address before accessing the platform. Recommended for all modes.</p>
              </div>
              <Switch
                isSelected={policy.require_email_verify}
                onValueChange={(val) => setPolicy(prev => prev ? { ...prev, require_email_verify: val } : prev)}
                aria-label="Require email verification"
              />
            </div>
          </CardBody>
        </Card>

        {/* Info box */}
        <Card shadow="sm" className="bg-primary-50 dark:bg-primary-900/10">
          <CardBody>
            <div className="flex gap-3">
              <Info size={20} className="text-primary shrink-0 mt-0.5" />
              <div className="text-sm text-default-700 dark:text-default-300 space-y-1">
                <p className="font-medium">How registration modes work:</p>
                <ul className="list-disc list-inside space-y-0.5">
                  <li><strong>Standard:</strong> User registers and can log in immediately (email verification optional).</li>
                  <li><strong>+ Admin Approval:</strong> User registers but cannot log in until an admin approves them.</li>
                  <li><strong>Verified Identity:</strong> User must pass an identity check (document scan, selfie) via a third-party provider.</li>
                  <li><strong>Government/Digital ID:</strong> Placeholder for future eID integration (EUDI wallet, UK trust framework, etc.).</li>
                  <li><strong>Invite Only:</strong> Registration is closed. Only users with an invitation can join.</li>
                </ul>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Save */}
        <div className="flex justify-end">
          <Button
            color="primary"
            startContent={!saving ? <Save size={16} /> : undefined}
            onPress={handleSave}
            isLoading={saving}
          >
            Save Registration Policy
          </Button>
        </div>
      </div>
    </div>
  );
}

export default RegistrationPolicySettings;
