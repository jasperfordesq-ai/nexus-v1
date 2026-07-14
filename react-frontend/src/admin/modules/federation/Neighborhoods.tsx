// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { getFormattingLocale } from '@/lib/helpers';
import { Card, CardBody, CardHeader, Button, Spinner, Input, Textarea, Select, SelectItem, useDisclosure, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Avatar } from '@/components/ui';
import { useState, useEffect, useCallback } from 'react';

import { Separator } from '@/components/ui';
import MapPin from 'lucide-react/icons/map-pin';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Trash2 from 'lucide-react/icons/trash-2';
import Users from 'lucide-react/icons/users';
import Calendar from 'lucide-react/icons/calendar';
import Building2 from 'lucide-react/icons/building-2';
import Globe from 'lucide-react/icons/globe';
import UserPlus from 'lucide-react/icons/user-plus';
import X from 'lucide-react/icons/x';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { BrokerEmptyState } from '@/broker/components';
import { PageHeader } from '../../components/PageHeader';
import { ConfirmModal } from '../../components/ConfirmModal';
import { StatCard } from '../../components/StatCard';
import { useTranslation } from 'react-i18next';

/**
 * Federation Neighborhoods (FD2)
 * Admin page for managing neighborhood clusters of tenants.
 * Add/remove tenants from neighborhoods, view stats.
 */


// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Types
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

interface NeighborhoodTenant {
  id: number;
  name: string;
  slug: string;
  member_count: number;
}

interface Neighborhood {
  id: number;
  name: string;
  description?: string;
  tenants: NeighborhoodTenant[];
  total_members: number;
  shared_events_count: number;
  created_at: string;
}

