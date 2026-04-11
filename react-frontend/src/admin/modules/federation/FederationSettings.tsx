// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Settings
 * Configure federation features and partnership preferences for the tenant.
 * Includes functional switches with save/dirty state tracking.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, Switch, Button, Input, Divider, Skeleton } from '@heroui/react';
import { Network, RefreshCw, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';

import { useTranslation } from 'react-i18next';
interface FedSettings {
  federation_enabled: boolean;
  tenant_id: number;
  settings: {
    allow_inbound_partnerships: boolean;
    auto_approve_partners: boolean;
    shared_categories: string[];
    max_partnerships: number;
  };
}

export function FederationSettings() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation.page_title'));
  const toast = useToast();

  const [data, setData] = useState<FedSettings | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getSettings();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          setData((payload as { data: FedSettings }).data);
        } else {
          setData(payload as FedSettings);
        }
        setDirty(false);
      }
    } catch {
      toast.error(t('federation.failed_to_load_federation_settings'));
      setData(null);
    }
    setLoading(false);
  }, [toast]);

  useEffect(() => { loadData(); }, [loadData]);

  const updateField = useCallback((field: string, value: boolean | number) => {
    setData((prev) => {
      if (!prev) return prev;
      if (field === 'federation_enabled') {
        return { ...prev, federation_enabled: value as boolean };
      }
      return {
        ...prev,
        settings: { ...prev.settings, [field]: value },
      };
    });
    setDirty(true);
  }, []);

  const handleSave = useCallback(async () => {
    if (!data) return;
    setSaving(true);
    try {
      const res = await adminFederation.updateSettings({
        federation_enabled: data.federation_enabled,
        settings: data.settings,
      });
      if (res.success) {
        toast.success(t('federation.federation_settings_saved_successfully'));
        setDirty(false);
      } else {
        const error = (res as { error?: string }).error || 'Failed to save federation settings';
        toast.error(error);
      }
    } catch {
      toast.error(t('federation.failed_to_save_federation_settings'));
    } finally {
      setSaving(false);
    }
  }, [data, toast]);

  if (loading) {
    return (
      <div>
        <PageHeader
          title={t('federation.federation_settings_title')}
          description={t('federation.federation_settings_desc')}
        />
        <div className="space-y-6">
          <Card shadow="sm">
            <CardHeader><Skeleton className="h-5 w-40 rounded-lg" /></CardHeader>
            <CardBody className="space-y-4">
              {[1, 2, 3].map((i) => (
                <div key={i} className="flex items-center justify-between">
                  <div className="space-y-2">
                    <Skeleton className="h-4 w-32 rounded-lg" />
                    <Skeleton className="h-3 w-48 rounded-lg" />
                  </div>
                  <Skeleton className="h-6 w-12 rounded-full" />
                </div>
              ))}
            </CardBody>
          </Card>
          <Card shadow="sm">
            <CardHeader><Skeleton className="h-5 w-48 rounded-lg" /></CardHeader>
            <CardBody className="space-y-4">
              {[1, 2].map((i) => (
                <div key={i} className="flex items-center justify-between">
                  <Skeleton className="h-4 w-36 rounded-lg" />
                  <Skeleton className="h-8 w-20 rounded-lg" />
                </div>
              ))}
            </CardBody>
          </Card>
        </div>
      </div>
    );
  }

  if (!data) {
    return (
      <div>
        <PageHeader
          title={t('federation.federation_settings_title')}
          description={t('federation.federation_settings_desc')}
          actions={
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData}>
              {t('federation.refresh')}
            </Button>
          }
        />
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center py-8 text-default-400">
            <Network size={40} className="mb-2" />
            <p>{t('federation.not_enabled_for_tenant')}</p>
            <p className="text-xs">{t('federation.enable_from_tenant_features')}</p>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('federation.federation_settings_title')}
        description={t('federation.federation_settings_desc')}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              size="sm"
            >
              {t('federation.refresh')}
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
              isDisabled={!dirty}
              size="sm"
            >
              {t('federation.save_changes')}
            </Button>
          </div>
        }
      />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{t('federation.federation_status')}</h3></CardHeader>
          <CardBody>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('federation.federation_enabled')}</p>
                <p className="text-sm text-default-500">{t('federation.federation_enabled_desc')}</p>
              </div>
              <Switch
                isSelected={data.federation_enabled}
                onValueChange={(val) => updateField('federation_enabled', val)}
                aria-label={t('federation.label_federation_enabled')}
              />
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{t('federation.partnership_preferences')}</h3></CardHeader>
          <CardBody className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('federation.allow_inbound_partnerships')}</p>
                <p className="text-sm text-default-500">{t('federation.allow_inbound_partnerships_desc')}</p>
              </div>
              <Switch
                isSelected={data.settings?.allow_inbound_partnerships ?? true}
                onValueChange={(val) => updateField('allow_inbound_partnerships', val)}
                aria-label={t('federation.label_allow_inbound_partnerships')}
              />
            </div>
            <Divider />
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('federation.auto_approve_partners')}</p>
                <p className="text-sm text-default-500">{t('federation.auto_approve_partners_desc')}</p>
              </div>
              <Switch
                isSelected={data.settings?.auto_approve_partners ?? false}
                onValueChange={(val) => updateField('auto_approve_partners', val)}
                aria-label={t('federation.label_auto_approve_partners')}
              />
            </div>
            <Divider />
            <div className="flex items-center justify-between gap-4">
              <div>
                <p className="font-medium">{t('federation.max_partnerships')}</p>
                <p className="text-sm text-default-500">{t('federation.max_partnerships_desc')}</p>
              </div>
              <Input
                type="number"
                value={String(data.settings?.max_partnerships ?? 10)}
                onValueChange={(val) => updateField('max_partnerships', parseInt(val) || 1)}
                variant="bordered"
                size="sm"
                className="w-24"
                min={1}
                max={100}
                aria-label={t('federation.label_max_partnerships')}
              />
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default FederationSettings;
