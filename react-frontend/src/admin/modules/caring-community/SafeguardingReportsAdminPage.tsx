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
  useDisclosure,
} from '@heroui/react';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import TriangleAlert from 'lucide-react/icons/triangle-alert';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

type Severity = 'low' | 'medium' | 'high' | 'critical';
type Status = 'submitted' | 'triaged' | 'investigating' | 'resolved' | 'dismissed';

type Report = {
  id: number;
  reporter_id: number;
  reporter_name: string;
  subject_user_id: number | null;
  subject_user_name: string | null;
  subject_organisation_id: number | null;
  category: string;
  severity: Severity;
  description: string;
  evidence_url: string | null;
  status: Status;
  assigned_to_user_id: number | null;
  assigned_to_name: string | null;
  review_due_at: string | null;
  is_overdue: boolean;
  escalated: boolean;
  escalated_at: string | null;
  resolution_notes: string | null;
  resolved_at: string | null;
  created_at: string;
  updated_at: string;
};

type Action = {
  id: number;
  actor_id: number;
  actor_name: string;
  action: string;
  notes: string | null;
  created_at: string;
};

type ReportDetail = Report & { actions: Action[] };

const SEVERITY_COLOR: Record<Severity, 'danger' | 'warning' | 'default' | 'success'> = {
  critical: 'danger',
  high: 'warning',
  medium: 'default',
  low: 'success',
};

const STATUS_COLOR: Record<Status, 'default' | 'primary' | 'warning' | 'success'> = {
  submitted: 'primary',
  triaged: 'primary',
  investigating: 'warning',
  resolved: 'success',
  dismissed: 'default',
};

const ALL_STATUSES: Status[] = ['submitted', 'triaged', 'investigating', 'resolved', 'dismissed'];
const ALL_SEVERITIES: Severity[] = ['critical', 'high', 'medium', 'low'];

