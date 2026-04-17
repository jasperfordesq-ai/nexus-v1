// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@heroui/react';
import { Play, Pause, FileText } from 'lucide-react';
import { resolveAssetUrl } from '@/lib/helpers';

export interface VoiceMessagePlayerProps {
  audioUrl?: string;
  audioBlob?: Blob;
  transcript?: string;
  transcriptLanguage?: string;
}

/**
 * Voice message player component
 */
export function VoiceMessagePlayer({ audioUrl, audioBlob, transcript }: VoiceMessagePlayerProps) {
  const { t } = useTranslation('messages');
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);
  const [showTranscript, setShowTranscript] = useState(false);
  const audioRef = useRef<HTMLAudioElement | null>(null);

  useEffect(() => {
    // Create audio element
    const audio = new Audio();
    audioRef.current = audio;

    if (audioBlob) {
      audio.src = URL.createObjectURL(audioBlob);
    } else if (audioUrl) {
      audio.src = resolveAssetUrl(audioUrl);
    }

    audio.onloadedmetadata = () => {
      setDuration(audio.duration);
    };

    audio.ontimeupdate = () => {
      setCurrentTime(audio.currentTime);
    };

    audio.onended = () => {
      setIsPlaying(false);
      setCurrentTime(0);
    };

    return () => {
      if (audioBlob) {
        URL.revokeObjectURL(audio.src);
      }
      audio.pause();
    };
  }, [audioUrl, audioBlob]);

  function togglePlay() {
    const audio = audioRef.current;
    if (!audio) return;

    if (isPlaying) {
      audio.pause();
      setIsPlaying(false);
    } else {
      audio.play();
      setIsPlaying(true);
    }
  }

  function formatTime(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }

  const progress = duration > 0 ? (currentTime / duration) * 100 : 0;

  return (
    <div className="min-w-[150px]">
      <div className="flex items-center gap-3">
        <Button
          isIconOnly
          size="sm"
          variant="light"
          className="w-8 h-8 min-w-0 bg-black/20 dark:bg-white/20 rounded-full"
          onPress={togglePlay}
          aria-label={isPlaying ? t('voice_pause') : t('voice_play')}
        >
          {isPlaying ? (
            <Pause className="w-4 h-4" aria-hidden="true" />
          ) : (
            <Play className="w-4 h-4 ml-0.5" aria-hidden="true" />
          )}
        </Button>
        <div className="flex-1">
          <div className="h-1 bg-theme-elevated rounded-full overflow-hidden">
            <div
              className="h-full bg-black/60 dark:bg-white/60 rounded-full transition-all"
              style={{ width: `${progress}%` }}
            />
          </div>
          <div className="flex justify-between text-xs opacity-50 mt-1">
            <span>{formatTime(currentTime)}</span>
            <span>{formatTime(duration)}</span>
          </div>
        </div>
      </div>

      {/* Transcript toggle and display */}
      {transcript && (
        <div className="mt-2">
          <Button
            size="sm"
            variant="light"
            className="h-6 min-w-0 px-2 text-xs opacity-60 hover:opacity-100 gap-1"
            onPress={() => setShowTranscript(!showTranscript)}
            startContent={<FileText className="w-3 h-3" aria-hidden="true" />}
          >
            {showTranscript ? t('voice.hide_transcript') : t('voice.show_transcript')}
          </Button>
          {showTranscript && (
            <p className="text-xs opacity-70 mt-1 whitespace-pre-wrap leading-relaxed">
              {transcript}
            </p>
          )}
        </div>
      )}
    </div>
  );
}
