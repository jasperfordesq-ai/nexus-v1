// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Settings
 * Global platform configuration and settings management.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Card, CardBody, CardHeader, Input, Switch, Button, Textarea, Spinner, Select, SelectItem, Chip, Tooltip } from '@heroui/react';
import Settings from 'lucide-react/icons/settings';
import Save from 'lucide-react/icons/save';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Scale from 'lucide-react/icons/scale';
import Lock from 'lucide-react/icons/lock';
import Upload from 'lucide-react/icons/upload';
import X from 'lucide-react/icons/x';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useToast, useTenant, useAuth } from '@/contexts';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';
import type { AdminSettingsResponse } from '../../api/types';
import SystemConfig from '../enterprise/SystemConfig';

// Keys handled directly by the cards above — excluded from the embedded
// SystemConfig editor below to avoid duplicate controls.
const DUPLICATE_KEYS = [
  'site_name',
  'site_description',
  'contact_email',
  'contact_phone',
  'footer_text',
  'registration_enabled',
  'require_approval',
  'require_email_verification',
  'maintenance_mode',
];

// Field names match the backend's TENANT_DIRECT_COLUMNS and GENERAL_SETTING_KEYS exactly
interface SettingsForm {
  name: string;               // tenants.name
  description: string;        // tenants.description
  contact_email: string;      // tenants.contact_email
  contact_phone: string;      // tenants.contact_phone
  registration_mode: string;  // general.registration_mode ('open' | 'closed' | 'invite')
  email_verification: boolean; // general.email_verification
  admin_approval: boolean;    // general.admin_approval
  maintenance_mode: boolean;  // general.maintenance_mode
  footer_text: string;        // general.footer_text (charity number, legal name, etc.)
  partner_logo_url: string;   // general.partner_logo_url (shown in footer left slot)
  default_currency: string;   // general.default_currency (ISO 4217 lowercase, e.g. 'eur', 'usd')
}

const CURRENCY_OPTIONS: Array<{ code: string; labelKey: string }> = [
  { code: 'eur', labelKey: 'system.currency_eur' },
  { code: 'usd', labelKey: 'system.currency_usd' },
  { code: 'gbp', labelKey: 'system.currency_gbp' },
  { code: 'cad', labelKey: 'system.currency_cad' },
  { code: 'aud', labelKey: 'system.currency_aud' },
  { code: 'jpy', labelKey: 'system.currency_jpy' },
];

const DEFAULT_SETTINGS: SettingsForm = {
  name: '',
  description: '',
  contact_email: '',
  contact_phone: '',
  registration_mode: 'open',
  email_verification: true,
  admin_approval: false,
  maintenance_mode: false,
  footer_text: '',
  partner_logo_url: '',
  default_currency: 'eur',
};

