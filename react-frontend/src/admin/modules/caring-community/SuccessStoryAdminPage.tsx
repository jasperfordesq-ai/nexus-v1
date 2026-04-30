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
  Switch,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Textarea,
  Tooltip,
  useDisclosure,
} from '@heroui/react';
import Award from 'lucide-react/icons/award';
import Pencil from 'lucide-react/icons/pencil';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Sparkles from 'lucide-react/icons/sparkles';
import Trash2 from 'lucide-react/icons/trash-2';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type MetricSource = 'pilot_scoreboard' | 'municipal_roi' | 'manual';

interface SuccessStory {
  id: string;
  title: string;
  narrative: string;
  metric_source: MetricSource;
  metric_key: string | null;
  before_value: number | null;
  after_value: number | null;
  unit: string;
  audience: string;
  sub_region_id: string | null;
  method_caveat: string;
  evidence_source: string;
  is_demo: boolean;
  is_published: boolean;
  created_at: string;
  updated_at: string;
}

interface ListResponse {
  items: SuccessStory[];
}

interface SeedResponse {
  items: SuccessStory[];
}

interface StoryResponse {
  story: SuccessStory;
}

const METRIC_SOURCES: MetricSource[] = ['pilot_scoreboard', 'municipal_roi', 'manual'];

const METRIC_SOURCE_LABEL: Record<MetricSource, string> = {
  pilot_scoreboard: 'AG83 pilot scoreboard',
  municipal_roi: 'AG76 municipal ROI',
  manual: 'Manual / illustrative',
};

const AUDIENCE_OPTIONS = [
  'all_residents',
  'municipality',
  'kanton',
  'verein_members',
  'volunteers',
  'recipients',
];

const AUDIENCE_LABEL: Record<string, string> = {
  all_residents: 'All residents',
  municipality: 'Municipality',
  kanton: 'Kanton',
  verein_members: 'Verein members',
  volunteers: 'Volunteers',
  recipients: 'Care recipients',
};

interface FormState {
  title: string;
  narrative: string;
  metric_source: MetricSource;
  metric_key: string;
  before_value: string;
  after_value: string;
  unit: string;
  audience: string;
  sub_region_id: string;
  method_caveat: string;
  evidence_source: string;
  is_demo: boolean;
  is_published: boolean;
}

function emptyForm(): FormState {
  return {
    title: '',
    narrative: '',
    metric_source: 'manual',
    metric_key: '',
    before_value: '',
    after_value: '',
    unit: '',
    audience: 'all_residents',
    sub_region_id: '',
    method_caveat: '',
    evidence_source: '',
    is_demo: true,
    is_published: false,
  };
}

function fromStory(s: SuccessStory): FormState {
  return {
    title: s.title,
    narrative: s.narrative,
    metric_source: s.metric_source,
    metric_key: s.metric_key ?? '',
    before_value: s.before_value === null ? '' : String(s.before_value),
    after_value: s.after_value === null ? '' : String(s.after_value),
    unit: s.unit,
    audience: s.audience,
    sub_region_id: s.sub_region_id ?? '',
    method_caveat: s.method_caveat,
    evidence_source: s.evidence_source,
    is_demo: s.is_demo,
    is_published: s.is_published,
  };
}

