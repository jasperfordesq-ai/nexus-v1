// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Neighborhoods (FD2)
 * Admin page for managing neighborhood clusters of tenants.
 * Add/remove tenants from neighborhoods, view stats.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Select,
  SelectItem,
  Avatar,
  Divider,
  useDisclosure,
} from '@heroui/react';
import {
  MapPin,
  Plus,
  RefreshCw,
  Trash2,
  Users,
  Calendar,
  Building2,
  Globe,
  UserPlus,
  X,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';
import { PageHeader } from '../../components';
import { StatCard } from '../../components';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

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

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function Neighborhoods() {
  const { t } = useTranslation('admin');
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

  // ─── Load data ───
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
  }, [toast]);

  useEffect(() => { loadData(); }, [loadData]);

  // ─── Create neighborhood ───
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
  }, [newName, newDescription, toast, createModal, loadData]);

  // ─── Add tenant to neighborhood ───
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
  }, [addToNeighborhood, selectedTenantId, toast, addTenantModal, loadData]);

  // ─── Remove tenant from neighborhood ───
  const handleRemoveTenant = useCallback(async (neighborhoodId: number, tenantId: number) => {
    try {
      await api.delete(`/v2/admin/federation/neighborhoods/${neighborhoodId}/tenants/${tenantId}`);
      toast.success(t('federation.tenant_removed_from_neighborhood'));
      loadData();
    } catch (err) {
      logError('Neighborhoods.removeTenant', err);
      toast.error(t('federation.failed_to_remove_tenant'));
    }
  }, [toast, loadData]);

  // ─── Delete neighborhood ───
  const handleDelete = useCallback(async (neighborhoodId: number) => {
    try {
      await api.delete(`/v2/admin/federation/neighborhoods/${neighborhoodId}`);
      toast.success(t('federation.neighborhood_deleted'));
      loadData();
    } catch (err) {
      logError('Neighborhoods.delete', err);
      toast.error(t('federation.failed_to_delete_neighborhood'));
    }
  }, [toast, loadData]);

  // ─── Stats ───
  const totalNeighborhoods = neighborhoods.length;
  const totalMembers = neighborhoods.reduce((sum, n) => sum + n.total_members, 0);
  const totalSharedEvents = neighborhoods.reduce((sum, n) => sum + n.shared_events_count, 0);
  const totalTenants = neighborhoods.reduce((sum, n) => sum + n.tenants.length, 0);

  // ─── Render ───
  if (loading) {
    return (
      <div>
        <PageHeader title={t('federation.neighborhoods_title')} description={t('federation.neighborhoods_desc')} />
        <div className="flex h-64 items-center justify-center">
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
            <Button variant="flat" size="sm" startContent={<RefreshCw size={16} />} onPress={() => loadData()}>
              {t('federation.refresh')}
            </Button>
            <Button color="primary" size="sm" startContent={<Plus size={16} />} onPress={createModal.onOpen}>
              {t('federation.new_neighborhood')}
            </Button>
          </div>
        }
      />

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard label={t('federation.label_neighborhoods')} value={totalNeighborhoods} icon={MapPin} color="primary" />
        <StatCard label={t('federation.label_communities')} value={totalTenants} icon={Building2} color="secondary" />
        <StatCard label={t('federation.label_total_members')} value={totalMembers} icon={Users} color="success" />
        <StatCard label={t('federation.label_shared_events')} value={totalSharedEvents} icon={Calendar} color="warning" />
      </div>

      {/* Neighborhoods grid */}
      {neighborhoods.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center py-12 text-default-400">
            <MapPin size={48} className="mb-4" />
            <p className="text-lg font-medium">{t('federation.no_neighborhoods_yet')}</p>
            <p className="text-sm">{t('federation.no_neighborhoods_desc')}</p>
            <Button
              color="primary"
              className="mt-4"
              startContent={<Plus size={16} />}
              onPress={createModal.onOpen}
            >
              {t('federation.create_neighborhood')}
            </Button>
          </CardBody>
        </Card>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {neighborhoods.map((neighborhood) => (
            <Card key={neighborhood.id} shadow="sm">
              <CardHeader className="flex justify-between items-start">
                <div>
                  <h3 className="text-lg font-semibold flex items-center gap-2">
                    <Globe size={18} className="text-primary" />
                    {neighborhood.name}
                  </h3>
                  {neighborhood.description && (
                    <p className="text-sm text-default-500 mt-1">{neighborhood.description}</p>
                  )}
                </div>
                <Button
                  size="sm"
                  variant="flat"
                  color="danger"
                  isIconOnly
                  aria-label={t('federation.label_delete_neighborhood')}
                  onPress={() => handleDelete(neighborhood.id)}
                >
                  <Trash2 size={14} />
                </Button>
              </CardHeader>
              <CardBody className="space-y-3">
                {/* Stats row */}
                <div className="flex items-center gap-4 text-sm text-default-500">
                  <span className="flex items-center gap-1">
                    <Building2 size={14} />
                    {t('federation.communities_count', { count: neighborhood.tenants.length })}
                  </span>
                  <span className="flex items-center gap-1">
                    <Users size={14} />
                    {t('federation.members_count', { count: neighborhood.total_members })}
                  </span>
                  <span className="flex items-center gap-1">
                    <Calendar size={14} />
                    {t('federation.shared_events_count', { count: neighborhood.shared_events_count })}
                  </span>
                </div>

                <Divider />

                {/* Tenants list */}
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-default-600">{t('federation.communities')}</span>
                    <Button
                      size="sm"
                      variant="flat"
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
                    <p className="text-sm text-default-400 py-2">{t('federation.no_communities_in_neighborhood')}</p>
                  ) : (
                    <div className="space-y-1.5">
                      {neighborhood.tenants.map((tenant) => (
                        <div
                          key={tenant.id}
                          className="flex items-center justify-between p-2 rounded-lg bg-default-100 hover:bg-default-200 transition-colors"
                        >
                          <div className="flex items-center gap-2">
                            <Avatar
                              name={tenant.name}
                              size="sm"
                              className="w-7 h-7"
                            />
                            <div>
                              <p className="text-sm font-medium">{tenant.name}</p>
                              <p className="text-xs text-default-400">
                                {t('federation.members_count', { count: tenant.member_count })} · {tenant.slug}
                              </p>
                            </div>
                          </div>
                          <Button
                            size="sm"
                            variant="light"
                            isIconOnly
                            color="danger"
                            aria-label={t('federation.remove_name', { name: tenant.name })}
                            onPress={() => handleRemoveTenant(neighborhood.id, tenant.id)}
                          >
                            <X size={14} />
                          </Button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                <div className="text-xs text-default-400 pt-1">
                  {t('federation.created_time', { time: formatRelativeTime(neighborhood.created_at) })}
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
                <Button variant="flat" onPress={onClose}>{t('federation.cancel')}</Button>
                <Button
                  color="primary"
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
                {t('federation.add_community_to', { name: addToNeighborhood?.name })}
              </ModalHeader>
              <ModalBody>
                <Select
                  label={t('federation.label_select_community')}
                  placeholder={t('federation.placeholder_choose_a_community_to_add')}
                  selectedKeys={selectedTenantId ? [selectedTenantId] : []}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0];
                    setSelectedTenantId(selected ? String(selected) : '');
                  }}
                >
                  {availableTenants
                    .filter((t) => !addToNeighborhood?.tenants.some((nt) => nt.id === t.id))
                    .map((t) => (
                      <SelectItem key={String(t.id)}>{t.name} ({t.slug})</SelectItem>
                    ))}
                </Select>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('federation.cancel')}</Button>
                <Button
                  color="primary"
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
    </div>
  );
}

export default Neighborhoods;
