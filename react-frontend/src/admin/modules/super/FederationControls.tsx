import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Card, CardBody, CardHeader, Button, Switch, Chip, Divider, Input,
} from '@heroui/react';
import { Globe, Shield, Lock, Unlock, AlertTriangle, Network, Trash2, Plus, Activity } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader, ConfirmModal, StatusBadge } from '../../components';
import type { FederationSystemControls, FederationWhitelistEntry, FederationPartnership } from '../../api/types';

export function FederationControls() {
  usePageTitle('Super Admin - Federation Controls');
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [controls, setControls] = useState<FederationSystemControls | null>(null);
  const [whitelist, setWhitelist] = useState<FederationWhitelistEntry[]>([]);
  const [partnerships, setPartnerships] = useState<FederationPartnership[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [lockdownConfirm, setLockdownConfirm] = useState(false);
  const [lockdownReason, setLockdownReason] = useState('');
  const [addTenantId, setAddTenantId] = useState('');
  const [partnerAction, setPartnerAction] = useState<{ type: 'suspend' | 'terminate'; id: number } | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    const [ctrlRes, wlRes, pRes] = await Promise.all([
      adminSuper.getSystemControls(),
      adminSuper.getWhitelist(),
      adminSuper.getFederationPartnerships(),
    ]);
    if (ctrlRes.success && ctrlRes.data) setControls(ctrlRes.data);
    if (wlRes.success && wlRes.data) setWhitelist(Array.isArray(wlRes.data) ? wlRes.data : []);
    if (pRes.success && pRes.data) setPartnerships(Array.isArray(pRes.data) ? pRes.data : []);
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const updateControl = async (key: string, value: boolean | number) => {
    setSaving(true);
    const res = await adminSuper.updateSystemControls({ [key]: value });
    if (res?.success) {
      toast.success('Setting updated');
      setControls(prev => prev ? { ...prev, [key]: value } : prev);
    } else {
      toast.error('Failed to update');
    }
    setSaving(false);
  };

  const handleLockdown = async () => {
    if (controls?.is_locked_down) {
      const res = await adminSuper.liftLockdown();
      if (res?.success) { toast.success('Lockdown lifted'); loadData(); }
      else toast.error('Failed to lift lockdown');
    } else {
      const res = await adminSuper.emergencyLockdown(lockdownReason || 'Emergency lockdown');
      if (res?.success) { toast.success('Lockdown activated'); loadData(); }
      else toast.error('Failed to activate lockdown');
    }
    setLockdownConfirm(false);
  };

  const handleAddWhitelist = async () => {
    if (!addTenantId) return;
    const res = await adminSuper.addToWhitelist(Number(addTenantId));
    if (res?.success) { toast.success('Added to whitelist'); setAddTenantId(''); loadData(); }
    else toast.error('Failed to add');
  };

  const handleRemoveWhitelist = async (tenantId: number) => {
    const res = await adminSuper.removeFromWhitelist(tenantId);
    if (res?.success) { toast.success('Removed from whitelist'); loadData(); }
    else toast.error('Failed to remove');
  };

  const handlePartnerAction = async () => {
    if (!partnerAction) return;
    const res = partnerAction.type === 'suspend'
      ? await adminSuper.suspendPartnership(partnerAction.id, 'Suspended by super admin')
      : await adminSuper.terminatePartnership(partnerAction.id, 'Terminated by super admin');
    if (res?.success) { toast.success(`Partnership ${partnerAction.type}d`); loadData(); }
    else toast.error('Action failed');
    setPartnerAction(null);
  };

  if (loading || !controls) return <div className="p-8 text-center text-default-400">Loading federation controls...</div>;

  type BooleanControlKey = Exclude<{
    [K in keyof FederationSystemControls]: FederationSystemControls[K] extends boolean ? K : never;
  }[keyof FederationSystemControls], undefined>;

  const featureToggles: Array<{ key: BooleanControlKey; label: string }> = [
    { key: 'cross_tenant_profiles_enabled', label: 'Cross-Tenant Profiles' },
    { key: 'cross_tenant_messaging_enabled', label: 'Cross-Tenant Messaging' },
    { key: 'cross_tenant_transactions_enabled', label: 'Cross-Tenant Transactions' },
    { key: 'cross_tenant_listings_enabled', label: 'Cross-Tenant Listings' },
    { key: 'cross_tenant_events_enabled', label: 'Cross-Tenant Events' },
    { key: 'cross_tenant_groups_enabled', label: 'Cross-Tenant Groups' },
  ];

  return (
    <div>
      <nav className="flex items-center gap-1 text-sm text-default-500 mb-1">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">Super Admin</Link>
        <span>/</span>
        <span className="text-foreground">Federation Controls</span>
      </nav>
      <PageHeader
        title="Federation Control Center"
        description="System-level federation management"
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/super/audit')}
            variant="flat"
            size="sm"
            startContent={<Activity size={16} />}
          >
            Federation Audit Log
          </Button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* System Status */}
        <Card>
          <CardHeader className="flex gap-2 items-center">
            <Globe size={20} /> <h3 className="font-semibold">System Status</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
              <span>Federation</span>
              <Switch isSelected={controls.federation_enabled} isDisabled={saving}
                onValueChange={(v) => updateControl('federation_enabled', v)} />
            </div>
            <div className="flex items-center justify-between">
              <span>Whitelist Mode</span>
              <Switch isSelected={controls.whitelist_mode_enabled} isDisabled={saving}
                onValueChange={(v) => updateControl('whitelist_mode_enabled', v)} />
            </div>
            <Divider />
            <div className="flex items-center justify-between">
              <span className="flex items-center gap-2">
                {controls.is_locked_down ? <Lock size={16} className="text-danger" /> : <Unlock size={16} className="text-success" />}
                Lockdown Status
              </span>
              <Chip color={controls.is_locked_down ? 'danger' : 'success'} variant="flat">
                {controls.is_locked_down ? 'LOCKED DOWN' : 'Normal'}
              </Chip>
            </div>
            <Button color={controls.is_locked_down ? 'success' : 'danger'} variant="flat"
              startContent={controls.is_locked_down ? <Unlock size={16} /> : <AlertTriangle size={16} />}
              onPress={() => setLockdownConfirm(true)}>
              {controls.is_locked_down ? 'Lift Lockdown' : 'Emergency Lockdown'}
            </Button>
          </CardBody>
        </Card>

        {/* Feature Toggles */}
        <Card>
          <CardHeader className="flex gap-2 items-center">
            <Shield size={20} /> <h3 className="font-semibold">Feature Controls</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            {featureToggles.map(({ key, label }) => (
              <div key={key} className="flex items-center justify-between">
                <span className="text-sm">{label}</span>
                <Switch size="sm" isSelected={controls[key]}
                  isDisabled={saving}
                  onValueChange={(v) => updateControl(key, v)} />
              </div>
            ))}
          </CardBody>
        </Card>

        {/* Whitelist */}
        <Card>
          <CardHeader className="flex gap-2 items-center">
            <Network size={20} /> <h3 className="font-semibold">Whitelist ({whitelist.length})</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <div className="flex gap-2">
              <Input size="sm" label="Tenant ID" value={addTenantId} onValueChange={setAddTenantId} className="max-w-[120px]" />
              <Button size="sm" color="primary" startContent={<Plus size={14} />} onPress={handleAddWhitelist}>Add</Button>
            </div>
            {whitelist.map((entry) => (
              <div key={entry.tenant_id} className="flex items-center justify-between py-1">
                <span>
                  <Link to={tenantPath(`/admin/super/tenants/${entry.tenant_id}`)} className="hover:text-primary">
                    {entry.tenant_name}
                  </Link>
                  {' '}<span className="text-xs text-default-400">(ID: {entry.tenant_id})</span>
                </span>
                <Button size="sm" variant="light" color="danger" isIconOnly onPress={() => handleRemoveWhitelist(entry.tenant_id)}>
                  <Trash2 size={14} />
                </Button>
              </div>
            ))}
            {whitelist.length === 0 && <p className="text-default-400 text-sm">No whitelisted tenants</p>}
          </CardBody>
        </Card>

        {/* Partnerships */}
        <Card>
          <CardHeader className="flex gap-2 items-center">
            <Network size={20} /> <h3 className="font-semibold">Partnerships ({partnerships.length})</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-2">
            {partnerships.map((p) => (
              <div key={p.id} className="flex items-center justify-between py-2 border-b last:border-b-0">
                <div>
                  <Link to={tenantPath(`/admin/super/tenants/${p.tenant_1_id}`)} className="font-medium hover:text-primary">
                    {p.tenant_1_name}
                  </Link>
                  <span className="text-default-400 mx-2">&harr;</span>
                  <Link to={tenantPath(`/admin/super/tenants/${p.tenant_2_id}`)} className="font-medium hover:text-primary">
                    {p.tenant_2_name}
                  </Link>
                  <StatusBadge status={p.status} />
                </div>
                {p.status === 'active' && (
                  <div className="flex gap-1">
                    <Button size="sm" variant="flat" color="warning" onPress={() => setPartnerAction({ type: 'suspend', id: p.id })}>Suspend</Button>
                    <Button size="sm" variant="flat" color="danger" onPress={() => setPartnerAction({ type: 'terminate', id: p.id })}>Terminate</Button>
                  </div>
                )}
              </div>
            ))}
            {partnerships.length === 0 && <p className="text-default-400 text-sm">No partnerships</p>}
          </CardBody>
        </Card>
      </div>

      <ConfirmModal isOpen={lockdownConfirm} onClose={() => { setLockdownConfirm(false); setLockdownReason(''); }}
        onConfirm={handleLockdown}
        title={controls.is_locked_down ? 'Lift Lockdown' : 'Emergency Lockdown'}
        message={controls.is_locked_down ? 'Lift the emergency lockdown?' : 'This will immediately disable ALL federation features.'}
        confirmLabel={controls.is_locked_down ? 'Lift' : 'Activate Lockdown'}
        confirmColor={controls.is_locked_down ? 'primary' : 'danger'}>
        {!controls.is_locked_down && (
          <Input
            label="Lockdown Reason"
            placeholder="Describe reason for emergency lockdown..."
            value={lockdownReason}
            onValueChange={setLockdownReason}
            className="mt-3"
          />
        )}
      </ConfirmModal>

      <ConfirmModal isOpen={!!partnerAction} onClose={() => setPartnerAction(null)}
        onConfirm={handlePartnerAction}
        title={partnerAction ? `${partnerAction.type === 'suspend' ? 'Suspend' : 'Terminate'} Partnership` : ''}
        message="Are you sure?"
        confirmLabel={partnerAction?.type === 'suspend' ? 'Suspend' : 'Terminate'}
        confirmColor="danger" />
    </div>
  );
}
export default FederationControls;
