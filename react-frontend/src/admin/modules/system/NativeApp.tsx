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
import { Smartphone, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

import { useTranslation } from 'react-i18next';
export function NativeApp() {
  const { t } = useTranslation('admin');
  usePageTitle(t('system.page_title'));
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
      .catch(() => toast.error(t('system.failed_to_load_native_app_settings')))
      .finally(() => setLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps -- load once on mount
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      await adminSettings.updateNativeAppSettings(formData);
      toast.success(t('system.native_app_settings_saved_successfully'));
    } catch {
      toast.error(t('system.failed_to_save_native_app_settings'));
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
      <PageHeader title={t('system.native_app_title')} description={t('system.native_app_desc')} />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Smartphone size={20} /> {t('system.app_configuration_heading')}</h3></CardHeader>
          <CardBody className="gap-4">
            <Input
              label={t('system.label_app_name')}
              variant="bordered"
              value={String(formData.app_name || '')}
              onValueChange={(v) => updateField('app_name', v)}
            />
            <Input
              label={t('system.label_bundle_id')}
              variant="bordered"
              value={String(formData.bundle_id || '')}
              onValueChange={(v) => updateField('bundle_id', v)}
            />
            <Input
              label={t('system.label_package_name')}
              variant="bordered"
              value={String(formData.package_name || '')}
              onValueChange={(v) => updateField('package_name', v)}
            />
            <Input
              label={t('system.label_app_version')}
              variant="bordered"
              value={String(formData.app_version || '')}
              onValueChange={(v) => updateField('app_version', v)}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{t('system.push_notifications_heading')}</h3></CardHeader>
          <CardBody className="gap-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('system.enable_push_notifications')}</p>
                <p className="text-sm text-default-500">{t('system.enable_push_notifications_desc')}</p>
              </div>
              <Switch isSelected={!!formData.push_enabled} onValueChange={(v) => updateField('push_enabled', v)} aria-label={t('system.label_push_notifications')} />
            </div>
            <Input
              label={t('system.label_f_c_m_server_key')}
              type="password"
              placeholder={t('system.placeholder_a_iza')}
              variant="bordered"
              description={t('system.desc_firebase_cloud_messaging_server_key')}
              value={String(formData.fcm_server_key || '')}
              onValueChange={(v) => updateField('fcm_server_key', v)}
            />
            <Input
              label={t('system.label_a_p_n_s_key_i_d')}
              type="password"
              placeholder="..."
              variant="bordered"
              description={t('system.desc_apple_push_notification_service_key')}
              value={String(formData.apns_key_id || '')}
              onValueChange={(v) => updateField('apns_key_id', v)}
            />
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{t('system.pwa_settings_heading')}</h3></CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('system.service_worker_enabled')}</p>
                <p className="text-sm text-default-500">{t('system.service_worker_enabled_desc')}</p>
              </div>
              <Switch isSelected={!!formData.service_worker_enabled} onValueChange={(v) => updateField('service_worker_enabled', v)} aria-label={t('system.label_service_worker')} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('system.install_prompt')}</p>
                <p className="text-sm text-default-500">{t('system.install_prompt_desc')}</p>
              </div>
              <Switch isSelected={!!formData.install_prompt_enabled} onValueChange={(v) => updateField('install_prompt_enabled', v)} aria-label={t('system.label_install_prompt')} />
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving}>{t('system.save_settings')}</Button>
        </div>
      </div>
    </div>
  );
}

export default NativeApp;
