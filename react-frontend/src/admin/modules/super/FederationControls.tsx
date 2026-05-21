// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Control Center
 * Super-admin hub for federation management: system status, feature toggles,
 * whitelist, partnerships, and quick links to sub-pages.
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import {
  Card, CardBody, CardHeader, Button, Switch, Chip, Divider, Input, Spinner,
  Code, Snippet, Accordion, AccordionItem,
} from '@heroui/react';
import Globe from 'lucide-react/icons/globe';
import Shield from 'lucide-react/icons/shield';
import Lock from 'lucide-react/icons/lock';
import Unlock from 'lucide-react/icons/lock-open';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Network from 'lucide-react/icons/network';
import Trash2 from 'lucide-react/icons/trash-2';
import Plus from 'lucide-react/icons/plus';
import Activity from 'lucide-react/icons/activity';
import ArrowRight from 'lucide-react/icons/arrow-right';
import ListChecks from 'lucide-react/icons/list-checks';
import Users from 'lucide-react/icons/users';
import Handshake from 'lucide-react/icons/handshake';
import KeyRound from 'lucide-react/icons/key-round';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader, ConfirmModal, StatCard } from '../../components';
import type { FederationSystemControls as FederationSystemControlsType, FederationWhitelistEntry, FederationPartnership } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function FederationControls() {
  const { t } = useTranslation('admin');
  usePageTitle(t('super.federation_controls_title'));
  const toast = useToast();
  const toastRef = useRef(toast);
  toastRef.current = toast;
  const { tenantPath } = useTenant();

  const [controls, setControls] = useState<FederationSystemControlsType | null>(null);
  const [whitelist, setWhitelist] = useState<FederationWhitelistEntry[]>([]);
  const [partnerships, setPartnerships] = useState<FederationPartnership[]>([]);
  const [jwtStatus, setJwtStatus] = useState<{ configured: boolean; issuer: string; key_bits: number; recommended_bits: number } | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState<string | null>(null);
  const [lockdownConfirm, setLockdownConfirm] = useState(false);
  const [lockdownReason, setLockdownReason] = useState('');
  const [addTenantId, setAddTenantId] = useState('');
  const [partnerAction, setPartnerAction] = useState<{ type: 'suspend' | 'terminate' | 'reactivate'; id: number } | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [ctrlRes, wlRes, pRes, jwtRes] = await Promise.all([
        adminSuper.getSystemControls(),
        adminSuper.getWhitelist(),
        adminSuper.getFederationPartnerships(),
        adminSuper.getFederationJwtStatus(),
      ]);
      if (ctrlRes.success && ctrlRes.data) setControls(ctrlRes.data);
      if (wlRes.success && wlRes.data) setWhitelist(Array.isArray(wlRes.data) ? wlRes.data : []);
      if (pRes.success && pRes.data) setPartnerships(Array.isArray(pRes.data) ? pRes.data : []);
      if (jwtRes.success && jwtRes.data) setJwtStatus(jwtRes.data);
    } catch {
      toastRef.current.error(t('super.federation_error'));
    }
    setLoading(false);
  }, [t])

  useEffect(() => { loadData(); }, [loadData]);

  const updateControl = async (key: string, value: boolean | number) => {
    setSaving(key);
    try {
      const res = await adminSuper.updateSystemControls({ [key]: value });
      if (res?.success) {
        setControls(prev => prev ? { ...prev, [key]: value } : prev);
      } else {
        toastRef.current.error(t('super.failed_to_update_setting'));
      }
    } catch {
      toastRef.current.error(t('super.failed_to_update_setting_detail'));
    } finally {
      setSaving(null);
    }
  };

  const handleLockdown = async () => {
    try {
      if (controls?.emergency_lockdown_active) {
        const res = await adminSuper.liftLockdown();
        if (res?.success) { toastRef.current.success(t('super.lockdown_lifted')); loadData(); }
        else toastRef.current.error(t('super.failed_to_lift_lockdown'));
      } else {
        if (!lockdownReason.trim()) {
          toastRef.current.error(t('super.please_provide_a_reason_for_the_lockdown'));
          return;
        }
        const res = await adminSuper.emergencyLockdown(lockdownReason);
        if (res?.success) { toastRef.current.success(t('super.lockdown_activated')); loadData(); }
        else toastRef.current.error(t('super.failed_to_activate_lockdown'));
      }
    } catch {
      toastRef.current.error(t('super.lockdown_action_failed_detail'));
    } finally {
      setLockdownConfirm(false);
    }
  };

  const handleAddWhitelist = async () => {
    if (!addTenantId) return;
    const res = await adminSuper.addToWhitelist(Number(addTenantId));
    if (res?.success) { toastRef.current.success(t('super.added_to_whitelist')); setAddTenantId(''); loadData(); }
    else toastRef.current.error(t('super.failed_to_add_to_whitelist'));
  };

  const handleRemoveWhitelist = async (tenantId: number) => {
    const res = await adminSuper.removeFromWhitelist(tenantId);
    if (res?.success) { toastRef.current.success(t('super.removed_from_whitelist')); loadData(); }
    else toastRef.current.error(t('super.failed_to_remove_from_whitelist'));
  };

  const handlePartnerAction = async () => {
    if (!partnerAction) return;
    let res;
    if (partnerAction.type === 'suspend') {
      res = await adminSuper.suspendPartnership(partnerAction.id, t('super.suspended_by_super_admin'));
    } else if (partnerAction.type === 'terminate') {
      res = await adminSuper.terminatePartnership(partnerAction.id, t('super.terminated_by_super_admin'));
    } else {
      res = await adminSuper.reactivatePartnership(partnerAction.id);
    }
    if (res?.success) {
      toastRef.current.success(
        partnerAction.type === 'suspend'
          ? t('super.partnership_suspended')
          : partnerAction.type === 'terminate'
          ? t('super.partnership_terminated')
          : t('super.partnership_reactivated')
      );
      loadData();
    } else {
      toastRef.current.error(t('super.action_failed'));
    }
    setPartnerAction(null);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Spinner size="lg" label={t('super.loading_federation_controls')} />
      </div>
    );
  }

  if (!controls) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] gap-4">
        <AlertTriangle size={48} className="text-warning" />
        <p className="text-lg text-default-500">{t('super.failed_to_load_federation_controls')}</p>
        <Button color="primary" onPress={loadData}>{t('super.retry')}</Button>
      </div>
    );
  }

  type BooleanControlKey = Exclude<{
    [K in keyof FederationSystemControlsType]: FederationSystemControlsType[K] extends boolean ? K : never;
  }[keyof FederationSystemControlsType], undefined>;

  const featureToggles: Array<{ key: BooleanControlKey; label: string; description: string }> = [
    { key: 'cross_tenant_profiles_enabled', label: t('super.toggle_profile_sharing'), description: t('super.toggle_profile_sharing_desc') },
    { key: 'cross_tenant_messaging_enabled', label: t('super.toggle_cross_messaging'), description: t('super.toggle_cross_messaging_desc') },
    { key: 'cross_tenant_transactions_enabled', label: t('super.toggle_cross_transactions'), description: t('super.toggle_cross_transactions_desc') },
    { key: 'cross_tenant_listings_enabled', label: t('super.toggle_listing_discovery'), description: t('super.toggle_listing_discovery_desc') },
    { key: 'cross_tenant_events_enabled', label: t('super.toggle_event_sharing'), description: t('super.toggle_event_sharing_desc') },
    { key: 'cross_tenant_groups_enabled', label: t('super.toggle_group_federation'), description: t('super.toggle_group_federation_desc') },
  ];

  const activePartnerships = partnerships.filter(p => p.status === 'active').length;
  const colorClasses: Record<string, { bg: string; text: string }> = {
    primary: { bg: 'bg-primary/10', text: 'text-primary' },
    success: { bg: 'bg-success/10', text: 'text-success' },
    secondary: { bg: 'bg-secondary/10', text: 'text-secondary' },
    warning: { bg: 'bg-warning/10', text: 'text-warning' },
    danger: { bg: 'bg-danger/10', text: 'text-danger' },
  };

  const partnershipStatusLabel = (status: string) => {
    switch (status) {
      case 'active':
        return t('super.status_active');
      case 'pending':
        return t('super.status_pending');
      case 'suspended':
        return t('super.status_suspended');
      case 'terminated':
        return t('super.status_terminated');
      default:
        return status;
    }
  };

  const quickLinks = [
    { label: t('super.link_whitelist'), description: t('super.link_whitelist_desc'), href: '/admin/super/federation/whitelist', icon: ListChecks, color: 'success' as const },
    { label: t('super.link_partnerships'), description: t('super.link_partnerships_desc'), href: '/admin/super/federation/partnerships', icon: Handshake, color: 'secondary' as const },
    { label: t('super.link_audit_log'), description: t('super.link_audit_log_desc'), href: '/admin/super/federation/audit', icon: Activity, color: 'warning' as const },
  ];

  return (
    <div className="space-y-6">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-1 text-sm text-default-500">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">{t('super.breadcrumb_super_admin')}</Link>
        <span>/</span>
        <span className="text-foreground font-medium">{t('super.breadcrumb_federation_controls')}</span>
      </nav>

      <PageHeader
        title={t('super.federation_controls_title')}
        description={t('super.federation_controls_desc')}
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label={t('super.label_federation_status')}
          value={controls.federation_enabled ? t('super.status_active') : t('super.status_disabled')}
          icon={Globe}
          color={controls.federation_enabled ? 'success' : 'danger'}
        />
        <StatCard
          label={t('super.label_whitelisted_tenants')}
          value={whitelist.length}
          icon={Shield}
          color="primary"
        />
        <StatCard
          label={t('super.label_active_partnerships')}
          value={activePartnerships}
          icon={Handshake}
          color="secondary"
        />
        <StatCard
          label={t('super.label_system_status')}
          value={controls.emergency_lockdown_active ? t('super.status_lockdown') : t('super.status_normal')}
          icon={controls.emergency_lockdown_active ? Lock : Unlock}
          color={controls.emergency_lockdown_active ? 'danger' : 'success'}
        />
      </div>

      {/* JWT Auth Configuration — platform-level setup that is easy to forget.
          Displayed prominently so operators notice when it is missing. */}
      <Card className={jwtStatus && !jwtStatus.configured ? 'border-2 border-warning' : ''}>
        <CardHeader className="flex items-center gap-3">
          <KeyRound size={20} className={jwtStatus?.configured ? 'text-success' : 'text-warning'} />
          <div className="flex-1">
            <p className="font-semibold">{t('super.jwt_auth_title')}</p>
            <p className="text-xs text-default-500">
              {t('super.jwt_auth_subtitle')}
            </p>
          </div>
          {jwtStatus ? (
            jwtStatus.configured ? (
              <Chip color="success" variant="flat" size="sm">{t('super.status_configured')}</Chip>
            ) : (
              <Chip color="warning" variant="flat" size="sm" startContent={<AlertTriangle size={14} />}>{t('super.status_not_configured')}</Chip>
            )
          ) : (
            <Chip variant="flat" size="sm">…</Chip>
          )}
        </CardHeader>
        <CardBody className="gap-3 text-sm">
          {jwtStatus?.configured && jwtStatus.key_bits < jwtStatus.recommended_bits && (
            <div className="rounded-md border border-warning bg-warning-50 dark:bg-warning-950 p-3 text-warning-700 dark:text-warning-300">
              <strong>{t('super.jwt_warn_weak_key')}</strong>{' '}
              {t('super.jwt_warn_weak_key_body')}
            </div>
          )}

          {!jwtStatus?.configured && (
            <div className="rounded-md border border-warning bg-warning-50 dark:bg-warning-950 p-3 text-warning-700 dark:text-warning-300">
              <strong>{t('super.jwt_warn_not_set')}</strong>{' '}
              {t('super.jwt_warn_not_set_body')}
            </div>
          )}

          <div className="text-default-600">
            <span className="font-medium">{t('super.jwt_issuer')}</span>{' '}
            <Code size="sm">{jwtStatus?.issuer || t('super.jwt_issuer_not_set')}</Code>
          </div>

          <Divider />

          <Accordion variant="light" isCompact>
            <AccordionItem
              key="what"
              aria-label={t('super.jwt_accordion_what')}
              title={<span className="font-medium">{t('super.jwt_accordion_what')}</span>}
            >
              <div className="space-y-2 text-default-600">
                <p>
                  {t('super.jwt_what_p1_intro')}{' '}
                  <strong>api_key</strong>, <strong>hmac</strong>, <strong>oauth2</strong>, {t('super.common_and')}{' '}
                  <strong>jwt</strong>. {t('super.jwt_what_p1_suffix')}
                </p>
                <p>
                  {t('super.jwt_what_p2')}
                </p>
                <p>
                  {t('super.jwt_what_p3_prefix')}{' '}
                  <strong>{t('super.jwt_based_federation_partnerships')}</strong>, {t('super.jwt_what_p3_suffix')}
                </p>
              </div>
            </AccordionItem>

            <AccordionItem
              key="setup"
              aria-label={t('super.jwt_accordion_setup')}
              title={<span className="font-medium">{t('super.jwt_accordion_setup')}</span>}
            >
              <div className="space-y-3 text-default-600">
                <div>
                  <p className="mb-1"><strong>{t('super.step_number', { number: 1 })}</strong> {t('super.jwt_setup_step_1')}</p>
                  <Snippet size="sm" symbol="$" hideCopyButton={false}>openssl rand -hex 32</Snippet>
                  <p className="text-xs text-default-500 mt-1">{t('super.jwt_setup_step_1_note')}</p>
                </div>

                <div>
                  <p className="mb-1"><strong>{t('super.step_number', { number: 2 })}</strong> {t('super.jwt_setup_step_2')}</p>
                  <ul className="list-disc pl-5 space-y-1 text-xs">
                    <li><strong>{t('super.jwt_setup_docker_env')}</strong> {t('super.jwt_setup_append_to')} <Code size="sm">/opt/nexus-php/.env</Code>:<br/>
                      <Code size="sm">FEDERATION_JWT_SECRET=&lt;paste 64-char hex&gt;</Code><br/>
                      <Code size="sm">FEDERATION_JWT_ISSUER=https://api.your-domain.com</Code>
                    </li>
                    <li><strong>{t('super.jwt_setup_docker_no_env')}</strong> {t('super.jwt_setup_docker_no_env_body')} <Code size="sm">environment:</Code> {t('super.jwt_setup_compose_key_suffix')}</li>
                    <li><strong>{t('super.jwt_setup_kubernetes')}</strong> {t('super.jwt_setup_kubernetes_body')} <Code size="sm">{t('super.kubernetes_secret_resource')}</Code> {t('super.jwt_setup_and_reference')} <Code size="sm">envFrom</Code> {t('super.common_or')} <Code size="sm">env.valueFrom.secretKeyRef</Code>.</li>
                    <li><strong>{t('super.jwt_setup_aws')}</strong> {t('super.jwt_setup_aws_body')} <Code size="sm">secrets</Code> {t('super.jwt_setup_aws_middle')} <Code size="sm">environment</Code> {t('super.jwt_setup_aws_suffix')}</li>
                    <li><strong>{t('super.jwt_setup_paas')}</strong> <Code size="sm">heroku config:set FEDERATION_JWT_SECRET=&lt;hex&gt;</Code> {t('super.jwt_setup_paas_suffix')}</li>
                    <li><strong>{t('super.jwt_setup_systemd')}</strong> {t('super.jwt_setup_systemd_body')} <Code size="sm">Environment=&quot;FEDERATION_JWT_SECRET=&lt;hex&gt;&quot;</Code> {t('super.jwt_setup_systemd_suffix')}</li>
                    <li><strong>{t('super.jwt_setup_hosting_panel')}</strong> {t('super.jwt_setup_hosting_panel_body')} <Code size="sm">&lt;environmentVariables&gt;</Code>).</li>
                  </ul>
                </div>

                <div>
                  <p className="mb-1"><strong>{t('super.step_number', { number: 3 })}</strong> {t('super.jwt_setup_step_3_prefix')} <Code size="sm">php artisan config:cache</Code>{t('super.jwt_setup_step_3_suffix')}</p>
                </div>

                <div>
                  <p className="mb-1"><strong>{t('super.step_number', { number: 4 })}</strong> {t('super.jwt_setup_step_4')}</p>
                </div>
              </div>
            </AccordionItem>

            <AccordionItem
              key="rotate"
              aria-label={t('super.jwt_accordion_rotate')}
              title={<span className="font-medium">{t('super.jwt_accordion_rotate')}</span>}
            >
              <div className="space-y-2 text-default-600">
                <p>{t('super.jwt_rotate_intro')}</p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>{t('super.jwt_rotate_reason_exposed')}</li>
                  <li>{t('super.jwt_rotate_reason_abuse')}</li>
                  <li>{t('super.jwt_rotate_reason_routine')}</li>
                </ul>
                <p><strong>{t('super.jwt_rotation_procedure')}</strong></p>
                <ol className="list-decimal pl-5 space-y-1">
                  <li>{t('super.jwt_rotate_step_1')} <Code size="sm">openssl rand -hex 32</Code>.</li>
                  <li>{t('super.jwt_rotate_step_2_prefix')} <Code size="sm">FEDERATION_JWT_SECRET</Code> {t('super.jwt_rotate_step_2_suffix')}</li>
                  <li>{t('super.jwt_rotate_step_3')}</li>
                  <li>{t('super.jwt_rotate_step_4')}</li>
                </ol>
                <p className="text-xs text-default-500">
                  {t('super.jwt_rotate_note')}
                </p>
              </div>
            </AccordionItem>

            <AccordionItem
              key="troubleshoot"
              aria-label={t('super.jwt_accordion_troubleshoot')}
              title={<span className="font-medium">{t('super.jwt_accordion_troubleshoot')}</span>}
            >
              <div className="space-y-2 text-default-600 text-xs">
                <p><strong>{t('super.jwt_troubleshoot_not_configured')}</strong></p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>{t('super.jwt_troubleshoot_restart')}</li>
                  <li>{t('super.jwt_troubleshoot_config_cache_prefix')} <Code size="sm">php artisan config:cache</Code>{t('super.jwt_troubleshoot_config_cache_suffix')}</li>
                  <li>{t('super.jwt_troubleshoot_printenv')} <Code size="sm">docker exec nexus-php-app printenv FEDERATION_JWT_SECRET</Code>.</li>
                </ul>
                <p className="pt-2"><strong>{t('super.jwt_troubleshoot_low_bits')}</strong></p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>{t('super.jwt_troubleshoot_low_bits_body_prefix')} <Code size="sm">openssl rand -hex 32</Code> {t('super.jwt_troubleshoot_low_bits_body_suffix')}</li>
                </ul>
                <p className="pt-2"><strong>{t('super.jwt_troubleshoot_needed')}</strong></p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>{t('super.jwt_troubleshoot_needed_body')}</li>
                </ul>
              </div>
            </AccordionItem>
          </Accordion>
        </CardBody>
      </Card>

      {/* Quick Navigation */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {quickLinks.map((link) => (
          <Card
            key={link.href}
            as={Link}
            to={tenantPath(link.href)}
            isPressable
            className="hover:shadow-md transition-shadow"
          >
            <CardBody className="flex flex-row items-center gap-3">
              <div className={`p-2 rounded-lg ${colorClasses[link.color]?.bg ?? ''}`}>
                <link.icon size={20} className={colorClasses[link.color]?.text ?? ''} />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-medium text-sm">{link.label}</p>
                <p className="text-xs text-default-500 truncate">{link.description}</p>
              </div>
              <ArrowRight size={16} className="text-default-400 shrink-0" />
            </CardBody>
          </Card>
        ))}
      </div>

      {/* Lockdown Banner */}
      {controls.emergency_lockdown_active && (
        <Card className="border-2 border-danger bg-danger-50 dark:bg-danger-950">
          <CardBody className="flex flex-row items-center gap-4">
            <Lock size={24} className="text-danger shrink-0" />
            <div className="flex-1">
              <p className="font-semibold text-danger">{t('super.emergency_lockdown_active')}</p>
              <p className="text-sm text-danger-600 dark:text-danger-400">
                {controls.emergency_lockdown_reason || t('super.all_federation_disabled')}
              </p>
            </div>
            <Button
              color="success"
              variant="solid"
              size="sm"
              startContent={<Unlock size={16} />}
              onPress={() => setLockdownConfirm(true)}
            >
              {t('super.lift_lockdown')}
            </Button>
          </CardBody>
        </Card>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* System Status */}
        <Card>
          <CardHeader className="flex gap-2 items-center pb-0">
            <Globe size={20} className="text-primary" />
            <h3 className="font-semibold text-lg">{t('super.system_status')}</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('super.federation_label')}</p>
                <p className="text-xs text-default-500">{t('super.federation_desc')}</p>
              </div>
              <Switch
                isSelected={controls.federation_enabled}
                isDisabled={!!saving}
                onValueChange={(v) => updateControl('federation_enabled', v)}
              />
            </div>
            <Divider />
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('super.whitelist_mode')}</p>
                <p className="text-xs text-default-500">{t('super.whitelist_mode_desc')}</p>
              </div>
              <Switch
                isSelected={controls.whitelist_mode_enabled}
                isDisabled={!!saving}
                onValueChange={(v) => updateControl('whitelist_mode_enabled', v)}
              />
            </div>
            <Divider />
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                {controls.emergency_lockdown_active ? <Lock size={16} className="text-danger" /> : <Unlock size={16} className="text-success" />}
                <div>
                  <p className="font-medium">{t('super.lockdown_status')}</p>
                  <p className="text-xs text-default-500">{t('super.lockdown_status_desc')}</p>
                </div>
              </div>
              <Chip color={controls.emergency_lockdown_active ? 'danger' : 'success'} variant="flat" size="sm">
                {controls.emergency_lockdown_active ? t('super.locked_down') : t('super.status_normal')}
              </Chip>
            </div>
            {!controls.emergency_lockdown_active && (
              <Button
                color="danger"
                variant="flat"
                size="sm"
                startContent={<AlertTriangle size={16} />}
                onPress={() => setLockdownConfirm(true)}
              >
                {t('super.emergency_lockdown')}
              </Button>
            )}
          </CardBody>
        </Card>

        {/* Feature Toggles */}
        <Card>
          <CardHeader className="flex gap-2 items-center pb-0">
            <Shield size={20} className="text-secondary" />
            <h3 className="font-semibold text-lg">{t('super.cross_tenant_features')}</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            {featureToggles.map(({ key, label, description }) => (
              <div key={key} className="flex items-center justify-between py-1">
                <div>
                  <p className="text-sm font-medium">{label}</p>
                  <p className="text-xs text-default-500">{description}</p>
                </div>
                <Switch
                  size="sm"
                  isSelected={controls[key]}
                  isDisabled={!!saving}
                  onValueChange={(v) => updateControl(key, v)}
                />
              </div>
            ))}
          </CardBody>
        </Card>

        {/* Whitelist */}
        <Card>
          <CardHeader className="flex justify-between items-center pb-0">
            <div className="flex gap-2 items-center">
              <Network size={20} className="text-success" />
              <h3 className="font-semibold text-lg">{t('super.whitelist')}</h3>
              <Chip size="sm" variant="flat">{whitelist.length}</Chip>
            </div>
            <Button
              as={Link}
              to={tenantPath('/admin/super/federation/whitelist')}
              size="sm"
              variant="light"
              endContent={<ArrowRight size={14} />}
            >
              {t('super.manage')}
            </Button>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <div className="flex gap-2">
              <Input
                size="sm"
                label={t('super.label_tenant_id')}
                value={addTenantId}
                onValueChange={setAddTenantId}
                className="max-w-[120px]"
                variant="bordered"
              />
              <Button size="sm" color="primary" startContent={<Plus size={14} />} onPress={handleAddWhitelist}>
                {t('super.add')}
              </Button>
            </div>
            <div className="max-h-[200px] overflow-y-auto">
              {whitelist.map((entry) => (
                <div key={entry.tenant_id} className="flex items-center justify-between py-2 border-b border-default-100 last:border-b-0">
                  <span>
                    <Link to={tenantPath(`/admin/super/tenants/${entry.tenant_id}`)} className="hover:text-primary font-medium text-sm">
                      {entry.tenant_name}
                    </Link>
                    {' '}<span className="text-xs text-default-400">{t('super.tenant_id_compact', { id: entry.tenant_id })}</span>
                  </span>
                  <Button size="sm" variant="light" color="danger" isIconOnly aria-label={t('super.remove_from_whitelist')} onPress={() => handleRemoveWhitelist(entry.tenant_id)}>
                    <Trash2 size={14} />
                  </Button>
                </div>
              ))}
              {whitelist.length === 0 && <p className="text-default-400 text-sm py-4 text-center">{t('super.no_whitelisted_tenants')}</p>}
            </div>
          </CardBody>
        </Card>

        {/* Partnerships */}
        <Card>
          <CardHeader className="flex justify-between items-center pb-0">
            <div className="flex gap-2 items-center">
              <Handshake size={20} className="text-secondary" />
              <h3 className="font-semibold text-lg">{t('super.partnerships')}</h3>
              <Chip size="sm" variant="flat">{partnerships.length}</Chip>
            </div>
            <Button
              as={Link}
              to={tenantPath('/admin/super/federation/partnerships')}
              size="sm"
              variant="light"
              endContent={<ArrowRight size={14} />}
            >
              {t('super.manage')}
            </Button>
          </CardHeader>
          <CardBody className="flex flex-col gap-2">
            <div className="max-h-[240px] overflow-y-auto">
              {partnerships.map((p) => {
                const statusColor = p.status === 'active' ? 'success'
                  : p.status === 'pending' ? 'warning'
                  : p.status === 'suspended' ? 'danger' : 'default';
                return (
                  <div key={p.id} className="flex items-center justify-between py-2 border-b border-default-100 last:border-b-0">
                    <div className="flex items-center gap-2 min-w-0">
                      <Users size={14} className="text-default-400 shrink-0" />
                      <span className="text-sm font-medium truncate">
                        {p.tenant_1_name}
                      </span>
                      <span className="text-default-400 shrink-0">&harr;</span>
                      <span className="text-sm font-medium truncate">
                        {p.tenant_2_name}
                      </span>
                      <Chip size="sm" variant="flat" color={statusColor} className="shrink-0">
                        {partnershipStatusLabel(p.status)}
                      </Chip>
                    </div>
                    {p.status === 'active' && (
                      <div className="flex gap-1 shrink-0 ml-2">
                        <Button size="sm" variant="flat" color="warning" onPress={() => setPartnerAction({ type: 'suspend', id: p.id })}>
                          {t('super.suspend')}
                        </Button>
                        <Button size="sm" variant="flat" color="danger" onPress={() => setPartnerAction({ type: 'terminate', id: p.id })}>
                          {t('super.end')}
                        </Button>
                      </div>
                    )}
                    {p.status === 'suspended' && (
                      <div className="flex gap-1 shrink-0 ml-2">
                        <Button size="sm" variant="flat" color="success" onPress={() => setPartnerAction({ type: 'reactivate', id: p.id })}>
                          {t('super.reactivate')}
                        </Button>
                      </div>
                    )}
                  </div>
                );
              })}
              {partnerships.length === 0 && <p className="text-default-400 text-sm py-4 text-center">{t('super.no_partnerships')}</p>}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Lockdown Confirm Modal */}
      <ConfirmModal
        isOpen={lockdownConfirm}
        onClose={() => { setLockdownConfirm(false); setLockdownReason(''); }}
        onConfirm={handleLockdown}
        title={controls.emergency_lockdown_active ? t('super.lift_lockdown') : t('super.emergency_lockdown')}
        message={controls.emergency_lockdown_active
          ? t('super.lift_lockdown_confirm')
          : t('super.emergency_lockdown_confirm')}
        confirmLabel={controls.emergency_lockdown_active ? t('super.lift_lockdown') : t('super.activate_lockdown')}
        confirmColor={controls.emergency_lockdown_active ? 'primary' : 'danger'}
      >
        {!controls.emergency_lockdown_active && (
          <Input
            label={t('super.lockdown_reason')}
            placeholder={t('super.lockdown_reason_placeholder')}
            value={lockdownReason}
            onValueChange={setLockdownReason}
            className="mt-3"
            variant="bordered"
          />
        )}
      </ConfirmModal>

      {/* Partnership Action Modal */}
      <ConfirmModal
        isOpen={!!partnerAction}
        onClose={() => setPartnerAction(null)}
        onConfirm={handlePartnerAction}
        title={partnerAction
          ? (partnerAction.type === 'suspend'
            ? t('super.suspend_partnership')
            : partnerAction.type === 'terminate'
            ? t('super.terminate_partnership')
            : t('super.reactivate_partnership'))
          : ''}
        message={partnerAction?.type === 'suspend'
          ? t('super.suspend_partnership_confirm')
          : partnerAction?.type === 'terminate'
          ? t('super.terminate_partnership_confirm')
          : t('super.reactivate_partnership_confirm')}
        confirmLabel={
          partnerAction?.type === 'suspend'
            ? t('super.suspend')
            : partnerAction?.type === 'terminate'
            ? t('super.terminate')
            : t('super.reactivate')
        }
        confirmColor={partnerAction?.type === 'reactivate' ? 'primary' : 'danger'}
      />
    </div>
  );
}

export default FederationControls;
