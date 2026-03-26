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

import { useTranslation } from 'react-i18next';
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

const REGISTRATION_MODE_ICONS: Record<string, typeof Globe> = {
  open: Globe,
  open_with_approval: UserCheck,
  verified_identity: ShieldCheck,
  invite_only: Lock,
  waitlist: Clock,
  government_id: Shield,
};

const REGISTRATION_MODE_COLORS: Record<string, 'success' | 'primary' | 'secondary' | 'warning' | 'default'> = {
  open: 'success',
  open_with_approval: 'primary',
  verified_identity: 'secondary',
  invite_only: 'warning',
  waitlist: 'default',
  government_id: 'default',
};

export function RegistrationPolicySettings() {
  const { t } = useTranslation('admin');
  usePageTitle(t('system.page_title'));
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

  const REGISTRATION_MODES = [
    { key: 'open', label: t('system.reg.mode_open'), description: t('system.reg.mode_open_desc'), icon: REGISTRATION_MODE_ICONS.open, color: REGISTRATION_MODE_COLORS.open },
    { key: 'open_with_approval', label: t('system.reg.mode_open_approval'), description: t('system.reg.mode_open_approval_desc'), icon: REGISTRATION_MODE_ICONS.open_with_approval, color: REGISTRATION_MODE_COLORS.open_with_approval },
    { key: 'verified_identity', label: t('system.reg.mode_verified'), description: t('system.reg.mode_verified_desc'), icon: REGISTRATION_MODE_ICONS.verified_identity, color: REGISTRATION_MODE_COLORS.verified_identity },
    { key: 'invite_only', label: t('system.reg.mode_invite'), description: t('system.reg.mode_invite_desc'), icon: REGISTRATION_MODE_ICONS.invite_only, color: REGISTRATION_MODE_COLORS.invite_only },
    { key: 'waitlist', label: t('system.reg.mode_waitlist'), description: t('system.reg.mode_waitlist_desc'), icon: REGISTRATION_MODE_ICONS.waitlist, color: REGISTRATION_MODE_COLORS.waitlist },
    { key: 'government_id', label: t('system.reg.mode_gov_id'), description: t('system.reg.mode_gov_id_desc'), icon: REGISTRATION_MODE_ICONS.government_id, color: REGISTRATION_MODE_COLORS.government_id },
  ];

  const VERIFICATION_LEVELS = [
    { key: 'none', label: t('system.reg.level_none'), description: t('system.reg.level_none_desc') },
    { key: 'document_only', label: t('system.reg.level_document'), description: t('system.reg.level_document_desc') },
    { key: 'document_selfie', label: t('system.reg.level_document_selfie'), description: t('system.reg.level_document_selfie_desc') },
    { key: 'reusable_digital_id', label: t('system.reg.level_reusable'), description: t('system.reg.level_reusable_desc') },
    { key: 'manual_review', label: t('system.reg.level_manual'), description: t('system.reg.level_manual_desc') },
  ];

  const POST_VERIFICATION_ACTIONS = [
    { key: 'activate', label: t('system.reg.post_activate'), description: t('system.reg.post_activate_desc') },
    { key: 'admin_approval', label: t('system.reg.post_admin_approval'), description: t('system.reg.post_admin_approval_desc') },
    { key: 'limited_access', label: t('system.reg.post_limited'), description: t('system.reg.post_limited_desc') },
    { key: 'reject_on_fail', label: t('system.reg.post_reject'), description: t('system.reg.post_reject_desc') },
  ];

  const FALLBACK_MODES = [
    { key: 'none', label: t('system.reg.fallback_none'), description: t('system.reg.fallback_none_desc') },
    { key: 'admin_review', label: t('system.reg.fallback_admin'), description: t('system.reg.fallback_admin_desc') },
    { key: 'native_registration', label: t('system.reg.fallback_native'), description: t('system.reg.fallback_native_desc') },
  ];

  const PROVIDER_DESCRIPTIONS: Record<string, string> = {
    stripe_identity: t('system.reg.provider_stripe'),
    veriff: t('system.reg.provider_veriff'),
    jumio: t('system.reg.provider_jumio'),
    onfido: t('system.reg.provider_onfido'),
    idenfy: t('system.reg.provider_idenfy'),
  };

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
      toast.error(t('system.failed_to_load_registration_policy'));
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
        toast.success(t('system.registration_policy_saved_successfully'));
        fetchData();
      }
    } catch {
      toast.error(t('system.failed_to_save_registration_policy'));
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
      toast.success(t('system.reg.credentials_saved', { provider: providers.find(p => p.slug === slug)?.name || t('system.reg.provider') }));
      setCredentialInputs(prev => ({ ...prev, [slug]: { api_key: '', webhook_secret: '' } }));
      fetchData();
    } catch {
      toast.error(t('system.failed_to_save_credentials_please_check'));
    } finally {
      setSavingCredentials(prev => ({ ...prev, [slug]: false }));
    }
  };

  const handleDeleteCredentials = async (slug: string) => {
    try {
      await api.delete(`/v2/admin/identity/provider-credentials/${slug}`);
      toast.success(t('system.credentials_removed_successfully'));
      fetchData();
    } catch {
      toast.error(t('system.failed_to_remove_credentials'));
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
        toast.success(t('system.reg.codes_generated', { count: res.data.codes.length }));
      }
    } catch {
      toast.error(t('system.failed_to_generate_invite_codes'));
    } finally {
      setGenerating(false);
    }
  };

  const handleDeactivateCode = async (id: number) => {
    try {
      await api.delete(`/v2/admin/invite-codes/${id}`);
      toast.success(t('system.invite_code_deactivated'));
      fetchInviteCodes();
    } catch {
      toast.error(t('system.failed_to_deactivate_invite_code'));
    }
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success(t('system.copied_to_clipboard'));
  };

  const showVerificationOptions = policy?.registration_mode === 'verified_identity' || policy?.registration_mode === 'government_id';
  const selectedMode = REGISTRATION_MODES.find(m => m.key === policy?.registration_mode);
  const availableProviderCount = providers.filter(p => p.slug !== 'mock' && p.available).length;
  const configuredProviderCount = providers.filter(p => p.slug !== 'mock' && p.has_credentials).length;
  const realProviders = providers.filter(p => p.slug !== 'mock');

  if (loading || !policy) {
    return (
      <div>
        <PageHeader title={t('system.registration_policy_settings_title')} description={t('system.reg.page_description', { name: tenant?.name || t('system.reg.your_community') })} />
        <div className="flex justify-center py-16"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={t('system.registration_policy_settings_title')} description={t('system.reg.page_description', { name: tenant?.name || t('system.reg.your_community') })} />

      <div className="space-y-6">

        {/* ── Section 1: Registration Method ── */}
        <Card shadow="sm">
          <CardHeader className="pb-0">
            <div>
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <ShieldCheck size={20} className="text-primary" /> {t('system.reg.registration_method')}
              </h3>
              <p className="text-sm text-default-500 mt-1">
                {t('system.reg.registration_method_desc')}
              </p>
            </div>
          </CardHeader>
          <CardBody className="gap-4">
            <Select
              label={t('system.label_registration_method')}
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
                <span className="text-sm">{t('system.reg.gov_id_future')}</span>
              </div>
            )}

            {policy.registration_mode === 'waitlist' && (
              <div className="flex items-center gap-2 p-3 rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400">
                <Users size={16} className="shrink-0" />
                <span className="text-sm">{t('system.reg.waitlist_info')}</span>
              </div>
            )}

            {policy.registration_mode === 'verified_identity' && availableProviderCount === 0 && (
              <div className="flex items-center gap-2 p-3 rounded-lg bg-danger-50 text-danger-700 dark:bg-danger-900/20 dark:text-danger-400">
                <AlertTriangle size={16} className="shrink-0" />
                <span className="text-sm">{t('system.reg.no_providers_warning')}</span>
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
                  <UserCheck size={20} className="text-secondary" /> {t('system.reg.verification_settings')}
                </h3>
                <p className="text-sm text-default-500 mt-1">
                  {t('system.reg.verification_settings_desc')}
                </p>
              </div>
            </CardHeader>
            <CardBody className="gap-4">
              <Select
                label={t('system.label_verification_provider')}
                selectedKeys={policy.verification_provider ? [policy.verification_provider] : []}
                onSelectionChange={(keys) => {
                  const key = Array.from(keys)[0] as string;
                  setPolicy(prev => prev ? { ...prev, verification_provider: key || null } : prev);
                }}
                variant="bordered"
                description={
                  availableProviderCount > 0
                    ? t('system.reg.providers_available', { count: availableProviderCount })
                    : t('system.reg.no_providers_yet')
                }
              >
                {providers.map((p) => (
                  <SelectItem key={p.slug} textValue={p.name}>
                    <div className="flex items-center gap-2">
                      <span>{p.name}</span>
                      {p.available ? (
                        <Chip size="sm" color="success" variant="flat">{t('system.reg.available')}</Chip>
                      ) : (
                        <Chip size="sm" color="danger" variant="flat">{t('system.reg.unavailable')}</Chip>
                      )}
                    </div>
                  </SelectItem>
                ))}
              </Select>

              <Select
                label={t('system.label_verification_level')}
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
                label={t('system.label_after_verification_passes')}
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
                label={t('system.label_fallback_if_provider_unavailable')}
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
                <Mail size={20} className="text-primary" /> {t('system.reg.email_verification')}
              </h3>
            </div>
          </CardHeader>
          <CardBody>
            <div className="flex items-center justify-between gap-4">
              <div>
                <p className="font-medium">{t('system.reg.require_email')}</p>
                <p className="text-sm text-default-500">
                  {t('system.reg.require_email_desc')}
                </p>
              </div>
              <Switch
                isSelected={policy.require_email_verify}
                onValueChange={(val) => setPolicy(prev => prev ? { ...prev, require_email_verify: val } : prev)}
                aria-label={t('system.label_require_email_verification')}
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
            {t('system.reg.save_policy')}
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
                    <Key size={20} className="text-warning" /> {t('system.reg.identity_providers')}
                  </h3>
                  <div className="flex items-center gap-2">
                    {configuredProviderCount > 0 && (
                      <Chip size="sm" color="success" variant="flat" startContent={<CheckCircle2 size={12} />}>
                        {t('system.reg.n_configured', { count: configuredProviderCount })}
                      </Chip>
                    )}
                    <Chip size="sm" color={availableProviderCount > 0 ? 'success' : 'default'} variant="flat">
                      {t('system.reg.n_available', { count: availableProviderCount })}
                    </Chip>
                  </div>
                </div>
                <p className="text-sm text-default-500 mt-1">
                  {t('system.reg.provider_credentials_desc')}
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
                            <Chip size="sm" color="success" variant="flat" startContent={<CheckCircle2 size={12} />}>{t('system.reg.credentials_saved_chip')}</Chip>
                          ) : (
                            <Chip size="sm" color="default" variant="flat">{t('system.reg.not_configured')}</Chip>
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
                            label={t('system.label_a_p_i_key_secret_key')}
                            placeholder={p.has_credentials ? t('system.reg.placeholder_saved') : t('system.reg.placeholder_api_key')}
                            value={inputs.api_key}
                            onChange={(e) => setCredentialInputs(prev => ({ ...prev, [p.slug]: { ...inputs, api_key: e.target.value } }))}
                            type={visibility.api_key ? 'text' : 'password'}
                            variant="bordered"
                            size="sm"
                            description={t('system.reg.api_key_desc')}
                            endContent={
                              <Button isIconOnly size="sm" variant="light" aria-label={visibility.api_key ? t('system.reg.hide_api_key') : t('system.reg.show_api_key')} onPress={() => setCredentialVisibility(prev => ({ ...prev, [p.slug]: { ...visibility, api_key: !visibility.api_key } }))}>
                                {visibility.api_key ? <EyeOff size={14} /> : <Eye size={14} />}
                              </Button>
                            }
                          />
                          <Input
                            label={t('system.label_webhook_secret')}
                            placeholder={p.has_credentials ? t('system.reg.placeholder_saved') : t('system.reg.placeholder_webhook')}
                            value={inputs.webhook_secret}
                            onChange={(e) => setCredentialInputs(prev => ({ ...prev, [p.slug]: { ...inputs, webhook_secret: e.target.value } }))}
                            type={visibility.webhook_secret ? 'text' : 'password'}
                            variant="bordered"
                            size="sm"
                            description={t('system.desc_used_to_verify_incoming_webhook_payloads')}
                            endContent={
                              <Button isIconOnly size="sm" variant="light" aria-label={visibility.webhook_secret ? t('system.reg.hide_webhook') : t('system.reg.show_webhook')} onPress={() => setCredentialVisibility(prev => ({ ...prev, [p.slug]: { ...visibility, webhook_secret: !visibility.webhook_secret } }))}>
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
                                {t('system.reg.remove_credentials')}
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
                              {t('system.reg.save_credentials')}
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
                    <p className="font-medium">{t('system.reg.how_to_get_credentials')}</p>
                    <ol className="list-decimal list-inside space-y-1 mt-1">
                      <li>{t('system.reg.credential_step_1')}</li>
                      <li>{t('system.reg.credential_step_2')}</li>
                      <li>{t('system.reg.credential_step_3')}</li>
                      <li>{t('system.reg.credential_step_4')}</li>
                    </ol>
                    <p className="mt-2 text-default-500">
                      {t('system.reg.credential_note')}
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
                  <Ticket size={20} className="text-warning" /> {t('system.reg.invite_codes')}
                </h3>
                <p className="text-sm text-default-500 mt-1">
                  {t('system.reg.invite_codes_desc')}
                </p>
              </div>
              <Button
                size="sm"
                color="primary"
                startContent={<Plus size={14} />}
                onPress={() => { setGeneratedCodes([]); setGenerateCount(1); setGenerateMaxUses(1); setGenerateExpiry(''); setGenerateNote(''); generateModal.onOpen(); }}
              >
                {t('system.reg.generate_codes')}
              </Button>
            </CardHeader>
            <CardBody>
              {inviteCodesLoading ? (
                <div className="flex justify-center py-4"><Spinner /></div>
              ) : inviteCodes.length === 0 ? (
                <div className="text-center py-8">
                  <Ticket size={32} className="mx-auto text-default-300 mb-2" />
                  <p className="text-sm text-default-500">{t('system.reg.no_invite_codes')}</p>
                  <p className="text-xs text-default-400 mt-1">{t('system.reg.no_invite_codes_hint')}</p>
                </div>
              ) : (
                <Table aria-label={t('system.label_invite_codes')} removeWrapper>
                  <TableHeader>
                    <TableColumn>{t('system.reg.col_code')}</TableColumn>
                    <TableColumn>{t('system.reg.col_uses')}</TableColumn>
                    <TableColumn>{t('system.reg.col_status')}</TableColumn>
                    <TableColumn>{t('system.reg.col_note')}</TableColumn>
                    <TableColumn>{t('system.reg.col_expires')}</TableColumn>
                    <TableColumn>{t('system.reg.col_actions')}</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {inviteCodes.map((ic) => (
                      <TableRow key={ic.id}>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <code className="text-sm font-mono">{ic.code}</code>
                            <Button isIconOnly size="sm" variant="light" aria-label={t('system.label_copy_invite_code')} onPress={() => copyToClipboard(ic.code)}>
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
                            <Chip size="sm" color="default" variant="flat">{t('system.reg.deactivated')}</Chip>
                          ) : ic.uses_count >= ic.max_uses ? (
                            <Chip size="sm" color="warning" variant="flat">{t('system.reg.exhausted')}</Chip>
                          ) : ic.expires_at && new Date(ic.expires_at) < new Date() ? (
                            <Chip size="sm" color="warning" variant="flat">{t('system.reg.expired')}</Chip>
                          ) : (
                            <Chip size="sm" color="success" variant="flat">{t('active')}</Chip>
                          )}
                        </TableCell>
                        <TableCell><span className="text-sm text-default-500">{ic.note || '—'}</span></TableCell>
                        <TableCell>
                          <span className="text-sm text-default-500">
                            {ic.expires_at ? new Date(ic.expires_at).toLocaleDateString() : t('system.reg.never')}
                          </span>
                        </TableCell>
                        <TableCell>
                          {ic.is_active ? (
                            <Button isIconOnly size="sm" variant="light" color="danger" aria-label={t('system.label_deactivate_invite_code')} onPress={() => handleDeactivateCode(ic.id)}>
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
                <p className="text-xs text-default-400 mt-2">{t('system.reg.total_codes', { count: inviteCodesTotal })}</p>
              )}
            </CardBody>
          </Card>
        )}

        {/* Generate Codes Modal */}
        <Modal isOpen={generateModal.isOpen} onOpenChange={generateModal.onOpenChange} size="md">
          <ModalContent>
            <ModalHeader>{t('system.reg.generate_invite_codes')}</ModalHeader>
            <ModalBody>
              {generatedCodes.length > 0 ? (
                <div className="space-y-2">
                  <p className="text-sm text-default-500">{t('system.reg.generated_share', { count: generatedCodes.length })}</p>
                  <div className="space-y-1">
                    {generatedCodes.map((code) => (
                      <div key={code} className="flex items-center justify-between p-2 bg-default-100 rounded-lg">
                        <code className="font-mono text-sm">{code}</code>
                        <Button isIconOnly size="sm" variant="light" aria-label={t('system.label_copy_code')} onPress={() => copyToClipboard(code)}>
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
                    {t('system.reg.copy_all_codes')}
                  </Button>
                </div>
              ) : (
                <div className="space-y-4">
                  <Input
                    label={t('system.label_number_of_codes')}
                    type="number"
                    min={1}
                    max={100}
                    value={String(generateCount)}
                    onChange={(e) => setGenerateCount(Math.max(1, Math.min(100, parseInt(e.target.value) || 1)))}
                    variant="bordered"
                    description={t('system.reg.generate_count_desc')}
                  />
                  <Input
                    label={t('system.label_max_uses_per_code')}
                    type="number"
                    min={1}
                    max={1000}
                    value={String(generateMaxUses)}
                    onChange={(e) => setGenerateMaxUses(Math.max(1, Math.min(1000, parseInt(e.target.value) || 1)))}
                    variant="bordered"
                    description={t('system.reg.max_uses_desc')}
                  />
                  <Input
                    label={t('system.reg.expiry_date')}
                    type="date"
                    value={generateExpiry}
                    onChange={(e) => setGenerateExpiry(e.target.value)}
                    variant="bordered"
                    description={t('system.desc_codes_expire_at_midnight_on_this_date_l')}
                  />
                  <Input
                    label={t('system.reg.note_optional')}
                    placeholder={t('system.reg.note_placeholder')}
                    value={generateNote}
                    onChange={(e) => setGenerateNote(e.target.value)}
                    variant="bordered"
                    description={t('system.desc_internal_note_to_help_you_remember_what_')}
                  />
                </div>
              )}
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={generateModal.onClose}>
                {generatedCodes.length > 0 ? t('system.reg.done') : t('cancel')}
              </Button>
              {generatedCodes.length === 0 && (
                <Button color="primary" onPress={handleGenerateInviteCodes} isLoading={generating} isDisabled={generating}>
                  {t('system.reg.generate')}
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
                <p className="font-semibold text-base">{t('system.reg.understanding_modes')}</p>
                <div className="space-y-2 text-default-700 dark:text-default-300">
                  <div className="flex items-start gap-2">
                    <Globe size={14} className="shrink-0 mt-1 text-success" />
                    <p>{t('system.reg.info_open')}</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <UserCheck size={14} className="shrink-0 mt-1 text-primary" />
                    <p>{t('system.reg.info_open_approval')}</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <ShieldCheck size={14} className="shrink-0 mt-1 text-secondary" />
                    <p>{t('system.reg.info_verified')}</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <Lock size={14} className="shrink-0 mt-1 text-warning" />
                    <p>{t('system.reg.info_invite')}</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <Clock size={14} className="shrink-0 mt-1 text-default-500" />
                    <p>{t('system.reg.info_waitlist')}</p>
                  </div>
                  <div className="flex items-start gap-2">
                    <Shield size={14} className="shrink-0 mt-1 text-default-400" />
                    <p>{t('system.reg.info_gov_id')}</p>
                  </div>
                </div>
                <Divider className="my-2" />
                <p className="text-default-500">
                  {t('system.reg.tip_change_modes')}
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
