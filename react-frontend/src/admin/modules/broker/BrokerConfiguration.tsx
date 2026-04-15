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

import { useTranslation } from 'react-i18next';
export default function BrokerConfiguration() {
  const { t } = useTranslation('admin');
  usePageTitle(t('broker.page_title'));
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
      toast.error(t('broker.failed_to_load_broker_configuration'));
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
        toast.success(t('broker.configuration_saved'));
      } else {
        toast.error(t('broker.failed_to_save_configuration'));
      }
    } catch {
      toast.error(t('broker.failed_to_save_configuration'));
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
        title={t('broker.broker_configuration_title')}
        description={t('broker.broker_configuration_desc')}
        actions={
          <div className="flex gap-2">
            <Button
              as={Link}
              to={tenantPath('/admin/broker-controls')}
              variant="flat"
              startContent={<ArrowLeft className="w-4 h-4" />}
              size="sm"
            >
              {t('broker.btn_back')}
            </Button>
            <Button
              color="primary"
              startContent={<Save className="w-4 h-4" />}
              onPress={handleSave}
              isLoading={saving}
              size="sm"
            >
              {t('broker.btn_save_changes')}
            </Button>
          </div>
        }
      />

      {/* Messaging Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('broker.messaging_oversight_heading')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.enable_broker_messaging')}</p>
              <p className="text-sm text-default-500">{t('broker.enable_broker_messaging_desc')}</p>
            </div>
            <Switch
              isSelected={config.broker_messaging_enabled}
              onValueChange={v => updateConfig('broker_messaging_enabled', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.copy_all_messages')}</p>
              <p className="text-sm text-default-500">{t('broker.copy_all_messages_desc')}</p>
            </div>
            <Switch
              isSelected={config.broker_copy_all_messages}
              onValueChange={v => updateConfig('broker_copy_all_messages', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.copy_threshold_hours')}</p>
              <p className="text-sm text-default-500">{t('broker.copy_threshold_hours_desc')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('broker.label_copy_threshold_hours')}
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
              <p className="font-medium">{t('broker.new_member_monitoring_days')}</p>
              <p className="text-sm text-default-500">{t('broker.new_member_monitoring_days_desc')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('broker.label_new_member_monitoring_days')}
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
              <p className="font-medium">{t('broker.require_exchange_for_listings')}</p>
              <p className="text-sm text-default-500">{t('broker.require_exchange_for_listings_desc')}</p>
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
          <h3 className="text-lg font-semibold">{t('broker.risk_tagging_heading')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.enable_risk_tagging')}</p>
              <p className="text-sm text-default-500">{t('broker.enable_risk_tagging_desc')}</p>
            </div>
            <Switch
              isSelected={config.risk_tagging_enabled}
              onValueChange={v => updateConfig('risk_tagging_enabled', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.auto_flag_high_risk')}</p>
              <p className="text-sm text-default-500">{t('broker.auto_flag_high_risk_desc')}</p>
            </div>
            <Switch
              isSelected={config.auto_flag_high_risk}
              onValueChange={v => updateConfig('auto_flag_high_risk', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.require_approval_high_risk')}</p>
              <p className="text-sm text-default-500">{t('broker.require_approval_high_risk_desc')}</p>
            </div>
            <Switch
              isSelected={config.require_approval_high_risk}
              onValueChange={v => updateConfig('require_approval_high_risk', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.notify_on_high_risk_match')}</p>
              <p className="text-sm text-default-500">{t('broker.notify_on_high_risk_match_desc')}</p>
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
          <h3 className="text-lg font-semibold">{t('broker.exchange_workflow_heading')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.broker_approval_required')}</p>
              <p className="text-sm text-default-500">{t('broker.broker_approval_required_desc')}</p>
            </div>
            <Switch
              isSelected={config.broker_approval_required}
              onValueChange={v => updateConfig('broker_approval_required', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.auto_approve_low_risk')}</p>
              <p className="text-sm text-default-500">{t('broker.auto_approve_low_risk_desc')}</p>
            </div>
            <Switch
              isSelected={config.auto_approve_low_risk}
              onValueChange={v => updateConfig('auto_approve_low_risk', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.exchange_timeout_days')}</p>
              <p className="text-sm text-default-500">{t('broker.exchange_timeout_days_desc')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('broker.label_exchange_timeout_days')}
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
              <p className="font-medium">{t('broker.max_hours_without_approval')}</p>
              <p className="text-sm text-default-500">{t('broker.max_hours_without_approval_desc')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('broker.label_max_hours_without_approval')}
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
              <p className="font-medium">{t('broker.confirmation_deadline_hours')}</p>
              <p className="text-sm text-default-500">{t('broker.confirmation_deadline_hours_desc')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('broker.label_confirmation_deadline_hours')}
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
              <p className="font-medium">{t('broker.request_expiry_hours')}</p>
              <p className="text-sm text-default-500">{t('broker.request_expiry_hours_desc')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('broker.label_expiry_hours')}
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
              <p className="font-medium">{t('broker.allow_hour_adjustment')}</p>
              <p className="text-sm text-default-500">{t('broker.allow_hour_adjustment_desc')}</p>
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
                  <p className="font-medium">{t('broker.max_hour_variance_percent')}</p>
                  <p className="text-sm text-default-500">{t('broker.max_hour_variance_percent_desc')}</p>
                </div>
                <Input
                  type="number"
                  aria-label={t('broker.label_max_hour_variance_percent')}
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
          <h3 className="text-lg font-semibold">{t('broker.broker_visibility_heading')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.visible_to_members')}</p>
              <p className="text-sm text-default-500">{t('broker.visible_to_members_desc')}</p>
            </div>
            <Switch
              isSelected={config.broker_visible_to_members}
              onValueChange={v => updateConfig('broker_visible_to_members', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.show_broker_name')}</p>
              <p className="text-sm text-default-500">{t('broker.show_broker_name_desc')}</p>
            </div>
            <Switch
              isSelected={config.show_broker_name}
              onValueChange={v => updateConfig('show_broker_name', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.broker_contact_email')}</p>
              <p className="text-sm text-default-500">{t('broker.broker_contact_email_desc')}</p>
            </div>
            <Input
              type="email"
              aria-label={t('broker.label_broker_contact_email')}
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
          <h3 className="text-lg font-semibold">{t('broker.message_copy_rules_heading')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.copy_first_contact')}</p>
              <p className="text-sm text-default-500">{t('broker.copy_first_contact_desc')}</p>
            </div>
            <Switch
              isSelected={config.copy_first_contact}
              onValueChange={v => updateConfig('copy_first_contact', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.copy_new_member_messages')}</p>
              <p className="text-sm text-default-500">{t('broker.copy_new_member_messages_desc')}</p>
            </div>
            <Switch
              isSelected={config.copy_new_member_messages}
              onValueChange={v => updateConfig('copy_new_member_messages', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.copy_high_risk_listing_messages')}</p>
              <p className="text-sm text-default-500">{t('broker.copy_high_risk_listing_messages_desc')}</p>
            </div>
            <Switch
              isSelected={config.copy_high_risk_listing_messages}
              onValueChange={v => updateConfig('copy_high_risk_listing_messages', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('broker.random_sample_percent')}</p>
              <p className="text-sm text-default-500">{t('broker.random_sample_percent_desc')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('broker.label_random_sample_percentage')}
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
              <p className="font-medium">{t('broker.retention_days')}</p>
              <p className="text-sm text-default-500">{t('broker.retention_days_desc')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('broker.label_retention_days')}
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
          <h3 className="text-lg font-semibold">{t('broker.compliance_safeguarding_heading')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="gap-4">
          <div className="flex justify-between items-center">
            <div>
              <p className="font-medium">{t('broker.enable_vetting_system')}</p>
              <p className="text-sm text-default-400">{t('broker.enable_vetting_system_desc')}</p>
            </div>
            <Switch
              isSelected={config.vetting_enabled}
              onValueChange={v => updateConfig('vetting_enabled', v)}
              size="sm"
            />
          </div>
          <div className="flex justify-between items-center">
            <div>
              <p className="font-medium">{t('broker.enable_insurance_certificates')}</p>
              <p className="text-sm text-default-400">{t('broker.enable_insurance_certificates_desc')}</p>
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
              <p className="font-medium">{t('broker.enforce_vetting_on_exchanges')}</p>
              <p className="text-sm text-default-400">{t('broker.enforce_vetting_on_exchanges_desc')}</p>
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
              <p className="font-medium">{t('broker.enforce_insurance_on_exchanges')}</p>
              <p className="text-sm text-default-400">{t('broker.enforce_insurance_on_exchanges_desc')}</p>
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
            <p className="text-sm text-default-600">{t('broker.vetting_expiry_warning_days')}:</p>
            <Input
              type="number"
              variant="bordered"
              aria-label={t('broker.label_vetting_expiry_warning_days')}
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
            <p className="text-sm text-default-600">{t('broker.insurance_expiry_warning_days')}:</p>
            <Input
              type="number"
              variant="bordered"
              aria-label={t('broker.label_insurance_expiry_warning_days')}
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
