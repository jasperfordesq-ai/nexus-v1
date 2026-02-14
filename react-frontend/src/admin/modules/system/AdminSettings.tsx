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

interface SettingsForm {
  site_name: string;
  site_description: string;
  support_email: string;
  contact_phone: string;
  open_registration: boolean;
  email_verification: boolean;
  admin_approval: boolean;
  maintenance_mode: boolean;
}

const DEFAULT_SETTINGS: SettingsForm = {
  site_name: '',
  site_description: '',
  support_email: '',
  contact_phone: '',
  open_registration: true,
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
        setForm({
          site_name: (data.site_name as string) ?? '',
          site_description: (data.site_description as string) ?? '',
          support_email: (data.support_email as string) ?? '',
          contact_phone: (data.contact_phone as string) ?? '',
          open_registration: data.open_registration !== false,
          email_verification: data.email_verification !== false,
          admin_approval: !!data.admin_approval,
          maintenance_mode: !!data.maintenance_mode,
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
      await adminSettings.update(form as unknown as Record<string, unknown>);
      toast.success('Settings saved');
    } catch {
      toast.error('Failed to save settings');
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
              value={form.site_name}
              onValueChange={(val) => setForm(prev => ({ ...prev, site_name: val }))}
            />
            <Textarea
              label="Site Description"
              placeholder="Community timebanking platform"
              variant="bordered"
              minRows={2}
              value={form.site_description}
              onValueChange={(val) => setForm(prev => ({ ...prev, site_description: val }))}
            />
            <Input
              label="Support Email"
              placeholder="support@project-nexus.ie"
              variant="bordered"
              value={form.support_email}
              onValueChange={(val) => setForm(prev => ({ ...prev, support_email: val }))}
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
                isSelected={form.open_registration}
                onValueChange={(val) => setForm(prev => ({ ...prev, open_registration: val }))}
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
