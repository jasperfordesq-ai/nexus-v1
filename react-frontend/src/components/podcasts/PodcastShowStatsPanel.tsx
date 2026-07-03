// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Creator-facing listen analytics for one show (studio sidebar).
 * Plain Tailwind bars only — Recharts is deliberately kept out of the
 * public bundle (it ships with the lazy-loaded admin app instead).
 */

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardBody, Spinner } from '@/components/ui';
import { podcastsApi, type PodcastShowStats } from '@/lib/api/podcasts';
import BarChart3 from 'lucide-react/icons/bar-chart-3';

interface PodcastShowStatsPanelProps {
  showId: number;
}

export function PodcastShowStatsPanel({ showId }: PodcastShowStatsPanelProps) {
  const { t } = useTranslation('podcasts');
  const [stats, setStats] = useState<PodcastShowStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    podcastsApi.showStats(showId)
      .then((res) => {
        if (!cancelled) setStats(res.success && res.data ? res.data : null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [showId]);

  // Analytics disabled for the tenant (or the request failed) — stay quiet.
  if (!loading && (!stats || !stats.enabled)) return null;

  const totals = stats?.totals;
  const topEpisodes = (stats?.top_episodes ?? []).slice(0, 5);
  const maxListens = Math.max(1, ...topEpisodes.map((episode) => episode.listen_count ?? 0));

  return (
    <Card>
      <CardBody className="space-y-3">
        <div className="flex items-center gap-2">
          <BarChart3 size={18} className="text-accent" aria-hidden="true" />
          <h2 className="text-lg font-semibold">{t('studio.stats.title')}</h2>
        </div>

        {loading ? (
          <div className="flex justify-center py-6" role="status" aria-busy="true">
            <Spinner size="sm" />
          </div>
        ) : (
          <>
            <dl className="grid grid-cols-2 gap-3">
              {[
                { key: 'listens', value: totals?.listens ?? 0 },
                { key: 'unique_listeners', value: totals?.unique_listeners ?? 0 },
                { key: 'completion_rate', value: `${totals?.completion_rate ?? 0}%` },
                { key: 'subscribers', value: totals?.subscribers ?? 0 },
              ].map((item) => (
                <div key={item.key} className="rounded-lg bg-surface-secondary/60 px-3 py-2">
                  <dt className="text-xs text-muted">{t(`studio.stats.${item.key}`)}</dt>
                  <dd className="text-lg font-semibold tabular-nums">{item.value}</dd>
                </div>
              ))}
            </dl>

            {topEpisodes.length > 0 && (
              <div className="space-y-2">
                <h3 className="text-sm font-semibold">{t('studio.stats.top_episodes')}</h3>
                {topEpisodes.map((episode) => (
                  <div key={episode.id} className="space-y-1">
                    <div className="flex items-center justify-between gap-2 text-xs">
                      <span className="min-w-0 truncate">{episode.title}</span>
                      <span className="shrink-0 tabular-nums text-muted">
                        {t('studio.stats.listen_count', { count: episode.listen_count ?? 0 })}
                      </span>
                    </div>
                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-surface-secondary" aria-hidden="true">
                      <div
                        className="h-full rounded-full bg-accent"
                        style={{ width: `${Math.max(4, ((episode.listen_count ?? 0) / maxListens) * 100)}%` }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            )}

            {(stats?.listens_over_time?.length ?? 0) > 0 && (
              <p className="text-xs text-muted">
                {t('studio.stats.last_days', { days: stats?.days ?? 30 })}
              </p>
            )}
          </>
        )}
      </CardBody>
    </Card>
  );
}
