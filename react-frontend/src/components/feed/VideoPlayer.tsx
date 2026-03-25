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

import { useRef, useState, useEffect, useCallback } from 'react';
import { Play, Volume2, VolumeX } from 'lucide-react';
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

  // Autoplay muted when visible, pause when not
  useEffect(() => {
    const video = videoRef.current;
    const container = containerRef.current;
    if (!video || !container) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
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

  const posterUrl = media.thumbnail_url ? resolveAssetUrl(media.thumbnail_url) : undefined;

  return (
    <div
      ref={containerRef}
      className={`relative overflow-hidden rounded-xl bg-black/5 dark:bg-white/5 group cursor-pointer ${className}`}
      onClick={handlePlayPause}
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
        aria-label={media.alt_text || t('video.aria_label', 'Video')}
        onPlay={handlePlay}
        onPause={handlePause}
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
      <button
        type="button"
        className="absolute bottom-3 right-3 bg-black/40 backdrop-blur-sm text-white rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/60 focus:opacity-100 focus:outline-none focus:ring-2 focus:ring-white/50"
        onClick={handleToggleMute}
        aria-label={isMuted ? t('video.unmute', 'Unmute') : t('video.mute', 'Mute')}
      >
        {isMuted ? (
          <VolumeX className="w-4 h-4" />
        ) : (
          <Volume2 className="w-4 h-4" />
        )}
      </button>
    </div>
  );
}
