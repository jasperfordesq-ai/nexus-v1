// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Input,
  Select,
  SelectItem,
  Switch,
} from '@heroui/react';
import { Lock, Shield } from 'lucide-react';
import PageHeader from '../../components/PageHeader';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminSuper } from '../../api/adminApi';
import type { FederationSystemControls as FederationSystemControlsType } from '../../api/types';

interface SystemControls {
  federation_enabled: boolean;
  whitelist_mode: boolean;
  max_level: number;
  lockdown_active: boolean;
  lockdown_reason?: string;
}

interface FeatureToggles {
  profiles: boolean;
  messaging: boolean;
  transactions: boolean;
  listings: boolean;
  events: boolean;
  groups: boolean;
}

function mapApiToLocal(sc: FederationSystemControlsType): { controls: SystemControls; features: FeatureToggles } {
  return {
    controls: {
      federation_enabled: sc.federation_enabled,
      whitelist_mode: sc.whitelist_mode_enabled,
      max_level: sc.max_federation_level,
      lockdown_active: sc.is_locked_down,
      lockdown_reason: sc.lockdown_reason,
    },
    features: {
      profiles: sc.cross_tenant_profiles_enabled,
      messaging: sc.cross_tenant_messaging_enabled,
      transactions: sc.cross_tenant_transactions_enabled,
      listings: sc.cross_tenant_listings_enabled,
      events: sc.cross_tenant_events_enabled,
      groups: sc.cross_tenant_groups_enabled,
    },
  };
}

