/**
 * Federation Settings
 * Configure federation features and partnership preferences for the tenant.
 * Includes functional switches with save/dirty state tracking.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, Switch, Button, Input, Divider, Spinner } from '@heroui/react';
import { Network, RefreshCw, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';

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
  usePageTitle('Admin - Federation Settings');
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
      toast.error('Failed to load federation settings');
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
        toast.success('Federation settings saved successfully');
        setDirty(false);
      } else {
        const error = (res as { error?: string }).error || 'Failed to save federation settings';
        toast.error(error);
      }
    } catch (err) {
      toast.error('Failed to save federation settings');
    } finally {
      setSaving(false);
    }
  }, [data, toast]);

  if (loading) {
    return (
      <div>
        <PageHeader
          title="Federation Settings"
          description="Configure cross-community federation features"
        />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  if (!data) {
    return (
      <div>
        <PageHeader
          title="Federation Settings"
          description="Configure cross-community federation features"
          actions={
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData}>
              Refresh
            </Button>
          }
        />
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center py-8 text-default-400">
            <Network size={40} className="mb-2" />
            <p>Federation feature is not enabled for this tenant.</p>
            <p className="text-xs">Enable it from Tenant Features to configure settings.</p>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Federation Settings"
        description="Configure cross-community federation features"
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              size="sm"
            >
              Refresh
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
              isDisabled={!dirty}
              size="sm"
            >
              Save Changes
            </Button>
          </div>
        }
      />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Federation Status</h3></CardHeader>
          <CardBody>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Federation Enabled</p>
                <p className="text-sm text-default-500">Allow this community to participate in the federation network</p>
              </div>
              <Switch
                isSelected={data.federation_enabled}
                onValueChange={(val) => updateField('federation_enabled', val)}
                aria-label="Federation enabled"
              />
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Partnership Preferences</h3></CardHeader>
          <CardBody className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Allow Inbound Partnerships</p>
                <p className="text-sm text-default-500">Other communities can request to partner with you</p>
              </div>
              <Switch
                isSelected={data.settings?.allow_inbound_partnerships ?? true}
                onValueChange={(val) => updateField('allow_inbound_partnerships', val)}
                aria-label="Allow inbound partnerships"
              />
            </div>
            <Divider />
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Auto-Approve Partners</p>
                <p className="text-sm text-default-500">Automatically approve incoming partnership requests</p>
              </div>
              <Switch
                isSelected={data.settings?.auto_approve_partners ?? false}
                onValueChange={(val) => updateField('auto_approve_partners', val)}
                aria-label="Auto approve partners"
              />
            </div>
            <Divider />
            <div className="flex items-center justify-between gap-4">
              <div>
                <p className="font-medium">Max Partnerships</p>
                <p className="text-sm text-default-500">Maximum number of active partnerships allowed</p>
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
                aria-label="Max partnerships"
              />
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default FederationSettings;
