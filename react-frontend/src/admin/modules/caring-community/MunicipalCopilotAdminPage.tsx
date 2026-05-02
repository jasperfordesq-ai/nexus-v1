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
import Wand2 from 'lucide-react/icons/wand-2';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Sparkles from 'lucide-react/icons/sparkles';
import CheckCircle2 from 'lucide-react/icons/check-circle-2';
import XCircle from 'lucide-react/icons/x-circle';
import AlertTriangle from 'lucide-react/icons/alert-triangle';
import Info from 'lucide-react/icons/info';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type ProposalStatus = 'proposed' | 'accepted' | 'rejected' | 'published';
type ToneAssessment = 'too_formal' | 'too_informal' | 'condescending' | 'ok';

interface Proposal {
  id: string;
  draft_text: string;
  polished_text: string;
  tone_assessment: ToneAssessment;
  clarity_warnings: string[];
  audience_suggestion: string;
  audience_hint: string;
  sub_region_id: string | null;
  moderation_flags: string[];
  model_used: string;
  created_by: number;
  created_at: string;
  status: ProposalStatus;
  accepted_at: string | null;
  rejected_at: string | null;
  rejection_reason: string | null;
  source_announcement_id: number | null;
  updated_at: string;
}

interface ListResponse {
  items: Proposal[];
  limit: number;
}

