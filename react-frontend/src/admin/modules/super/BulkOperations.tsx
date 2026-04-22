// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  Card, CardBody, CardHeader, Button, Select, SelectItem, Switch, Divider, Chip, Checkbox, RadioGroup, Radio,
} from '@heroui/react';
import { Users, Building2, ArrowRight, CheckCheck, XCircle } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';
import type { SuperAdminTenant, SuperAdminUser, BulkOperationResult } from '../../api/types';

export function BulkOperations() {
  usePageTitle("Super Admin");
  const toast = useToast();
  const { tenantPath } = useTenant();

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
      const result = res.data as BulkOperationResult | undefined;
      toast.success(`Bulk Users Moved`);
      setSelectedUserIds(new Set());
      loadUsersForTenant(sourceTenant);
    } else {
      toast.error(res?.error || "Bulk Move failed");
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
      const result = res.data as BulkOperationResult | undefined;
      toast.success(`Bulk Tenants updated`);
      setSelectedTenantIds(new Set());
      loadTenants();
    } else {
      toast.error(res?.error || "Bulk Update failed");
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

  const selectAllTenants = () => {
    setSelectedTenantIds(new Set(tenants.filter(t => t.id !== 1).map(t => t.id)));
  };

  const deselectAllTenants = () => {
    setSelectedTenantIds(new Set());
  };

  const availableTenants = tenants.filter(t => t.id !== 1);

  return (
    <div>
      <nav className="flex items-center gap-1 text-sm text-default-500 mb-1">
        <Link to={tenantPath('/admin/super')} className="hover:text-primary">Super Admin</Link>
        <span>/</span>
        <span className="text-foreground">{"Bulk Operations"}</span>
      </nav>
      <PageHeader title={"Bulk Operations"} description={"Perform bulk operations across multiple tenants"} />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Bulk Move Users */}
        <Card>
          <CardHeader className="flex flex-col items-start gap-1">
            <div className="flex gap-2 items-center">
              <Users size={20} /> <h3 className="text-lg font-semibold">{"Bulk Move Users"}</h3>
            </div>
            <p className="text-xs text-default-400">
              {"Bulk Move."}{' '}
              <Link to={tenantPath('/admin/super/users')} className="text-primary hover:underline">{"Manage Individual Users"}</Link>
            </p>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <Select label={"Source Tenant"} selectedKeys={sourceTenant ? [sourceTenant] : []}
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
            {loading && <p className="text-center text-default-400 text-sm">{"Loading users..."}</p>}

            <Divider />
            <Select label={"Target Tenant"} selectedKeys={targetTenant ? [targetTenant] : []}
              onSelectionChange={(keys) => setTargetTenant(String(Array.from(keys)[0] || ''))}>
              {tenants.filter(t => String(t.id) !== sourceTenant).map(t =>
                <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
            </Select>

            <div className="bg-secondary-50 border border-secondary-200 text-secondary-700 rounded-medium p-3">
              <Switch
                isSelected={grantSA}
                onValueChange={setGrantSA}
                classNames={{
                  wrapper: 'group-data-[selected=true]:bg-gradient-to-r group-data-[selected=true]:from-purple-500 group-data-[selected=true]:to-pink-500',
                }}
              >
                <div>
                  <p className="text-sm font-medium">{"Grant Sa on Arrival"}</p>
                  <p className="text-xs text-default-500 mt-0.5">{"Grant Sa on Arrival."}</p>
                </div>
              </Switch>
            </div>

            <Button color="primary" startContent={<ArrowRight size={16} />}
              isDisabled={selectedUserIds.size === 0 || !targetTenant}
              onPress={() => setMoveConfirm(true)}>
              {`Move N Users`}
            </Button>
          </CardBody>
        </Card>

        {/* Bulk Update Tenants */}
        <Card>
          <CardHeader className="flex flex-col items-start gap-1">
            <div className="flex gap-2 items-center">
              <Building2 size={20} /> <h3 className="text-lg font-semibold">{"Bulk Update Tenants"}</h3>
            </div>
            <p className="text-xs text-default-400">
              {"Bulk Update."}{' '}
              <Link to={tenantPath('/admin/super/tenants')} className="text-primary hover:underline">{"Manage Individual Tenants"}</Link>
            </p>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="flex items-center justify-between mb-1">
              <p className="text-sm font-medium">{"Select Tenants"}</p>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="flat"
                  color="primary"
                  startContent={<CheckCheck size={14} />}
                  onPress={selectAllTenants}
                >
                  {"Select All"}
                </Button>
                <Button
                  size="sm"
                  variant="flat"
                  color="default"
                  startContent={<XCircle size={14} />}
                  onPress={deselectAllTenants}
                >
                  {"Deselect All"}
                </Button>
              </div>
            </div>
            <div className="max-h-48 overflow-y-auto border rounded-lg p-2">
              {availableTenants.map(t => (
                <Checkbox key={t.id} isSelected={selectedTenantIds.has(t.id)}
                  onValueChange={() => toggleTenant(t.id)} className="w-full py-1">
                  {t.name} <Chip size="sm" variant="flat" color={t.is_active ? 'success' : 'default'} className="ml-2">
                    {t.is_active ? 'Active' : 'Inactive'}
                  </Chip>
                </Checkbox>
              ))}
            </div>
            <Divider />
            <div>
              <p className="text-sm font-medium mb-3">{"Select"}</p>
              <RadioGroup value={bulkAction} onValueChange={setBulkAction}>
                <div className="grid grid-cols-2 gap-3">
                  <div
                    onClick={() => setBulkAction('activate')}
                    className={`cursor-pointer border-2 rounded-lg p-3 transition-all ${
                      bulkAction === 'activate'
                        ? 'border-success bg-success/10'
                        : 'border-default-200 hover:border-success/50'
                    }`}
                  >
                    <Radio value="activate" classNames={{ wrapper: 'hidden' }}>
                      <div className="flex flex-col gap-1">
                        <p className="text-sm font-semibold text-success">{"Activate"}</p>
                        <p className="text-xs text-default-500">{"Activate Desc"}</p>
                      </div>
                    </Radio>
                  </div>
                  <div
                    onClick={() => setBulkAction('deactivate')}
                    className={`cursor-pointer border-2 rounded-lg p-3 transition-all ${
                      bulkAction === 'deactivate'
                        ? 'border-danger bg-danger/10'
                        : 'border-default-200 hover:border-danger/50'
                    }`}
                  >
                    <Radio value="deactivate" classNames={{ wrapper: 'hidden' }}>
                      <div className="flex flex-col gap-1">
                        <p className="text-sm font-semibold text-danger">{"Deactivate"}</p>
                        <p className="text-xs text-default-500">{"Deactivate Desc"}</p>
                      </div>
                    </Radio>
                  </div>
                  <div
                    onClick={() => setBulkAction('enable_hub')}
                    className={`cursor-pointer border-2 rounded-lg p-3 transition-all ${
                      bulkAction === 'enable_hub'
                        ? 'border-primary bg-primary/10'
                        : 'border-default-200 hover:border-primary/50'
                    }`}
                  >
                    <Radio value="enable_hub" classNames={{ wrapper: 'hidden' }}>
                      <div className="flex flex-col gap-1">
                        <p className="text-sm font-semibold text-primary">{"Enable Hub"}</p>
                        <p className="text-xs text-default-500">{"Enable Hub Desc"}</p>
                      </div>
                    </Radio>
                  </div>
                  <div
                    onClick={() => setBulkAction('disable_hub')}
                    className={`cursor-pointer border-2 rounded-lg p-3 transition-all ${
                      bulkAction === 'disable_hub'
                        ? 'border-warning bg-warning/10'
                        : 'border-default-200 hover:border-warning/50'
                    }`}
                  >
                    <Radio value="disable_hub" classNames={{ wrapper: 'hidden' }}>
                      <div className="flex flex-col gap-1">
                        <p className="text-sm font-semibold text-warning-600 dark:text-warning">{"Disable Hub"}</p>
                        <p className="text-xs text-default-500">{"Disable Hub Desc"}</p>
                      </div>
                    </Radio>
                  </div>
                </div>
              </RadioGroup>
            </div>
            <Button
              color={
                bulkAction === 'activate' ? 'success' :
                bulkAction === 'deactivate' ? 'danger' :
                bulkAction === 'enable_hub' ? 'primary' :
                bulkAction === 'disable_hub' ? 'warning' :
                'default'
              }
              isDisabled={selectedTenantIds.size === 0 || !bulkAction}
              onPress={() => setTenantConfirm(true)}
            >
              {`Apply to N Tenants`}
            </Button>
          </CardBody>
        </Card>
      </div>

      <ConfirmModal isOpen={moveConfirm} onClose={() => setMoveConfirm(false)}
        onConfirm={handleBulkMoveUsers}
        title={"Are you sure you want to bulk move?"} message={`${`Move Count`}${grantSA ? ` ${"Grant Sa"}` : ''}`}
        confirmLabel={"Move Users"} confirmColor="primary" isLoading={moveLoading} />

      <ConfirmModal isOpen={tenantConfirm} onClose={() => setTenantConfirm(false)}
        onConfirm={handleBulkUpdateTenants}
        title={"Are you sure you want to bulk update?"} message={`Apply`}
        confirmLabel={"Apply"} confirmColor={
          bulkAction === 'activate' ? 'primary' :
          bulkAction === 'deactivate' ? 'danger' :
          'warning'
        } isLoading={tenantLoading} />
    </div>
  );
}
export default BulkOperations;
