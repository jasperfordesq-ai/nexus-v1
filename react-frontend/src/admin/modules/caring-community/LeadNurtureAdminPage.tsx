// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
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
  Tooltip,
} from '@heroui/react';
import Download from 'lucide-react/icons/download';
import Filter from 'lucide-react/icons/filter';
import Info from 'lucide-react/icons/info';
import Mailbox from 'lucide-react/icons/mailbox';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import UserMinus from 'lucide-react/icons/user-minus';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

const SEGMENTS = ['municipality', 'investor', 'business', 'resident', 'partner'] as const;
const STAGES = ['captured', 'contacted', 'engaged', 'qualified', 'converted', 'dormant', 'unsubscribed'] as const;

type Segment = typeof SEGMENTS[number];
type Stage = typeof STAGES[number];

interface Contact {
  id: string;
  name: string | null;
  email: string;
  phone: string | null;
  organisation: string | null;
  segment: Segment;
  source: string | null;
  locale: string | null;
  interests: string[];
  stage: Stage;
  consent: boolean;
  consent_at: string | null;
  follow_up_at: string | null;
  last_contacted_at: string | null;
  notes: string | null;
  created_at: string;
}

interface ListResponse {
  items: Contact[];
  total: number;
  last_updated_at: string | null;
}

interface Summary {
  total: number;
  by_segment: Partial<Record<Segment, number>>;
  by_stage: Partial<Record<Stage, number>>;
  last_updated_at: string | null;
}

const STAGE_COLOR: Record<Stage, 'default' | 'primary' | 'warning' | 'success' | 'secondary' | 'danger'> = {
  captured: 'default',
  contacted: 'primary',
  engaged: 'primary',
  qualified: 'warning',
  converted: 'success',
  dormant: 'secondary',
  unsubscribed: 'danger',
};

