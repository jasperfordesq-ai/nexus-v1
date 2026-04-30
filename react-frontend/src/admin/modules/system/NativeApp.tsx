// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Native App Settings
 * Configure mobile app (Capacitor) settings and tenant-branded build readiness.
 */

import { useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Input,
  Select,
  SelectItem,
  Spinner,
  Switch,
} from '@heroui/react';
import Download from 'lucide-react/icons/download';
import Save from 'lucide-react/icons/save';
import Smartphone from 'lucide-react/icons/smartphone';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

type NativeAppForm = Record<string, string | boolean>;

interface NativeAppReadiness {
  has_ios_identity?: boolean;
  has_android_identity?: boolean;
  has_store_metadata?: boolean;
  push_routing_configured?: boolean;
  tenant_branded_ready?: boolean;
}

interface NativeAppResponse {
  native_app?: NativeAppForm;
  deployment_readiness?: NativeAppReadiness;
}

const DEFAULT_FORM: NativeAppForm = {
  native_app_name: 'Project NEXUS',
  native_app_short_name: 'NEXUS',
  native_app_bundle_id: 'com.nexus.timebank',
  native_app_package_name: 'com.nexus.timebank',
  native_app_version: '1.1.0',
  native_app_push_enabled: true,
  native_app_fcm_server_key: '',
  native_app_apns_key_id: '',
  native_app_apns_team_id: '',
  native_app_service_worker: true,
  native_app_install_prompt: true,
  native_app_theme_color: '#1976D2',
  native_app_background_color: '#ffffff',
  native_app_display: 'standalone',
  native_app_orientation: 'portrait',
  native_app_store_mode: 'shared',
  native_app_build_profile: 'preview',
  native_app_ios_app_store_id: '',
  native_app_android_play_store_id: '',
  native_app_marketing_url: '',
  native_app_privacy_url: '',
  native_app_support_url: '',
  native_app_push_sender_id: '',
  native_app_tenant_channel_prefix: '',
};

const readinessLabels: Array<[keyof NativeAppReadiness, string]> = [
  ['has_ios_identity', 'iOS identity'],
  ['has_android_identity', 'Android identity'],
  ['has_store_metadata', 'Store metadata'],
  ['push_routing_configured', 'Push routing'],
  ['tenant_branded_ready', 'Tenant-branded ready'],
];

function fieldValue(formData: NativeAppForm, key: string): string {
  const value = formData[key];
  return typeof value === 'boolean' ? String(value) : String(value ?? '');
}

