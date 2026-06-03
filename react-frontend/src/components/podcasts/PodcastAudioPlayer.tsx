// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Chip, Progress } from '@/components/ui';
import type { PodcastChapter, PodcastEpisode } from '@/lib/api/podcasts';
import Clock from 'lucide-react/icons/clock';
import Gauge from 'lucide-react/icons/gauge';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import RotateCw from 'lucide-react/icons/rotate-cw';

interface PodcastAudioPlayerProps {
  episode: PodcastEpisode;
  onCompleted?: (seconds: number) => void;
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
  const [duration, setDuration] = useState(episode.duration_seconds ?? 0);
  const [playbackRate, setPlaybackRate] = useState(1);

  const chapters = (episode.chapters ?? []).filter((chapter): chapter is PodcastChapter => Boolean(chapter.title));
  const progress = duration > 0 ? Math.min(100, (currentTime / duration) * 100) : 0;

  function seek(seconds: number): void {
    if (!audioRef.current) return;
    audioRef.current.currentTime = seconds;
    audioRef.current.play().catch(() => undefined);
  }

  function skip(deltaSeconds: number): void {
    if (!audioRef.current) return;
    const nextTime = Math.min(Math.max(audioRef.current.currentTime + deltaSeconds, 0), duration || Number.MAX_SAFE_INTEGER);
    audioRef.current.currentTime = nextTime;
    setCurrentTime(nextTime);
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
        onLoadedMetadata={(event) => setDuration(event.currentTarget.duration || episode.duration_seconds || 0)}
        onTimeUpdate={(event) => setCurrentTime(event.currentTarget.currentTime)}
        onRateChange={(event) => setPlaybackRate(event.currentTarget.playbackRate)}
        onEnded={handleEnded}
      >
        {t('player.unsupported')}
      </audio>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant="tertiary"
            startContent={<RotateCcw size={16} aria-hidden="true" />}
            onPress={() => skip(-15)}
          >
            {t('player.skip_back')}
          </Button>
          <Button
            size="sm"
            variant="tertiary"
            startContent={<RotateCw size={16} aria-hidden="true" />}
            onPress={() => skip(30)}
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
            >
              {t('player.speed', { rate })}
            </Button>
          ))}
        </div>
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs text-muted">
          <span>{formatTime(currentTime)}</span>
          <span>{duration > 0 ? formatTime(duration) : t('player.duration_unknown')}</span>
        </div>
        <Progress aria-label={t('player.progress')} value={progress} />
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
                onPress={() => seek(chapter.starts_at_seconds)}
              >
                <span>{formatTime(chapter.starts_at_seconds)}</span>
                <span>{chapter.title}</span>
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