export default function LeadNurtureAdminPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('lead_nurture.meta.page_title'));
  const { showToast } = useToast();

  const [data, setData] = useState<ListResponse | null>(null);
  const [summary, setSummary] = useState<Summary | null>(null);
  const [loading, setLoading] = useState(true);
  const [segmentFilter, setSegmentFilter] = useState<string>('');
  const [stageFilter, setStageFilter] = useState<string>('');

  const [editing, setEditing] = useState<Contact | null>(null);
  const [draftStage, setDraftStage] = useState<Stage>('captured');
  const [draftFollowUp, setDraftFollowUp] = useState<string>('');
  const [draftNotes, setDraftNotes] = useState<string>('');
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (segmentFilter) params.set('segment', segmentFilter);
      if (stageFilter) params.set('stage', stageFilter);
      const qs = params.toString() ? `?${params.toString()}` : '';
      const [listRes, sumRes] = await Promise.all([
        api.get<ListResponse>(`/v2/admin/caring-community/leads${qs}`),
        api.get<Summary>('/v2/admin/caring-community/leads/summary'),
      ]);
      setData(listRes.data ?? null);
      setSummary(sumRes.data ?? null);
    } catch {
      showToast(t('lead_nurture.toasts.load_failed'), 'error');
    } finally {
      setLoading(false);
    }
  }, [segmentFilter, stageFilter, showToast, t]);

  useEffect(() => {
    load();
  }, [load]);

  const openEdit = (contact: Contact) => {
    setEditing(contact);
    setDraftStage(contact.stage);
    setDraftFollowUp(contact.follow_up_at ?? '');
    setDraftNotes(contact.notes ?? '');
  };

  const closeEdit = () => {
    setEditing(null);
    setDraftFollowUp('');
    setDraftNotes('');
  };

  const saveEdit = async () => {
    if (!editing) return;
    setSaving(true);
    try {
      await api.put(`/v2/admin/caring-community/leads/${editing.id}`, {
        stage: draftStage,
        follow_up_at: draftFollowUp || null,
        notes: draftNotes || null,
      });
      showToast(t('lead_nurture.toasts.updated'), 'success');
      closeEdit();
      await load();
    } catch {
      showToast(t('lead_nurture.toasts.update_failed'), 'error');
    } finally {
      setSaving(false);
    }
  };

  const unsubscribe = async (contact: Contact) => {
    if (!window.confirm(t('lead_nurture.confirm_unsubscribe', { email: contact.email }))) return;
    try {
      await api.post(`/v2/admin/caring-community/leads/${contact.id}/unsubscribe`);
      showToast(t('lead_nurture.toasts.unsubscribed'), 'success');
      await load();
    } catch {
      showToast(t('lead_nurture.toasts.unsubscribe_failed'), 'error');
    }
  };

  const exportCsv = async () => {
    try {
      const params = new URLSearchParams();
      if (segmentFilter) params.set('segment', segmentFilter);
      const qs = params.toString() ? `?${params.toString()}` : '';
      await api.download(`/v2/admin/caring-community/leads/export.csv${qs}`, {
        filename: 'lead-nurture-export.csv',
      });
      showToast(t('lead_nurture.toasts.exported'), 'success');
    } catch {
      showToast(t('lead_nurture.toasts.export_failed'), 'error');
    }
  };

  const emptyValue = t('lead_nurture.empty.value');
  const segmentLabel = useCallback((segment: Segment) => t(`lead_nurture.segments.${segment}`), [t]);
  const stageLabel = useCallback((stage: Stage) => t(`lead_nurture.stages.${stage}`), [t]);

  const segmentChips = useMemo(() => {
    if (!summary) return null;
    return SEGMENTS.map((s) => (
      <Chip key={s} size="sm" variant="flat">
        {segmentLabel(s)}: {summary.by_segment[s] ?? 0}
      </Chip>
    ));
  }, [segmentLabel, summary]);

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('lead_nurture.meta.title')}
        subtitle={t('lead_nurture.meta.subtitle')}
        icon={<Mailbox size={20} />}
        actions={
          <div className="flex gap-2">
            <Tooltip content={t('lead_nurture.actions.refresh')}>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                onPress={load}
                isLoading={loading}
                aria-label={t('lead_nurture.actions.refresh_aria')}
              >
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
            <Button
              size="sm"
              variant="flat"
              startContent={<Download size={14} />}
              onPress={exportCsv}
            >
              {t('lead_nurture.actions.export_csv')}
            </Button>
          </div>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">{t('lead_nurture.about.title')}</p>
              <p className="text-default-600">
                {t('lead_nurture.about.body')}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {summary && (
        <Card>
          <CardHeader className="pb-2">
            <span className="text-sm font-semibold">{t('lead_nurture.summary.title')}</span>
          </CardHeader>
          <CardBody className="pt-0 space-y-3">
            <div className="flex items-center gap-3">
              <span className="text-2xl font-bold">{summary.total}</span>
              <span className="text-sm text-default-500">{t('lead_nurture.summary.total_contacts')}</span>
            </div>
            <Divider />
            <div className="flex flex-wrap gap-2">
              <span className="text-xs text-default-500 self-center">{t('lead_nurture.summary.by_segment')}</span>
              {segmentChips}
            </div>
            <div className="flex flex-wrap gap-2">
              <span className="text-xs text-default-500 self-center">{t('lead_nurture.summary.by_stage')}</span>
              {STAGES.map((s) => (
                <Chip key={s} size="sm" variant="flat" color={STAGE_COLOR[s]}>
                  {stageLabel(s)}: {summary.by_stage[s] ?? 0}
                </Chip>
              ))}
            </div>
          </CardBody>
        </Card>
      )}

      <Card>
        <CardHeader className="pb-2 flex items-center gap-2">
          <Filter size={14} className="text-default-500" />
          <span className="text-sm font-semibold">{t('lead_nurture.filters.title')}</span>
        </CardHeader>
        <CardBody className="pt-0">
          <div className="flex flex-wrap gap-3">
            <Select
              size="sm"
              label={t('lead_nurture.filters.segment')}
              className="max-w-[200px]"
              description={t('lead_nurture.filters.segment_description')}
              selectedKeys={segmentFilter ? [segmentFilter] : []}
              onSelectionChange={(keys) => {
                const v = Array.from(keys)[0];
                setSegmentFilter(typeof v === 'string' ? v : '');
              }}
            >
              <SelectItem key="">{t('lead_nurture.filters.all_segments')}</SelectItem>
              <>{SEGMENTS.map((s) => <SelectItem key={s}>{segmentLabel(s)}</SelectItem>)}</>
            </Select>
            <Select
              size="sm"
              label={t('lead_nurture.filters.stage')}
              className="max-w-[200px]"
              selectedKeys={stageFilter ? [stageFilter] : []}
              onSelectionChange={(keys) => {
                const v = Array.from(keys)[0];
                setStageFilter(typeof v === 'string' ? v : '');
              }}
            >
              <SelectItem key="">{t('lead_nurture.filters.all_stages')}</SelectItem>
              <>{STAGES.map((s) => <SelectItem key={s}>{stageLabel(s)}</SelectItem>)}</>
            </Select>
          </div>
        </CardBody>
      </Card>

      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {!loading && data && (
        <Card>
          <CardHeader className="pb-2">
            <span className="text-sm font-semibold">{t('lead_nurture.contacts.title', { count: data.items.length })}</span>
          </CardHeader>
          <CardBody className="pt-0">
            {data.items.length === 0 ? (
              <p className="text-sm text-default-500 py-8 text-center">{t('lead_nurture.contacts.empty')}</p>
            ) : (
              <Table aria-label={t('lead_nurture.contacts.table_aria')} removeWrapper>
                <TableHeader>
                  <TableColumn>{t('lead_nurture.table.name_email')}</TableColumn>
                  <TableColumn>{t('lead_nurture.table.organisation')}</TableColumn>
                  <TableColumn>{t('lead_nurture.table.segment')}</TableColumn>
                  <TableColumn>{t('lead_nurture.table.stage')}</TableColumn>
                  <TableColumn>{t('lead_nurture.table.source')}</TableColumn>
                  <TableColumn>{t('lead_nurture.table.captured')}</TableColumn>
                  <TableColumn>{t('lead_nurture.table.follow_up')}</TableColumn>
                  <TableColumn>{t('lead_nurture.table.actions')}</TableColumn>
                </TableHeader>
                <TableBody>
                  {data.items.map((c) => (
                    <TableRow key={c.id}>
                      <TableCell>
                        <div className="flex flex-col">
                          <span className="text-sm font-medium">{c.name ?? emptyValue}</span>
                          <span className="text-xs text-default-500">{c.email}</span>
                        </div>
                      </TableCell>
                      <TableCell className="text-sm">{c.organisation ?? emptyValue}</TableCell>
                      <TableCell>
                        <Chip size="sm" variant="flat">{segmentLabel(c.segment)}</Chip>
                      </TableCell>
                      <TableCell>
                        <Chip size="sm" variant="flat" color={STAGE_COLOR[c.stage]}>
                          {stageLabel(c.stage)}
                        </Chip>
                      </TableCell>
                      <TableCell className="text-xs text-default-500">{c.source ?? emptyValue}</TableCell>
                      <TableCell className="text-xs text-default-500">
                        {c.consent_at ? new Date(c.consent_at).toLocaleDateString() : emptyValue}
                      </TableCell>
                      <TableCell className="text-xs text-default-500">
                        {c.follow_up_at ? new Date(c.follow_up_at).toLocaleDateString() : emptyValue}
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button size="sm" variant="flat" onPress={() => openEdit(c)}>
                            {t('lead_nurture.actions.edit')}
                          </Button>
                          {c.stage !== 'unsubscribed' && (
                            <Tooltip content={t('lead_nurture.actions.mark_unsubscribed')}>
                              <Button
                                size="sm"
                                isIconOnly
                                variant="flat"
                                color="danger"
                                onPress={() => unsubscribe(c)}
                                aria-label={t('lead_nurture.actions.unsubscribe_aria')}
                              >
                                <UserMinus size={14} />
                              </Button>
                            </Tooltip>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardBody>
        </Card>
      )}

      <Modal isOpen={editing !== null} onClose={closeEdit} size="lg">
        <ModalContent>
          <ModalHeader>{t('lead_nurture.modal.title')}</ModalHeader>
          <ModalBody>
            {editing && (
              <div className="space-y-3">
                <p className="text-sm text-default-500">{editing.name ?? emptyValue} · {editing.email}</p>
                <Select
                  label={t('lead_nurture.filters.stage')}
                  description={t('lead_nurture.modal.stage_description')}
                  selectedKeys={[draftStage]}
                  onSelectionChange={(keys) => {
                    const v = Array.from(keys)[0];
                    if (typeof v === 'string') setDraftStage(v as Stage);
                  }}
                >
                  {STAGES.map((s) => (
                    <SelectItem key={s}>{stageLabel(s)}</SelectItem>
                  ))}
                </Select>
                <Input
                  label={t('lead_nurture.fields.follow_up_date')}
                  placeholder={t('lead_nurture.fields.follow_up_placeholder')}
                  value={draftFollowUp}
                  onValueChange={setDraftFollowUp}
                />
                <Textarea
                  label={t('lead_nurture.fields.notes')}
                  value={draftNotes}
                  onValueChange={setDraftNotes}
                  minRows={3}
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeEdit} isDisabled={saving}>{t('lead_nurture.actions.cancel')}</Button>
            <Button color="primary" onPress={saveEdit} isLoading={saving}>{t('lead_nurture.actions.save')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
