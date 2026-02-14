/**
 * Native App Settings
 * Configure mobile app (Capacitor) settings and build options.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Switch, Button, Spinner } from '@heroui/react';
import { Smartphone, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

export function NativeApp() {
  usePageTitle('Admin - Native App');
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<Record<string, unknown>>({
    app_name: 'Project NEXUS',
    bundle_id: 'ie.project-nexus.app',
    package_name: 'ie.projectnexus.app',
    app_version: '1.0.0',
    push_enabled: true,
    fcm_server_key: '',
    apns_key_id: '',
    service_worker_enabled: true,
    install_prompt_enabled: true,
  });

  useEffect(() => {
    adminSettings.getNativeAppSettings()
      .then(res => {
        if (res.data) {
          setFormData(prev => ({ ...prev, ...res.data }));
        }
      })
      .catch(() => toast.error('Failed to load native app settings'))
      .finally(() => setLoading(false));
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      await adminSettings.updateNativeAppSettings(formData);
      toast.success('Native app settings saved successfully');
    } catch {
      toast.error('Failed to save native app settings');
    } finally {
      setSaving(false);
    }
  };

  const updateField = (key: string, value: unknown) => {
    setFormData(prev => ({ ...prev, [key]: value }));
  };

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Native App" description="Mobile app configuration and build settings" />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Smartphone size={20} /> App Configuration</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label="App Name"
              variant="bordered"
              value={String(formData.app_name || '')}
              onValueChange={(v) => updateField('app_name', v)}
            />
            <Input
              label="Bundle ID (iOS)"
              variant="bordered"
              value={String(formData.bundle_id || '')}
              onValueChange={(v) => updateField('bundle_id', v)}
            />
            <Input
              label="Package Name (Android)"
              variant="bordered"
              value={String(formData.package_name || '')}
              onValueChange={(v) => updateField('package_name', v)}
            />
            <Input
              label="App Version"
              variant="bordered"
              value={String(formData.app_version || '')}
              onValueChange={(v) => updateField('app_version', v)}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Push Notifications</h3></CardHeader>
          <CardBody className="gap-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Enable Push Notifications</p>
                <p className="text-sm text-default-500">Send push notifications to mobile app users</p>
              </div>
              <Switch isSelected={!!formData.push_enabled} onValueChange={(v) => updateField('push_enabled', v)} aria-label="Push notifications" />
            </div>
            <Input
              label="FCM Server Key"
              type="password"
              placeholder="AIza..."
              variant="bordered"
              description="Firebase Cloud Messaging server key"
              value={String(formData.fcm_server_key || '')}
              onValueChange={(v) => updateField('fcm_server_key', v)}
            />
            <Input
              label="APNS Key ID"
              type="password"
              placeholder="..."
              variant="bordered"
              description="Apple Push Notification service key"
              value={String(formData.apns_key_id || '')}
              onValueChange={(v) => updateField('apns_key_id', v)}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">PWA Settings</h3></CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Service Worker Enabled</p>
                <p className="text-sm text-default-500">Enable offline support and caching</p>
              </div>
              <Switch isSelected={!!formData.service_worker_enabled} onValueChange={(v) => updateField('service_worker_enabled', v)} aria-label="Service worker" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Install Prompt</p>
                <p className="text-sm text-default-500">Show "Add to Home Screen" prompt</p>
              </div>
              <Switch isSelected={!!formData.install_prompt_enabled} onValueChange={(v) => updateField('install_prompt_enabled', v)} aria-label="Install prompt" />
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving}>Save Settings</Button>
        </div>
      </div>
    </div>
  );
}

export default NativeApp;