function toPayload(form: FormState): Record<string, unknown> {
  return {
    title: form.title.trim(),
    narrative: form.narrative.trim(),
    metric_source: form.metric_source,
    metric_key: form.metric_key.trim() === '' ? null : form.metric_key.trim(),
    before_value: form.before_value === '' ? null : Number(form.before_value),
    after_value: form.after_value === '' ? null : Number(form.after_value),
    unit: form.unit.trim(),
    audience: form.audience.trim(),
    sub_region_id: form.sub_region_id.trim() === '' ? null : form.sub_region_id.trim(),
    method_caveat: form.method_caveat.trim(),
    evidence_source: form.evidence_source.trim(),
    is_demo: form.is_demo,
    is_published: form.is_published,
  };
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function SuccessStoryAdminPage(): JSX.Element {
  usePageTitle('Success Stories');
  const { showToast } = useToast();

  const [items, setItems] = useState<SuccessStory[]>([]);
  const [loading, setLoading] = useState(true);
  const [seeding, setSeeding] = useState(false);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [refreshingId, setRefreshingId] = useState<string | null>(null);

  const editModal = useDisclosure();
  const deleteModal = useDisclosure();

  const [editing, setEditing] = useState<SuccessStory | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [target, setTarget] = useState<SuccessStory | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<ListResponse>(
        '/v2/admin/caring-community/success-stories',
      );
      if (res.success && res.data) {
        setItems(res.data.items ?? []);
      } else {
        setItems([]);
      }
    } catch {
      showToast('Failed to load success stories', 'error');
      setItems([]);
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

  const openEdit = (s: SuccessStory) => {
    setEditing(s);
    setForm(fromStory(s));
    editModal.onOpen();
  };

  const openDelete = (s: SuccessStory) => {
    setTarget(s);
    deleteModal.onOpen();
  };

  const handleSeed = async () => {
    setSeeding(true);
    try {
      const res = await api.post<SeedResponse>(
        '/v2/admin/caring-community/success-stories/seed-demo',
        {},
      );
      if (res.success && res.data) {
        setItems(res.data.items ?? []);
        showToast('Seeded 3 demo cards', 'success');
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
      const payload = toPayload(form);
      if (editing) {
        const res = await api.put<StoryResponse>(
          `/v2/admin/caring-community/success-stories/${editing.id}`,
          payload,
        );
        if (res.success && res.data?.story) {
          showToast('Story updated', 'success');
          editModal.onClose();
          await load();
        } else {
          showToast(res.error ?? 'Update failed', 'error');
        }
      } else {
        const res = await api.post<StoryResponse>(
          '/v2/admin/caring-community/success-stories',
          payload,
        );
        if (res.success && res.data?.story) {
          showToast('Story created', 'success');
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
        `/v2/admin/caring-community/success-stories/${target.id}`,
      );
      if (res.success) {
        showToast('Story removed', 'success');
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

  const handleRefresh = async (s: SuccessStory) => {
    setRefreshingId(s.id);
    try {
      const res = await api.post<StoryResponse>(
        `/v2/admin/caring-community/success-stories/${s.id}/refresh-live`,
        {},
      );
      if (res.success && res.data?.story) {
        showToast('Live metric refreshed', 'success');
        await load();
      } else {
        showToast(res.error ?? 'Refresh failed', 'error');
      }
    } catch {
      showToast('Refresh failed', 'error');
    } finally {
      setRefreshingId(null);
    }
  };

  const isFormValid =
    form.title.trim() !== '' &&
    form.narrative.trim() !== '' &&
    form.method_caveat.trim() !== '' &&
    form.evidence_source.trim() !== '';

  const formatDelta = (s: SuccessStory): string => {
    const before = s.before_value === null ? '—' : s.before_value.toLocaleString();
    const after = s.after_value === null ? '—' : s.after_value.toLocaleString();
    const unit = s.unit ? ` ${s.unit}` : '';
    return `${before}${unit} → ${after}${unit}`;
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Success Stories"
        subtitle="AG91 — proof cards tied to live metrics"
        icon={<Award size={24} />}
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
              New story
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
                <Award size={32} />
              </div>
              <div className="max-w-md space-y-1">
                <h3 className="text-base font-semibold">No success stories yet</h3>
                <p className="text-sm text-default-500">
                  Seed 3 illustrative demo cards to show what proof cards look like to
                  procurement officers and funders. They are clearly labelled "Demo /
                  illustrative" until you mark them as real evidence by hand.
                </p>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <Button
                  color="primary"
                  startContent={<Sparkles size={14} />}
                  onPress={() => void handleSeed()}
                  isLoading={seeding}
                >
                  Seed 3 demo cards
                </Button>
                <Button
                  variant="flat"
                  startContent={<Plus size={14} />}
                  onPress={openCreate}
                >
                  New story
                </Button>
              </div>
            </div>
          </CardBody>
        </Card>
      ) : (
        <Card shadow="sm">
          <CardBody>
            <Table aria-label="Success stories" removeWrapper>
              <TableHeader>
                <TableColumn>Title</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Demo</TableColumn>
                <TableColumn>Audience</TableColumn>
                <TableColumn>Metric</TableColumn>
                <TableColumn aria-label="Row actions">Actions</TableColumn>
              </TableHeader>
              <TableBody>
                {items.map((s) => (
                  <TableRow key={s.id}>
                    <TableCell>
                      <div className="flex flex-col">
                        <span className="font-medium">{s.title}</span>
                        <span className="line-clamp-1 max-w-md text-xs text-default-500">
                          {s.narrative}
                        </span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        color={s.is_published ? 'success' : 'default'}
                        variant="flat"
                      >
                        {s.is_published ? 'Published' : 'Unpublished'}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        color={s.is_demo ? 'warning' : 'success'}
                        variant="flat"
                      >
                        {s.is_demo ? 'Demo' : 'Real'}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">
                        {AUDIENCE_LABEL[s.audience] ?? s.audience}
                      </span>
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-col">
                        <span className="text-sm">{formatDelta(s)}</span>
                        <span className="text-xs text-default-500">
                          {METRIC_SOURCE_LABEL[s.metric_source]}
                        </span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1">
                        {s.metric_source !== 'manual' && (
                          <Tooltip content="Refresh from live data">
                            <Button
                              size="sm"
                              variant="light"
                              isIconOnly
                              aria-label="Refresh from live data"
                              onPress={() => void handleRefresh(s)}
                              isLoading={refreshingId === s.id}
                            >
                              <RefreshCw size={14} />
                            </Button>
                          </Tooltip>
                        )}
                        <Button
                          size="sm"
                          variant="light"
                          isIconOnly
                          aria-label="Edit"
                          onPress={() => openEdit(s)}
                        >
                          <Pencil size={14} />
                        </Button>
                        <Button
                          size="sm"
                          variant="light"
                          color="danger"
                          isIconOnly
                          aria-label="Delete"
                          onPress={() => openDelete(s)}
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

      {/* Editor modal */}
      <Modal
        isOpen={editModal.isOpen}
        onClose={editModal.onClose}
        size="3xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader>
            {editing ? `Edit: ${editing.title}` : 'New success story'}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label="Title"
                placeholder="e.g. 30% lower information distribution effort"
                variant="bordered"
                isRequired
                value={form.title}
                onValueChange={(v) => setForm({ ...form, title: v })}
              />

              <Textarea
                label="Narrative"
                placeholder="2–4 sentences explaining the story to a procurement officer or funder."
                variant="bordered"
                isRequired
                minRows={3}
                value={form.narrative}
                onValueChange={(v) => setForm({ ...form, narrative: v })}
              />

              <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Select
                  label="Metric source"
                  variant="bordered"
                  selectedKeys={[form.metric_source]}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      metric_source: (e.target.value || 'manual') as MetricSource,
                    })
                  }
                >
                  {METRIC_SOURCES.map((s) => (
                    <SelectItem key={s}>{METRIC_SOURCE_LABEL[s]}</SelectItem>
                  ))}
                </Select>

                <Input
                  label="Metric key"
                  placeholder="e.g. approved_hours, formal_care_offset_chf"
                  description="Required for non-manual sources"
                  variant="bordered"
                  value={form.metric_key}
                  onValueChange={(v) => setForm({ ...form, metric_key: v })}
                />
              </div>

              <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                <Input
                  label="Before value"
                  type="number"
                  variant="bordered"
                  value={form.before_value}
                  onValueChange={(v) => setForm({ ...form, before_value: v })}
                />
                <Input
                  label="After value"
                  type="number"
                  variant="bordered"
                  value={form.after_value}
                  onValueChange={(v) => setForm({ ...form, after_value: v })}
                />
                <Input
                  label="Unit"
                  placeholder="e.g. hours, CHF, %"
                  variant="bordered"
                  value={form.unit}
                  onValueChange={(v) => setForm({ ...form, unit: v })}
                />
              </div>

              <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Select
                  label="Audience"
                  variant="bordered"
                  selectedKeys={[form.audience]}
                  onChange={(e) =>
                    setForm({ ...form, audience: e.target.value || 'all_residents' })
                  }
                >
                  {AUDIENCE_OPTIONS.map((a) => (
                    <SelectItem key={a}>{AUDIENCE_LABEL[a] ?? a}</SelectItem>
                  ))}
                </Select>

                <Input
                  label="Sub-region ID (optional)"
                  placeholder="Leave blank for all"
                  variant="bordered"
                  value={form.sub_region_id}
                  onValueChange={(v) => setForm({ ...form, sub_region_id: v })}
                />
              </div>

              <Textarea
                label="Method caveat"
                placeholder='e.g. "Pilot region only; n=42 members; 90-day window"'
                description="Required — keeps claims honest."
                variant="bordered"
                isRequired
                minRows={2}
                value={form.method_caveat}
                onValueChange={(v) => setForm({ ...form, method_caveat: v })}
              />

              <Input
                label="Evidence source"
                placeholder='e.g. "AG83 pilot scoreboard 2026-04-30 baseline"'
                description="Required — where the number comes from."
                variant="bordered"
                isRequired
                value={form.evidence_source}
                onValueChange={(v) => setForm({ ...form, evidence_source: v })}
              />

              <Divider />

              <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div className="flex items-center justify-between rounded-lg border border-default-200 px-4 py-3">
                  <div>
                    <p className="text-sm font-medium">Demo / illustrative</p>
                    <p className="text-xs text-default-500">
                      Card is labelled as demo on the gallery
                    </p>
                  </div>
                  <Switch
                    isSelected={form.is_demo}
                    onValueChange={(v) => setForm({ ...form, is_demo: v })}
                  />
                </div>
                <div className="flex items-center justify-between rounded-lg border border-default-200 px-4 py-3">
                  <div>
                    <p className="text-sm font-medium">Published</p>
                    <p className="text-xs text-default-500">
                      Visible on the member-facing gallery
                    </p>
                  </div>
                  <Switch
                    isSelected={form.is_published}
                    onValueChange={(v) => setForm({ ...form, is_published: v })}
                  />
                </div>
              </div>
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
              {editing ? 'Save changes' : 'Create story'}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete confirmation modal */}
      <Modal isOpen={deleteModal.isOpen} onClose={deleteModal.onClose} size="md">
        <ModalContent>
          <ModalHeader>Remove success story</ModalHeader>
          <ModalBody>
            {target ? (
              <div className="space-y-3">
                <p className="text-sm">
                  This will permanently remove{' '}
                  <span className="font-semibold">{target.title}</span> from the
                  gallery.
                </p>
                <Divider />
                <p className="text-xs text-default-500">This action cannot be undone.</p>
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
