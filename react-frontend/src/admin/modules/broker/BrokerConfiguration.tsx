// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Configuration
 * Configure broker controls, messaging oversight, and risk settings.
 * Parity: PHP BrokerControlsController::configuration()
 */

import { useState, useEffect, useCallback } from 'react';
import { Card, CardHeader, CardBody, Button, Input, Switch, Divider, Spinner } from '@heroui/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Link } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { adminBroker } from '../../api/adminApi';
import type { BrokerConfig } from '../../api/types';
import { PageHeader } from '../../components/PageHeader';
import { useTenant, useToast } from '@/contexts';

export default function BrokerConfiguration() {
  usePageTitle("Broker Controls");
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [config, setConfig] = useState<BrokerConfig>({
    broker_messaging_enabled: true,
    broker_copy_all_messages: false,
    broker_copy_threshold_hours: 5,
    new_member_monitoring_days: 30,
    require_exchange_for_listings: false,
    risk_tagging_enabled: true,
    auto_flag_high_risk: true,
    require_approval_high_risk: false,
    notify_on_high_risk_match: true,
    broker_approval_required: true,
    auto_approve_low_risk: false,
    exchange_timeout_days: 7,
    max_hours_without_approval: 5,
    confirmation_deadline_hours: 48,
    allow_hour_adjustment: false,
    max_hour_variance_percent: 20,
    expiry_hours: 168,
    broker_visible_to_members: false,
    show_broker_name: false,
    broker_contact_email: '',
    copy_first_contact: true,
    copy_new_member_messages: true,
    copy_high_risk_listing_messages: true,
    random_sample_percentage: 0,
    retention_days: 90,
    vetting_enabled: false,
    insurance_enabled: false,
    enforce_vetting_on_exchanges: false,
    enforce_insurance_on_exchanges: false,
    vetting_expiry_warning_days: 30,
    insurance_expiry_warning_days: 30,
  });

  const loadConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getConfiguration();
      if (res.success && res.data) {
        setConfig(res.data);
      }
    } catch {
      toast.error("Failed to load broker configuration");
    } finally {
      setLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    loadConfig();
  }, [loadConfig]);

  async function handleSave() {
    setSaving(true);
    try {
      const res = await adminBroker.saveConfiguration(config);
      if (res.success) {
        toast.success("Configuration Saved");
      } else {
        toast.error("Failed to save configuration");
      }
    } catch {
      toast.error("Failed to save configuration");
    } finally {
      setSaving(false);
    }
  }

  function updateConfig<K extends keyof BrokerConfig>(key: K, value: BrokerConfig[K]) {
    setConfig(prev => ({ ...prev, [key]: value }));
  }

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[300px]">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={"Broker Configuration"}
        description={"Configure broker settings, thresholds, and notification preferences"}
        actions={
          <div className="flex gap-2">
            <Button
              as={Link}
              to={tenantPath('/admin/broker-controls')}
              variant="flat"
              startContent={<ArrowLeft className="w-4 h-4" />}
              size="sm"
            >
              {"Back"}
            </Button>
            <Button
              color="primary"
              startContent={<Save className="w-4 h-4" />}
              onPress={handleSave}
              isLoading={saving}
              size="sm"
            >
              {"Save Changes"}
            </Button>
          </div>
        }
      />

      {/* Messaging Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{"Messaging Oversight"}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Enable Broker Messaging"}</p>
              <p className="text-sm text-default-500">{"Enable Broker Messaging."}</p>
            </div>
            <Switch
              isSelected={config.broker_messaging_enabled}
              onValueChange={v => updateConfig('broker_messaging_enabled', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Copy All Messages"}</p>
              <p className="text-sm text-default-500">{"Copy All Messages."}</p>
            </div>
            <Switch
              isSelected={config.broker_copy_all_messages}
              onValueChange={v => updateConfig('broker_copy_all_messages', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Copy Threshold Hours"}</p>
              <p className="text-sm text-default-500">{"Copy Threshold Hours."}</p>
            </div>
            <Input
              type="number"
              aria-label={"Copy Threshold Hours"}
              value={String(config.broker_copy_threshold_hours)}
              onValueChange={v => updateConfig('broker_copy_threshold_hours', parseInt(v) || 0)}
              className="w-24"
              min={0}
              max={100}
              size="sm"
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"New Member Monitoring Days"}</p>
              <p className="text-sm text-default-500">{"New Member Monitoring Days."}</p>
            </div>
            <Input
              type="number"
              aria-label={"New Member Monitoring Days"}
              value={String(config.new_member_monitoring_days)}
              onValueChange={v => updateConfig('new_member_monitoring_days', parseInt(v) || 0)}
              className="w-24"
              min={0}
              max={365}
              size="sm"
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Require Exchange for Listings"}</p>
              <p className="text-sm text-default-500">{"Require Exchange for Listings."}</p>
            </div>
            <Switch
              isSelected={config.require_exchange_for_listings}
              onValueChange={v => updateConfig('require_exchange_for_listings', v)}
            />
          </div>
        </CardBody>
      </Card>

      {/* Risk Tagging Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{"Risk Tagging"}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Enable Risk Tagging"}</p>
              <p className="text-sm text-default-500">{"Enable Risk Tagging."}</p>
            </div>
            <Switch
              isSelected={config.risk_tagging_enabled}
              onValueChange={v => updateConfig('risk_tagging_enabled', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Auto Flag High Risk"}</p>
              <p className="text-sm text-default-500">{"Auto Flag High Risk."}</p>
            </div>
            <Switch
              isSelected={config.auto_flag_high_risk}
              onValueChange={v => updateConfig('auto_flag_high_risk', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Require Approval High Risk"}</p>
              <p className="text-sm text-default-500">{"Require Approval High Risk."}</p>
            </div>
            <Switch
              isSelected={config.require_approval_high_risk}
              onValueChange={v => updateConfig('require_approval_high_risk', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Notify on High Risk Match"}</p>
              <p className="text-sm text-default-500">{"Notify on High Risk Match."}</p>
            </div>
            <Switch
              isSelected={config.notify_on_high_risk_match}
              onValueChange={v => updateConfig('notify_on_high_risk_match', v)}
            />
          </div>
        </CardBody>
      </Card>

      {/* Exchange Workflow Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{"Exchange Workflow"}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Broker Approval Required"}</p>
              <p className="text-sm text-default-500">{"Broker Approval Required."}</p>
            </div>
            <Switch
              isSelected={config.broker_approval_required}
              onValueChange={v => updateConfig('broker_approval_required', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Auto Approve Low Risk"}</p>
              <p className="text-sm text-default-500">{"Auto Approve Low Risk."}</p>
            </div>
            <Switch
              isSelected={config.auto_approve_low_risk}
              onValueChange={v => updateConfig('auto_approve_low_risk', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Exchange Timeout Days"}</p>
              <p className="text-sm text-default-500">{"Exchange Timeout Days."}</p>
            </div>
            <Input
              type="number"
              aria-label={"Exchange Timeout Days"}
              value={String(config.exchange_timeout_days)}
              onValueChange={v => updateConfig('exchange_timeout_days', parseInt(v) || 7)}
              className="w-24"
              min={1}
              max={90}
              size="sm"
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Max Hours Without Approval"}</p>
              <p className="text-sm text-default-500">{"Max Hours Without Approval."}</p>
            </div>
            <Input
              type="number"
              aria-label={"Max Hours Without Approval"}
              value={String(config.max_hours_without_approval)}
              onValueChange={v => updateConfig('max_hours_without_approval', v === '' ? 0 : parseFloat(v))}
              className="w-24"
              min={0}
              max={24}
              step={0.5}
              size="sm"
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Confirmation Deadline Hours"}</p>
              <p className="text-sm text-default-500">{"Confirmation Deadline Hours."}</p>
            </div>
            <Input
              type="number"
              aria-label={"Confirmation Deadline Hours"}
              value={String(config.confirmation_deadline_hours)}
              onValueChange={v => updateConfig('confirmation_deadline_hours', parseInt(v) || 48)}
              className="w-24"
              min={1}
              max={720}
              size="sm"
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Request Expiry Hours"}</p>
              <p className="text-sm text-default-500">{"Request Expiry Hours."}</p>
            </div>
            <Input
              type="number"
              aria-label={"Expiry Hours"}
              value={String(config.expiry_hours)}
              onValueChange={v => updateConfig('expiry_hours', parseInt(v) || 168)}
              className="w-24"
              min={1}
              max={720}
              size="sm"
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Allow Hour Adjustment"}</p>
              <p className="text-sm text-default-500">{"Allow Hour Adjustment."}</p>
            </div>
            <Switch
              isSelected={config.allow_hour_adjustment}
              onValueChange={v => updateConfig('allow_hour_adjustment', v)}
            />
          </div>
          {config.allow_hour_adjustment && (
            <>
              <Divider />
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium">{"Max Hour Variance Percent"}</p>
                  <p className="text-sm text-default-500">{"Max Hour Variance Percent."}</p>
                </div>
                <Input
                  type="number"
                  aria-label={"Max Hour Variance Percent"}
                  value={String(config.max_hour_variance_percent)}
                  onValueChange={v => updateConfig('max_hour_variance_percent', parseInt(v) || 0)}
                  className="w-24"
                  min={0}
                  max={100}
                  size="sm"
                />
              </div>
            </>
          )}
        </CardBody>
      </Card>

      {/* Broker Visibility Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{"Broker Visibility"}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Visible to Members"}</p>
              <p className="text-sm text-default-500">{"Visible to Members."}</p>
            </div>
            <Switch
              isSelected={config.broker_visible_to_members}
              onValueChange={v => updateConfig('broker_visible_to_members', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Show Broker Name"}</p>
              <p className="text-sm text-default-500">{"Show Broker Name."}</p>
            </div>
            <Switch
              isSelected={config.show_broker_name}
              onValueChange={v => updateConfig('show_broker_name', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Broker Contact Email"}</p>
              <p className="text-sm text-default-500">{"Broker Contact Email."}</p>
            </div>
            <Input
              type="email"
              aria-label={"Broker Contact Email"}
              value={config.broker_contact_email}
              onValueChange={v => updateConfig('broker_contact_email', v)}
              placeholder="broker@example.com"
              className="w-64"
              size="sm"
            />
          </div>
        </CardBody>
      </Card>

      {/* Message Copy Rules */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{"Message Copy Rules"}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Copy First Contact"}</p>
              <p className="text-sm text-default-500">{"Copy First Contact."}</p>
            </div>
            <Switch
              isSelected={config.copy_first_contact}
              onValueChange={v => updateConfig('copy_first_contact', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Copy New Member Messages"}</p>
              <p className="text-sm text-default-500">{"Copy New Member Messages."}</p>
            </div>
            <Switch
              isSelected={config.copy_new_member_messages}
              onValueChange={v => updateConfig('copy_new_member_messages', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Copy High Risk Listing Messages"}</p>
              <p className="text-sm text-default-500">{"Copy High Risk Listing Messages."}</p>
            </div>
            <Switch
              isSelected={config.copy_high_risk_listing_messages}
              onValueChange={v => updateConfig('copy_high_risk_listing_messages', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Random Sample Percent"}</p>
              <p className="text-sm text-default-500">{"Random Sample Percent."}</p>
            </div>
            <Input
              type="number"
              aria-label={"Random Sample Percentage"}
              value={String(config.random_sample_percentage)}
              onValueChange={v => updateConfig('random_sample_percentage', parseInt(v) || 0)}
              className="w-24"
              min={0}
              max={100}
              size="sm"
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{"Retention Days"}</p>
              <p className="text-sm text-default-500">{"Retention Days."}</p>
            </div>
            <Input
              type="number"
              aria-label={"Retention Days"}
              value={String(config.retention_days)}
              onValueChange={v => updateConfig('retention_days', parseInt(v) || 90)}
              className="w-24"
              min={1}
              max={3650}
              size="sm"
            />
          </div>
        </CardBody>
      </Card>

      {/* Compliance & Safeguarding */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{"Compliance Safeguarding"}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="gap-4">
          <div className="flex justify-between items-center">
            <div>
              <p className="font-medium">{"Enable Vetting System"}</p>
              <p className="text-sm text-default-400">{"Enable Vetting System."}</p>
            </div>
            <Switch
              isSelected={config.vetting_enabled}
              onValueChange={v => updateConfig('vetting_enabled', v)}
              size="sm"
            />
          </div>
          <div className="flex justify-between items-center">
            <div>
              <p className="font-medium">{"Enable Insurance Certificates"}</p>
              <p className="text-sm text-default-400">{"Enable Insurance Certificates."}</p>
            </div>
            <Switch
              isSelected={config.insurance_enabled}
              onValueChange={v => updateConfig('insurance_enabled', v)}
              size="sm"
            />
          </div>
          <Divider />
          <div className="flex justify-between items-center">
            <div>
              <p className="font-medium">{"Enforce Vetting on Exchanges"}</p>
              <p className="text-sm text-default-400">{"Enforce Vetting on Exchanges."}</p>
            </div>
            <Switch
              isSelected={config.enforce_vetting_on_exchanges}
              onValueChange={v => updateConfig('enforce_vetting_on_exchanges', v)}
              size="sm"
              isDisabled={!config.vetting_enabled}
            />
          </div>
          <div className="flex justify-between items-center">
            <div>
              <p className="font-medium">{"Enforce Insurance on Exchanges"}</p>
              <p className="text-sm text-default-400">{"Enforce Insurance on Exchanges."}</p>
            </div>
            <Switch
              isSelected={config.enforce_insurance_on_exchanges}
              onValueChange={v => updateConfig('enforce_insurance_on_exchanges', v)}
              size="sm"
              isDisabled={!config.insurance_enabled}
            />
          </div>
          <Divider />
          <div className="flex items-center gap-3">
            <p className="text-sm text-default-600">{"Vetting Expiry Warning Days"}:</p>
            <Input
              type="number"
              variant="bordered"
              aria-label={"Vetting Expiry Warning Days"}
              value={String(config.vetting_expiry_warning_days)}
              onValueChange={v => updateConfig('vetting_expiry_warning_days', parseInt(v) || 30)}
              className="w-24"
              min={1}
              max={365}
              size="sm"
              isDisabled={!config.vetting_enabled}
            />
          </div>
          <div className="flex items-center gap-3">
            <p className="text-sm text-default-600">{"Insurance Expiry Warning Days"}:</p>
            <Input
              type="number"
              variant="bordered"
              aria-label={"Insurance Expiry Warning Days"}
              value={String(config.insurance_expiry_warning_days)}
              onValueChange={v => updateConfig('insurance_expiry_warning_days', parseInt(v) || 30)}
              className="w-24"
              min={1}
              max={365}
              size="sm"
              isDisabled={!config.insurance_enabled}
            />
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
