// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG61 — KI-Agenten Autonomous Agent Framework — Admin Page
 *
 * ADMIN IS ENGLISH-ONLY — NO t() calls.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Chip,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Slider,
  Switch,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Tabs,
} from '@heroui/react';
import Bot from 'lucide-react/icons/bot';
import Brain from 'lucide-react/icons/brain';
import Info from 'lucide-react/icons/info';
import Zap from 'lucide-react/icons/zap';
import CheckCircle from 'lucide-react/icons/check-circle';
import XCircle from 'lucide-react/icons/x-circle';
import Clock from 'lucide-react/icons/clock';
import Activity from 'lucide-react/icons/activity';
import Play from 'lucide-react/icons/play';
import BarChart3 from 'lucide-react/icons/bar-chart-3';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface AgentConfig {
  enabled: boolean;
  auto_apply_threshold: number;
  tandem_matching_enabled: boolean;
  nudge_dispatch_enabled: boolean;
  activity_summary_enabled: boolean;
  demand_forecast_enabled: boolean;
  help_routing_enabled: boolean;
  schedule_hour: number;
  max_proposals_per_run: number;
  notification_email: string | null;
}

interface AgentRun {
  id: number;
  tenant_id: number;
  agent_type: string;
  status: 'pending' | 'running' | 'completed' | 'failed' | 'cancelled';
  triggered_by: string;
  proposals_generated: number;
  proposals_applied: number;
  output_summary: string | null;
  error_message: string | null;
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
  proposals?: AgentProposal[];
}

interface AgentProposal {
  id: number;
  run_id: number;
  proposal_type: string;
  subject_user_id: number | null;
  target_user_id: number | null;
  proposal_data: Record<string, unknown>;
  status: 'pending_review' | 'approved' | 'auto_applied' | 'rejected' | 'expired';
  confidence_score: number | null;
  reviewer_id: number | null;
  reviewed_at: string | null;
  applied_at: string | null;
  expires_at: string | null;
  created_at: string;
  run_agent_type?: string;
}