interface AvailableTenant {
  id: number;
  name: string;
  slug: string;
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Component
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

export function Neighborhoods() {
  const { t } = useTranslation('admin_federation');
  usePageTitle(t('federation.page_title'));
  const toast = useToast();
  const createModal = useDisclosure();
  const addTenantModal = useDisclosure();

  const [neighborhoods, setNeighborhoods] = useState<Neighborhood[]>([]);
  const [availableTenants, setAvailableTenants] = useState<AvailableTenant[]>([]);
  const [loading, setLoading] = useState(true);

  // Create form
  const [newName, setNewName] = useState('');
  const [newDescription, setNewDescription] = useState('');
  const [creating, setCreating] = useState(false);

  // Add tenant
  const [addToNeighborhood, setAddToNeighborhood] = useState<Neighborhood | null>(null);
  const [selectedTenantId, setSelectedTenantId] = useState('');
  const [addingTenant, setAddingTenant] = useState(false);
  // Confirm modals
  const [removeTenantTarget, setRemoveTenantTarget] = useState<{ neighborhoodId: number; tenantId: number } | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<number | null>(null);

  // â”€â”€â”€ Load data â”€â”€â”€
  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [neighborhoodsRes, tenantsRes] = await Promise.all([
        api.get('/v2/admin/federation/neighborhoods'),
        api.get('/v2/admin/federation/available-tenants'),
      ]);

      if (neighborhoodsRes.success) {
        const payload = neighborhoodsRes.data;
        setNeighborhoods(
          Array.isArray(payload) ? payload : (payload as { neighborhoods?: Neighborhood[] })?.neighborhoods ?? []
        );
      }

      if (tenantsRes.success) {
        const payload = tenantsRes.data;
        setAvailableTenants(
          Array.isArray(payload) ? payload : (payload as { tenants?: AvailableTenant[] })?.tenants ?? []
        );
      }
    } catch (err) {
      logError('Neighborhoods.load', err);
      toast.error(t('federation.failed_to_load_neighborhoods'));
    }
    setLoading(false);
  }, [t, toast]);

  useEffect(() => { loadData(); }, [loadData]);

  // â”€â”€â”€ Create neighborhood â”€â”€â”€
  const handleCreate = useCallback(async () => {
    if (!newName.trim()) return;
    setCreating(true);
    try {
      const res = await api.post('/v2/admin/federation/neighborhoods', {
        name: newName.trim(),
        description: newDescription.trim() || undefined,
      });
      if (res.success) {
        toast.success(t('federation.neighborhood_created'));
        setNewName('');
        setNewDescription('');
        createModal.onClose();
        loadData();
      }
    } catch (err) {
      logError('Neighborhoods.create', err);
      toast.error(t('federation.failed_to_create_neighborhood'));
    }
    setCreating(false);
  }, [newName, newDescription, t, toast, createModal, loadData]);

  // â”€â”€â”€ Add tenant to neighborhood â”€â”€â”€
  const handleAddTenant = useCallback(async () => {
    if (!addToNeighborhood || !selectedTenantId) return;
    setAddingTenant(true);
    try {
      const res = await api.post(`/v2/admin/federation/neighborhoods/${addToNeighborhood.id}/tenants`, {
        tenant_id: parseInt(selectedTenantId),
      });
      if (res.success) {
        toast.success(t('federation.tenant_added_to_neighborhood'));
        setSelectedTenantId('');
        setAddToNeighborhood(null);
        addTenantModal.onClose();
        loadData();
      }
    } catch (err) {
      logError('Neighborhoods.addTenant', err);
      toast.error(t('federation.failed_to_add_tenant'));
    }
    setAddingTenant(false);
  }, [addToNeighborhood, selectedTenantId, t, toast, addTenantModal, loadData]);

  // â”€â”€â”€ Remove tenant from neighborhood â”€â”€â”€
  const confirmRemoveTenant = useCallback(async () => {
    if (!removeTenantTarget) return;
    const { neighborhoodId, tenantId } = removeTenantTarget;
    try {
      const res = await api.delete(`/v2/admin/federation/neighborhoods/${neighborhoodId}/tenants/${tenantId}`);
      if (res.success) {
        toast.success(t('federation.tenant_removed_from_neighborhood'));
        loadData();
      } else {
        toast.error(t('federation.failed_to_remove_tenant'));
      }
    } catch (err) {
      logError('Neighborhoods.removeTenant', err);
      toast.error(t('federation.failed_to_remove_tenant'));
    }
    setRemoveTenantTarget(null);
  }, [t, toast, loadData, removeTenantTarget]);

  // â”€â”€â”€ Delete neighborhood â”€â”€â”€
  const confirmDelete = useCallback(async () => {
    if (deleteTarget === null) return;
    try {
      const res = await api.delete(`/v2/admin/federation/neighborhoods/${deleteTarget}`);
      if (res.success) {
        toast.success(t('federation.neighborhood_deleted'));
        loadData();
      } else {
        toast.error(t('federation.failed_to_delete_neighborhood'));
      }
    } catch (err) {
      logError('Neighborhoods.delete', err);
      toast.error(t('federation.failed_to_delete_neighborhood'));
    }
    setDeleteTarget(null);
  }, [t, toast, loadData, deleteTarget]);

  // â”€â”€â”€ Stats â”€â”€â”€
  const totalNeighborhoods = neighborhoods.length;
  const totalMembers = neighborhoods.reduce((sum, n) => sum + n.total_members, 0);
  const totalSharedEvents = neighborhoods.reduce((sum, n) => sum + n.shared_events_count, 0);
  const totalTenants = neighborhoods.reduce((sum, n) => sum + n.tenants.length, 0);

  // â”€â”€â”€ Render â”€â”€â”€
  if (loading) {
    return (
      <div>
        <PageHeader title={t('federation.neighborhoods_title')} description={t('federation.neighborhoods_desc')} />
        <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('federation.neighborhoods_title')}
        description={t('federation.neighborhoods_desc')}
        actions={
          <div className="flex items-center gap-2">
            <Button variant="tertiary" size="sm" startContent={<RefreshCw size={16} />} onPress={() => loadData()}>
              {t('common.refresh')}
            </Button>
            <Button size="sm" startContent={<Plus size={16} />} onPress={createModal.onOpen}>
              {t('federation.new_neighborhood')}
            </Button>
          </div>
        }
      />

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard label={t('federation.label_neighborhoods')} value={totalNeighborhoods} icon={MapPin} color="default" />
        <StatCard label={t('federation.label_communities')} value={totalTenants} icon={Building2} color="default" />
        <StatCard label={t('federation.label_total_members')} value={totalMembers} icon={Users} color="success" />
        <StatCard label={t('federation.label_shared_events')} value={totalSharedEvents} icon={Calendar} color="warning" />
      </div>

      {/* Neighborhoods grid */}
      {neighborhoods.length === 0 ? (
        <BrokerEmptyState
          icon={MapPin}
          title={t('federation.no_neighborhoods_yet')}
          hint={t('federation.no_neighborhoods_desc')}
          action={
            <Button startContent={<Plus size={16} />} onPress={createModal.onOpen}>
              {t('federation.create_neighborhood')}
            </Button>
          }
        />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {neighborhoods.map((neighborhood) => (
            <Card key={neighborhood.id}>
              <CardHeader className="flex justify-between items-start">
                <div>
                  <h3 className="text-lg font-semibold flex items-center gap-2">
                    <Globe size={18} className="text-accent" />
                    {neighborhood.name}
                  </h3>
                  {neighborhood.description && (
                    <p className="text-sm text-muted mt-1">{neighborhood.description}</p>
                  )}
                </div>
                <Button
                  size="sm"
                  variant="danger-soft"
                  isIconOnly
                  aria-label={t('federation.label_delete_neighborhood')}
                  onPress={() => setDeleteTarget(neighborhood.id)}
                >
                  <Trash2 size={14} />
                </Button>
              </CardHeader>
              <CardBody className="space-y-3">
                {/* Stats row */}
                <div className="flex items-center gap-4 text-sm text-muted">
                  <span className="flex items-center gap-1">
                    <Building2 size={14} />
                    {neighborhood.tenants.length} {t('federation.label_communities')}
                  </span>
                  <span className="flex items-center gap-1">
                    <Users size={14} />
                    {t('federation.members_count_value', { count: neighborhood.total_members })}
                  </span>
                  <span className="flex items-center gap-1">
                    <Calendar size={14} />
                    {neighborhood.shared_events_count} {t('federation.label_shared_events')}
                  </span>
                </div>

                <Separator />

                {/* Tenants list */}
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-foreground">{t('federation.label_communities')}</span>
                    <Button
                      size="sm"
                      variant="tertiary"
                      startContent={<UserPlus size={14} />}
                      onPress={() => {
                        setAddToNeighborhood(neighborhood);
                        addTenantModal.onOpen();
                      }}
                    >
                      {t('federation.add')}
                    </Button>
                  </div>

                  {neighborhood.tenants.length === 0 ? (
                    <p className="text-sm text-muted py-2">{t('federation.no_communities_in_neighborhood')}</p>
                  ) : (
                    <div className="space-y-1.5">
                      {neighborhood.tenants.map((tenant) => (
                        <div
                          key={tenant.id}
                          className="flex items-center justify-between p-2 rounded-lg bg-surface-secondary hover:bg-surface-tertiary transition-colors"
                        >
                          <div className="flex items-center gap-2">
                            <Avatar
                              name={tenant.name}
                              size="sm"
                              className="w-7 h-7"
                            />
                            <div>
                              <p className="text-sm font-medium">{tenant.name}</p>
                              <p className="text-xs text-muted">
                                {t('federation.members_count_value', { count: tenant.member_count })} {' · '} {tenant.slug}
                              </p>
                            </div>
                          </div>
                          <Button
                            size="sm"
                            variant="danger-soft"
                            isIconOnly
                            aria-label={t('federation.remove_tenant_aria', { name: tenant.name })}
                            onPress={() => setRemoveTenantTarget({ neighborhoodId: neighborhood.id, tenantId: tenant.id })}
                          >
                            <X size={14} />
                          </Button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                <div className="text-xs text-muted pt-1">
                  {t('federation.created_date', { date: new Date(neighborhood.created_at).toLocaleDateString(getFormattingLocale()) })}
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      {/* Create Neighborhood Modal */}
      <Modal isOpen={createModal.isOpen} onOpenChange={createModal.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <MapPin size={20} />
                {t('federation.new_neighborhood')}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('federation.label_name')}
                  placeholder={t('federation.placeholder_neighborhood_name')}
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                />
                <Textarea
                  label={t('federation.label_description')}
                  placeholder={t('federation.placeholder_optional_description_of_this_neighborhood_cluster')}
                  value={newDescription}
                  onChange={(e) => setNewDescription(e.target.value)}
                  minRows={2}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="tertiary" onPress={onClose}>{t('federation.cancel')}</Button>
                <Button
                  isLoading={creating}
                  isDisabled={!newName.trim()}
                  onPress={handleCreate}
                >
                  {t('federation.create')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Add Tenant Modal */}
      <Modal isOpen={addTenantModal.isOpen} onOpenChange={addTenantModal.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <UserPlus size={20} />
                {t('federation.add_community')}
              </ModalHeader>
              <ModalBody>
                <Select
                  label={t('federation.label_select_community')}
                  placeholder={t('federation.placeholder_choose_community')}
                  selectedKeys={selectedTenantId ? [selectedTenantId] : []}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0];
                    setSelectedTenantId(selected ? String(selected) : '');
                  }}
                >
                  {availableTenants
                    .filter((t) => !addToNeighborhood?.tenants.some((nt) => nt.id === t.id))
                    .map((t) => (
                      <SelectItem key={String(t.id)} id={String(t.id)}>{t.name} ({t.slug})</SelectItem>
                    ))}
                </Select>
              </ModalBody>
              <ModalFooter>
                <Button variant="tertiary" onPress={onClose}>{t('federation.cancel')}</Button>
                <Button
                  isLoading={addingTenant}
                  isDisabled={!selectedTenantId}
                  onPress={handleAddTenant}
                >
                  {t('federation.add_community')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Remove tenant confirmation */}
      {removeTenantTarget && (
        <ConfirmModal
          isOpen={!!removeTenantTarget}
          onClose={() => setRemoveTenantTarget(null)}
          onConfirm={confirmRemoveTenant}
          title={t('federation.remove_tenant_title')}
          message={t('federation.confirm_remove_tenant')}
          confirmLabel={t('federation.remove')}
          confirmColor="danger"
        />
      )}

      {/* Delete neighborhood confirmation */}
      {deleteTarget !== null && (
        <ConfirmModal
          isOpen={deleteTarget !== null}
          onClose={() => setDeleteTarget(null)}
          onConfirm={confirmDelete}
          title={t('federation.delete_neighborhood_title')}
          message={t('federation.confirm_delete_neighborhood')}
          confirmLabel={t('federation.delete')}
          confirmColor="danger"
        />
      )}
    </div>
  );
}

export default Neighborhoods;
