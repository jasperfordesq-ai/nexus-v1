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
import Network from 'lucide-react/icons/network';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import KeyRound from 'lucide-react/icons/key-round';
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
  usePageTitle("Federation");
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
      toast.error("Failed to load federation settings");
      setData(null);
    }
    setLoading(false);
  }, [toast])


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
        toast.success("Federation settings saved successfully");
        setDirty(false);
      } else {
        const error = (res as { error?: string }).error || "Save failed";
        toast.error(error);
      }
    } catch {
      toast.error("Failed to save federation settings");
    } finally {
      setSaving(false);
    }
  }, [data, toast])


  if (loading) {
    return (
      <div>
        <PageHeader
          title={"Federation Settings"}
          description={"Configure federation settings including profile, limits, and permissions"}
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
          title={"Federation Settings"}
          description={"Configure federation settings including profile, limits, and permissions"}
          actions={
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData}>
              {"Refresh"}
            </Button>
          }
        />
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center py-8 text-default-400">
            <Network size={40} className="mb-2" />
            <p>{"Not Enabled for Tenant"}</p>
            <p className="text-xs">{"Enable from Tenant Features"}</p>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={"Federation Settings"}
        description={"Configure federation settings including profile, limits, and permissions"}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              size="sm"
            >
              {"Refresh"}
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
              isDisabled={!dirty}
              size="sm"
            >
              {"Save Changes"}
            </Button>
          </div>
        }
      />

      <div className="space-y-4">
        {/* Platform-level JWT auth is configured in Super Admin → Federation
            Controls. Tenant admins see this pointer so they know where to
            look (and can ask the platform operator) when a partner using
            JWT-based auth has trouble. */}
        <Card shadow="sm" className="border border-default-200">
          <CardBody className="flex flex-row items-start gap-3 text-sm">
            <KeyRound size={18} className="text-default-500 mt-0.5 shrink-0" />
            <div>
              <p className="font-medium">JWT-based federation auth is configured at platform level</p>
              <p className="text-default-500 mt-1">
                Most partners authenticate via API key, HMAC, or OAuth2 — those are configured
                per-partner in <strong>External Partners</strong> and need no platform setup.
                If a partner specifically requires JWT auth, the platform super-admin must set{' '}
                <code className="text-xs bg-default-100 px-1 rounded">FEDERATION_JWT_SECRET</code>{' '}
                in the server environment. Status and setup instructions live under{' '}
                <strong>Super Admin → Federation Controls</strong>.
              </p>
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{"Federation Status"}</h3></CardHeader>
          <CardBody>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Federation Enabled"}</p>
                <p className="text-sm text-default-500">{"Enable federation to allow this community to partner with others"}</p>
              </div>
              <Switch
                isSelected={data.federation_enabled}
                onValueChange={(val) => updateField('federation_enabled', val)}
                aria-label={"Federation Enabled"}
              />
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{"Partnership Preferences"}</h3></CardHeader>
          <CardBody className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Allow Inbound Partnerships"}</p>
                <p className="text-sm text-default-500">{"Allow other communities to send partnership requests to this community"}</p>
              </div>
              <Switch
                isSelected={data.settings?.allow_inbound_partnerships ?? true}
                onValueChange={(val) => updateField('allow_inbound_partnerships', val)}
                aria-label={"Allow Inbound Partnerships"}
              />
            </div>
            <Divider />
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Auto Approve Partners"}</p>
                <p className="text-sm text-default-500">{"Automatically approve incoming partnership requests without manual review"}</p>
              </div>
              <Switch
                isSelected={data.settings?.auto_approve_partners ?? false}
                onValueChange={(val) => updateField('auto_approve_partners', val)}
                aria-label={"Auto Approve Partners"}
              />
            </div>
            <Divider />
            <div className="flex items-center justify-between gap-4">
              <div>
                <p className="font-medium">{"Max Partnerships"}</p>
                <p className="text-sm text-default-500">{"Maximum number of active partnerships this community can maintain"}</p>
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
                aria-label={"Max Partnerships"}
              />
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default FederationSettings;
