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
  Accordion, AccordionItem,
} from '@heroui/react';
import {
  ShieldCheck, Save, Info, AlertTriangle, Key, Ticket, Plus, Trash2, Copy,
  Eye, EyeOff, CheckCircle2, HelpCircle, Lock, UserCheck, Users,
  Clock, Mail, Globe, Shield,
} from 'lucide-react';
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
  {
    key: 'open',
    label: 'Open Registration',
    description: 'Anyone can register and access the platform immediately. Best for public communities that want maximum growth.',
    icon: Globe,
    color: 'success' as const,
  },
  {
    key: 'open_with_approval',
    label: 'Open + Admin Approval',
    description: 'Users can register, but an admin must approve each account before they can access the platform. Good for communities that want to vet members.',
    icon: UserCheck,
    color: 'primary' as const,
  },
  {
    key: 'verified_identity',
    label: 'Identity Verification Required',
    description: 'Users must pass an automated identity check (document scan, selfie match) through a third-party provider before activation. Ideal for high-trust communities.',
    icon: ShieldCheck,
    color: 'secondary' as const,
  },
  {
    key: 'invite_only',
    label: 'Invite Only',
    description: 'Registration is closed to the public. Only users with a valid invite code can create an account. Best for private or controlled-growth communities.',
    icon: Lock,
    color: 'warning' as const,
  },
  {
    key: 'waitlist',
    label: 'Waitlist',
    description: 'Users sign up for a waitlist and are activated in order when capacity allows. Useful for managing growth or staged rollouts.',
    icon: Clock,
    color: 'default' as const,
  },
  {
    key: 'government_id',
    label: 'Government / Digital ID (Coming Soon)',
    description: 'Users authenticate via government-issued digital identity (e.g. EUDI Wallet, UK Digital Identity Trust Framework). This mode is a placeholder for future integration.',
    icon: Shield,
    color: 'default' as const,
  },
];

const VERIFICATION_LEVELS = [
  { key: 'none', label: 'None', description: 'No document verification required.' },
  { key: 'document_only', label: 'Document Only', description: 'Users upload an ID document (passport, driving licence, national ID). The provider checks authenticity.' },
  { key: 'document_selfie', label: 'Document + Selfie', description: 'Users upload an ID document AND take a live selfie. The provider compares the face to the document photo. Strongest automated check.' },
  { key: 'reusable_digital_id', label: 'Reusable Digital ID', description: 'One-time verification that issues a reusable credential. Users can present it across services without re-verifying.' },
  { key: 'manual_review', label: 'Manual Review', description: 'Documents are submitted and queued for manual admin review instead of automated checking.' },
];

const POST_VERIFICATION_ACTIONS = [
  { key: 'activate', label: 'Activate Automatically', description: 'User is activated as soon as verification passes. Fastest onboarding experience.' },
  { key: 'admin_approval', label: 'Admin Approval After Verification', description: 'Even after passing verification, an admin must manually approve the user. Double layer of security.' },
  { key: 'limited_access', label: 'Limited Access While Pending', description: 'User gets basic read-only access while waiting for full admin approval. Reduces friction.' },
  { key: 'reject_on_fail', label: 'Reject on Failure', description: 'If verification fails, the user account is automatically deactivated. Strictest policy.' },
];

const FALLBACK_MODES = [
  { key: 'none', label: 'No Fallback (Pause Registration)', description: 'If the verification provider is unavailable, new registrations are paused until it recovers.' },
  { key: 'admin_review', label: 'Fall Back to Admin Review', description: 'If the provider is down, users are queued for manual admin approval instead.' },
  { key: 'native_registration', label: 'Fall Back to Standard Registration', description: 'If the provider is down, allow standard registration without verification as a temporary measure.' },
];

