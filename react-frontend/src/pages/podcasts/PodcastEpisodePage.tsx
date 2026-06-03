// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router-dom';
import { Button, Card, CardBody, Chip, Spinner } from '@/components/ui';
import { PodcastAudioPlayer } from '@/components/podcasts/PodcastAudioPlayer';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { podcastsApi, type PodcastEpisode } from '@/lib/api/podcasts';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Heart from 'lucide-react/icons/heart';

export default function PodcastEpisodePage() {
  const { t } = useTranslation('podcasts');
  const { showSlug = '', episodeSlug = '' } = useParams();
  const { tenantPath } = useTenant();
  const { isAuthenticated } = useAuth();
  const [episode, setEpisode] = useState<PodcastEpisode | null>(null);
  const [loading, setLoading] = useState(true);
  const [reactionActive, setReactionActive] = useState(false);

  usePageTitle(episode?.title ?? t('episode.title'));

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    podcastsApi.episode(showSlug, episodeSlug)
      .then((res) => {
        if (!cancelled) setEpisode(res.success && res.data ? res.data : null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [showSlug, episodeSlug]);

  async function handleCompleted(seconds: number): Promise<void> {
    if (!episode || !isAuthenticated) return;
    await podcastsApi.recordListen(episode.id, {
      listened_seconds: Math.round(seconds),
      completed: true,
      session_id: `${episode.id}:${Date.now()}`,
    });
  }

  async function handleReaction(): Promise<void> {
    if (!episode || !isAuthenticated) return;
    const res = await podcastsApi.toggleReaction(episode.id);
    if (res.success && res.data) {
      setReactionActive(res.data.active);
    }
  }

  if (loading) {
    return (
      <div className="flex justify-center py-20" role="status" aria-busy="true">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!episode) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center">
        <p className="text-lg font-medium">{t('episode.not_found')}</p>
        <Button as={Link} to={tenantPath('/podcasts')} className="mt-4" variant="tertiary">
          {t('actions.back_to_podcasts')}
        </Button>
      </div>
    );
  }

  const showPath = episode.show?.slug ?? showSlug;

  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <Button as={Link} to={tenantPath(`/podcasts/${showPath}`)} variant="tertiary" size="sm" startContent={<ArrowLeft size={16} aria-hidden="true" />}>
        {t('actions.back_to_show')}
      </Button>

      <article className="mt-5 space-y-6">
        <header>
          <div className="mb-2 flex flex-wrap items-center gap-2">
            <Chip size="sm" variant="soft">{t(`episode.type.${episode.episode_type}`)}</Chip>
            {episode.status !== 'published' || episode.moderation_status !== 'approved' ? (
              <Chip size="sm" variant="soft" color="warning">{t(`moderation.${episode.moderation_status}`)}</Chip>
            ) : null}
          </div>
          <h1 className="text-3xl font-bold leading-tight">{episode.title}</h1>
          <p className="mt-2 text-sm text-muted">
            {episode.show?.title ? t('episode.from_show', { title: episode.show.title }) : t('title')}
          </p>
          {episode.summary && <p className="mt-4 text-base">{episode.summary}</p>}
        </header>

        <Card>
          <CardBody>
            <PodcastAudioPlayer episode={episode} onCompleted={handleCompleted} />
          </CardBody>
        </Card>

        <div className="flex flex-wrap gap-2">
          {isAuthenticated && (
            <Button variant={reactionActive ? 'secondary' : 'tertiary'} size="sm" startContent={<Heart size={16} aria-hidden="true" />} onPress={handleReaction}>
              {reactionActive ? t('episode.reacted') : t('episode.react')}
            </Button>
          )}
        </div>

        {episode.description && (
          <section>
            <h2 className="mb-2 text-lg font-semibold">{t('episode.description')}</h2>
            <p className="whitespace-pre-line text-sm text-muted">{episode.description}</p>
          </section>
        )}

        {episode.transcript && (
          <section>
            <h2 className="mb-2 text-lg font-semibold">{t('episode.transcript')}</h2>
            <div className="max-h-[28rem] overflow-auto rounded-lg border border-border bg-surface-secondary/50 p-4 text-sm leading-6 text-foreground">
              <p className="whitespace-pre-line">{episode.transcript}</p>
            </div>
          </section>
        )}
      </article>
    </div>
  );
}
