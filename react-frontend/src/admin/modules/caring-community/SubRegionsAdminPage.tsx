// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SubRegionsAdminPage — AG77 Sub-Regional Geographic Units Admin
 *
 * Admin is English-only — NO t() calls. Plain English JSX.
 *
 * Manages tenant-scoped Quartier/Ortsteil/municipality/canton subdivisions
 * used by the care-provider directory and member-facing locality filters.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
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
import Info from 'lucide-react/icons/info';
import Plus from 'lucide-react/icons/plus';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import MapPin from 'lucide-react/icons/map-pin';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type SubRegionType = 'quartier' | 'ortsteil' | 'municipality' | 'canton' | 'other';

interface SubRegion {
  id: number;
  name: string;
  slug: string;
  type: SubRegionType;
  description: string | null;
  postal_codes: string[] | null;
  center_latitude: number | null;
  center_longitude: number | null;
  status: 'active' | 'inactive';
  created_at: string | null;
}

interface ListResponse {
  data: SubRegion[];
  total: number;
  per_page: number;
  current_page: number;
}

interface FormData {
  name: string;
  slug: string;
  type: SubRegionType | '';
  description: string;
  postal_codes: string;
  center_latitude: string;
  center_longitude: string;
  status: 'active' | 'inactive';
}

const EMPTY_FORM: FormData = {
  name: '',
  slug: '',
  type: '',
  description: '',
  postal_codes: '',
  center_latitude: '',
  center_longitude: '',
  status: 'active',
};

const REGION_TYPES: { value: SubRegionType; label: string }[] = [
  { value: 'quartier',     label: 'Quartier (neighbourhood)' },
  { value: 'ortsteil',     label: 'Ortsteil (district)' },
  { value: 'municipality', label: 'Municipality (Gemeinde)' },
  { value: 'canton',       label: 'Canton' },
  { value: 'other',        label: 'Other' },
];

const TYPE_FILTER_OPTIONS: { value: SubRegionType | 'all'; label: string }[] = [
  { value: 'all',          label: 'All types' },
  ...REGION_TYPES.map((t) => ({ value: t.value, label: t.label })),
];

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

type ChipColor = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';

function typeColor(type: SubRegionType): ChipColor {
  switch (type) {
    case 'quartier':     return 'primary';
    case 'ortsteil':     return 'secondary';
    case 'municipality': return 'success';
    case 'canton':       return 'warning';
    default:             return 'default';
  }
}

function parsePostalCodes(value: string): string[] {
  return value
    .split(/[,;\s]+/)
    .map((s) => s.trim())
    .filter(Boolean);
}

