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
} from '@heroui/react';
import {
  Globe, Shield, Lock, Unlock, AlertTriangle, Network, Trash2, Plus,
  Activity, ArrowRight, Settings, ListChecks, Users, Handshake,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader, ConfirmModal, StatCard } from '../../components';
import type { FederationSystemControls as FederationSystemControlsType, FederationWhitelistEntry, FederationPartnership } from '../../api/types';

export function FederationControls() {
  usePageTitle('Super Admin - Federation Controls');
  const toast = useToast();
  const toastRef = useRef(toast);
  toastRef.current = toast;
  const { tenantPath } = useTenant();

  const [controls, setControls] = useState<FederationSystemControlsType | null>(null);
  const [whitelist, setWhitelist] = useState<FederationWhitelistEntry[]>([]);
  const [partnerships, setPartnerships] = useState<FederationPartnership[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState<string | null>(null);
  const [lockdownConfirm, setLockdownConfirm] = useState(false);
  const [lockdownReason, setLockdownReason] = useState('');
  const [addTenantId, setAddTenantId] = useState('');
  const [partnerAction, setPartnerAction] = useState<{ type: 'suspend' | 'terminate'; id: number } | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [ctrlRes, wlRes, pRes] = await Promise.all([
        adminSuper.getSystemControls(),
        adminSuper.getWhitelist(),
        adminSuper.getFederationPartnerships(),
      ]);
      if (ctrlRes.success && ctrlRes.data) setControls(ctrlRes.data);
      if (wlRes.success && wlRes.data) setWhitelist(Array.isArray(wlRes.data) ? wlRes.data : []);
      if (pRes.success && pRes.data) setPartnerships(Array.isArray(pRes.data) ? pRes.data : []);
    } catch (err) {
      toastRef.current.error(`Federation error: ${err instanceof Error ? err.message : 'Unknown error'}`);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const updateControl = async (key: string, value: boolean | number) => {
    setSaving(key);
    const res = await adminSuper.updateSystemControls({ [key]: value });
    if (res?.success) {
      setControls(prev => prev ? { ...prev, [key]: value } : prev);
    } else {
      toastRef.current.error('Failed to update setting');
    }
    setSaving(null);
  };

  const handleLockdown = async () => {
    if (controls?.is_locked_down) {
      const res = await adminSuper.liftLockdown();
      if (res?.success) { toastRef.current.success('Lockdown lifted'); loadData(); }
      else toastRef.current.error('Failed to lift lockdown');
    } else {
      const res = await adminSuper.emergencyLockdown(lockdownReason || 'Emergency lockdown');
      if (res?.success) { toastRef.current.success('Lockdown activated'); loadData(); }
      else toastRef.current.error('Failed to activate lockdown');
    }
    setLockdownConfirm(false);
  };

  const handleAddWhitelist = async () => {
    if (!addTenantId) return;
    const res = await adminSuper.addToWhitelist(Number(addTenantId));
    if (res?.success) { toastRef.current.success('Added to whitelist'); setAddTenantId(''); loadData(); }
    else toastRef.current.error('Failed to add');
  };

  const handleRemoveWhitelist = async (tenantId: number) => {
    const res = await adminSuper.removeFromWhitelist(tenantId);
    if (res?.success) { toastRef.current.success('Removed from whitelist'); loadData(); }
    else toastRef.current.error('Failed to remove');
  };

  const handlePartnerAction = async () => {
    if (!partnerAction) return;
    const res = partnerAction.type === 'suspend'
      ? await adminSuper.suspendPartnership(partnerAction.id, 'Suspended by super admin')
      : await adminSuper.terminatePartnership(partnerAction.id, 'Terminated by super admin');
    if (res?.success) { toastRef.current.success(`Partnership ${partnerAction.type}d`); loadData(); }
    else toastRef.current.error('Action failed');
    setPartnerAction(null);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Spinner size="lg" label="Loading federation controls..." />
      </div>
    );
  }

  if (!controls) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] gap-4">
        <AlertTriangle size={48} className="text-warning" />
        <p className="text-lg text-default-500">Failed to load federation controls</p>
        <Button color="primary" onPress={loadData}>Retry</Button>
      </div>
    );
  }

  type BooleanControlKey = Exclude<{
    [K in keyof FederationSystemControlsType]: FederationSystemControlsType[K] extends boolean ? K : never;
  }[keyof FederationSystemControlsType], undefined>;

  const featureToggles: Array<{ key: BooleanControlKey; label: string; description: string }> = [
    { key: 'cross_tenant_profiles_enabled', label: 'Profile Sharing', description: 'View profiles across communities' },
    { key: 'cross_tenant_messaging_enabled', label: 'Cross-Community Messaging', description: 'Send messages between communities' },
    { key: 'cross_tenant_transactions_enabled', label: 'Cross-Community Transactions', description: 'Time credit transfers between communities' },
    { key: 'cross_tenant_listings_enabled', label: 'Listing Discovery', description: 'Show listings from partner communities' },
    { key: 'cross_tenant_events_enabled', label: 'Event Sharing', description: 'Share events across communities' },
    { key: 'cross_tenant_groups_enabled', label: 'Group Federation', description: 'Enable cross-community groups' },
  ];

  const activePartnerships = partnerships.filter(p => p.status === 'active').length;
  const pendingPartnerships = partnerships.filter(p => p.status === 'pending').length;

  const quickLinks = [
    { label: 'System Controls', description: 'Emergency lockdown & kill switches', href: '/admin/super/federation/system-controls', icon: Settings, color: 'primary' as const },
    { label: 'Whitelist', description: `${whitelist.length} whitelisted tenants`, href: '/admin/super/federation/whitelist', icon: ListChecks, color: 'success' as const },
    { label: 'Partnerships', description: `${activePartnerships} active, ${pendingPartnerships} pending`, href: '/admin/super/federation/partnerships', icon: Handshake, color: 'secondary' as const },
    { label: 'Audit Log', description: 'Federation action history', href: '/admin/super/federation/audit', icon: Activity, color: 'warning' as const },
  ];

  return (
    <div className="space-y-6">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-1 text-sm text-default-500">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">Super Admin</Link>
        <span>/</span>
        <span className="text-foreground font-medium">Federation Controls</span>
      </nav>

      <PageHeader
        title="Federation Control Center"
        description="System-level federation management — master controls, feature toggles, and partnership oversight"
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Federation Status"
          value={controls.federation_enabled ? 'Active' : 'Disabled'}
          icon={Globe}
          color={controls.federation_enabled ? 'success' : 'danger'}
        />
        <StatCard
          label="Whitelisted Tenants"
          value={whitelist.length}
          icon={Shield}
          color="primary"
        />
        <StatCard
          label="Active Partnerships"
          value={activePartnerships}
          icon={Handshake}
          color="secondary"
        />
        <StatCard
          label="System Status"
          value={controls.is_locked_down ? 'LOCKDOWN' : 'Normal'}
          icon={controls.is_locked_down ? Lock : Unlock}
          color={controls.is_locked_down ? 'danger' : 'success'}
        />
      </div>

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
              <div className={`p-2 rounded-lg bg-${link.color}/10`}>
                <link.icon size={20} className={`text-${link.color}`} />
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
      {controls.is_locked_down && (
        <Card className="border-2 border-danger bg-danger-50 dark:bg-danger-950">
          <CardBody className="flex flex-row items-center gap-4">
            <Lock size={24} className="text-danger shrink-0" />
            <div className="flex-1">
              <p className="font-semibold text-danger">Emergency Lockdown Active</p>
              <p className="text-sm text-danger-600 dark:text-danger-400">
                {controls.lockdown_reason || 'All federation features are currently disabled.'}
              </p>
            </div>
            <Button
              color="success"
              variant="solid"
              size="sm"
              startContent={<Unlock size={16} />}
              onPress={() => setLockdownConfirm(true)}
            >
              Lift Lockdown
            </Button>
          </CardBody>
        </Card>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* System Status */}
        <Card>
          <CardHeader className="flex gap-2 items-center pb-0">
            <Globe size={20} className="text-primary" />
            <h3 className="font-semibold text-lg">System Status</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Federation</p>
                <p className="text-xs text-default-500">Enable or disable federation system-wide</p>
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
                <p className="font-medium">Whitelist Mode</p>
                <p className="text-xs text-default-500">Only whitelisted tenants can federate</p>
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
                {controls.is_locked_down ? <Lock size={16} className="text-danger" /> : <Unlock size={16} className="text-success" />}
                <div>
                  <p className="font-medium">Lockdown Status</p>
                  <p className="text-xs text-default-500">Emergency kill switch for all federation</p>
                </div>
              </div>
              <Chip color={controls.is_locked_down ? 'danger' : 'success'} variant="flat" size="sm">
                {controls.is_locked_down ? 'LOCKED DOWN' : 'Normal'}
              </Chip>
            </div>
            {!controls.is_locked_down && (
              <Button
                color="danger"
                variant="flat"
                size="sm"
                startContent={<AlertTriangle size={16} />}
                onPress={() => setLockdownConfirm(true)}
              >
                Emergency Lockdown
              </Button>
            )}
          </CardBody>
        </Card>

        {/* Feature Toggles */}
        <Card>
          <CardHeader className="flex gap-2 items-center pb-0">
            <Shield size={20} className="text-secondary" />
            <h3 className="font-semibold text-lg">Cross-Tenant Features</h3>
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
              <h3 className="font-semibold text-lg">Whitelist</h3>
              <Chip size="sm" variant="flat">{whitelist.length}</Chip>
            </div>
            <Button
              as={Link}
              to={tenantPath('/admin/super/federation/whitelist')}
              size="sm"
              variant="light"
              endContent={<ArrowRight size={14} />}
            >
              Manage
            </Button>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <div className="flex gap-2">
              <Input
                size="sm"
                label="Tenant ID"
                value={addTenantId}
                onValueChange={setAddTenantId}
                className="max-w-[120px]"
                variant="bordered"
              />
              <Button size="sm" color="primary" startContent={<Plus size={14} />} onPress={handleAddWhitelist}>
                Add
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
                  <Button size="sm" variant="light" color="danger" isIconOnly aria-label="Remove from whitelist" onPress={() => handleRemoveWhitelist(entry.tenant_id)}>
                    <Trash2 size={14} />
                  </Button>
                </div>
              ))}
              {whitelist.length === 0 && <p className="text-default-400 text-sm py-4 text-center">No whitelisted tenants</p>}
            </div>
          </CardBody>
        </Card>

        {/* Partnerships */}
        <Card>
          <CardHeader className="flex justify-between items-center pb-0">
            <div className="flex gap-2 items-center">
              <Handshake size={20} className="text-secondary" />
              <h3 className="font-semibold text-lg">Partnerships</h3>
              <Chip size="sm" variant="flat">{partnerships.length}</Chip>
            </div>
            <Button
              as={Link}
              to={tenantPath('/admin/super/federation/partnerships')}
              size="sm"
              variant="light"
              endContent={<ArrowRight size={14} />}
            >
              Manage
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
                        {p.status}
                      </Chip>
                    </div>
                    {p.status === 'active' && (
                      <div className="flex gap-1 shrink-0 ml-2">
                        <Button size="sm" variant="flat" color="warning" onPress={() => setPartnerAction({ type: 'suspend', id: p.id })}>
                          Suspend
                        </Button>
                        <Button size="sm" variant="flat" color="danger" onPress={() => setPartnerAction({ type: 'terminate', id: p.id })}>
                          End
                        </Button>
                      </div>
                    )}
                  </div>
                );
              })}
              {partnerships.length === 0 && <p className="text-default-400 text-sm py-4 text-center">No partnerships</p>}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Lockdown Confirm Modal */}
      <ConfirmModal
        isOpen={lockdownConfirm}
        onClose={() => { setLockdownConfirm(false); setLockdownReason(''); }}
        onConfirm={handleLockdown}
        title={controls.is_locked_down ? 'Lift Lockdown' : 'Emergency Lockdown'}
        message={controls.is_locked_down
          ? 'This will re-enable all federation features. Are you sure?'
          : 'This will immediately disable ALL federation features across ALL tenants. Use only in emergencies.'}
        confirmLabel={controls.is_locked_down ? 'Lift Lockdown' : 'Activate Lockdown'}
        confirmColor={controls.is_locked_down ? 'primary' : 'danger'}
      >
        {!controls.is_locked_down && (
          <Input
            label="Lockdown Reason"
            placeholder="Describe reason for emergency lockdown..."
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
        title={partnerAction ? `${partnerAction.type === 'suspend' ? 'Suspend' : 'Terminate'} Partnership` : ''}
        message={partnerAction?.type === 'suspend'
          ? 'All federation features will be temporarily disabled for this partnership.'
          : 'This will permanently end this partnership. This action cannot be undone.'}
        confirmLabel={partnerAction?.type === 'suspend' ? 'Suspend' : 'Terminate'}
        confirmColor="danger"
      />
    </div>
  );
}

export default FederationControls;
