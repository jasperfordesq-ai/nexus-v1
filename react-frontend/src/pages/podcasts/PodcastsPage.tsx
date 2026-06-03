// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { AlphaBadge, Avatar, Button, Card, CardBody, Chip, SearchField, Spinner } from '@/components/ui';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { podcastsApi, type PodcastShow } from '@/lib/api/podcasts';
import PodcastIcon from 'lucide-react/icons/podcast';
import Plus from 'lucide-react/icons/plus';
import Search from 'lucide-react/icons/search';

function showArtwork(show: PodcastShow): string | undefined {
  return show.artwork_url ?? undefined;
}

export default function PodcastsPage() {
  const { t } = useTranslation('podcasts');
  usePageTitle(t('title'));
  const { tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();

  const [shows, setShows] = useState<PodcastShow[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    podcastsApi
      .browse({ q: searchTerm || undefined, page })
      .then((res) => {
        if (cancelled) return;
        if (res.success && res.data) {
          setShows((prev) => (page === 1 ? res.data!.items : [...prev, ...res.data!.items]));
          setHasMore(res.data.has_more);
        } else {
          setShows([]);
          setHasMore(false);
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [searchTerm, page]);

  useEffect(() => {
    setPage(1);
  }, [searchTerm]);

  return (
    <div className="mx-auto max-w-7xl px-4 py-6">
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold leading-tight">{t('title')}</h1>
            <AlphaBadge />
          </div>
          <p className="mt-1 max-w-2xl text-sm text-muted">{t('subtitle')}</p>
        </div>

        {isAuthenticated && (
          <div className="flex flex-col gap-2 sm:flex-row">
            <Button as={Link} to={tenantPath('/podcasts/studio')} variant="tertiary" size="sm">
              {t('studio.title')}
            </Button>
            <Button as={Link} to={tenantPath('/podcasts/studio')} color="primary" size="sm" startContent={<Plus size={16} aria-hidden="true" />}>
              {t('studio.create_show')}
            </Button>
          </div>
        )}
      </div>

      <div className="mb-6 max-w-xl">
        <SearchField
          size="sm"
          value={searchTerm}
          onValueChange={setSearchTerm}
          onClear={() => setSearchTerm('')}
          isClearable
          placeholder={t('browse.search_placeholder')}
          aria-label={t('browse.search_placeholder')}
          startContent={<Search size={16} className="text-muted" aria-hidden="true" />}
        />
      </div>

      {loading ? (
        <div className="flex justify-center py-16" role="status" aria-busy="true">
          <Spinner size="lg" />
        </div>
      ) : shows.length === 0 ? (
        <div className="py-16 text-center text-muted">
          <PodcastIcon size={42} className="mx-auto mb-3" aria-hidden="true" />
          <p className="text-lg font-medium text-foreground">{t('browse.empty')}</p>
          <p className="mt-1 text-sm">{t('browse.empty_hint')}</p>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {shows.map((show) => (
              <Card key={show.id} className="h-full">
                <CardBody className="flex h-full flex-col gap-4">
                  <div className="flex gap-4">
                    <Avatar
                      src={showArtwork(show)}
                      name={show.title}
                      radius="md"
                      className="h-16 w-16 shrink-0"
                    />
                    <div className="min-w-0">
                      <Link className="text-base font-semibold hover:text-accent" to={tenantPath(`/podcasts/${show.slug}`)}>
                        {show.title}
                      </Link>
                      <p className="mt-1 line-clamp-2 text-sm text-muted">{show.summary || t('show.no_summary')}</p>
                    </div>
                  </div>

                  <div className="mt-auto flex flex-wrap items-center gap-2 text-xs text-muted">
                    <Chip size="sm" variant="soft">{t(`visibility.${show.visibility}`)}</Chip>
                    <span>{t('show.episode_count', { count: show.approved_episode_count ?? show.episode_count ?? 0 })}</span>
                    {show.owner?.name ? <span>{t('show.byline', { name: show.owner.name })}</span> : null}
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>

          {hasMore && (
            <div className="mt-6 flex justify-center">
              <Button variant="secondary" isLoading={loading} onPress={() => setPage((p) => p + 1)}>
                {t('browse.load_more')}
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