function postalCodesPreview(codes: string[] | null): string {
  if (!codes || codes.length === 0) return '—';
  if (codes.length <= 3) return codes.join(', ');
  return `${codes.slice(0, 3).join(', ')} +${codes.length - 3}`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function SubRegionsAdminPage() {
  usePageTitle('Sub-Regions — Admin');
  const { showToast } = useToast();

  const [regions, setRegions] = useState<SubRegion[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState<SubRegionType | 'all'>('all');

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<SubRegion | null>(null);
  const [form, setForm] = useState<FormData>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [formErrors, setFormErrors] = useState<Partial<Record<keyof FormData, string>>>({});

  // ── Fetch ────────────────────────────────────────────────────────────────

  const fetchRegions = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string> = {};
      if (search.trim()) params.search = search.trim();
      if (typeFilter !== 'all') params.type = typeFilter;

      const query = new URLSearchParams(params).toString();
      const url = '/v2/admin/caring-community/sub-regions' + (query ? `?${query}` : '');
      const res = await api.get<ListResponse>(url);
      setRegions(res.data?.data ?? []);
    } catch (err) {
      logError('SubRegionsAdminPage.fetch', err);
      setError('Failed to load sub-regions.');
    } finally {
      setLoading(false);
    }
  }, [search, typeFilter]);

  useEffect(() => {
    void fetchRegions();
  }, [fetchRegions]);

  // ── Modal helpers ────────────────────────────────────────────────────────

  function openCreate() {
    setEditTarget(null);
    setForm(EMPTY_FORM);
    setFormErrors({});
    setModalOpen(true);
  }

  function openEdit(region: SubRegion) {
    setEditTarget(region);
    setForm({
      name: region.name,
      slug: region.slug,
      type: region.type,
      description: region.description ?? '',
      postal_codes: (region.postal_codes ?? []).join(', '),
      center_latitude: region.center_latitude !== null ? String(region.center_latitude) : '',
      center_longitude: region.center_longitude !== null ? String(region.center_longitude) : '',
      status: region.status,
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
    const errors: Partial<Record<keyof FormData, string>> = {};
    if (!form.name.trim()) errors.name = 'Name is required.';
    if (!form.type) errors.type = 'Type is required.';

    if (form.center_latitude !== '' && Number.isNaN(Number(form.center_latitude))) {
      errors.center_latitude = 'Latitude must be a number.';
    }
    if (form.center_longitude !== '' && Number.isNaN(Number(form.center_longitude))) {
      errors.center_longitude = 'Longitude must be a number.';
    }

    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      return;
    }

    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        name: form.name.trim(),
        type: form.type,
        description: form.description.trim() || null,
        status: form.status,
        postal_codes: parsePostalCodes(form.postal_codes),
        center_latitude: form.center_latitude !== '' ? Number(form.center_latitude) : null,
        center_longitude: form.center_longitude !== '' ? Number(form.center_longitude) : null,
      };
      if (form.slug.trim()) payload.slug = form.slug.trim();

      if (editTarget) {
        await api.put(`/v2/admin/caring-community/sub-regions/${editTarget.id}`, payload);
        showToast('Sub-region updated successfully.', 'success');
      } else {
        await api.post('/v2/admin/caring-community/sub-regions', payload);
        showToast('Sub-region created successfully.', 'success');
      }

      closeModal();
      void fetchRegions();
    } catch (err) {
      logError('SubRegionsAdminPage.save', err);
      const e = err as { response?: { data?: { errors?: Array<{ field?: string; message?: string }>; error?: { message?: string } } } };
      const apiErrors = e?.response?.data?.errors;
      if (Array.isArray(apiErrors) && apiErrors.length > 0) {
        const fieldErrors: Partial<Record<keyof FormData, string>> = {};
        for (const ae of apiErrors) {
          if (ae.field && ae.message) {
            fieldErrors[ae.field as keyof FormData] = ae.message;
          }
        }
        setFormErrors(fieldErrors);
      } else {
        showToast(e?.response?.data?.error?.message ?? 'Failed to save sub-region.', 'error');
      }
    } finally {
      setSaving(false);
    }
  }

  // ── Delete ───────────────────────────────────────────────────────────────

  async function handleDelete(region: SubRegion) {
    if (!window.confirm(
      `Mark "${region.name}" as inactive? Linked care providers will be detached from it.`
    )) {
      return;
    }
    try {
      await api.delete(`/v2/admin/caring-community/sub-regions/${region.id}`);
      showToast('Sub-region marked inactive.', 'success');
      void fetchRegions();
    } catch (err) {
      logError('SubRegionsAdminPage.delete', err);
      showToast('Failed to delete sub-region.', 'error');
    }
  }

  // ── Render ───────────────────────────────────────────────────────────────

  const visibleRegions = useMemo(() => regions, [regions]);

  return (
    <div className="space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-xl font-semibold flex items-center gap-2">
            <MapPin className="h-5 w-5 text-primary" aria-hidden="true" />
            Sub-Regions
          </h1>
          <p className="text-sm text-default-500 mt-0.5">
            Quartier, Ortsteil, Gemeinde and Kanton subdivisions for the care provider directory and locality filters
          </p>
        </div>
        <Button
          color="primary"
          startContent={<Plus className="h-4 w-4" aria-hidden="true" />}
          onPress={openCreate}
        >
          Add Sub-Region
        </Button>
      </div>

      {/* Intro card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                Sub-Regions divide your community into geographical areas (neighbourhoods, districts,
                or wards). They are used to assign coordinators to specific areas, filter help
                requests by location, and break down pilot metrics by neighbourhood. Adding
                sub-regions is optional but strongly recommended for larger communities with multiple
                coordinator teams.
              </p>
              <p className="text-default-500">
                For the Postal codes field: enter a postcode list, neighbourhood name, or freeform
                description. Precise boundaries are not required — this is for coordinator assignment
                and reporting filters, not mapping.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Filters */}
      <div className="flex items-center gap-3 flex-wrap">
        <Input
          aria-label="Search sub-regions"
          placeholder="Search by name, description or slug..."
          value={search}
          onValueChange={setSearch}
          className="max-w-xs"
          size="sm"
        />
        <Select
          aria-label="Filter by type"
          selectedKeys={new Set([typeFilter])}
          onSelectionChange={(keys) => {
            const v = Array.from(keys)[0] as SubRegionType | 'all' | undefined;
            if (v) setTypeFilter(v);
          }}
          className="max-w-[220px]"
          size="sm"
        >
          {TYPE_FILTER_OPTIONS.map(({ value, label }) => (
            <SelectItem key={value}>{label}</SelectItem>
          ))}
        </Select>
      </div>

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
        <Table aria-label="Sub-regions table" removeWrapper>
          <TableHeader>
            <TableColumn>Name</TableColumn>
            <TableColumn>Type</TableColumn>
            <TableColumn>Postal codes</TableColumn>
            <TableColumn>Coordinates</TableColumn>
            <TableColumn>Status</TableColumn>
            <TableColumn>Actions</TableColumn>
          </TableHeader>
          <TableBody emptyContent="No sub-regions yet — add one to start segmenting your tenant geographically.">
            {visibleRegions.map((region) => (
              <TableRow key={region.id}>
                <TableCell>
                  <div>
                    <p className="font-medium">{region.name}</p>
                    <p className="text-xs text-default-500 font-mono">{region.slug}</p>
                  </div>
                </TableCell>
                <TableCell>
                  <Chip size="sm" color={typeColor(region.type)} variant="flat">
                    {region.type}
                  </Chip>
                </TableCell>
                <TableCell>
                  <span className="text-xs text-default-600 font-mono">
                    {postalCodesPreview(region.postal_codes)}
                  </span>
                </TableCell>
                <TableCell>
                  {region.center_latitude !== null && region.center_longitude !== null ? (
                    <span className="text-xs text-default-500 font-mono">
                      {region.center_latitude.toFixed(4)}, {region.center_longitude.toFixed(4)}
                    </span>
                  ) : (
                    <span className="text-xs text-default-400">—</span>
                  )}
                </TableCell>
                <TableCell>
                  <Chip
                    size="sm"
                    color={region.status === 'active' ? 'success' : 'default'}
                    variant="flat"
                  >
                    {region.status}
                  </Chip>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      onPress={() => openEdit(region)}
                      aria-label="Edit sub-region"
                    >
                      <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                    </Button>
                    {region.status === 'active' && (
                      <Button
                        size="sm"
                        variant="flat"
                        color="danger"
                        isIconOnly
                        onPress={() => handleDelete(region)}
                        aria-label="Mark inactive"
                      >
                        <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                      </Button>
                    )}
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
                {editTarget ? `Edit: ${editTarget.name}` : 'Add Sub-Region'}
              </ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label="Name"
                  isRequired
                  value={form.name}
                  onValueChange={(v) => setForm((f) => ({ ...f, name: v }))}
                  isInvalid={!!formErrors.name}
                  errorMessage={formErrors.name}
                  placeholder="e.g. Lorzenhof"
                />
                <Input
                  label="Slug (auto-generated if blank)"
                  value={form.slug}
                  onValueChange={(v) => setForm((f) => ({ ...f, slug: v }))}
                  isInvalid={!!formErrors.slug}
                  errorMessage={formErrors.slug}
                  placeholder="e.g. lorzenhof"
                  description="Lowercase, hyphenated identifier used in URLs and filters."
                />
                <Select
                  label="Type"
                  isRequired
                  selectedKeys={form.type ? new Set([form.type]) : new Set()}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as SubRegionType;
                    setForm((f) => ({ ...f, type: val }));
                  }}
                  isInvalid={!!formErrors.type}
                  errorMessage={formErrors.type}
                >
                  {REGION_TYPES.map(({ value, label }) => (
                    <SelectItem key={value}>{label}</SelectItem>
                  ))}
                </Select>
                <Textarea
                  label="Description"
                  value={form.description}
                  onValueChange={(v) => setForm((f) => ({ ...f, description: v }))}
                  placeholder="Brief description of this area..."
                  minRows={2}
                />
                <Input
                  label="Postal codes"
                  value={form.postal_codes}
                  onValueChange={(v) => setForm((f) => ({ ...f, postal_codes: v }))}
                  placeholder="6330, 6331, 6332"
                  description="Comma- or space-separated PLZ list."
                />
                <div className="grid grid-cols-2 gap-3">
                  <Input
                    label="Centre latitude"
                    value={form.center_latitude}
                    onValueChange={(v) => setForm((f) => ({ ...f, center_latitude: v }))}
                    placeholder="47.1758"
                    isInvalid={!!formErrors.center_latitude}
                    errorMessage={formErrors.center_latitude}
                  />
                  <Input
                    label="Centre longitude"
                    value={form.center_longitude}
                    onValueChange={(v) => setForm((f) => ({ ...f, center_longitude: v }))}
                    placeholder="8.4622"
                    isInvalid={!!formErrors.center_longitude}
                    errorMessage={formErrors.center_longitude}
                  />
                </div>
                <Select
                  label="Status"
                  selectedKeys={new Set([form.status])}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as 'active' | 'inactive' | undefined;
                    if (val) setForm((f) => ({ ...f, status: val }));
                  }}
                >
                  <SelectItem key="active">Active</SelectItem>
                  <SelectItem key="inactive">Inactive</SelectItem>
                </Select>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={closeModal} isDisabled={saving}>
                  Cancel
                </Button>
                <Button color="primary" onPress={handleSave} isLoading={saving}>
                  {editTarget ? 'Save Changes' : 'Create Sub-Region'}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
