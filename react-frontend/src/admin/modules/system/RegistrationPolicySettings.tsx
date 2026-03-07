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
  Divider, Input, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, useDisclosure,
} from '@heroui/react';
import { ShieldCheck, Save, Info, AlertTriangle, Key, Ticket, Plus, Trash2, Copy, Eye, EyeOff } from 'lucide-react';
import { VerificationAuditLog } from './VerificationAuditLog';
import { VerificationReviewQueue } from './VerificationReviewQueue';
import { ProviderHealthDashboard } from './ProviderHealthDashboard';
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
  has_credentials: boolean;
}

interface InviteCode {
  id: number;
  code: string;
  max_uses: number;
  uses_count: number;
  note: string | null;
  is_active: number;
  expires_at: string | null;
  created_at: string;
  creator_name: string | null;
}

interface InviteCodesResponse {
  items: InviteCode[];
  total: number;
}

const REGISTRATION_MODES = [
  { key: 'open', label: 'Standard Registration', description: 'Anyone can register and access the platform immediately.' },
  { key: 'open_with_approval', label: 'Standard Registration + Admin Approval', description: 'Users register but must be approved by an admin before accessing the platform.' },
  { key: 'verified_identity', label: 'Verified Identity via Provider', description: 'Users must complete identity verification (document check, selfie, etc.) before activation.' },
  { key: 'government_id', label: 'Government / Digital ID (Future)', description: 'Users authenticate via government-issued digital identity. Coming soon.' },
  { key: 'invite_only', label: 'Invite Only / Closed', description: 'Registration is closed. Only invited users can join.' },
  { key: 'waitlist', label: 'Waitlist', description: 'Users join a waitlist and are activated in order when capacity opens.' },
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

  // Per-provider credential state
  const [credentialInputs, setCredentialInputs] = useState<Record<string, { api_key: string; webhook_secret: string }>>({});
  const [credentialVisibility, setCredentialVisibility] = useState<Record<string, { api_key: boolean; webhook_secret: boolean }>>({});
  const [savingCredentials, setSavingCredentials] = useState<Record<string, boolean>>({});

  // Invite codes state
  const [inviteCodes, setInviteCodes] = useState<InviteCode[]>([]);
  const [inviteCodesTotal, setInviteCodesTotal] = useState(0);
  const [inviteCodesLoading, setInviteCodesLoading] = useState(false);
  const [generateCount, setGenerateCount] = useState(1);
  const [generateMaxUses, setGenerateMaxUses] = useState(1);
  const [generateExpiry, setGenerateExpiry] = useState('');
  const [generateNote, setGenerateNote] = useState('');
  const [generating, setGenerating] = useState(false);
  const [generatedCodes, setGeneratedCodes] = useState<string[]>([]);
  const generateModal = useDisclosure();

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

  const fetchInviteCodes = useCallback(async () => {
    setInviteCodesLoading(true);
    try {
      const res = await api.get<InviteCodesResponse>('/v2/admin/invite-codes');
      if (res.data) {
        setInviteCodes(res.data.items || []);
        setInviteCodesTotal(res.data.total || 0);
      }
    } catch { /* ignore */ } finally {
      setInviteCodesLoading(false);
    }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);
  useEffect(() => {
    if (policy?.registration_mode === 'invite_only') fetchInviteCodes();
  }, [policy?.registration_mode, fetchInviteCodes]);

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

  const handleSaveCredentials = async (slug: string) => {
    const input = credentialInputs[slug];
    if (!input?.api_key && !input?.webhook_secret) return;
    setSavingCredentials(prev => ({ ...prev, [slug]: true }));
    try {
      const body: Record<string, string> = {};
      if (input.api_key) body.api_key = input.api_key;
      if (input.webhook_secret) body.webhook_secret = input.webhook_secret;
      await api.put(`/v2/admin/identity/provider-credentials/${slug}`, body);
      toast.success('Credentials saved and encrypted');
      setCredentialInputs(prev => ({ ...prev, [slug]: { api_key: '', webhook_secret: '' } }));
      fetchData();
    } catch {
      toast.error('Failed to save credentials');
    } finally {
      setSavingCredentials(prev => ({ ...prev, [slug]: false }));
    }
  };

  const handleDeleteCredentials = async (slug: string) => {
    try {
      await api.delete(`/v2/admin/identity/provider-credentials/${slug}`);
      toast.success('Credentials removed');
      fetchData();
    } catch {
      toast.error('Failed to remove credentials');
    }
  };

  const handleGenerateInviteCodes = async () => {
    setGenerating(true);
    try {
      const res = await api.post<{ codes: string[] }>('/v2/admin/invite-codes', {
        count: generateCount,
        max_uses: generateMaxUses,
        expires_at: generateExpiry || undefined,
        note: generateNote || undefined,
      });
      if (res.data?.codes) {
        setGeneratedCodes(res.data.codes);
        fetchInviteCodes();
        toast.success(`Generated ${res.data.codes.length} invite code(s)`);
      }
    } catch {
      toast.error('Failed to generate invite codes');
    } finally {
      setGenerating(false);
    }
  };

  const handleDeactivateCode = async (id: number) => {
    try {
      await api.delete(`/v2/admin/invite-codes/${id}`);
      toast.success('Invite code deactivated');
      fetchInviteCodes();
    } catch {
      toast.error('Failed to deactivate invite code');
    }
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success('Copied to clipboard');
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

        {/* Provider API Credentials — per-provider management */}
        {showVerificationOptions && (
          <Card shadow="sm">
            <CardHeader>
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Key size={20} /> Provider API Credentials
              </h3>
            </CardHeader>
            <CardBody className="gap-4">
              <p className="text-sm text-default-500">
                Enter your own API credentials for each provider. Credentials are encrypted at rest (AES-256-GCM) and never exposed in API responses.
                Providers with credentials configured will show as &quot;Available&quot;.
              </p>
              {providers.filter(p => p.slug !== 'mock').map((p) => {
                const inputs = credentialInputs[p.slug] || { api_key: '', webhook_secret: '' };
                const visibility = credentialVisibility[p.slug] || { api_key: false, webhook_secret: false };
                const isSaving = savingCredentials[p.slug] || false;

                return (
                  <Card key={p.slug} shadow="none" className="border border-default-200">
                    <CardBody className="gap-3">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <span className="font-medium">{p.name}</span>
                          {p.has_credentials ? (
                            <Chip size="sm" color="success" variant="flat">Credentials Saved</Chip>
                          ) : (
                            <Chip size="sm" color="default" variant="flat">No Credentials</Chip>
                          )}
                          {p.available ? (
                            <Chip size="sm" color="success" variant="dot">Available</Chip>
                          ) : (
                            <Chip size="sm" color="danger" variant="dot">Unavailable</Chip>
                          )}
                        </div>
                        {p.has_credentials && (
                          <Button size="sm" variant="light" color="danger" startContent={<Trash2 size={14} />} onPress={() => handleDeleteCredentials(p.slug)}>
                            Remove
                          </Button>
                        )}
                      </div>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <Input
                          label="API Key / Secret Key"
                          placeholder={p.has_credentials ? '••••••••  (saved)' : 'sk_live_... or api key'}
                          value={inputs.api_key}
                          onChange={(e) => setCredentialInputs(prev => ({ ...prev, [p.slug]: { ...inputs, api_key: e.target.value } }))}
                          type={visibility.api_key ? 'text' : 'password'}
                          variant="bordered"
                          size="sm"
                          endContent={
                            <Button isIconOnly size="sm" variant="light" onPress={() => setCredentialVisibility(prev => ({ ...prev, [p.slug]: { ...visibility, api_key: !visibility.api_key } }))}>
                              {visibility.api_key ? <EyeOff size={14} /> : <Eye size={14} />}
                            </Button>
                          }
                        />
                        <Input
                          label="Webhook Secret"
                          placeholder={p.has_credentials ? '••••••••  (saved)' : 'whsec_... or webhook secret'}
                          value={inputs.webhook_secret}
                          onChange={(e) => setCredentialInputs(prev => ({ ...prev, [p.slug]: { ...inputs, webhook_secret: e.target.value } }))}
                          type={visibility.webhook_secret ? 'text' : 'password'}
                          variant="bordered"
                          size="sm"
                          endContent={
                            <Button isIconOnly size="sm" variant="light" onPress={() => setCredentialVisibility(prev => ({ ...prev, [p.slug]: { ...visibility, webhook_secret: !visibility.webhook_secret } }))}>
                              {visibility.webhook_secret ? <EyeOff size={14} /> : <Eye size={14} />}
                            </Button>
                          }
                        />
                      </div>
                      {(inputs.api_key || inputs.webhook_secret) && (
                        <div className="flex justify-end">
                          <Button
                            size="sm"
                            color="primary"
                            variant="flat"
                            startContent={!isSaving ? <Save size={14} /> : undefined}
                            isLoading={isSaving}
                            onPress={() => handleSaveCredentials(p.slug)}
                          >
                            Save {p.name} Credentials
                          </Button>
                        </div>
                      )}
                    </CardBody>
                  </Card>
                );
              })}
            </CardBody>
          </Card>
        )}

        {/* Invite Codes Management (conditional) */}
        {policy.registration_mode === 'invite_only' && (
          <Card shadow="sm">
            <CardHeader className="flex justify-between items-center">
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Ticket size={20} /> Invite Codes
              </h3>
              <Button
                size="sm"
                color="primary"
                startContent={<Plus size={14} />}
                onPress={() => { setGeneratedCodes([]); setGenerateCount(1); setGenerateMaxUses(1); setGenerateExpiry(''); setGenerateNote(''); generateModal.onOpen(); }}
              >
                Generate Codes
              </Button>
            </CardHeader>
            <CardBody>
              {inviteCodesLoading ? (
                <div className="flex justify-center py-4"><Spinner /></div>
              ) : inviteCodes.length === 0 ? (
                <p className="text-sm text-default-500 text-center py-4">No invite codes generated yet.</p>
              ) : (
                <Table aria-label="Invite codes" removeWrapper>
                  <TableHeader>
                    <TableColumn>Code</TableColumn>
                    <TableColumn>Uses</TableColumn>
                    <TableColumn>Status</TableColumn>
                    <TableColumn>Note</TableColumn>
                    <TableColumn>Actions</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {inviteCodes.map((ic) => (
                      <TableRow key={ic.id}>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <code className="text-sm font-mono">{ic.code}</code>
                            <Button isIconOnly size="sm" variant="light" onPress={() => copyToClipboard(ic.code)}>
                              <Copy size={12} />
                            </Button>
                          </div>
                        </TableCell>
                        <TableCell>{ic.uses_count}/{ic.max_uses}</TableCell>
                        <TableCell>
                          {!ic.is_active ? (
                            <Chip size="sm" color="default" variant="flat">Deactivated</Chip>
                          ) : ic.uses_count >= ic.max_uses ? (
                            <Chip size="sm" color="warning" variant="flat">Exhausted</Chip>
                          ) : ic.expires_at && new Date(ic.expires_at) < new Date() ? (
                            <Chip size="sm" color="warning" variant="flat">Expired</Chip>
                          ) : (
                            <Chip size="sm" color="success" variant="flat">Active</Chip>
                          )}
                        </TableCell>
                        <TableCell><span className="text-sm text-default-500">{ic.note || '—'}</span></TableCell>
                        <TableCell>
                          {ic.is_active ? (
                            <Button isIconOnly size="sm" variant="light" color="danger" onPress={() => handleDeactivateCode(ic.id)}>
                              <Trash2 size={14} />
                            </Button>
                          ) : null}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
              {inviteCodesTotal > 0 && (
                <p className="text-xs text-default-400 mt-2">{inviteCodesTotal} total code(s)</p>
              )}
            </CardBody>
          </Card>
        )}

        {/* Generate Codes Modal */}
        <Modal isOpen={generateModal.isOpen} onOpenChange={generateModal.onOpenChange} size="md">
          <ModalContent>
            <ModalHeader>Generate Invite Codes</ModalHeader>
            <ModalBody>
              {generatedCodes.length > 0 ? (
                <div className="space-y-2">
                  <p className="text-sm text-default-500">Generated {generatedCodes.length} code(s):</p>
                  <div className="space-y-1">
                    {generatedCodes.map((code) => (
                      <div key={code} className="flex items-center justify-between p-2 bg-default-100 rounded-lg">
                        <code className="font-mono text-sm">{code}</code>
                        <Button isIconOnly size="sm" variant="light" onPress={() => copyToClipboard(code)}>
                          <Copy size={14} />
                        </Button>
                      </div>
                    ))}
                  </div>
                  <Button
                    size="sm"
                    variant="flat"
                    onPress={() => copyToClipboard(generatedCodes.join('\n'))}
                    startContent={<Copy size={14} />}
                    className="mt-2"
                  >
                    Copy all
                  </Button>
                </div>
              ) : (
                <div className="space-y-4">
                  <Input
                    label="Number of codes"
                    type="number"
                    min={1}
                    max={100}
                    value={String(generateCount)}
                    onChange={(e) => setGenerateCount(Math.max(1, Math.min(100, parseInt(e.target.value) || 1)))}
                    variant="bordered"
                    description="Generate up to 100 codes at once"
                  />
                  <Input
                    label="Max uses per code"
                    type="number"
                    min={1}
                    max={1000}
                    value={String(generateMaxUses)}
                    onChange={(e) => setGenerateMaxUses(Math.max(1, Math.min(1000, parseInt(e.target.value) || 1)))}
                    variant="bordered"
                    description="How many times each code can be used (1 = single-use)"
                  />
                  <Input
                    label="Expiry date (optional)"
                    type="date"
                    value={generateExpiry}
                    onChange={(e) => setGenerateExpiry(e.target.value)}
                    variant="bordered"
                    description="Codes expire at midnight on this date (leave blank for no expiry)"
                  />
                  <Input
                    label="Note (optional)"
                    placeholder="e.g. March 2026 onboarding batch"
                    value={generateNote}
                    onChange={(e) => setGenerateNote(e.target.value)}
                    variant="bordered"
                  />
                </div>
              )}
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={generateModal.onClose}>
                {generatedCodes.length > 0 ? 'Done' : 'Cancel'}
              </Button>
              {generatedCodes.length === 0 && (
                <Button color="primary" onPress={handleGenerateInviteCodes} isLoading={generating}>
                  Generate
                </Button>
              )}
            </ModalFooter>
          </ModalContent>
        </Modal>

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
                  <li><strong>Waitlist:</strong> Users join a queue and are activated in order when capacity opens.</li>
                </ul>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Provider Health Dashboard (conditional) */}
        {showVerificationOptions && <ProviderHealthDashboard />}

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

        {/* Pending Verification Reviews */}
        <VerificationReviewQueue />

        {/* Audit Log */}
        <VerificationAuditLog />
      </div>
    </div>
  );
}

export default RegistrationPolicySettings;
