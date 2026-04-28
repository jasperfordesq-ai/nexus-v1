// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PushCampaignAdminPage — AG57 Paid Push Campaign Management
 *
 * Admin console for reviewing, approving, rejecting and dispatching
 * paid push notification campaigns from advertisers (SMEs, Vereins, Gemeinden).
 *
 * English-only — NO t() calls (admin panel policy).
 *
 * Features:
 *  - 4 stat cards: Total Campaigns, Pending Review, Sends This Month, Revenue This Month
 *  - Campaigns table with status chips, open-rate, cost, approve/reject/dispatch actions
 *  - Approve triggers immediate dispatch when no future scheduled_at
 *  - Reject modal with mandatory reason
 *  - Campaign detail modal with analytics bar chart (div-based)
 *  - Create Campaign modal
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  ChipProps,
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
  Tab,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Tabs,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import BarChart3 from 'lucide-react/icons/bar-chart-3';
import Bell from 'lucide-react/icons/bell';
import CheckCircle from 'lucide-react/icons/check-circle';
import Clock from 'lucide-react/icons/clock';
import Megaphone from 'lucide-react/icons/megaphone';
import Send from 'lucide-react/icons/send';
import TrendingUp from 'lucide-react/icons/trending-up';
import Users from 'lucide-react/icons/users';
import XCircle from 'lucide-react/icons/x-circle';
import api from '@/lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type CampaignStatus =
  | 'draft'
  | 'pending_review'
  | 'scheduled'
  | 'sending'
  | 'sent'
  | 'paused'
  | 'rejected'
  | 'cancelled';

type AdvertiserType = 'sme' | 'verein' | 'gemeinde' | 'private';

interface Campaign {
  id: number;
  tenant_id: number;
  created_by: number;
  name: string;
  status: CampaignStatus;
  advertiser_type: AdvertiserType;
  title: string;
  body: string;
  cta_url: string | null;
  audience_filter: string | null;
  target_count: number | null;
  actual_send_count: number;
  scheduled_at: string | null;
  sent_at: string | null;
  cost_per_send: number;
  total_cost_cents: number;
  approved_by: number | null;
  approved_at: string | null;
  rejection_reason: string | null;
  open_count: number;
  click_count: number;
  created_at: string;
  updated_at: string;
  advertiser_name: string;
  advertiser_email: string;
  analytics?: CampaignAnalytics;
}

interface CampaignAnalytics {
  send_count: number;
  open_count: number;
  click_count: number;
  open_rate: number;
  daily_breakdown: Array<{ date: string; sends: number; opens: number }>;
}

interface OverviewStats {
  total_campaigns: number;
  by_status: Record<string, number>;
  sends_this_month: number;
  opens_this_month: number;
  revenue_cents_this_month: number;
}

// ---------------------------------------------------------------------------
// Status config
// ---------------------------------------------------------------------------

const STATUS_CONFIG: Record<
  CampaignStatus,
  { label: string; color: ChipProps['color'] }
> = {
  draft:          { label: 'Draft',          color: 'default' },
  pending_review: { label: 'Pending Review', color: 'warning' },
  scheduled:      { label: 'Scheduled',      color: 'primary' },
  sending:        { label: 'Sending',        color: 'secondary' },
  sent:           { label: 'Sent',           color: 'success' },
  paused:         { label: 'Paused',         color: 'default' },
  rejected:       { label: 'Rejected',       color: 'danger' },
  cancelled:      { label: 'Cancelled',      color: 'danger' },
};

