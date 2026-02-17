/**
 * Broker Configuration
 * Configure broker controls, messaging oversight, and risk settings.
 * Parity: PHP BrokerControlsController::configuration()
 */

import { useState, useEffect } from 'react';
import { Card, CardHeader, CardBody, Button, Input, Switch, Divider, Spinner } from '@heroui/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Link } from 'react-router-dom';
import { adminBroker } from '../../api/adminApi';
import type { BrokerConfig } from '../../api/types';
import { PageHeader } from '../../components/PageHeader';
import { useTenant, useToast } from '@/contexts';

export default function BrokerConfiguration() {
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [config, setConfig] = useState<BrokerConfig>({
    broker_messaging_enabled: true,
    broker_copy_all_messages: false,
    broker_copy_threshold_hours: 5,
    risk_tagging_enabled: true,
    auto_flag_high_risk: true,
    require_approval_high_risk: false,
    broker_approval_required: true,
    auto_approve_low_risk: false,
    exchange_timeout_days: 7,
    broker_visible_to_members: false,
    show_broker_name: false,
    broker_contact_email: '',
  });

  useEffect(() => {
    loadConfig();
  }, []);

  async function loadConfig() {
    setLoading(true);
    try {
      const res = await adminBroker.getConfiguration();
      if (res.success && res.data) {
        setConfig(res.data);
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }

  async function handleSave() {
    setSaving(true);
    try {
      const res = await adminBroker.saveConfiguration(config);
      if (res.success) {
        toast.success('Configuration saved');
      } else {
        toast.error('Failed to save configuration');
      }
    } catch {
      toast.error('Failed to save configuration');
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
        title="Broker Configuration"
        description="Configure broker controls, messaging oversight, and risk settings"
        actions={
          <div className="flex gap-2">
            <Button
              as={Link}
              to={tenantPath('/admin/broker-controls')}
              variant="flat"
              startContent={<ArrowLeft className="w-4 h-4" />}
              size="sm"
            >
              Back
            </Button>
            <Button
              color="primary"
              startContent={<Save className="w-4 h-4" />}
              onPress={handleSave}
              isLoading={saving}
              size="sm"
            >
              Save Changes
            </Button>
          </div>
        }
      />

      {/* Messaging Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">Messaging Oversight</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Enable Broker Messaging</p>
              <p className="text-sm text-default-500">Allow brokers to review message copies</p>
            </div>
            <Switch
              isSelected={config.broker_messaging_enabled}
              onValueChange={v => updateConfig('broker_messaging_enabled', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Copy All Messages</p>
              <p className="text-sm text-default-500">Send all messages to broker inbox (not just high-risk)</p>
            </div>
            <Switch
              isSelected={config.broker_copy_all_messages}
              onValueChange={v => updateConfig('broker_copy_all_messages', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Copy Threshold (hours)</p>
              <p className="text-sm text-default-500">Copy messages for exchanges above this hour value</p>
            </div>
            <Input
              type="number"
              value={String(config.broker_copy_threshold_hours)}
              onValueChange={v => updateConfig('broker_copy_threshold_hours', parseInt(v) || 0)}
              className="w-24"
              min={0}
              max={100}
              size="sm"
            />
          </div>
        </CardBody>
      </Card>

      {/* Risk Tagging Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">Risk Tagging</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Enable Risk Tagging</p>
              <p className="text-sm text-default-500">Allow brokers to tag listings with risk levels</p>
            </div>
            <Switch
              isSelected={config.risk_tagging_enabled}
              onValueChange={v => updateConfig('risk_tagging_enabled', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Auto-Flag High Risk</p>
              <p className="text-sm text-default-500">Automatically flag exchanges involving high-risk listings</p>
            </div>
            <Switch
              isSelected={config.auto_flag_high_risk}
              onValueChange={v => updateConfig('auto_flag_high_risk', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Require Approval for High Risk</p>
              <p className="text-sm text-default-500">Mandate broker approval for high-risk exchanges</p>
            </div>
            <Switch
              isSelected={config.require_approval_high_risk}
              onValueChange={v => updateConfig('require_approval_high_risk', v)}
            />
          </div>
        </CardBody>
      </Card>

      {/* Exchange Workflow Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">Exchange Workflow</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Broker Approval Required</p>
              <p className="text-sm text-default-500">All exchanges must pass broker review before proceeding</p>
            </div>
            <Switch
              isSelected={config.broker_approval_required}
              onValueChange={v => updateConfig('broker_approval_required', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Auto-Approve Low Risk</p>
              <p className="text-sm text-default-500">Automatically approve exchanges with no risk tags</p>
            </div>
            <Switch
              isSelected={config.auto_approve_low_risk}
              onValueChange={v => updateConfig('auto_approve_low_risk', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Exchange Timeout (days)</p>
              <p className="text-sm text-default-500">Auto-expire pending exchanges after this many days</p>
            </div>
            <Input
              type="number"
              value={String(config.exchange_timeout_days)}
              onValueChange={v => updateConfig('exchange_timeout_days', parseInt(v) || 7)}
              className="w-24"
              min={1}
              max={90}
              size="sm"
            />
          </div>
        </CardBody>
      </Card>

      {/* Broker Visibility Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">Broker Visibility</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Visible to Members</p>
              <p className="text-sm text-default-500">Show broker information on member-facing pages</p>
            </div>
            <Switch
              isSelected={config.broker_visible_to_members}
              onValueChange={v => updateConfig('broker_visible_to_members', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Show Broker Name</p>
              <p className="text-sm text-default-500">Display broker name when members are notified of reviews</p>
            </div>
            <Switch
              isSelected={config.show_broker_name}
              onValueChange={v => updateConfig('show_broker_name', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Broker Contact Email</p>
              <p className="text-sm text-default-500">Email shown to members for broker enquiries</p>
            </div>
            <Input
              type="email"
              value={config.broker_contact_email}
              onValueChange={v => updateConfig('broker_contact_email', v)}
              placeholder="broker@example.com"
              className="w-64"
              size="sm"
            />
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
