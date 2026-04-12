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

import { useTranslation } from 'react-i18next';
export function BulkOperations() {
  const { t } = useTranslation('admin');
  usePageTitle(t('super.page_title'));
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
    // Final hard confirmation — destructive cross-tenant move
    const message = t('bulk_operations.final_confirm_move', {
      count: selectedUserIds.size,
      defaultValue: `You are about to move ${selectedUserIds.size} user(s) to another tenant. This action cannot be undone automatically. Continue?`,
    });
    if (typeof window !== 'undefined' && !window.confirm(message)) {
      setMoveConfirm(false);
      return;
    }
    setMoveLoading(true);
    const res = await adminSuper.bulkMoveUsers({
      user_ids: Array.from(selectedUserIds),
      target_tenant_id: Number(targetTenant),
      grant_super_admin: grantSA,
    });
    if (res?.success) {
      const result = res.data as BulkOperationResult | undefined;
      toast.success(t('super.bulk_users_moved', { count: result?.updated_count || selectedUserIds.size }));
      setSelectedUserIds(new Set());
      loadUsersForTenant(sourceTenant);
    } else {
      toast.error(res?.error || t('super.bulk_move_failed'));
    }
    setMoveLoading(false);
    setMoveConfirm(false);
  };

  const handleBulkUpdateTenants = async () => {
    // Final hard confirmation for destructive/impactful bulk updates
    const message = t('bulk_operations.final_confirm_update', {
      action: bulkAction.replace('_', ' '),
      count: selectedTenantIds.size,
      defaultValue: `You are about to ${bulkAction.replace('_', ' ')} ${selectedTenantIds.size} tenant(s). This affects all users on those tenants. Continue?`,
    });
    if (typeof window !== 'undefined' && !window.confirm(message)) {
      setTenantConfirm(false);
      return;
    }
    setTenantLoading(true);
    const res = await adminSuper.bulkUpdateTenants({
      tenant_ids: Array.from(selectedTenantIds),
      action: bulkAction as 'activate' | 'deactivate' | 'enable_hub' | 'disable_hub',
    });
    if (res?.success) {
      const result = res.data as BulkOperationResult | undefined;
      toast.success(t('super.bulk_tenants_updated', { count: result?.updated_count || selectedTenantIds.size }));
      setSelectedTenantIds(new Set());
      loadTenants();
    } else {
      toast.error(res?.error || t('super.bulk_update_failed'));
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
        <span className="text-foreground">Bulk Operations</span>
      </nav>
      <PageHeader title={t('super.bulk_operations_title')} description={t('super.bulk_operations_desc')} />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Bulk Move Users */}
        <Card>
          <CardHeader className="flex flex-col items-start gap-1">
            <div className="flex gap-2 items-center">
              <Users size={20} /> <h3 className="text-lg font-semibold">{t('bulk_operations.bulk_move_users')}</h3>
            </div>
            <p className="text-xs text-default-400">
              {t('bulk_operations.bulk_move_desc')}{' '}
              <Link to={tenantPath('/admin/super/users')} className="text-primary hover:underline">Manage individual users</Link>
            </p>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <Select label={t('super.label_source_tenant')} selectedKeys={sourceTenant ? [sourceTenant] : []}
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
            <Select label={t('super.label_target_tenant')} selectedKeys={targetTenant ? [targetTenant] : []}
              onSelectionChange={(keys) => setTargetTenant(String(Array.from(keys)[0] || ''))}>
              {tenants.filter(t => String(t.id) !== sourceTenant).map(t =>
                <SelectItem key={String(t.id)}>{t.name}</SelectItem>)}
            </Select>

            <div className="bg-purple-50 dark:bg-purple-50/10 border border-purple-200 dark:border-purple-200/20 rounded-lg p-3">
              <Switch
                isSelected={grantSA}
                onValueChange={setGrantSA}
                classNames={{
                  wrapper: 'group-data-[selected=true]:bg-gradient-to-r group-data-[selected=true]:from-purple-500 group-data-[selected=true]:to-pink-500',
                }}
              >
                <div>
                  <p className="text-sm font-medium">{t('bulk_operations.grant_sa_on_arrival')}</p>
                  <p className="text-xs text-default-500 mt-0.5">{t('bulk_operations.grant_sa_on_arrival_desc')}</p>
                </div>
              </Switch>
            </div>

            <Button color="primary" startContent={<ArrowRight size={16} />}
              isDisabled={selectedUserIds.size === 0 || !targetTenant}
              onPress={() => setMoveConfirm(true)}>
              {t('bulk_operations.move_n_users', { count: selectedUserIds.size })}
            </Button>
          </CardBody>
        </Card>

        {/* Bulk Update Tenants */}
        <Card>
          <CardHeader className="flex flex-col items-start gap-1">
            <div className="flex gap-2 items-center">
              <Building2 size={20} /> <h3 className="text-lg font-semibold">{t('bulk_operations.bulk_update_tenants')}</h3>
            </div>
            <p className="text-xs text-default-400">
              {t('bulk_operations.bulk_update_desc')}{' '}
              <Link to={tenantPath('/admin/super/tenants')} className="text-primary hover:underline">Manage individual tenants</Link>
            </p>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="flex items-center justify-between mb-1">
              <p className="text-sm font-medium">{t('bulk_operations.select_tenants')}</p>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="flat"
                  color="primary"
                  startContent={<CheckCheck size={14} />}
                  onPress={selectAllTenants}
                >
                  {t('bulk_operations.select_all')}
                </Button>
                <Button
                  size="sm"
                  variant="flat"
                  color="default"
                  startContent={<XCircle size={14} />}
                  onPress={deselectAllTenants}
                >
                  {t('bulk_operations.deselect_all')}
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
              <p className="text-sm font-medium mb-3">{t('bulk_operations.select_action')}</p>
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
                        <p className="text-sm font-semibold text-success">Activate</p>
                        <p className="text-xs text-default-500">Enable selected tenants</p>
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
                        <p className="text-sm font-semibold text-danger">Deactivate</p>
                        <p className="text-xs text-default-500">Disable selected tenants</p>
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
                        <p className="text-sm font-semibold text-primary">Enable Hub</p>
                        <p className="text-xs text-default-500">Allow sub-tenants</p>
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
                        <p className="text-sm font-semibold text-warning-600 dark:text-warning">Disable Hub</p>
                        <p className="text-xs text-default-500">Remove sub-tenant support</p>
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
              {t('bulk_operations.apply_to_n_tenants', { count: selectedTenantIds.size })}
            </Button>
          </CardBody>
        </Card>
      </div>

      <ConfirmModal isOpen={moveConfirm} onClose={() => setMoveConfirm(false)}
        onConfirm={handleBulkMoveUsers}
        title={t('bulk_operations.confirm_bulk_move')} message={`${t('bulk_operations.move_count', { count: selectedUserIds.size })}${grantSA ? ` ${t('bulk_operations.grant_sa_note')}` : ''}`}
        confirmLabel={t('bulk_operations.move_users')} confirmColor="primary" isLoading={moveLoading} />

      <ConfirmModal isOpen={tenantConfirm} onClose={() => setTenantConfirm(false)}
        onConfirm={handleBulkUpdateTenants}
        title={t('bulk_operations.confirm_bulk_update')} message={t('bulk_operations.apply_action', { action: bulkAction.replace('_', ' '), count: selectedTenantIds.size })}
        confirmLabel={t('bulk_operations.apply')} confirmColor={
          bulkAction === 'activate' ? 'primary' :
          bulkAction === 'deactivate' ? 'danger' :
          'warning'
        } isLoading={tenantLoading} />
    </div>
  );
}
export default BulkOperations;
