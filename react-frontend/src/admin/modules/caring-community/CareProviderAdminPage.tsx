// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CareProviderAdminPage — AG64 Care-Provider Directory Admin
 *
 * Admin is English-only — NO t() calls. Plain English JSX.
 *
 * Allows admins to create, edit, delete, and verify care providers
 * across all types (Spitex, Tagesstätten, private, Vereine, volunteers).
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  Chip,
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
} from '@heroui/react';
import BadgeCheck from 'lucide-react/icons/badge-check';
import Info from 'lucide-react/icons/info';
import Plus from 'lucide-react/icons/plus';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Layers from 'lucide-react/icons/layers';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type ProviderType = 'spitex' | 'tagesstätte' | 'private' | 'verein' | 'volunteer';

interface CareProvider {
  id: number;
  name: string;
  type: ProviderType;
  description: string | null;
  address: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  website_url: string | null;
  is_verified: boolean;
  status: 'active' | 'inactive';
  created_at: string | null;
}

interface DirectoryResponse {
  data: CareProvider[];
  total: number;
  per_page: number;
  current_page: number;
}

interface DuplicatePair {
  provider_a: { id: number; name: string; type: ProviderType; is_verified: boolean };
  provider_b: { id: number; name: string; type: ProviderType; is_verified: boolean };
  score: number;
  signals: string[];
}

interface DuplicatesResponse {
  pairs: DuplicatePair[];
  total: number;
  scanned: number;
}

const SIGNAL_LABELS: Record<string, string> = {
  name_match:      'Name match',
  name_similar:    'Name similar',
  email_match:     'Email match',
  phone_match:     'Phone match',
  website_match:   'Website match',
  address_overlap: 'Address overlap',
};

interface ProviderFormData {
  name: string;
  type: ProviderType | '';
  description: string;
  address: string;
  contact_phone: string;
  contact_email: string;
  website_url: string;
}

const EMPTY_FORM: ProviderFormData = {
  name: '',
  type: '',
  description: '',
  address: '',
  contact_phone: '',
  contact_email: '',
  website_url: '',
};

const PROVIDER_TYPES: { value: ProviderType; label: string }[] = [
  { value: 'spitex',      label: 'Spitex' },
  { value: 'tagesstätte', label: 'Tagesstätte (Day Centre)' },
  { value: 'private',     label: 'Private Service' },
  { value: 'verein',      label: 'Verein (Association)' },
  { value: 'volunteer',   label: 'Volunteer Group' },
];

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

type ChipColor = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';

