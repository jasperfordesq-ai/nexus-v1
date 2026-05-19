// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AdCampaignAdminPage — AG56 Local Advertising Platform
 *
 * Admin interface for managing local ad campaigns.
 * Features:
 *   - Overview stat cards (Active, Impressions Today, Clicks Today, Revenue)
 *   - Campaign table with status filtering
 *   - Approve / Reject (with reason) / Pause actions
 *   - View Details modal (campaign info + creatives + 30d stats)
 *   - Create Campaign modal
 *
 * User-facing admin copy is routed through translations.
 */

import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Chip,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Select,
  SelectItem,
  Tabs,
  Tab,
  Textarea,
} from '@heroui/react';
import Megaphone from 'lucide-react/icons/megaphone';
import TrendingUp from 'lucide-react/icons/trending-up';
import Eye from 'lucide-react/icons/eye';
import MousePointer from 'lucide-react/icons/mouse-pointer';
import CheckCircle from 'lucide-react/icons/check-circle';
import XCircle from 'lucide-react/icons/x-circle';
import Pause from 'lucide-react/icons/pause';
import DollarSign from 'lucide-react/icons/dollar-sign';
import BarChart3 from 'lucide-react/icons/bar-chart-3';
import Plus from 'lucide-react/icons/plus';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, DataTable, StatCard, type Column } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface AdCampaign {
  id: number;
  tenant_id: number;
  created_by: number;
  name: string;
  status: 'pending_review' | 'active' | 'paused' | 'completed' | 'rejected';
  advertiser_type: 'sme' | 'verein' | 'gemeinde' | 'private';
  budget_cents: number;
  spent_cents: number;
  start_date: string | null;
  end_date: string | null;
  audience_filters: string | null;
  placement: 'feed' | 'discovery' | 'markt' | 'all';
  approved_by: number | null;
  approved_at: string | null;
  rejection_reason: string | null;
  impression_count: number;
  click_count: number;
  created_at: string;
  updated_at: string;
  advertiser_name?: string;
  advertiser_email?: string;
  creative_count?: number;
  creatives?: AdCreative[];
  stats?: CampaignStats;
}

interface AdCreative {
  id: number;
  campaign_id: number;
  tenant_id: number;
  headline: string;
  body: string;
  cta_text: string | null;
  image_url: string | null;
  destination_url: string | null;
  is_active: number;
  created_at: string;
}

interface CampaignStats {
  campaign_id: number;
  impressions: number;
  clicks: number;
  ctr_percent: number;
  budget_cents: number;
  spent_cents: number;
  budget_remaining: number | null;
  daily: Array<{ date: string; impressions: number; clicks: number }>;
}

interface OverviewStats {
  active_campaigns: number;
  impressions_today: number;
  clicks_today: number;
  total_revenue_cents: number;
}

interface CreateCampaignForm {
  name: string;
  advertiser_type: string;
  budget_cents: string;
  start_date: string;
  end_date: string;
  placement: string;
  audience_filters: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<AdCampaign['status'], 'warning' | 'success' | 'default' | 'secondary' | 'danger'> = {
  pending_review: 'warning',
  active:         'success',
  paused:         'default',
  completed:      'secondary',
  rejected:       'danger',
};

const STATUS_LABEL_KEYS: Record<AdCampaign['status'], string> = {
  pending_review: 'advertising.status.pending_review',
  active:         'advertising.status.active',
  paused:         'advertising.status.paused',
  completed:      'advertising.status.completed',
  rejected:       'advertising.status.rejected',
};

function formatCents(cents: number): string {
  return `€${(cents / 100).toFixed(2)}`;
}

function ctr(impressions: number, clicks: number): string {
  if (impressions === 0) return '0.00%';
  return `${((clicks / impressions) * 100).toFixed(2)}%`;
}

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

const EMPTY_FORM: CreateCampaignForm = {
  name: '',
  advertiser_type: 'sme',
  budget_cents: '0',
  start_date: '',
  end_date: '',
  placement: 'feed',
  audience_filters: '',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function AdCampaignAdminPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('advertising.ad.page_title', 'Ad Campaigns'));
  const toast = useToast();

