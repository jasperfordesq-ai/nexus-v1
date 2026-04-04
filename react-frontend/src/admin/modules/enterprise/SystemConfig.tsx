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

const CONFIG_SCHEMA: ConfigGroup[] = [
  {
    key: 'general',
    label: 'General',
    description: 'Basic platform settings',
    icon: <Settings2 size={18} />,
    settings: [
      { key: 'site_name', label: 'Site Name', description: 'The name displayed in the header and page titles', type: 'text', default: '' },
      { key: 'site_description', label: 'Site Description', description: 'Brief description shown in SEO metadata', type: 'textarea', default: '' },
      { key: 'contact_email', label: 'Contact Email', description: 'Main contact email for the platform', type: 'email', default: '' },
      { key: 'contact_phone', label: 'Contact Phone', description: 'Main contact phone number', type: 'text', default: '' },
      { key: 'timezone', label: 'Timezone', description: 'Default timezone for date/time display', type: 'text', default: 'UTC' },
      { key: 'footer_text', label: 'Footer / Legal Text', description: 'Displayed in the site footer (e.g., charity number, company registration)', type: 'textarea', default: '' },
      {
        key: 'locale', label: 'Default Locale', description: 'Default language for the platform', type: 'select', default: 'en',
        options: [
          { label: 'English', value: 'en' }, { label: 'Irish', value: 'ga' }, { label: 'German', value: 'de' },
          { label: 'French', value: 'fr' }, { label: 'Italian', value: 'it' }, { label: 'Portuguese', value: 'pt' },
          { label: 'Spanish', value: 'es' }, { label: 'Dutch', value: 'nl' }, { label: 'Polish', value: 'pl' },
          { label: 'Japanese', value: 'ja' }, { label: 'Arabic', value: 'ar' },
        ],
      },
    ],
  },
  {
    key: 'registration',
    label: 'Registration & Onboarding',
    description: 'Control how new members join',
    icon: <UserPlus size={18} />,
    settings: [
      { key: 'registration_enabled', label: 'Open Registration (read-only)', description: 'Managed via Settings → Registration Policy — toggle disabled to prevent overwriting granular policy modes', type: 'boolean', default: true },
      { key: 'require_approval', label: 'Require Admin Approval (read-only)', description: 'Managed via Settings → Registration Policy', type: 'boolean', default: false },
      { key: 'require_email_verification', label: 'Require Email Verification', description: 'Members must verify email before accessing the platform', type: 'boolean', default: true },
      { key: 'maintenance_mode', label: 'Maintenance Mode (read-only)', description: 'Managed via CLI: sudo bash scripts/maintenance.sh on|off — UI toggle disabled because maintenance mode requires both file and database layers', type: 'boolean', default: false },
      { key: 'onboarding_enabled', label: 'Onboarding Flow', description: 'Show guided onboarding for new members', type: 'boolean', default: true },
      { key: 'welcome_message', label: 'Welcome Message', description: 'Message shown to new members after registration', type: 'textarea', default: '' },
    ],
  },
  {
    key: 'wallet',
    label: 'Time Credits & Wallet',
    description: 'Configure timebanking economics',
    icon: <Wallet size={18} />,
    settings: [
      { key: 'starting_balance', label: 'Starting Balance', description: 'Time credits given to new members on signup', type: 'number', default: 0, validation: { min: 0 } },
      { key: 'max_transaction', label: 'Max Transaction', description: 'Maximum hours per single transaction (0 = unlimited)', type: 'number', default: 0, validation: { min: 0 } },
      { key: 'currency_name', label: 'Currency Name', description: 'What to call your time credits (e.g., "Hours", "Credits")', type: 'text', default: 'Hours' },
      { key: 'currency_symbol', label: 'Currency Symbol', description: 'Symbol for the currency (e.g., "h", "tc")', type: 'text', default: 'h' },
    ],
  },
  {
    key: 'content',
    label: 'Content & Moderation',
    description: 'Content policies and moderation settings',
    icon: <Shield size={18} />,
    settings: [
      { key: 'auto_approve_listings', label: 'Auto-approve Listings', description: 'New listings go live immediately without admin review', type: 'boolean', default: true },
      { key: 'auto_approve_blog', label: 'Auto-approve Blog Posts', description: 'Blog posts publish immediately', type: 'boolean', default: false },
      { key: 'max_listing_images', label: 'Max Listing Images', description: 'Maximum images per listing', type: 'number', default: 5, validation: { min: 1, max: 20 } },
      { key: 'profanity_filter', label: 'Profanity Filter', description: 'Automatically filter offensive language in posts and messages', type: 'boolean', default: false },
    ],
  },
  {
    key: 'notifications',
    label: 'Notifications',
    description: 'Notification and email preferences',
    icon: <Bell size={18} />,
    settings: [
      { key: 'email_notifications_enabled', label: 'Email Notifications', description: 'Send email notifications for platform activity', type: 'boolean', default: true },
      { key: 'push_notifications_enabled', label: 'Push Notifications', description: 'Enable browser/mobile push notifications', type: 'boolean', default: true },
      {
        key: 'digest_frequency', label: 'Digest Frequency', description: 'How often to send activity digest emails', type: 'select', default: 'weekly',
        options: [
          { label: 'Daily', value: 'daily' }, { label: 'Weekly', value: 'weekly' },
          { label: 'Monthly', value: 'monthly' }, { label: 'Never', value: 'never' },
        ],
      },
    ],
  },
  {
    key: 'limits',
    label: 'Limits & Quotas',
    description: 'Platform usage limits',
    icon: <Gauge size={18} />,
    settings: [
      { key: 'max_listings_per_user', label: 'Max Listings per User', description: 'Maximum active listings a member can have (0 = unlimited)', type: 'number', default: 0, validation: { min: 0 } },
      { key: 'max_groups_per_user', label: 'Max Groups per User', description: 'Maximum groups a member can create (0 = unlimited)', type: 'number', default: 0, validation: { min: 0 } },
      { key: 'max_file_upload_mb', label: 'Max File Upload (MB)', description: 'Maximum file size for uploads in megabytes', type: 'number', default: 10, validation: { min: 1, max: 100 } },
    ],
  },
];

