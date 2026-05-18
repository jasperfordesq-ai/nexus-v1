// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * System Configuration
 * Grouped settings editor with descriptions, validation, and reset-to-defaults.
 * Replaces the legacy flat key-value editor.
 */

import { useState, useEffect, useCallback, useMemo, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
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
import ArrowRight from 'lucide-react/icons/arrow-right';
import { useTenant } from '@/contexts';
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
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
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
  /**
   * Optional inline "Configure" link rendered next to the control. Use for
   * master-switch settings whose full configuration lives on another page.
   */
  manage?: { href: string; label: string };
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

/** Build the config schema. Labels and descriptions are English-only (admin panel is not translated). */
function buildConfigSchema(): ConfigGroup[] {
  return [
    {
      key: 'general',
      label: "General",
      description: "Site identity, localization, and footer text.",
      icon: <Settings2 size={18} />,
      settings: [
        { key: 'site_name', label: "Site name", description: "Public name shown in the header, browser tab, and emails.", type: 'text', default: '' },
        { key: 'site_description', label: "Site description", description: "Short tagline used in metadata and shared links.", type: 'textarea', default: '' },
        { key: 'contact_email', label: "Contact email", description: "Address members use to reach support.", type: 'email', default: '' },
        { key: 'contact_phone', label: "Contact phone", description: "Public phone number for support enquiries.", type: 'text', default: '' },
        { key: 'timezone', label: "Timezone", description: "IANA timezone identifier (e.g. Europe/Dublin) used for scheduling and timestamps.", type: 'text', default: 'UTC' },
        { key: 'footer_text', label: "Footer legal text", description: "Charity number, company registration, or other legal text displayed in the footer.", type: 'textarea', default: '' },
        {
          key: 'locale', label: "Default locale", description: "Language used for new members until they pick their own.", type: 'select', default: 'en',
          options: SUPPORTED_LOCALE_CODES.map((code) => ({ label: code, value: code })),
        },
      ],
    },
    {
      key: 'registration',
      label: "Registration & onboarding",
      description: "Who can sign up, what they verify, and how they're welcomed.",
      icon: <UserPlus size={18} />,
      settings: [
        { key: 'registration_enabled', label: "Registration enabled", description: "Turn off to close sign-ups entirely.", type: 'boolean', default: true },
        { key: 'require_approval', label: "Admin approval required", description: "New accounts must be approved by an admin before they can sign in.", type: 'boolean', default: false },
        { key: 'require_email_verification', label: "Require email verification", description: "Members must verify their email address before accessing the platform.", type: 'boolean', default: true },
        { key: 'maintenance_mode', label: "Maintenance mode", description: "Read-only. Toggle via scripts/maintenance.sh on the server.", type: 'boolean', default: false },
        {
          key: 'onboarding_enabled',
          label: "Show onboarding flow",
          description: "Walk new members through profile and skills setup after sign-up.",
          type: 'boolean',
          default: true,
          manage: { href: '/admin/onboarding-settings', label: "Configure steps" },
        },
        { key: 'welcome_message', label: "Welcome message", description: "Shown to new members on their first sign-in.", type: 'textarea', default: '' },
      ],
    },
    {
      key: 'wallet',
      label: "Wallet & credits",
      description: "Time-credit balance, transfer caps, and how the currency is named.",
      icon: <Wallet size={18} />,
      settings: [
        { key: 'starting_balance', label: "Starting balance", description: "Time credits granted to new members on sign-up. Set to 0 to disable.", type: 'number', default: 0, validation: { min: 0 } },
        { key: 'max_transaction', label: "Maximum transfer", description: "Largest single transfer between members. Set to 0 for no limit.", type: 'number', default: 0, validation: { min: 0 } },
        { key: 'currency_name', label: "Currency name", description: "Display name for the time-bank currency (e.g. Hours, Credits, TimeBucks).", type: 'text', default: 'Hours' },
        { key: 'currency_symbol', label: "Currency symbol", description: "Short symbol shown next to balances (e.g. h, ⌛, TC).", type: 'text', default: 'h' },
      ],
    },
    {
      key: 'content',
      label: "Content & moderation",
      description: "Approval rules and content filters for listings and posts.",
      icon: <Shield size={18} />,
      settings: [
        { key: 'auto_approve_listings', label: "Auto-approve listings", description: "Publish new listings immediately instead of holding them for moderator review.", type: 'boolean', default: true },
        { key: 'auto_approve_blog', label: "Auto-approve blog posts", description: "Publish new blog posts immediately instead of holding them for moderator review.", type: 'boolean', default: false },
        { key: 'max_listing_images', label: "Max images per listing", description: "Upper limit on photos attached to a single listing.", type: 'number', default: 5, validation: { min: 1, max: 20 } },
        { key: 'profanity_filter', label: "Profanity filter", description: "Block submissions containing words on the platform's profanity list.", type: 'boolean', default: false },
      ],
    },
    {
      key: 'notifications',
      label: "Notifications",
      description: "Default delivery channels for new accounts and digest cadence.",
      icon: <Bell size={18} />,
      settings: [
        { key: 'email_notifications_enabled', label: "Email notifications enabled", description: "Default for new accounts. Members can override in their own settings.", type: 'boolean', default: true },
        { key: 'push_notifications_enabled', label: "Push notifications enabled", description: "Default for new accounts. Members can override in their own settings.", type: 'boolean', default: true },
        {
          key: 'digest_frequency', label: "Digest frequency", description: "How often the activity digest email is sent.", type: 'select', default: 'monthly',
          options: [
            { label: "Daily", value: 'daily' },
            { label: "Monthly", value: 'monthly' },
            { label: "Monthly", value: 'monthly' },
            { label: "Never", value: 'never' },
          ],
        },
      ],
    },
    {
      key: 'limits',
      label: "Limits",
      description: "Per-member caps and upload size.",
      icon: <Gauge size={18} />,
      settings: [
        { key: 'max_listings_per_user', label: "Max listings per member", description: "Most listings a single member can have active at once. Set to 0 for no limit.", type: 'number', default: 0, validation: { min: 0 } },
        { key: 'max_groups_per_user', label: "Max groups per member", description: "Most groups a single member can join. Set to 0 for no limit.", type: 'number', default: 0, validation: { min: 0 } },
        { key: 'max_file_upload_mb', label: "Max upload size (MB)", description: "Per-file upload size limit. Larger files are rejected at the server.", type: 'number', default: 10, validation: { min: 1, max: 100 } },
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
  { key: 'digest_frequency', type: 'select', default: 'monthly' },
  { key: 'max_listings_per_user', type: 'number', default: 0, validation: { min: 0 } },
  { key: 'max_groups_per_user', type: 'number', default: 0, validation: { min: 0 } },
  { key: 'max_file_upload_mb', type: 'number', default: 10, validation: { min: 1, max: 100 } },
];

/** All known schema keys — static list for normalization/validation (independent of translations) */
const SCHEMA_KEYS = new Set(STATIC_SETTINGS.map((s) => s.key));

/**
 * Curated list of related admin pages. Always visible — these are peer
 * configuration pages that admins commonly reach for from the Settings page.
 */
interface RelatedAdminPage {
  label: string;
  description: string;
  /** Path relative to the tenant root (e.g. "/admin/federation"). Pass through tenantPath() at render. */
  href: string;
  destLabel: string;
}

const RELATED_ADMIN_PAGES: RelatedAdminPage[] = [
  {
    label: "Onboarding settings",
    description: "Welcome steps, required profile fields, and skills setup for new members.",
    href: '/admin/onboarding-settings',
    destLabel: "Onboarding",
  },
  {
    label: "Module configuration",
    description: "Toggle features on/off and tune how each module behaves — the single home for tenant feature configuration.",
    href: '/admin/module-configuration',
    destLabel: "Module Configuration",
  },
  {
    label: "Operations",
    description: "Cache stats and background job controls.",
    href: '/admin/operations',
    destLabel: "Operations",
  },
  {
    label: "Translation settings",
    description: "Default language and the languages members can choose from.",
    href: '/admin/translation-config',
    destLabel: "Translation Settings",
  },
  {
    label: "Image settings",
    description: "Default formats, sizes, and WebP conversion behaviour.",
    href: '/admin/image-settings',
    destLabel: "Image Settings",
  },
  {
    label: "Registration policy",
    description: "Granular sign-up rules — invite codes, allowlisted domains, captcha.",
    href: '/admin/settings/registration-policy',
    destLabel: "Registration Policy",
  },
  {
    label: "Federation",
    description: "Inbound partnerships, auto-approval, shared categories, and partnership limits.",
    href: '/admin/federation',
    destLabel: "Federation",
  },
  {
    label: "Broker controls",
    description: "Exchange workflow, messaging review, and risk tagging.",
    href: '/broker',
    destLabel: "Broker Panel",
  },
  {
    label: "Safeguarding options",
    description: "Reporting flows, escalation paths, and safeguarding policies.",
    href: '/admin/safeguarding-options',
    destLabel: "Safeguarding",
  },
];

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

function validateSetting(def: ConfigSettingDef, value: unknown): string | null {
  const str = String(value ?? '');

  if (def.validation?.required && str.trim() === '') {
    return "Required";
  }

  if (def.type === 'email' && str.trim() !== '' && !EMAIL_RE.test(str)) {
    return "Enter a valid email address";
  }

  if (def.type === 'url' && str.trim() !== '' && !URL_RE.test(str)) {
    return "Enter a valid URL (must start with http:// or https://)";
  }

  if (def.type === 'number' && str.trim() !== '') {
    const num = Number(str);
    if (isNaN(num)) return "Must be a number";
    if (def.validation?.min !== undefined && num < def.validation.min) {
      return `Must be ${def.validation.min} or greater`;
    }
    if (def.validation?.max !== undefined && num > def.validation.max) {
      return `Must be ${def.validation.max} or less`;
    }
  }

  if (def.validation?.pattern && str.trim() !== '') {
    const re = new RegExp(def.validation.pattern);
    if (!re.test(str)) return "Invalid format";
  }

  return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

interface SystemConfigProps {
  /** Setting keys to hide from the editor (used to suppress duplicates with the parent's own form). */
  excludeKeys?: string[];
  /**
   * Called after a successful save or reset. The parent should refetch its own
   * data — Reset in particular clears keys that may be owned by the parent.
   */
  onAfterChange?: () => void;
}

/**
 * Embedded-only editor for platform configuration. Rendered inside the Admin
 * Settings page; never routed standalone (the legacy /admin/enterprise/config
 * route was retired and its sidebar entries removed).
 */
export function SystemConfig({ excludeKeys, onAfterChange }: SystemConfigProps = {}) {
  const { t } = useTranslation('admin');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const excludeSet = useMemo(() => new Set(excludeKeys ?? []), [excludeKeys]);

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

  // Config schema (English-only — admin panel is not translated)
  const configSchema = buildConfigSchema()
    .map((g) => ({ ...g, settings: g.settings.filter((s) => !excludeSet.has(s.key)) }))
    .filter((g) => g.settings.length > 0);

  // ── Data loading ──────────────────────────────────────────────────────

  const loadData = useCallback(async () => {
    setLoading(true);
    setLoadError(false);
    try {
      const res = await adminEnterprise.getConfig();
      if (!res.success || !res.data) {
        toast.error(res.error || "Failed to load settings");
        setLoadError(true);
        return;
      }
      const raw = res.data as unknown as Record<string, unknown>;
      const data = normalizeConfig(raw);
      setConfig(data);
      setEdited({ ...data });
      setErrors({});
    } catch {
      toast.error("Failed to load settings");
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


  // ── Change handler ────────────────────────────────────────────────────

  function handleChange(key: string, value: unknown, def?: ConfigSettingDef) {
    setEdited((prev) => ({ ...prev, [key]: value }));

    if (def) {
      const error = validateSetting(def, value);
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
      toast.error("Settings haven't loaded yet — reload before saving.");
      return;
    }
    // Validate all schema fields
    const newErrors: Record<string, string> = {};
    for (const group of configSchema) {
      for (const def of group.settings) {
        const error = validateSetting(def, getSettingValue(def.key, def.default));
        if (error) newErrors[def.key] = error;
      }
    }
    setErrors(newErrors);
    if (Object.keys(newErrors).length > 0) {
      toast.error("Fix the highlighted errors before saving.");
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
        toast.error("No changes to save.");
        setSaving(false);
        return;
      }
      await adminEnterprise.updateConfig(payload);
      toast.success("Settings saved.");
      await loadData();
      onAfterChange?.();
    } catch {
      toast.error("Failed to save settings.");
    } finally {
      setSaving(false);
    }
  }

  // ── Reset handler ─────────────────────────────────────────────────────

  async function handleReset() {
    setResetting(true);
    try {
      await adminEnterprise.resetConfig();
      toast.success("Configuration reset to defaults.");
      setShowResetModal(false);
      await loadData();
      onAfterChange?.();
    } catch {
      toast.error("Failed to reset configuration.");
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
            <div className="flex items-center gap-2 shrink-0">
              {def.manage && (
                <Button
                  as={Link}
                  to={tenantPath(def.manage.href)}
                  size="sm"
                  variant="flat"
                  color="primary"
                  endContent={<ArrowRight size={14} />}
                >
                  {def.manage.label}
                </Button>
              )}
              <Switch
                isSelected={value === true}
                onValueChange={isReadOnly ? undefined : (v) => handleChange(def.key, v, def)}
                isDisabled={isReadOnly}
                aria-label={def.label}
                size="sm"
              />
            </div>
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
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  if (loadError) {
    return (
      <Card shadow="sm" className="border-danger-200 bg-danger-50">
        <CardBody className="text-center py-12">
          <p className="text-danger font-medium mb-3">{"Failed to load settings"}</p>
          <p className="text-sm text-default-500 mb-4">{"We couldn't reach the configuration service. Check your connection and try again."}</p>
          <Button color="primary" variant="flat" onPress={loadData} startContent={<RefreshCw size={16} />}>
            {"Retry"}
          </Button>
        </CardBody>
      </Card>
    );
  }

  // ── Render ────────────────────────────────────────────────────────────

  return (
    <div>
      <div className="flex justify-end mb-3">
        <div className="flex gap-2 flex-wrap">
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
      </div>

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

        {/* Related admin pages — peer configuration surfaces that don't fit
            the schema above. Always visible since these admin pages always
            exist on the platform. */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-3 pb-1">
            <div className="flex items-center justify-center w-8 h-8 rounded-lg bg-primary/10 text-primary">
              <Settings2 size={18} />
            </div>
            <div className="flex-1 min-w-0">
              <h3 className="text-base font-semibold text-foreground">{"Related admin pages"}</h3>
              <p className="text-xs text-default-400">
                {"Configuration that lives on its own dedicated page."}
              </p>
            </div>
          </CardHeader>
          <CardBody className="px-5 pb-4 pt-2">
            <div className="divide-y divide-default-100">
              {RELATED_ADMIN_PAGES.map((entry) => (
                <div key={entry.href} className="flex items-center justify-between gap-4 py-3">
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-foreground">{entry.label}</p>
                    <p className="text-xs text-default-500 mt-0.5">{entry.description}</p>
                  </div>
                  <Button
                    as={Link}
                    to={tenantPath(entry.href)}
                    size="sm"
                    variant="flat"
                    color="primary"
                    endContent={<ArrowRight size={14} />}
                  >
                    {`Open ${entry.destLabel}`}
                  </Button>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Reset confirmation modal */}
      <Modal isOpen={showResetModal} onOpenChange={setShowResetModal} size="md">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex flex-col gap-1">
                {"Reset configuration to defaults?"}
              </ModalHeader>
              <ModalBody>
                <p className="text-sm text-default-700">
                  {"This restores all platform configuration to its default values."}
                </p>
                <p className="text-sm text-default-600">
                  {"Will be cleared: registration mode, email verification, admin approval, footer text, default locale, timezone, welcome message, wallet, content, notifications, and limits settings."}
                </p>
                <p className="text-sm text-default-600">
                  {"Will be preserved: site name, description, contact details, default currency, and maintenance mode (CLI-managed)."}
                </p>
                <p className="text-sm font-medium text-danger">
                  {"This action cannot be undone."}
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
