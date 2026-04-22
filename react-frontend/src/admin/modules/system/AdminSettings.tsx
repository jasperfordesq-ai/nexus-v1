// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Settings
 * Global platform configuration and settings management.
 */

import { useState, useEffect, useCallback } from 'react';
import { Card, CardBody, CardHeader, Input, Switch, Button, Textarea, Spinner, Select, SelectItem } from '@heroui/react';
import Settings from 'lucide-react/icons/settings';
import Save from 'lucide-react/icons/save';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Scale from 'lucide-react/icons/scale';
import { Link } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';
import type { AdminSettingsResponse } from '../../api/types';

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
  default_currency: string;   // general.default_currency (ISO 4217 lowercase, e.g. 'eur', 'usd')
}

const CURRENCY_OPTIONS: Array<{ code: string; label: string }> = [
  { code: 'eur', label: 'EUR — Euro' },
  { code: 'usd', label: 'USD — US Dollar' },
  { code: 'gbp', label: 'GBP — British Pound' },
  { code: 'cad', label: 'CAD — Canadian Dollar' },
  { code: 'aud', label: 'AUD — Australian Dollar' },
  { code: 'jpy', label: 'JPY — Japanese Yen' },
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
  default_currency: 'eur',
};

export function AdminSettings() {
  usePageTitle("System");
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
          default_currency: (settings.default_currency as string)?.toLowerCase() || 'eur',
        };
        setForm(loaded);
        setOriginalForm(loaded);
      }
    } catch {
      toast.error("Failed to load settings");
    } finally {
      setLoading(false);
    }
  }, [toast])


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
      if (form.default_currency !== originalForm.default_currency) changes.default_currency = form.default_currency;

      if (Object.keys(changes).length === 0) {
        toast.error("No changes to save");
        setSaving(false);
        return;
      }
      const res = await adminSettings.update(changes);

      if (res.success) {
        toast.success("Settings Saved");
        // Reload settings to confirm persistence
        fetchSettings();
      } else {
        const error = (res as { error?: string }).error || 'Save failed';
        toast.error(error);
      }
    } catch (err) {
      toast.error("Failed to save settings");
      console.error('Settings save error:', err);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div>
        <PageHeader title={"Admin Settings"} description={`Manage platform-wide settings for ${tenant?.name || "your community"}`} />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={"Admin Settings"} description={`Manage platform-wide settings for ${tenant?.name || "your community"}`} />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Settings size={20} /> {"General"}
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label={"Site Name"}
              placeholder={"Project NEXUS..."}
              variant="bordered"
              value={form.name}
              onValueChange={(val) => setForm(prev => ({ ...prev, name: val }))}
            />
            <Textarea
              label={"Site Description"}
              placeholder={"Community Timebanking Platform..."}
              variant="bordered"
              minRows={2}
              value={form.description}
              onValueChange={(val) => setForm(prev => ({ ...prev, description: val }))}
            />
            <Input
              label={"Support Email"}
              placeholder="support@project-nexus.ie"
              variant="bordered"
              value={form.contact_email}
              onValueChange={(val) => setForm(prev => ({ ...prev, contact_email: val }))}
            />
            <Input
              label={"Contact Phone"}
              placeholder="+1 555 123 4567"
              variant="bordered"
              value={form.contact_phone}
              onValueChange={(val) => setForm(prev => ({ ...prev, contact_phone: val }))}
            />
            <Select
              label={"Default currency"}
              description={"Used for subscription and marketplace pricing"}
              variant="bordered"
              selectedKeys={[form.default_currency]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as string | undefined;
                if (val) setForm(prev => ({ ...prev, default_currency: val }));
              }}
            >
              {CURRENCY_OPTIONS.map(opt => (
                <SelectItem key={opt.code}>{opt.label}</SelectItem>
              ))}
            </Select>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Scale size={20} /> {"Branding & Legal"}
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Textarea
              label={"Footer Legal Text"}
              description={"Displayed in the site footer and on legal/about pages"}
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
            <h3 className="text-lg font-semibold">{"Registration & Access"}</h3>
            <Link to={tenantPath("/admin/settings/registration-policy")}>
              <Button size="sm" variant="flat" color="primary" startContent={<ShieldCheck size={14} />}>
                {"Advanced policy"}
              </Button>
            </Link>
          </CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Open registration"}</p>
                <p className="text-sm text-default-500">{"Allow anyone to register an account"}</p>
              </div>
              <Switch
                isSelected={form.registration_mode === 'open'}
                onValueChange={(val) => setForm(prev => ({ ...prev, registration_mode: val ? 'open' : 'closed' }))}
                aria-label={"Open Registration"}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Require email verification"}</p>
                <p className="text-sm text-default-500">{"Members must verify their email before accessing the platform"}</p>
              </div>
              <Switch
                isSelected={form.email_verification}
                onValueChange={(val) => setForm(prev => ({ ...prev, email_verification: val }))}
                aria-label={"Email Verification"}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Admin approval required"}</p>
                <p className="text-sm text-default-500">{"New accounts must be approved by an admin before activation"}</p>
              </div>
              <Switch
                isSelected={form.admin_approval}
                onValueChange={(val) => setForm(prev => ({ ...prev, admin_approval: val }))}
                aria-label={"Admin Approval"}
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-default-400">{"Maintenance mode (read only)"}</p>
                <p className="text-sm text-default-500">Use CLI: <code className="text-xs bg-default-100 px-1 rounded">sudo bash scripts/maintenance.sh on|off</code></p>
              </div>
              <Switch
                isSelected={form.maintenance_mode}
                isDisabled
                aria-label={"Maintenance Mode"}
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
            {"Save settings"}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default AdminSettings;
