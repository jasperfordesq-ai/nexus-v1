// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Chip, Slider } from '@/components/ui';
import type { PodcastChapter, PodcastEpisode } from '@/lib/api/podcasts';
import Clock from 'lucide-react/icons/clock';
import Gauge from 'lucide-react/icons/gauge';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import RotateCw from 'lucide-react/icons/rotate-cw';
import TriangleAlert from 'lucide-react/icons/triangle-alert';

interface PodcastAudioPlayerProps {
  episode: PodcastEpisode;
  onCompleted?: (seconds: number) => void;
}

function normalizeDuration(value: number | null | undefined): number {
  return typeof value === 'number' && Number.isFinite(value) && value > 0 ? value : 0;
}

function formatTime(totalSeconds: number): string {
  const seconds = Math.max(0, Math.floor(totalSeconds));
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const remaining = seconds % 60;

  if (hours > 0) {
    return `${hours}:${String(minutes).padStart(2, '0')}:${String(remaining).padStart(2, '0')}`;
  }

  return `${minutes}:${String(remaining).padStart(2, '0')}`;
}

export function PodcastAudioPlayer({ episode, onCompleted }: PodcastAudioPlayerProps) {
  const { t } = useTranslation('podcasts');
  const audioRef = useRef<HTMLAudioElement | null>(null);
  const completedRef = useRef(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(() => normalizeDuration(episode.duration_seconds));
  const [playbackRate, setPlaybackRate] = useState(1);
  const [scrubValue, setScrubValue] = useState<number | null>(null);
  const [hasError, setHasError] = useState(false);

  const chapters = (episode.chapters ?? []).filter((chapter): chapter is PodcastChapter => Boolean(chapter.title));
  const canSeek = !hasError && duration > 0;
  const sliderMax = Math.max(1, duration);
  const sliderValue = Math.min(Math.max(scrubValue ?? currentTime, 0), sliderMax);

  useEffect(() => {
    completedRef.current = false;
    setCurrentTime(0);
    setDuration(normalizeDuration(episode.duration_seconds));
    setPlaybackRate(1);
    setScrubValue(null);
    setHasError(false);
    if (audioRef.current) {
      audioRef.current.currentTime = 0;
      audioRef.current.playbackRate = 1;
    }
  }, [episode.id, episode.duration_seconds]);

  function seekTo(seconds: number, autoplay: boolean): void {
    const audio = audioRef.current;
    if (!audio || hasError) return;
    const upperBound = duration > 0 ? duration : seconds;
    const next = Math.min(Math.max(seconds, 0), upperBound);
    audio.currentTime = next;
    setCurrentTime(next);
    if (autoplay) audio.play().catch(() => undefined);
  }

  function skip(deltaSeconds: number): void {
    const audio = audioRef.current;
    if (!audio || hasError) return;
    seekTo(audio.currentTime + deltaSeconds, false);
  }

  function changePlaybackRate(nextRate: number): void {
    setPlaybackRate(nextRate);
    if (audioRef.current) {
      audioRef.current.playbackRate = nextRate;
    }
  }

  function handleEnded(): void {
    if (completedRef.current) return;
    completedRef.current = true;
    onCompleted?.(duration || currentTime);
  }

  return (
    <div className="space-y-4">
      <audio
        ref={audioRef}
        className="w-full"
        controls
        preload="metadata"
        src={episode.audio_url}
        onLoadedMetadata={(event) => {
          setDuration(normalizeDuration(event.currentTarget.duration) || normalizeDuration(episode.duration_seconds));
          setHasError(false);
        }}
        onTimeUpdate={(event) => {
          // Freeze the displayed position while the user is scrubbing.
          if (scrubValue === null) setCurrentTime(event.currentTarget.currentTime);
        }}
        onRateChange={(event) => setPlaybackRate(event.currentTarget.playbackRate)}
        onEnded={handleEnded}
        onError={() => setHasError(true)}
      >
        {t('player.unsupported')}
      </audio>

      {hasError && (
        <div
          role="alert"
          className="flex items-center gap-2 rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger"
        >
          <TriangleAlert size={16} aria-hidden="true" />
          <span>{t('player.load_error')}</span>
        </div>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="tertiary"
            startContent={<RotateCcw size={16} aria-hidden="true" />}
            onPress={() => skip(-15)}
            isDisabled={!canSeek}
          >
            {t('player.skip_back')}
          </Button>
          <Button
            size="sm"
            variant="tertiary"
            startContent={<RotateCw size={16} aria-hidden="true" />}
            onPress={() => skip(30)}
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
              onPress={() => changePlaybackRate(rate)}
              isDisabled={hasError}
            >
              {t('player.speed', { rate })}
            </Button>
          ))}
        </div>
      </div>

      <div className="space-y-1">
        <Slider
          aria-label={t('player.progress')}
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
            seekTo(target, false);
          }}
        />
        <div className="flex items-center justify-between text-xs text-muted">
          <span className="tabular-nums">{formatTime(sliderValue)}</span>
          <span className="tabular-nums">{duration > 0 ? formatTime(duration) : t('player.duration_unknown')}</span>
        </div>
      </div>

      {chapters.length > 0 && (
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Clock size={16} className="text-muted" aria-hidden="true" />
            <h2 className="text-sm font-semibold">{t('episode.chapters')}</h2>
          </div>
          <div className="flex flex-wrap gap-2">
            {chapters.map((chapter, index) => (
              <Button
                key={`${chapter.starts_at_seconds}-${index}`}
                size="sm"
                variant="tertiary"
                onPress={() => seekTo(chapter.starts_at_seconds, true)}
                isDisabled={hasError}
                aria-label={t('player.jump_to_chapter', { time: formatTime(chapter.starts_at_seconds), title: chapter.title })}
              >
                <span className="tabular-nums text-muted">{formatTime(chapter.starts_at_seconds)}</span>
                <span className="ml-2">{chapter.title}</span>
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
