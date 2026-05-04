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
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Save from 'lucide-react/icons/save';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { adminBroker } from '@/admin/api/adminApi';
import type { BrokerConfig } from '@/admin/api/types';
import { PageHeader } from '@/admin/components/PageHeader';
import { useAuth, useTenant, useToast } from '@/contexts';

const ADMIN_ONLY_CONFIG_KEYS = [
  'broker_messaging_enabled',
  'broker_copy_all_messages',
  'require_exchange_for_listings',
  'risk_tagging_enabled',
  'auto_flag_high_risk',
  'require_approval_high_risk',
  'notify_on_high_risk_match',
  'broker_approval_required',
  'auto_approve_low_risk',
  'max_hours_without_approval',
  'vetting_enabled',
  'insurance_enabled',
  'enforce_vetting_on_exchanges',
  'enforce_insurance_on_exchanges',
] as const satisfies readonly (keyof BrokerConfig)[];

export default function BrokerConfiguration() {
  const { t } = useTranslation('broker');
  usePageTitle(t('configuration.page_title'));
  const { tenantPath } = useTenant();
  const { user } = useAuth();
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

  const role = (user?.role as string) || '';
  const userRecord = user as Record<string, unknown> | null;
  const isAdminTier =
    role === 'admin' ||
    role === 'tenant_admin' ||
    role === 'super_admin' ||
    role === 'god' ||
    userRecord?.is_admin === true ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true ||
    userRecord?.is_god === true;

  const canEditKey = (key: keyof BrokerConfig) =>
    isAdminTier || !ADMIN_ONLY_CONFIG_KEYS.includes(key as (typeof ADMIN_ONLY_CONFIG_KEYS)[number]);

  const loadConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getConfiguration();
      if (res.success && res.data) {
        setConfig(res.data);
      }
    } catch {
      toast.error(t('configuration.load_failed'));
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
      const payload = isAdminTier
        ? config
        : (Object.fromEntries(
            Object.entries(config).filter(([key]) => canEditKey(key as keyof BrokerConfig))
          ) as Partial<BrokerConfig>);

      const res = await adminBroker.saveConfiguration(payload);
      if (res.success) {
        if (res.data) {
          setConfig(prev => ({ ...prev, ...res.data }));
        }
        toast.success(t('configuration.save_success'));
      } else {
        toast.error(t('configuration.save_failed'));
      }
    } catch {
      toast.error(t('configuration.save_failed'));
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
        title={t('configuration.title')}
        description={t('configuration.description')}
        actions={
          <div className="flex gap-2">
            <Button
              as={Link}
              to={tenantPath('/broker')}
              variant="flat"
              startContent={<ArrowLeft className="w-4 h-4" />}
              size="sm"
            >
              {t('configuration.back')}
            </Button>
            <Button
              color="primary"
              startContent={<Save className="w-4 h-4" />}
              onPress={handleSave}
              isLoading={saving}
              size="sm"
            >
              {t('configuration.save_changes')}
            </Button>
          </div>
        }
      />

      {!isAdminTier && (
        <Card shadow="sm" className="border border-warning-200 bg-warning-50/60">
          <CardBody className="py-3">
            <p className="text-sm font-medium text-warning-700">
              {t('configuration.limited_access_title')}
            </p>
            <p className="text-sm text-default-600">
              {t('configuration.limited_access_body')}
            </p>
          </CardBody>
        </Card>
      )}

      {/* Messaging Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('configuration.section_messaging')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_broker_messaging_enabled_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_broker_messaging_enabled_help')}</p>
            </div>
            <Switch
              isSelected={config.broker_messaging_enabled}
              onValueChange={v => updateConfig('broker_messaging_enabled', v)}
              isDisabled={!canEditKey('broker_messaging_enabled')}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_broker_copy_all_messages_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_broker_copy_all_messages_help')}</p>
            </div>
            <Switch
              isSelected={config.broker_copy_all_messages}
              onValueChange={v => updateConfig('broker_copy_all_messages', v)}
              isDisabled={!canEditKey('broker_copy_all_messages')}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_broker_copy_threshold_hours_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_broker_copy_threshold_hours_help')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('configuration.field_broker_copy_threshold_hours_aria')}
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
              <p className="font-medium">{t('configuration.field_new_member_monitoring_days_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_new_member_monitoring_days_help')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('configuration.field_new_member_monitoring_days_aria')}
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
              <p className="font-medium">{t('configuration.field_require_exchange_for_listings_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_require_exchange_for_listings_help')}</p>
            </div>
            <Switch
              isSelected={config.require_exchange_for_listings}
              onValueChange={v => updateConfig('require_exchange_for_listings', v)}
              isDisabled={!canEditKey('require_exchange_for_listings')}
            />
          </div>
        </CardBody>
      </Card>

      {/* Risk Tagging Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('configuration.section_risk_tagging')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_risk_tagging_enabled_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_risk_tagging_enabled_help')}</p>
            </div>
            <Switch
              isSelected={config.risk_tagging_enabled}
              onValueChange={v => updateConfig('risk_tagging_enabled', v)}
              isDisabled={!canEditKey('risk_tagging_enabled')}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_auto_flag_high_risk_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_auto_flag_high_risk_help')}</p>
            </div>
            <Switch
              isSelected={config.auto_flag_high_risk}
              onValueChange={v => updateConfig('auto_flag_high_risk', v)}
              isDisabled={!canEditKey('auto_flag_high_risk')}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_require_approval_high_risk_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_require_approval_high_risk_help')}</p>
            </div>
            <Switch
              isSelected={config.require_approval_high_risk}
              onValueChange={v => updateConfig('require_approval_high_risk', v)}
              isDisabled={!canEditKey('require_approval_high_risk')}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_notify_on_high_risk_match_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_notify_on_high_risk_match_help')}</p>
            </div>
            <Switch
              isSelected={config.notify_on_high_risk_match}
              onValueChange={v => updateConfig('notify_on_high_risk_match', v)}
              isDisabled={!canEditKey('notify_on_high_risk_match')}
            />
          </div>
        </CardBody>
      </Card>

      {/* Exchange Workflow Settings */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('configuration.section_exchange_workflow')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_broker_approval_required_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_broker_approval_required_help')}</p>
            </div>
            <Switch
              isSelected={config.broker_approval_required}
              onValueChange={v => updateConfig('broker_approval_required', v)}
              isDisabled={!canEditKey('broker_approval_required')}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_auto_approve_low_risk_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_auto_approve_low_risk_help')}</p>
            </div>
            <Switch
              isSelected={config.auto_approve_low_risk}
              onValueChange={v => updateConfig('auto_approve_low_risk', v)}
              isDisabled={!canEditKey('auto_approve_low_risk')}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_exchange_timeout_days_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_exchange_timeout_days_help')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('configuration.field_exchange_timeout_days_aria')}
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
              <p className="font-medium">{t('configuration.field_max_hours_without_approval_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_max_hours_without_approval_help')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('configuration.field_max_hours_without_approval_aria')}
              value={String(config.max_hours_without_approval)}
              onValueChange={v => updateConfig('max_hours_without_approval', v === '' ? 0 : parseFloat(v))}
              className="w-24"
              min={0}
              max={24}
              step={0.5}
              size="sm"
              isDisabled={!canEditKey('max_hours_without_approval')}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_confirmation_deadline_hours_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_confirmation_deadline_hours_help')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('configuration.field_confirmation_deadline_hours_aria')}
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
              <p className="font-medium">{t('configuration.field_expiry_hours_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_expiry_hours_help')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('configuration.field_expiry_hours_aria')}
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
              <p className="font-medium">{t('configuration.field_allow_hour_adjustment_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_allow_hour_adjustment_help')}</p>
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
                  <p className="font-medium">{t('configuration.field_max_hour_variance_percent_label')}</p>
                  <p className="text-sm text-default-500">{t('configuration.field_max_hour_variance_percent_help')}</p>
                </div>
                <Input
                  type="number"
                  aria-label={t('configuration.field_max_hour_variance_percent_aria')}
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
          <h3 className="text-lg font-semibold">{t('configuration.section_broker_visibility')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_broker_visible_to_members_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_broker_visible_to_members_help')}</p>
            </div>
            <Switch
              isSelected={config.broker_visible_to_members}
              onValueChange={v => updateConfig('broker_visible_to_members', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_show_broker_name_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_show_broker_name_help')}</p>
            </div>
            <Switch
              isSelected={config.show_broker_name}
              onValueChange={v => updateConfig('show_broker_name', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_broker_contact_email_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_broker_contact_email_help')}</p>
            </div>
            <Input
              type="email"
              aria-label={t('configuration.field_broker_contact_email_aria')}
              value={config.broker_contact_email}
              onValueChange={v => updateConfig('broker_contact_email', v)}
              placeholder={t('configuration.field_broker_contact_email_placeholder')}
              className="w-64"
              size="sm"
            />
          </div>
        </CardBody>
      </Card>

      {/* Message Copy Rules */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">{t('configuration.section_message_copy_rules')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_copy_first_contact_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_copy_first_contact_help')}</p>
            </div>
            <Switch
              isSelected={config.copy_first_contact}
              onValueChange={v => updateConfig('copy_first_contact', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_copy_new_member_messages_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_copy_new_member_messages_help')}</p>
            </div>
            <Switch
              isSelected={config.copy_new_member_messages}
              onValueChange={v => updateConfig('copy_new_member_messages', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_copy_high_risk_listing_messages_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_copy_high_risk_listing_messages_help')}</p>
            </div>
            <Switch
              isSelected={config.copy_high_risk_listing_messages}
              onValueChange={v => updateConfig('copy_high_risk_listing_messages', v)}
            />
          </div>
          <Divider />
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">{t('configuration.field_random_sample_percentage_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_random_sample_percentage_help')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('configuration.field_random_sample_percentage_aria')}
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
              <p className="font-medium">{t('configuration.field_retention_days_label')}</p>
              <p className="text-sm text-default-500">{t('configuration.field_retention_days_help')}</p>
            </div>
            <Input
              type="number"
              aria-label={t('configuration.field_retention_days_aria')}
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
          <h3 className="text-lg font-semibold">{t('configuration.section_compliance_safeguarding')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="gap-4">
          <div className="flex justify-between items-center">
            <div>
              <p className="font-medium">{t('configuration.field_vetting_enabled_label')}</p>
              <p className="text-sm text-default-400">{t('configuration.field_vetting_enabled_help')}</p>
            </div>
            <Switch
              isSelected={config.vetting_enabled}
              onValueChange={v => updateConfig('vetting_enabled', v)}
              size="sm"
              isDisabled={!canEditKey('vetting_enabled')}
            />
          </div>
          <div className="flex justify-between items-center">
            <div>
              <p className="font-medium">{t('configuration.field_insurance_enabled_label')}</p>
              <p className="text-sm text-default-400">{t('configuration.field_insurance_enabled_help')}</p>
            </div>
            <Switch
              isSelected={config.insurance_enabled}
              onValueChange={v => updateConfig('insurance_enabled', v)}
              size="sm"
              isDisabled={!canEditKey('insurance_enabled')}
            />
          </div>
          <Divider />
          <div className="flex justify-between items-center">
            <div>
              <p className="font-medium">{t('configuration.field_enforce_vetting_on_exchanges_label')}</p>
              <p className="text-sm text-default-400">{t('configuration.field_enforce_vetting_on_exchanges_help')}</p>
            </div>
            <Switch
              isSelected={config.enforce_vetting_on_exchanges}
              onValueChange={v => updateConfig('enforce_vetting_on_exchanges', v)}
              size="sm"
              isDisabled={!config.vetting_enabled || !canEditKey('enforce_vetting_on_exchanges')}
            />
          </div>
          <div className="flex justify-between items-center">
            <div>
              <p className="font-medium">{t('configuration.field_enforce_insurance_on_exchanges_label')}</p>
              <p className="text-sm text-default-400">{t('configuration.field_enforce_insurance_on_exchanges_help')}</p>
            </div>
            <Switch
              isSelected={config.enforce_insurance_on_exchanges}
              onValueChange={v => updateConfig('enforce_insurance_on_exchanges', v)}
              size="sm"
              isDisabled={!config.insurance_enabled || !canEditKey('enforce_insurance_on_exchanges')}
            />
          </div>
          <Divider />
          <div className="flex items-center gap-3">
            <p className="text-sm text-default-600">{t('configuration.field_vetting_expiry_warning_days_label')}:</p>
            <Input
              type="number"
              variant="bordered"
              aria-label={t('configuration.field_vetting_expiry_warning_days_aria')}
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
            <p className="text-sm text-default-600">{t('configuration.field_insurance_expiry_warning_days_label')}:</p>
            <Input
              type="number"
              variant="bordered"
              aria-label={t('configuration.field_insurance_expiry_warning_days_aria')}
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