/** All known schema keys for fast lookup */
const SCHEMA_KEYS = new Set(CONFIG_SCHEMA.flatMap((g) => g.settings.map((s) => s.key)));

/** Schema definitions keyed by setting key for fast lookup */
const SCHEMA_MAP = new Map<string, ConfigSettingDef>(
  CONFIG_SCHEMA.flatMap((g) => g.settings.map((s) => [s.key, s] as const)),
);

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

function validateSetting(def: ConfigSettingDef, value: unknown): string | null {
  const str = String(value ?? '');

  if (def.validation?.required && str.trim() === '') {
    return `${def.label} is required`;
  }

  if (def.type === 'email' && str.trim() !== '' && !EMAIL_RE.test(str)) {
    return 'Enter a valid email address';
  }

  if (def.type === 'url' && str.trim() !== '' && !URL_RE.test(str)) {
    return 'Enter a valid URL (https://...)';
  }

  if (def.type === 'number' && str.trim() !== '') {
    const num = Number(str);
    if (isNaN(num)) return 'Must be a number';
    if (def.validation?.min !== undefined && num < def.validation.min) {
      return `Minimum value is ${def.validation.min}`;
    }
    if (def.validation?.max !== undefined && num > def.validation.max) {
      return `Maximum value is ${def.validation.max}`;
    }
  }

  if (def.validation?.pattern && str.trim() !== '') {
    const re = new RegExp(def.validation.pattern);
    if (!re.test(str)) return `Invalid format`;
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
      toast.error('Cannot save — configuration failed to load. Please reload the page.');
      return;
    }
    // Validate all schema fields
    const newErrors: Record<string, string> = {};
    for (const group of CONFIG_SCHEMA) {
      for (const def of group.settings) {
        const error = validateSetting(def, getSettingValue(def.key, def.default));
        if (error) newErrors[def.key] = error;
      }
    }
    setErrors(newErrors);
    if (Object.keys(newErrors).length > 0) {
      toast.error('Please fix validation errors before saving');
      return;
    }

    setSaving(true);
    try {
      // Only send schema-defined keys — exclude unknown/JSON blob keys (modules, federation, etc.)
      // Excluded keys are managed by dedicated pages and must not be overwritten here:
      // - maintenance_mode: requires file + DB layers (use CLI)
      // - registration_enabled/require_approval: managed by Registration Policy page
      const SAVE_EXCLUDED = new Set(['maintenance_mode', 'registration_enabled', 'require_approval']);
      const payload: Record<string, unknown> = {};
      for (const key of SCHEMA_KEYS) {
        if (SAVE_EXCLUDED.has(key)) continue;
        if (key in edited) payload[key] = edited[key];
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
      toast.success('Configuration reset to defaults');
      setShowResetModal(false);
      await loadData();
    } catch {
      toast.error('Failed to reset configuration');
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
        const READ_ONLY_KEYS = new Set(['maintenance_mode', 'registration_enabled', 'require_approval']);
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
              {(def.options ?? []).map((opt) => (
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
            <p className="text-danger font-medium mb-3">Failed to load configuration</p>
            <p className="text-sm text-default-500 mb-4">The server returned an error. Your settings are not shown to prevent accidental overwrites.</p>
            <Button color="primary" variant="flat" onPress={loadData} startContent={<RefreshCw size={16} />}>
              Retry
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
              Reset to Defaults
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
        {CONFIG_SCHEMA.map((group) => (
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
                <h3 className="text-base font-semibold text-foreground">Advanced / Custom Settings</h3>
                <p className="text-xs text-default-400">
                  Configuration keys not defined in the standard schema. These are preserved as raw key-value pairs.
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
                      description="Managed by other configuration pages"
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
                Reset Configuration
              </ModalHeader>
              <ModalBody>
                <p className="text-sm text-default-600">
                  This will remove all configuration values and reset them to platform defaults.
                  This action cannot be undone.
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose} size="sm">
                  Cancel
                </Button>
                <Button
                  color="danger"
                  onPress={handleReset}
                  isLoading={resetting}
                  size="sm"
                >
                  Reset All
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