export default function FederationSystemControls() {
  usePageTitle('System Controls');
  const toast = useToast();
  const [controls, setControls] = useState<SystemControls>({
    federation_enabled: false,
    whitelist_mode: false,
    max_level: 4,
    lockdown_active: false,
  });
  const [features, setFeatures] = useState<FeatureToggles>({
    profiles: false,
    messaging: false,
    transactions: false,
    listings: false,
    events: false,
    groups: false,
  });
  const [lockdownReason, setLockdownReason] = useState('');
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    const res = await adminSuper.getSystemControls();
    if (res.success && res.data) {
      const mapped = mapApiToLocal(res.data);
      setControls(mapped.controls);
      setFeatures(mapped.features);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const handleTriggerLockdown = async () => {
    if (!lockdownReason.trim()) {
      toast.error('Please provide a reason for the lockdown');
      return;
    }

    const res = await adminSuper.emergencyLockdown(lockdownReason);
    if (res.success) {
      setControls(prev => ({ ...prev, lockdown_active: true, lockdown_reason: lockdownReason }));
      toast.success('Emergency lockdown activated');
      setLockdownReason('');
    } else {
      toast.error(res.error || 'Failed to activate lockdown');
    }
  };

  const handleLiftLockdown = async () => {
    const res = await adminSuper.liftLockdown();
    if (res.success) {
      setControls(prev => ({ ...prev, lockdown_active: false, lockdown_reason: undefined }));
      toast.success('Emergency lockdown lifted');
    } else {
      toast.error(res.error || 'Failed to lift lockdown');
    }
  };

  const handleToggleControl = async (key: keyof SystemControls, value: boolean | number) => {
    const apiKeyMap: Record<string, string> = {
      federation_enabled: 'federation_enabled',
      whitelist_mode: 'whitelist_mode_enabled',
      max_level: 'max_federation_level',
    };
    const apiKey = apiKeyMap[key];
    if (!apiKey) return;

    const res = await adminSuper.updateSystemControls({ [apiKey]: value });
    if (res.success) {
      setControls(prev => ({ ...prev, [key]: value }));
      toast.success('System control updated');
    } else {
      toast.error(res.error || 'Failed to update control');
    }
  };

  const handleToggleFeature = async (key: keyof FeatureToggles, value: boolean) => {
    const apiKey = `cross_tenant_${key}_enabled`;
    const res = await adminSuper.updateSystemControls({ [apiKey]: value });
    if (res.success) {
      setFeatures(prev => ({ ...prev, [key]: value }));
      toast.success(`${key} feature ${value ? 'enabled' : 'disabled'}`);
    } else {
      toast.error(res.error || 'Failed to update feature');
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="System Controls"
        description="Master kill switches and emergency controls"
      />

      {/* Emergency Controls */}
      <Card className={`border-2 ${controls.lockdown_active ? 'border-danger' : 'border-warning'}`}>
        <CardHeader>
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <Lock className="w-5 h-5 text-danger" />
            Emergency Controls
          </h3>
        </CardHeader>
        <CardBody className="space-y-4">
          {controls.lockdown_active ? (
            <div className="space-y-3">
              <div className="p-4 rounded-lg bg-danger-50 dark:bg-danger-950 border border-danger">
                <p className="font-semibold text-danger">Lockdown Active</p>
                <p className="text-sm text-danger-600 dark:text-danger-400 mt-1">
                  {controls.lockdown_reason || 'No reason provided'}
                </p>
              </div>
              <Button
                color="danger"
                variant="solid"
                onPress={handleLiftLockdown}
                fullWidth
              >
                Lift Emergency Lockdown
              </Button>
            </div>
          ) : (
            <div className="space-y-3">
              <Input
                label="Lockdown Reason"
                placeholder="Enter reason for emergency lockdown"
                value={lockdownReason}
                onValueChange={setLockdownReason}
                variant="bordered"
                description="This will immediately disable all federation features"
              />
              <Button
                color="danger"
                variant="solid"
                onPress={handleTriggerLockdown}
                fullWidth
              >
                Trigger Emergency Lockdown
              </Button>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Master Kill Switch */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <Shield className="w-5 h-5 text-primary" />
            Master Kill Switch
          </h3>
        </CardHeader>
        <CardBody className="space-y-6">
          {/* Federation Enabled */}
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Federation Enabled</p>
              <p className="text-sm text-default-500">
                Enable or disable the entire federation system
              </p>
            </div>
            <Switch
              isSelected={controls.federation_enabled}
              onValueChange={(v) => handleToggleControl('federation_enabled', v)}
            />
          </div>

          {/* Whitelist Mode */}
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Whitelist Mode</p>
              <p className="text-sm text-default-500">
                Only allow whitelisted tenants to use federation
              </p>
            </div>
            <Switch
              isSelected={controls.whitelist_mode}
              onValueChange={(v) => handleToggleControl('whitelist_mode', v)}
            />
          </div>

          {/* Max Level */}
          <div>
            <Select
              label="Maximum Partnership Level"
              selectedKeys={[controls.max_level.toString()]}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0];
                if (selected) handleToggleControl('max_level', parseInt(String(selected)));
              }}
              variant="bordered"
              description="Highest level allowed for new partnerships"
            >
              <SelectItem key="1">Level 1 - Profiles Only</SelectItem>
              <SelectItem key="2">Level 2 - Profiles + Messaging</SelectItem>
              <SelectItem key="3">Level 3 - Profiles + Messaging + Transactions</SelectItem>
              <SelectItem key="4">Level 4 - Full Integration</SelectItem>
            </Select>
          </div>
        </CardBody>
      </Card>

      {/* Cross-Tenant Features */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">Cross-Tenant Features</h3>
        </CardHeader>
        <CardBody className="space-y-4">
          {[
            { key: 'profiles', label: 'Profile Sharing', description: 'Allow viewing profiles across communities' },
            { key: 'messaging', label: 'Cross-Community Messaging', description: 'Enable messaging between communities' },
            { key: 'transactions', label: 'Cross-Community Transactions', description: 'Allow time credit transfers between communities' },
            { key: 'listings', label: 'Listing Discovery', description: 'Show listings from partner communities' },
            { key: 'events', label: 'Event Sharing', description: 'Share events across communities' },
            { key: 'groups', label: 'Group Federation', description: 'Enable cross-community groups' },
          ].map(feature => (
            <div key={feature.key} className="flex items-center justify-between">
              <div>
                <p className="font-medium">{feature.label}</p>
                <p className="text-sm text-default-500">{feature.description}</p>
              </div>
              <Switch
                isSelected={features[feature.key as keyof FeatureToggles]}
                onValueChange={(v) => handleToggleFeature(feature.key as keyof FeatureToggles, v)}
              />
            </div>
          ))}
        </CardBody>
      </Card>
    </div>
  );
}
