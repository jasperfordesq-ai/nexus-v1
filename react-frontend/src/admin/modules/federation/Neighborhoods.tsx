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
import { PageHeader, ConfirmModal } from '../../components';
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
  usePageTitle("Federation");
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
      toast.error("Failed to load neighborhoods");
    }
    setLoading(false);
  }, [t, toast]);

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
        toast.success("Neighborhood Created");
        setNewName('');
        setNewDescription('');
        createModal.onClose();
        loadData();
      }
    } catch (err) {
      logError('Neighborhoods.create', err);
      toast.error("Failed to create neighborhood");
    }
    setCreating(false);
  }, [t, newName, newDescription, toast, createModal, loadData]);

  // ─── Add tenant to neighborhood ───
  const handleAddTenant = useCallback(async () => {
    if (!addToNeighborhood || !selectedTenantId) return;
    setAddingTenant(true);
    try {
      const res = await api.post(`/v2/admin/federation/neighborhoods/${addToNeighborhood.id}/tenants`, {
        tenant_id: parseInt(selectedTenantId),
      });
      if (res.success) {
        toast.success("Tenant Added to Neighborhood");
        setSelectedTenantId('');
        setAddToNeighborhood(null);
        addTenantModal.onClose();
        loadData();
      }
    } catch (err) {
      logError('Neighborhoods.addTenant', err);
      toast.error("Failed to add tenant");
    }
    setAddingTenant(false);
  }, [t, addToNeighborhood, selectedTenantId, toast, addTenantModal, loadData]);

  // ─── Remove tenant from neighborhood ───
  const confirmRemoveTenant = useCallback(async () => {
    if (!removeTenantTarget) return;
    const { neighborhoodId, tenantId } = removeTenantTarget;
    try {
      const res = await api.delete(`/v2/admin/federation/neighborhoods/${neighborhoodId}/tenants/${tenantId}`);
      if (res.success) {
        toast.success("Tenant Removed from Neighborhood");
        loadData();
      } else {
        toast.error(res.error || "Failed to remove tenant");
      }
    } catch (err) {
      logError('Neighborhoods.removeTenant', err);
      toast.error("Failed to remove tenant");
    }
    setRemoveTenantTarget(null);
  }, [t, toast, loadData, removeTenantTarget]);

  // ─── Delete neighborhood ───
  const confirmDelete = useCallback(async () => {
    if (deleteTarget === null) return;
    try {
      const res = await api.delete(`/v2/admin/federation/neighborhoods/${deleteTarget}`);
      if (res.success) {
        toast.success("Neighborhood Deleted");
        loadData();
      } else {
        toast.error(res.error || "Failed to delete neighborhood");
      }
    } catch (err) {
      logError('Neighborhoods.delete', err);
      toast.error("Failed to delete neighborhood");
    }
    setDeleteTarget(null);
  }, [t, toast, loadData, deleteTarget]);

  // ─── Stats ───
  const totalNeighborhoods = neighborhoods.length;
  const totalMembers = neighborhoods.reduce((sum, n) => sum + n.total_members, 0);
  const totalSharedEvents = neighborhoods.reduce((sum, n) => sum + n.shared_events_count, 0);
  const totalTenants = neighborhoods.reduce((sum, n) => sum + n.tenants.length, 0);

  // ─── Render ───
  if (loading) {
    return (
      <div>
        <PageHeader title={"Neighborhoods"} description={"Manage neighborhood clusters that group related communities together"} />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={"Neighborhoods"}
        description={"Manage neighborhood clusters that group related communities together"}
        actions={
          <div className="flex items-center gap-2">
            <Button variant="flat" size="sm" startContent={<RefreshCw size={16} />} onPress={() => loadData()}>
              {"Refresh"}
            </Button>
            <Button color="primary" size="sm" startContent={<Plus size={16} />} onPress={createModal.onOpen}>
              {"New Neighborhood"}
            </Button>
          </div>
        }
      />

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard label={"Neighborhoods"} value={totalNeighborhoods} icon={MapPin} color="primary" />
        <StatCard label={"Communities"} value={totalTenants} icon={Building2} color="secondary" />
        <StatCard label={"Total Members"} value={totalMembers} icon={Users} color="success" />
        <StatCard label={"Shared Events"} value={totalSharedEvents} icon={Calendar} color="warning" />
      </div>

      {/* Neighborhoods grid */}
      {neighborhoods.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center py-12 text-default-400">
            <MapPin size={48} className="mb-4" />
            <p className="text-lg font-medium">{"No neighborhoods yet"}</p>
            <p className="text-sm">{"No neighborhoods have been created yet"}</p>
            <Button
              color="primary"
              className="mt-4"
              startContent={<Plus size={16} />}
              onPress={createModal.onOpen}
            >
              {"Create Neighborhood"}
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
                  aria-label={"Delete Neighborhood"}
                  onPress={() => setDeleteTarget(neighborhood.id)}
                >
                  <Trash2 size={14} />
                </Button>
              </CardHeader>
              <CardBody className="space-y-3">
                {/* Stats row */}
                <div className="flex items-center gap-4 text-sm text-default-500">
                  <span className="flex items-center gap-1">
                    <Building2 size={14} />
                    {`Communities`}
                  </span>
                  <span className="flex items-center gap-1">
                    <Users size={14} />
                    {`Members`}
                  </span>
                  <span className="flex items-center gap-1">
                    <Calendar size={14} />
                    {`Shared Events`}
                  </span>
                </div>

                <Divider />

                {/* Tenants list */}
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-default-600">{"Communities"}</span>
                    <Button
                      size="sm"
                      variant="flat"
                      startContent={<UserPlus size={14} />}
                      onPress={() => {
                        setAddToNeighborhood(neighborhood);
                        addTenantModal.onOpen();
                      }}
                    >
                      {"Add"}
                    </Button>
                  </div>

                  {neighborhood.tenants.length === 0 ? (
                    <p className="text-sm text-default-400 py-2">{"No communities in neighborhood"}</p>
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
                                {`Members`} · {tenant.slug}
                              </p>
                            </div>
                          </div>
                          <Button
                            size="sm"
                            variant="light"
                            isIconOnly
                            color="danger"
                            aria-label={`Remove Name`}
                            onPress={() => setRemoveTenantTarget({ neighborhoodId: neighborhood.id, tenantId: tenant.id })}
                          >
                            <X size={14} />
                          </Button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                <div className="text-xs text-default-400 pt-1">
                  {`Created Time`}
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
                {"New Neighborhood"}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={"Name"}
                  placeholder={"Neighborhood Name..."}
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                />
                <Textarea
                  label={"Description"}
                  placeholder={"Optional Description of This Neighborhood Cluster..."}
                  value={newDescription}
                  onChange={(e) => setNewDescription(e.target.value)}
                  minRows={2}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{"Cancel"}</Button>
                <Button
                  color="primary"
                  isLoading={creating}
                  isDisabled={!newName.trim()}
                  onPress={handleCreate}
                >
                  {"Create"}
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
                {`Add Community`}
              </ModalHeader>
              <ModalBody>
                <Select
                  label={"Select Community"}
                  placeholder={"Choose a Community to Add..."}
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
                <Button variant="flat" onPress={onClose}>{"Cancel"}</Button>
                <Button
                  color="primary"
                  isLoading={addingTenant}
                  isDisabled={!selectedTenantId}
                  onPress={handleAddTenant}
                >
                  {"Add Community"}
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
          title={t('federation.remove_tenant_title', 'Remove Tenant')}
          message={t('federation.confirm_remove_tenant', 'Are you sure you want to remove this tenant from the neighborhood?')}
          confirmLabel={t('federation.remove', 'Remove')}
          confirmColor="danger"
        />
      )}

      {/* Delete neighborhood confirmation */}
      {deleteTarget !== null && (
        <ConfirmModal
          isOpen={deleteTarget !== null}
          onClose={() => setDeleteTarget(null)}
          onConfirm={confirmDelete}
          title={t('federation.delete_neighborhood_title', 'Delete Neighborhood')}
          message={t('federation.confirm_delete_neighborhood', 'Are you sure you want to delete this neighborhood? This cannot be undone.')}
          confirmLabel={t('federation.delete', 'Delete')}
          confirmColor="danger"
        />
      )}
    </div>
  );
}

export default Neighborhoods;
