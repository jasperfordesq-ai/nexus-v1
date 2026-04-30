// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  Chip,
  Divider,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Select,
  SelectItem,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import PlugZap from 'lucide-react/icons/plug-zap';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import Plus from 'lucide-react/icons/plus';
import Sparkles from 'lucide-react/icons/sparkles';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

type IntegrationStatus =
  | 'proposed'
  | 'scoping'
  | 'blocked'
  | 'sandbox'
  | 'live'
  | 'deprecated';

type DsaStatus = 'not_required' | 'drafting' | 'in_review' | 'signed';

type IntegrationCategory =
  | 'banking'
  | 'payment'
  | 'identity_verification'
  | 'professional_care'
  | 'municipal_data'
  | 'postal'
  | 'ahv'
  | 'healthcare'
  | 'other';

interface Integration {
  id: string;
  name: string;
  category: IntegrationCategory;
  owner_name: string;
  owner_email: string;
  status: IntegrationStatus;
  interface_spec_url: string;
  dsa_status: DsaStatus;
  sandbox_url: string;
  notes: string;
  created_at: string;
  updated_at: string;
}

interface ListResponse {
  items: Integration[];
  last_updated_at: string | null;
}

interface ItemResponse {
  item: Integration;
}

const STATUSES: IntegrationStatus[] = [
  'proposed',
  'scoping',
  'blocked',
  'sandbox',
  'live',
  'deprecated',
];

const DSA_STATUSES: DsaStatus[] = ['not_required', 'drafting', 'in_review', 'signed'];

const CATEGORIES: IntegrationCategory[] = [
  'banking',
  'payment',
  'identity_verification',
  'professional_care',
  'municipal_data',
  'postal',
  'ahv',
  'healthcare',
  'other',
];

const STATUS_COLOR: Record<
  IntegrationStatus,
  'default' | 'primary' | 'danger' | 'warning' | 'success' | 'secondary'
> = {
  proposed: 'default',
  scoping: 'primary',
  blocked: 'danger',
  sandbox: 'warning',
  live: 'success',
  deprecated: 'secondary',
};

const DSA_COLOR: Record<DsaStatus, 'default' | 'primary' | 'warning' | 'success'> = {
  not_required: 'default',
  drafting: 'primary',
  in_review: 'warning',
  signed: 'success',
};

const CATEGORY_LABEL: Record<IntegrationCategory, string> = {
  banking: 'Banking',
  payment: 'Payment',
  identity_verification: 'Identity verification',
  professional_care: 'Professional care',
  municipal_data: 'Municipal data',
  postal: 'Postal',
  ahv: 'AHV',
  healthcare: 'Healthcare',
  other: 'Other',
};

const DSA_LABEL: Record<DsaStatus, string> = {
  not_required: 'Not required',
  drafting: 'Drafting',
  in_review: 'In review',
  signed: 'Signed',
};

interface FormState {
  name: string;
  category: IntegrationCategory;
  owner_name: string;
  owner_email: string;
  status: IntegrationStatus;
  interface_spec_url: string;
  dsa_status: DsaStatus;
  sandbox_url: string;
  notes: string;
}

function emptyForm(): FormState {
  return {
    name: '',
    category: 'other',
    owner_name: '',
    owner_email: '',
    status: 'proposed',
    interface_spec_url: '',
    dsa_status: 'not_required',
    sandbox_url: '',
    notes: '',
  };
}

function fromIntegration(item: Integration): FormState {
  return {
    name: item.name,
    category: item.category,
    owner_name: item.owner_name,
    owner_email: item.owner_email,
    status: item.status,
    interface_spec_url: item.interface_spec_url,
    dsa_status: item.dsa_status,
    sandbox_url: item.sandbox_url,
    notes: item.notes,
  };
}

