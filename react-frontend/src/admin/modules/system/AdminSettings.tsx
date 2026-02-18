/**
 * Admin Settings
 * Global platform configuration and settings management.
 */

import { useState, useEffect, useCallback } from 'react';
import { Card, CardBody, CardHeader, Input, Switch, Button, Textarea, Spinner } from '@heroui/react';
import { Settings, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

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
};

export function AdminSettings() {
  usePageTitle('Admin - Settings');
  const toast = useToast();

  const [form, setForm] = useState<SettingsForm>(DEFAULT_SETTINGS);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const fetchSettings = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminSettings.get();
      const data = res.data;
      if (data) {
        // API returns nested structure: { tenant: {...}, settings: {...} }
        const tenant = (data as any).tenant || data;
        const settings = (data as any).settings || data;

        setForm({
          name: (tenant.name as string) ?? '',
          description: (tenant.description as string) ?? '',
          contact_email: (tenant.contact_email as string) ?? '',
          contact_phone: (tenant.contact_phone as string) ?? '',
          registration_mode: (settings.registration_mode as string) ?? 'open',
          email_verification: settings.email_verification === 'true' || settings.email_verification === true || settings.email_verification !== 'false',
          admin_approval: settings.admin_approval === 'true' || settings.admin_approval === true,
          maintenance_mode: settings.maintenance_mode === 'true' || settings.maintenance_mode === true,
        });
      }
    } catch {
      toast.error('Failed to load settings');
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
      const res = await adminSettings.update({
        name: form.name,
        description: form.description,
        contact_email: form.contact_email,
        contact_phone: form.contact_phone,
        registration_mode: form.registration_mode,
        email_verification: String(form.email_verification),
        admin_approval: String(form.admin_approval),
        maintenance_mode: String(form.maintenance_mode),
      });

      if (res.success) {
        toast.success('Settings saved');
        // Reload settings to confirm persistence
        fetchSettings();
      } else {
        const error = (res as { error?: string }).error || 'Save failed';
        toast.error(error);
      }
    } catch (err) {
      toast.error('Failed to save settings');
      console.error('Settings save error:', err);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div>
        <PageHeader title="Admin Settings" description="Global platform configuration" />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Admin Settings" description="Global platform configuration" />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Settings size={20} /> General
            </h3>
          </CardHeader>
          <CardBody className="gap-4">
            <Input
              label="Site Name"
              placeholder="Project NEXUS"
              variant="bordered"
              value={form.name}
              onValueChange={(val) => setForm(prev => ({ ...prev, name: val }))}
            />
            <Textarea
              label="Site Description"
              placeholder="Community timebanking platform"
              variant="bordered"
              minRows={2}
              value={form.description}
              onValueChange={(val) => setForm(prev => ({ ...prev, description: val }))}
            />
            <Input
              label="Support Email"
              placeholder="support@project-nexus.ie"
              variant="bordered"
              value={form.contact_email}
              onValueChange={(val) => setForm(prev => ({ ...prev, contact_email: val }))}
            />
            <Input
              label="Contact Phone"
              placeholder="+353..."
              variant="bordered"
              value={form.contact_phone}
              onValueChange={(val) => setForm(prev => ({ ...prev, contact_phone: val }))}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold">Registration & Access</h3>
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
                aria-label="Open registration"
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
                aria-label="Email verification"
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
                aria-label="Admin approval"
              />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Maintenance Mode</p>
                <p className="text-sm text-default-500">Only admins can access the platform</p>
              </div>
              <Switch
                isSelected={form.maintenance_mode}
                onValueChange={(val) => setForm(prev => ({ ...prev, maintenance_mode: val }))}
                aria-label="Maintenance mode"
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
