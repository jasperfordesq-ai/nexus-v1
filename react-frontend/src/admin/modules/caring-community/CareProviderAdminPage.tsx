// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CareProviderAdminPage — AG64 Care-Provider Directory Admin
 *
 * Allows admins to create, edit, delete, and verify care providers
 * across all types (Spitex, Tagesstätten, private, Vereine, volunteers).
 */

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
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

interface ProviderFormData {
  name: string;
  type: ProviderType | '';
  status: 'active' | 'inactive';
  description: string;
  address: string;
  contact_phone: string;
  contact_email: string;
  website_url: string;
}

type ProviderFormErrors = Partial<Record<keyof ProviderFormData, string>>;

const EMPTY_FORM: ProviderFormData = {
  name: '',
  type: '',
  status: 'active',
  description: '',
  address: '',
  contact_phone: '',
  contact_email: '',
  website_url: '',
};

const PROVIDER_TYPES: ProviderType[] = ['spitex', 'tagesstätte', 'private', 'verein', 'volunteer'];

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
  const { t } = useTranslation('caring_community');
  usePageTitle(t('admin.providers.meta_title'));
  const { showToast } = useToast();

  const [providers, setProviders] = useState<CareProvider[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [perPage, setPerPage] = useState(20);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<CareProvider | null>(null);
  const [form, setForm] = useState<ProviderFormData>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [formErrors, setFormErrors] = useState<ProviderFormErrors>({});

  // Duplicates panel state
  const [duplicates, setDuplicates] = useState<DuplicatesResponse | null>(null);
  const [duplicatesLoading, setDuplicatesLoading] = useState(false);
  const [duplicatesOpen, setDuplicatesOpen] = useState(false);

  // ── Fetch ────────────────────────────────────────────────────────────────

  const fetchProviders = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<DirectoryResponse>(`/v2/admin/caring-community/providers?page=${page}`);
      if (!res.success) throw new Error(res.error ?? t('admin.providers.errors.load'));
      setProviders(res.data?.data ?? []);
      setTotal(res.data?.total ?? 0);
      setPerPage(res.data?.per_page ?? 20);
    } catch (err) {
      logError('CareProviderAdminPage.fetch', err);
      setError(t('admin.providers.errors.load'));
    } finally {
      setLoading(false);
    }
  }, [page, t]);

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
      status: provider.status,
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
    const errors: ProviderFormErrors = {};
    if (!form.name.trim()) errors.name = t('admin.providers.errors.name_required');
    if (!form.type) errors.type = t('admin.providers.errors.type_required');

    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      return;
    }

    setSaving(true);
    try {
      const payload = {
        name: form.name.trim(),
        type: form.type,
        status: form.status,
        description: form.description.trim() || null,
        address: form.address.trim() || null,
        contact_phone: form.contact_phone.trim() || null,
        contact_email: form.contact_email.trim() || null,
        website_url: form.website_url.trim() || null,
      };

      if (editTarget) {
        const res = await api.put(`/v2/admin/caring-community/providers/${editTarget.id}`, payload);
        if (!res.success) throw new Error(res.error ?? t('admin.providers.errors.save'));
        showToast(t('admin.providers.messages.updated'), 'success');
      } else {
        const res = await api.post('/v2/admin/caring-community/providers', payload);
        if (!res.success) throw new Error(res.error ?? t('admin.providers.errors.save'));
        showToast(t('admin.providers.messages.created'), 'success');
      }

      closeModal();
      void fetchProviders();
    } catch (err) {
      logError('CareProviderAdminPage.save', err);
      showToast(t('admin.providers.errors.save'), 'error');
    } finally {
      setSaving(false);
    }
  }

  // ── Delete ───────────────────────────────────────────────────────────────

  async function handleDelete(provider: CareProvider) {
    if (!window.confirm(t('admin.providers.confirm_delete', { name: provider.name }))) {
      return;
    }
    try {
      const res = await api.delete(`/v2/admin/caring-community/providers/${provider.id}`);
      if (!res.success) throw new Error(res.error ?? t('admin.providers.errors.delete'));
      showToast(t('admin.providers.messages.removed'), 'success');
      void fetchProviders();
    } catch (err) {
      logError('CareProviderAdminPage.delete', err);
      showToast(t('admin.providers.errors.delete'), 'error');
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
      if (!res.success) throw new Error(res.error ?? t('admin.providers.errors.duplicates'));
      setDuplicates(res.data ?? { pairs: [], total: 0, scanned: 0 });
    } catch (err) {
      logError('CareProviderAdminPage.duplicates', err);
      showToast(t('admin.providers.errors.duplicates'), 'error');
      setDuplicates({ pairs: [], total: 0, scanned: 0 });
    } finally {
      setDuplicatesLoading(false);
    }
  }

  async function handleDeactivateProvider(providerId: number, providerName: string) {
    if (!window.confirm(t('admin.providers.confirm_deactivate', { name: providerName }))) {
      return;
    }
    try {
      const res = await api.delete(`/v2/admin/caring-community/providers/${providerId}`);
      if (!res.success) throw new Error(res.error ?? t('admin.providers.errors.deactivate'));
      showToast(t('admin.providers.messages.deactivated', { name: providerName }), 'success');
      await loadDuplicates();
      void fetchProviders();
    } catch (err) {
      logError('CareProviderAdminPage.deactivate', err);
      showToast(t('admin.providers.errors.deactivate'), 'error');
    }
  }

  // ── Verify ───────────────────────────────────────────────────────────────

  async function handleVerify(provider: CareProvider) {
    try {
      const res = await api.post(`/v2/admin/caring-community/providers/${provider.id}/verify`, {});
      if (!res.success) throw new Error(res.error ?? t('admin.providers.errors.verify'));
      showToast(t('admin.providers.messages.verified', { name: provider.name }), 'success');
      void fetchProviders();
    } catch (err) {
      logError('CareProviderAdminPage.verify', err);
      showToast(t('admin.providers.errors.verify'), 'error');
    }
  }

  // ── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-xl font-semibold">{t('admin.providers.title')}</h1>
          <p className="text-sm text-default-500 mt-0.5">
            {t('admin.providers.subtitle')}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="flat"
            startContent={<Layers className="h-4 w-4" aria-hidden="true" />}
            onPress={loadDuplicates}
            isLoading={duplicatesLoading && duplicates === null}
          >
            {t('admin.providers.actions.find_duplicates')}
          </Button>
          <Button
            color="primary"
            startContent={<Plus className="h-4 w-4" aria-hidden="true" />}
            onPress={openCreate}
          >
            {t('admin.providers.actions.add_provider')}
          </Button>
        </div>
      </div>

      {/* Intro card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">
                {t('admin.providers.about.title')}
              </p>
              <p className="text-default-600">
                {t('admin.providers.about.body')}
              </p>
              <div className="space-y-0.5 pt-1 text-default-500">
                <p><strong>{t('admin.providers.about.verification_label')}:</strong> {t('admin.providers.about.verification_body')}</p>
                <p><strong>{t('admin.providers.about.types_label')}:</strong> {t('admin.providers.about.types_body')}</p>
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
                {t('admin.providers.duplicates.title')}
              </h2>
              <p className="text-xs text-default-500 mt-0.5">
                {duplicates
                  ? t('admin.providers.duplicates.summary', { scanned: duplicates.scanned, total: duplicates.total })
                  : t('admin.providers.duplicates.scanning')}
              </p>
            </div>
            <Button size="sm" variant="light" onPress={() => setDuplicatesOpen(false)}>
              {t('admin.providers.actions.close')}
            </Button>
          </div>

          {duplicatesLoading ? (
            <div className="flex justify-center py-6">
              <Spinner size="sm" />
            </div>
          ) : duplicates && duplicates.pairs.length === 0 ? (
            <p className="text-sm text-default-500 py-3">
              {t('admin.providers.duplicates.empty')}
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
                          <BadgeCheck className="h-3.5 w-3.5 text-primary shrink-0" aria-label={t('admin.providers.verified')} />
                        )}
                        {pair.provider_a.name}
                      </p>
                      <p className="text-xs text-default-500">
                        #{pair.provider_a.id} · {pair.provider_a.type}
                      </p>
                    </div>
                    <div className="text-default-400 text-xs self-center">{t('admin.providers.duplicates.vs')}</div>
                    <div className="flex-1 min-w-[260px]">
                      <p className="font-medium text-sm flex items-center gap-1.5">
                        {pair.provider_b.is_verified && (
                          <BadgeCheck className="h-3.5 w-3.5 text-primary shrink-0" aria-label={t('admin.providers.verified')} />
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
                      {t('admin.providers.duplicates.match_percent', { percent: Math.round(pair.score * 100) })}
                    </Chip>
                  </div>
                  <div className="flex items-center gap-1.5 mt-2 flex-wrap">
                    {pair.signals.map((sig) => (
                      <Chip key={sig} size="sm" variant="flat" color="primary">
                        {t(`admin.providers.signals.${sig}`, { defaultValue: sig })}
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
                      {t('admin.providers.actions.deactivate_named', { name: pair.provider_b.name })}
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      color="danger"
                      onPress={() =>
                        handleDeactivateProvider(pair.provider_a.id, pair.provider_a.name)
                      }
                    >
                      {t('admin.providers.actions.deactivate_named', { name: pair.provider_a.name })}
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
        <>
          <Table aria-label={t('admin.providers.table.aria')} removeWrapper>
            <TableHeader>
              <TableColumn>{t('admin.providers.table.name')}</TableColumn>
              <TableColumn>{t('admin.providers.table.type')}</TableColumn>
              <TableColumn>{t('admin.providers.table.verified')}</TableColumn>
              <TableColumn>{t('admin.providers.table.status')}</TableColumn>
              <TableColumn>{t('admin.providers.table.actions')}</TableColumn>
            </TableHeader>
            <TableBody emptyContent={t('admin.providers.empty')}>
              {providers.map((provider) => (
                <TableRow key={provider.id}>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      {provider.is_verified && (
                        <BadgeCheck className="h-4 w-4 text-primary shrink-0" aria-label={t('admin.providers.verified')} />
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
                      {t(`admin.providers.types.${provider.type}`)}
                    </Chip>
                  </TableCell>
                  <TableCell>
                    {provider.is_verified ? (
                      <Chip size="sm" color="success" variant="flat">{t('admin.providers.yes')}</Chip>
                    ) : (
                      <Chip size="sm" color="default" variant="flat">{t('admin.providers.no')}</Chip>
                    )}
                  </TableCell>
                  <TableCell>
                    <Chip
                      size="sm"
                      color={provider.status === 'active' ? 'success' : 'default'}
                      variant="flat"
                    >
                      {t(`admin.providers.status.${provider.status}`)}
                    </Chip>
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <Button
                        size="sm"
                        variant="flat"
                        isIconOnly
                        onPress={() => openEdit(provider)}
                        aria-label={t('admin.providers.actions.edit_provider')}
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
                          aria-label={t('admin.providers.actions.verify_provider')}
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
                        aria-label={t('admin.providers.actions.delete_provider')}
                      >
                        <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {total > perPage && (
            <div className="flex items-center justify-between gap-3 border-t border-divider pt-4 text-sm text-default-500">
              <span>
                {t('admin.providers.pagination.summary', {
                  page,
                  total,
                  from: (page - 1) * perPage + 1,
                  to: Math.min(page * perPage, total),
                })}
              </span>
              <div className="flex items-center gap-2">
                <Button size="sm" variant="flat" isDisabled={page <= 1} onPress={() => setPage((p) => Math.max(1, p - 1))}>
                  {t('admin.providers.pagination.previous')}
                </Button>
                <Button size="sm" variant="flat" isDisabled={page * perPage >= total} onPress={() => setPage((p) => p + 1)}>
                  {t('admin.providers.pagination.next')}
                </Button>
              </div>
            </div>
          )}
        </>
      )}

      {/* Create / Edit Modal */}
      <Modal isOpen={modalOpen} onOpenChange={(open) => { if (!open) closeModal(); }} size="2xl">
        <ModalContent>
          {() => (
            <>
              <ModalHeader>
                {editTarget
                  ? t('admin.providers.modal.edit_title', { name: editTarget.name })
                  : t('admin.providers.modal.create_title')}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label={t('admin.providers.fields.name')}
                  isRequired
                  value={form.name}
                  onValueChange={(v) => setForm((f) => ({ ...f, name: v }))}
                  isInvalid={!!formErrors.name}
                  errorMessage={formErrors.name}
                  placeholder={t('admin.providers.placeholders.name')}
                />
                <Select
                  label={t('admin.providers.fields.type')}
                  isRequired
                  selectedKeys={form.type ? new Set([form.type]) : new Set()}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as ProviderType;
                    setForm((f) => ({ ...f, type: val }));
                  }}
                  isInvalid={!!formErrors.type}
                  errorMessage={formErrors.type}
                >
                  {PROVIDER_TYPES.map((value) => (
                    <SelectItem key={value}>{t(`admin.providers.types.${value}`)}</SelectItem>
                  ))}
                </Select>
                <Select
                  label={t('admin.providers.fields.status')}
                  selectedKeys={new Set([form.status])}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as 'active' | 'inactive';
                    setForm((f) => ({ ...f, status: val }));
                  }}
                >
                  <SelectItem key="active">{t('admin.providers.status.active')}</SelectItem>
                  <SelectItem key="inactive">{t('admin.providers.status.inactive')}</SelectItem>
                </Select>
                <Textarea
                  label={t('admin.providers.fields.description')}
                  value={form.description}
                  onValueChange={(v) => setForm((f) => ({ ...f, description: v }))}
                  placeholder={t('admin.providers.placeholders.description')}
                  minRows={2}
                />
                <Input
                  label={t('admin.providers.fields.address')}
                  value={form.address}
                  onValueChange={(v) => setForm((f) => ({ ...f, address: v }))}
                  placeholder={t('admin.providers.placeholders.address')}
                />
                <div className="grid grid-cols-2 gap-3">
                  <Input
                    label={t('admin.providers.fields.phone')}
                    value={form.contact_phone}
                    onValueChange={(v) => setForm((f) => ({ ...f, contact_phone: v }))}
                    placeholder="+1 555 123 4567"
                  />
                  <Input
                    label={t('admin.providers.fields.email')}
                    type="email"
                    value={form.contact_email}
                    onValueChange={(v) => setForm((f) => ({ ...f, contact_email: v }))}
                    placeholder="info@example.com"
                  />
                </div>
                <Input
                  label={t('admin.providers.fields.website')}
                  value={form.website_url}
                  onValueChange={(v) => setForm((f) => ({ ...f, website_url: v }))}
                  placeholder="https://example.com"
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={closeModal} isDisabled={saving}>
                  {t('admin.providers.actions.cancel')}
                </Button>
                <Button color="primary" onPress={handleSave} isLoading={saving}>
                  {editTarget ? t('admin.providers.actions.save_changes') : t('admin.providers.actions.create_provider')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