export function AdminSettings() {
  const { t: tNav } = useTranslation('admin_nav');
  const { t } = useTranslation('admin');
  useAdminPageMeta({ title: tNav('system') });
  const toast = useToast();
  const { tenant, tenantPath } = useTenant();
  const { user } = useAuth();
  const userRecord = user as Record<string, unknown> | null;
  const isGod =
    (user?.role as string) === 'god' ||
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true;

  const [form, setForm] = useState<SettingsForm>(DEFAULT_SETTINGS);
  const [originalForm, setOriginalForm] = useState<SettingsForm>(DEFAULT_SETTINGS);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [uploadingLogo, setUploadingLogo] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const fetchSettings = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminSettings.get();
      const data = res.data as AdminSettingsResponse | undefined;
      if (data) {
        // API returns nested structure: { tenant: {...}, settings: {...} }
        const tenant = data.tenant;
        const settings = data.settings;

        const loaded: SettingsForm = {
          name: (tenant.name as string) ?? '',
          description: (tenant.description as string) ?? '',
          contact_email: (tenant.contact_email as string) ?? '',
          contact_phone: (tenant.contact_phone as string) ?? '',
          registration_mode: (settings.registration_mode as string) ?? 'open',
          email_verification: settings.email_verification === 'true' || settings.email_verification === '1',
          admin_approval: settings.admin_approval === 'true' || settings.admin_approval === '1',
          maintenance_mode: settings.maintenance_mode === 'true' || settings.maintenance_mode === '1',
          footer_text: (settings.footer_text as string) ?? '',
          partner_logo_url: (settings.partner_logo_url as string) ?? '',
          default_currency: (settings.default_currency as string)?.toLowerCase() || 'eur',
        };
        setForm(loaded);
        setOriginalForm(loaded);
      }
    } catch {
      toast.error(t('system.failed_to_load_settings'));
    } finally {
      setLoading(false);
    }
  }, [t, toast])


  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  const handleSave = async () => {
    setSaving(true);
    try {
      // Only send fields that actually changed — prevents clobbering values
      // set on other admin pages (enterprise config, registration policy, etc.)
      const changes: Record<string, unknown> = {};
      if (form.name !== originalForm.name) changes.name = form.name;
      if (form.description !== originalForm.description) changes.description = form.description;
      if (form.contact_email !== originalForm.contact_email) changes.contact_email = form.contact_email;
      if (form.contact_phone !== originalForm.contact_phone) changes.contact_phone = form.contact_phone;
      if (form.registration_mode !== originalForm.registration_mode) changes.registration_mode = form.registration_mode;
      if (form.email_verification !== originalForm.email_verification) changes.email_verification = String(form.email_verification);
      if (form.admin_approval !== originalForm.admin_approval) changes.admin_approval = String(form.admin_approval);
      if (form.footer_text !== originalForm.footer_text) changes.footer_text = form.footer_text;
      if (form.partner_logo_url !== originalForm.partner_logo_url) changes.partner_logo_url = form.partner_logo_url;
      if (form.default_currency !== originalForm.default_currency) changes.default_currency = form.default_currency;

      if (Object.keys(changes).length === 0) {
        toast.error(t('system.no_changes_to_save'));
        setSaving(false);
        return;
      }
      const res = await adminSettings.update(changes);

      if (res.success) {
        toast.success(t('system.settings_saved'));
        // Reload settings to confirm persistence
        fetchSettings();
      } else {
        const error = (res as { error?: string }).error || t('system.save_failed');
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('system.failed_to_save_settings'));
      console.error('Settings save error:', err);
    } finally {
      setSaving(false);
    }
  };

  const handlePartnerLogoUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploadingLogo(true);
    try {
      const res = await adminSettings.uploadPartnerLogo(file);
      if (res.data?.url) {
        setForm(prev => ({ ...prev, partner_logo_url: res.data!.url }));
        setOriginalForm(prev => ({ ...prev, partner_logo_url: res.data!.url }));
        toast.success('Partner logo uploaded');
      } else {
        toast.error('Upload failed');
      }
    } catch {
      toast.error('Upload failed');
    } finally {
      setUploadingLogo(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  if (loading) {
    return (
      <div>
        <PageHeader
          title={t('system.admin_settings_title')}
          description={t('system.admin_settings_desc', { name: tenant?.name || t('system.your_community') })}
        />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('system.admin_settings_title')}
        description={t('system.admin_settings_desc', { name: tenant?.name || t('system.your_community') })}
      />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Settings size={20} /> {t('system.section_general')}
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label={t('system.label_site_name')}
              placeholder={t('system.placeholder_project_nexus')}
              variant="bordered"
              value={form.name}
              onValueChange={(val) => setForm(prev => ({ ...prev, name: val }))}
            />
            <Textarea
              label={t('system.label_site_description')}
              placeholder={t('system.placeholder_community_timebanking_platform')}
              variant="bordered"
              minRows={2}
              value={form.description}
              onValueChange={(val) => setForm(prev => ({ ...prev, description: val }))}
            />
            <Input
              label={t('system.label_support_email')}
              placeholder="support@project-nexus.ie"
              variant="bordered"
              value={form.contact_email}
              onValueChange={(val) => setForm(prev => ({ ...prev, contact_email: val }))}
            />
            <Input
              label={t('system.label_contact_phone')}
              placeholder="+1 555 123 4567"
              variant="bordered"
              value={form.contact_phone}
              onValueChange={(val) => setForm(prev => ({ ...prev, contact_phone: val }))}
            />
            <Select
              label={t('system.label_default_currency')}
              description={t('system.desc_default_currency')}
              variant="bordered"
              selectedKeys={[form.default_currency]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as string | undefined;
                if (val) setForm(prev => ({ ...prev, default_currency: val }));
              }}
            >
              {CURRENCY_OPTIONS.map(opt => (
                <SelectItem key={opt.code}>{t(opt.labelKey)}</SelectItem>
              ))}
            </Select>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Scale size={20} /> {t('system.section_branding_legal')}
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Textarea
              label={t('system.label_footer_legal_text')}
              description={t('system.desc_displayed_in_the_site_footer_and_legal_h')}
              placeholder={t('system.placeholder_footer_legal_text')}
              variant="bordered"
              minRows={2}
              value={form.footer_text}
              onValueChange={(val) => setForm(prev => ({ ...prev, footer_text: val }))}
            />
            {/* Partner Logo Upload */}
            <div className="space-y-2">
              <p className="text-sm font-medium">Partner Logo</p>
              <p className="text-xs text-default-500">
                Shown in the footer left slot. PNG, JPEG, WebP or SVG — max 2 MB.
              </p>
              {form.partner_logo_url && (
                <div className="flex items-start gap-3 p-3 rounded-lg border border-default-200 bg-default-50">
                  <img
                    src={form.partner_logo_url}
                    alt="Partner logo preview"
                    className="h-14 w-auto max-w-[180px] object-contain rounded"
                    onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                  />
                  <Button
                    isIconOnly
                    size="sm"
                    variant="flat"
                    color="danger"
                    aria-label="Remove partner logo"
                    onPress={() => {
                      setForm(prev => ({ ...prev, partner_logo_url: '' }));
                    }}
                  >
                    <X size={14} />
                  </Button>
                </div>
              )}
              <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
                className="hidden"
                onChange={handlePartnerLogoUpload}
              />
              <div className="flex gap-2 flex-wrap">
                <Button
                  variant="flat"
                  color="primary"
                  size="sm"
                  isLoading={uploadingLogo}
                  startContent={!uploadingLogo ? <Upload size={14} /> : undefined}
                  onPress={() => fileInputRef.current?.click()}
                >
                  {form.partner_logo_url ? 'Replace image' : 'Upload image'}
                </Button>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between">
            <h3 className="text-lg font-semibold">{t('system.section_registration_access')}</h3>
            <Link to={tenantPath("/admin/settings/registration-policy")}>
              <Button size="sm" variant="flat" color="primary" startContent={<ShieldCheck size={14} />}>
                {t('system.advanced_policy')}
              </Button>
            </Link>
          </CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('system.label_open_registration')}</p>
                <p className="text-sm text-default-500">{t('system.desc_open_registration')}</p>
              </div>
              <Switch
                isSelected={form.registration_mode === 'open'}
                onValueChange={(val) => setForm(prev => ({ ...prev, registration_mode: val ? 'open' : 'closed' }))}
                aria-label={t('system.label_open_registration')}
              />
            </div>
            <div className="flex items-center justify-between">
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <p className="font-medium">{t('system.require_email_verification')}</p>
                  {!isGod && (
                    <Tooltip content={t('system.super_admin_only_tooltip')}>
                      <Chip size="sm" color="warning" variant="flat" startContent={<Lock size={10} />}>{t('system.super_admin_only')}</Chip>
                    </Tooltip>
                  )}
                </div>
                <p className="text-sm text-default-500">{t('system.desc_email_verification')}</p>
              </div>
              <Switch
                isSelected={form.email_verification}
                onValueChange={(val) => setForm(prev => ({ ...prev, email_verification: val }))}
                isDisabled={!isGod}
                aria-label={t('system.label_email_verification')}
              />
            </div>
            <div className="flex items-center justify-between">
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <p className="font-medium">{t('system.admin_approval_required')}</p>
                  {!isGod && (
                    <Tooltip content={t('system.super_admin_only_tooltip')}>
                      <Chip size="sm" color="warning" variant="flat" startContent={<Lock size={10} />}>{t('system.super_admin_only')}</Chip>
                    </Tooltip>
                  )}
                </div>
                <p className="text-sm text-default-500">{t('system.admin_approval_required_desc')}</p>
              </div>
              <Switch
                isSelected={form.admin_approval}
                onValueChange={(val) => setForm(prev => ({ ...prev, admin_approval: val }))}
                isDisabled={!isGod}
                aria-label={t('system.label_admin_approval')}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-default-400">{t('system.maintenance_mode_read_only')}</p>
                <p className="text-sm text-default-500">{t('system.maintenance_mode_cli_prefix')} <code className="text-xs bg-default-100 px-1 rounded">sudo bash scripts/maintenance.sh on|off</code></p>
              </div>
              <Switch
                isSelected={form.maintenance_mode}
                isDisabled
                aria-label={t('system.label_maintenance_mode')}
              />
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button
            color="primary"
            startContent={!saving ? <Save size={16} /> : undefined}
            onPress={handleSave}
            isLoading={saving}
          >
            {t('system.btn_save_settings')}
          </Button>
        </div>

        {/* Additional platform configuration. Persists via the enterprise
            config endpoint; duplicate keys handled by the cards above are
            hidden via excludeKeys. Rendered without an outer Card to avoid
            double card nesting — SystemConfig provides its own per-group Cards. */}
        <div className="pt-6 mt-2 border-t border-default-200">
          <div className="mb-4">
            <h2 className="text-lg font-semibold text-foreground">{t('system.additional_configuration')}</h2>
            <p className="text-sm text-default-500 mt-1">
              {t('system.additional_configuration_desc')}
            </p>
          </div>
          <SystemConfig
            excludeKeys={DUPLICATE_KEYS}
            onAfterChange={fetchSettings}
          />
        </div>
      </div>
    </div>
  );
}

export default AdminSettings;
