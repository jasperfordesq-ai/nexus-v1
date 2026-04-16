// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * System Configuration
 * Grouped settings editor with descriptions, validation, and reset-to-defaults.
 * Replaces the legacy flat key-value editor.
 */

import { useEffect, useState, useCallback, type ReactNode } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Input,
  Button,
  Spinner,
  Switch,
  Select,
  SelectItem,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Tooltip,
} from '@heroui/react';
import {
  Save,
  RefreshCw,
  RotateCcw,
  Settings2,
  UserPlus,
  Wallet,
  Shield,
  Bell,
  Gauge,
  Info,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Config schema types
// ─────────────────────────────────────────────────────────────────────────────

interface ConfigSettingDef {
  key: string;
  label: string;
  description: string;
  type: 'text' | 'number' | 'boolean' | 'select' | 'textarea' | 'email' | 'url';
  default?: string | number | boolean;
  options?: { label: string; value: string }[];
  validation?: {
    required?: boolean;
    min?: number;
    max?: number;
    pattern?: string;
  };
}

interface ConfigGroup {
  key: string;
  label: string;
  description: string;
  icon: ReactNode;
  settings: ConfigSettingDef[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Config schema definition
// ─────────────────────────────────────────────────────────────────────────────

// Supported platform language codes — names resolved via i18n inside the component
const SUPPORTED_LOCALE_CODES = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'] as const;

/** Build the config schema with translated labels/descriptions. Called inside the component so t() is available. */
function buildConfigSchema(t: (key: string) => string): ConfigGroup[] {
  return [
    {
      key: 'general',
      label: t('enterprise.system_config.general_label'),
      description: t('enterprise.system_config.general_desc'),
      icon: <Settings2 size={18} />,
      settings: [
        { key: 'site_name', label: t('enterprise.system_config.setting_site_name_label'), description: t('enterprise.system_config.setting_site_name_desc'), type: 'text', default: '' },
        { key: 'site_description', label: t('enterprise.system_config.setting_site_description_label'), description: t('enterprise.system_config.setting_site_description_desc'), type: 'textarea', default: '' },
        { key: 'contact_email', label: t('enterprise.system_config.setting_contact_email_label'), description: t('enterprise.system_config.setting_contact_email_desc'), type: 'email', default: '' },
        { key: 'contact_phone', label: t('enterprise.system_config.setting_contact_phone_label'), description: t('enterprise.system_config.setting_contact_phone_desc'), type: 'text', default: '' },
        { key: 'timezone', label: t('enterprise.system_config.setting_timezone_label'), description: t('enterprise.system_config.setting_timezone_desc'), type: 'text', default: 'UTC' },
        { key: 'footer_text', label: t('enterprise.system_config.setting_footer_text_label'), description: t('enterprise.system_config.setting_footer_text_desc'), type: 'textarea', default: '' },
        {
          key: 'locale', label: t('enterprise.system_config.setting_locale_label'), description: t('enterprise.system_config.setting_locale_desc'), type: 'select', default: 'en',
          options: SUPPORTED_LOCALE_CODES.map((code) => ({ label: code, value: code })),
        },
      ],
    },
    {
      key: 'registration',
      label: t('enterprise.system_config.registration_label'),
      description: t('enterprise.system_config.registration_desc'),
      icon: <UserPlus size={18} />,
      settings: [
        { key: 'registration_enabled', label: t('enterprise.system_config.setting_registration_enabled_label'), description: t('enterprise.system_config.setting_registration_enabled_desc'), type: 'boolean', default: true },
        { key: 'require_approval', label: t('enterprise.system_config.setting_require_approval_label'), description: t('enterprise.system_config.setting_require_approval_desc'), type: 'boolean', default: false },
        { key: 'require_email_verification', label: t('enterprise.system_config.setting_require_email_verification_label'), description: t('enterprise.system_config.setting_require_email_verification_desc'), type: 'boolean', default: true },
        { key: 'maintenance_mode', label: t('enterprise.system_config.setting_maintenance_mode_label'), description: t('enterprise.system_config.setting_maintenance_mode_desc'), type: 'boolean', default: false },
        { key: 'onboarding_enabled', label: t('enterprise.system_config.setting_onboarding_enabled_label'), description: t('enterprise.system_config.setting_onboarding_enabled_desc'), type: 'boolean', default: true },
        { key: 'welcome_message', label: t('enterprise.system_config.setting_welcome_message_label'), description: t('enterprise.system_config.setting_welcome_message_desc'), type: 'textarea', default: '' },
      ],
    },
    {
      key: 'wallet',
      label: t('enterprise.system_config.wallet_label'),
      description: t('enterprise.system_config.wallet_desc'),
      icon: <Wallet size={18} />,
      settings: [
        { key: 'starting_balance', label: t('enterprise.system_config.setting_starting_balance_label'), description: t('enterprise.system_config.setting_starting_balance_desc'), type: 'number', default: 0, validation: { min: 0 } },
        { key: 'max_transaction', label: t('enterprise.system_config.setting_max_transaction_label'), description: t('enterprise.system_config.setting_max_transaction_desc'), type: 'number', default: 0, validation: { min: 0 } },
        { key: 'currency_name', label: t('enterprise.system_config.setting_currency_name_label'), description: t('enterprise.system_config.setting_currency_name_desc'), type: 'text', default: 'Hours' },
        { key: 'currency_symbol', label: t('enterprise.system_config.setting_currency_symbol_label'), description: t('enterprise.system_config.setting_currency_symbol_desc'), type: 'text', default: 'h' },
      ],
    },
    {
      key: 'content',
      label: t('enterprise.system_config.content_label'),
      description: t('enterprise.system_config.content_desc'),
      icon: <Shield size={18} />,
      settings: [
        { key: 'auto_approve_listings', label: t('enterprise.system_config.setting_auto_approve_listings_label'), description: t('enterprise.system_config.setting_auto_approve_listings_desc'), type: 'boolean', default: true },
        { key: 'auto_approve_blog', label: t('enterprise.system_config.setting_auto_approve_blog_label'), description: t('enterprise.system_config.setting_auto_approve_blog_desc'), type: 'boolean', default: false },
        { key: 'max_listing_images', label: t('enterprise.system_config.setting_max_listing_images_label'), description: t('enterprise.system_config.setting_max_listing_images_desc'), type: 'number', default: 5, validation: { min: 1, max: 20 } },
        { key: 'profanity_filter', label: t('enterprise.system_config.setting_profanity_filter_label'), description: t('enterprise.system_config.setting_profanity_filter_desc'), type: 'boolean', default: false },
      ],
    },
    {
      key: 'notifications',
      label: t('enterprise.system_config.notifications_label'),
      description: t('enterprise.system_config.notifications_desc'),
      icon: <Bell size={18} />,
      settings: [
        { key: 'email_notifications_enabled', label: t('enterprise.system_config.setting_email_notifications_enabled_label'), description: t('enterprise.system_config.setting_email_notifications_enabled_desc'), type: 'boolean', default: true },
        { key: 'push_notifications_enabled', label: t('enterprise.system_config.setting_push_notifications_enabled_label'), description: t('enterprise.system_config.setting_push_notifications_enabled_desc'), type: 'boolean', default: true },
        {
          key: 'digest_frequency', label: t('enterprise.system_config.setting_digest_frequency_label'), description: t('enterprise.system_config.setting_digest_frequency_desc'), type: 'select', default: 'weekly',
          options: [
            { label: t('enterprise.system_config.digest_daily'), value: 'daily' },
            { label: t('enterprise.system_config.digest_weekly'), value: 'weekly' },
            { label: t('enterprise.system_config.digest_monthly'), value: 'monthly' },
            { label: t('enterprise.system_config.digest_never'), value: 'never' },
          ],
        },
      ],
    },
    {
      key: 'limits',
      label: t('enterprise.system_config.limits_label'),
      description: t('enterprise.system_config.limits_desc'),
      icon: <Gauge size={18} />,
      settings: [
        { key: 'max_listings_per_user', label: t('enterprise.system_config.setting_max_listings_per_user_label'), description: t('enterprise.system_config.setting_max_listings_per_user_desc'), type: 'number', default: 0, validation: { min: 0 } },
        { key: 'max_groups_per_user', label: t('enterprise.system_config.setting_max_groups_per_user_label'), description: t('enterprise.system_config.setting_max_groups_per_user_desc'), type: 'number', default: 0, validation: { min: 0 } },
        { key: 'max_file_upload_mb', label: t('enterprise.system_config.setting_max_file_upload_mb_label'), description: t('enterprise.system_config.setting_max_file_upload_mb_desc'), type: 'number', default: 10, validation: { min: 1, max: 100 } },
      ],
    },
  ];
}

/** Static type+default definitions for normalization/validation — no labels (those come from translations) */
type StaticSettingDef = Pick<ConfigSettingDef, 'key' | 'type' | 'default' | 'validation'>;

const STATIC_SETTINGS: StaticSettingDef[] = [
  { key: 'site_name', type: 'text', default: '' },
  { key: 'site_description', type: 'textarea', default: '' },
  { key: 'contact_email', type: 'email', default: '' },
  { key: 'contact_phone', type: 'text', default: '' },
  { key: 'timezone', type: 'text', default: 'UTC' },
  { key: 'footer_text', type: 'textarea', default: '' },
  { key: 'locale', type: 'select', default: 'en' },
  { key: 'registration_enabled', type: 'boolean', default: true },
  { key: 'require_approval', type: 'boolean', default: false },
  { key: 'require_email_verification', type: 'boolean', default: true },
  { key: 'maintenance_mode', type: 'boolean', default: false },
  { key: 'onboarding_enabled', type: 'boolean', default: true },
  { key: 'welcome_message', type: 'textarea', default: '' },
  { key: 'starting_balance', type: 'number', default: 0, validation: { min: 0 } },
  { key: 'max_transaction', type: 'number', default: 0, validation: { min: 0 } },
  { key: 'currency_name', type: 'text', default: 'Hours' },
  { key: 'currency_symbol', type: 'text', default: 'h' },
  { key: 'auto_approve_listings', type: 'boolean', default: true },
  { key: 'auto_approve_blog', type: 'boolean', default: false },
  { key: 'max_listing_images', type: 'number', default: 5, validation: { min: 1, max: 20 } },
  { key: 'profanity_filter', type: 'boolean', default: false },
  { key: 'email_notifications_enabled', type: 'boolean', default: true },
  { key: 'push_notifications_enabled', type: 'boolean', default: true },
  { key: 'digest_frequency', type: 'select', default: 'weekly' },
  { key: 'max_listings_per_user', type: 'number', default: 0, validation: { min: 0 } },
  { key: 'max_groups_per_user', type: 'number', default: 0, validation: { min: 0 } },
  { key: 'max_file_upload_mb', type: 'number', default: 10, validation: { min: 1, max: 100 } },
];

/** All known schema keys — static list for normalization/validation (independent of translations) */
const SCHEMA_KEYS = new Set(STATIC_SETTINGS.map((s) => s.key));

/** Schema definitions keyed by setting key for fast lookup */
const SCHEMA_MAP = new Map<string, StaticSettingDef>(STATIC_SETTINGS.map((s) => [s.key, s]));

/**
 * Normalize a raw API value to the correct JS type based on schema definition.
 * Handles all the string/boolean/number coercion issues from mixed storage formats.
 */
function normalizeValue(raw: unknown, def: ConfigSettingDef): unknown {
  if (raw === undefined || raw === null) return def.default ?? '';

  switch (def.type) {
    case 'boolean':
      if (typeof raw === 'boolean') return raw;
      if (typeof raw === 'number') return raw !== 0;
      if (typeof raw === 'string') {
        return raw === 'true' || raw === '1' || raw === 'on';
      }
      return Boolean(raw);

    case 'number': {
      if (typeof raw === 'number') return raw;
      const num = Number(raw);
      return isNaN(num) ? (def.default ?? 0) : num;
    }

    case 'select':
    case 'text':
    case 'email':
    case 'url':
    case 'textarea':
      return String(raw ?? '');

    default:
      return raw;
  }
}

/** Normalize all schema keys in a loaded config object */
function normalizeConfig(data: Record<string, unknown>): Record<string, unknown> {
  const result = { ...data };
  for (const [key, def] of SCHEMA_MAP) {
    result[key] = normalizeValue(result[key], def);
  }
  return result;
}

// ─────────────────────────────────────────────────────────────────────────────
// Validation helpers
// ─────────────────────────────────────────────────────────────────────────────

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const URL_RE = /^https?:\/\/.+/;

function validateSetting(def: ConfigSettingDef, value: unknown, t: (key: string, opts?: Record<string, unknown>) => string): string | null {
  const str = String(value ?? '');

  if (def.validation?.required && str.trim() === '') {
    return t('enterprise.system_config.validation_required', { label: def.label });
  }

  if (def.type === 'email' && str.trim() !== '' && !EMAIL_RE.test(str)) {
    return t('enterprise.system_config.validation_invalid_email');
  }

  if (def.type === 'url' && str.trim() !== '' && !URL_RE.test(str)) {
    return t('enterprise.system_config.validation_invalid_url');
  }

  if (def.type === 'number' && str.trim() !== '') {
    const num = Number(str);
    if (isNaN(num)) return t('enterprise.system_config.validation_must_be_number');
    if (def.validation?.min !== undefined && num < def.validation.min) {
      return t('enterprise.system_config.validation_min_value', { min: def.validation.min });
    }
    if (def.validation?.max !== undefined && num > def.validation.max) {
      return t('enterprise.system_config.validation_max_value', { max: def.validation.max });
    }
  }

  if (def.validation?.pattern && str.trim() !== '') {
    const re = new RegExp(def.validation.pattern);
    if (!re.test(str)) return t('enterprise.system_config.validation_invalid_format');
  }

  return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function SystemConfig() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const toast = useToast();

  const [config, setConfig] = useState<Record<string, unknown>>({});
  const [edited, setEdited] = useState<Record<string, unknown>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [saving, setSaving] = useState(false);
  const [resetting, setResetting] = useState(false);
  const [showResetModal, setShowResetModal] = useState(false);

  // Track whether there are unsaved changes
  const hasChanges = JSON.stringify(config) !== JSON.stringify(edited);

  // Translated locale options — built inside the component so t() is available
  const localeOptions = SUPPORTED_LOCALE_CODES.map((code) => ({
    label: t(`system.lang_${code}`),
    value: code,
  }));

  // Config schema with translated strings — rebuilt on each render (translations change with locale)
  const configSchema = buildConfigSchema(t);

  // ── Data loading ──────────────────────────────────────────────────────

  const loadData = useCallback(async () => {
    setLoading(true);
    setLoadError(false);
    try {
      const res = await adminEnterprise.getConfig();
      if (!res.success || !res.data) {
        toast.error(res.error || t('enterprise.failed_to_load_configuration'));
        setLoadError(true);
        return;
      }
      const raw = res.data as unknown as Record<string, unknown>;
      const data = normalizeConfig(raw);
      setConfig(data);
      setEdited({ ...data });
      setErrors({});
    } catch {
      toast.error(t('enterprise.failed_to_load_configuration'));
      setLoadError(true);
    } finally {
      setLoading(false);
    }
  }, [toast, t]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // ── Setting value helpers ─────────────────────────────────────────────

  function getSettingValue(key: string, defaultVal?: unknown): unknown {
    return edited[key] ?? defaultVal ?? '';
  }

  function getUnknownKeys(): string[] {
    return Object.keys(edited).filter((k) => !SCHEMA_KEYS.has(k)).sort();
  }

  // ── Change handler ────────────────────────────────────────────────────

  function handleChange(key: string, value: unknown, def?: ConfigSettingDef) {
    setEdited((prev) => ({ ...prev, [key]: value }));

    if (def) {
      const error = validateSetting(def, value, t);
      setErrors((prev) => {
        const next = { ...prev };
        if (error) {
          next[key] = error;
        } else {
          delete next[key];
        }
        return next;
      });
    }
  }

  // ── Save handler ──────────────────────────────────────────────────────

  async function handleSave() {
    if (loadError) {
      toast.error(t('enterprise.cannot_save_config_not_loaded'));
      return;
    }
    // Validate all schema fields
    const newErrors: Record<string, string> = {};
    for (const group of configSchema) {
      for (const def of group.settings) {
        const error = validateSetting(def, getSettingValue(def.key, def.default), t);
        if (error) newErrors[def.key] = error;
      }
    }
    setErrors(newErrors);
    if (Object.keys(newErrors).length > 0) {
      toast.error(t('enterprise.fix_validation_errors'));
      return;
    }

    setSaving(true);
    try {
      // Only send fields the user actually changed — prevents clobbering values
      // set on other pages or in previous sessions. maintenance_mode is always
      // excluded because it requires the .maintenance file layer (CLI only).
      const payload: Record<string, unknown> = {};
      for (const key of SCHEMA_KEYS) {
        if (key === 'maintenance_mode') continue;
        if (key in edited && JSON.stringify(edited[key]) !== JSON.stringify(config[key])) {
          payload[key] = edited[key];
        }
      }
      if (Object.keys(payload).length === 0) {
        toast.error(t('enterprise.no_changes_to_save'));
        setSaving(false);
        return;
      }
      await adminEnterprise.updateConfig(payload);
      toast.success(t('enterprise.configuration_saved'));
      await loadData();
    } catch {
      toast.error(t('enterprise.failed_to_save_configuration'));
    } finally {
      setSaving(false);
    }
  }

  // ── Reset handler ─────────────────────────────────────────────────────

  async function handleReset() {
    setResetting(true);
    try {
      await adminEnterprise.resetConfig();
      toast.success(t('enterprise.configuration_reset_to_defaults'));
      setShowResetModal(false);
      await loadData();
    } catch {
      toast.error(t('enterprise.failed_to_reset_configuration'));
    } finally {
      setResetting(false);
    }
  }

  // ── Render individual setting ─────────────────────────────────────────

  function renderSetting(def: ConfigSettingDef) {
    const value = getSettingValue(def.key, def.default);
    const error = errors[def.key];

    switch (def.type) {
      case 'boolean': {
        // Some settings are managed by dedicated pages and must not be toggled here
        const READ_ONLY_KEYS = new Set(['maintenance_mode']);
        const isReadOnly = READ_ONLY_KEYS.has(def.key);
        return (
          <div key={def.key} className="flex items-center justify-between gap-4 py-2">
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-1.5">
                <span className={`text-sm font-medium ${isReadOnly ? 'text-default-400' : 'text-foreground'}`}>{def.label}</span>
                <Tooltip content={def.description} delay={300}>
                  <Info size={14} className="text-default-400 shrink-0 cursor-help" />
                </Tooltip>
              </div>
              <p className="text-xs text-default-400 mt-0.5">{def.description}</p>
            </div>
            <Switch
              isSelected={value === true}
              onValueChange={isReadOnly ? undefined : (v) => handleChange(def.key, v, def)}
              isDisabled={isReadOnly}
              aria-label={def.label}
              size="sm"
            />
          </div>
        );
      }

      case 'select':
        return (
          <div key={def.key} className="py-2">
            <div className="flex items-center gap-1.5 mb-1">
              <span className="text-sm font-medium text-foreground">{def.label}</span>
              <Tooltip content={def.description} delay={300}>
                <Info size={14} className="text-default-400 shrink-0 cursor-help" />
              </Tooltip>
            </div>
            <Select
              selectedKeys={value ? [String(value)] : []}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0];
                if (selected !== undefined) handleChange(def.key, String(selected), def);
              }}
              aria-label={def.label}
              variant="bordered"
              size="sm"
              className="max-w-xs"
              isInvalid={!!error}
              errorMessage={error}
            >
              {(def.key === 'locale' ? localeOptions : (def.options ?? [])).map((opt) => (
                <SelectItem key={opt.value}>{opt.label}</SelectItem>
              ))}
            </Select>
            <p className="text-xs text-default-400 mt-1">{def.description}</p>
          </div>
        );

      case 'textarea':
        return (
          <div key={def.key} className="py-2">
            <div className="flex items-center gap-1.5 mb-1">
              <span className="text-sm font-medium text-foreground">{def.label}</span>
              <Tooltip content={def.description} delay={300}>
                <Info size={14} className="text-default-400 shrink-0 cursor-help" />
              </Tooltip>
            </div>
            <Textarea
              value={String(value)}
              onValueChange={(v) => handleChange(def.key, v, def)}
              aria-label={def.label}
              variant="bordered"
              size="sm"
              minRows={2}
              maxRows={5}
              isInvalid={!!error}
              errorMessage={error}
            />
            <p className="text-xs text-default-400 mt-1">{def.description}</p>
          </div>
        );

      case 'number':
        return (
          <div key={def.key} className="py-2">
            <div className="flex items-center gap-1.5 mb-1">
              <span className="text-sm font-medium text-foreground">{def.label}</span>
              <Tooltip content={def.description} delay={300}>
                <Info size={14} className="text-default-400 shrink-0 cursor-help" />
              </Tooltip>
            </div>
            <Input
              type="number"
              value={String(value ?? '')}
              onValueChange={(v) => handleChange(def.key, v === '' ? (def.default ?? 0) : Number(v), def)}
              aria-label={def.label}
              variant="bordered"
              size="sm"
              className="max-w-xs"
              isInvalid={!!error}
              errorMessage={error}
              min={def.validation?.min}
              max={def.validation?.max}
            />
            <p className="text-xs text-default-400 mt-1">{def.description}</p>
          </div>
        );

      default: {
        // text, email, url
        return (
          <div key={def.key} className="py-2">
            <div className="flex items-center gap-1.5 mb-1">
              <span className="text-sm font-medium text-foreground">{def.label}</span>
              <Tooltip content={def.description} delay={300}>
                <Info size={14} className="text-default-400 shrink-0 cursor-help" />
              </Tooltip>
            </div>
            <Input
              type={def.type === 'email' ? 'email' : def.type === 'url' ? 'url' : 'text'}
              value={String(value)}
              onValueChange={(v) => handleChange(def.key, v, def)}
              aria-label={def.label}
              variant="bordered"
              size="sm"
              className="max-w-md"
              isInvalid={!!error}
              errorMessage={error}
            />
            <p className="text-xs text-default-400 mt-1">{def.description}</p>
          </div>
        );
      }
    }
  }

  // ── Loading state ─────────────────────────────────────────────────────

  if (loading) {
    return (
      <div>
        <PageHeader title={t('enterprise.system_config_title')} description={t('enterprise.system_config_desc')} />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  if (loadError) {
    return (
      <div>
        <PageHeader title={t('enterprise.system_config_title')} description={t('enterprise.system_config_desc')} />
        <Card shadow="sm" className="border-danger-200 bg-danger-50">
          <CardBody className="text-center py-12">
            <p className="text-danger font-medium mb-3">{t('enterprise.failed_to_load_configuration')}</p>
            <p className="text-sm text-default-500 mb-4">{t('enterprise.server_error_config_warning')}</p>
            <Button color="primary" variant="flat" onPress={loadData} startContent={<RefreshCw size={16} />}>
              {t('enterprise.retry')}
            </Button>
          </CardBody>
        </Card>
      </div>
    );
  }

  const unknownKeys = getUnknownKeys();

  // ── Render ────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('enterprise.system_config_title')}
        description={t('enterprise.system_config_desc')}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              size="sm"
            >
              {t('enterprise.reload')}
            </Button>
            <Button
              variant="flat"
              color="danger"
              startContent={<RotateCcw size={16} />}
              onPress={() => setShowResetModal(true)}
              size="sm"
            >
              {t('enterprise.reset_to_defaults')}
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
              isDisabled={!hasChanges || Object.keys(errors).length > 0}
              size="sm"
            >
              {t('enterprise.save_changes')}
            </Button>
          </div>
        }
      />

      <div className="space-y-6">
        {configSchema.map((group) => (
          <Card key={group.key} shadow="sm">
            <CardHeader className="flex items-center gap-3 pb-1">
              <div className="flex items-center justify-center w-8 h-8 rounded-lg bg-primary/10 text-primary">
                {group.icon}
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-base font-semibold text-foreground">{group.label}</h3>
                <p className="text-xs text-default-400">{group.description}</p>
              </div>
            </CardHeader>
            <CardBody className="px-5 pb-4 pt-2">
              <div className="divide-y divide-default-100">
                {group.settings.map((def) => renderSetting(def))}
              </div>
            </CardBody>
          </Card>
        ))}

        {/* Unknown / custom keys not in schema */}
        {unknownKeys.length > 0 && (
          <Card shadow="sm">
            <CardHeader className="flex items-center gap-3 pb-1">
              <div className="flex items-center justify-center w-8 h-8 rounded-lg bg-warning/10 text-warning">
                <Settings2 size={18} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-base font-semibold text-foreground">{t('system.advanced_custom_settings')}</h3>
                <p className="text-xs text-default-400">
                  {t('enterprise.system_config.custom_settings_desc')}
                </p>
              </div>
            </CardHeader>
            <CardBody className="px-5 pb-4 pt-2 space-y-3">
              {unknownKeys.map((key) => {
                const rawValue = edited[key];
                const displayValue = typeof rawValue === 'object' && rawValue !== null
                  ? JSON.stringify(rawValue, null, 2)
                  : String(rawValue ?? '');
                return (
                  <div key={key} className="flex items-start gap-3">
                    <div className="w-48 shrink-0 pt-2">
                      <span className="text-sm font-mono font-medium text-default-600">{key}</span>
                    </div>
                    <Input
                      value={displayValue}
                      isReadOnly
                      aria-label={key}
                      variant="bordered"
                      size="sm"
                      className="flex-1"
                      description={t('enterprise.system_config.managed_by_other_pages')}
                    />
                  </div>
                );
              })}
            </CardBody>
          </Card>
        )}
      </div>

      {/* Reset confirmation modal */}
      <Modal isOpen={showResetModal} onOpenChange={setShowResetModal} size="sm">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex flex-col gap-1">
                {t('enterprise.reset_configuration')}
              </ModalHeader>
              <ModalBody>
                <p className="text-sm text-default-600">
                  {t('enterprise.reset_config_confirm')}
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose} size="sm">
                  {t('enterprise.cancel')}
                </Button>
                <Button
                  color="danger"
                  onPress={handleReset}
                  isLoading={resetting}
                  size="sm"
                >
                  {t('enterprise.reset_all')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default SystemConfig;
