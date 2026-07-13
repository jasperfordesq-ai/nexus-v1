// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Full episode player — a VIEW over PodcastPlayerContext (which owns the
// single audio element). Binding rule: when the context is already playing
// this episode we bind to the live state and never reload; otherwise we show
// static metadata and only load() inside the user's Play press (so browsing
// other episode pages never interrupts current playback, and play() stays in
// a user-gesture call stack for iOS).

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Chip, Slider } from '@/components/ui';
import { usePodcastPlayer, type PlayerTrack } from '@/contexts/PodcastPlayerContext';
import type { PodcastChapter, PodcastEpisode } from '@/lib/api/podcasts';
import Clock from 'lucide-react/icons/clock';
import Gauge from 'lucide-react/icons/gauge';
import Pause from 'lucide-react/icons/pause';
import Play from 'lucide-react/icons/play';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import RotateCw from 'lucide-react/icons/rotate-cw';
import TriangleAlert from 'lucide-react/icons/triangle-alert';
import Volume2 from 'lucide-react/icons/volume-2';
import { safePodcastArtworkUrl } from '@/lib/podcasts/artwork';

interface PodcastAudioPlayerProps {
  episode: PodcastEpisode;
  /** Show slug from the route — episode.show may be absent on some payloads. */
  showSlug?: string;
}

function normalizeDuration(value: number | null | undefined): number {
  return typeof value === 'number' && Number.isFinite(value) && value > 0 ? value : 0;
}

export function formatTime(totalSeconds: number): string {
  const seconds = Math.max(0, Math.floor(totalSeconds));
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const remaining = seconds % 60;

  if (hours > 0) {
    return `${hours}:${String(minutes).padStart(2, '0')}:${String(remaining).padStart(2, '0')}`;
  }

  return `${minutes}:${String(remaining).padStart(2, '0')}`;
}

export function trackFromEpisode(episode: PodcastEpisode, showSlug?: string): PlayerTrack {
  return {
    episodeId: episode.id,
    showSlug: episode.show?.slug ?? showSlug ?? '',
    episodeSlug: episode.slug,
    title: episode.title,
    showTitle: episode.show?.title ?? null,
    artworkUrl: safePodcastArtworkUrl(episode.cover_image_url ?? episode.show?.artwork_url),
    audioUrl: episode.audio_url,
    durationSeconds: episode.duration_seconds ?? null,
    chapters: episode.chapters ?? [],
    explicit: episode.explicit,
  };
}

