// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Settings
 * Global platform configuration and settings management.
 */

import { useState, useEffect, useCallback } from 'react';
import { Card, CardBody, CardHeader, Input, Switch, Button, Textarea, Spinner } from '@heroui/react';
import { Settings, Save, ShieldCheck, Scale } from 'lucide-react';
import { Link } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';
import type { AdminSettingsResponse } from '../../api/types';

import { useTranslation } from 'react-i18next';
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
}

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
};

export function AdminSettings() {
  const { t } = useTranslation('admin');
  usePageTitle(t('system.page_title'));
  const toast = useToast();
  const { tenant, tenantPath } = useTenant();

  const [form, setForm] = useState<SettingsForm>(DEFAULT_SETTINGS);
  const [originalForm, setOriginalForm] = useState<SettingsForm>(DEFAULT_SETTINGS);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

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
        };
        setForm(loaded);
        setOriginalForm(loaded);
      }
    } catch {
      toast.error(t('system.failed_to_load_settings'));
    } finally {
      setLoading(false);
    }
  }, [toast]);

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
        const error = (res as { error?: string }).error || 'Save failed';
        toast.error(error);
      }
    } catch (err) {
      toast.error(t('system.failed_to_save_settings'));
      console.error('Settings save error:', err);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div>
        <PageHeader title={t('system.admin_settings_title')} description={`Settings for ${tenant?.name || 'your community'}`} />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={t('system.admin_settings_title')} description={`Settings for ${tenant?.name || 'your community'}`} />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Settings size={20} /> General
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label={t('system.label_site_name')}
              placeholder={t('system.placeholder_project_n_e_x_u_s')}
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
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Scale size={20} /> Branding &amp; Legal
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Textarea
              label={t('system.label_footer_legal_text')}
              description={t('system.desc_displayed_in_the_site_footer_and_legal_h')}
              placeholder="e.g. hOUR Timebank CLG. Registered Charity No. 20204862. Company No. 705275."
              variant="bordered"
              minRows={2}
              value={form.footer_text}
              onValueChange={(val) => setForm(prev => ({ ...prev, footer_text: val }))}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between">
            <h3 className="text-lg font-semibold">Registration & Access</h3>
            <Link to={tenantPath("/admin/settings/registration-policy")}>
              <Button size="sm" variant="flat" color="primary" startContent={<ShieldCheck size={14} />}>
                Advanced Policy
              </Button>
            </Link>
          </CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Open Registration</p>
                <p className="text-sm text-default-500">Allow new users to register without an invitation</p>
              </div>
              <Switch
                isSelected={form.registration_mode === 'open'}
                onValueChange={(val) => setForm(prev => ({ ...prev, registration_mode: val ? 'open' : 'closed' }))}
                aria-label={t('system.label_open_registration')}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Require Email Verification</p>
                <p className="text-sm text-default-500">Users must verify their email before accessing the platform</p>
              </div>
              <Switch
                isSelected={form.email_verification}
                onValueChange={(val) => setForm(prev => ({ ...prev, email_verification: val }))}
                aria-label={t('system.label_email_verification')}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Admin Approval Required</p>
                <p className="text-sm text-default-500">New registrations require admin approval</p>
              </div>
              <Switch
                isSelected={form.admin_approval}
                onValueChange={(val) => setForm(prev => ({ ...prev, admin_approval: val }))}
                aria-label={t('system.label_admin_approval')}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-default-400">Maintenance Mode (read-only)</p>
                <p className="text-sm text-default-500">Use CLI: <code className="text-xs bg-default-100 px-1 rounded">sudo bash scripts/maintenance.sh on|off</code></p>
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
            Save Settings
          </Button>
        </div>
      </div>
    </div>
  );
}

export default AdminSettings;
