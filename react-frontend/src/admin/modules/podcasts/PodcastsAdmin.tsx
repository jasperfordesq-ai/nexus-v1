// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Podcasts — tenant-scoped show and episode moderation for the Podcasts module.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import CheckCircle from 'lucide-react/icons/circle-check-big';
import Flag from 'lucide-react/icons/flag';
import Podcast from 'lucide-react/icons/podcast';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import XCircle from 'lucide-react/icons/circle-x';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import type { PodcastEpisode, PodcastModerationStatus, PodcastShow, PodcastStatus } from '@/lib/api/podcasts';
import {
  Button,
  Card,
  CardBody,
  Chip,
  Spinner,
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
import { PageHeader } from '../../components';

type ModerationFilter = 'all' | PodcastModerationStatus;
type ModerationAction = 'approve' | 'reject' | 'flag';

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
}

interface PodcastAdminIndex {
  shows: PodcastShow[];
  episodes: PodcastEpisode[];
  stats: PodcastAdminStats;
  top_episodes: PodcastEpisode[];
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

function formatDate(value?: string | null): string {
  if (!value) return '';
  return new Date(value).toLocaleDateString();
}

export default function PodcastsAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle(t('podcasts_admin.title'));
  const toast = useToast();

  const [data, setData] = useState<PodcastAdminIndex | null>(null);
  const [filter, setFilter] = useState<ModerationFilter>('all');
  const [loading, setLoading] = useState(true);
  const [actionKey, setActionKey] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const suffix = filter === 'all' ? '' : `?moderation_status=${encodeURIComponent(filter)}`;
      const res = await api.get<PodcastAdminIndex>(`/v2/admin/podcasts${suffix}`);
      if (res.success && res.data) {
        setData(res.data);
      } else {
        toast.error(t('podcasts_admin.load_failed'));
      }
    } catch {
      toast.error(t('podcasts_admin.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [filter, t, toast]);

  useEffect(() => {
    load();
  }, [load]);

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
    ];
  }, [data, t]);

  const moderate = async (type: 'show' | 'episode', id: number, action: ModerationAction) => {
    const key = `${type}:${id}:${action}`;
    setActionKey(key);
    try {
      const endpoint = type === 'show'
        ? `/v2/admin/podcasts/shows/${id}/moderate`
        : `/v2/admin/podcasts/episodes/${id}/moderate`;
      const res = await api.post(endpoint, { action });
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

  const actionButtons = (type: 'show' | 'episode', id: number) => (
    <div className="flex items-center gap-1">
      <Tooltip content={t('podcasts_admin.actions.approve')}>
        <Button
          isIconOnly
          size="sm"
          variant="tertiary"
          color="success"
          aria-label={t('podcasts_admin.actions.approve')}
          isLoading={actionKey === `${type}:${id}:approve`}
          onPress={() => moderate(type, id, 'approve')}
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
          isLoading={actionKey === `${type}:${id}:reject`}
          onPress={() => moderate(type, id, 'reject')}
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
          isLoading={actionKey === `${type}:${id}:flag`}
          onPress={() => moderate(type, id, 'flag')}
        >
          <Flag size={16} aria-hidden="true" />
        </Button>
      </Tooltip>
    </div>
  );

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

      {loading && !data ? (
        <div className="flex justify-center py-16" role="status" aria-busy="true">
          <Spinner size="lg" />
        </div>
      ) : (
        <div className="space-y-8">
          <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-9">
            {stats.map((stat) => (
              <Card key={stat.key}>
                <CardBody className="p-4">
                  <div className="text-2xl font-semibold text-foreground">{stat.value}</div>
                  <div className="text-xs text-muted">{stat.label}</div>
                </CardBody>
              </Card>
            ))}
          </div>

          <section>
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold">{t('podcasts_admin.sections.top_episodes')}</h2>
              <span className="text-sm text-muted">{t('podcasts_admin.count', { count: data?.top_episodes.length ?? 0 })}</span>
            </div>
            {data?.top_episodes.length ? (
              <Table aria-label={t('podcasts_admin.sections.top_episodes')}>
                <TableHeader>
                  <TableColumn>{t('podcasts_admin.columns.title')}</TableColumn>
                  <TableColumn>{t('podcasts_admin.columns.show')}</TableColumn>
                  <TableColumn>{t('podcasts_admin.columns.listens')}</TableColumn>
                </TableHeader>
                <TableBody>
                  {data.top_episodes.map((episode) => (
                    <TableRow key={episode.id}>
                      <TableCell>{episode.title}</TableCell>
                      <TableCell>{episode.show?.title ?? t('podcasts_admin.empty_value')}</TableCell>
                      <TableCell>{episode.listen_count}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : (
              <p className="py-6 text-sm text-muted">{t('podcasts_admin.empty.top_episodes')}</p>
            )}
          </section>

          <Tabs
            aria-label={t('podcasts_admin.filters.label')}
            selectedKey={filter}
            onSelectionChange={(key) => setFilter(String(key) as ModerationFilter)}
          >
            {FILTERS.map((item) => (
              <Tab key={item} title={t(`podcasts_admin.filters.${item}`)} />
            ))}
          </Tabs>

          <section>
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold">{t('podcasts_admin.sections.shows')}</h2>
              <span className="text-sm text-muted">{t('podcasts_admin.count', { count: data?.shows.length ?? 0 })}</span>
            </div>
            {data?.shows.length ? (
              <Table aria-label={t('podcasts_admin.sections.shows')}>
                <TableHeader>
                  <TableColumn>{t('podcasts_admin.columns.title')}</TableColumn>
                  <TableColumn>{t('podcasts_admin.columns.owner')}</TableColumn>
                  <TableColumn>{t('podcasts_admin.columns.status')}</TableColumn>
                  <TableColumn>{t('podcasts_admin.columns.moderation')}</TableColumn>
                  <TableColumn>{t('podcasts_admin.columns.updated')}</TableColumn>
                  <TableColumn align="end">{t('podcasts_admin.columns.actions')}</TableColumn>
                </TableHeader>
                <TableBody>
                  {data.shows.map((show) => (
                    <TableRow key={show.id}>
                      <TableCell>
                        <div className="min-w-0">
                          <div className="truncate font-medium text-foreground">{show.title}</div>
                          <div className="truncate text-xs text-muted">{show.summary || t('podcasts_admin.empty_value')}</div>
                        </div>
                      </TableCell>
                      <TableCell>{show.owner?.name ?? t('podcasts_admin.empty_value')}</TableCell>
                      <TableCell>{statusChip(show.status)}</TableCell>
                      <TableCell>{moderationChip(show.moderation_status)}</TableCell>
                      <TableCell>{formatDate(show.updated_at) || t('podcasts_admin.empty_value')}</TableCell>
                      <TableCell>{actionButtons('show', show.id)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : (
              <p className="py-6 text-sm text-muted">{t('podcasts_admin.empty.shows')}</p>
            )}
          </section>

          <section>
            <div className="mb-3 flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold">{t('podcasts_admin.sections.episodes')}</h2>
              <span className="text-sm text-muted">{t('podcasts_admin.count', { count: data?.episodes.length ?? 0 })}</span>
            </div>
            {data?.episodes.length ? (
              <Table aria-label={t('podcasts_admin.sections.episodes')}>
                <TableHeader>
                  <TableColumn>{t('podcasts_admin.columns.title')}</TableColumn>
                  <TableColumn>{t('podcasts_admin.columns.show')}</TableColumn>
                  <TableColumn>{t('podcasts_admin.columns.author')}</TableColumn>
                  <TableColumn>{t('podcasts_admin.columns.status')}</TableColumn>
                  <TableColumn>{t('podcasts_admin.columns.moderation')}</TableColumn>
                  <TableColumn align="end">{t('podcasts_admin.columns.actions')}</TableColumn>
                </TableHeader>
                <TableBody>
                  {data.episodes.map((episode) => (
                    <TableRow key={episode.id}>
                      <TableCell>
                        <div className="min-w-0">
                          <div className="truncate font-medium text-foreground">{episode.title}</div>
                          <div className="truncate text-xs text-muted">{episode.summary || t('podcasts_admin.empty_value')}</div>
                        </div>
                      </TableCell>
                      <TableCell>{episode.show?.title ?? t('podcasts_admin.empty_value')}</TableCell>
                      <TableCell>{episode.author?.name ?? t('podcasts_admin.empty_value')}</TableCell>
                      <TableCell>{statusChip(episode.status)}</TableCell>
                      <TableCell>{moderationChip(episode.moderation_status)}</TableCell>
                      <TableCell>{actionButtons('episode', episode.id)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : (
              <p className="py-6 text-sm text-muted">{t('podcasts_admin.empty.episodes')}</p>
            )}
          </section>
        </div>
      )}
    </div>
  );
}