export function PodcastAudioPlayer({ episode, showSlug }: PodcastAudioPlayerProps) {
  const { t } = useTranslation('podcasts');
  const player = usePodcastPlayer();
  const [scrubValue, setScrubValue] = useState<number | null>(null);

  const isActive = player.track?.episodeId === episode.id;
  const hasError = isActive && player.hasError;
  const isPlaying = isActive && (player.status === 'playing' || player.status === 'loading');

  const resumeAt = !isActive ? player.getResumePosition(episode.id) : null;
  const currentTime = isActive ? player.currentTime : (resumeAt ?? 0);
  const duration = isActive && player.duration > 0 ? player.duration : normalizeDuration(episode.duration_seconds);
  const playbackRate = isActive ? player.playbackRate : 1;

  const chapters = episode.chapters_enabled === false
    ? []
    : (episode.chapters ?? []).filter((chapter): chapter is PodcastChapter => Boolean(chapter.title));
  const canSeek = isActive && !hasError && duration > 0;
  const sliderMax = Math.max(1, duration);
  const sliderValue = Math.min(Math.max(scrubValue ?? currentTime, 0), sliderMax);

  function activate(seekSeconds?: number): void {
    player.load(trackFromEpisode(episode, showSlug), { autoplay: true });
    if (typeof seekSeconds === 'number') {
      player.seekTo(seekSeconds);
    }
  }

  function handlePlayPause(): void {
    if (isActive) {
      player.toggle();
    } else {
      activate();
    }
  }

  function handleStartOver(): void {
    if (isActive) {
      player.startOver();
    } else {
      activate(0);
    }
  }

  function handleChapter(startsAt: number): void {
    if (isActive) {
      player.seekTo(startsAt);
      player.play();
    } else {
      activate(startsAt);
    }
  }

  return (
    <div className="space-y-4">
      {hasError && (
        <div
          role="alert"
          className="flex flex-wrap items-center gap-2 rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger"
        >
          <TriangleAlert size={16} aria-hidden="true" />
          <span>{t('player.load_error')}</span>
          <Button
            size="sm"
            variant="tertiary"
            startContent={<RefreshCw size={14} aria-hidden="true" />}
            onPress={() => player.retry()}
          >
            {t('player.retry')}
          </Button>
        </div>
      )}

      <div className="flex flex-wrap items-center gap-3">
        <Button
          color="primary"
          startContent={isPlaying ? <Pause size={18} aria-hidden="true" /> : <Play size={18} aria-hidden="true" />}
          onPress={handlePlayPause}
          isDisabled={hasError}
          aria-pressed={isPlaying}
        >
          {isPlaying ? t('player.pause') : t('player.play')}
        </Button>
        {resumeAt !== null && resumeAt > 0 && (
          <div className="flex items-center gap-2 text-sm text-muted">
            <span>{t('player.resume_from', { time: formatTime(resumeAt) })}</span>
            <Button size="sm" variant="tertiary" onPress={handleStartOver}>
              {t('player.start_over')}
            </Button>
          </div>
        )}
        {isActive && currentTime > 5 && player.status !== 'ended' && (
          <Button size="sm" variant="tertiary" onPress={handleStartOver}>
            {t('player.start_over')}
          </Button>
        )}
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="tertiary"
            startContent={<RotateCcw size={16} aria-hidden="true" />}
            onPress={() => player.skip(-15)}
            isDisabled={!canSeek}
          >
            {t('player.skip_back')}
          </Button>
          <Button
            size="sm"
            variant="tertiary"
            startContent={<RotateCw size={16} aria-hidden="true" />}
            onPress={() => player.skip(30)}
            isDisabled={!canSeek}
          >
            {t('player.skip_forward')}
          </Button>
        </div>
        <div className="flex items-center gap-2">
          <Gauge size={16} className="text-muted" aria-hidden="true" />
          {[1, 1.25, 1.5, 2].map((rate) => (
            <Button
              key={rate}
              size="sm"
              variant={playbackRate === rate ? 'secondary' : 'tertiary'}
              onPress={() => player.setRate(rate)}
              isDisabled={!isActive || hasError}
            >
              {t('player.speed', { rate })}
            </Button>
          ))}
        </div>
      </div>

      <div className="space-y-1">
        <Slider
          aria-label={t('player.progress')}
          formatOptions={{ style: 'unit', unit: 'second', unitDisplay: 'long' }}
          size="sm"
          hideValue
          minValue={0}
          maxValue={sliderMax}
          step={1}
          value={sliderValue}
          isDisabled={!canSeek}
          onChange={(value) => setScrubValue(typeof value === 'number' ? value : (value[0] ?? 0))}
          onChangeEnd={(value) => {
            const target = typeof value === 'number' ? value : (value[0] ?? 0);
            setScrubValue(null);
            player.seekTo(target);
          }}
        />
        <div className="flex items-center justify-between text-xs text-muted">
          <span className="tabular-nums">{formatTime(sliderValue)}</span>
          <span className="tabular-nums">{duration > 0 ? formatTime(duration) : t('player.duration_unknown')}</span>
        </div>
      </div>

      {isActive && (
        <div className="flex items-center gap-3">
          <Volume2 size={16} className="shrink-0 text-muted" aria-hidden="true" />
          <Slider
            aria-label={t('player.volume')}
            formatOptions={{ style: 'percent' }}
            className="max-w-48 flex-1"
            size="sm"
            hideValue
            minValue={0}
            maxValue={1}
            step={0.05}
            value={player.volume}
            isDisabled={hasError}
            onChange={(value) => player.setVolume(typeof value === 'number' ? value : (value[0] ?? 1))}
          />
          <span className="w-10 text-right text-xs tabular-nums text-muted">
            {Math.round(player.volume * 100)}%
          </span>
        </div>
      )}

      {chapters.length > 0 && (
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Clock size={16} className="text-muted" aria-hidden="true" />
            <h2 className="text-sm font-semibold">{t('episode.chapters')}</h2>
          </div>
          <div className="flex max-h-48 flex-col gap-1 overflow-y-auto pr-1 sm:max-h-none sm:flex-row sm:flex-wrap sm:gap-2 sm:overflow-visible">
            {chapters.map((chapter, index) => (
              <Button
                key={`${chapter.starts_at_seconds}-${index}`}
                size="sm"
                variant="tertiary"
                className="justify-start sm:justify-center"
                onPress={() => handleChapter(chapter.starts_at_seconds)}
                isDisabled={hasError}
                aria-label={t('player.jump_to_chapter', { time: formatTime(chapter.starts_at_seconds), title: chapter.title })}
              >
                <span className="tabular-nums text-muted">{formatTime(chapter.starts_at_seconds)}</span>
                <span className="ml-2 truncate">{chapter.title}</span>
              </Button>
            ))}
          </div>
        </div>
      )}

      {episode.explicit && (
        <Chip size="sm" variant="soft" color="warning">
          {t('episode.explicit')}
        </Chip>
      )}
    </div>
  );
}
