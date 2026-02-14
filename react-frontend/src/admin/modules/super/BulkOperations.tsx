import { useState, useEffect, useCallback } from 'react';
import {
  Card, CardBody, CardHeader, Button, Select, SelectItem, Switch, Divider, Chip, Checkbox,
} from '@heroui/react';
import { Users, Building2, ArrowRight } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';
import type { SuperAdminTenant, SuperAdminUser } from '../../api/types';

export function BulkOperations() {
  usePageTitle('Super Admin - Bulk Operations');
  const toast = useToast();

  const [tenants, setTenants] = useState<SuperAdminTenant[]>([]);
  const [users, setUsers] = useState<SuperAdminUser[]>([]);
  const [loading, setLoading] = useState(false);

  // Bulk Move Users state
  const [sourceTenant, setSourceTenant] = useState('');
  const [targetTenant, setTargetTenant] = useState('');
  const [selectedUserIds, setSelectedUserIds] = useState<Set<number>>(new Set());
  const [grantSA, setGrantSA] = useState(false);
  const [moveConfirm, setMoveConfirm] = useState(false);
  const [moveLoading, setMoveLoading] = useState(false);

  // Bulk Update Tenants state
  const [selectedTenantIds, setSelectedTenantIds] = useState<Set<number>>(new Set());
  const [bulkAction, setBulkAction] = useState('');
  const [tenantConfirm, setTenantConfirm] = useState(false);
  const [tenantLoading, setTenantLoading] = useState(false);

  const loadTenants = useCallback(async () => {
    const res = await adminSuper.listTenants();
    if (res.success && res.data) {
      setTenants(Array.isArray(res.data) ? res.data as SuperAdminTenant[] : []);
    }
  }, []);

  useEffect(() => { loadTenants(); }, [loadTenants]);

  const loadUsersForTenant = async (tenantId: string) => {
    if (!tenantId) return;
    setLoading(true);
    const res = await adminSuper.listUsers({ tenant_id: Number(tenantId), limit: 100 });
    if (res.success && res.data) {
      setUsers(Array.isArray(res.data) ? res.data as SuperAdminUser[] : []);
    }
    setLoading(false);
  };

  const handleBulkMoveUsers = async () => {
    setMoveLoading(true);
    const res = await adminSuper.bulkMoveUsers({
      user_ids: Array.from(selectedUserIds),
      target_tenant_id: Number(targetTenant),
      grant_super_admin: grantSA,
    });
    if (res?.success) {
      const data = res.data as unknown as { updated_count?: number };
      toast.success(`Moved ${data?.updated_count || selectedUserIds.size} user(s)`);
      setSelectedUserIds(new Set());
      loadUsersForTenant(sourceTenant);
    } else {
      toast.error(res?.error || 'Bulk move failed');
    }
    setMoveLoading(false);
    setMoveConfirm(false);
  };

  const handleBulkUpdateTenants = async () => {
    setTenantLoading(true);
    const res = await adminSuper.bulkUpdateTenants({
      tenant_ids: Array.from(selectedTenantIds),
      action: bulkAction as 'activate' | 'deactivate' | 'enable_hub' | 'disable_hub',
    });
    if (res?.success) {
      const data = res.data as unknown as { updated_count?: number };
      toast.success(`Updated ${data?.updated_count || selectedTenantIds.size} tenant(s)`);
      setSelectedTenantIds(new Set());
      loadTenants();
    } else {
      toast.error(res?.error || 'Bulk update failed');
    }
    setTenantLoading(false);
    setTenantConfirm(false);
  };

  const toggleUser = (id: number) => {
    setSelectedUserIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  const toggleTenant = (id: number) => {
    setSelectedTenantIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  return (
    <div>
      <PageHeader title="Bulk Operations" description="Move users and update tenants in bulk" />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Bulk Move Users */}
        <Card>
          <CardHeader className="flex gap-2 items-center">
            <Users size={20} /> <h3 className="text-lg font-semibold">Bulk Move Users</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <Select label="Source Tenant" selectedKeys={sourceTenant ? [sourceTenant] : []}
              onSelectionChange={(keys) => {
                const val = String(Array.from(keys)[0] || '');
                setSourceTenant(val);
                setSelectedUserIds(new Set());
                if (val) loadUsersForTenant(val);
              }}>
              {tenants.map(t => <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
            </Select>

            {users.length > 0 && (
              <div className="max-h-48 overflow-y-auto border rounded-lg p-2">
                {users.map(u => (
                  <Checkbox key={u.id} isSelected={selectedUserIds.has(u.id)}
                    onValueChange={() => toggleUser(u.id)} className="w-full py-1">
                    {u.name} <span className="text-default-400 text-xs">({u.email})</span>
                  </Checkbox>
                ))}
              </div>
            )}
            {loading && <p className="text-center text-default-400 text-sm">Loading users...</p>}

            <Divider />
            <Select label="Target Tenant" selectedKeys={targetTenant ? [targetTenant] : []}
              onSelectionChange={(keys) => setTargetTenant(String(Array.from(keys)[0] || ''))}>
              {tenants.filter(t => String(t.id) !== sourceTenant).map(t =>
                <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
            </Select>

            <Switch isSelected={grantSA} onValueChange={setGrantSA}>
              Grant Super Admin on arrival
            </Switch>

            <Button color="primary" startContent={<ArrowRight size={16} />}
              isDisabled={selectedUserIds.size === 0 || !targetTenant}
              onPress={() => setMoveConfirm(true)}>
              Move {selectedUserIds.size} User(s)
            </Button>
          </CardBody>
        </Card>

        {/* Bulk Update Tenants */}
        <Card>
          <CardHeader className="flex gap-2 items-center">
            <Building2 size={20} /> <h3 className="text-lg font-semibold">Bulk Update Tenants</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="max-h-48 overflow-y-auto border rounded-lg p-2">
              {tenants.filter(t => t.id !== 1).map(t => (
                <Checkbox key={t.id} isSelected={selectedTenantIds.has(t.id)}
                  onValueChange={() => toggleTenant(t.id)} className="w-full py-1">
                  {t.name} <Chip size="sm" variant="flat" color={t.is_active ? 'success' : 'default'} className="ml-2">
                    {t.is_active ? 'Active' : 'Inactive'}
                  </Chip>
                </Checkbox>
              ))}
            </div>
            <Divider />
            <Select label="Action" selectedKeys={bulkAction ? [bulkAction] : []}
              onSelectionChange={(keys) => setBulkAction(String(Array.from(keys)[0] || ''))}>
              <SelectItem key="activate">Activate</SelectItem>
              <SelectItem key="deactivate">Deactivate</SelectItem>
              <SelectItem key="enable_hub">Enable Hub</SelectItem>
              <SelectItem key="disable_hub">Disable Hub</SelectItem>
            </Select>
            <Button color="warning" isDisabled={selectedTenantIds.size === 0 || !bulkAction}
              onPress={() => setTenantConfirm(true)}>
              Apply to {selectedTenantIds.size} Tenant(s)
            </Button>
          </CardBody>
        </Card>
      </div>

      <ConfirmModal isOpen={moveConfirm} onClose={() => setMoveConfirm(false)}
        onConfirm={handleBulkMoveUsers}
        title="Confirm Bulk Move" message={`Move ${selectedUserIds.size} user(s) to the selected tenant?`}
        confirmLabel="Move Users" confirmColor="primary" isLoading={moveLoading} />

      <ConfirmModal isOpen={tenantConfirm} onClose={() => setTenantConfirm(false)}
        onConfirm={handleBulkUpdateTenants}
        title="Confirm Bulk Update" message={`Apply '${bulkAction}' to ${selectedTenantIds.size} tenant(s)?`}
        confirmLabel="Apply" confirmColor="warning" isLoading={tenantLoading} />
    </div>
  );
}
export default BulkOperations;
