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
import {
  Globe, Shield, Lock, Unlock, AlertTriangle, Network, Trash2, Plus,
  Activity, ArrowRight, ListChecks, Users, Handshake, KeyRound,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader, ConfirmModal, StatCard } from '../../components';
import type { FederationSystemControls as FederationSystemControlsType, FederationWhitelistEntry, FederationPartnership } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function FederationControls() {
  const { t } = useTranslation('admin');
  usePageTitle(t('super.page_title'));
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
  const [partnerAction, setPartnerAction] = useState<{ type: 'suspend' | 'terminate'; id: number } | null>(null);

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
    } catch (err) {
      toastRef.current.error(t('super.federation_error', { message: err instanceof Error ? err.message : 'Unknown error' }));
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
        toastRef.current.error(t('super.failed_to_update_setting', 'Failed to update setting'));
      }
    } catch (err) {
      toastRef.current.error(t('super.failed_to_update_setting_detail', { detail: err instanceof Error ? err.message : '', defaultValue: 'Failed to update setting: {{detail}}' }));
    } finally {
      setSaving(null);
    }
  };

  const handleLockdown = async () => {
    try {
      if (controls?.emergency_lockdown_active) {
        const res = await adminSuper.liftLockdown();
        if (res?.success) { toastRef.current.success(t('super.lockdown_lifted', 'Lockdown lifted')); loadData(); }
        else toastRef.current.error(t('super.failed_to_lift_lockdown', 'Failed to lift lockdown'));
      } else {
        if (!lockdownReason.trim()) {
          toastRef.current.error(t('super.please_provide_a_reason_for_the_lockdown', 'Please provide a reason for the lockdown'));
          return;
        }
        const res = await adminSuper.emergencyLockdown(lockdownReason);
        if (res?.success) { toastRef.current.success(t('super.lockdown_activated', 'Lockdown activated')); loadData(); }
        else toastRef.current.error(t('super.failed_to_activate_lockdown', 'Failed to activate lockdown'));
      }
    } catch (err) {
      toastRef.current.error(t('super.lockdown_action_failed_detail', { detail: err instanceof Error ? err.message : '', defaultValue: 'Lockdown action failed: {{detail}}' }));
    } finally {
      setLockdownConfirm(false);
    }
  };

  const handleAddWhitelist = async () => {
    if (!addTenantId) return;
    const res = await adminSuper.addToWhitelist(Number(addTenantId));
    if (res?.success) { toastRef.current.success(t('super.added_to_whitelist', 'Added to whitelist')); setAddTenantId(''); loadData(); }
    else toastRef.current.error(t('super.failed_to_add_to_whitelist', 'Failed to add'));
  };

  const handleRemoveWhitelist = async (tenantId: number) => {
    const res = await adminSuper.removeFromWhitelist(tenantId);
    if (res?.success) { toastRef.current.success(t('super.removed_from_whitelist', 'Removed from whitelist')); loadData(); }
    else toastRef.current.error(t('super.failed_to_remove_from_whitelist', 'Failed to remove'));
  };

  const handlePartnerAction = async () => {
    if (!partnerAction) return;
    const res = partnerAction.type === 'suspend'
      ? await adminSuper.suspendPartnership(partnerAction.id, t('super.suspended_by_super_admin', 'Suspended by super admin'))
      : await adminSuper.terminatePartnership(partnerAction.id, t('super.terminated_by_super_admin', 'Terminated by super admin'));
    if (res?.success) {
      toastRef.current.success(
        partnerAction.type === 'suspend'
          ? t('super.partnership_suspended', 'Partnership suspended')
          : t('super.partnership_terminated', 'Partnership terminated')
      );
      loadData();
    } else {
      toastRef.current.error(t('super.action_failed', 'Action failed'));
    }
    setPartnerAction(null);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Spinner size="lg" label={t('super.loading_federation_controls', 'Loading federation controls...')} />
      </div>
    );
  }

  if (!controls) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] gap-4">
        <AlertTriangle size={48} className="text-warning" />
        <p className="text-lg text-default-500">{t('super.failed_to_load_federation_controls', 'Failed to load federation controls')}</p>
        <Button color="primary" onPress={loadData}>{t('super.retry', 'Retry')}</Button>
      </div>
    );
  }

  type BooleanControlKey = Exclude<{
    [K in keyof FederationSystemControlsType]: FederationSystemControlsType[K] extends boolean ? K : never;
  }[keyof FederationSystemControlsType], undefined>;

  const featureToggles: Array<{ key: BooleanControlKey; label: string; description: string }> = [
    { key: 'cross_tenant_profiles_enabled', label: t('super.toggle_profile_sharing', 'Profile Sharing'), description: t('super.toggle_profile_sharing_desc', 'View profiles across communities') },
    { key: 'cross_tenant_messaging_enabled', label: t('super.toggle_cross_messaging', 'Cross-Community Messaging'), description: t('super.toggle_cross_messaging_desc', 'Send messages between communities') },
    { key: 'cross_tenant_transactions_enabled', label: t('super.toggle_cross_transactions', 'Cross-Community Transactions'), description: t('super.toggle_cross_transactions_desc', 'Time credit transfers between communities') },
    { key: 'cross_tenant_listings_enabled', label: t('super.toggle_listing_discovery', 'Listing Discovery'), description: t('super.toggle_listing_discovery_desc', 'Show listings from partner communities') },
    { key: 'cross_tenant_events_enabled', label: t('super.toggle_event_sharing', 'Event Sharing'), description: t('super.toggle_event_sharing_desc', 'Share events across communities') },
    { key: 'cross_tenant_groups_enabled', label: t('super.toggle_group_federation', 'Group Federation'), description: t('super.toggle_group_federation_desc', 'Enable cross-community groups') },
  ];

  const activePartnerships = partnerships.filter(p => p.status === 'active').length;
  const pendingPartnerships = partnerships.filter(p => p.status === 'pending').length;

  const colorClasses: Record<string, { bg: string; text: string }> = {
    primary: { bg: 'bg-primary/10', text: 'text-primary' },
    success: { bg: 'bg-success/10', text: 'text-success' },
    secondary: { bg: 'bg-secondary/10', text: 'text-secondary' },
    warning: { bg: 'bg-warning/10', text: 'text-warning' },
    danger: { bg: 'bg-danger/10', text: 'text-danger' },
  };

  const quickLinks = [
    { label: t('super.link_whitelist', 'Whitelist'), description: t('super.link_whitelist_desc', { count: whitelist.length }), href: '/admin/super/federation/whitelist', icon: ListChecks, color: 'success' as const },
    { label: t('super.link_partnerships', 'Partnerships'), description: t('super.link_partnerships_desc', { active: activePartnerships, pending: pendingPartnerships }), href: '/admin/super/federation/partnerships', icon: Handshake, color: 'secondary' as const },
    { label: t('super.link_audit_log', 'Audit Log'), description: t('super.link_audit_log_desc', 'Federation action history'), href: '/admin/super/federation/audit', icon: Activity, color: 'warning' as const },
  ];

  return (
    <div className="space-y-6">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-1 text-sm text-default-500">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">{t('super.breadcrumb_super_admin', 'Super Admin')}</Link>
        <span>/</span>
        <span className="text-foreground font-medium">{t('super.breadcrumb_federation_controls', 'Federation Controls')}</span>
      </nav>

      <PageHeader
        title={t('super.federation_controls_title')}
        description={t('super.federation_controls_desc')}
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label={t('super.label_federation_status')}
          value={controls.federation_enabled ? t('super.status_active', 'Active') : t('super.status_disabled', 'Disabled')}
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
          value={controls.emergency_lockdown_active ? t('super.status_lockdown', 'LOCKDOWN') : t('super.status_normal', 'Normal')}
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
            <p className="font-semibold">{t('super_controls.federation_jwt')}</p>
            <p className="text-xs text-default-500">
              Shared secret used to sign and verify cross-platform federation tokens
            </p>
          </div>
          {jwtStatus ? (
            jwtStatus.configured ? (
              <Chip color="success" variant="flat" size="sm">Configured · {jwtStatus.key_bits}-bit</Chip>
            ) : (
              <Chip color="warning" variant="flat" size="sm" startContent={<AlertTriangle size={14} />}>Not configured</Chip>
            )
          ) : (
            <Chip variant="flat" size="sm">…</Chip>
          )}
        </CardHeader>
        <CardBody className="gap-3 text-sm">
          {jwtStatus?.configured && jwtStatus.key_bits < jwtStatus.recommended_bits && (
            <div className="rounded-md border border-warning bg-warning-50 dark:bg-warning-950 p-3 text-warning-700 dark:text-warning-300">
              <strong>Key is weaker than recommended.</strong> Current: {jwtStatus.key_bits}-bit.
              Recommended: at least {jwtStatus.recommended_bits}-bit. Regenerate with the openssl
              command below and replace the env var.
            </div>
          )}

          {!jwtStatus?.configured && (
            <div className="rounded-md border border-warning bg-warning-50 dark:bg-warning-950 p-3 text-warning-700 dark:text-warning-300">
              <strong>FEDERATION_JWT_SECRET is not set on this server.</strong> JWT-authenticated
              federation requests will fail. Partnerships using <Code size="sm">api_key</Code>,
              <Code size="sm">hmac</Code>, or <Code size="sm">oauth2</Code> still work — only the
              JWT auth method is affected.
            </div>
          )}

          <div className="text-default-600">
            <span className="font-medium">Issuer (iss claim):</span>{' '}
            <Code size="sm">{jwtStatus?.issuer || '(falls back to APP_URL)'}</Code>
          </div>

          <Divider />

          <Accordion variant="light" isCompact>
            <AccordionItem
              key="what"
              aria-label="What is this for"
              title={<span className="font-medium">What is this for?</span>}
            >
              <div className="space-y-2 text-default-600">
                <p>
                  NEXUS can authenticate incoming and outgoing federation requests with four
                  different methods: <strong>api_key</strong>, <strong>hmac</strong>,{' '}
                  <strong>oauth2</strong>, and <strong>jwt</strong>. The first three store
                  credentials per partner in the database — no server-wide config needed.
                </p>
                <p>
                  The JWT method signs tokens with a single server-wide HMAC-SHA256 secret
                  (this one). It is used by the V2 and native-ingest cross-tenant bridges, and
                  by some newer partner protocols that prefer signed tokens to API keys.
                </p>
                <p>
                  If you are <strong>not using JWT-based federation partnerships</strong>, you
                  can leave this unset — federation will work via api_key / hmac / oauth2. You
                  will get a clear log entry the first time JWT is actually needed.
                </p>
              </div>
            </AccordionItem>

            <AccordionItem
              key="setup"
              aria-label="How to configure"
              title={<span className="font-medium">How to configure (first-time setup)</span>}
            >
              <div className="space-y-3 text-default-600">
                <div>
                  <p className="mb-1"><strong>Step 1.</strong> Generate a 256-bit random secret on any machine:</p>
                  <Snippet size="sm" symbol="$" hideCopyButton={false}>openssl rand -hex 32</Snippet>
                  <p className="text-xs text-default-500 mt-1">(Copy the 64-character hex string it prints.)</p>
                </div>

                <div>
                  <p className="mb-1"><strong>Step 2.</strong> Set it as an environment variable on the server. Use exactly ONE of the options below — whichever matches your hosting stack:</p>
                  <ul className="list-disc pl-5 space-y-1 text-xs">
                    <li><strong>Docker Compose / plain Linux server with .env file:</strong> append to <Code size="sm">/opt/nexus-php/.env</Code>:<br/>
                      <Code size="sm">FEDERATION_JWT_SECRET=&lt;paste 64-char hex&gt;</Code><br/>
                      <Code size="sm">FEDERATION_JWT_ISSUER=https://api.your-domain.com</Code>
                    </li>
                    <li><strong>Docker Compose without .env file:</strong> add under the app service&apos;s <Code size="sm">environment:</Code> key in the compose file.</li>
                    <li><strong>Kubernetes:</strong> create a <Code size="sm">Secret</Code> and reference it via <Code size="sm">envFrom</Code> or <Code size="sm">env.valueFrom.secretKeyRef</Code>.</li>
                    <li><strong>AWS ECS / Fargate:</strong> add to the task-definition <Code size="sm">secrets</Code> (AWS Secrets Manager) or <Code size="sm">environment</Code> block.</li>
                    <li><strong>Heroku / Render / Fly.io:</strong> <Code size="sm">heroku config:set FEDERATION_JWT_SECRET=&lt;hex&gt;</Code> (or the equivalent dashboard field).</li>
                    <li><strong>systemd service:</strong> add <Code size="sm">Environment=&quot;FEDERATION_JWT_SECRET=&lt;hex&gt;&quot;</Code> to the service unit file.</li>
                    <li><strong>Plesk / cPanel / IIS:</strong> use the hosting panel&apos;s environment-variable editor (Plesk: PHP Settings → Env Vars; IIS: web.config <Code size="sm">&lt;environmentVariables&gt;</Code>).</li>
                  </ul>
                </div>

                <div>
                  <p className="mb-1"><strong>Step 3.</strong> Restart the PHP container (or reload PHP-FPM) so the new env var is picked up. If you use <Code size="sm">php artisan config:cache</Code>, re-run it before the restart.</p>
                </div>

                <div>
                  <p className="mb-1"><strong>Step 4.</strong> Refresh this page. The status pill should flip to green &quot;Configured · 256-bit&quot;.</p>
                </div>
              </div>
            </AccordionItem>

            <AccordionItem
              key="rotate"
              aria-label="How to rotate"
              title={<span className="font-medium">{t('super_controls.rotation_policy')}</span>}
            >
              <div className="space-y-2 text-default-600">
                <p>Rotate this secret when:</p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>You suspect the secret was exposed (committed to git, leaked in logs, a developer who had access has left).</li>
                  <li>A federation partner using JWT auth has reported token abuse.</li>
                  <li>Routine schedule — annually is reasonable.</li>
                </ul>
                <p><strong>Rotation procedure:</strong></p>
                <ol className="list-decimal pl-5 space-y-1">
                  <li>Generate a new 256-bit secret: <Code size="sm">openssl rand -hex 32</Code>.</li>
                  <li>Replace <Code size="sm">FEDERATION_JWT_SECRET</Code> in server env (same method as initial setup).</li>
                  <li>Restart the PHP container.</li>
                  <li>Notify any federation partners that rely on verifying tokens issued by us — they may need to update their trusted-issuer cache (usually no action needed because we publish the secret out-of-band to them, not the public).</li>
                </ol>
                <p className="text-xs text-default-500">
                  Rotation invalidates all outstanding tokens. Tokens already in flight at the
                  moment of rotation will fail verification; the requesting partner will re-mint.
                </p>
              </div>
            </AccordionItem>

            <AccordionItem
              key="troubleshoot"
              aria-label="Troubleshooting"
              title={<span className="font-medium">{t('super_controls.troubleshooting')}</span>}
            >
              <div className="space-y-2 text-default-600 text-xs">
                <p><strong>Status shows &quot;Not configured&quot; but I set the env var:</strong></p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>Restart the PHP container. PHP reads env vars at process start.</li>
                  <li>If you use <Code size="sm">php artisan config:cache</Code>, re-run it after setting the var, then restart.</li>
                  <li>Check the var actually reaches PHP: <Code size="sm">docker exec nexus-php-app printenv FEDERATION_JWT_SECRET</Code>.</li>
                </ul>
                <p className="pt-2"><strong>Key bits shows a low number:</strong></p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>Someone used a short / human-memorable string. Regenerate with <Code size="sm">openssl rand -hex 32</Code> (gives 256 bits).</li>
                </ul>
                <p className="pt-2"><strong>Federation was working with api_key/hmac and still works — do I need this at all?</strong></p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>No. JWT is only for the JWT auth method. Existing partners with api_key / hmac / oauth2 are unaffected.</li>
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
              <p className="font-semibold text-danger">{t('super.emergency_lockdown_active', 'Emergency Lockdown Active')}</p>
              <p className="text-sm text-danger-600 dark:text-danger-400">
                {controls.emergency_lockdown_reason || t('super.all_federation_disabled', 'All federation features are currently disabled.')}
              </p>
            </div>
            <Button
              color="success"
              variant="solid"
              size="sm"
              startContent={<Unlock size={16} />}
              onPress={() => setLockdownConfirm(true)}
            >
              {t('super.lift_lockdown', 'Lift Lockdown')}
            </Button>
          </CardBody>
        </Card>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* System Status */}
        <Card>
          <CardHeader className="flex gap-2 items-center pb-0">
            <Globe size={20} className="text-primary" />
            <h3 className="font-semibold text-lg">{t('super.system_status', 'System Status')}</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{t('super.federation_label', 'Federation')}</p>
                <p className="text-xs text-default-500">{t('super.federation_desc', 'Enable or disable federation system-wide')}</p>
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
                <p className="font-medium">{t('super.whitelist_mode', 'Whitelist Mode')}</p>
                <p className="text-xs text-default-500">{t('super.whitelist_mode_desc', 'Only whitelisted tenants can federate')}</p>
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
                  <p className="font-medium">{t('super.lockdown_status', 'Lockdown Status')}</p>
                  <p className="text-xs text-default-500">{t('super.lockdown_status_desc', 'Emergency kill switch for all federation')}</p>
                </div>
              </div>
              <Chip color={controls.emergency_lockdown_active ? 'danger' : 'success'} variant="flat" size="sm">
                {controls.emergency_lockdown_active ? t('super.locked_down', 'LOCKED DOWN') : t('super.status_normal', 'Normal')}
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
                {t('super.emergency_lockdown', 'Emergency Lockdown')}
              </Button>
            )}
          </CardBody>
        </Card>

        {/* Feature Toggles */}
        <Card>
          <CardHeader className="flex gap-2 items-center pb-0">
            <Shield size={20} className="text-secondary" />
            <h3 className="font-semibold text-lg">{t('super.cross_tenant_features', 'Cross-Tenant Features')}</h3>
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
              <h3 className="font-semibold text-lg">{t('super.whitelist', 'Whitelist')}</h3>
              <Chip size="sm" variant="flat">{whitelist.length}</Chip>
            </div>
            <Button
              as={Link}
              to={tenantPath('/admin/super/federation/whitelist')}
              size="sm"
              variant="light"
              endContent={<ArrowRight size={14} />}
            >
              {t('super.manage', 'Manage')}
            </Button>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <div className="flex gap-2">
              <Input
                size="sm"
                label={t('super.label_tenant_i_d')}
                value={addTenantId}
                onValueChange={setAddTenantId}
                className="max-w-[120px]"
                variant="bordered"
              />
              <Button size="sm" color="primary" startContent={<Plus size={14} />} onPress={handleAddWhitelist}>
                {t('super.add', 'Add')}
              </Button>
            </div>
            <div className="max-h-[200px] overflow-y-auto">
              {whitelist.map((entry) => (
                <div key={entry.tenant_id} className="flex items-center justify-between py-2 border-b border-default-100 last:border-b-0">
                  <span>
                    <Link to={tenantPath(`/admin/super/tenants/${entry.tenant_id}`)} className="hover:text-primary font-medium text-sm">
                      {entry.tenant_name}
                    </Link>
                    {' '}<span className="text-xs text-default-400">(ID: {entry.tenant_id})</span>
                  </span>
                  <Button size="sm" variant="light" color="danger" isIconOnly aria-label={t('super.label_remove_from_whitelist')} onPress={() => handleRemoveWhitelist(entry.tenant_id)}>
                    <Trash2 size={14} />
                  </Button>
                </div>
              ))}
              {whitelist.length === 0 && <p className="text-default-400 text-sm py-4 text-center">{t('super.no_whitelisted_tenants', 'No whitelisted tenants')}</p>}
            </div>
          </CardBody>
        </Card>

        {/* Partnerships */}
        <Card>
          <CardHeader className="flex justify-between items-center pb-0">
            <div className="flex gap-2 items-center">
              <Handshake size={20} className="text-secondary" />
              <h3 className="font-semibold text-lg">{t('super.partnerships', 'Partnerships')}</h3>
              <Chip size="sm" variant="flat">{partnerships.length}</Chip>
            </div>
            <Button
              as={Link}
              to={tenantPath('/admin/super/federation/partnerships')}
              size="sm"
              variant="light"
              endContent={<ArrowRight size={14} />}
            >
              {t('super.manage', 'Manage')}
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
                        {p.status.charAt(0).toUpperCase() + p.status.slice(1)}
                      </Chip>
                    </div>
                    {p.status === 'active' && (
                      <div className="flex gap-1 shrink-0 ml-2">
                        <Button size="sm" variant="flat" color="warning" onPress={() => setPartnerAction({ type: 'suspend', id: p.id })}>
                          {t('super.suspend', 'Suspend')}
                        </Button>
                        <Button size="sm" variant="flat" color="danger" onPress={() => setPartnerAction({ type: 'terminate', id: p.id })}>
                          {t('super.end', 'End')}
                        </Button>
                      </div>
                    )}
                  </div>
                );
              })}
              {partnerships.length === 0 && <p className="text-default-400 text-sm py-4 text-center">{t('super.no_partnerships', 'No partnerships')}</p>}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Lockdown Confirm Modal */}
      <ConfirmModal
        isOpen={lockdownConfirm}
        onClose={() => { setLockdownConfirm(false); setLockdownReason(''); }}
        onConfirm={handleLockdown}
        title={controls.emergency_lockdown_active ? t('super.lift_lockdown', 'Lift Lockdown') : t('super.emergency_lockdown', 'Emergency Lockdown')}
        message={controls.emergency_lockdown_active
          ? t('super.lift_lockdown_confirm', 'This will re-enable all federation features. Are you sure?')
          : t('super.emergency_lockdown_confirm', 'This will immediately disable ALL federation features across ALL tenants. Use only in emergencies.')}
        confirmLabel={controls.emergency_lockdown_active ? t('super.lift_lockdown', 'Lift Lockdown') : t('super.activate_lockdown', 'Activate Lockdown')}
        confirmColor={controls.emergency_lockdown_active ? 'primary' : 'danger'}
      >
        {!controls.emergency_lockdown_active && (
          <Input
            label={t('super.label_lockdown_reason')}
            placeholder={t('super.placeholder_describe_reason_for_emergency_lockdown')}
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
            ? t('super.suspend_partnership', 'Suspend Partnership')
            : t('super.terminate_partnership', 'Terminate Partnership'))
          : ''}
        message={partnerAction?.type === 'suspend'
          ? t('super.suspend_partnership_confirm', 'All federation features will be temporarily disabled for this partnership.')
          : t('super.terminate_partnership_confirm', 'This will permanently end this partnership. This action cannot be undone.')}
        confirmLabel={partnerAction?.type === 'suspend' ? t('super.suspend', 'Suspend') : t('super.terminate', 'Terminate')}
        confirmColor="danger"
      />
    </div>
  );
}

export default FederationControls;
