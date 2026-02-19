// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Input,
  Select,
  SelectItem,
} from '@heroui/react';
import { Lock, Shield } from 'lucide-react';
import PageHeader from '../../../components/PageHeader';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';

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

  useEffect(() => {
    // TODO: Replace with adminApi.getFederationStats()
    setLoading(false);
  }, []);

  const handleTriggerLockdown = async () => {
    if (!lockdownReason.trim()) {
      toast.error('Please provide a reason for the lockdown');
      return;
    }

    // TODO: Replace with adminApi.triggerLockdown(lockdownReason)
    setControls(prev => ({ ...prev, lockdown_active: true, lockdown_reason: lockdownReason }));
    toast.success('Emergency lockdown activated');
    setLockdownReason('');
  };

  const handleLiftLockdown = async () => {
    // TODO: Replace with adminApi.liftLockdown()
    setControls(prev => ({ ...prev, lockdown_active: false, lockdown_reason: undefined }));
    toast.success('Emergency lockdown lifted');
  };

  const handleToggleControl = async (key: keyof SystemControls, value: boolean | number) => {
    // TODO: Replace with adminApi.updateSystemControl(key, value)
    setControls(prev => ({ ...prev, [key]: value }));
    toast.success('System control updated');
  };

  const handleToggleFeature = async (key: keyof FeatureToggles, value: boolean) => {
    // TODO: Replace with adminApi.updateSystemControl(`feature_${key}`, value)
    setFeatures(prev => ({ ...prev, [key]: value }));
    toast.success(`${key} feature ${value ? 'enabled' : 'disabled'}`);
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
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={controls.federation_enabled}
                onChange={(e) => handleToggleControl('federation_enabled', e.target.checked)}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-default-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-default-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-default-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-default-600 peer-checked:bg-success-600"></div>
            </label>
          </div>

          {/* Whitelist Mode */}
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Whitelist Mode</p>
              <p className="text-sm text-default-500">
                Only allow whitelisted tenants to use federation
              </p>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={controls.whitelist_mode}
                onChange={(e) => handleToggleControl('whitelist_mode', e.target.checked)}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-default-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-default-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-default-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-default-600 peer-checked:bg-success-600"></div>
            </label>
          </div>

          {/* Max Level */}
          <div>
            <Select
              label="Maximum Partnership Level"
              selectedKeys={[controls.max_level.toString()]}
              onChange={(e) => handleToggleControl('max_level', parseInt(e.target.value))}
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
              <label className="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  checked={features[feature.key as keyof FeatureToggles]}
                  onChange={(e) => handleToggleFeature(feature.key as keyof FeatureToggles, e.target.checked)}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-default-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-default-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-default-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-default-600 peer-checked:bg-success-600"></div>
              </label>
            </div>
          ))}
        </CardBody>
      </Card>
    </div>
  );
}
