// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG71 — Pilot Region Inquiry Admin Page
 *
 * Platform-level pipeline board for managing incoming Gemeinde
 * pilot inquiries.  English-only (admin panel policy).
 *
 * GET  /v2/admin/pilot-inquiries
 * GET  /v2/admin/pilot-inquiries/stats
 * GET  /v2/admin/pilot-inquiries/{id}
 * POST /v2/admin/pilot-inquiries/{id}/stage
 * POST /v2/admin/pilot-inquiries/{id}/assign
 * POST /v2/admin/pilot-inquiries/{id}/notes
 * GET  /v2/admin/pilot-inquiries/export
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Button,
  Chip,
  Spinner,
  Select,
  SelectItem,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  Card,
  CardBody,
} from '@heroui/react';
import Info from 'lucide-react/icons/info';
import MapPin from 'lucide-react/icons/map-pin';
import Users from 'lucide-react/icons/users';
import TrendingUp from 'lucide-react/icons/trending-up';
import CheckCircle from 'lucide-react/icons/check-circle';
import Clock from 'lucide-react/icons/clock';
import Star from 'lucide-react/icons/star';
import FileText from 'lucide-react/icons/file-text';
import Download from 'lucide-react/icons/download';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { StatCard, PageHeader } from '../../components';

// ─── Types ────────────────────────────────────────────────────────────────────

interface PilotInquiry {
  id: number;
  municipality_name: string;
  region: string | null;
  country: string;
  population: number | null;
  contact_name: string;
  contact_email: string;
  contact_phone: string | null;
  contact_role: string | null;
  has_kiss_cooperative: number;
  has_existing_digital_tool: number;
  existing_tool_name: string | null;
  timeline_months: number | null;
  interest_modules: string | null;  // JSON string
  budget_indication: string | null;
  notes: string | null;
  fit_score: number | null;
  fit_breakdown: string | null;     // JSON string
  stage: string;
  assigned_to: number | null;
  assigned_user_name: string | null;
  assigned_user_email: string | null;
  proposal_sent_at: string | null;
  pilot_agreed_at: string | null;
  went_live_at: string | null;
  rejection_reason: string | null;
  internal_notes: string | null;
  source: string | null;
  created_at: string;
}

interface PipelineStats {
  total: number;
  avg_fit_score: number;
  by_stage: Record<string, { count: number; avg_fit_score: number }>;
  by_country: Array<{ country: string; count: number }>;
  avg_days_to_proposal: number | null;
  avg_days_to_agreed: number | null;
  avg_days_to_live: number | null;
}

const STAGES = [
  { key: 'new',           label: 'New',            color: 'default' },
  { key: 'qualified',     label: 'Qualified',       color: 'primary' },
  { key: 'proposal_sent', label: 'Proposal Sent',   color: 'warning' },
  { key: 'pilot_agreed',  label: 'Pilot Agreed',    color: 'success' },
  { key: 'live',          label: 'Live',            color: 'success' },
  { key: 'rejected',      label: 'Rejected',        color: 'danger' },
  { key: 'dormant',       label: 'Dormant',         color: 'default' },
] as const;

type StageKey = typeof STAGES[number]['key'];

function stageConfig(stage: string) {
  return STAGES.find(s => s.key === stage) ?? { key: stage, label: stage, color: 'default' as const };
}

// ─── Fit score chip ───────────────────────────────────────────────────────────

function FitChip({ score }: { score: number | null }) {
  if (score === null) return <span className="text-xs text-gray-400">—</span>;
  const color = score >= 60 ? 'success' : score >= 40 ? 'warning' : 'default';
  return (
    <Chip size="sm" color={color} variant="flat">
      {score.toFixed(1)}
    </Chip>
  );
}

// ─── Inquiry card (pipeline view) ─────────────────────────────────────────────

