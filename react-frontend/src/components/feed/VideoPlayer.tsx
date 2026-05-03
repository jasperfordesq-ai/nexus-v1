// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VideoPlayer — Inline video player for feed posts with controls.
 *
 * - Play/pause on click with overlay icon.
 * - Native browser controls on interaction.
 * - Muted autoplay when scrolled into view (IntersectionObserver).
 * - Poster from thumbnail_url if available.
 */

import { useRef, useState, useEffect, useCallback, type KeyboardEvent } from 'react';
import { Button } from '@heroui/react';
import Play from 'lucide-react/icons/play';
import Volume2 from 'lucide-react/icons/volume-2';
import VolumeX from 'lucide-react/icons/volume-x';
import VideoOff from 'lucide-react/icons/video-off';
import { useTranslation } from 'react-i18next';
import { resolveAssetUrl } from '@/lib/helpers';
import type { PostMedia } from './types';

interface VideoPlayerProps {
  media: PostMedia;
  className?: string;
}

export function VideoPlayer({ media, className = '' }: VideoPlayerProps) {
  const { t } = useTranslation('feed');
  const videoRef = useRef<HTMLVideoElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [isMuted, setIsMuted] = useState(true);
  const [showPlayOverlay, setShowPlayOverlay] = useState(true);
  const [hasError, setHasError] = useState(false);

  // Autoplay muted when visible, pause when not
  useEffect(() => {
    const video = videoRef.current;
    const container = containerRef.current;
    if (!video || !container) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) {
          video.play().catch(() => {
            // Autoplay blocked — user must interact
          });
        } else {
          video.pause();
        }
      },
      { threshold: 0.5 },
    );

    observer.observe(container);
    return () => observer.disconnect();
  }, []);

  const handlePlayPause = useCallback(() => {
    const video = videoRef.current;
    if (!video) return;

    if (video.paused) {
      video.play().catch(() => {});
      setIsPlaying(true);
      setShowPlayOverlay(false);
    } else {
      video.pause();
      setIsPlaying(false);
      setShowPlayOverlay(true);
    }
  }, []);

  const handleToggleMute = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    const video = videoRef.current;
    if (!video) return;
    video.muted = !video.muted;
    setIsMuted(video.muted);
  }, []);

  const handlePlay = useCallback(() => {
    setIsPlaying(true);
    setShowPlayOverlay(false);
  }, []);

  const handlePause = useCallback(() => {
    setIsPlaying(false);
    setShowPlayOverlay(true);
  }, []);

  const handleError = useCallback(() => {
    setHasError(true);
    setIsPlaying(false);
    setShowPlayOverlay(false);
  }, []);

  const handleKeyDown = useCallback(
    (e: KeyboardEvent<HTMLDivElement>) => {
      if (hasError) return;
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        handlePlayPause();
      }
    },
    [hasError, handlePlayPause]
  );

  const posterUrl = media.thumbnail_url ? resolveAssetUrl(media.thumbnail_url) : undefined;

  if (hasError) {
    return (
      <div
        className={`relative overflow-hidden rounded-xl bg-theme-elevated border border-theme-default flex flex-col items-center justify-center py-10 px-4 text-center ${className}`}
        role="status"
      >
        <VideoOff className="w-8 h-8 text-theme-muted mb-2" aria-hidden="true" />
        <p className="text-sm font-medium text-theme-primary">{t('video.unavailable')}</p>
        <p className="text-xs text-theme-muted mt-1">{t('video.unavailable_desc')}</p>
      </div>
    );
  }

  return (
    <div
      ref={containerRef}
      className={`relative overflow-hidden rounded-xl bg-black/5 dark:bg-white/5 group cursor-pointer ${className}`}
      onClick={handlePlayPause}
      onKeyDown={handleKeyDown}
      role="button"
      tabIndex={0}
      aria-label={isPlaying ? t('video.pause') : t('video.play')}
    >
      <video
        ref={videoRef}
        src={resolveAssetUrl(media.file_url)}
        poster={posterUrl}
        muted={isMuted}
        loop
        playsInline
        preload="metadata"
        className="w-full max-h-[500px] sm:max-h-[500px] max-sm:max-h-[400px] object-contain"
        aria-label={media.alt_text || t('video.aria_label')}
        onPlay={handlePlay}
        onPause={handlePause}
        onError={handleError}
      />

      {/* Play overlay — shown when paused */}
      {showPlayOverlay && (
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
          <div className="bg-black/40 backdrop-blur-sm rounded-full p-4">
            <Play className="w-8 h-8 text-white fill-white" aria-hidden="true" />
          </div>
        </div>
      )}

      {/* Mute toggle — bottom right */}
      <Button
        isIconOnly
        variant="flat"
        size="sm"
        className="absolute bottom-3 right-3 bg-black/40 backdrop-blur-sm text-white rounded-full min-w-[44px] min-h-[44px] opacity-100 lg:opacity-0 lg:group-hover:opacity-100 focus:opacity-100 transition-opacity"
        onClick={handleToggleMute}
        aria-label={isMuted ? t('video.unmute') : t('video.mute')}
      >
        {isMuted ? (
          <VolumeX className="w-4 h-4" />
        ) : (
          <Volume2 className="w-4 h-4" />
        )}
      </Button>
    </div>
  );
}