interface ProposalResponse {
  proposal: Proposal;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const STATUS_COLORS: Record<ProposalStatus, 'primary' | 'warning' | 'default' | 'success'> = {
  proposed: 'primary',
  accepted: 'warning',
  rejected: 'default',
  published: 'success',
};

const TONE_LABELS: Record<ToneAssessment, string> = {
  too_formal: 'Too formal',
  too_informal: 'Too informal',
  condescending: 'Condescending',
  ok: 'Tone OK',
};

const TONE_COLORS: Record<ToneAssessment, 'success' | 'warning' | 'danger'> = {
  ok: 'success',
  too_formal: 'warning',
  too_informal: 'warning',
  condescending: 'danger',
};

function formatTime(iso: string | null): string {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

function truncate(text: string, n: number): string {
  if (text.length <= n) return text;
  return text.slice(0, n).trimEnd() + '…';
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function MunicipalCopilotAdminPage() {
  usePageTitle('Municipal Communication Copilot');
  const { showToast } = useToast();

  const [proposals, setProposals] = useState<Proposal[]>([]);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);

  const [draft, setDraft] = useState('');
  const [audienceHint, setAudienceHint] = useState('');
  const [latestId, setLatestId] = useState<string | null>(null);

  const rejectDisclosure = useDisclosure();
  const [rejectTargetId, setRejectTargetId] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejecting, setRejecting] = useState(false);

  const latest = useMemo<Proposal | null>(() => {
    if (latestId) {
      return proposals.find((p) => p.id === latestId) ?? null;
    }
    return proposals[0] ?? null;
  }, [latestId, proposals]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<ListResponse>('/v2/admin/caring-community/copilot/proposals');
      setProposals(res.data?.items ?? []);
    } catch {
      showToast('Failed to load copilot proposals', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const handleGenerate = useCallback(async () => {
    const trimmed = draft.trim();
    if (trimmed === '') {
      showToast('Enter a draft first', 'error');
      return;
    }
    if (trimmed.length > 4000) {
      showToast('Draft is too long (max 4000 characters)', 'error');
      return;
    }
    setGenerating(true);
    try {
      const res = await api.post<ProposalResponse>(
        '/v2/admin/caring-community/copilot/proposals',
        {
          draft: trimmed,
          audience_hint: audienceHint.trim() || undefined,
        },
      );
      const newProposal = res.data?.proposal ?? null;
      if (newProposal) {
        setLatestId(newProposal.id);
        setProposals((prev) => [newProposal, ...prev.filter((p) => p.id !== newProposal.id)]);
        showToast('Proposal generated', 'success');
      }
    } catch {
      showToast('Failed to generate proposal', 'error');
    } finally {
      setGenerating(false);
    }
  }, [draft, audienceHint, showToast]);

  const handleAccept = useCallback(
    async (proposal: Proposal, polishedOverride?: string) => {
      try {
        const body: Record<string, unknown> = {};
        if (polishedOverride !== undefined && polishedOverride !== proposal.polished_text) {
          body.edited_polished_text = polishedOverride;
        }
        const res = await api.post<ProposalResponse>(
          `/v2/admin/caring-community/copilot/proposals/${proposal.id}/accept`,
          body,
        );
        const updated = res.data?.proposal ?? null;
        if (updated) {
          setProposals((prev) => prev.map((p) => (p.id === updated.id ? updated : p)));
          showToast('Proposal accepted — now publish via Announcements admin', 'success');
        }
      } catch {
        showToast('Failed to accept proposal', 'error');
      }
    },
    [showToast],
  );

  const openReject = useCallback(
    (proposalId: string) => {
      setRejectTargetId(proposalId);
      setRejectReason('');
      rejectDisclosure.onOpen();
    },
    [rejectDisclosure],
  );

  const handleReject = useCallback(async () => {
    if (!rejectTargetId) return;
    const reason = rejectReason.trim();
    if (reason === '') {
      showToast('Reason is required', 'error');
      return;
    }
    setRejecting(true);
    try {
      const res = await api.post<ProposalResponse>(
        `/v2/admin/caring-community/copilot/proposals/${rejectTargetId}/reject`,
        { reason },
      );
      const updated = res.data?.proposal ?? null;
      if (updated) {
        setProposals((prev) => prev.map((p) => (p.id === updated.id ? updated : p)));
        showToast('Proposal rejected', 'success');
      }
      rejectDisclosure.onClose();
      setRejectTargetId(null);
      setRejectReason('');
    } catch {
      showToast('Failed to reject proposal', 'error');
    } finally {
      setRejecting(false);
    }
  }, [rejectTargetId, rejectReason, rejectDisclosure, showToast]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Municipal Communication Copilot"
        subtitle="Draft official announcements, have them polished by AI, check tone and clarity, then publish. Designed for coordinators who need to communicate with residents and municipality contacts in a consistent, accessible voice."
        icon={<Wand2 size={20} />}
        actions={
          <Button
            size="sm"
            variant="flat"
            startContent={<RefreshCw size={14} />}
            onPress={load}
            isLoading={loading}
          >
            Refresh
          </Button>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                The Municipal Copilot helps you write announcements that are clear, appropriately formal, and free of jargon. Paste a rough draft — the AI will suggest improvements, flag tone issues (too formal, condescending, or unclear), and list any moderation concerns. You review and accept or reject the suggestion before anything is published.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Two-column workspace */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Left: Draft input */}
        <Card>
          <CardHeader className="pb-2">
            <span className="font-semibold text-sm">New draft</span>
          </CardHeader>
          <CardBody className="pt-0 space-y-3">
            <Textarea
              label="Draft announcement"
              placeholder="Paste a rough draft — the copilot will polish, check tone, and flag issues."
              minRows={8}
              maxRows={16}
              value={draft}
              onValueChange={setDraft}
              description={`${draft.length} / 4000 characters`}
            />
            <Input
              label="Audience hint (optional)"
              placeholder="e.g. caregivers in north sub-region"
              value={audienceHint}
              onValueChange={setAudienceHint}
              description="Specify who this announcement is for (e.g. 'all residents', 'volunteers', 'municipality contacts') so the AI can calibrate the tone and vocabulary appropriately."
            />
            <div className="flex justify-end">
              <Button
                color="primary"
                startContent={<Sparkles size={15} />}
                onPress={handleGenerate}
                isLoading={generating}
                isDisabled={draft.trim() === ''}
              >
                Generate proposal
              </Button>
            </div>
          </CardBody>
        </Card>

        {/* Right: Latest proposal preview */}
        <Card>
          <CardHeader className="pb-2 flex items-center justify-between">
            <span className="font-semibold text-sm">Latest proposal</span>
            {latest && (
              <Chip size="sm" color={STATUS_COLORS[latest.status]} variant="flat">
                {latest.status}
              </Chip>
            )}
          </CardHeader>
          <CardBody className="pt-0 space-y-3">
            {!latest && (
              <p className="text-sm text-default-500">
                No proposal yet. Generate one from the draft on the left.
              </p>
            )}
            {latest && (
              <>
                <Textarea
                  label="Polished text"
                  value={latest.polished_text}
                  minRows={6}
                  maxRows={14}
                  isReadOnly
                />

                <div className="flex flex-wrap gap-2 items-center">
                  <Chip
                    size="sm"
                    color={TONE_COLORS[latest.tone_assessment] ?? 'default'}
                    variant="flat"
                  >
                    {TONE_LABELS[latest.tone_assessment] ?? latest.tone_assessment}
                  </Chip>
                  <Chip size="sm" variant="flat" color="primary">
                    Audience: {latest.audience_suggestion}
                  </Chip>
                  <Chip size="sm" variant="flat" color="default">
                    Model: {latest.model_used}
                  </Chip>
                </div>
                <p className="text-xs text-default-400">
                  Tone legend — <span className="font-medium">too_formal</span>: overly bureaucratic; <span className="font-medium">too_informal</span>: may seem unprofessional; <span className="font-medium">condescending</span>: may patronise the reader; <span className="font-medium">ok</span>: no tone issues detected.
                </p>

                {latest.clarity_warnings.length > 0 && (
                  <div className="space-y-1">
                    <p className="text-xs font-semibold text-default-600">Clarity warnings</p>
                    <div className="flex flex-wrap gap-1.5">
                      {latest.clarity_warnings.map((w, i) => (
                        <Chip
                          key={`cw-${i}`}
                          size="sm"
                          color="warning"
                          variant="flat"
                          startContent={<AlertTriangle size={12} />}
                        >
                          {w}
                        </Chip>
                      ))}
                    </div>
                  </div>
                )}

                {latest.moderation_flags.length > 0 && (
                  <div className="space-y-1">
                    <p className="text-xs font-semibold text-default-600">Moderation flags</p>
                    <div className="flex flex-wrap gap-1.5">
                      {latest.moderation_flags.map((f, i) => (
                        <Chip
                          key={`mf-${i}`}
                          size="sm"
                          color="danger"
                          variant="flat"
                          startContent={<AlertTriangle size={12} />}
                        >
                          {f}
                        </Chip>
                      ))}
                    </div>
                  </div>
                )}

                {latest.status === 'accepted' && (
                  <div className="rounded-md border border-warning-200 bg-warning-50 px-3 py-2 text-xs text-warning-700">
                    Accepted. Now publish the polished text via the Announcements admin.
                  </div>
                )}

                {latest.status === 'rejected' && latest.rejection_reason && (
                  <div className="rounded-md border border-default-200 bg-default-50 px-3 py-2 text-xs text-default-600">
                    Rejected — reason: {latest.rejection_reason}
                  </div>
                )}

                {latest.status === 'proposed' && (
                  <>
                    <Divider />
                    <div className="flex justify-end gap-2">
                      <Button
                        size="sm"
                        variant="flat"
                        color="default"
                        startContent={<XCircle size={14} />}
                        onPress={() => openReject(latest.id)}
                      >
                        Reject
                      </Button>
                      <Button
                        size="sm"
                        color="primary"
                        startContent={<CheckCircle2 size={14} />}
                        onPress={() => handleAccept(latest)}
                      >
                        Accept
                      </Button>
                    </div>
                  </>
                )}
              </>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Recent proposals table */}
      <Card>
        <CardHeader className="pb-2">
          <span className="font-semibold text-sm">Recent proposals</span>
        </CardHeader>
        <CardBody className="pt-0">
          {loading ? (
            <div className="flex justify-center py-10">
              <Spinner />
            </div>
          ) : proposals.length === 0 ? (
            <p className="text-sm text-default-500 py-6 text-center">
              No proposals yet. Drafts you analyse will appear here as an audit trail.
            </p>
          ) : (
            <Table aria-label="Recent copilot proposals" removeWrapper>
              <TableHeader>
                <TableColumn>STATUS</TableColumn>
                <TableColumn>DRAFT</TableColumn>
                <TableColumn>AUDIENCE</TableColumn>
                <TableColumn>TONE</TableColumn>
                <TableColumn>CREATED</TableColumn>
                <TableColumn>ACTIONS</TableColumn>
              </TableHeader>
              <TableBody>
                {proposals.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell>
                      <Chip size="sm" color={STATUS_COLORS[p.status]} variant="flat">
                        {p.status}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <span className="text-xs text-default-700">
                        {truncate(p.draft_text, 80)}
                      </span>
                    </TableCell>
                    <TableCell>
                      <span className="text-xs">{p.audience_suggestion}</span>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={TONE_COLORS[p.tone_assessment] ?? 'default'}
                      >
                        {TONE_LABELS[p.tone_assessment] ?? p.tone_assessment}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <span className="text-xs text-default-500">{formatTime(p.created_at)}</span>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Button
                          size="sm"
                          variant="light"
                          onPress={() => setLatestId(p.id)}
                        >
                          View
                        </Button>
                        {p.status === 'proposed' && (
                          <>
                            <Button
                              size="sm"
                              variant="flat"
                              color="primary"
                              onPress={() => handleAccept(p)}
                            >
                              Accept
                            </Button>
                            <Button
                              size="sm"
                              variant="flat"
                              color="default"
                              onPress={() => openReject(p.id)}
                            >
                              Reject
                            </Button>
                          </>
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

      {/* Reject modal */}
      <Modal isOpen={rejectDisclosure.isOpen} onOpenChange={rejectDisclosure.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>Reject proposal</ModalHeader>
              <ModalBody>
                <Textarea
                  label="Reason"
                  placeholder="Why is this proposal being rejected?"
                  value={rejectReason}
                  onValueChange={setRejectReason}
                  minRows={3}
                  maxRows={8}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose} isDisabled={rejecting}>
                  Cancel
                </Button>
                <Button
                  color="danger"
                  onPress={handleReject}
                  isLoading={rejecting}
                  isDisabled={rejectReason.trim() === ''}
                >
                  Reject proposal
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