export default function ExternalIntegrationsAdminPage(): JSX.Element {
  usePageTitle('External Integration Backlog');
  const { showToast } = useToast();

  const [items, setItems] = useState<Integration[]>([]);
  const [lastUpdatedAt, setLastUpdatedAt] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [seeding, setSeeding] = useState(false);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const editModal = useDisclosure();
  const deleteModal = useDisclosure();

  const [editing, setEditing] = useState<Integration | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [target, setTarget] = useState<Integration | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<ListResponse>(
        '/v2/admin/caring-community/external-integrations',
      );
      if (res.success && res.data) {
        setItems(res.data.items ?? []);
        setLastUpdatedAt(res.data.last_updated_at ?? null);
      } else {
        setItems([]);
        setLastUpdatedAt(null);
      }
    } catch {
      showToast('Failed to load integration backlog', 'error');
      setItems([]);
      setLastUpdatedAt(null);
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm());
    editModal.onOpen();
  };

  const openEdit = (item: Integration) => {
    setEditing(item);
    setForm(fromIntegration(item));
    editModal.onOpen();
  };

  const openDelete = (item: Integration) => {
    setTarget(item);
    deleteModal.onOpen();
  };

  const handleSeed = async () => {
    setSeeding(true);
    try {
      const res = await api.post<ListResponse>(
        '/v2/admin/caring-community/external-integrations/seed-defaults',
        {},
      );
      if (res.success && res.data) {
        setItems(res.data.items ?? []);
        setLastUpdatedAt(res.data.last_updated_at ?? null);
        showToast('Seeded default backlog', 'success');
      } else {
        showToast(res.error ?? 'Seed failed', 'error');
      }
    } catch {
      showToast('Seed failed', 'error');
    } finally {
      setSeeding(false);
    }
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      if (editing) {
        const res = await api.put<ItemResponse>(
          `/v2/admin/caring-community/external-integrations/${editing.id}`,
          form,
        );
        if (res.success && res.data?.item) {
          showToast('Integration updated', 'success');
          editModal.onClose();
          await load();
        } else {
          showToast(res.error ?? 'Update failed', 'error');
        }
      } else {
        const res = await api.post<ItemResponse>(
          '/v2/admin/caring-community/external-integrations',
          form,
        );
        if (res.success && res.data?.item) {
          showToast('Integration created', 'success');
          editModal.onClose();
          await load();
        } else {
          showToast(res.error ?? 'Create failed', 'error');
        }
      }
    } catch {
      showToast(editing ? 'Update failed' : 'Create failed', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!target) return;
    setDeleting(true);
    try {
      const res = await api.delete(
        `/v2/admin/caring-community/external-integrations/${target.id}`,
      );
      if (res.success) {
        showToast('Integration removed', 'success');
        deleteModal.onClose();
        setTarget(null);
        await load();
      } else {
        showToast(res.error ?? 'Delete failed', 'error');
      }
    } catch {
      showToast('Delete failed', 'error');
    } finally {
      setDeleting(false);
    }
  };

  const isFormValid = form.name.trim() !== '';

  return (
    <div className="space-y-6">
      <PageHeader
        title="External Integration Backlog"
        subtitle="AG87 — partner-dependent integrations awaiting external owner / DSA / sandbox"
        icon={<PlugZap size={24} />}
        actions={
          <>
            <Button
              size="sm"
              variant="flat"
              startContent={<RefreshCw size={14} />}
              onPress={() => void load()}
              isLoading={loading}
            >
              Refresh
            </Button>
            <Button
              size="sm"
              color="primary"
              startContent={<Plus size={14} />}
              onPress={openCreate}
            >
              Add integration
            </Button>
          </>
        }
      />

      {loading ? (
        <Card shadow="sm">
          <CardBody>
            <div className="flex justify-center py-12">
              <Spinner />
            </div>
          </CardBody>
        </Card>
      ) : items.length === 0 ? (
        <Card shadow="sm">
          <CardBody>
            <div className="flex flex-col items-center gap-4 py-10 text-center">
              <div className="rounded-full bg-default-100 p-4 text-default-500">
                <PlugZap size={32} />
              </div>
              <div className="max-w-md space-y-1">
                <h3 className="text-base font-semibold">No integrations tracked yet</h3>
                <p className="text-sm text-default-500">
                  Seed a curated set of well-known partner-dependent integrations
                  (AHV submission, Spitex handoff, cantonal master-data feed,
                  PostFinance, Twint, postal-address verification) so the
                  platform operator can track owner, DSA status, and sandbox
                  readiness.
                </p>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <Button
                  color="primary"
                  startContent={<Sparkles size={14} />}
                  onPress={() => void handleSeed()}
                  isLoading={seeding}
                >
                  Seed default backlog
                </Button>
                <Button
                  variant="flat"
                  startContent={<Plus size={14} />}
                  onPress={openCreate}
                >
                  Add integration
                </Button>
              </div>
            </div>
          </CardBody>
        </Card>
      ) : (
        <Card shadow="sm">
          <CardBody>
            {lastUpdatedAt && (
              <p className="mb-3 text-xs text-default-500">
                Last updated {new Date(lastUpdatedAt).toLocaleString()}
              </p>
            )}
            <Table aria-label="External integrations" removeWrapper>
              <TableHeader>
                <TableColumn>Name</TableColumn>
                <TableColumn>Category</TableColumn>
                <TableColumn>Owner</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>DSA</TableColumn>
                <TableColumn>Sandbox</TableColumn>
                <TableColumn>Updated</TableColumn>
                <TableColumn aria-label="Row actions">Actions</TableColumn>
              </TableHeader>
              <TableBody>
                {items.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>
                      <div className="flex flex-col">
                        <span className="font-medium">{item.name}</span>
                        {item.notes && (
                          <span className="line-clamp-1 max-w-md text-xs text-default-500">
                            {item.notes}
                          </span>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">{CATEGORY_LABEL[item.category]}</span>
                    </TableCell>
                    <TableCell>
                      {item.owner_name ? (
                        <div className="flex flex-col">
                          <span className="text-sm">{item.owner_name}</span>
                          {item.owner_email && (
                            <span className="text-xs text-default-500">
                              {item.owner_email}
                            </span>
                          )}
                        </div>
                      ) : (
                        <span className="text-default-400">—</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        color={STATUS_COLOR[item.status]}
                        variant="flat"
                      >
                        {item.status}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        color={DSA_COLOR[item.dsa_status]}
                        variant="flat"
                      >
                        {DSA_LABEL[item.dsa_status]}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      {item.sandbox_url ? (
                        <a
                          href={item.sandbox_url}
                          target="_blank"
                          rel="noreferrer"
                          className="text-sm text-primary underline"
                        >
                          Open
                        </a>
                      ) : (
                        <span className="text-default-400">—</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <span className="text-xs text-default-500">
                        {new Date(item.updated_at).toLocaleDateString()}
                      </span>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1">
                        <Button
                          size="sm"
                          variant="light"
                          isIconOnly
                          aria-label="Edit"
                          onPress={() => openEdit(item)}
                        >
                          <Pencil size={14} />
                        </Button>
                        <Button
                          size="sm"
                          variant="light"
                          color="danger"
                          isIconOnly
                          aria-label="Delete"
                          onPress={() => openDelete(item)}
                        >
                          <Trash2 size={14} />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      )}

      <Modal
        isOpen={editModal.isOpen}
        onClose={editModal.onClose}
        size="2xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader>
            {editing ? `Edit: ${editing.name}` : 'Add integration'}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label="Name"
                placeholder="e.g. AHV submission gateway"
                variant="bordered"
                isRequired
                value={form.name}
                onValueChange={(v) => setForm({ ...form, name: v })}
              />

              <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Select
                  label="Category"
                  variant="bordered"
                  selectedKeys={[form.category]}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      category: (e.target.value || 'other') as IntegrationCategory,
                    })
                  }
                >
                  {CATEGORIES.map((c) => (
                    <SelectItem key={c}>{CATEGORY_LABEL[c]}</SelectItem>
                  ))}
                </Select>

                <Select
                  label="Status"
                  variant="bordered"
                  selectedKeys={[form.status]}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      status: (e.target.value || 'proposed') as IntegrationStatus,
                    })
                  }
                >
                  {STATUSES.map((s) => (
                    <SelectItem key={s}>{s}</SelectItem>
                  ))}
                </Select>
              </div>

              <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Input
                  label="Owner name"
                  placeholder="External contact full name"
                  variant="bordered"
                  value={form.owner_name}
                  onValueChange={(v) => setForm({ ...form, owner_name: v })}
                />
                <Input
                  label="Owner email"
                  type="email"
                  placeholder="contact@partner.example"
                  variant="bordered"
                  value={form.owner_email}
                  onValueChange={(v) => setForm({ ...form, owner_email: v })}
                />
              </div>

              <Input
                label="Interface specification URL"
                placeholder="https://docs.partner.example/spec"
                variant="bordered"
                value={form.interface_spec_url}
                onValueChange={(v) => setForm({ ...form, interface_spec_url: v })}
              />

              <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Select
                  label="Data sharing agreement"
                  variant="bordered"
                  selectedKeys={[form.dsa_status]}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      dsa_status: (e.target.value || 'not_required') as DsaStatus,
                    })
                  }
                >
                  {DSA_STATUSES.map((d) => (
                    <SelectItem key={d}>{DSA_LABEL[d]}</SelectItem>
                  ))}
                </Select>

                <Input
                  label="Sandbox URL"
                  placeholder="https://sandbox.partner.example"
                  variant="bordered"
                  value={form.sandbox_url}
                  onValueChange={(v) => setForm({ ...form, sandbox_url: v })}
                />
              </div>

              <Textarea
                label="Notes"
                placeholder="Open questions, blockers, dependencies, contractual notes…"
                variant="bordered"
                minRows={3}
                value={form.notes}
                onValueChange={(v) => setForm({ ...form, notes: v })}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={editModal.onClose}>
              Cancel
            </Button>
            <Button
              color="primary"
              onPress={() => void handleSave()}
              isDisabled={!isFormValid}
              isLoading={saving}
            >
              {editing ? 'Save changes' : 'Create integration'}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      <Modal isOpen={deleteModal.isOpen} onClose={deleteModal.onClose} size="md">
        <ModalContent>
          <ModalHeader>Remove integration</ModalHeader>
          <ModalBody>
            {target ? (
              <div className="space-y-3">
                <p className="text-sm">
                  This will permanently remove the backlog entry for{' '}
                  <span className="font-semibold">{target.name}</span>. The
                  external partner record is not affected — only the tracking
                  entry is removed.
                </p>
                <Divider />
                <p className="text-xs text-default-500">
                  This action cannot be undone.
                </p>
              </div>
            ) : null}
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={deleteModal.onClose}>
              Cancel
            </Button>
            <Button
              color="danger"
              onPress={() => void handleDelete()}
              isLoading={deleting}
            >
              Remove
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