interface AgentStats {
  total_runs: number;
  total_proposals: number;
  proposals_by_status: Record<string, number>;
  runs_last_30_days: Array<{ day: string; agent_type: string; count: number }>;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const AGENT_TYPES = [
  'tandem_matching',
  'demand_forecast',
  'nudge_dispatch',
  'activity_summary',
  'help_routing',
  'member_welcome',
] as const;

type AgentType = (typeof AGENT_TYPES)[number];

function statusColor(status: string): 'success' | 'danger' | 'warning' | 'default' | 'primary' {
  switch (status) {
    case 'completed':
    case 'approved':
    case 'auto_applied':
      return 'success';
    case 'failed':
    case 'rejected':
    case 'expired':
      return 'danger';
    case 'running':
    case 'pending_review':
      return 'warning';
    case 'pending':
      return 'default';
    default:
      return 'primary';
  }
}

function confidenceColor(score: number | null): 'success' | 'warning' | 'danger' {
  if (score === null) return 'warning';
  if (score >= 0.8) return 'success';
  if (score >= 0.5) return 'warning';
  return 'danger';
}

function fmtDate(s: string | null): string {
  if (!s) return '—';
  return new Date(s).toLocaleString();
}

function durationMs(run: AgentRun): string {
  if (!run.started_at || !run.completed_at) return '—';
  const ms = new Date(run.completed_at).getTime() - new Date(run.started_at).getTime();
  if (ms < 1000) return `${ms}ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
  return `${Math.round(ms / 60000)}m`;
}

function agentTypeLabel(t: string): string {
  return t.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function KiAgentAdminPage() {
  usePageTitle('KI-Agenten');
  const toast = useToast();

  const [config, setConfig] = useState<AgentConfig | null>(null);
  const [configDirty, setConfigDirty] = useState<Partial<AgentConfig>>({});
  const [savingConfig, setSavingConfig] = useState(false);

  const [runs, setRuns] = useState<AgentRun[]>([]);
  const [proposals, setProposals] = useState<AgentProposal[]>([]);
  const [stats, setStats] = useState<AgentStats | null>(null);
  const [loadingRuns, setLoadingRuns] = useState(false);
  const [loadingProposals, setLoadingProposals] = useState(false);

  const [selectedRun, setSelectedRun] = useState<AgentRun | null>(null);
  const [runModalOpen, setRunModalOpen] = useState(false);

  const [triggerType, setTriggerType] = useState<AgentType>('tandem_matching');
  const [triggering, setTriggering] = useState(false);

  const [proposalFilter, setProposalFilter] = useState('pending_review');
  const [approvingAll, setApprovingAll] = useState(false);

  // -------------------------------------------------------------------------
  // Fetch
  // -------------------------------------------------------------------------

  const fetchConfig = useCallback(async () => {
    try {
      const res = await api.get<AgentConfig>('/v2/admin/ki-agents/config');
      setConfig(res.data ?? null);
    } catch {
      toast.error('Failed to load agent config');
    }
  }, [toast]);

  const fetchRuns = useCallback(async () => {
    setLoadingRuns(true);
    try {
      const res = await api.get<AgentRun[]>('/v2/admin/ki-agents/runs?limit=50');
      setRuns(res.data ?? []);
    } catch {
      toast.error('Failed to load runs');
    } finally {
      setLoadingRuns(false);
    }
  }, [toast]);

  const fetchProposals = useCallback(async () => {
    setLoadingProposals(true);
    try {
      const url = proposalFilter
        ? `/v2/admin/ki-agents/proposals?status=${proposalFilter}&limit=100`
        : '/v2/admin/ki-agents/proposals?limit=100';
      const res = await api.get<AgentProposal[]>(url);
      setProposals(res.data ?? []);
    } catch {
      toast.error('Failed to load proposals');
    } finally {
      setLoadingProposals(false);
    }
  }, [proposalFilter, toast]);

  const fetchStats = useCallback(async () => {
    try {
      const res = await api.get<AgentStats>('/v2/admin/ki-agents/stats');
      setStats(res.data ?? null);
    } catch {
      // Non-critical — stats may not be available
    }
  }, []);

  useEffect(() => {
    void fetchConfig();
    void fetchRuns();
    void fetchProposals();
    void fetchStats();
  }, [fetchConfig, fetchRuns, fetchProposals, fetchStats]);

  useEffect(() => {
    void fetchProposals();
  }, [proposalFilter, fetchProposals]);

  // -------------------------------------------------------------------------
  // Config handlers
  // -------------------------------------------------------------------------

  const mergedConfig = config ? { ...config, ...configDirty } : null;

  function patchConfig<K extends keyof AgentConfig>(key: K, value: AgentConfig[K]) {
    setConfigDirty((prev) => ({ ...prev, [key]: value }));
  }

  async function handleSaveConfig() {
    if (!mergedConfig) return;
    setSavingConfig(true);
    try {
      const res = await api.put<AgentConfig>('/v2/admin/ki-agents/config', mergedConfig);
      setConfig(res.data ?? null);
      setConfigDirty({});
      toast.success('Agent config saved');
    } catch {
      toast.error('Failed to save config');
    } finally {
      setSavingConfig(false);
    }
  }

  // -------------------------------------------------------------------------
  // Run handlers
  // -------------------------------------------------------------------------

  async function handleTriggerRun() {
    setTriggering(true);
    try {
      const res = await api.post<AgentRun>('/v2/admin/ki-agents/trigger', { agent_type: triggerType });
      toast.success(`Run triggered: ${res.data?.proposals_generated ?? 0} proposals generated`);
      void fetchRuns();
      void fetchProposals();
      void fetchStats();
    } catch {
      toast.error('Failed to trigger run');
    } finally {
      setTriggering(false);
    }
  }

  async function handleOpenRunDetail(runId: number) {
    try {
      const res = await api.get<AgentRun>(`/v2/admin/ki-agents/runs/${runId}`);
      setSelectedRun(res.data ?? null);
      setRunModalOpen(true);
    } catch {
      toast.error('Failed to load run details');
    }
  }

  // -------------------------------------------------------------------------
  // Proposal handlers
  // -------------------------------------------------------------------------

  async function handleApprove(id: number) {
    try {
      await api.post(`/v2/admin/ki-agents/proposals/${id}/approve`, {});
      toast.success('Proposal approved and applied');
      void fetchProposals();
    } catch {
      toast.error('Failed to approve proposal');
    }
  }

  async function handleReject(id: number) {
    try {
      await api.post(`/v2/admin/ki-agents/proposals/${id}/reject`, {});
      toast.success('Proposal rejected');
      void fetchProposals();
    } catch {
      toast.error('Failed to reject proposal');
    }
  }

  async function handleApproveAllEligible() {
    setApprovingAll(true);
    try {
      const res = await api.post<{ approved: number; failed: number; threshold: number }>(
        '/v2/admin/ki-agents/proposals/approve-eligible',
        {},
      );
      toast.success(
        `Approved ${res.data?.approved ?? 0} proposals (${res.data?.failed ?? 0} failed, threshold ${((res.data?.threshold ?? 0) * 100).toFixed(0)}%)`,
      );
      void fetchProposals();
      void fetchStats();
    } catch {
      toast.error('Failed to approve eligible proposals');
    } finally {
      setApprovingAll(false);
    }
  }

  // -------------------------------------------------------------------------
  // Render
  // -------------------------------------------------------------------------

  const pendingCount = proposals.filter((p) => p.status === 'pending_review').length;

  return (
    <div className="space-y-6 p-6">
      {/* Page header */}
      <div className="flex items-center gap-3">
        <Brain size={28} className="text-primary" />
        <div>
          <h1 className="text-2xl font-bold">KI-Agenten</h1>
          <p className="text-sm text-default-500">
            AG61 — Autonomous Agent Framework. Agents propose actions; humans approve before
            anything is applied.
          </p>
        </div>
        {stats && (
          <div className="ml-auto flex gap-4 text-sm">
            <span className="text-default-500">
              <strong>{stats.total_runs}</strong> total runs
            </span>
            <span className="text-default-500">
              <strong>{stats.total_proposals}</strong> total proposals
            </span>
            {pendingCount > 0 && (
              <Chip color="warning" size="sm" startContent={<Clock size={12} />}>
                {pendingCount} pending review
              </Chip>
            )}
          </div>
        )}
      </div>

      <div className="rounded-xl border-l-4 border-l-primary bg-primary-50 px-4 py-3 dark:bg-primary-900/20">
        <div className="flex gap-3">
          <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
          <div className="space-y-1 text-sm">
            <p className="font-semibold text-primary-800 dark:text-primary-200">About KI-Agenten</p>
            <p className="text-default-600">
              KI-Agenten (AI Agents) automate routine coordination tasks — finding good care pairings, nudging
              inactive members, summarising coordinator workloads, and routing open help requests. Every agent
              works on a <strong>propose-then-approve</strong> model: agents generate proposals, but nothing is
              applied until a human approves it (unless the proposal's confidence score exceeds the auto-apply
              threshold you configure). Use the <strong>Config</strong> tab to enable agents and set thresholds,
              the <strong>Runs</strong> tab to see execution history, and the <strong>Proposals</strong> tab to
              review and approve or reject pending suggestions.
            </p>
            <p className="text-default-500">
              <strong>Tandem Matching</strong> — suggests KISS-style one-to-one care pairings based on skills,
              location, and availability.{' '}
              <strong>Nudge Dispatch</strong> — sends targeted prompts to members who have been inactive or
              have unmatched requests.{' '}
              <strong>Demand Forecast</strong> — predicts upcoming care demand from historical patterns.{' '}
              <strong>Help Routing</strong> — automatically suggests the best coordinator for open help requests.{' '}
              <strong>Activity Summary</strong> — emails coordinators a weekly digest of volunteer activity.
            </p>
          </div>
        </div>
      </div>

      <Tabs aria-label="KI-Agenten tabs" variant="underlined" color="primary">
        {/* ================================================================ */}
        {/* CONFIG TAB                                                        */}
        {/* ================================================================ */}
        <Tab
          key="config"
          title={
            <div className="flex items-center gap-2">
              <Zap size={16} />
              <span>Config</span>
            </div>
          }
        >
          {mergedConfig ? (
            <div className="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
              {/* Master switch */}
              <div className="col-span-full flex items-center justify-between rounded-xl border border-default-200 p-4">
                <div>
                  <p className="font-semibold">Enable KI-Agenten</p>
                  <p className="text-sm text-default-500">
                    Master on/off. When off, no agents will run on schedule.
                  </p>
                </div>
                <Switch
                  isSelected={mergedConfig.enabled}
                  onValueChange={(v) => patchConfig('enabled', v)}
                  color="success"
                />
              </div>

              {/* Agent toggles */}
              <div className="rounded-xl border border-default-200 p-4 space-y-4">
                <p className="font-semibold text-sm text-default-700">Agent Types</p>
                {(
                  [
                    ['tandem_matching_enabled', 'Tandem Matching', 'Suggest KISS-style support pairings'],
                    ['nudge_dispatch_enabled', 'Nudge Dispatch', 'Smart engagement nudges to members'],
                    ['activity_summary_enabled', 'Activity Summary', 'Weekly vol-log summary to coordinators'],
                    ['demand_forecast_enabled', 'Demand Forecast', 'Predict care demand from vol-log trends'],
                    ['help_routing_enabled', 'Help Routing', 'Auto-route open help requests'],
                  ] as [keyof AgentConfig, string, string][]
                ).map(([key, label, desc]) => (
                  <div key={key} className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium">{label}</p>
                      <p className="text-xs text-default-400">{desc}</p>
                    </div>
                    <Switch
                      size="sm"
                      isSelected={!!mergedConfig[key]}
                      onValueChange={(v) => patchConfig(key, v as AgentConfig[typeof key])}
                      isDisabled={!mergedConfig.enabled}
                    />
                  </div>
                ))}
              </div>

              {/* Thresholds */}
              <div className="rounded-xl border border-default-200 p-4 space-y-5">
                <p className="font-semibold text-sm text-default-700">Thresholds & Schedule</p>

                <div>
                  <p className="text-sm mb-2">
                    Auto-apply threshold:{' '}
                    <strong>{(mergedConfig.auto_apply_threshold * 100).toFixed(0)}%</strong>
                  </p>
                  <Slider
                    minValue={0}
                    maxValue={1}
                    step={0.01}
                    value={mergedConfig.auto_apply_threshold}
                    onChange={(v) =>
                      patchConfig('auto_apply_threshold', typeof v === 'number' ? v : (v[0] ?? 0))
                    }
                    aria-label="Auto-apply threshold"
                    color="success"
                  />
                  <p className="text-xs text-default-400 mt-1">
                    Proposals above this confidence are applied automatically without review.
                  </p>
                </div>

                <Input
                  label="Max proposals per run"
                  type="number"
                  value={String(mergedConfig.max_proposals_per_run)}
                  onValueChange={(v) => patchConfig('max_proposals_per_run', parseInt(v, 10) || 50)}
                  variant="bordered"
                  size="sm"
                  min={1}
                  max={500}
                />

                <Input
                  label="Schedule hour (0–23, server local time)"
                  type="number"
                  value={String(mergedConfig.schedule_hour)}
                  onValueChange={(v) => patchConfig('schedule_hour', parseInt(v, 10) || 2)}
                  variant="bordered"
                  size="sm"
                  min={0}
                  max={23}
                />

                <Input
                  label="Notification email"
                  type="email"
                  value={mergedConfig.notification_email ?? ''}
                  onValueChange={(v) => patchConfig('notification_email', v || null)}
                  placeholder="admin@example.org"
                  variant="bordered"
                  size="sm"
                />
              </div>

              {/* Save */}
              <div className="col-span-full flex justify-end">
                <Button
                  color="primary"
                  onPress={handleSaveConfig}
                  isLoading={savingConfig}
                  startContent={<CheckCircle size={16} />}
                >
                  Save Config
                </Button>
              </div>
            </div>
          ) : (
            <p className="mt-6 text-default-400">Loading config…</p>
          )}
        </Tab>

        {/* ================================================================ */}
        {/* PROPOSALS TAB                                                     */}
        {/* ================================================================ */}
        <Tab
          key="proposals"
          title={
            <div className="flex items-center gap-2">
              <Activity size={16} />
              <span>
                Proposals{pendingCount > 0 && ` (${pendingCount})`}
              </span>
            </div>
          }
        >
          <div className="mt-4 space-y-4">
            {/* Filter + Approve All */}
            <div className="flex items-center justify-between gap-3 flex-wrap">
              <div className="flex gap-2">
                {['pending_review', 'approved', 'auto_applied', 'rejected', 'expired', ''].map(
                  (s) => (
                    <Chip
                      key={s || 'all'}
                      color={proposalFilter === s ? 'primary' : 'default'}
                      variant={proposalFilter === s ? 'solid' : 'bordered'}
                      className="cursor-pointer"
                      onClick={() => setProposalFilter(s)}
                    >
                      {s || 'All'}
                    </Chip>
                  ),
                )}
              </div>
              <div className="flex flex-col items-end gap-1">
                <Button
                  color="success"
                  size="sm"
                  startContent={<Zap size={14} />}
                  onPress={handleApproveAllEligible}
                  isLoading={approvingAll}
                >
                  Approve All Eligible
                </Button>
                <p className="text-xs text-default-400">
                  Approves all <em>pending_review</em> proposals whose confidence meets the auto-apply threshold.
                </p>
              </div>
            </div>

            {/* Status + confidence legend */}
            <div className="flex flex-wrap items-center gap-x-5 gap-y-1.5 rounded-lg border border-default-200 bg-default-50 px-3 py-2 text-xs text-default-500">
              <span className="font-medium text-default-700">Status:</span>
              <span><Chip size="sm" color="warning" variant="flat" className="mr-1">pending review</Chip>awaiting your decision</span>
              <span><Chip size="sm" color="success" variant="flat" className="mr-1">approved</Chip>human-approved &amp; applied</span>
              <span><Chip size="sm" color="success" variant="flat" className="mr-1">auto applied</Chip>applied automatically (exceeded threshold)</span>
              <span><Chip size="sm" color="danger" variant="flat" className="mr-1">rejected</Chip>discarded, not applied</span>
              <span className="ml-2 font-medium text-default-700">Confidence:</span>
              <span className="text-success-600">■ ≥80% safe to auto-apply</span>
              <span className="text-warning-600">■ 50–79% review recommended</span>
              <span className="text-danger-600">■ &lt;50% low confidence</span>
            </div>

            <Table
              aria-label="Agent proposals"
              isStriped
              removeWrapper
            >
              <TableHeader>
                <TableColumn>Type</TableColumn>
                <TableColumn>Subject User</TableColumn>
                <TableColumn>Target User</TableColumn>
                <TableColumn>Confidence</TableColumn>
                <TableColumn>Proposal Data</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Created</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody emptyContent={proposalFilter === 'pending_review' ? 'No proposals awaiting review. Run an agent from the Runs tab to generate proposals.' : 'No proposals match this filter.'}>
                {proposals.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell>
                      <span className="font-mono text-xs">{p.proposal_type}</span>
                    </TableCell>
                    <TableCell>{p.subject_user_id ?? '—'}</TableCell>
                    <TableCell>{p.target_user_id ?? '—'}</TableCell>
                    <TableCell>
                      {p.confidence_score !== null ? (
                        <Chip size="sm" color={confidenceColor(p.confidence_score)}>
                          {(p.confidence_score * 100).toFixed(0)}%
                        </Chip>
                      ) : (
                        '—'
                      )}
                    </TableCell>
                    <TableCell>
                      <span className="text-xs text-default-400 line-clamp-2 max-w-[200px] block">
                        {JSON.stringify(p.proposal_data).slice(0, 120)}
                        {JSON.stringify(p.proposal_data).length > 120 ? '…' : ''}
                      </span>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={statusColor(p.status)}>
                        {p.status.replace(/_/g, ' ')}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <span className="text-xs">{fmtDate(p.created_at)}</span>
                    </TableCell>
                    <TableCell>
                      {p.status === 'pending_review' && (
                        <div className="flex gap-1">
                          <Button
                            size="sm"
                            color="success"
                            variant="flat"
                            isIconOnly
                            onPress={() => handleApprove(p.id)}
                            aria-label="Approve"
                          >
                            <CheckCircle size={14} />
                          </Button>
                          <Button
                            size="sm"
                            color="danger"
                            variant="flat"
                            isIconOnly
                            onPress={() => handleReject(p.id)}
                            aria-label="Reject"
                          >
                            <XCircle size={14} />
                          </Button>
                        </div>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </Tab>

        {/* ================================================================ */}
        {/* RUNS TAB                                                          */}
        {/* ================================================================ */}
        <Tab
          key="runs"
          title={
            <div className="flex items-center gap-2">
              <BarChart3 size={16} />
              <span>Runs</span>
            </div>
          }
        >
          <div className="mt-4 space-y-4">
            {/* Trigger run */}
            <div className="flex items-center gap-3 rounded-xl border border-default-200 p-4 flex-wrap">
              <Bot size={20} className="text-primary" />
              <p className="font-semibold text-sm">Trigger a run now</p>
              <select
                value={triggerType}
                onChange={(e) => setTriggerType(e.target.value as AgentType)}
                className="rounded-lg border border-default-300 bg-default-50 px-3 py-1.5 text-sm"
                aria-label="Agent type to trigger"
                title="Agent type"
              >
                {AGENT_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {agentTypeLabel(t)}
                  </option>
                ))}
              </select>
              <Button
                color="primary"
                size="sm"
                startContent={<Play size={14} />}
                onPress={handleTriggerRun}
                isLoading={triggering}
              >
                Trigger
              </Button>
            </div>

            <Table
              aria-label="Agent runs"
              isStriped
              removeWrapper
            >
              <TableHeader>
                <TableColumn>Agent Type</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Proposals</TableColumn>
                <TableColumn>Applied</TableColumn>
                <TableColumn>Started</TableColumn>
                <TableColumn>Duration</TableColumn>
                <TableColumn title="'manual' = triggered by an admin from this page; 'scheduled' = run automatically on the configured schedule hour">Triggered by</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody emptyContent="No runs yet. Use the 'Trigger a run now' panel above to kick off your first agent run.">
                {runs.map((r) => (
                  <TableRow key={r.id}>
                    <TableCell>
                      <span className="font-medium">{agentTypeLabel(r.agent_type)}</span>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={statusColor(r.status)}>
                        {r.status}
                      </Chip>
                    </TableCell>
                    <TableCell>{r.proposals_generated}</TableCell>
                    <TableCell>{r.proposals_applied}</TableCell>
                    <TableCell>
                      <span className="text-xs">{fmtDate(r.started_at)}</span>
                    </TableCell>
                    <TableCell>{durationMs(r)}</TableCell>
                    <TableCell>
                      <span className="text-xs capitalize">{r.triggered_by}</span>
                    </TableCell>
                    <TableCell>
                      <Button
                        size="sm"
                        variant="flat"
                        onPress={() => handleOpenRunDetail(r.id)}
                      >
                        Details
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </Tab>
      </Tabs>

      {/* ================================================================ */}
      {/* RUN DETAIL MODAL                                                  */}
      {/* ================================================================ */}
      <Modal
        isOpen={runModalOpen}
        onClose={() => setRunModalOpen(false)}
        size="4xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <Bot size={18} />
                Run #{selectedRun?.id} — {selectedRun ? agentTypeLabel(selectedRun.agent_type) : ''}
              </ModalHeader>
              <ModalBody>
                {selectedRun && (
                  <div className="space-y-4">
                    {/* Meta */}
                    <div className="grid grid-cols-2 gap-3 text-sm">
                      <div>
                        <span className="text-default-500">Status: </span>
                        <Chip size="sm" color={statusColor(selectedRun.status)}>
                          {selectedRun.status}
                        </Chip>
                      </div>
                      <div>
                        <span className="text-default-500">Triggered by: </span>
                        <span className="capitalize">{selectedRun.triggered_by}</span>
                      </div>
                      <div>
                        <span className="text-default-500">Started: </span>
                        {fmtDate(selectedRun.started_at)}
                      </div>
                      <div>
                        <span className="text-default-500">Duration: </span>
                        {durationMs(selectedRun)}
                      </div>
                      <div>
                        <span className="text-default-500">Proposals generated: </span>
                        {selectedRun.proposals_generated}
                      </div>
                      <div>
                        <span className="text-default-500">Proposals applied: </span>
                        {selectedRun.proposals_applied}
                      </div>
                    </div>

                    {selectedRun.output_summary && (
                      <p className="text-sm text-default-600 bg-default-50 rounded-lg p-3">
                        {selectedRun.output_summary}
                      </p>
                    )}

                    {selectedRun.error_message && (
                      <p className="text-sm text-danger bg-danger-50 rounded-lg p-3">
                        {selectedRun.error_message}
                      </p>
                    )}

                    {/* Proposals list */}
                    {selectedRun.proposals && selectedRun.proposals.length > 0 && (
                      <div>
                        <p className="font-semibold text-sm mb-2">
                          Proposals ({selectedRun.proposals.length})
                        </p>
                        <Table aria-label="Run proposals" removeWrapper>
                          <TableHeader>
                            <TableColumn>Type</TableColumn>
                            <TableColumn>Subject</TableColumn>
                            <TableColumn>Confidence</TableColumn>
                            <TableColumn>Status</TableColumn>
                          </TableHeader>
                          <TableBody>
                            {selectedRun.proposals.map((p) => (
                              <TableRow key={p.id}>
                                <TableCell>
                                  <span className="font-mono text-xs">{p.proposal_type}</span>
                                </TableCell>
                                <TableCell>{p.subject_user_id ?? '—'}</TableCell>
                                <TableCell>
                                  {p.confidence_score !== null ? (
                                    <Chip size="sm" color={confidenceColor(p.confidence_score)}>
                                      {(p.confidence_score * 100).toFixed(0)}%
                                    </Chip>
                                  ) : (
                                    '—'
                                  )}
                                </TableCell>
                                <TableCell>
                                  <Chip size="sm" color={statusColor(p.status)}>
                                    {p.status.replace(/_/g, ' ')}
                                  </Chip>
                                </TableCell>
                              </TableRow>
                            ))}
                          </TableBody>
                        </Table>
                      </div>
                    )}
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={onClose}>
                  Close
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
