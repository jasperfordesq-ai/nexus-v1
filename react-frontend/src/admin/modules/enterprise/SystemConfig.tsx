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
import Save from 'lucide-react/icons/save';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import Settings2 from 'lucide-react/icons/settings-2';
import UserPlus from 'lucide-react/icons/user-plus';
import Wallet from 'lucide-react/icons/wallet';
import Shield from 'lucide-react/icons/shield';
import Bell from 'lucide-react/icons/bell';
import Gauge from 'lucide-react/icons/gauge';
import Info from 'lucide-react/icons/info';
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
      label: "General",
      description: "General.",
      icon: <Settings2 size={18} />,
      settings: [
        { key: 'site_name', label: "Setting Site Name", description: "Setting Site Name.", type: 'text', default: '' },
        { key: 'site_description', label: "Setting Site Description", description: "Setting Site Description.", type: 'textarea', default: '' },
        { key: 'contact_email', label: "Setting Contact Email", description: "Setting Contact Email.", type: 'email', default: '' },
        { key: 'contact_phone', label: "Setting Contact Phone", description: "Setting Contact Phone.", type: 'text', default: '' },
        { key: 'timezone', label: "Setting Timezone", description: "Setting Timezone.", type: 'text', default: 'UTC' },
        { key: 'footer_text', label: "Setting Footer Text", description: "Setting Footer Text.", type: 'textarea', default: '' },
        {
          key: 'locale', label: "Setting Locale", description: "Setting Locale.", type: 'select', default: 'en',
          options: SUPPORTED_LOCALE_CODES.map((code) => ({ label: code, value: code })),
        },
      ],
    },
    {
      key: 'registration',
      label: "Registration",
      description: "Registration.",
      icon: <UserPlus size={18} />,
      settings: [
        { key: 'registration_enabled', label: "Setting Registration Enabled", description: "Setting Registration Enabled.", type: 'boolean', default: true },
        { key: 'require_approval', label: "Setting Require Approval", description: "Setting Require Approval.", type: 'boolean', default: false },
        { key: 'require_email_verification', label: "Setting Require Email Verification", description: "Setting Require Email Verification.", type: 'boolean', default: true },
        { key: 'maintenance_mode', label: "Setting Maintenance Mode", description: "Setting Maintenance Mode.", type: 'boolean', default: false },
        { key: 'onboarding_enabled', label: "Setting Onboarding Enabled", description: "Setting Onboarding Enabled.", type: 'boolean', default: true },
        { key: 'welcome_message', label: "Setting Welcome Message", description: "Setting Welcome Message.", type: 'textarea', default: '' },
      ],
    },
    {
      key: 'wallet',
      label: "Wallet",
      description: "Wallet.",
      icon: <Wallet size={18} />,
      settings: [
        { key: 'starting_balance', label: "Setting Starting Balance", description: "Setting Starting Balance.", type: 'number', default: 0, validation: { min: 0 } },
        { key: 'max_transaction', label: "Setting Max Transaction", description: "Setting Max Transaction.", type: 'number', default: 0, validation: { min: 0 } },
        { key: 'currency_name', label: "Setting Currency Name", description: "Setting Currency Name.", type: 'text', default: 'Hours' },
        { key: 'currency_symbol', label: "Setting Currency Symbol", description: "Setting Currency Symbol.", type: 'text', default: 'h' },
      ],
    },
    {
      key: 'content',
      label: "Content",
      description: "Content.",
      icon: <Shield size={18} />,
      settings: [
        { key: 'auto_approve_listings', label: "Setting Auto Approve Listings", description: "Setting Auto Approve Listings.", type: 'boolean', default: true },
        { key: 'auto_approve_blog', label: "Setting Auto Approve Blog", description: "Setting Auto Approve Blog.", type: 'boolean', default: false },
        { key: 'max_listing_images', label: "Setting Max Listing Images", description: "Setting Max Listing Images.", type: 'number', default: 5, validation: { min: 1, max: 20 } },
        { key: 'profanity_filter', label: "Setting Profanity Filter", description: "Setting Profanity Filter.", type: 'boolean', default: false },
      ],
    },
    {
      key: 'notifications',
      label: "Notifications",
      description: "Notifications.",
      icon: <Bell size={18} />,
      settings: [
        { key: 'email_notifications_enabled', label: "Setting Email Notifications Enabled", description: "Setting Email Notifications Enabled.", type: 'boolean', default: true },
        { key: 'push_notifications_enabled', label: "Setting Push Notifications Enabled", description: "Setting Push Notifications Enabled.", type: 'boolean', default: true },
        {
          key: 'digest_frequency', label: "Setting Digest Frequency", description: "Setting Digest Frequency.", type: 'select', default: 'weekly',
          options: [
            { label: "Digest Daily", value: 'daily' },
            { label: "Digest Weekly", value: 'weekly' },
            { label: "Digest Monthly", value: 'monthly' },
            { label: "Digest Never", value: 'never' },
          ],
        },
      ],
    },
    {
      key: 'limits',
      label: "Limits",
      description: "Limits.",
      icon: <Gauge size={18} />,
      settings: [
        { key: 'max_listings_per_user', label: "Setting Max Listings Per User", description: "Setting Max Listings Per User.", type: 'number', default: 0, validation: { min: 0 } },
        { key: 'max_groups_per_user', label: "Setting Max Groups Per User", description: "Setting Max Groups Per User.", type: 'number', default: 0, validation: { min: 0 } },
        { key: 'max_file_upload_mb', label: "Setting Max File Upload Mb", description: "Setting Max File Upload Mb.", type: 'number', default: 10, validation: { min: 1, max: 100 } },
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
function normalizeValue(raw: unknown, def: StaticSettingDef): unknown {
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
    return `Validation Required`;
  }

  if (def.type === 'email' && str.trim() !== '' && !EMAIL_RE.test(str)) {
    return "Validation Invalid Email";
  }

  if (def.type === 'url' && str.trim() !== '' && !URL_RE.test(str)) {
    return "Validation Invalid URL";
  }

  if (def.type === 'number' && str.trim() !== '') {
    const num = Number(str);
    if (isNaN(num)) return "Validation Must Be Number";
    if (def.validation?.min !== undefined && num < def.validation.min) {
      return `Validation Min Value`;
    }
    if (def.validation?.max !== undefined && num > def.validation.max) {
      return `Validation Max Value`;
    }
  }

  if (def.validation?.pattern && str.trim() !== '') {
    const re = new RegExp(def.validation.pattern);
    if (!re.test(str)) return "Validation Invalid Format";
  }

  return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function SystemConfig() {
  const { t } = useTranslation('admin');
  usePageTitle("Enterprise");
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
  const configSchema = buildConfigSchema((key) => t(key));

  // ── Data loading ──────────────────────────────────────────────────────

  const loadData = useCallback(async () => {
    setLoading(true);
    setLoadError(false);
    try {
      const res = await adminEnterprise.getConfig();
      if (!res.success || !res.data) {
        toast.error(res.error || "Failed to load configuration");
        setLoadError(true);
        return;
      }
      const raw = res.data as unknown as Record<string, unknown>;
      const data = normalizeConfig(raw);
      setConfig(data);
      setEdited({ ...data });
      setErrors({});
    } catch {
      toast.error("Failed to load configuration");
      setLoadError(true);
    } finally {
      setLoading(false);
    }
  }, [toast]);


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
      const error = validateSetting(def, value, (key, opts) => t(key, opts));
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
      toast.error("Cannot Save Config Not Loaded");
      return;
    }
    // Validate all schema fields
    const newErrors: Record<string, string> = {};
    for (const group of configSchema) {
      for (const def of group.settings) {
        const error = validateSetting(def, getSettingValue(def.key, def.default), (key, opts) => t(key, opts));
        if (error) newErrors[def.key] = error;
      }
    }
    setErrors(newErrors);
    if (Object.keys(newErrors).length > 0) {
      toast.error("Fix Validation Errors");
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
        toast.error("No changes to save found");
        setSaving(false);
        return;
      }
      await adminEnterprise.updateConfig(payload);
      toast.success("Configuration Saved");
      await loadData();
    } catch {
      toast.error("Failed to save configuration");
    } finally {
      setSaving(false);
    }
  }

  // ── Reset handler ─────────────────────────────────────────────────────

  async function handleReset() {
    setResetting(true);
    try {
      await adminEnterprise.resetConfig();
      toast.success("Configuration Reset to Defaults");
      setShowResetModal(false);
      await loadData();
    } catch {
      toast.error("Failed to reset configuration");
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
        <PageHeader title={"System Config"} description={"View and manage system configuration keys"} />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  if (loadError) {
    return (
      <div>
        <PageHeader title={"System Config"} description={"View and manage system configuration keys"} />
        <Card shadow="sm" className="border-danger-200 bg-danger-50">
          <CardBody className="text-center py-12">
            <p className="text-danger font-medium mb-3">{"Failed to load configuration"}</p>
            <p className="text-sm text-default-500 mb-4">{"Server Error Config Warning"}</p>
            <Button color="primary" variant="flat" onPress={loadData} startContent={<RefreshCw size={16} />}>
              {"Retry"}
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
        title={"System Config"}
        description={"View and manage system configuration keys"}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              size="sm"
            >
              {"Reload"}
            </Button>
            <Button
              variant="flat"
              color="danger"
              startContent={<RotateCcw size={16} />}
              onPress={() => setShowResetModal(true)}
              size="sm"
            >
              {"Reset to Defaults"}
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
              isDisabled={!hasChanges || Object.keys(errors).length > 0}
              size="sm"
            >
              {"Save Changes"}
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
                <h3 className="text-base font-semibold text-foreground">{"Advanced Custom Settings"}</h3>
                <p className="text-xs text-default-400">
                  {"Custom Settings."}
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
                      description={"Managed by Other Pages"}
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
                {"Reset Configuration"}
              </ModalHeader>
              <ModalBody>
                <p className="text-sm text-default-600">
                  {"Reset Config Confirm"}
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose} size="sm">
                  {"Cancel"}
                </Button>
                <Button
                  color="danger"
                  onPress={handleReset}
                  isLoading={resetting}
                  size="sm"
                >
                  {"Reset All"}
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