const ADVERTISER_LABELS: Record<AdvertiserType, string> = {
  sme:      'SME',
  verein:   'Verein',
  gemeinde: 'Gemeinde',
  private:  'Private',
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatDate(ts: string | null): string {
  if (!ts) return '—';
  return new Date(ts).toLocaleString();
}

function formatCents(cents: number): string {
  return `€${(cents / 100).toFixed(2)}`;
}

function openRate(campaign: Campaign): string {
  if (campaign.actual_send_count === 0) return '—';
  const rate = (campaign.open_count / campaign.actual_send_count) * 100;
  return `${rate.toFixed(1)}%`;
}

function StatusChip({ status }: { status: CampaignStatus }) {
  const cfg = STATUS_CONFIG[status] ?? { label: status, color: 'default' as const };
  return (
    <Chip size="sm" color={cfg.color} variant="flat">
      {cfg.label}
    </Chip>
  );
}

// ---------------------------------------------------------------------------
// Stat Card
// ---------------------------------------------------------------------------

function StatCard({
  icon,
  label,
  value,
  sub,
}: {
  icon: React.ReactNode;
  label: string;
  value: string | number;
  sub?: string;
}) {
  return (
    <Card className="flex-1 min-w-[160px]">
      <CardBody className="flex flex-col gap-2 p-4">
        <div className="flex items-center gap-2 text-default-500">
          {icon}
          <span className="text-xs font-medium uppercase tracking-wide">{label}</span>
        </div>
        <p className="text-2xl font-bold">{value}</p>
        {sub && <p className="text-xs text-default-400">{sub}</p>}
      </CardBody>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Analytics Bar Chart (div-based, no Recharts needed)
// ---------------------------------------------------------------------------

function MiniBarChart({
  breakdown,
}: {
  breakdown: Array<{ date: string; sends: number; opens: number }>;
}) {
  if (breakdown.length === 0) {
    return <p className="text-default-400 text-sm">No daily data yet.</p>;
  }

  const maxSends = Math.max(...breakdown.map((d) => d.sends), 1);

  return (
    <div className="space-y-1">
      <div className="flex gap-4 text-xs text-default-400 mb-2">
        <span className="flex items-center gap-1">
          <span className="inline-block w-3 h-3 rounded-sm bg-primary" />
          Sends
        </span>
        <span className="flex items-center gap-1">
          <span className="inline-block w-3 h-3 rounded-sm bg-success" />
          Opens
        </span>
      </div>
      <div className="overflow-x-auto">
        <div className="flex gap-1 items-end min-w-0" style={{ minHeight: 60 }}>
          {breakdown.map((d) => {
            const sendPct = (d.sends / maxSends) * 100;
            const openPct = maxSends > 0 ? (d.opens / maxSends) * 100 : 0;
            return (
              <div key={d.date} className="flex flex-col items-center gap-0.5 flex-1 min-w-[28px]">
                <div className="w-full flex gap-0.5 items-end" style={{ height: 48 }}>
                  <div
                    className="flex-1 bg-primary rounded-t opacity-80"
                    style={{ height: `${sendPct}%` }}
                    title={`${d.sends} sends`}
                  />
                  <div
                    className="flex-1 bg-success rounded-t opacity-80"
                    style={{ height: `${openPct}%` }}
                    title={`${d.opens} opens`}
                  />
                </div>
                <span className="text-[10px] text-default-400 rotate-45 origin-top-left mt-1 whitespace-nowrap">
                  {d.date.slice(5)}
                </span>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

export default function PushCampaignAdminPage() {
  // Modals
  const detailDisc   = useDisclosure();
  const rejectDisc   = useDisclosure();
  const createDisc   = useDisclosure();

  // Data
  const [campaigns, setCampaigns]   = useState<Campaign[]>([]);
  const [stats, setStats]           = useState<OverviewStats | null>(null);
  const [loading, setLoading]       = useState(true);
  const [error, setError]           = useState<string | null>(null);

  // Status filter tab
  const [statusFilter, setStatusFilter] = useState<string>('all');

  // Detail modal
  const [detailCampaign, setDetailCampaign] = useState<Campaign | null>(null);
  const [detailLoading, setDetailLoading]   = useState(false);

  // Reject modal
  const [rejectTarget, setRejectTarget]     = useState<Campaign | null>(null);
  const [rejectReason, setRejectReason]     = useState('');
  const [rejectSubmitting, setRejectSubmitting] = useState(false);

  // Action loading states (per campaign id)
  const [approving, setApproving]   = useState<number | null>(null);
  const [dispatching, setDispatching] = useState<number | null>(null);

  // Create form
  const [createName, setCreateName]                 = useState('');
  const [createTitle, setCreateTitle]               = useState('');
  const [createBody, setCreateBody]                 = useState('');
  const [createType, setCreateType]                 = useState<AdvertiserType>('sme');
  const [createCtaUrl, setCreateCtaUrl]             = useState('');
  const [createScheduledAt, setCreateScheduledAt]   = useState('');
  const [createSubmitting, setCreateSubmitting]     = useState(false);
  const [createError, setCreateError]               = useState<string | null>(null);

  // Feedback
  const [actionMsg, setActionMsg] = useState<string | null>(null);

  // -------------------------------------------------------------------------
  // Data fetching
  // -------------------------------------------------------------------------

  const fetchCampaigns = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = statusFilter !== 'all' ? `?status=${statusFilter}` : '';
      const [campRes, statsRes] = await Promise.all([
        api.get<{ data: Campaign[] }>(`/v2/admin/push-campaigns${params}`),
        api.get<{ data: OverviewStats }>('/v2/admin/push-campaigns/stats'),
      ]);

      const campRaw = campRes.data;
      const list: Campaign[] = Array.isArray(campRaw)
        ? campRaw
        : Array.isArray((campRaw as { data?: Campaign[] }).data)
          ? (campRaw as { data: Campaign[] }).data
          : [];
      setCampaigns(list);

      const statsRaw = statsRes.data;
      const statsData: OverviewStats = (statsRaw as { data?: OverviewStats }).data ?? (statsRaw as OverviewStats);
      setStats(statsData);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to load campaigns');
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    void fetchCampaigns();
  }, [fetchCampaigns]);

  // -------------------------------------------------------------------------
  // Actions
  // -------------------------------------------------------------------------

  const handleApprove = async (campaign: Campaign) => {
    setApproving(campaign.id);
    setActionMsg(null);
    try {
      await api.post(`/v2/admin/push-campaigns/${campaign.id}/approve`);
      setActionMsg(`Campaign "${campaign.name}" approved.`);
      await fetchCampaigns();
    } catch (e: unknown) {
      setActionMsg(e instanceof Error ? e.message : 'Failed to approve campaign.');
    } finally {
      setApproving(null);
    }
  };

  const handleDispatch = async (campaign: Campaign) => {
    setDispatching(campaign.id);
    setActionMsg(null);
    try {
      await api.post(`/v2/admin/push-campaigns/${campaign.id}/dispatch`);
      setActionMsg(`Campaign "${campaign.name}" dispatched.`);
      await fetchCampaigns();
    } catch (e: unknown) {
      setActionMsg(e instanceof Error ? e.message : 'Failed to dispatch campaign.');
    } finally {
      setDispatching(null);
    }
  };

  const openRejectModal = (campaign: Campaign) => {
    setRejectTarget(campaign);
    setRejectReason('');
    rejectDisc.onOpen();
  };

  const handleReject = async () => {
    if (!rejectTarget || !rejectReason.trim()) return;
    setRejectSubmitting(true);
    try {
      await api.post(`/v2/admin/push-campaigns/${rejectTarget.id}/reject`, {
        reason: rejectReason.trim(),
      });
      rejectDisc.onClose();
      setActionMsg(`Campaign "${rejectTarget.name}" rejected.`);
      await fetchCampaigns();
    } catch (e: unknown) {
      setActionMsg(e instanceof Error ? e.message : 'Failed to reject campaign.');
    } finally {
      setRejectSubmitting(false);
    }
  };

  const openDetailModal = async (campaign: Campaign) => {
    setDetailCampaign(campaign);
    detailDisc.onOpen();
    setDetailLoading(true);
    try {
      const res = await api.get<{ data: Campaign }>(`/v2/admin/push-campaigns/${campaign.id}`);
      const raw = res.data;
      const full: Campaign = (raw as { data?: Campaign }).data ?? (raw as Campaign);
      setDetailCampaign(full);
    } catch {
      // Keep the partial data already set
    } finally {
      setDetailLoading(false);
    }
  };

  const handleCreate = async () => {
    if (!createName.trim() || !createTitle.trim() || !createBody.trim()) {
      setCreateError('Name, title, and body are required.');
      return;
    }
    setCreateSubmitting(true);
    setCreateError(null);
    try {
      await api.post('/v2/me/push-campaigns', {
        name:            createName.trim(),
        title:           createTitle.trim(),
        body:            createBody.trim(),
        advertiser_type: createType,
        cta_url:         createCtaUrl.trim() || null,
        scheduled_at:    createScheduledAt || null,
      });
      createDisc.onClose();
      setCreateName('');
      setCreateTitle('');
      setCreateBody('');
      setCreateType('sme');
      setCreateCtaUrl('');
      setCreateScheduledAt('');
      await fetchCampaigns();
    } catch (e: unknown) {
      setCreateError(e instanceof Error ? e.message : 'Failed to create campaign.');
    } finally {
      setCreateSubmitting(false);
    }
  };

  // -------------------------------------------------------------------------
  // Derived values
  // -------------------------------------------------------------------------

  const pendingCount = campaigns.filter((c) => c.status === 'pending_review').length;

  // -------------------------------------------------------------------------
  // Render
  // -------------------------------------------------------------------------

  return (
    <>
      {/* ------------------------------------------------------------------ */}
      {/* Stats cards                                                          */}
      {/* ------------------------------------------------------------------ */}
      <div className="flex flex-wrap gap-3 mb-6">
        <StatCard
          icon={<Megaphone size={16} />}
          label="Total Campaigns"
          value={stats?.total_campaigns ?? 0}
        />
        <StatCard
          icon={<Clock size={16} />}
          label="Pending Review"
          value={pendingCount}
          sub={pendingCount > 0 ? 'Needs attention' : undefined}
        />
        <StatCard
          icon={<Users size={16} />}
          label="Sends This Month"
          value={(stats?.sends_this_month ?? 0).toLocaleString()}
        />
        <StatCard
          icon={<TrendingUp size={16} />}
          label="Revenue This Month"
          value={formatCents(stats?.revenue_cents_this_month ?? 0)}
        />
      </div>

      {/* ------------------------------------------------------------------ */}
      {/* Main card                                                            */}
      {/* ------------------------------------------------------------------ */}
      <Card>
        <CardHeader className="flex items-center justify-between gap-4 flex-wrap">
          <div className="flex items-center gap-2">
            <Bell size={20} className="text-primary" />
            <h2 className="text-lg font-semibold">Paid Push Campaigns (AG57)</h2>
          </div>
          <Button
            color="primary"
            startContent={<Send size={16} />}
            onPress={createDisc.onOpen}
          >
            New Campaign
          </Button>
        </CardHeader>
        <Divider />
        <CardBody>
          {actionMsg && (
            <div className="mb-3 px-3 py-2 rounded-lg bg-default-100 text-sm text-default-700">
              {actionMsg}
            </div>
          )}

          {/* Status filter tabs */}
          <Tabs
            aria-label="Filter by status"
            selectedKey={statusFilter}
            onSelectionChange={(k) => setStatusFilter(k as string)}
            className="mb-4"
            size="sm"
          >
            <Tab key="all" title="All" />
            <Tab key="pending_review" title="Pending Review" />
            <Tab key="scheduled" title="Scheduled" />
            <Tab key="sent" title="Sent" />
            <Tab key="rejected" title="Rejected" />
            <Tab key="draft" title="Drafts" />
          </Tabs>

          {loading && (
            <div className="flex justify-center py-10">
              <Spinner size="lg" />
            </div>
          )}

          {!loading && error && (
            <p className="text-danger text-sm">{error}</p>
          )}

          {!loading && !error && campaigns.length === 0 && (
            <p className="text-default-400 text-sm py-4">No campaigns found.</p>
          )}

          {!loading && !error && campaigns.length > 0 && (
            <Table aria-label="Push campaigns" removeWrapper>
              <TableHeader>
                <TableColumn>Campaign</TableColumn>
                <TableColumn>Advertiser</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Title Preview</TableColumn>
                <TableColumn>Targets</TableColumn>
                <TableColumn>Open Rate</TableColumn>
                <TableColumn>Cost</TableColumn>
                <TableColumn>Scheduled</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody>
                {campaigns.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell>
                      <button
                        className="text-primary text-sm font-medium hover:underline text-left"
                        onClick={() => openDetailModal(c)}
                      >
                        {c.name}
                      </button>
                    </TableCell>
                    <TableCell>
                      <div className="text-sm">
                        <p>{c.advertiser_name || '—'}</p>
                        <p className="text-default-400 text-xs">
                          {ADVERTISER_LABELS[c.advertiser_type] ?? c.advertiser_type}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <StatusChip status={c.status} />
                    </TableCell>
                    <TableCell>
                      <p className="text-sm max-w-[180px] truncate" title={c.title}>
                        {c.title}
                      </p>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">
                        {c.actual_send_count > 0
                          ? c.actual_send_count.toLocaleString()
                          : (c.target_count != null ? `~${c.target_count.toLocaleString()}` : '—')}
                      </span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">{openRate(c)}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">
                        {c.total_cost_cents > 0
                          ? formatCents(c.total_cost_cents)
                          : `${c.cost_per_send}¢/send`}
                      </span>
                    </TableCell>
                    <TableCell>
                      <span className="text-xs text-default-400">
                        {c.scheduled_at ? formatDate(c.scheduled_at) : 'Immediate'}
                      </span>
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1 flex-wrap">
                        {c.status === 'pending_review' && (
                          <>
                            <Button
                              size="sm"
                              color="success"
                              variant="flat"
                              startContent={<CheckCircle size={14} />}
                              isLoading={approving === c.id}
                              onPress={() => handleApprove(c)}
                            >
                              Approve
                            </Button>
                            <Button
                              size="sm"
                              color="danger"
                              variant="flat"
                              startContent={<XCircle size={14} />}
                              onPress={() => openRejectModal(c)}
                            >
                              Reject
                            </Button>
                          </>
                        )}
                        {c.status === 'scheduled' && (
                          <Button
                            size="sm"
                            color="primary"
                            variant="flat"
                            startContent={<Send size={14} />}
                            isLoading={dispatching === c.id}
                            onPress={() => handleDispatch(c)}
                          >
                            Dispatch Now
                          </Button>
                        )}
                        <Button
                          size="sm"
                          variant="flat"
                          startContent={<BarChart3 size={14} />}
                          onPress={() => openDetailModal(c)}
                        >
                          Details
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* ------------------------------------------------------------------ */}
      {/* Campaign Detail Modal                                                */}
      {/* ------------------------------------------------------------------ */}
      <Modal
        isOpen={detailDisc.isOpen}
        onClose={detailDisc.onClose}
        size="3xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <BarChart3 size={18} />
            Campaign Details
          </ModalHeader>
          <ModalBody>
            {detailLoading && (
              <div className="flex justify-center py-8">
                <Spinner />
              </div>
            )}
            {detailCampaign && !detailLoading && (
              <div className="space-y-4">
                {/* Summary */}
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <p className="text-default-400 text-xs mb-0.5">Campaign Name</p>
                    <p className="font-medium">{detailCampaign.name}</p>
                  </div>
                  <div>
                    <p className="text-default-400 text-xs mb-0.5">Status</p>
                    <StatusChip status={detailCampaign.status} />
                  </div>
                  <div>
                    <p className="text-default-400 text-xs mb-0.5">Advertiser</p>
                    <p>{detailCampaign.advertiser_name || '—'}</p>
                    <p className="text-default-400 text-xs">{detailCampaign.advertiser_email}</p>
                  </div>
                  <div>
                    <p className="text-default-400 text-xs mb-0.5">Type</p>
                    <p>{ADVERTISER_LABELS[detailCampaign.advertiser_type] ?? detailCampaign.advertiser_type}</p>
                  </div>
                  <div className="col-span-2">
                    <p className="text-default-400 text-xs mb-0.5">Push Title</p>
                    <p className="font-medium">{detailCampaign.title}</p>
                  </div>
                  <div className="col-span-2">
                    <p className="text-default-400 text-xs mb-0.5">Push Body</p>
                    <p>{detailCampaign.body}</p>
                  </div>
                  {detailCampaign.cta_url && (
                    <div className="col-span-2">
                      <p className="text-default-400 text-xs mb-0.5">CTA URL</p>
                      <a
                        href={detailCampaign.cta_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-primary text-xs break-all"
                      >
                        {detailCampaign.cta_url}
                      </a>
                    </div>
                  )}
                </div>

                <Divider />

                {/* Metrics */}
                <div className="grid grid-cols-4 gap-3 text-center text-sm">
                  <div>
                    <p className="text-2xl font-bold">{detailCampaign.actual_send_count.toLocaleString()}</p>
                    <p className="text-default-400 text-xs">Sent</p>
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{detailCampaign.open_count.toLocaleString()}</p>
                    <p className="text-default-400 text-xs">Opens</p>
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{openRate(detailCampaign)}</p>
                    <p className="text-default-400 text-xs">Open Rate</p>
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{formatCents(detailCampaign.total_cost_cents)}</p>
                    <p className="text-default-400 text-xs">Revenue</p>
                  </div>
                </div>

                {/* Analytics chart */}
                {detailCampaign.analytics && (
                  <>
                    <Divider />
                    <div>
                      <p className="text-sm font-medium mb-3">Daily Breakdown</p>
                      <MiniBarChart breakdown={detailCampaign.analytics.daily_breakdown} />
                    </div>
                  </>
                )}

                {/* Rejection reason */}
                {detailCampaign.rejection_reason && (
                  <>
                    <Divider />
                    <div className="rounded-lg bg-danger-50 border border-danger-200 p-3 text-sm">
                      <p className="font-medium text-danger mb-1">Rejection Reason</p>
                      <p className="text-danger-700">{detailCampaign.rejection_reason}</p>
                    </div>
                  </>
                )}

                {/* Dates */}
                <Divider />
                <div className="grid grid-cols-2 gap-3 text-xs text-default-400">
                  <div>
                    <span>Created: </span>
                    <span>{formatDate(detailCampaign.created_at)}</span>
                  </div>
                  <div>
                    <span>Approved: </span>
                    <span>{formatDate(detailCampaign.approved_at)}</span>
                  </div>
                  <div>
                    <span>Scheduled: </span>
                    <span>{detailCampaign.scheduled_at ? formatDate(detailCampaign.scheduled_at) : 'Immediate'}</span>
                  </div>
                  <div>
                    <span>Sent: </span>
                    <span>{formatDate(detailCampaign.sent_at)}</span>
                  </div>
                </div>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={detailDisc.onClose}>
              Close
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ------------------------------------------------------------------ */}
      {/* Reject Modal                                                         */}
      {/* ------------------------------------------------------------------ */}
      <Modal isOpen={rejectDisc.isOpen} onClose={rejectDisc.onClose} size="lg">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <XCircle size={18} className="text-danger" />
            Reject Campaign
          </ModalHeader>
          <ModalBody>
            {rejectTarget && (
              <>
                <p className="text-sm text-default-600">
                  Rejecting <strong>{rejectTarget.name}</strong>. Please provide a reason that will
                  be visible to the advertiser.
                </p>
                <Textarea
                  label="Rejection Reason"
                  placeholder="e.g. Content does not comply with community guidelines..."
                  value={rejectReason}
                  onValueChange={setRejectReason}
                  variant="bordered"
                  minRows={3}
                  isRequired
                />
              </>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={rejectDisc.onClose}>
              Cancel
            </Button>
            <Button
              color="danger"
              isLoading={rejectSubmitting}
              isDisabled={!rejectReason.trim()}
              onPress={handleReject}
            >
              Reject Campaign
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ------------------------------------------------------------------ */}
      {/* Create Campaign Modal                                                */}
      {/* ------------------------------------------------------------------ */}
      <Modal isOpen={createDisc.isOpen} onClose={createDisc.onClose} size="2xl">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Megaphone size={18} className="text-primary" />
            New Push Campaign
          </ModalHeader>
          <ModalBody>
            <div className="space-y-3">
              {createError && (
                <p className="text-danger text-sm">{createError}</p>
              )}
              <Input
                label="Campaign Name"
                placeholder="e.g. Spring Promotion — Café Seeblick"
                value={createName}
                onValueChange={setCreateName}
                variant="bordered"
                isRequired
              />
              <Select
                label="Advertiser Type"
                selectedKeys={[createType]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as AdvertiserType;
                  if (val) setCreateType(val);
                }}
                variant="bordered"
              >
                <SelectItem key="sme">SME</SelectItem>
                <SelectItem key="verein">Verein</SelectItem>
                <SelectItem key="gemeinde">Gemeinde</SelectItem>
                <SelectItem key="private">Private</SelectItem>
              </Select>
              <Input
                label="Push Notification Title"
                placeholder="Max 100 characters"
                value={createTitle}
                onValueChange={setCreateTitle}
                variant="bordered"
                isRequired
                description={`${createTitle.length}/100`}
                maxLength={100}
              />
              <Textarea
                label="Push Notification Body"
                placeholder="Max 400 characters"
                value={createBody}
                onValueChange={setCreateBody}
                variant="bordered"
                isRequired
                minRows={2}
                description={`${createBody.length}/400`}
                maxLength={400}
              />
              <Input
                label="CTA URL (optional)"
                placeholder="https://... or nexus://..."
                value={createCtaUrl}
                onValueChange={setCreateCtaUrl}
                variant="bordered"
              />
              <Input
                type="datetime-local"
                label="Scheduled At (optional — leave blank to send on approval)"
                value={createScheduledAt}
                onValueChange={setCreateScheduledAt}
                variant="bordered"
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={createDisc.onClose}>
              Cancel
            </Button>
            <Button
              color="primary"
              startContent={<Send size={16} />}
              isLoading={createSubmitting}
              isDisabled={!createName.trim() || !createTitle.trim() || !createBody.trim()}
              onPress={handleCreate}
            >
              Create Campaign
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}