  // ── Data state ──────────────────────────────────────────────────────────────
  const [campaigns, setCampaigns] = useState<AdCampaign[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('all');
  const [overviewStats, setOverviewStats] = useState<OverviewStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // ── Modal state ─────────────────────────────────────────────────────────────
  const [detailCampaign, setDetailCampaign] = useState<AdCampaign | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const [rejectTarget, setRejectTarget] = useState<AdCampaign | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejectLoading, setRejectLoading] = useState(false);

  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<CreateCampaignForm>(EMPTY_FORM);
  const [createLoading, setCreateLoading] = useState(false);

  const [actionLoading, setActionLoading] = useState<number | null>(null);

  const PAGE_SIZE = 50;

  // ── Load overview stats ─────────────────────────────────────────────────────

  const loadStats = useCallback(async () => {
    setStatsLoading(true);
    try {
      const res = await api.get('/v2/admin/ad-campaigns/stats');
      if (res.data) {
        setOverviewStats(res.data as OverviewStats);
      }
    } catch {
      // Non-critical — stats failing shouldn't block the page
    } finally {
      setStatsLoading(false);
    }
  }, []);

  // ── Load campaigns ──────────────────────────────────────────────────────────

  const loadCampaigns = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('page', String(page));
      params.set('limit', String(PAGE_SIZE));
      if (statusFilter !== 'all') params.set('status', statusFilter);