function InquiryCard({
  inquiry,
  onClick,
}: {
  inquiry: PilotInquiry;
  onClick: () => void;
}) {
  const sc = stageConfig(inquiry.stage);
  return (
    <Card
      isPressable
      onPress={onClick}
      className="cursor-pointer hover:shadow-md transition-shadow"
    >
      <CardBody className="p-4 space-y-2">
        <div className="flex items-start justify-between gap-2">
          <div>
            <p className="font-semibold text-sm leading-tight">{inquiry.municipality_name}</p>
            <p className="text-xs text-gray-500">{inquiry.country}{inquiry.region ? ` · ${inquiry.region}` : ''}</p>
          </div>
          <FitChip score={inquiry.fit_score} />
        </div>
        <p className="text-xs text-gray-500">{inquiry.contact_name}</p>
        <div className="flex items-center justify-between">
          <Chip size="sm" color={sc.color as never} variant="flat">{sc.label}</Chip>
          {inquiry.assigned_user_name?.trim() && (
            <span className="text-xs text-gray-400 truncate max-w-[100px]">{inquiry.assigned_user_name.trim()}</span>
          )}
        </div>
      </CardBody>
    </Card>
  );
}

// ─── Detail modal ─────────────────────────────────────────────────────────────

function InquiryDetailModal({
  inquiry,
  isOpen,
  onClose,
  onRefresh,
}: {
  inquiry: PilotInquiry | null;
  isOpen: boolean;
  onClose: () => void;
  onRefresh: () => void;
}) {
  const toast = useToast();
  const [newStage, setNewStage]         = useState('');
  const [rejectionReason, setRejReason] = useState('');
  const [internalNotes, setNotes]       = useState('');
  const [saving, setSaving]             = useState(false);

  useEffect(() => {
    if (inquiry) {
      setNewStage(inquiry.stage);
      setNotes(inquiry.internal_notes ?? '');
      setRejReason(inquiry.rejection_reason ?? '');
    }
  }, [inquiry]);

  if (!inquiry) return null;

  async function saveStage() {
    setSaving(true);
    try {
      await api.post(`/v2/admin/pilot-inquiries/${inquiry!.id}/stage`, {
        stage: newStage,
        rejection_reason: newStage === 'rejected' ? rejectionReason : undefined,
      });
      toast.success('Stage updated');
      onRefresh();
      onClose();
    } catch (err) {
      logError('stage update failed', err);
      toast.error('Failed to update stage');
    } finally {
      setSaving(false);
    }
  }

  async function saveNotes() {
    setSaving(true);
    try {
      await api.post(`/v2/admin/pilot-inquiries/${inquiry!.id}/notes`, { internal_notes: internalNotes });
      toast.success('Notes saved');
      onRefresh();
    } catch (err) {
      logError('notes save failed', err);
      toast.error('Failed to save notes');
    } finally {
      setSaving(false);
    }
  }

  // Parse JSON fields safely
  let modules: string[] = [];
  try { modules = JSON.parse(inquiry.interest_modules ?? '[]'); } catch { /* ignore */ }

  let breakdown: Record<string, number> = {};
  try { breakdown = JSON.parse(inquiry.fit_breakdown ?? '{}'); } catch { /* ignore */ }

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="3xl" scrollBehavior="inside">
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <MapPin className="w-5 h-5 text-indigo-500" />
          {inquiry.municipality_name}
          <FitChip score={inquiry.fit_score} />
        </ModalHeader>
        <ModalBody className="space-y-4 text-sm">
          {/* Municipality info */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Country / Region</p>
              <p>{inquiry.country}{inquiry.region ? ` · ${inquiry.region}` : ''}</p>
            </div>
            <div>
              <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Population</p>
              <p>{inquiry.population?.toLocaleString() ?? '—'}</p>
            </div>
            <div>
              <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">KISS Cooperative</p>
              <p>{inquiry.has_kiss_cooperative ? 'Yes' : 'No'}</p>
            </div>
            <div>
              <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Existing Digital Tool</p>
              <p>{inquiry.has_existing_digital_tool ? (inquiry.existing_tool_name ?? 'Yes') : 'No'}</p>
            </div>
          </div>

          {/* Contact */}
          <div>
            <p className="text-gray-400 text-xs uppercase tracking-wide mb-1">Contact</p>
            <p className="font-medium">{inquiry.contact_name}</p>
            <p className="text-indigo-400">{inquiry.contact_email}</p>
            {inquiry.contact_phone && <p>{inquiry.contact_phone}</p>}
            {inquiry.contact_role && <p className="text-gray-500">{inquiry.contact_role}</p>}
          </div>

          {/* Modules + timeline + budget */}
          {modules.length > 0 && (
            <div>
              <p className="text-gray-400 text-xs uppercase tracking-wide mb-1">Modules of Interest</p>
              <div className="flex flex-wrap gap-1">
                {modules.map((m: string) => (
                  <Chip key={m} size="sm" variant="flat" color="primary">{m.replace(/_/g, ' ')}</Chip>
                ))}
              </div>
            </div>
          )}

          <div className="grid grid-cols-2 gap-3">
            <div>
              <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Timeline</p>
              <p>{inquiry.timeline_months === 0 ? 'ASAP' : inquiry.timeline_months ? `${inquiry.timeline_months} months` : '—'}</p>
            </div>
            <div>
              <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Budget</p>
              <p>{inquiry.budget_indication?.replace(/_/g, ' ') ?? '—'}</p>
            </div>
          </div>

          {/* Notes */}
          {inquiry.notes && (
            <div>
              <p className="text-gray-400 text-xs uppercase tracking-wide mb-0.5">Notes from Gemeinde</p>
              <p className="text-gray-300 italic">{inquiry.notes}</p>
            </div>
          )}

          {/* Fit score breakdown */}
          {Object.keys(breakdown).length > 0 && (
            <div>
              <p className="text-gray-400 text-xs uppercase tracking-wide mb-1">Fit Score Breakdown</p>
              <div className="grid grid-cols-2 gap-1">
                {Object.entries(breakdown).map(([key, val]) => (
                  <div key={key} className="flex items-center justify-between text-xs bg-gray-800/40 rounded px-2 py-1">
                    <span className="capitalize">{key.replace(/_/g, ' ')}</span>
                    <span className="font-semibold text-indigo-400">+{val}</span>
                  </div>
                ))}
                <div className="flex items-center justify-between text-xs bg-indigo-500/20 rounded px-2 py-1 font-semibold col-span-2">
                  <span>Total</span>
                  <span>{inquiry.fit_score}</span>
                </div>
              </div>
            </div>
          )}

          {/* Stage update */}
          <div className="border-t border-white/10 pt-4">
            <p className="text-gray-400 text-xs uppercase tracking-wide mb-2">Update Stage</p>
            <Select
              size="sm"
              selectedKeys={newStage ? [newStage] : []}
              onSelectionChange={keys => setNewStage(Array.from(keys)[0] as string ?? '')}
              classNames={{ trigger: 'bg-gray-800/50' }}
            >
              {STAGES.map(s => (
                <SelectItem key={s.key}>{s.label}</SelectItem>
              ))}
            </Select>
            {newStage === 'rejected' && (
              <Textarea
                className="mt-2"
                size="sm"
                placeholder="Rejection reason…"
                value={rejectionReason}
                onValueChange={setRejReason}
              />
            )}
            <Button
              size="sm"
              color="primary"
              className="mt-2"
              isLoading={saving}
              onPress={saveStage}
            >
              Save Stage
            </Button>
          </div>

          {/* Internal notes */}
          <div className="border-t border-white/10 pt-4">
            <p className="text-gray-400 text-xs uppercase tracking-wide mb-2">Internal Notes (admin only)</p>
            <Textarea
              size="sm"
              minRows={2}
              value={internalNotes}
              onValueChange={setNotes}
              placeholder="Add internal notes…"
            />
            <Button
              size="sm"
              variant="flat"
              className="mt-2"
              isLoading={saving}
              onPress={saveNotes}
            >
              Save Notes
            </Button>
          </div>

          {/* Timestamps */}
          <div className="border-t border-white/10 pt-4 grid grid-cols-2 gap-2 text-xs text-gray-400">
            <div><span className="font-medium text-gray-300">Submitted:</span> {new Date(inquiry.created_at).toLocaleDateString()}</div>
            {inquiry.proposal_sent_at && <div><span className="font-medium text-gray-300">Proposal:</span> {new Date(inquiry.proposal_sent_at).toLocaleDateString()}</div>}
            {inquiry.pilot_agreed_at  && <div><span className="font-medium text-gray-300">Agreed:</span>   {new Date(inquiry.pilot_agreed_at).toLocaleDateString()}</div>}
            {inquiry.went_live_at     && <div><span className="font-medium text-gray-300">Live:</span>      {new Date(inquiry.went_live_at).toLocaleDateString()}</div>}
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="light" onPress={onClose}>Close</Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export function PilotInquiryAdminPage() {
  usePageTitle("Pilot Inquiries");
  const toast = useToast();

  const [inquiries, setInquiries]   = useState<PilotInquiry[]>([]);
  const [stats, setStats]           = useState<PipelineStats | null>(null);
  const [loading, setLoading]       = useState(true);
  const [stageFilter, setStageFilter] = useState<string>('');
  const [selected, setSelected]     = useState<PilotInquiry | null>(null);
  const { isOpen, onOpen, onClose } = useDisclosure();

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [listRes, statsRes] = await Promise.all([
        api.get('/v2/admin/pilot-inquiries' + (stageFilter ? `?stage=${stageFilter}` : '')),
        api.get('/v2/admin/pilot-inquiries/stats'),
      ]);
      const listData  = 'data' in listRes  ? listRes.data  : listRes;
      const statsData = 'data' in statsRes ? statsRes.data : statsRes;
      setInquiries(Array.isArray(listData) ? listData : []);
      if (statsData && typeof statsData === 'object') setStats(statsData as PipelineStats);
    } catch (err) {
      logError('PilotInquiryAdminPage loadData', err);
      toast.error('Failed to load pilot inquiries');
    } finally {
      setLoading(false);
    }
  }, [stageFilter, toast]);

  useEffect(() => { loadData(); }, [loadData]);

  function openDetail(inquiry: PilotInquiry) {
    setSelected(inquiry);
    onOpen();
  }

  function handleExport() {
    window.open('/api/v2/admin/pilot-inquiries/export', '_blank');
  }

  // In-pipeline = qualified + proposal_sent + pilot_agreed
  const inPipeline = stats
    ? (['qualified', 'proposal_sent', 'pilot_agreed'] as StageKey[])
        .reduce((acc, s) => acc + (stats.by_stage[s]?.count ?? 0), 0)
    : 0;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Pilot Inquiries"
        subtitle="Manage incoming Gemeinde pilot region expressions of interest"
        icon={<MapPin className="w-6 h-6" />}
        actions={
          <div className="flex gap-2">
            <Button
              size="sm"
              variant="flat"
              startContent={<RefreshCw className="w-4 h-4" />}
              onPress={loadData}
              isLoading={loading}
            >
              Refresh
            </Button>
            <Button
              size="sm"
              color="primary"
              variant="flat"
              startContent={<Download className="w-4 h-4" />}
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
                This is the top-of-funnel pipeline for municipalities (<em>Gemeinden</em>) and regions interested
                in running a NEXUS/KISS pilot. Each inquiry is submitted via the public interest form and scored
                automatically using a fit algorithm (population size, existing KISS cooperative, digital readiness,
                budget indication, and timeline). Use this board to move inquiries through the pipeline, assign
                them to a sales or community contact, and track when proposals are sent and pilots go live.
              </p>
              <p className="text-default-500">
                <strong>Pipeline stages:</strong> New → Contacted → Qualified → Proposal sent → Pilot agreed → Live → Rejected / Withdrawn.
                The <strong>Fit score</strong> (0–100) is calculated automatically — 70+ is a strong match. Click any
                card to view full details, update the stage, or add internal notes.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Fit score scale */}
      <div className="flex flex-wrap items-center gap-x-5 gap-y-1.5 rounded-lg border border-default-200 bg-default-50 px-3 py-2 text-xs text-default-500">
        <span className="font-medium text-default-700">Fit score scale (0–100):</span>
        <span className="flex items-center gap-1.5"><Chip size="sm" color="success" variant="flat">60–100</Chip>Good match — prioritise outreach</span>
        <span className="flex items-center gap-1.5"><Chip size="sm" color="warning" variant="flat">40–59</Chip>Potential — investigate further</span>
        <span className="flex items-center gap-1.5"><Chip size="sm" color="default" variant="flat">0–39</Chip>Weak fit — low priority</span>
        <span className="ml-3 text-default-400">Score factors: population size, existing KISS cooperative, digital readiness, budget, timeline.</span>
      </div>

      {/* Stats row */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <StatCard
          title="Total Inquiries"
          value={stats?.total ?? 0}
          icon={<FileText className="w-5 h-5 text-indigo-400" />}
        />
        <StatCard
          title="Qualified"
          value={stats?.by_stage['qualified']?.count ?? 0}
          icon={<Star className="w-5 h-5 text-amber-400" />}
        />
        <StatCard
          title="In Pipeline"
          value={inPipeline}
          icon={<TrendingUp className="w-5 h-5 text-emerald-400" />}
        />
        <StatCard
          title="Avg Fit Score"
          value={stats ? stats.avg_fit_score.toFixed(1) : '—'}
          icon={<CheckCircle className="w-5 h-5 text-purple-400" />}
        />
      </div>

      {/* Stage timing */}
      {stats && (
        <div className="flex flex-col gap-1">
          <p className="text-xs font-medium text-default-600">Pipeline velocity — average days from inquiry submission to each milestone:</p>
          <div className="flex gap-4 text-xs text-gray-400 flex-wrap">
          {stats.avg_days_to_proposal !== null && (
            <span className="flex items-center gap-1">
              <Clock className="w-3 h-3" /> Avg to proposal: <strong className="text-white">{stats.avg_days_to_proposal}d</strong>
            </span>
          )}
          {stats.avg_days_to_agreed !== null && (
            <span className="flex items-center gap-1">
              <Clock className="w-3 h-3" /> Avg to agreed: <strong className="text-white">{stats.avg_days_to_agreed}d</strong>
            </span>
          )}
          {stats.avg_days_to_live !== null && (
            <span className="flex items-center gap-1">
              <Clock className="w-3 h-3" /> Avg to live: <strong className="text-white">{stats.avg_days_to_live}d</strong>
            </span>
          )}
          </div>
        </div>
      )}

      {/* Stage filter */}
      <div className="flex items-center gap-3 flex-wrap">
        <span className="text-sm text-gray-400">Filter by stage:</span>
        <Button
          size="sm"
          variant={stageFilter === '' ? 'solid' : 'flat'}
          color={stageFilter === '' ? 'primary' : 'default'}
          onPress={() => setStageFilter('')}
        >
          All
        </Button>
        {STAGES.map(s => (
          <Button
            key={s.key}
            size="sm"
            variant={stageFilter === s.key ? 'solid' : 'flat'}
            color={stageFilter === s.key ? (s.color as never) : 'default'}
            onPress={() => setStageFilter(stageFilter === s.key ? '' : s.key)}
          >
            {s.label}
            {stats?.by_stage[s.key] && (
              <span className="ml-1 opacity-60">({stats.by_stage[s.key]?.count ?? 0})</span>
            )}
          </Button>
        ))}
      </div>

      {/* Inquiry grid */}
      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : inquiries.length === 0 ? (
        <div className="text-center py-16 text-gray-400">
          <MapPin className="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p className="font-medium text-default-600">No pilot inquiries yet.</p>
          <p className="text-sm mt-1 text-default-400">
            Inquiries arrive via the public interest form on the NEXUS sales site. Once submitted, they appear
            here automatically. Use the stage filter above to check if inquiries exist in other stages.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {inquiries.map(inq => (
            <InquiryCard
              key={inq.id}
              inquiry={inq}
              onClick={() => openDetail(inq)}
            />
          ))}
        </div>
      )}

      {/* By country summary */}
      {stats && stats.by_country.length > 0 && (
        <div className="mt-4">
          <p className="text-xs text-gray-400 uppercase tracking-wide mb-2 flex items-center gap-1">
            <Users className="w-3 h-3" /> By country
          </p>
          <div className="flex gap-2 flex-wrap">
            {stats.by_country.map(c => (
              <Chip key={c.country} size="sm" variant="flat">
                {c.country}: {c.count}
              </Chip>
            ))}
          </div>
        </div>
      )}

      {/* Detail modal */}
      <InquiryDetailModal
        inquiry={selected}
        isOpen={isOpen}
        onClose={onClose}
        onRefresh={loadData}
      />
    </div>
  );
}

export default PilotInquiryAdminPage;