function typeColor(type: ProviderType): ChipColor {
  switch (type) {
    case 'spitex':       return 'primary';
    case 'tagesstätte':  return 'secondary';
    case 'private':      return 'warning';
    case 'verein':       return 'success';
    case 'volunteer':    return 'danger';
    default:             return 'default';
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function CareProviderAdminPage() {
  usePageTitle('Care Provider Directory — Admin');
  const { showToast } = useToast();

  const [providers, setProviders] = useState<CareProvider[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<CareProvider | null>(null);
  const [form, setForm] = useState<ProviderFormData>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [formErrors, setFormErrors] = useState<Partial<ProviderFormData>>({});

  // Duplicates panel state
  const [duplicates, setDuplicates] = useState<DuplicatesResponse | null>(null);
  const [duplicatesLoading, setDuplicatesLoading] = useState(false);
  const [duplicatesOpen, setDuplicatesOpen] = useState(false);

  // ── Fetch ────────────────────────────────────────────────────────────────

  const fetchProviders = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<DirectoryResponse>('/v2/admin/caring-community/providers');
      setProviders(res.data?.data ?? []);
    } catch (err) {
      logError('CareProviderAdminPage.fetch', err);
      setError('Failed to load care providers.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void fetchProviders();
  }, [fetchProviders]);

  // ── Modal helpers ────────────────────────────────────────────────────────

  function openCreate() {
    setEditTarget(null);
    setForm(EMPTY_FORM);
    setFormErrors({});
    setModalOpen(true);
  }

  function openEdit(provider: CareProvider) {
    setEditTarget(provider);
    setForm({
      name: provider.name,
      type: provider.type,
      description: provider.description ?? '',
      address: provider.address ?? '',
      contact_phone: provider.contact_phone ?? '',
      contact_email: provider.contact_email ?? '',
      website_url: provider.website_url ?? '',
    });
    setFormErrors({});
    setModalOpen(true);
  }

  function closeModal() {
    setModalOpen(false);
    setEditTarget(null);
    setForm(EMPTY_FORM);
    setFormErrors({});
  }

  // ── Save ─────────────────────────────────────────────────────────────────

  async function handleSave() {
    const errors: Partial<ProviderFormData> = {};
    if (!form.name.trim()) errors.name = 'Name is required.';
    if (!form.type) errors.type = '';

    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      return;
    }

    setSaving(true);
    try {
      const payload = {
        name: form.name.trim(),
        type: form.type,
        description: form.description.trim() || null,
        address: form.address.trim() || null,
        contact_phone: form.contact_phone.trim() || null,
        contact_email: form.contact_email.trim() || null,
        website_url: form.website_url.trim() || null,
      };

      if (editTarget) {
        await api.put(`/v2/admin/caring-community/providers/${editTarget.id}`, payload);
        showToast('Provider updated successfully.', 'success');
      } else {
        await api.post('/v2/admin/caring-community/providers', payload);
        showToast('Provider created successfully.', 'success');
      }

      closeModal();
      void fetchProviders();
    } catch (err) {
      logError('CareProviderAdminPage.save', err);
      showToast('Failed to save provider.', 'error');
    } finally {
      setSaving(false);
    }
  }

  // ── Delete ───────────────────────────────────────────────────────────────

  async function handleDelete(provider: CareProvider) {
    if (!window.confirm(`Delete "${provider.name}"? This will hide it from the directory.`)) {
      return;
    }
    try {
      await api.delete(`/v2/admin/caring-community/providers/${provider.id}`);
      showToast('Provider removed.', 'success');
      void fetchProviders();
    } catch (err) {
      logError('CareProviderAdminPage.delete', err);
      showToast('Failed to delete provider.', 'error');
    }
  }

  // ── Duplicates ───────────────────────────────────────────────────────────

  async function loadDuplicates() {
    setDuplicatesLoading(true);
    setDuplicatesOpen(true);
    try {
      const res = await api.get<DuplicatesResponse>(
        '/v2/admin/caring-community/providers/duplicates?threshold=0.65',
      );
      setDuplicates(res.data ?? { pairs: [], total: 0, scanned: 0 });
    } catch (err) {
      logError('CareProviderAdminPage.duplicates', err);
      showToast('Failed to scan for duplicates.', 'error');
      setDuplicates({ pairs: [], total: 0, scanned: 0 });
    } finally {
      setDuplicatesLoading(false);
    }
  }

  async function handleDeactivateProvider(providerId: number, providerName: string) {
    if (!window.confirm(`Mark "${providerName}" as inactive? You can re-activate it later.`)) {
      return;
    }
    try {
      await api.delete(`/v2/admin/caring-community/providers/${providerId}`);
      showToast(`"${providerName}" deactivated.`, 'success');
      await loadDuplicates();
      void fetchProviders();
    } catch (err) {
      logError('CareProviderAdminPage.deactivate', err);
      showToast('Failed to deactivate provider.', 'error');
    }
  }

  // ── Verify ───────────────────────────────────────────────────────────────

  async function handleVerify(provider: CareProvider) {
    try {
      await api.post(`/v2/admin/caring-community/providers/${provider.id}/verify`, {});
      showToast(`"${provider.name}" marked as verified.`, 'success');
      void fetchProviders();
    } catch (err) {
      logError('CareProviderAdminPage.verify', err);
      showToast('Failed to verify provider.', 'error');
    }
  }

  // ── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-xl font-semibold">Care Provider Directory</h1>
          <p className="text-sm text-default-500 mt-0.5">
            Manage Spitex, day centres, associations, and volunteer groups
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="flat"
            startContent={<Layers className="h-4 w-4" aria-hidden="true" />}
            onPress={loadDuplicates}
            isLoading={duplicatesLoading && duplicates === null}
          >
            Find Duplicates
          </Button>
          <Button
            color="primary"
            startContent={<Plus className="h-4 w-4" aria-hidden="true" />}
            onPress={openCreate}
          >
            Add Provider
          </Button>
        </div>
      </div>

      {/* Intro card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                Care Providers are external organisations (home care agencies, social enterprises,
                charities) that participate in the community care network alongside individual
                volunteers. They can be assigned help requests, receive time credit payments, and
                appear in the provider directory visible to members. Verify each provider's
                credentials before listing them.
              </p>
              <div className="space-y-0.5 pt-1 text-default-500">
                <p><strong>Verification:</strong> Verified providers display a badge in the directory. Verification requires a check of their registration, insurance, and safeguarding policies. Use the shield icon in the Actions column to mark a provider as verified.</p>
                <p><strong>Provider types:</strong> Spitex (home care), Tagesstätte (day centre), Private service, Verein (association), Volunteer group. Add the description field to list the types of care offered — members and coordinators use this to find the right provider for each request.</p>
              </div>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Duplicates Panel */}
      {duplicatesOpen && (
        <div className="rounded-xl border border-default-200 bg-default-50 dark:bg-default-100/40 p-4">
          <div className="flex items-center justify-between gap-3 mb-3">
            <div>
              <h2 className="text-sm font-semibold flex items-center gap-2">
                <Layers className="h-4 w-4" aria-hidden="true" />
                Potential Duplicates
              </h2>
              <p className="text-xs text-default-500 mt-0.5">
                {duplicates
                  ? `Scanned ${duplicates.scanned} active providers — ${duplicates.total} pair${duplicates.total === 1 ? '' : 's'} above 65% similarity`
                  : 'Scanning…'}
              </p>
            </div>
            <Button size="sm" variant="light" onPress={() => setDuplicatesOpen(false)}>
              Close
            </Button>
          </div>

          {duplicatesLoading ? (
            <div className="flex justify-center py-6">
              <Spinner size="sm" />
            </div>
          ) : duplicates && duplicates.pairs.length === 0 ? (
            <p className="text-sm text-default-500 py-3">
              No likely duplicates found. The directory looks clean.
            </p>
          ) : (
            <div className="space-y-2">
              {duplicates?.pairs.map((pair, idx) => (
                <div
                  key={`${pair.provider_a.id}-${pair.provider_b.id}-${idx}`}
                  className="rounded-lg border border-default-200 bg-content1 p-3"
                >
                  <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div className="flex-1 min-w-[260px]">
                      <p className="font-medium text-sm flex items-center gap-1.5">
                        {pair.provider_a.is_verified && (
                          <BadgeCheck className="h-3.5 w-3.5 text-primary shrink-0" aria-label="Verified" />
                        )}
                        {pair.provider_a.name}
                      </p>
                      <p className="text-xs text-default-500">
                        #{pair.provider_a.id} · {pair.provider_a.type}
                      </p>
                    </div>
                    <div className="text-default-400 text-xs self-center">vs</div>
                    <div className="flex-1 min-w-[260px]">
                      <p className="font-medium text-sm flex items-center gap-1.5">
                        {pair.provider_b.is_verified && (
                          <BadgeCheck className="h-3.5 w-3.5 text-primary shrink-0" aria-label="Verified" />
                        )}
                        {pair.provider_b.name}
                      </p>
                      <p className="text-xs text-default-500">
                        #{pair.provider_b.id} · {pair.provider_b.type}
                      </p>
                    </div>
                    <Chip
                      size="sm"
                      color={pair.score >= 0.85 ? 'danger' : pair.score >= 0.75 ? 'warning' : 'default'}
                      variant="flat"
                    >
                      {Math.round(pair.score * 100)}% match
                    </Chip>
                  </div>
                  <div className="flex items-center gap-1.5 mt-2 flex-wrap">
                    {pair.signals.map((sig) => (
                      <Chip key={sig} size="sm" variant="flat" color="primary">
                        {SIGNAL_LABELS[sig] ?? sig}
                      </Chip>
                    ))}
                  </div>
                  <div className="flex items-center gap-2 mt-2.5">
                    <Button
                      size="sm"
                      variant="flat"
                      color="danger"
                      onPress={() =>
                        handleDeactivateProvider(pair.provider_b.id, pair.provider_b.name)
                      }
                    >
                      Deactivate &ldquo;{pair.provider_b.name}&rdquo;
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      color="danger"
                      onPress={() =>
                        handleDeactivateProvider(pair.provider_a.id, pair.provider_a.name)
                      }
                    >
                      Deactivate &ldquo;{pair.provider_a.name}&rdquo;
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Table */}
      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : error ? (
        <div className="rounded-xl border border-danger/30 bg-danger/5 p-6 text-center text-danger">
          {error}
        </div>
      ) : (
        <Table aria-label="Care providers table" removeWrapper>
          <TableHeader>
            <TableColumn>Name</TableColumn>
            <TableColumn>Type</TableColumn>
            <TableColumn>Verified</TableColumn>
            <TableColumn>Status</TableColumn>
            <TableColumn>Actions</TableColumn>
          </TableHeader>
          <TableBody emptyContent="No care providers yet — add one to get started.">
            {providers.map((provider) => (
              <TableRow key={provider.id}>
                <TableCell>
                  <div className="flex items-center gap-2">
                    {provider.is_verified && (
                      <BadgeCheck className="h-4 w-4 text-primary shrink-0" aria-label="Verified" />
                    )}
                    <div>
                      <p className="font-medium">{provider.name}</p>
                      {provider.address && (
                        <p className="text-xs text-default-500">{provider.address}</p>
                      )}
                    </div>
                  </div>
                </TableCell>
                <TableCell>
                  <Chip size="sm" color={typeColor(provider.type)} variant="flat">
                    {provider.type}
                  </Chip>
                </TableCell>
                <TableCell>
                  {provider.is_verified ? (
                    <Chip size="sm" color="success" variant="flat">Yes</Chip>
                  ) : (
                    <Chip size="sm" color="default" variant="flat">No</Chip>
                  )}
                </TableCell>
                <TableCell>
                  <Chip
                    size="sm"
                    color={provider.status === 'active' ? 'success' : 'default'}
                    variant="flat"
                  >
                    {provider.status}
                  </Chip>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      onPress={() => openEdit(provider)}
                      aria-label="Edit provider"
                    >
                      <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                    </Button>
                    {!provider.is_verified && (
                      <Button
                        size="sm"
                        variant="flat"
                        color="primary"
                        isIconOnly
                        onPress={() => handleVerify(provider)}
                        aria-label="Verify provider"
                      >
                        <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" />
                      </Button>
                    )}
                    <Button
                      size="sm"
                      variant="flat"
                      color="danger"
                      isIconOnly
                      onPress={() => handleDelete(provider)}
                      aria-label="Delete provider"
                    >
                      <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}

      {/* Create / Edit Modal */}
      <Modal isOpen={modalOpen} onOpenChange={(open) => { if (!open) closeModal(); }} size="2xl">
        <ModalContent>
          {() => (
            <>
              <ModalHeader>
                {editTarget ? `Edit: ${editTarget.name}` : 'Add Care Provider'}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label="Name"
                  isRequired
                  value={form.name}
                  onValueChange={(v) => setForm((f) => ({ ...f, name: v }))}
                  isInvalid={!!formErrors.name}
                  errorMessage={formErrors.name}
                  placeholder="e.g. Spitex Zürich Sihl"
                />
                <Select
                  label="Type"
                  isRequired
                  selectedKeys={form.type ? new Set([form.type]) : new Set()}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as ProviderType;
                    setForm((f) => ({ ...f, type: val }));
                  }}
                  isInvalid={!!formErrors.type}
                  errorMessage={formErrors.type}
                >
                  {PROVIDER_TYPES.map(({ value, label }) => (
                    <SelectItem key={value}>{label}</SelectItem>
                  ))}
                </Select>
                <Textarea
                  label="Description"
                  value={form.description}
                  onValueChange={(v) => setForm((f) => ({ ...f, description: v }))}
                  placeholder="Brief description of services offered..."
                  minRows={2}
                />
                <Input
                  label="Address"
                  value={form.address}
                  onValueChange={(v) => setForm((f) => ({ ...f, address: v }))}
                  placeholder="Street, City, Postcode"
                />
                <div className="grid grid-cols-2 gap-3">
                  <Input
                    label="Phone"
                    value={form.contact_phone}
                    onValueChange={(v) => setForm((f) => ({ ...f, contact_phone: v }))}
                    placeholder="+41 44 000 00 00"
                  />
                  <Input
                    label="Email"
                    type="email"
                    value={form.contact_email}
                    onValueChange={(v) => setForm((f) => ({ ...f, contact_email: v }))}
                    placeholder="info@example.com"
                  />
                </div>
                <Input
                  label="Website"
                  value={form.website_url}
                  onValueChange={(v) => setForm((f) => ({ ...f, website_url: v }))}
                  placeholder="https://example.com"
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={closeModal} isDisabled={saving}>
                  Cancel
                </Button>
                <Button color="primary" onPress={handleSave} isLoading={saving}>
                  {editTarget ? 'Save Changes' : 'Create Provider'}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
