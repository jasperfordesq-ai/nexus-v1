// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Native App Settings
 * Configure mobile app (Capacitor) settings and build options.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Switch, Button, Spinner } from '@heroui/react';
import Smartphone from 'lucide-react/icons/smartphone';
import Save from 'lucide-react/icons/save';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

export function NativeApp() {
  usePageTitle("System");
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<Record<string, unknown>>({
    app_name: 'Project NEXUS',
    bundle_id: 'com.nexus.timebank',
    package_name: 'com.nexus.timebank',
    app_version: '1.1',
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
      .catch(() => toast.error("Failed to load native app settings"))
      .finally(() => setLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps -- load once on mount
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      await adminSettings.updateNativeAppSettings(formData);
      toast.success("Native app settings saved successfully");
    } catch {
      toast.error("Failed to save native app settings");
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
      <PageHeader title={"Native App"} description={"Configure native mobile app settings including FCM and APNs credentials"} />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Smartphone size={20} /> {"App Configuration"}</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label={"App Name"}
              variant="bordered"
              value={String(formData.app_name || '')}
              onValueChange={(v) => updateField('app_name', v)}
            />
            <Input
              label={"Bundle ID"}
              variant="bordered"
              value={String(formData.bundle_id || '')}
              onValueChange={(v) => updateField('bundle_id', v)}
            />
            <Input
              label={"Package Name"}
              variant="bordered"
              value={String(formData.package_name || '')}
              onValueChange={(v) => updateField('package_name', v)}
            />
            <Input
              label={"App Version"}
              variant="bordered"
              value={String(formData.app_version || '')}
              onValueChange={(v) => updateField('app_version', v)}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{"Push Notifications"}</h3></CardHeader>
          <CardBody className="gap-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Enable Push Notifications"}</p>
                <p className="text-sm text-default-500">{"Enable Push Notifications."}</p>
              </div>
              <Switch isSelected={!!formData.push_enabled} onValueChange={(v) => updateField('push_enabled', v)} aria-label={"Push Notifications"} />
            </div>
            <Input
              label={"FCM Server Key"}
              type="password"
              placeholder={"e.g. AIza..."}
              variant="bordered"
              description={"Firebase Cloud Messaging server key for Android push notifications"}
              value={String(formData.fcm_server_key || '')}
              onValueChange={(v) => updateField('fcm_server_key', v)}
            />
            <Input
              label={"APNs Key ID"}
              type="password"
              placeholder="..."
              variant="bordered"
              description={"Apple Push Notification Service (APNs) private key for iOS push notifications"}
              value={String(formData.apns_key_id || '')}
              onValueChange={(v) => updateField('apns_key_id', v)}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{"Pwa Settings"}</h3></CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Service Worker Enabled"}</p>
                <p className="text-sm text-default-500">{"Service Worker Enabled."}</p>
              </div>
              <Switch isSelected={!!formData.service_worker_enabled} onValueChange={(v) => updateField('service_worker_enabled', v)} aria-label={"Service Worker"} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Install Prompt"}</p>
                <p className="text-sm text-default-500">{"Install Prompt."}</p>
              </div>
              <Switch isSelected={!!formData.install_prompt_enabled} onValueChange={(v) => updateField('install_prompt_enabled', v)} aria-label={"Install Prompt"} />
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving}>{"Save Settings"}</Button>
        </div>
      </div>
    </div>
  );
}

export default NativeApp;