export default function SafeguardingReportsAdminPage(): JSX.Element {
  usePageTitle('Safeguarding Reports - Admin');
  const { showToast } = useToast();

  const [reports, setReports] = useState<Report[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [severityFilter, setSeverityFilter] = useState<string>('');

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<ReportDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const detailModal = useDisclosure();

  const [assigneeId, setAssigneeId] = useState('');
  const [escalationNote, setEscalationNote] = useState('');
  const [statusToSet, setStatusToSet] = useState<Status>('triaged');
  const [statusNotes, setStatusNotes] = useState('');
  const [newNote, setNewNote] = useState('');

  const params = useMemo(() => {
    const p = new URLSearchParams();
    if (statusFilter) p.set('status', statusFilter);
    if (severityFilter) p.set('severity', severityFilter);
    return p.toString();
  }, [statusFilter, severityFilter]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const url = `/v2/admin/caring-community/safeguarding/reports${params ? `?${params}` : ''}`;
      const res = await api.get<{ items: Report[] }>(url);
      if (res.success && res.data) {
        setReports(res.data.items ?? []);
      } else {
        setReports([]);
      }
    } catch {
      showToast('Failed to load reports', 'error');
      setReports([]);
    } finally {
      setLoading(false);
    }
  }, [params, showToast]);

  useEffect(() => {
    void load();
  }, [load]);

  const openDetail = useCallback(
    async (id: number) => {
      setSelectedId(id);
      setDetail(null);
      setDetailLoading(true);
      detailModal.onOpen();
      try {
        const res = await api.get<ReportDetail>(
          `/v2/admin/caring-community/safeguarding/reports/${id}`,
        );
        if (res.success && res.data) {
          setDetail(res.data);
        }
      } catch {
        showToast('Failed to load report detail', 'error');
      } finally {
        setDetailLoading(false);
      }
    },
    [detailModal, showToast],
  );

  const handleAssign = async () => {
    if (selectedId === null) return;
    const id = Number(assigneeId.trim());
    if (!id) return;
    try {
      await api.post(`/v2/admin/caring-community/safeguarding/reports/${selectedId}/assign`, {
        assignee_user_id: id,
      });
      showToast('Assigned', 'success');
      setAssigneeId('');
      await openDetail(selectedId);
      await load();
    } catch {
      showToast('Assignment failed', 'error');
    }
  };

  const handleEscalate = async () => {
    if (selectedId === null) return;
    try {
      await api.post(`/v2/admin/caring-community/safeguarding/reports/${selectedId}/escalate`, {
        note: escalationNote.trim() || undefined,
      });
      showToast('Escalated', 'success');
      setEscalationNote('');
      await openDetail(selectedId);
      await load();
    } catch {
      showToast('Escalation failed', 'error');
    }
  };

  const handleStatus = async () => {
    if (selectedId === null) return;
    try {
      await api.post(`/v2/admin/caring-community/safeguarding/reports/${selectedId}/status`, {
        status: statusToSet,
        notes: statusNotes.trim() || undefined,
      });
      showToast('Status updated', 'success');
      setStatusNotes('');
      await openDetail(selectedId);
      await load();
    } catch {
      showToast('Status change failed', 'error');
    }
  };

  const handleNote = async () => {
    if (selectedId === null) return;
    const note = newNote.trim();
    if (!note) return;
    try {
      await api.post(`/v2/admin/caring-community/safeguarding/reports/${selectedId}/note`, {
        note,
      });
      showToast('Note added', 'success');
      setNewNote('');
      await openDetail(selectedId);
    } catch {
      showToast('Note failed', 'error');
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Safeguarding Reports"
        description="Review, assign, and resolve safeguarding concerns raised by members."
      />

      {/* About card */}
      <Card className="border-l-4 border-l-danger bg-danger-50 dark:bg-danger-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-danger" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-danger-800 dark:text-danger-200">About this page</p>
              <p className="text-default-600">
                This page manages formal safeguarding incidents — situations where a member's welfare, safety, or
                dignity may be at risk. Reports are submitted by members, coordinators, or administrators. All reports
                must be triaged within the SLA configured in Operating Policy. Critical and High severity reports
                require immediate attention. Reports are tenant-scoped and cannot be seen by other communities.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Severity legend */}
      <Card shadow="none" className="border border-divider">
        <CardHeader>
          <p className="text-sm font-semibold">Severity levels</p>
        </CardHeader>
        <Divider />
        <CardBody className="py-3">
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div className="flex items-start gap-2">
              <Chip size="sm" color="danger" variant="flat" className="shrink-0 mt-0.5">Critical</Chip>
              <p className="text-default-600">Immediate physical risk — escalate within 1 hour</p>
            </div>
            <div className="flex items-start gap-2">
              <Chip size="sm" color="warning" variant="flat" className="shrink-0 mt-0.5">High</Chip>
              <p className="text-default-600">Serious concern — triage within 24 hours</p>
            </div>
            <div className="flex items-start gap-2">
              <Chip size="sm" color="default" variant="flat" className="shrink-0 mt-0.5">Medium</Chip>
              <p className="text-default-600">Significant but non-urgent — triage within 72 hours</p>
            </div>
            <div className="flex items-start gap-2">
              <Chip size="sm" color="success" variant="flat" className="shrink-0 mt-0.5">Low</Chip>
              <p className="text-default-600">Minor concern — review at next coordinator meeting</p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Status workflow guide */}
      <Card shadow="none" className="border border-divider">
        <CardHeader>
          <p className="text-sm font-semibold">Status workflow</p>
        </CardHeader>
        <Divider />
        <CardBody className="py-3">
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5 text-sm">
            <div>
              <Chip size="sm" color="primary" variant="flat" className="mb-1">Submitted</Chip>
              <p className="text-xs text-default-500">Report received, not yet reviewed by a coordinator</p>
            </div>
            <div>
              <Chip size="sm" color="primary" variant="flat" className="mb-1">Triaged</Chip>
              <p className="text-xs text-default-500">Coordinator has assessed severity and assigned ownership</p>
            </div>
            <div>
              <Chip size="sm" color="warning" variant="flat" className="mb-1">Investigating</Chip>
              <p className="text-xs text-default-500">Active investigation or support intervention underway</p>
            </div>
            <div>
              <Chip size="sm" color="success" variant="flat" className="mb-1">Resolved</Chip>
              <p className="text-xs text-default-500">Closed with documented resolution notes — action taken</p>
            </div>
            <div>
              <Chip size="sm" color="default" variant="flat" className="mb-1">Dismissed</Chip>
              <p className="text-xs text-default-500">Closed with no further action — a reason must be recorded</p>
            </div>
          </div>
        </CardBody>
      </Card>

      <Card shadow="sm">
        <CardHeader className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-end gap-3">
            <Select
              label="Status"
              size="sm"
              variant="bordered"
              className="w-44"
              selectedKeys={statusFilter ? [statusFilter] : []}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              {[
                <SelectItem key="">All</SelectItem>,
                ...ALL_STATUSES.map((s) => <SelectItem key={s}>{s}</SelectItem>),
              ]}
            </Select>
            <Select
              label="Severity"
              size="sm"
              variant="bordered"
              className="w-44"
              selectedKeys={severityFilter ? [severityFilter] : []}
              onChange={(e) => setSeverityFilter(e.target.value)}
            >
              {[
                <SelectItem key="">All</SelectItem>,
                ...ALL_SEVERITIES.map((s) => <SelectItem key={s}>{s}</SelectItem>),
              ]}
            </Select>
          </div>
          <Button
            size="sm"
            variant="flat"
            startContent={<RefreshCw size={14} />}
            onPress={() => void load()}
            isLoading={loading}
          >
            Refresh
          </Button>
        </CardHeader>
        <Divider />
        <CardBody>
          {loading ? (
            <div className="flex justify-center py-12">
              <Spinner />
            </div>
          ) : reports.length === 0 ? (
            <p className="py-10 text-center text-sm text-default-500">No reports.</p>
          ) : (
            <Table aria-label="Safeguarding reports" removeWrapper>
              <TableHeader>
                <TableColumn>Severity</TableColumn>
                <TableColumn>Category</TableColumn>
                <TableColumn>Reporter</TableColumn>
                <TableColumn>Subject</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Assigned</TableColumn>
                <TableColumn>SLA</TableColumn>
                <TableColumn>Created</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody>
                {reports.map((r) => (
                  <TableRow key={r.id}>
                    <TableCell>
                      <Chip size="sm" color={SEVERITY_COLOR[r.severity]} variant="flat">
                        {r.severity}
                      </Chip>
                    </TableCell>
                    <TableCell>{r.category}</TableCell>
                    <TableCell>{r.reporter_name || `#${r.reporter_id}`}</TableCell>
                    <TableCell>
                      {r.subject_user_name ?? (r.subject_organisation_id ? `Org #${r.subject_organisation_id}` : '—')}
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={STATUS_COLOR[r.status]} variant="flat">
                        {r.status}
                      </Chip>
                    </TableCell>
                    <TableCell>{r.assigned_to_name ?? '—'}</TableCell>
                    <TableCell>
                      {r.is_overdue ? (
                        <Chip size="sm" color="danger" variant="bordered" startContent={<TriangleAlert size={12} />}>
                          Overdue
                        </Chip>
                      ) : r.review_due_at ? (
                        new Date(r.review_due_at).toLocaleString()
                      ) : (
                        '—'
                      )}
                    </TableCell>
                    <TableCell>{new Date(r.created_at).toLocaleDateString()}</TableCell>
                    <TableCell>
                      <Button size="sm" variant="flat" onPress={() => void openDetail(r.id)}>
                        Open
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      <Modal isOpen={detailModal.isOpen} onClose={detailModal.onClose} size="3xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>
            Report #{selectedId}
          </ModalHeader>
          <ModalBody>
            {detailLoading || !detail ? (
              <div className="flex justify-center py-12">
                <Spinner />
              </div>
            ) : (
              <div className="space-y-5">
                <div className="flex flex-wrap items-center gap-2">
                  <Chip size="sm" color={SEVERITY_COLOR[detail.severity]} variant="flat">
                    {detail.severity}
                  </Chip>
                  <Chip size="sm" color={STATUS_COLOR[detail.status]} variant="flat">
                    {detail.status}
                  </Chip>
                  {detail.escalated && (
                    <Chip size="sm" color="danger" variant="bordered">
                      Escalated
                    </Chip>
                  )}
                  {detail.is_overdue && (
                    <Chip size="sm" color="danger" variant="flat">
                      Overdue
                    </Chip>
                  )}
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-default-500">Category</p>
                  <p className="text-sm">{detail.category}</p>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-default-500">Reporter</p>
                  <p className="text-sm">{detail.reporter_name || `#${detail.reporter_id}`}</p>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-default-500">Subject</p>
                  <p className="text-sm">
                    {detail.subject_user_name ?? (detail.subject_organisation_id ? `Org #${detail.subject_organisation_id}` : '—')}
                  </p>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-default-500">Description</p>
                  <p className="whitespace-pre-wrap text-sm">{detail.description}</p>
                </div>

                {detail.evidence_url && (
                  <div>
                    <p className="text-xs uppercase tracking-wide text-default-500">Evidence</p>
                    <a
                      href={detail.evidence_url}
                      target="_blank"
                      rel="noreferrer"
                      className="text-sm text-primary underline"
                    >
                      {detail.evidence_url}
                    </a>
                  </div>
                )}

                {detail.resolution_notes && (
                  <div>
                    <p className="text-xs uppercase tracking-wide text-default-500">Resolution Notes</p>
                    <p className="text-sm whitespace-pre-wrap">{detail.resolution_notes}</p>
                  </div>
                )}

                <Divider />

                <div className="space-y-3">
                  <h3 className="text-sm font-semibold">Actions</h3>

                  <div className="flex flex-wrap items-end gap-2">
                    <Input
                      label="Assignee user ID"
                      size="sm"
                      variant="bordered"
                      value={assigneeId}
                      onValueChange={setAssigneeId}
                      className="w-48"
                    />
                    <Button size="sm" color="primary" variant="flat" onPress={() => void handleAssign()}>
                      Assign
                    </Button>
                  </div>

                  <div className="flex flex-wrap items-end gap-2">
                    <Input
                      label="Escalation note (optional)"
                      size="sm"
                      variant="bordered"
                      value={escalationNote}
                      onValueChange={setEscalationNote}
                      className="flex-1"
                    />
                    <Button size="sm" color="warning" variant="flat" onPress={() => void handleEscalate()}>
                      Escalate
                    </Button>
                  </div>

                  <div className="flex flex-wrap items-end gap-2">
                    <Select
                      label="Set status"
                      size="sm"
                      variant="bordered"
                      selectedKeys={[statusToSet]}
                      onChange={(e) => setStatusToSet((e.target.value as Status) || 'triaged')}
                      className="w-44"
                    >
                      {ALL_STATUSES.map((s) => (
                        <SelectItem key={s}>{s}</SelectItem>
                      ))}
                    </Select>
                    <Input
                      label="Notes (optional)"
                      size="sm"
                      variant="bordered"
                      value={statusNotes}
                      onValueChange={setStatusNotes}
                      className="flex-1"
                    />
                    <Button size="sm" color="primary" onPress={() => void handleStatus()}>
                      Update
                    </Button>
                  </div>
                  {(statusToSet === 'resolved' || statusToSet === 'dismissed') && (
                    <p className="text-xs text-default-500 mt-1">
                      {statusToSet === 'resolved'
                        ? '"Resolved" closes this report with documented resolution notes. Use when action has been taken.'
                        : '"Dismissed" closes this report with no further action. A reason must be recorded in the notes above.'}
                    </p>
                  )}

                  <div className="flex flex-col gap-2">
                    <Textarea
                      label="Add note"
                      size="sm"
                      variant="bordered"
                      value={newNote}
                      onValueChange={setNewNote}
                      minRows={2}
                    />
                    <div className="flex justify-end">
                      <Button size="sm" variant="flat" onPress={() => void handleNote()}>
                        Add note
                      </Button>
                    </div>
                  </div>
                </div>

                <Divider />

                <div className="space-y-3">
                  <h3 className="text-sm font-semibold">History</h3>
                  {detail.actions.length === 0 ? (
                    <p className="text-sm text-default-500">No actions yet.</p>
                  ) : (
                    <ul className="space-y-2">
                      {detail.actions.map((a) => (
                        <li key={a.id} className="rounded-lg border border-default-200 p-3">
                          <div className="flex items-center justify-between">
                            <p className="text-sm font-medium">{a.action}</p>
                            <p className="text-xs text-default-500">
                              {new Date(a.created_at).toLocaleString()}
                            </p>
                          </div>
                          <p className="mt-1 text-xs text-default-500">by {a.actor_name || `#${a.actor_id}`}</p>
                          {a.notes && <p className="mt-1 text-sm whitespace-pre-wrap">{a.notes}</p>}
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={detailModal.onClose}>
              Close
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
