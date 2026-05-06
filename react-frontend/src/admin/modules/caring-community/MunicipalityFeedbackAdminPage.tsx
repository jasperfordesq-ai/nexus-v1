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
} from '@heroui/react';
import Inbox from 'lucide-react/icons/inbox';
import Download from 'lucide-react/icons/download';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import CheckCircle from 'lucide-react/icons/check-circle';
import XCircle from 'lucide-react/icons/x-circle';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type FeedbackStatus = 'new' | 'triaging' | 'in_progress' | 'resolved' | 'closed';
type FeedbackCategory = 'question' | 'idea' | 'issue_report' | 'sentiment';
type SentimentTag = 'positive' | 'neutral' | 'negative' | 'concerned' | null;

interface FeedbackRow {
  id: number;
  tenant_id: number;
  submitter_user_id: number | null;
  sub_region_id: number | null;
  category: FeedbackCategory;
  subject: string;
  body: string;
  sentiment_tag: SentimentTag;
  status: FeedbackStatus;
  assigned_user_id: number | null;
  assigned_role: string | null;
  triage_notes: string | null;
  resolution_notes: string | null;
  is_anonymous: boolean;
  is_public: boolean;
  created_at: string;
  updated_at: string;
}

interface DashboardStats {
  total_open: number;
  by_status: Record<string, number>;
  by_category: Record<string, number>;
  by_sub_region: Record<string, number>;
  recent_count_7d: number;
  sentiment_distribution: Record<string, number>;
}

interface PaginatedMeta {
  current_page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_more: boolean;
}

const STATUS_OPTIONS: FeedbackStatus[] = ['new', 'triaging', 'in_progress', 'resolved', 'closed'];
const CATEGORY_OPTIONS: FeedbackCategory[] = ['question', 'idea', 'issue_report', 'sentiment'];

const STATUS_COLOR: Record<FeedbackStatus, 'default' | 'primary' | 'warning' | 'success' | 'danger'> = {
  new: 'primary',
  triaging: 'warning',
  in_progress: 'warning',
  resolved: 'success',
  closed: 'default',
};

const CATEGORY_COLOR: Record<FeedbackCategory, 'default' | 'primary' | 'secondary' | 'warning'> = {
  question: 'primary',
  idea: 'secondary',
  issue_report: 'warning',
  sentiment: 'default',
};