// Helper: provider descriptions for the credential cards
const PROVIDER_DESCRIPTIONS: Record<string, string> = {
  stripe_identity: 'Stripe Identity verifies government-issued IDs with document + selfie matching. Requires a Stripe account with Identity enabled.',
  veriff: 'Veriff provides AI-powered identity verification with global document coverage. Sign up at veriff.com for API credentials.',
  jumio: 'Jumio offers enterprise-grade identity proofing with liveness detection. Contact Jumio sales for API access.',
  onfido: 'Onfido uses AI and biometrics to verify real identity from 195 countries. Sign up at onfido.com for API keys.',
  idenfy: 'iDenfy provides automated KYC/AML identity verification. Register at idenfy.com for sandbox and production keys.',
};

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
        toast.success('Registration policy saved successfully');
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
      toast.success(`${providers.find(p => p.slug === slug)?.name || 'Provider'} credentials saved and encrypted`);
      setCredentialInputs(prev => ({ ...prev, [slug]: { api_key: '', webhook_secret: '' } }));
      fetchData();
    } catch {
      toast.error('Failed to save credentials. Please check your API keys and try again.');
    } finally {
      setSavingCredentials(prev => ({ ...prev, [slug]: false }));
    }
  };

  const handleDeleteCredentials = async (slug: string) => {
    try {
      await api.delete(`/v2/admin/identity/provider-credentials/${slug}`);
      toast.success('Credentials removed successfully');
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
  const availableProviderCount = providers.filter(p => p.slug !== 'mock' && p.available).length;
  const configuredProviderCount = providers.filter(p => p.slug !== 'mock' && p.has_credentials).length;
  const realProviders = providers.filter(p => p.slug !== 'mock');

  if (loading || !policy) {
    return (
      <div>
        <PageHeader title="Registration & Identity Verification" description={`Control how new members join ${tenant?.name || 'your community'}`} />
        <div className="flex justify-center py-16"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Registration & Identity Verification" description={`Control how new members join ${tenant?.name || 'your community'}`} />

      <div className="space-y-6">

        {/* ── Section 1: Registration Method ── */}
        <Card shadow="sm">
          <CardHeader className="pb-0">
            <div>
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <ShieldCheck size={20} className="text-primary" /> Registration Method
              </h3>
              <p className="text-sm text-default-500 mt-1">
                Choose how new users join your community. This controls the entire registration flow, from sign-up form to account activation.
              </p>
            </div>
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
              classNames={{ description: 'text-default-500' }}
              description={selectedMode?.description}
            >
              {REGISTRATION_MODES.map((mode) => {
                const Icon = mode.icon;
                return (
                  <SelectItem key={mode.key} textValue={mode.label}>
                    <div className="flex items-center gap-2">
                      <Icon size={16} />
                      <span>{mode.label}</span>
                    </div>
                  </SelectItem>
                );
              })}
            </Select>

            {policy.registration_mode === 'government_id' && (
              <div className="flex items-center gap-2 p-3 rounded-lg bg-warning-50 text-warning-700 dark:bg-warning-900/20 dark:text-warning-400">
                <AlertTriangle size={16} className="shrink-0" />
                <span className="text-sm">Government/Digital ID integration is a future feature. Select a different mode for now, or it will behave like admin approval.</span>
              </div>
            )}

            {policy.registration_mode === 'waitlist' && (
              <div className="flex items-center gap-2 p-3 rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400">
                <Users size={16} className="shrink-0" />
                <span className="text-sm">Users will join a queue when they register. You can activate them from the Members admin page in the order they signed up.</span>
              </div>
            )}

            {policy.registration_mode === 'verified_identity' && availableProviderCount === 0 && (
              <div className="flex items-center gap-2 p-3 rounded-lg bg-danger-50 text-danger-700 dark:bg-danger-900/20 dark:text-danger-400">
                <AlertTriangle size={16} className="shrink-0" />
                <span className="text-sm">
                  <strong>No verification providers are available.</strong> You need to configure API credentials for at least one provider below before identity verification will work.
                </span>
              </div>
            )}
          </CardBody>
        </Card>

        {/* ── Section 2: Identity Verification Settings (conditional) ── */}
        {showVerificationOptions && (
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div>
                <h3 className="text-lg font-semibold flex items-center gap-2">
                  <UserCheck size={20} className="text-secondary" /> Verification Settings
                </h3>
                <p className="text-sm text-default-500 mt-1">
                  Configure how identity verification works — which provider to use, what level of checking, and what happens after verification.
                </p>
              </div>
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
                description={
                  availableProviderCount > 0
                    ? `${availableProviderCount} provider(s) available. Configure API keys in the "Provider API Credentials" section below.`
                    : 'No providers available yet. Add API credentials in the section below to enable a provider.'
                }
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

        {/* ── Section 3: Email Verification ── */}
        <Card shadow="sm">
          <CardHeader className="pb-0">
            <div>
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Mail size={20} className="text-primary" /> Email Verification
              </h3>
            </div>
          </CardHeader>
          <CardBody>
            <div className="flex items-center justify-between gap-4">
              <div>
                <p className="font-medium">Require Email Verification</p>
                <p className="text-sm text-default-500">
                  Users must click a confirmation link sent to their email before they can access the platform.
                  Recommended for all registration modes to prevent fake accounts.
                </p>
              </div>
              <Switch
                isSelected={policy.require_email_verify}
                onValueChange={(val) => setPolicy(prev => prev ? { ...prev, require_email_verify: val } : prev)}
                aria-label="Require email verification"
              />
            </div>
          </CardBody>
        </Card>

        {/* ── Save Button ── */}
        <div className="flex justify-end">
          <Button
            color="primary"
            size="lg"
            startContent={!saving ? <Save size={18} /> : undefined}
            onPress={handleSave}
            isLoading={saving}
          >
            Save Registration Policy
          </Button>
        </div>

        <Divider className="my-2" />

        {/* ── Section 4: Provider API Credentials (always visible) ── */}
        {realProviders.length > 0 && (
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div className="w-full">
                <div className="flex items-center justify-between">
                  <h3 className="text-lg font-semibold flex items-center gap-2">
                    <Key size={20} className="text-warning" /> Identity Verification Providers
                  </h3>
                  <div className="flex items-center gap-2">
                    {configuredProviderCount > 0 && (
                      <Chip size="sm" color="success" variant="flat" startContent={<CheckCircle2 size={12} />}>
                        {configuredProviderCount} configured
                      </Chip>
                    )}
                    <Chip size="sm" color={availableProviderCount > 0 ? 'success' : 'default'} variant="flat">
                      {availableProviderCount} available
                    </Chip>
                  </div>
                </div>
                <p className="text-sm text-default-500 mt-1">
                  To use identity verification, you need an account with at least one provider below.
                  Enter your API credentials here — they are encrypted at rest using AES-256-GCM and are never
                  exposed in API responses. Once credentials are saved, the provider will show as &quot;Available&quot;
                  and can be selected as your verification provider.
                </p>
              </div>
            </CardHeader>
            <CardBody className="gap-3 pt-4">
              <Accordion variant="splitted" selectionMode="multiple">
                {realProviders.map((p) => {
                  const inputs = credentialInputs[p.slug] || { api_key: '', webhook_secret: '' };
                  const visibility = credentialVisibility[p.slug] || { api_key: false, webhook_secret: false };
                  const isSaving = savingCredentials[p.slug] || false;
                  const description = PROVIDER_DESCRIPTIONS[p.slug];

                  return (
                    <AccordionItem
                      key={p.slug}
                      aria-label={p.name}
                      title={
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="font-medium">{p.name}</span>
                          {p.has_credentials ? (
                            <Chip size="sm" color="success" variant="flat" startContent={<CheckCircle2 size={12} />}>Credentials Saved</Chip>
                          ) : (
                            <Chip size="sm" color="default" variant="flat">Not Configured</Chip>
                          )}
                          {p.available ? (
                            <Chip size="sm" color="success" variant="dot">Available</Chip>
                          ) : (
                            <Chip size="sm" color="danger" variant="dot">Unavailable</Chip>
                          )}
                        </div>
                      }
                    >
                      <div className="space-y-4 pb-2">
                        {description && (
                          <p className="text-sm text-default-500">{description}</p>
                        )}

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                          <Input
                            label="API Key / Secret Key"
                            placeholder={p.has_credentials ? '(saved — enter new value to replace)' : 'Enter your API key'}
                            value={inputs.api_key}
                            onChange={(e) => setCredentialInputs(prev => ({ ...prev, [p.slug]: { ...inputs, api_key: e.target.value } }))}
                            type={visibility.api_key ? 'text' : 'password'}
                            variant="bordered"
                            size="sm"
                            description="Your provider's API key or secret key"
                            endContent={
                              <Button isIconOnly size="sm" variant="light" aria-label={visibility.api_key ? "Hide API key" : "Show API key"} onPress={() => setCredentialVisibility(prev => ({ ...prev, [p.slug]: { ...visibility, api_key: !visibility.api_key } }))}>
                                {visibility.api_key ? <EyeOff size={14} /> : <Eye size={14} />}
                              </Button>
                            }
                          />
                          <Input
                            label="Webhook Secret"
                            placeholder={p.has_credentials ? '(saved — enter new value to replace)' : 'Enter your webhook secret'}
                            value={inputs.webhook_secret}
                            onChange={(e) => setCredentialInputs(prev => ({ ...prev, [p.slug]: { ...inputs, webhook_secret: e.target.value } }))}
                            type={visibility.webhook_secret ? 'text' : 'password'}
                            variant="bordered"
                            size="sm"
                            description="Used to verify incoming webhook payloads from the provider"
                            endContent={
                              <Button isIconOnly size="sm" variant="light" aria-label={visibility.webhook_secret ? "Hide webhook secret" : "Show webhook secret"} onPress={() => setCredentialVisibility(prev => ({ ...prev, [p.slug]: { ...visibility, webhook_secret: !visibility.webhook_secret } }))}>
                                {visibility.webhook_secret ? <EyeOff size={14} /> : <Eye size={14} />}
                              </Button>
                            }
                          />
                        </div>

                        <div className="flex items-center justify-between">
                          <div>
                            {p.has_credentials && (
                              <Button
                                size="sm"
                                variant="light"
                                color="danger"
                                startContent={<Trash2 size={14} />}
                                onPress={() => handleDeleteCredentials(p.slug)}
                              >
                                Remove Credentials
                              </Button>
                            )}
                          </div>
                          {(inputs.api_key || inputs.webhook_secret) && (
                            <Button
                              size="sm"
                              color="primary"
                              startContent={!isSaving ? <Save size={14} /> : undefined}
                              isLoading={isSaving}
                              onPress={() => handleSaveCredentials(p.slug)}
                            >
                              Save Credentials
                            </Button>
                          )}
                        </div>
                      </div>
                    </AccordionItem>
                  );
                })}
              </Accordion>

              {realProviders.length > 0 && configuredProviderCount === 0 && (
                <div className="flex items-start gap-2 p-3 rounded-lg bg-default-100 dark:bg-default-50/10">
                  <HelpCircle size={16} className="text-default-500 shrink-0 mt-0.5" />
                  <div className="text-sm text-default-600 dark:text-default-400">
                    <p className="font-medium">How to get API credentials</p>
                    <ol className="list-decimal list-inside space-y-1 mt-1">
                      <li>Sign up for an account with your chosen provider (e.g. Stripe, Veriff, Onfido)</li>
                      <li>Navigate to their developer dashboard and create API keys</li>
                      <li>Copy your API Key and Webhook Secret into the fields above</li>
                      <li>Click &quot;Save Credentials&quot; — the provider will immediately become available</li>
                    </ol>
                    <p className="mt-2 text-default-500">
                      You only need to configure <strong>one</strong> provider. Credentials are stored encrypted and can be updated or removed at any time.
                    </p>
                  </div>
                </div>
              )}
            </CardBody>
          </Card>
        )}

        {/* ── Section 5: Invite Codes (conditional) ── */}
        {policy.registration_mode === 'invite_only' && (
          <Card shadow="sm">
            <CardHeader className="flex justify-between items-center">
              <div>
                <h3 className="text-lg font-semibold flex items-center gap-2">
                  <Ticket size={20} className="text-warning" /> Invite Codes
                </h3>
                <p className="text-sm text-default-500 mt-1">
                  Generate and manage invite codes for your community. Share codes with people you want to invite — they will enter the code during registration.
                </p>
              </div>
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
                <div className="text-center py-8">
                  <Ticket size={32} className="mx-auto text-default-300 mb-2" />
                  <p className="text-sm text-default-500">No invite codes generated yet.</p>
                  <p className="text-xs text-default-400 mt-1">Click &quot;Generate Codes&quot; to create your first invite codes.</p>
                </div>
              ) : (
                <Table aria-label="Invite codes" removeWrapper>
                  <TableHeader>
                    <TableColumn>Code</TableColumn>
                    <TableColumn>Uses</TableColumn>
                    <TableColumn>Status</TableColumn>
                    <TableColumn>Note</TableColumn>
                    <TableColumn>Expires</TableColumn>
                    <TableColumn>Actions</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {inviteCodes.map((ic) => (
                      <TableRow key={ic.id}>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <code className="text-sm font-mono">{ic.code}</code>
                            <Button isIconOnly size="sm" variant="light" aria-label="Copy invite code" onPress={() => copyToClipboard(ic.code)}>
                              <Copy size={12} />
                            </Button>
                          </div>
                        </TableCell>
                        <TableCell>
                          <span className={ic.uses_count >= ic.max_uses ? 'text-warning' : ''}>
                            {ic.uses_count}/{ic.max_uses}
                          </span>
                        </TableCell>
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
                          <span className="text-sm text-default-500">
                            {ic.expires_at ? new Date(ic.expires_at).toLocaleDateString() : 'Never'}
                          </span>
                        </TableCell>
                        <TableCell>
                          {ic.is_active ? (
                            <Button isIconOnly size="sm" variant="light" color="danger" aria-label="Deactivate invite code" onPress={() => handleDeactivateCode(ic.id)}>
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
                  <p className="text-sm text-default-500">Generated {generatedCodes.length} code(s). Share these with the people you want to invite:</p>
                  <div className="space-y-1">
                    {generatedCodes.map((code) => (
                      <div key={code} className="flex items-center justify-between p-2 bg-default-100 rounded-lg">
                        <code className="font-mono text-sm">{code}</code>
                        <Button isIconOnly size="sm" variant="light" aria-label="Copy code" onPress={() => copyToClipboard(code)}>
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
                    Copy All Codes
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
                    description="How many invite codes to generate (max 100)"
                  />
                  <Input
                    label="Max uses per code"
                    type="number"
                    min={1}
                    max={1000}
                    value={String(generateMaxUses)}
                    onChange={(e) => setGenerateMaxUses(Math.max(1, Math.min(1000, parseInt(e.target.value) || 1)))}
                    variant="bordered"
                    description="Set to 1 for single-use codes, or higher for shared codes"
                  />
                  <Input
                    label="Expiry date (optional)"
                    type="date"
                    value={generateExpiry}
                    onChange={(e) => setGenerateExpiry(e.target.value)}
                    variant="bordered"
                    description="Codes expire at midnight on this date. Leave blank for codes that never expire."
                  />
                  <Input
                    label="Note (optional)"
                    placeholder="e.g. March 2026 onboarding batch"
                    value={generateNote}
                    onChange={(e) => setGenerateNote(e.target.value)}
                    variant="bordered"
                    description="Internal note to help you remember what this batch was for"
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

        {/* ── Section 6: How Registration Modes Work ── */}
        <Card shadow="sm" className="bg-primary-50/50 dark:bg-primary-900/10 border border-primary-200 dark:border-primary-800">
          <CardBody>
            <div className="flex gap-3">
              <Info size={20} className="text-primary shrink-0 mt-0.5" />
              <div className="text-sm space-y-2">
                <p className="font-semibold text-base">Understanding Registration Modes</p>
                <div className="space-y-2 text-default-700 dark:text-default-300">
                  <div className="flex items-start gap-2">
                    <Globe size={14} className="shrink-0 mt-1 text-success" />
                    <p><strong>Open Registration</strong> — Lowest friction. Users sign up and are active immediately. Good for public communities prioritising growth.</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <UserCheck size={14} className="shrink-0 mt-1 text-primary" />
                    <p><strong>Open + Admin Approval</strong> — Users register but stay pending until an admin manually approves them. Balances accessibility with control.</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <ShieldCheck size={14} className="shrink-0 mt-1 text-secondary" />
                    <p><strong>Identity Verification</strong> — Users must pass automated ID checks (document scan, selfie match) via a third-party provider. Highest trust level for automated onboarding.</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <Lock size={14} className="shrink-0 mt-1 text-warning" />
                    <p><strong>Invite Only</strong> — Registration is closed. Only users with a valid invite code can join. Best for private communities or controlled rollouts.</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <Clock size={14} className="shrink-0 mt-1 text-default-500" />
                    <p><strong>Waitlist</strong> — Users join a queue and are activated in order. Useful for managing capacity or creating exclusivity during launch.</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <Shield size={14} className="shrink-0 mt-1 text-default-400" />
                    <p><strong>Government/Digital ID</strong> — Future feature. Will support government-issued digital identity credentials (EUDI Wallet, UK trust framework, etc.).</p>
                  </div>
                </div>
                <Divider className="my-2" />
                <p className="text-default-500">
                  <strong>Tip:</strong> You can change modes at any time. Existing users are not affected — only new registrations use the updated policy.
                  Email verification is independent of the registration mode and can be enabled alongside any method.
                </p>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* ── Section 7: Provider Health Dashboard (conditional) ── */}
        {showVerificationOptions && <ProviderHealthDashboard />}

        {/* ── Section 8: Pending Reviews ── */}
        <VerificationReviewQueue />

        {/* ── Section 9: Audit Log ── */}
        <VerificationAuditLog />
      </div>
    </div>
  );
}

export default RegistrationPolicySettings;
