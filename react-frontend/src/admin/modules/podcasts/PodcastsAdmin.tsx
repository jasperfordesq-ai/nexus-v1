// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Podcasts — tenant-scoped show and episode moderation for the Podcasts module.
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';

import ArrowUp from 'lucide-react/icons/arrow-up';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Flag from 'lucide-react/icons/flag';
import HardDrive from 'lucide-react/icons/hard-drive';
import Podcast from 'lucide-react/icons/podcast';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Rss from 'lucide-react/icons/rss';
import ShieldCheck from 'lucide-react/icons/shield-check';
import XCircle from 'lucide-react/icons/circle-x';
import Eye from 'lucide-react/icons/eye';
import Search from 'lucide-react/icons/search';
import { usePageTitle } from '@/hooks';
import { feedIssueKey } from '@/lib/podcasts/feedIssues';
import { useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { podcastsApi, type PodcastEpisode, type PodcastModerationStatus, type PodcastShow, type PodcastStatus } from '@/lib/api/podcasts';
import {
  Button,
  Card,
  CardBody,
  Chip,
  Spinner,
  SearchField,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Textarea,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Tabs,
  Tooltip,
} from '@/components/ui';
import { PageHeader } from '../../components/PageHeader';
import { DataTable, type Column } from '../../components/DataTable';
import { BulkActionToolbar, type BulkAction } from '../../components/BulkActionToolbar';

type ModerationFilter = 'all' | PodcastModerationStatus;
type ModerationAction = 'approve' | 'reject' | 'flag';

const PAGE_SIZE = 20;

interface PodcastAdminStats {
  total_shows: number;
  published_shows: number;
  pending_shows: number;
  total_episodes: number;
  published_episodes: number;
  pending_episodes: number;
  total_listens: number;
  completed_listens: number;
  completion_rate: number;
  unique_listeners: number;
  open_reports: number;
  subscribers: number;
  pending_media_scans: number;
  media_scan_unavailable: number;
  pending_media_processing: number;
  failed_media_processing?: number;
  infected_media?: number;
  rss_ready_shows?: number;
}

interface PodcastReport {
  id: number;
  episode_id: number;
  reporter_user_id: number;
  episode_title?: string | null;
  episode_slug?: string | null;
  show_title?: string | null;
  show_slug?: string | null;
  reporter_name?: string | null;
  reason: string;
  details?: string | null;
  status: string;
  created_at?: string | null;
}

interface FeedValidationResult {
  showId: number;
  showTitle: string;
  valid: boolean;
  errors: string[];
  warnings: string[];
}

interface PodcastBreakdown {
  client?: string;
  bucket?: string;
  listens: number;
}

interface PodcastAdminIndex {
  shows: PodcastShow[];
  episodes: PodcastEpisode[];
  stats: PodcastAdminStats;
  top_episodes: PodcastEpisode[];
  reports: PodcastReport[];
  client_breakdown: PodcastBreakdown[];
  retention: PodcastBreakdown[];
}

const FILTERS: ModerationFilter[] = ['all', 'pending', 'approved', 'rejected', 'flagged'];

const moderationColors: Record<PodcastModerationStatus, 'success' | 'warning' | 'danger' | 'default'> = {
  approved: 'success',
  pending: 'warning',
  rejected: 'danger',
  flagged: 'danger',
};

const statusColors: Record<PodcastStatus, 'success' | 'warning' | 'default'> = {
  published: 'success',
  draft: 'warning',
  archived: 'default',
};

const analyticsTableClassNames = { table: 'min-w-[24rem]' };
const mediumTableClassNames = { table: 'min-w-[42rem]' };
const wideTableClassNames = { table: 'min-w-[64rem]' };

function formatDate(value?: string | null): string {
  if (!value) return '';
  return new Date(value).toLocaleDateString(getFormattingLocale());
}

function formatDuration(seconds: number): string {
  const safe = Math.max(0, Math.floor(seconds));
  return `${Math.floor(safe / 60)}:${String(safe % 60).padStart(2, '0')}`;
}


export default function PodcastsAdmin() {
  const { t } = useTranslation('admin_podcasts');
  usePageTitle(t('podcasts_admin.title'));
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [data, setData] = useState<PodcastAdminIndex | null>(null);
  const [filter, setFilter] = useState<ModerationFilter>('all');
  const [loading, setLoading] = useState(true);
  const [actionKey, setActionKey] = useState<string | null>(null);
  const [feedValidation, setFeedValidation] = useState<FeedValidationResult | null>(null);
  const [showsPage, setShowsPage] = useState(1);
  const [episodesPage, setEpisodesPage] = useState(1);
  const [totals, setTotals] = useState<{ shows: number; episodes: number }>({ shows: 0, episodes: 0 });
  const [selectedShowIds, setSelectedShowIds] = useState<Set<string>>(new Set());
  const [selectedEpisodeIds, setSelectedEpisodeIds] = useState<Set<string>>(new Set());
  const [bulkLoading, setBulkLoading] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const requestIdRef = useRef(0);
  const requestAbortRef = useRef<AbortController | null>(null);
  const [reviewItem, setReviewItem] = useState<{ type: 'show'; item: PodcastShow } | { type: 'episode'; item: PodcastEpisode } | null>(null);
  const [moderationNotes, setModerationNotes] = useState('');

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(searchTerm.trim()), 350);
    return () => window.clearTimeout(timer);
  }, [searchTerm]);

  const load = useCallback(async () => {
    requestAbortRef.current?.abort();
    const controller = new AbortController();
    requestAbortRef.current = controller;
    const requestId = ++requestIdRef.current;
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (filter !== 'all') params.set('moderation_status', filter);
      params.set('shows_page', String(showsPage));
      params.set('episodes_page', String(episodesPage));
      params.set('per_page', String(PAGE_SIZE));
      if (debouncedSearch) params.set('q', debouncedSearch);
      const res = await api.get<PodcastAdminIndex>(`/v2/admin/podcasts?${params.toString()}`, { signal: controller.signal });
      if (requestId !== requestIdRef.current) return;
      if (res.success && res.data) {
        setData(res.data);
        // Server-side pagination totals; fall back to row counts when the
        // meta is absent so the tables degrade to single-page mode.
        const meta = res.meta as { shows_total?: number; episodes_total?: number } | undefined;
        setTotals({
          shows: meta?.shows_total ?? res.data.shows.length,
          episodes: meta?.episodes_total ?? res.data.episodes.length,
        });
      } else {
        toast.error(t('podcasts_admin.load_failed'));
      }
    } catch {
      if (controller.signal.aborted) return;
      toast.error(t('podcasts_admin.load_failed'));
    } finally {
      if (requestId === requestIdRef.current) setLoading(false);
    }
  }, [filter, showsPage, episodesPage, debouncedSearch, t, toast]);

  useEffect(() => {
    void load();
    return () => requestAbortRef.current?.abort();
  }, [load]);

  useEffect(() => {
    setShowsPage(1);
    setEpisodesPage(1);
  }, [debouncedSearch]);

  const stats = useMemo(() => {
    if (!data) return [];
    return [
      { key: 'total_shows', label: t('podcasts_admin.stats.total_shows'), value: data.stats.total_shows },
      { key: 'published_shows', label: t('podcasts_admin.stats.published_shows'), value: data.stats.published_shows },
      { key: 'pending_shows', label: t('podcasts_admin.stats.pending_shows'), value: data.stats.pending_shows },
      { key: 'total_episodes', label: t('podcasts_admin.stats.total_episodes'), value: data.stats.total_episodes },
      { key: 'published_episodes', label: t('podcasts_admin.stats.published_episodes'), value: data.stats.published_episodes },
      { key: 'pending_episodes', label: t('podcasts_admin.stats.pending_episodes'), value: data.stats.pending_episodes },
      { key: 'total_listens', label: t('podcasts_admin.stats.total_listens'), value: data.stats.total_listens },
      { key: 'completed_listens', label: t('podcasts_admin.stats.completed_listens'), value: data.stats.completed_listens },
      { key: 'completion_rate', label: t('podcasts_admin.stats.completion_rate'), value: t('podcasts_admin.stats.percent', { value: data.stats.completion_rate }) },
      { key: 'unique_listeners', label: t('podcasts_admin.stats.unique_listeners'), value: data.stats.unique_listeners },
      { key: 'open_reports', label: t('podcasts_admin.stats.open_reports'), value: data.stats.open_reports },
      { key: 'subscribers', label: t('podcasts_admin.stats.subscribers'), value: data.stats.subscribers },
      { key: 'pending_media_scans', label: t('podcasts_admin.stats.pending_media_scans'), value: data.stats.pending_media_scans },
      { key: 'media_scan_unavailable', label: t('podcasts_admin.stats.media_scan_unavailable'), value: data.stats.media_scan_unavailable },
      { key: 'pending_media_processing', label: t('podcasts_admin.stats.pending_media_processing'), value: data.stats.pending_media_processing },
    ];
  }, [data, t]);

  const readiness = useMemo(() => {
    if (!data) return [];

    return [
      {
        key: 'moderation',
        icon: ShieldCheck,
        label: t('podcasts_admin.readiness.moderation'),
        value: data.stats.pending_shows + data.stats.pending_episodes,
        detail: t('podcasts_admin.readiness.pending_items', {
          count: data.stats.pending_shows + data.stats.pending_episodes,
        }),
        color: data.stats.pending_shows + data.stats.pending_episodes > 0 ? 'warning' : 'success',
      },
      {
        key: 'reports',
        icon: Flag,
        label: t('podcasts_admin.readiness.reports'),
        value: data.stats.open_reports,
        detail: t('podcasts_admin.readiness.open_reports', { count: data.stats.open_reports }),
        color: data.stats.open_reports > 0 ? 'danger' : 'success',
      },
      {
        key: 'media',
        icon: HardDrive,
        label: t('podcasts_admin.readiness.media'),
        value: data.stats.pending_media_scans + data.stats.media_scan_unavailable + data.stats.pending_media_processing + (data.stats.failed_media_processing ?? 0) + (data.stats.infected_media ?? 0),
        detail: t('podcasts_admin.readiness.media_jobs', {
          count: data.stats.pending_media_scans + data.stats.media_scan_unavailable + data.stats.pending_media_processing + (data.stats.failed_media_processing ?? 0) + (data.stats.infected_media ?? 0),
        }),
        color: data.stats.pending_media_scans + data.stats.media_scan_unavailable + data.stats.pending_media_processing + (data.stats.failed_media_processing ?? 0) + (data.stats.infected_media ?? 0) > 0 ? 'warning' : 'success',
      },
      {
        key: 'rss',
        icon: Rss,
        label: t('podcasts_admin.readiness.rss'),
        value: data.stats.rss_ready_shows ?? 0,
        detail: t('podcasts_admin.readiness.rss_ready', { count: data.stats.rss_ready_shows ?? 0 }),
        color: (data.stats.rss_ready_shows ?? 0) > 0 ? 'success' : 'default',
      },
    ] as const;
  }, [data, t]);

  const moderate = async (type: 'show' | 'episode', id: number, action: ModerationAction, notes?: string) => {
    const key = `${type}:${id}:${action}`;
    setActionKey(key);
    try {
      const endpoint = type === 'show'
        ? `/v2/admin/podcasts/shows/${id}/moderate`
        : `/v2/admin/podcasts/episodes/${id}/moderate`;
      const trimmedNotes = notes?.trim();
      const res = await api.post(endpoint, trimmedNotes ? { action, notes: trimmedNotes } : { action });
      if (res.success) {
        toast.success(t(`podcasts_admin.toasts.${type}_${action}`));
        load();
      } else {
        toast.error(res.error || t('podcasts_admin.action_failed'));
      }
    } catch {
      toast.error(t('podcasts_admin.action_failed'));
    } finally {
      setActionKey(null);
    }
  };

  const openReview = (type: 'show' | 'episode', item: PodcastShow | PodcastEpisode): void => {
    setModerationNotes(item.moderation_notes ?? '');
    setReviewItem(type === 'show'
      ? { type, item: item as PodcastShow }
      : { type, item: item as PodcastEpisode });
  };

  const submitReview = (action: ModerationAction): void => {
    if (!reviewItem) return;
    void moderate(reviewItem.type, reviewItem.item.id, action, moderationNotes);
    setReviewItem(null);
    setModerationNotes('');
  };

  const resolveReport = async (reportId: number, status: 'resolved' | 'dismissed' | 'escalated') => {
    const key = `report:${reportId}:${status}`;
    setActionKey(key);
    try {
      const res = await podcastsApi.resolveReport(reportId, status);
      if (res.success) {
        toast.success(t(`podcasts_admin.toasts.report_${status}`));
        load();
      } else {
        toast.error(res.error || t('podcasts_admin.action_failed'));
      }
    } catch {
      toast.error(t('podcasts_admin.action_failed'));
    } finally {
      setActionKey(null);
    }
  };

  const bulkModerate = async (type: 'show' | 'episode', ids: string[], action: 'approve' | 'reject') => {
    if (ids.length === 0) return;
    setBulkLoading(true);
    let ok = 0;
    let failed = 0;
    // Fan out per-id moderate calls in small chunks — no bulk endpoint exists
    // yet, and unbounded parallelism would trip the write rate limiter.
    const chunkSize = 5;
    for (let i = 0; i < ids.length; i += chunkSize) {
      const chunk = ids.slice(i, i + chunkSize);
      const results = await Promise.allSettled(chunk.map((id) => api.post(
        type === 'show'
          ? `/v2/admin/podcasts/shows/${id}/moderate`
          : `/v2/admin/podcasts/episodes/${id}/moderate`,
        { action },
      )));
      for (const result of results) {
        if (result.status === 'fulfilled' && result.value.success) {
          ok++;
        } else {
          failed++;
        }
      }
    }
    setBulkLoading(false);
    if (failed > 0) {
      toast.error(t('podcasts_admin.bulk.partial', { ok, failed }));
    } else {
      toast.success(t('podcasts_admin.bulk.done', { count: ok }));
    }
    if (type === 'show') {
      setSelectedShowIds(new Set());
    } else {
      setSelectedEpisodeIds(new Set());
    }
    load();
  };

  const bulkActionsFor = (type: 'show' | 'episode', selected: Set<string>): BulkAction[] => [
    {
      key: 'approve',
      label: t('podcasts_admin.bulk.approve_selected'),
      color: 'success',
      onConfirm: () => bulkModerate(type, Array.from(selected), 'approve'),
    },
    {
      key: 'reject',
      label: t('podcasts_admin.bulk.reject_selected'),
      color: 'danger',
      destructive: true,
      confirmTitle: t('podcasts_admin.bulk.confirm_reject_title'),
      confirmMessage: t('podcasts_admin.bulk.confirm_reject_message', { count: selected.size }),
      onConfirm: () => bulkModerate(type, Array.from(selected), 'reject'),
    },
  ];

  const validateFeed = async (show: PodcastShow) => {
    const key = `feed:${show.id}`;
    setActionKey(key);
    try {
      const res = await podcastsApi.validateFeed(show.id);
      if (res.success && res.data) {
        setFeedValidation({
          showId: show.id,
          showTitle: show.title,
          valid: res.data.valid,
          errors: res.data.errors,
          warnings: res.data.warnings,
        });
        if (res.data.valid) {
          toast.success(t('podcasts_admin.toasts.feed_valid'));
        } else {
          toast.error(t('podcasts_admin.toasts.feed_invalid', { count: res.data.errors.length }));
        }
      } else {
        toast.error(res.error || t('podcasts_admin.action_failed'));
      }
    } catch {
      toast.error(t('podcasts_admin.action_failed'));
    } finally {
      setActionKey(null);
    }
  };

  const moderationChip = (status: PodcastModerationStatus) => (
    <Chip size="sm" variant="soft" color={moderationColors[status] ?? 'default'}>
      {t(`podcasts_admin.moderation.${status}`)}
    </Chip>
  );

  const statusChip = (status: PodcastStatus) => (
    <Chip size="sm" variant="soft" color={statusColors[status] ?? 'default'}>
      {t(`podcasts_admin.status.${status}`)}
    </Chip>
  );

  const reviewState = (group: 'visibility' | 'media_scan' | 'media_processing' | 'report_status', value?: string | null) => {
    if (!value) return t('podcasts_admin.empty_value');
    return t(`podcasts_admin.review_values.${group}.${value}`, {
      defaultValue: t('common.unknown'),
    });
  };

  const actionButtons = (type: 'show' | 'episode', item: PodcastShow | PodcastEpisode) => (
    <div className="flex min-w-[7.5rem] items-center justify-end gap-1">
      <Tooltip content={t('podcasts_admin.actions.inspect')}>
        <Button
          isIconOnly
          size="sm"
          variant="tertiary"
          aria-label={t('podcasts_admin.actions.inspect')}
          isDisabled={actionKey !== null || bulkLoading}
          onPress={() => openReview(type, item)}
        >
          <Eye size={16} aria-hidden="true" />
        </Button>
      </Tooltip>
      <Tooltip content={t('podcasts_admin.actions.approve')}>
        <Button
          isIconOnly
          size="sm"
          variant="tertiary"
          color="success"
          aria-label={t('podcasts_admin.actions.approve')}
          isLoading={actionKey === `${type}:${item.id}:approve`}
          isDisabled={actionKey !== null || bulkLoading}
          onPress={() => openReview(type, item)}
        >
          <CheckCircle size={16} aria-hidden="true" />
        </Button>
      </Tooltip>
      <Tooltip content={t('podcasts_admin.actions.reject')}>
        <Button
          isIconOnly
          size="sm"
          variant="tertiary"
          color="danger"
          aria-label={t('podcasts_admin.actions.reject')}
          isLoading={actionKey === `${type}:${item.id}:reject`}
          isDisabled={actionKey !== null || bulkLoading}
          onPress={() => openReview(type, item)}
        >
          <XCircle size={16} aria-hidden="true" />
        </Button>
      </Tooltip>
      <Tooltip content={t('podcasts_admin.actions.flag')}>
        <Button
          isIconOnly
          size="sm"
          variant="tertiary"
          aria-label={t('podcasts_admin.actions.flag')}
          isLoading={actionKey === `${type}:${item.id}:flag`}
          isDisabled={actionKey !== null || bulkLoading}
          onPress={() => openReview(type, item)}
        >
          <Flag size={16} aria-hidden="true" />
        </Button>
      </Tooltip>
    </div>
  );

  const reportButtons = (reportId: number) => (
    <div className="flex min-w-[7.5rem] items-center justify-end gap-1">
      <Tooltip content={t('podcasts_admin.actions.resolve_report')}>
        <Button
          isIconOnly
          size="sm"
          variant="tertiary"
          color="success"
          aria-label={t('podcasts_admin.actions.resolve_report')}
          isLoading={actionKey === `report:${reportId}:resolved`}
          isDisabled={actionKey !== null || bulkLoading}
          onPress={() => resolveReport(reportId, 'resolved')}
        >
          <CheckCircle size={16} aria-hidden="true" />
        </Button>
      </Tooltip>
      <Tooltip content={t('podcasts_admin.actions.dismiss_report')}>
        <Button
          isIconOnly
          size="sm"
          variant="tertiary"
          aria-label={t('podcasts_admin.actions.dismiss_report')}
          isLoading={actionKey === `report:${reportId}:dismissed`}
          isDisabled={actionKey !== null || bulkLoading}
          onPress={() => resolveReport(reportId, 'dismissed')}
        >
          <XCircle size={16} aria-hidden="true" />
        </Button>
      </Tooltip>
      <Tooltip content={t('podcasts_admin.actions.escalate_report')}>
        <Button
          isIconOnly
          size="sm"
          variant="tertiary"
          color="warning"
          aria-label={t('podcasts_admin.actions.escalate_report')}
          isLoading={actionKey === `report:${reportId}:escalated`}
          isDisabled={actionKey !== null || bulkLoading}
          onPress={() => resolveReport(reportId, 'escalated')}
        >
          <ArrowUp size={16} aria-hidden="true" />
        </Button>
      </Tooltip>
    </div>
  );

  const showColumns: Column<PodcastShow>[] = [
    {
      key: 'title',
      label: t('podcasts_admin.columns.title'),
      isRowHeader: true,
      render: (show) => (
        <div className="min-w-0 max-w-[22rem]">
          <div className="truncate font-medium text-foreground">{show.title}</div>
          <div className="truncate text-xs text-muted">{show.summary || t('podcasts_admin.empty_value')}</div>
        </div>
      ),
    },
    { key: 'owner', label: t('podcasts_admin.columns.owner'), render: (show) => show.owner?.name ?? t('podcasts_admin.empty_value') },
    { key: 'status', label: t('podcasts_admin.columns.status'), render: (show) => statusChip(show.status) },
    { key: 'moderation', label: t('podcasts_admin.columns.moderation'), render: (show) => moderationChip(show.moderation_status) },
    { key: 'updated', label: t('podcasts_admin.columns.updated'), render: (show) => formatDate(show.updated_at) || t('podcasts_admin.empty_value') },
    {
      key: 'actions',
      label: t('podcasts_admin.columns.actions'),
      render: (show) => (
        <div className="flex items-center justify-end gap-1">
          <Tooltip content={t('podcasts_admin.actions.validate_feed')}>
            <Button
              isIconOnly
              size="sm"
              variant="tertiary"
              aria-label={t('podcasts_admin.actions.validate_feed')}
              isLoading={actionKey === `feed:${show.id}`}
              isDisabled={actionKey !== null || bulkLoading}
              onPress={() => validateFeed(show)}
            >
              <Rss size={16} aria-hidden="true" />
            </Button>
          </Tooltip>
          {actionButtons('show', show)}
        </div>
      ),
    },
  ];

  const episodeColumns: Column<PodcastEpisode>[] = [
    {
      key: 'title',
      label: t('podcasts_admin.columns.title'),
      isRowHeader: true,
      render: (episode) => (
        <div className="min-w-0 max-w-[22rem]">
          <div className="truncate font-medium text-foreground">{episode.title}</div>
          <div className="truncate text-xs text-muted">{episode.summary || t('podcasts_admin.empty_value')}</div>
        </div>
      ),
    },
    { key: 'show', label: t('podcasts_admin.columns.show'), render: (episode) => <div className="max-w-[18rem] truncate">{episode.show?.title ?? t('podcasts_admin.empty_value')}</div> },
    { key: 'author', label: t('podcasts_admin.columns.author'), render: (episode) => <div className="max-w-[16rem] truncate">{episode.author?.name ?? t('podcasts_admin.empty_value')}</div> },
    { key: 'status', label: t('podcasts_admin.columns.status'), render: (episode) => statusChip(episode.status) },
    { key: 'moderation', label: t('podcasts_admin.columns.moderation'), render: (episode) => moderationChip(episode.moderation_status) },
    { key: 'actions', label: t('podcasts_admin.columns.actions'), render: (episode) => actionButtons('episode', episode) },
  ];

  return (
    <div className="mx-auto max-w-7xl px-4 pb-8">
      <PageHeader
        title={t('podcasts_admin.title')}
        description={t('podcasts_admin.description')}
        icon={<Podcast size={24} />}
        actions={
          <Button
            size="sm"
            variant="tertiary"
            startContent={<RefreshCw size={16} aria-hidden="true" />}
            onPress={load}
            isLoading={loading}
          >
            {t('podcasts_admin.actions.refresh')}
          </Button>
        }
      />

      <div className="mb-5 max-w-xl">
        <SearchField
          value={searchTerm}
          onValueChange={setSearchTerm}
          onClear={() => setSearchTerm('')}
          isClearable
          aria-label={t('podcasts_admin.search.label')}
          placeholder={t('podcasts_admin.search.placeholder')}
          startContent={<Search size={16} className="text-muted" aria-hidden="true" />}
        />
      </div>

      {loading && !data ? (
        <div className="flex justify-center py-16" role="status" aria-busy="true">
          <Spinner size="lg" />
        </div>
      ) : (
        <div className="space-y-8">
          <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-7">
            {stats.map((stat) => (
              <Card key={stat.key}>
                <CardBody className="p-4">
                  <div className="text-2xl font-semibold text-foreground">{stat.value}</div>
                  <div className="text-xs text-muted">{stat.label}</div>
                </CardBody>
              </Card>
            ))}
          </div>

          <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            {readiness.map((item) => {
              const Icon = item.icon;
              return (
                <Card key={item.key}>
                  <CardBody className="flex flex-row items-start gap-3 p-4">
                    <div className="rounded-md bg-surface-secondary p-2 text-accent">
                      <Icon size={18} aria-hidden="true" />
                    </div>
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <p className="text-sm font-semibold text-foreground">{item.label}</p>
                        <Chip size="sm" variant="soft" color={item.color}>
                          {item.value}
                        </Chip>
                      </div>
                      <p className="mt-1 text-xs text-muted">{item.detail}</p>
                    </div>
                  </CardBody>
                </Card>
              );
            })}
          </section>

          {feedValidation && (
            <Card>
              <CardBody className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <h2 className="text-lg font-semibold">{t('podcasts_admin.feed_validation.title')}</h2>
                    <p className="text-sm text-muted">{t('podcasts_admin.feed_validation.subtitle', { title: feedValidation.showTitle })}</p>
                  </div>
                  <Chip size="sm" variant="soft" color={feedValidation.valid ? 'success' : 'danger'}>
                    {feedValidation.valid ? t('podcasts_admin.feed_validation.valid') : t('podcasts_admin.feed_validation.invalid')}
                  </Chip>
                </div>
                {feedValidation.errors.length > 0 && (
                  <div>
                    <p className="text-sm font-medium text-foreground">{t('podcasts_admin.feed_validation.errors')}</p>
                    <ul className="mt-1 list-disc space-y-1 pl-5 text-sm text-muted">
                      {feedValidation.errors.map((issue) => (
                        <li key={issue}>{t(`podcasts_admin.feed_issues.${feedIssueKey(issue)}`, { defaultValue: issue })}</li>
                      ))}
                    </ul>
                  </div>
                )}
                {feedValidation.warnings.length > 0 && (
                  <div>
                    <p className="text-sm font-medium text-foreground">{t('podcasts_admin.feed_validation.warnings')}</p>
                    <ul className="mt-1 list-disc space-y-1 pl-5 text-sm text-muted">
                      {feedValidation.warnings.map((issue) => (
                        <li key={issue}>{t(`podcasts_admin.feed_issues.${feedIssueKey(issue)}`, { defaultValue: issue })}</li>
                      ))}
                    </ul>
                  </div>
                )}
              </CardBody>
            </Card>
          )}

          <section>
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold">{t('podcasts_admin.sections.top_episodes')}</h2>
              <span className="text-sm text-muted">{t('podcasts_admin.count', { count: data?.top_episodes.length ?? 0 })}</span>
            </div>
            {data?.top_episodes.length ? (
              <Table aria-label={t('podcasts_admin.sections.top_episodes')} classNames={mediumTableClassNames}>
                <TableHeader>
                  <TableColumn className="min-w-[16rem]">{t('podcasts_admin.columns.title')}</TableColumn>
                  <TableColumn className="min-w-[14rem]">{t('podcasts_admin.columns.show')}</TableColumn>
                  <TableColumn align="end">{t('podcasts_admin.columns.listens')}</TableColumn>
                </TableHeader>
                <TableBody>
                  {data.top_episodes.map((episode) => (
                    <TableRow key={episode.id}>
                      <TableCell>
                        <div className="max-w-[24rem] truncate font-medium text-foreground">{episode.title}</div>
                      </TableCell>
                      <TableCell>
                        <div className="max-w-[20rem] truncate">{episode.show?.title ?? t('podcasts_admin.empty_value')}</div>
                      </TableCell>
                      <TableCell className="text-right tabular-nums">{episode.listen_count}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : (
              <p className="py-6 text-sm text-muted">{t('podcasts_admin.empty.top_episodes')}</p>
            )}
          </section>

          <section className="grid gap-6 lg:grid-cols-2">
            <div>
              <div className="mb-3 flex items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">{t('podcasts_admin.sections.client_breakdown')}</h2>
                <span className="text-sm text-muted">{t('podcasts_admin.count', { count: data?.client_breakdown.length ?? 0 })}</span>
              </div>
              {data?.client_breakdown.length ? (
                <Table aria-label={t('podcasts_admin.sections.client_breakdown')} classNames={analyticsTableClassNames}>
                  <TableHeader>
                    <TableColumn>{t('podcasts_admin.columns.client')}</TableColumn>
                    <TableColumn align="end">{t('podcasts_admin.columns.listens')}</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {data.client_breakdown.map((row) => (
                      <TableRow key={row.client}>
                        <TableCell>{row.client ?? t('podcasts_admin.empty_value')}</TableCell>
                        <TableCell className="text-right tabular-nums">{row.listens}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              ) : (
                <p className="py-6 text-sm text-muted">{t('podcasts_admin.empty.analytics')}</p>
              )}
            </div>

            <div>
              <div className="mb-3 flex items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">{t('podcasts_admin.sections.retention')}</h2>
                <span className="text-sm text-muted">{t('podcasts_admin.count', { count: data?.retention.length ?? 0 })}</span>
              </div>
              {data?.retention.length ? (
                <Table aria-label={t('podcasts_admin.sections.retention')} classNames={analyticsTableClassNames}>
                  <TableHeader>
                    <TableColumn>{t('podcasts_admin.columns.bucket')}</TableColumn>
                    <TableColumn align="end">{t('podcasts_admin.columns.listens')}</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {data.retention.map((row) => (
                      <TableRow key={row.bucket}>
                        <TableCell>{row.bucket ?? t('podcasts_admin.empty_value')}</TableCell>
                        <TableCell className="text-right tabular-nums">{row.listens}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              ) : (
                <p className="py-6 text-sm text-muted">{t('podcasts_admin.empty.analytics')}</p>
              )}
            </div>
          </section>

          <section>
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold">{t('podcasts_admin.sections.reports')}</h2>
              <span className="text-sm text-muted">{t('podcasts_admin.count', { count: data?.reports.length ?? 0 })}</span>
            </div>
            {data?.reports.length ? (
              <Table aria-label={t('podcasts_admin.sections.reports')} classNames={wideTableClassNames}>
                <TableHeader>
                  <TableColumn className="min-w-[18rem]">{t('podcasts_admin.columns.episode')}</TableColumn>
                  <TableColumn className="min-w-[11rem]">{t('podcasts_admin.columns.reason')}</TableColumn>
                  <TableColumn className="min-w-[18rem]">{t('podcasts_admin.columns.details')}</TableColumn>
                  <TableColumn className="min-w-[8rem]">{t('podcasts_admin.columns.updated')}</TableColumn>
                  <TableColumn align="end" className="min-w-[9rem]">{t('podcasts_admin.columns.actions')}</TableColumn>
                </TableHeader>
                <TableBody>
                  {data.reports.map((report) => (
                    <TableRow key={report.id}>
                      <TableCell>
                        <div className="min-w-0">
                          <div className="truncate font-medium text-foreground">
                            {report.show_slug && report.episode_slug ? (
                              <Link className="hover:text-accent" to={tenantPath(`/podcasts/${report.show_slug}/${report.episode_slug}`)}>
                                {report.episode_title ?? t('podcasts_admin.report_unknown_episode', { id: report.episode_id })}
                              </Link>
                            ) : report.episode_title ?? t('podcasts_admin.report_unknown_episode', { id: report.episode_id })}
                          </div>
                          <div className="truncate text-xs text-muted">
                            {report.reporter_name
                              ? t('podcasts_admin.report_meta', {
                                  show: report.show_title ?? t('podcasts_admin.empty_value'),
                                  reporter: t('podcasts_admin.reporter', { name: report.reporter_name }),
                                })
                              : report.show_title ?? t('podcasts_admin.empty_value')}
                          </div>
                        </div>
                      </TableCell>
                      <TableCell>{t(`podcasts_admin.report_reasons.${report.reason}`, { defaultValue: report.reason })}</TableCell>
                      <TableCell className="max-w-md whitespace-normal text-sm text-muted">{report.details || t('podcasts_admin.empty_value')}</TableCell>
                      <TableCell>{formatDate(report.created_at) || t('podcasts_admin.empty_value')}</TableCell>
                      <TableCell className="text-right">{reportButtons(report.id)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : (
              <p className="py-6 text-sm text-muted">{t('podcasts_admin.empty.reports')}</p>
            )}
          </section>

          <Tabs
            aria-label={t('podcasts_admin.filters.label')}
            selectedKey={filter}
            onSelectionChange={(key) => {
              setFilter(String(key) as ModerationFilter);
              setShowsPage(1);
              setEpisodesPage(1);
              setSelectedShowIds(new Set());
              setSelectedEpisodeIds(new Set());
            }}
          >
            {FILTERS.map((item) => (
              <Tab key={item} title={t(`podcasts_admin.filters.${item}`)} />
            ))}
          </Tabs>

          <section>
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold">{t('podcasts_admin.sections.shows')}</h2>
              <span className="text-sm text-muted">{t('podcasts_admin.count', { count: totals.shows })}</span>
            </div>
            <BulkActionToolbar
              selectedCount={selectedShowIds.size}
              actions={bulkActionsFor('show', selectedShowIds)}
              onClearSelection={() => setSelectedShowIds(new Set())}
              isLoading={bulkLoading}
            />
            <DataTable<PodcastShow>
              columns={showColumns}
              data={data?.shows ?? []}
              keyField="id"
              totalItems={totals.shows}
              page={showsPage}
              pageSize={PAGE_SIZE}
              onPageChange={setShowsPage}
              selectable
              selectedKeys={selectedShowIds}
              onSelectionChange={setSelectedShowIds}
              emptyContent={t('podcasts_admin.empty.shows')}
            />
          </section>

          <section>
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold">{t('podcasts_admin.sections.episodes')}</h2>
              <span className="text-sm text-muted">{t('podcasts_admin.count', { count: totals.episodes })}</span>
            </div>
            <BulkActionToolbar
              selectedCount={selectedEpisodeIds.size}
              actions={bulkActionsFor('episode', selectedEpisodeIds)}
              onClearSelection={() => setSelectedEpisodeIds(new Set())}
              isLoading={bulkLoading}
            />
            <DataTable<PodcastEpisode>
              columns={episodeColumns}
              data={data?.episodes ?? []}
              keyField="id"
              totalItems={totals.episodes}
              page={episodesPage}
              pageSize={PAGE_SIZE}
              onPageChange={setEpisodesPage}
              selectable
              selectedKeys={selectedEpisodeIds}
              onSelectionChange={setSelectedEpisodeIds}
              emptyContent={t('podcasts_admin.empty.episodes')}
            />
          </section>
        </div>
      )}

      <Modal isOpen={reviewItem !== null} onClose={() => setReviewItem(null)} size="2xl">
        <ModalContent>
          <ModalHeader>{t(`podcasts_admin.review.${reviewItem?.type ?? 'episode'}_title`)}</ModalHeader>
          <ModalBody className="gap-4">
            {reviewItem && (
              <>
                <div className="space-y-2 rounded-lg border border-border bg-surface-secondary/50 p-4">
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-lg font-semibold text-foreground">{reviewItem.item.title}</h3>
                    {statusChip(reviewItem.item.status)}
                    {moderationChip(reviewItem.item.moderation_status)}
                  </div>
                  <p className="whitespace-pre-line text-sm text-muted">
                    {reviewItem.item.description || reviewItem.item.summary || t('podcasts_admin.empty_value')}
                  </p>
                  {reviewItem.type === 'show' ? (
                    <dl className="grid gap-2 text-sm sm:grid-cols-2">
                      <div><dt className="font-medium">{t('podcasts_admin.columns.owner')}</dt><dd className="text-muted">{reviewItem.item.owner?.name ?? t('podcasts_admin.empty_value')}</dd></div>
                      <div><dt className="font-medium">{t('podcasts_admin.review.visibility')}</dt><dd className="text-muted">{reviewState('visibility', reviewItem.item.visibility)}</dd></div>
                      <div><dt className="font-medium">{t('podcasts_admin.review.language')}</dt><dd className="text-muted">{reviewItem.item.language}</dd></div>
                      <div><dt className="font-medium">{t('podcasts_admin.review.category')}</dt><dd className="text-muted">{reviewItem.item.category || t('podcasts_admin.empty_value')}</dd></div>
                    </dl>
                  ) : (
                    <div className="space-y-4">
                      <audio
                        className="w-full"
                        controls
                        controlsList="nodownload"
                        preload="metadata"
                        src={reviewItem.item.audio_url}
                      >
                        {t('podcasts_admin.review.audio_unsupported')}
                      </audio>
                      <dl className="grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt className="font-medium">{t('podcasts_admin.columns.show')}</dt><dd className="text-muted">{reviewItem.item.show?.title ?? t('podcasts_admin.empty_value')}</dd></div>
                        <div><dt className="font-medium">{t('podcasts_admin.columns.author')}</dt><dd className="text-muted">{reviewItem.item.author?.name ?? t('podcasts_admin.empty_value')}</dd></div>
                        <div><dt className="font-medium">{t('podcasts_admin.review.media_scan')}</dt><dd className="text-muted">{reviewState('media_scan', reviewItem.item.media_scan_status)}</dd></div>
                        <div><dt className="font-medium">{t('podcasts_admin.review.media_processing')}</dt><dd className="text-muted">{reviewState('media_processing', reviewItem.item.media_processing_status)}</dd></div>
                      </dl>
                      {reviewItem.item.transcript && (
                        <div>
                          <h4 className="mb-1 text-sm font-semibold">{t('podcasts_admin.review.transcript')}</h4>
                          <p className="max-h-64 overflow-auto whitespace-pre-line rounded-md bg-surface p-3 text-sm text-muted">{reviewItem.item.transcript}</p>
                        </div>
                      )}
                      {reviewItem.item.chapters && reviewItem.item.chapters.length > 0 && (
                        <div>
                          <h4 className="mb-1 text-sm font-semibold">{t('podcasts_admin.review.chapters')}</h4>
                          <ol className="max-h-48 space-y-1 overflow-auto rounded-md bg-surface p-3 text-sm text-muted">
                            {reviewItem.item.chapters.map((chapter, index) => (
                              <li key={`${chapter.starts_at_seconds}-${index}`} className="flex gap-3">
                                <span className="tabular-nums">{formatDuration(chapter.starts_at_seconds)}</span>
                                <span>{chapter.title}</span>
                              </li>
                            ))}
                          </ol>
                        </div>
                      )}
                      {reviewItem.item.report_history && reviewItem.item.report_history.length > 0 && (
                        <div>
                          <h4 className="mb-1 text-sm font-semibold">{t('podcasts_admin.review.report_history')}</h4>
                          <ul className="max-h-64 space-y-2 overflow-auto rounded-md bg-surface p-3 text-sm">
                            {reviewItem.item.report_history.map((report) => (
                              <li key={report.id} className="border-b border-border pb-2 last:border-0 last:pb-0">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                  <span className="font-medium">{t(`podcasts_admin.report_reasons.${report.reason}`, { defaultValue: report.reason })}</span>
                                  <Chip size="sm" variant="soft">{reviewState('report_status', report.status)}</Chip>
                                </div>
                                {report.reporter_name && <p className="text-xs text-muted">{t('podcasts_admin.reporter', { name: report.reporter_name })}</p>}
                                {report.details && <p className="mt-1 whitespace-pre-line text-muted">{report.details}</p>}
                                <p className="mt-1 text-xs text-muted">{formatDate(report.reviewed_at ?? report.created_at) || t('podcasts_admin.empty_value')}</p>
                              </li>
                            ))}
                          </ul>
                        </div>
                      )}
                    </div>
                  )}
                </div>
                <Textarea
                  label={t('podcasts_admin.review.notes')}
                  description={t('podcasts_admin.review.notes_hint')}
                  value={moderationNotes}
                  onValueChange={setModerationNotes}
                  maxLength={2000}
                />
              </>
            )}
          </ModalBody>
          <ModalFooter className="flex-wrap">
            <Button variant="tertiary" onPress={() => setReviewItem(null)}>{t('common.close')}</Button>
            <Button color="warning" variant="secondary" isDisabled={actionKey !== null} onPress={() => submitReview('flag')}>{t('podcasts_admin.actions.flag')}</Button>
            <Button color="danger" variant="secondary" isDisabled={actionKey !== null} onPress={() => submitReview('reject')}>{t('podcasts_admin.actions.reject')}</Button>
            <Button color="success" isDisabled={actionKey !== null} onPress={() => submitReview('approve')}>{t('podcasts_admin.actions.approve')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