function fmtDate(iso: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function relativeTime(iso: string): string {
  if (!iso) return '—';
  const diff = Date.now() - new Date(iso).getTime();
  const minutes = Math.floor(diff / 60000);
  if (minutes < 1) return 'just now';
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 30) return `${days}d ago`;
  const months = Math.floor(days / 30);
  return `${months}mo ago`;
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function MunicipalityFeedbackAdminPage() {
  usePageTitle('Municipality Feedback Inbox');
  const { showToast } = useToast();

  const [items, setItems] = useState<FeedbackRow[]>([]);
  const [meta, setMeta] = useState<PaginatedMeta | null>(null);
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  // Filters
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [categoryFilter, setCategoryFilter] = useState<string>('');
  const [subRegionFilter, setSubRegionFilter] = useState<string>('');
  const [page, setPage] = useState(1);

  // Detail modal
  const [selected, setSelected] = useState<FeedbackRow | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);
  const [savingTriage, setSavingTriage] = useState(false);
  const [triageStatus, setTriageStatus] = useState<FeedbackStatus>('new');
  const [triageAssignedUserId, setTriageAssignedUserId] = useState<string>('');
  const [triageAssignedRole, setTriageAssignedRole] = useState<string>('');
  const [triageNotes, setTriageNotes] = useState<string>('');
  const [resolutionNotes, setResolutionNotes] = useState<string>('');

  const loadStats = useCallback(async () => {
    try {
      const res = await api.get<DashboardStats>('/v2/admin/caring-community/feedback/dashboard');
      setStats(res.data ?? null);
    } catch {
      // Non-fatal
    }
  }, []);

  const loadList = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (statusFilter) params.set('status', statusFilter);
      if (categoryFilter) params.set('category', categoryFilter);
      if (subRegionFilter.trim()) params.set('sub_region_id', subRegionFilter.trim());
      params.set('page', String(page));
      params.set('per_page', '25');

      const res = await api.get<FeedbackRow[]>(
        `/v2/admin/caring-community/feedback?${params.toString()}`,
      );
      setItems(Array.isArray(res.data) ? res.data : []);
      setMeta((res.meta as PaginatedMeta | undefined) ?? null);
    } catch {
      showToast('Failed to load feedback', 'error');
    } finally {
      setLoading(false);
    }
  }, [statusFilter, categoryFilter, subRegionFilter, page, showToast]);

  useEffect(() => {
    void loadStats();
  }, [loadStats]);

  useEffect(() => {
    void loadList();
  }, [loadList]);

  const openDetail = useCallback((row: FeedbackRow) => {
    setSelected(row);
    setTriageStatus(row.status);
    setTriageAssignedUserId(row.assigned_user_id !== null ? String(row.assigned_user_id) : '');
    setTriageAssignedRole(row.assigned_role ?? '');
    setTriageNotes(row.triage_notes ?? '');
    setResolutionNotes(row.resolution_notes ?? '');
    setDetailOpen(true);
  }, []);

  const handleSaveTriage = useCallback(async () => {
    if (!selected) return;
    setSavingTriage(true);
    try {
      const res = await api.put<FeedbackRow>(
        `/v2/admin/caring-community/feedback/${selected.id}/triage`,
        {
          status: triageStatus,
          assigned_user_id: triageAssignedUserId.trim() === '' ? null : Number(triageAssignedUserId),
          assigned_role: triageAssignedRole.trim() === '' ? null : triageAssignedRole.trim(),
          triage_notes: triageNotes.trim() === '' ? null : triageNotes.trim(),
        },
      );
      showToast('Triage saved', 'success');
      if (res.data) setSelected(res.data);
      await loadList();
      await loadStats();
    } catch {
      showToast('Failed to save triage', 'error');
    } finally {
      setSavingTriage(false);
    }
  }, [selected, triageStatus, triageAssignedUserId, triageAssignedRole, triageNotes, loadList, loadStats, showToast]);

  const handleResolve = useCallback(async () => {
    if (!selected) return;
    if (resolutionNotes.trim() === '') {
      showToast('Resolution notes are required', 'error');
      return;
    }
    setSavingTriage(true);
    try {
      const res = await api.post<FeedbackRow>(
        `/v2/admin/caring-community/feedback/${selected.id}/resolve`,
        { resolution_notes: resolutionNotes.trim() },
      );
      showToast('Marked as resolved', 'success');
      if (res.data) setSelected(res.data);
      await loadList();
      await loadStats();
    } catch {
      showToast('Failed to resolve', 'error');
    } finally {
      setSavingTriage(false);
    }
  }, [selected, resolutionNotes, loadList, loadStats, showToast]);

  const handleClose = useCallback(async () => {
    if (!selected) return;
    setSavingTriage(true);
    try {
      const res = await api.post<FeedbackRow>(
        `/v2/admin/caring-community/feedback/${selected.id}/close`,
        {},
      );
      showToast('Closed', 'success');
      if (res.data) setSelected(res.data);
      await loadList();
      await loadStats();
    } catch {
      showToast('Failed to close', 'error');
    } finally {
      setSavingTriage(false);
    }
  }, [selected, loadList, loadStats, showToast]);

  const handleExport = useCallback(async () => {
    try {
      const params = new URLSearchParams();
      if (statusFilter) params.set('status', statusFilter);
      if (categoryFilter) params.set('category', categoryFilter);
      const url = `/v2/admin/caring-community/feedback/export.csv${params.toString() ? `?${params.toString()}` : ''}`;
      await api.download(url, { filename: 'municipality-feedback-export.csv' });
    } catch {
      showToast('Failed to export', 'error');
    }
  }, [statusFilter, categoryFilter, showToast]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Municipality Feedback Inbox"
        subtitle="Receive, triage, and resolve resident feedback — questions, ideas, issues, and complaints routed to the municipality or community team."
        icon={<Inbox size={20} />}
        actions={
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant="flat"
              startContent={<RefreshCw size={14} />}
              onPress={() => {
                void loadList();
                void loadStats();
              }}
              isLoading={loading}
            >
              Refresh
            </Button>
            <Button
              size="sm"
              color="primary"
              startContent={<Download size={14} />}
              onPress={handleExport}
            >
              Export CSV
            </Button>
          </div>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                This inbox collects feedback submitted by residents through the platform. Each item is categorised (question, idea, issue, complaint) and assigned a sentiment score. Use the triage controls to assign ownership, add internal notes, and track resolution. Items visible to all residents are marked as public — replies may be seen by the submitter and other members.
              </p>
              <p className="text-default-500 text-xs pt-1">
                Status workflow — <span className="font-medium">submitted</span>: received, not yet reviewed; <span className="font-medium">triaged</span>: reviewed and assigned to an owner; <span className="font-medium">in_progress</span>: actively being worked on; <span className="font-medium">resolved</span>: closed with a documented resolution; <span className="font-medium">closed</span>: closed without resolution (with reason).
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Dashboard chips */}
      {stats && (
        <Card>
          <CardBody className="space-y-3">
            <div className="flex flex-wrap items-center gap-3">
              <Chip color="primary" variant="flat">
                Total open: <span className="font-semibold ml-1">{stats.total_open}</span>
              </Chip>
              <Chip color="default" variant="flat">
                Last 7 days: <span className="font-semibold ml-1">{stats.recent_count_7d}</span>
              </Chip>
            </div>
            <Divider />
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs uppercase tracking-wide text-default-500 mr-2">By status:</span>
              {Object.entries(stats.by_status).map(([k, v]) => (
                <Chip key={k} size="sm" variant="flat">
                  {k}: {v}
                </Chip>
              ))}
              {Object.keys(stats.by_status).length === 0 && (
                <span className="text-sm text-default-500">No submissions yet.</span>
              )}
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs uppercase tracking-wide text-default-500 mr-2">By category:</span>
              {Object.entries(stats.by_category).map(([k, v]) => (
                <Chip key={k} size="sm" variant="flat" color={CATEGORY_COLOR[k as FeedbackCategory] ?? 'default'}>
                  {k}: {v}
                </Chip>
              ))}
            </div>
            {Object.keys(stats.sentiment_distribution).length > 0 && (
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs uppercase tracking-wide text-default-500 mr-2">Sentiment:</span>
                {Object.entries(stats.sentiment_distribution).map(([k, v]) => (
                  <Chip key={k} size="sm" variant="flat">
                    {k}: {v}
                  </Chip>
                ))}
              </div>
            )}
          </CardBody>
        </Card>
      )}

      {/* Filter bar */}
      <Card>
        <CardBody className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <Select
            label="Status"
            placeholder="All statuses"
            selectedKeys={statusFilter ? [statusFilter] : []}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
            variant="bordered"
            size="sm"
          >
            {STATUS_OPTIONS.map((s) => (
              <SelectItem key={s}>{s}</SelectItem>
            ))}
          </Select>
          <Select
            label="Category"
            placeholder="All categories"
            selectedKeys={categoryFilter ? [categoryFilter] : []}
            onChange={(e) => {
              setCategoryFilter(e.target.value);
              setPage(1);
            }}
            variant="bordered"
            size="sm"
          >
            {CATEGORY_OPTIONS.map((c) => (
              <SelectItem key={c}>{c}</SelectItem>
            ))}
          </Select>
          <Input
            label="Sub-region ID"
            placeholder="e.g. 12"
            value={subRegionFilter}
            onValueChange={(v) => {
              setSubRegionFilter(v);
              setPage(1);
            }}
            variant="bordered"
            size="sm"
          />
        </CardBody>
      </Card>

      {/* Table */}
      <Card>
        <CardBody className="p-0">
          {loading ? (
            <div className="flex justify-center py-12">
              <Spinner size="lg" />
            </div>
          ) : items.length === 0 ? (
            <div className="flex flex-col items-center gap-3 py-12 text-default-500">
              <Inbox size={40} className="opacity-30" />
              <p className="text-sm">No feedback submissions match these filters.</p>
            </div>
          ) : (
            <Table
              aria-label="Feedback submissions"
              removeWrapper
              classNames={{ th: 'bg-default-100 text-xs font-semibold uppercase tracking-wide' }}
            >
              <TableHeader>
                <TableColumn>ID</TableColumn>
                <TableColumn>Category</TableColumn>
                <TableColumn>Subject</TableColumn>
                <TableColumn>Submitter</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Created</TableColumn>
              </TableHeader>
              <TableBody>
                {items.map((row) => (
                  <TableRow key={row.id} className="cursor-pointer hover:bg-default-50">
                    <TableCell className="text-xs font-mono text-default-500">#{row.id}</TableCell>
                    <TableCell>
                      <Chip size="sm" variant="flat" color={CATEGORY_COLOR[row.category]}>
                        {row.category}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <button
                        type="button"
                        className="text-left text-sm font-medium text-primary hover:underline"
                        onClick={() => openDetail(row)}
                      >
                        {row.subject}
                      </button>
                    </TableCell>
                    <TableCell className="text-xs text-default-500">
                      {row.is_anonymous ? (
                        <span className="italic">anonymous</span>
                      ) : (
                        <span>user #{row.submitter_user_id ?? '?'}</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" variant="flat" color={STATUS_COLOR[row.status]}>
                        {row.status}
                      </Chip>
                    </TableCell>
                    <TableCell className="text-xs text-default-500 whitespace-nowrap">
                      {relativeTime(row.created_at)}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
          {meta && meta.total_pages > 1 && (
            <div className="flex items-center justify-between p-3 border-t border-default-200">
              <span className="text-xs text-default-500">
                Page {meta.current_page} of {meta.total_pages} ({meta.total} total)
              </span>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="flat"
                  isDisabled={meta.current_page <= 1}
                  onPress={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </Button>
                <Button
                  size="sm"
                  variant="flat"
                  isDisabled={!meta.has_more}
                  onPress={() => setPage((p) => p + 1)}
                >
                  Next
                </Button>
              </div>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Detail / triage modal */}
      <Modal isOpen={detailOpen} onOpenChange={setDetailOpen} size="2xl" placement="center">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex flex-col gap-1">
                <div className="flex items-center gap-2">
                  <Inbox size={18} className="text-primary" />
                  <span className="text-base">Feedback #{selected?.id}</span>
                  {selected && (
                    <Chip size="sm" variant="flat" color={STATUS_COLOR[selected.status]} className="ml-2">
                      {selected.status}
                    </Chip>
                  )}
                </div>
                <p className="text-sm font-normal text-default-500">{selected?.subject}</p>
              </ModalHeader>
              <ModalBody className="gap-4">
                {selected && (
                  <>
                    <div className="grid grid-cols-2 gap-3 text-sm">
                      <div>
                        <span className="text-default-500">Category: </span>
                        <Chip size="sm" variant="flat" color={CATEGORY_COLOR[selected.category]}>
                          {selected.category}
                        </Chip>
                      </div>
                      <div>
                        <span className="text-default-500">Submitter: </span>
                        {selected.is_anonymous ? (
                          <span className="italic">anonymous (id #{selected.submitter_user_id ?? '?'} — admin only)</span>
                        ) : (
                          <span>user #{selected.submitter_user_id ?? '?'}</span>
                        )}
                      </div>
                      <div>
                        <span className="text-default-500">Sentiment: </span>
                        <span>{selected.sentiment_tag ?? '—'}</span>
                      </div>
                      <div>
                        <span className="text-default-500">Sub-region: </span>
                        <span>{selected.sub_region_id ?? '—'}</span>
                      </div>
                      <div className="col-span-2">
                        <span className="text-default-500">Public visible: </span>
                        <span>{selected.is_public ? 'yes' : 'no'}</span>
                      </div>
                      <div className="col-span-2 text-xs text-default-500">
                        Submitted {fmtDate(selected.created_at)} · Updated {fmtDate(selected.updated_at)}
                      </div>
                    </div>
                    <Divider />
                    <div>
                      <p className="text-xs uppercase tracking-wide text-default-500 mb-1">Body</p>
                      <p className="text-sm whitespace-pre-wrap leading-relaxed">{selected.body}</p>
                    </div>
                    <Divider />
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <Select
                        label="Status"
                        selectedKeys={[triageStatus]}
                        onChange={(e) => setTriageStatus(e.target.value as FeedbackStatus)}
                        variant="bordered"
                        size="sm"
                      >
                        {STATUS_OPTIONS.map((s) => (
                          <SelectItem key={s}>{s}</SelectItem>
                        ))}
                      </Select>
                      <Input
                        label="Assigned User ID"
                        placeholder="Numeric user ID"
                        value={triageAssignedUserId}
                        onValueChange={setTriageAssignedUserId}
                        variant="bordered"
                        size="sm"
                      />
                      <Input
                        label="Assigned Role"
                        placeholder="e.g. coordinator, municipality_announcer"
                        value={triageAssignedRole}
                        onValueChange={setTriageAssignedRole}
                        variant="bordered"
                        size="sm"
                      />
                    </div>
                    <Textarea
                      label="Triage Notes"
                      placeholder="Internal notes for the triage team"
                      description="Internal only — not visible to the submitter. Use for team coordination, links to related issues, or escalation context."
                      value={triageNotes}
                      onValueChange={setTriageNotes}
                      variant="bordered"
                      size="sm"
                      minRows={2}
                    />
                    <Textarea
                      label="Resolution Notes (required for Resolve)"
                      placeholder="What was done to address this?"
                      description="Required when resolving. Summarise what action was taken. May be shown to the submitter depending on the item's public visibility setting."
                      value={resolutionNotes}
                      onValueChange={setResolutionNotes}
                      variant="bordered"
                      size="sm"
                      minRows={2}
                    />
                  </>
                )}
              </ModalBody>
              <ModalFooter className="flex-wrap gap-2">
                <Button variant="flat" onPress={onClose} isDisabled={savingTriage}>
                  Cancel
                </Button>
                <Button
                  color="default"
                  variant="flat"
                  startContent={<XCircle size={14} />}
                  onPress={handleClose}
                  isLoading={savingTriage}
                >
                  Close (no resolution)
                </Button>
                <Button
                  color="success"
                  startContent={<CheckCircle size={14} />}
                  onPress={handleResolve}
                  isLoading={savingTriage}
                >
                  Resolve
                </Button>
                <Button color="primary" onPress={handleSaveTriage} isLoading={savingTriage}>
                  Save Triage
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