export function NativeApp() {
  usePageTitle('System');
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [formData, setFormData] = useState<NativeAppForm>(DEFAULT_FORM);
  const [readiness, setReadiness] = useState<NativeAppReadiness>({});

  const tenantBranded = formData.native_app_store_mode === 'tenant_branded';
  const readyCount = useMemo(
    () => readinessLabels.filter(([key]) => readiness[key]).length,
    [readiness],
  );

  useEffect(() => {
    adminSettings.getNativeAppSettings()
      .then((res) => {
        const payload = (res.data ?? {}) as NativeAppResponse;
        setFormData((prev) => ({ ...prev, ...(payload.native_app ?? {}) }));
        setReadiness(payload.deployment_readiness ?? {});
      })
      .catch(() => toast.error('Failed to load native app settings'))
      .finally(() => setLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps -- load once on mount
  }, []);

  const updateField = (key: string, value: string | boolean) => {
    setFormData((prev) => ({ ...prev, [key]: value }));
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      await adminSettings.updateNativeAppSettings(formData);
      const refreshed = await adminSettings.getNativeAppSettings();
      const payload = (refreshed.data ?? {}) as NativeAppResponse;
      setFormData((prev) => ({ ...prev, ...(payload.native_app ?? {}) }));
      setReadiness(payload.deployment_readiness ?? {});
      toast.success('Native app settings saved successfully');
    } catch {
      toast.error('Failed to save native app settings');
    } finally {
      setSaving(false);
    }
  };

  const exportBuildManifest = async () => {
    setExporting(true);
    try {
      const response = await adminSettings.getNativeAppBuildManifest();
      const manifest = response.data ?? {};
      const blob = new Blob([JSON.stringify(manifest, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'native-app-build-manifest.json';
      link.click();
      URL.revokeObjectURL(url);
      toast.success('Build manifest exported');
    } catch {
      toast.error('Failed to export build manifest');
    } finally {
      setExporting(false);
    }
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
      <PageHeader
        title="Native App"
        description="Configure PWA, push, and tenant-branded mobile app build readiness"
      />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2">
            <Smartphone size={20} />
            <h3 className="text-lg font-semibold">App Configuration</h3>
          </CardHeader>
          <CardBody className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Input label="App name" variant="bordered" value={fieldValue(formData, 'native_app_name')} onValueChange={(v) => updateField('native_app_name', v)} />
            <Input label="Short name" variant="bordered" value={fieldValue(formData, 'native_app_short_name')} onValueChange={(v) => updateField('native_app_short_name', v)} />
            <Input label="Bundle ID" variant="bordered" value={fieldValue(formData, 'native_app_bundle_id')} onValueChange={(v) => updateField('native_app_bundle_id', v)} />
            <Input label="Package name" variant="bordered" value={fieldValue(formData, 'native_app_package_name')} onValueChange={(v) => updateField('native_app_package_name', v)} />
            <Input label="App version" variant="bordered" value={fieldValue(formData, 'native_app_version')} onValueChange={(v) => updateField('native_app_version', v)} />
            <Select label="Store mode" selectedKeys={[fieldValue(formData, 'native_app_store_mode')]} onChange={(event) => updateField('native_app_store_mode', event.target.value)}>
              <SelectItem key="shared">Shared NEXUS app</SelectItem>
              <SelectItem key="tenant_branded">Tenant-branded app</SelectItem>
            </Select>
            <Select label="Build profile" selectedKeys={[fieldValue(formData, 'native_app_build_profile')]} onChange={(event) => updateField('native_app_build_profile', event.target.value)}>
              <SelectItem key="preview">Preview</SelectItem>
              <SelectItem key="production">Production</SelectItem>
            </Select>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Tenant-Branded Store Metadata</h3></CardHeader>
          <CardBody className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Input label="iOS App Store ID" variant="bordered" value={fieldValue(formData, 'native_app_ios_app_store_id')} onValueChange={(v) => updateField('native_app_ios_app_store_id', v)} />
            <Input label="Android Play Store ID" variant="bordered" value={fieldValue(formData, 'native_app_android_play_store_id')} onValueChange={(v) => updateField('native_app_android_play_store_id', v)} />
            <Input label="Marketing URL" variant="bordered" value={fieldValue(formData, 'native_app_marketing_url')} onValueChange={(v) => updateField('native_app_marketing_url', v)} />
            <Input label="Privacy URL" variant="bordered" value={fieldValue(formData, 'native_app_privacy_url')} onValueChange={(v) => updateField('native_app_privacy_url', v)} />
            <Input label="Support URL" variant="bordered" value={fieldValue(formData, 'native_app_support_url')} onValueChange={(v) => updateField('native_app_support_url', v)} className="md:col-span-2" />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Push Notifications</h3></CardHeader>
          <CardBody className="gap-4">
            <div className="flex items-center justify-between gap-4">
              <div>
                <p className="font-medium">Enable push notifications</p>
                <p className="text-sm text-default-500">Route FCM/APNs notifications through the configured mobile app channel.</p>
              </div>
              <Switch isSelected={!!formData.native_app_push_enabled} onValueChange={(v) => updateField('native_app_push_enabled', v)} aria-label="Push Notifications" />
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Input label="FCM server key" type="password" variant="bordered" value={fieldValue(formData, 'native_app_fcm_server_key')} onValueChange={(v) => updateField('native_app_fcm_server_key', v)} />
              <Input label="APNs key ID" type="password" variant="bordered" value={fieldValue(formData, 'native_app_apns_key_id')} onValueChange={(v) => updateField('native_app_apns_key_id', v)} />
              <Input label="APNs team ID" variant="bordered" value={fieldValue(formData, 'native_app_apns_team_id')} onValueChange={(v) => updateField('native_app_apns_team_id', v)} />
              <Input label="Push sender ID" variant="bordered" value={fieldValue(formData, 'native_app_push_sender_id')} onValueChange={(v) => updateField('native_app_push_sender_id', v)} />
              <Input label="Tenant channel prefix" variant="bordered" value={fieldValue(formData, 'native_app_tenant_channel_prefix')} onValueChange={(v) => updateField('native_app_tenant_channel_prefix', v)} className="md:col-span-2" />
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">PWA Settings</h3></CardHeader>
          <CardBody className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="flex items-center justify-between rounded-lg border border-divider p-3">
              <div>
                <p className="font-medium">Service worker</p>
                <p className="text-sm text-default-500">Enable offline shell and app caching.</p>
              </div>
              <Switch isSelected={!!formData.native_app_service_worker} onValueChange={(v) => updateField('native_app_service_worker', v)} aria-label="Service Worker" />
            </div>
            <div className="flex items-center justify-between rounded-lg border border-divider p-3">
              <div>
                <p className="font-medium">Install prompt</p>
                <p className="text-sm text-default-500">Show eligible browser install prompts.</p>
              </div>
              <Switch isSelected={!!formData.native_app_install_prompt} onValueChange={(v) => updateField('native_app_install_prompt', v)} aria-label="Install Prompt" />
            </div>
            <Input label="Theme color" type="color" variant="bordered" value={fieldValue(formData, 'native_app_theme_color')} onValueChange={(v) => updateField('native_app_theme_color', v)} />
            <Input label="Background color" type="color" variant="bordered" value={fieldValue(formData, 'native_app_background_color')} onValueChange={(v) => updateField('native_app_background_color', v)} />
            <Select label="Display" selectedKeys={[fieldValue(formData, 'native_app_display')]} onChange={(event) => updateField('native_app_display', event.target.value)}>
              <SelectItem key="standalone">Standalone</SelectItem>
              <SelectItem key="fullscreen">Fullscreen</SelectItem>
              <SelectItem key="minimal-ui">Minimal UI</SelectItem>
              <SelectItem key="browser">Browser</SelectItem>
            </Select>
            <Select label="Orientation" selectedKeys={[fieldValue(formData, 'native_app_orientation')]} onChange={(event) => updateField('native_app_orientation', event.target.value)}>
              <SelectItem key="portrait">Portrait</SelectItem>
              <SelectItem key="landscape">Landscape</SelectItem>
              <SelectItem key="any">Any</SelectItem>
            </Select>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between">
            <h3 className="text-lg font-semibold">Deployment Readiness</h3>
            <Chip color={tenantBranded && readiness.tenant_branded_ready ? 'success' : 'warning'} variant="flat">
              {readyCount} / {readinessLabels.length}
            </Chip>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-5">
              {readinessLabels.map(([key, label]) => (
                <div key={key} className="rounded-lg border border-divider p-3">
                  <p className="text-sm font-medium">{label}</p>
                  <Chip size="sm" color={readiness[key] ? 'success' : 'default'} variant="flat" className="mt-2">
                    {readiness[key] ? 'Ready' : 'Missing'}
                  </Chip>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end gap-2">
          <Button variant="flat" startContent={<Download size={16} />} onPress={() => void exportBuildManifest()} isLoading={exporting}>
            Export Build Manifest
          </Button>
          <Button color="primary" startContent={<Save size={16} />} onPress={() => void handleSave()} isLoading={saving}>
            Save Settings
          </Button>
        </div>
      </div>
    </div>
  );
}

export default NativeApp;