      const res = await api.get(`/v2/admin/ad-campaigns?${params.toString()}`);
      if (res.data) {
        const items = Array.isArray(res.data) ? (res.data as AdCampaign[]) : [];
        setCampaigns(items);
        setTotal(res.meta?.total ?? items.length);
      }
    } catch {
      toast.error(t('advertising.ad.toasts.load_failed', 'Failed to load ad campaigns'));
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, t, toast]);

  useEffect(() => {
    void loadStats();
  }, [loadStats]);

  useEffect(() => {
    void loadCampaigns();
  }, [loadCampaigns]);

  // ── Actions ─────────────────────────────────────────────────────────────────

  const handleApprove = async (campaign: AdCampaign) => {
    setActionLoading(campaign.id);
    try {
      const res = await api.post(`/v2/admin/ad-campaigns/${campaign.id}/approve`);
      if (res.data) {
        toast.success(t('advertising.ad.toasts.approved', { name: campaign.name }));
        void loadCampaigns();
        void loadStats();
      } else {
        toast.error(t('advertising.ad.toasts.approve_failed', 'Failed to approve campaign'));
      }
    } catch {
      toast.error(t('advertising.shared.unexpected_error', 'An unexpected error occurred'));
    } finally {
      setActionLoading(null);
    }
  };

  const handleRejectSubmit = async () => {
    if (!rejectTarget || !rejectReason.trim()) return;
    setRejectLoading(true);
    try {
      const res = await api.post(`/v2/admin/ad-campaigns/${rejectTarget.id}/reject`, { reason: rejectReason.trim() });
      if (res.data) {
        toast.success(t('advertising.ad.toasts.rejected', { name: rejectTarget.name }));
        setRejectTarget(null);
        setRejectReason('');
        void loadCampaigns();
        void loadStats();
      } else {
        toast.error(t('advertising.ad.toasts.reject_failed', 'Failed to reject campaign'));
      }
    } catch {
      toast.error(t('advertising.shared.unexpected_error', 'An unexpected error occurred'));
    } finally {
      setRejectLoading(false);
    }
  };

  const handlePause = async (campaign: AdCampaign) => {
    setActionLoading(campaign.id);
    try {
      const res = await api.post(`/v2/admin/ad-campaigns/${campaign.id}/pause`);
      if (res.data) {
        toast.success(t('advertising.ad.toasts.paused', { name: campaign.name }));
        void loadCampaigns();
        void loadStats();
      } else {
        toast.error(t('advertising.ad.toasts.pause_failed', 'Failed to pause campaign'));
      }
    } catch {
      toast.error(t('advertising.shared.unexpected_error', 'An unexpected error occurred'));
    } finally {
      setActionLoading(null);
    }
  };

  const handleViewDetails = async (campaign: AdCampaign) => {
    setDetailCampaign({ ...campaign });
    setDetailLoading(true);
    try {
      const res = await api.get(`/v2/admin/ad-campaigns/${campaign.id}`);
      if (res.data) {
        setDetailCampaign(res.data as AdCampaign);
      }
    } catch {
      toast.error(t('advertising.ad.toasts.details_failed', 'Failed to load campaign details'));
    } finally {
      setDetailLoading(false);
    }
  };

  const handleCreateSubmit = async () => {
    if (!createForm.name.trim()) {
      toast.error(t('advertising.ad.toasts.name_required', 'Campaign name is required'));
      return;
    }
    setCreateLoading(true);
    try {
      const payload: Record<string, unknown> = {
        name:            createForm.name.trim(),
        advertiser_type: createForm.advertiser_type,
        budget_cents:    parseInt(createForm.budget_cents || '0', 10),
        placement:       createForm.placement,
        start_date:      createForm.start_date || null,
        end_date:        createForm.end_date || null,
      };

      if (createForm.audience_filters.trim()) {
        try {
          payload.audience_filters = JSON.parse(createForm.audience_filters);
        } catch {
          toast.error(t('advertising.ad.toasts.audience_json_invalid', 'Audience filters must be valid JSON'));
          setCreateLoading(false);
          return;
        }
      }

      const res = await api.post('/v2/me/ad-campaigns', payload);
      if (res.data) {
        toast.success(t('advertising.ad.toasts.created', 'Campaign created and submitted for review'));
        setCreateOpen(false);
        setCreateForm(EMPTY_FORM);
        void loadCampaigns();
        void loadStats();
      } else {
        toast.error(t('advertising.ad.toasts.create_failed', 'Failed to create campaign'));
      }
    } catch {
      toast.error(t('advertising.shared.unexpected_error', 'An unexpected error occurred'));
    } finally {
      setCreateLoading(false);
    }
  };

  // ── Table columns ───────────────────────────────────────────────────────────

  const columns: Column<AdCampaign>[] = [
    {
      key: 'id',
      label: t('advertising.shared.columns.id', 'ID'),
      render: (item) => <span className="text-xs text-default-400">#{item.id}</span>,
    },
    {
      key: 'name',
      label: t('advertising.shared.columns.campaign', 'Campaign'),
      sortable: true,
      render: (item) => (
        <div>
          <p className="font-medium text-foreground truncate max-w-[200px]">{item.name}</p>
          <p className="text-xs text-default-400 capitalize">{item.advertiser_type}</p>
        </div>
      ),
    },
    {
      key: 'advertiser_name',
      label: t('advertising.shared.columns.advertiser', 'Advertiser'),
      render: (item) => (
        <div>
          <p className="text-sm text-default-700">{item.advertiser_name ?? '—'}</p>
          <p className="text-xs text-default-400">{item.advertiser_email ?? ''}</p>
        </div>
      ),
    },
    {
      key: 'status',
      label: t('advertising.shared.columns.status', 'Status'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color={STATUS_COLORS[item.status]}>
          {t(STATUS_LABEL_KEYS[item.status])}
        </Chip>
      ),
    },
    {
      key: 'budget_cents',
      label: t('advertising.ad.columns.budget_spent', 'Budget / Spent'),
      render: (item) => (
        <div className="text-sm">
          <span className="text-default-700">{formatCents(item.budget_cents)}</span>
          <span className="text-default-400"> / </span>
          <span className="text-warning">{formatCents(item.spent_cents)}</span>
        </div>
      ),
    },
    {
      key: 'impression_count',
      label: t('advertising.shared.columns.impressions', 'Impressions'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.impression_count.toLocaleString()}</span>
      ),
    },
    {
      key: 'click_count',
      label: t('advertising.shared.columns.clicks', 'Clicks'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.click_count.toLocaleString()}</span>
      ),
    },
    {
      key: 'ctr',
      label: t('advertising.shared.columns.ctr', 'CTR'),
      render: (item) => (
        <span className="text-sm text-default-600">{ctr(item.impression_count, item.click_count)}</span>
      ),
    },
    {
      key: 'actions',
      label: t('advertising.shared.columns.actions', 'Actions'),
      render: (item) => (
        <div className="flex items-center gap-1 flex-wrap">
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="primary"
            onPress={() => void handleViewDetails(item)}
            aria-label={t('advertising.shared.actions.view_details', 'View details')}
          >
            <BarChart3 size={14} />
          </Button>

          {item.status === 'pending_review' && (
            <>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="success"
                onPress={() => void handleApprove(item)}
                isLoading={actionLoading === item.id}
                aria-label={t('advertising.shared.actions.approve', 'Approve')}
              >
                <CheckCircle size={14} />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="danger"
                onPress={() => { setRejectTarget(item); setRejectReason(''); }}
                aria-label={t('advertising.shared.actions.reject', 'Reject')}
              >
                <XCircle size={14} />
              </Button>
            </>
          )}

          {item.status === 'active' && (
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="warning"
              onPress={() => void handlePause(item)}
              isLoading={actionLoading === item.id}
              aria-label={t('advertising.shared.actions.pause', 'Pause')}
            >
              <Pause size={14} />
            </Button>
          )}
        </div>
      ),
    },
  ];

  // ── Render ──────────────────────────────────────────────────────────────────

  return (
    <div>
      <PageHeader
        title={t('advertising.ad.header.title', 'Local Advertising')}
        description={t('advertising.ad.header.description', 'Manage local ad campaigns from SMEs, associations, and municipalities')}
        actions={
          <Button
            color="primary"
            startContent={<Plus size={16} />}
            onPress={() => { setCreateForm(EMPTY_FORM); setCreateOpen(true); }}
          >
            {t('advertising.shared.actions.create_campaign', 'Create campaign')}
          </Button>
        }
      />

      {/* ── Overview stat cards ── */}
      <div className="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-4">
        <StatCard
          label={t('advertising.ad.stats.active_campaigns', 'Active campaigns')}
          value={overviewStats?.active_campaigns ?? 0}
          icon={Megaphone}
          color="primary"
          loading={statsLoading}
        />
        <StatCard
          label={t('advertising.ad.stats.impressions_today', 'Impressions today')}
          value={overviewStats?.impressions_today ?? 0}
          icon={Eye}
          color="secondary"
          loading={statsLoading}
        />
        <StatCard
          label={t('advertising.ad.stats.clicks_today', 'Clicks today')}
          value={overviewStats?.clicks_today ?? 0}
          icon={MousePointer}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label={t('advertising.ad.stats.total_revenue', 'Total revenue')}
          value={overviewStats ? formatCents(overviewStats.total_revenue_cents) : '—'}
          icon={DollarSign}
          color="success"
          loading={statsLoading}
        />
      </div>

      {/* ── Status filter tabs ── */}
      <div className="mb-4">
        <Tabs
          selectedKey={statusFilter}
          onSelectionChange={(key) => { setStatusFilter(key as string); setPage(1); }}
          variant="underlined"
          size="sm"
        >
          <Tab key="all"            title={t('advertising.status.all', 'All')} />
          <Tab key="pending_review" title={t('advertising.status.pending_review', 'Pending Review')} />
          <Tab key="active"         title={t('advertising.status.active', 'Active')} />
          <Tab key="paused"         title={t('advertising.status.paused', 'Paused')} />
          <Tab key="completed"      title={t('advertising.status.completed', 'Completed')} />
          <Tab key="rejected"       title={t('advertising.status.rejected', 'Rejected')} />
        </Tabs>
      </div>

      {/* ── Campaign table ── */}
      <DataTable
        columns={columns}
        data={campaigns}
        isLoading={loading}
        searchPlaceholder={t('advertising.shared.search_campaigns', 'Search campaigns...')}
        onRefresh={loadCampaigns}
        totalItems={total}
        page={page}
        pageSize={PAGE_SIZE}
        onPageChange={setPage}
      />

      {/* ──────────────────────────────────────────────────────────────────────
          View Details Modal
      ────────────────────────────────────────────────────────────────────── */}
      <Modal
        isOpen={!!detailCampaign}
        onClose={() => setDetailCampaign(null)}
        size="2xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {() => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <TrendingUp size={20} className="text-primary" />
                {detailCampaign?.name ?? t('advertising.shared.campaign_details', 'Campaign details')}
              </ModalHeader>

              <ModalBody>
                {detailLoading ? (
                  <div className="flex justify-center py-10">
                    <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                  </div>
                ) : detailCampaign ? (
                  <div className="space-y-6">
                    {/* Campaign metadata */}
                    <div className="grid grid-cols-2 gap-3 text-sm">
                      <div>
                        <p className="text-default-400 text-xs uppercase tracking-wide mb-0.5">{t('advertising.shared.columns.advertiser', 'Advertiser')}</p>
                        <p className="text-foreground font-medium">{detailCampaign.advertiser_name ?? '—'}</p>
                        <p className="text-default-400 text-xs">{detailCampaign.advertiser_email ?? ''}</p>
                      </div>
                      <div>
                        <p className="text-default-400 text-xs uppercase tracking-wide mb-0.5">{t('advertising.shared.columns.status', 'Status')}</p>
                        <Chip size="sm" variant="flat" color={STATUS_COLORS[detailCampaign.status]}>
                          {t(STATUS_LABEL_KEYS[detailCampaign.status])}
                        </Chip>
                      </div>
                      <div>
                        <p className="text-default-400 text-xs uppercase tracking-wide mb-0.5">{t('advertising.ad.columns.budget_spent', 'Budget / Spent')}</p>
                        <p className="text-foreground">
                          {formatCents(detailCampaign.budget_cents)} / {formatCents(detailCampaign.spent_cents)}
                        </p>
                      </div>
                      <div>
                        <p className="text-default-400 text-xs uppercase tracking-wide mb-0.5">{t('advertising.ad.fields.placement', 'Placement')}</p>
                        <p className="text-foreground capitalize">{detailCampaign.placement}</p>
                      </div>
                      <div>
                        <p className="text-default-400 text-xs uppercase tracking-wide mb-0.5">{t('advertising.ad.fields.start_date', 'Start date')}</p>
                        <p className="text-foreground">{formatDate(detailCampaign.start_date)}</p>
                      </div>
                      <div>
                        <p className="text-default-400 text-xs uppercase tracking-wide mb-0.5">{t('advertising.ad.fields.end_date', 'End date')}</p>
                        <p className="text-foreground">{formatDate(detailCampaign.end_date)}</p>
                      </div>
                    </div>

                    {/* Rejection reason */}
                    {detailCampaign.rejection_reason && (
                      <div className="rounded-lg bg-danger-50 border border-danger-200 p-3">
                        <p className="text-xs font-semibold text-danger uppercase tracking-wide mb-1">{t('advertising.shared.rejection_reason', 'Rejection reason')}</p>
                        <p className="text-sm text-danger-700">{detailCampaign.rejection_reason}</p>
                      </div>
                    )}

                    {/* Stats summary */}
                    {detailCampaign.stats && (
                      <div>
                        <p className="text-sm font-semibold text-foreground mb-2">{t('advertising.ad.performance_30_days', 'Performance (30 days)')}</p>
                        <div className="grid grid-cols-3 gap-3">
                          <div className="rounded-lg bg-default-100 p-3 text-center">
                            <p className="text-2xl font-bold text-foreground">{detailCampaign.stats.impressions.toLocaleString()}</p>
                            <p className="text-xs text-default-400 mt-0.5">{t('advertising.shared.columns.impressions', 'Impressions')}</p>
                          </div>
                          <div className="rounded-lg bg-default-100 p-3 text-center">
                            <p className="text-2xl font-bold text-foreground">{detailCampaign.stats.clicks.toLocaleString()}</p>
                            <p className="text-xs text-default-400 mt-0.5">{t('advertising.shared.columns.clicks', 'Clicks')}</p>
                          </div>
                          <div className="rounded-lg bg-default-100 p-3 text-center">
                            <p className="text-2xl font-bold text-foreground">{detailCampaign.stats.ctr_percent.toFixed(2)}%</p>
                            <p className="text-xs text-default-400 mt-0.5">{t('advertising.shared.columns.ctr', 'CTR')}</p>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* Creatives */}
                    {(detailCampaign.creatives ?? []).length > 0 && (
                      <div>
                        <p className="text-sm font-semibold text-foreground mb-2">
                          {t('advertising.ad.creatives_count', { count: detailCampaign.creatives!.length })}
                        </p>
                        <div className="space-y-3">
                          {detailCampaign.creatives!.map((creative) => (
                            <div
                              key={creative.id}
                              className="rounded-lg border border-default-200 p-3 bg-default-50"
                            >
                              <p className="font-semibold text-foreground">{creative.headline}</p>
                              <p className="text-sm text-default-600 mt-0.5">{creative.body}</p>
                              {creative.cta_text && (
                                <span className="mt-1 inline-block rounded bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                  {creative.cta_text}
                                </span>
                              )}
                              {creative.destination_url && (
                                <p className="mt-1 text-xs text-default-400 truncate">
                                  <a href={creative.destination_url} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                                    {creative.destination_url}
                                  </a>
                                </p>
                              )}
                              {creative.image_url && (
                                <img
                                  src={creative.image_url}
                                  alt={t('advertising.ad.creative_alt', 'Creative')}
                                  className="mt-2 max-h-32 rounded object-contain"
                                />
                              )}
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    {(detailCampaign.creatives ?? []).length === 0 && (
                      <p className="text-sm text-default-400 italic">{t('advertising.ad.no_creatives', 'No creatives attached to this campaign yet.')}</p>
                    )}
                  </div>
                ) : null}
              </ModalBody>

              <ModalFooter>
                <Button variant="light" onPress={() => setDetailCampaign(null)}>
                  {t('advertising.shared.actions.close', 'Close')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* ──────────────────────────────────────────────────────────────────────
          Reject Modal
      ────────────────────────────────────────────────────────────────────── */}
      <Modal
        isOpen={!!rejectTarget}
        onClose={() => { setRejectTarget(null); setRejectReason(''); }}
        size="md"
      >
        <ModalContent>
          {() => (
            <>
              <ModalHeader className="flex items-center gap-2 text-danger">
                <XCircle size={20} />
                {t('advertising.shared.actions.reject_campaign', 'Reject campaign')}
              </ModalHeader>
              <ModalBody>
                <p className="text-sm text-default-600 mb-3">
                  {t('advertising.ad.reject_intro_prefix', 'You are rejecting')} <strong>{rejectTarget?.name}</strong>. {t('advertising.ad.reject_intro_suffix', 'Please provide a reason so the advertiser knows how to improve their submission.')}
                </p>
                <Textarea
                  label={t('advertising.shared.rejection_reason', 'Rejection reason')}
                  placeholder={t('advertising.ad.rejection_placeholder', 'e.g. Content violates community guidelines - please revise the headline.')}
                  value={rejectReason}
                  onValueChange={setRejectReason}
                  minRows={3}
                  variant="bordered"
                  isRequired
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={() => { setRejectTarget(null); setRejectReason(''); }}>
                  {t('advertising.shared.actions.cancel', 'Cancel')}
                </Button>
                <Button
                  color="danger"
                  onPress={handleRejectSubmit}
                  isLoading={rejectLoading}
                  isDisabled={!rejectReason.trim()}
                >
                  {t('advertising.shared.actions.reject_campaign', 'Reject campaign')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* ──────────────────────────────────────────────────────────────────────
          Create Campaign Modal
      ────────────────────────────────────────────────────────────────────── */}
      <Modal
        isOpen={createOpen}
        onClose={() => { setCreateOpen(false); setCreateForm(EMPTY_FORM); }}
        size="lg"
        scrollBehavior="inside"
      >
        <ModalContent>
          {() => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <Plus size={20} className="text-primary" />
                {t('advertising.ad.create_title', 'Create ad campaign')}
              </ModalHeader>
              <ModalBody className="space-y-4">
                <Input
                  label={t('advertising.shared.fields.campaign_name', 'Campaign name')}
                  placeholder={t('advertising.ad.placeholders.campaign_name', 'Summer Sale - Cafe Helvetia')}
                  value={createForm.name}
                  onValueChange={(v) => setCreateForm((f) => ({ ...f, name: v }))}
                  variant="bordered"
                  isRequired
                />

                <Select
                  label={t('advertising.shared.fields.advertiser_type', 'Advertiser type')}
                  selectedKeys={[createForm.advertiser_type]}
                  onSelectionChange={(keys) => {
                    const v = Array.from(keys)[0] as string;
                    setCreateForm((f) => ({ ...f, advertiser_type: v }));
                  }}
                  variant="bordered"
                >
                  <SelectItem key="sme">{t('advertising.advertiser.sme_local', 'SME (local business)')}</SelectItem>
                  <SelectItem key="verein">{t('advertising.advertiser.verein', 'Association')}</SelectItem>
                  <SelectItem key="gemeinde">{t('advertising.advertiser.gemeinde', 'Municipality')}</SelectItem>
                  <SelectItem key="private">{t('advertising.advertiser.private', 'Private')}</SelectItem>
                </Select>

                <Select
                  label={t('advertising.ad.fields.placement', 'Placement')}
                  selectedKeys={[createForm.placement]}
                  onSelectionChange={(keys) => {
                    const v = Array.from(keys)[0] as string;
                    setCreateForm((f) => ({ ...f, placement: v }));
                  }}
                  variant="bordered"
                >
                  <SelectItem key="feed">{t('advertising.ad.placement.feed', 'Feed')}</SelectItem>
                  <SelectItem key="discovery">{t('advertising.ad.placement.discovery', 'Discovery')}</SelectItem>
                  <SelectItem key="markt">{t('advertising.ad.placement.market', 'Market')}</SelectItem>
                  <SelectItem key="all">{t('advertising.ad.placement.all', 'All placements')}</SelectItem>
                </Select>

                <Input
                  label={t('advertising.ad.fields.total_budget_cents', 'Total budget (in cents)')}
                  placeholder={t('advertising.ad.placeholders.budget', 'e.g. 5000 = EUR 50.00')}
                  value={createForm.budget_cents}
                  onValueChange={(v) => setCreateForm((f) => ({ ...f, budget_cents: v }))}
                  type="number"
                  min="0"
                  variant="bordered"
                  description={t('advertising.ad.budget_description', 'Enter 0 for unlimited budget')}
                />

                <div className="grid grid-cols-2 gap-3">
                  <Input
                    label={t('advertising.ad.fields.start_date', 'Start date')}
                    type="date"
                    value={createForm.start_date}
                    onValueChange={(v) => setCreateForm((f) => ({ ...f, start_date: v }))}
                    variant="bordered"
                  />
                  <Input
                    label={t('advertising.ad.fields.end_date', 'End date')}
                    type="date"
                    value={createForm.end_date}
                    onValueChange={(v) => setCreateForm((f) => ({ ...f, end_date: v }))}
                    variant="bordered"
                  />
                </div>

                <Textarea
                  label={t('advertising.ad.fields.audience_filters', 'Audience filters (JSON, optional)')}
                  placeholder={`{"radius_km": 5, "lat": 47.1758, "lng": 8.4622, "interests": ["gardening"]}`}
                  value={createForm.audience_filters}
                  onValueChange={(v) => setCreateForm((f) => ({ ...f, audience_filters: v }))}
                  variant="bordered"
                  minRows={3}
                  description={t('advertising.ad.audience_description', 'Leave blank to target all community members')}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={() => { setCreateOpen(false); setCreateForm(EMPTY_FORM); }}>
                  {t('advertising.shared.actions.cancel', 'Cancel')}
                </Button>
                <Button
                  color="primary"
                  onPress={handleCreateSubmit}
                  isLoading={createLoading}
                  isDisabled={!createForm.name.trim()}
                >
                  {t('advertising.shared.actions.submit_for_review', 'Submit for review')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default AdCampaignAdminPage;
