// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * YouTubeEmbed — Click-to-play video embed for YouTube and Vimeo.
 *
 * Shows a thumbnail with a play button overlay. Clicking loads the iframe
 * with the actual embed player. Uses privacy-enhanced mode for YouTube
 * (youtube-nocookie.com).
 *
 * Performance: iframe is NOT loaded until the user clicks play, reducing
 * initial page weight significantly for feeds with many video links.
 */

import { useState, useCallback } from 'react';
import { Card, Button } from '@heroui/react';
import { Play } from 'lucide-react';

/* ───────────────────────── Types ───────────────────────── */

interface YouTubeEmbedProps {
  /** The embed URL (e.g., https://www.youtube-nocookie.com/embed/VIDEO_ID) */
  embedUrl: string;
  /** Thumbnail image URL (optional, falls back to YouTube's default) */
  thumbnailUrl?: string;
  /** Video title for accessibility */
  title?: string;
}

/* ───────────────────────── Helpers ───────────────────────── */

/**
 * Extract YouTube video ID from an embed URL.
 */
function extractVideoId(embedUrl: string): string | null {
  const match = embedUrl.match(
    /(?:youtube(?:-nocookie)?\.com\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/
  );
  return match?.[1] ?? null;
}

/**
 * Get a YouTube thumbnail URL for a video ID.
 */
function getYouTubeThumbnail(videoId: string): string {
  return `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`;
}

/* ───────────────────────── Component ───────────────────────── */

export function YouTubeEmbed({ embedUrl, thumbnailUrl, title = 'Video' }: YouTubeEmbedProps) {
  const [isPlaying, setIsPlaying] = useState(false);

  const videoId = extractVideoId(embedUrl);

  // Determine thumbnail: use provided, or derive from video ID
  const thumbnail = thumbnailUrl || (videoId ? getYouTubeThumbnail(videoId) : null);

  const handlePlay = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    e.preventDefault();
    setIsPlaying(true);
  }, []);

  // Build the embed src with autoplay when played
  const embedSrc = isPlaying
    ? `${embedUrl}?autoplay=1&rel=0`
    : embedUrl;

  return (
    <Card
      shadow="none"
      className="overflow-hidden border border-[var(--border-default)] bg-[var(--surface-elevated)]"
    >
      <div className="relative w-full" style={{ aspectRatio: '16 / 9' }}>
        {isPlaying ? (
          <iframe
            src={embedSrc}
            title={title}
            className="absolute inset-0 w-full h-full"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
            loading="lazy"
          />
        ) : (
          <Button
            variant="flat"
            onPress={handlePlay}
            className="absolute inset-0 w-full h-full group/play cursor-pointer bg-black rounded-none p-0 min-w-0 focus:ring-2 focus:ring-[var(--color-primary)] focus:ring-inset"
            aria-label={`Play video: ${title}`}
          >
            {/* Thumbnail */}
            {thumbnail && (
              <img
                src={thumbnail}
                alt={`Thumbnail for ${title}`}
                className="w-full h-full object-cover opacity-90 group-hover/play:opacity-100 transition-opacity"
                loading="lazy"
              />
            )}

            {/* Dark overlay */}
            <div className="absolute inset-0 bg-black/20 group-hover/play:bg-black/10 transition-colors" />

            {/* Play button */}
            <div className="absolute inset-0 flex items-center justify-center">
              <div className="w-16 h-16 rounded-full bg-red-600 flex items-center justify-center shadow-xl group-hover/play:scale-110 group-hover/play:bg-red-500 transition-all duration-200">
                <Play className="w-7 h-7 text-white ml-1" fill="white" aria-hidden="true" />
              </div>
            </div>

            {/* Video platform branding in corner */}
            <div className="absolute bottom-2 right-2 px-2 py-0.5 rounded bg-black/60 text-white text-[10px] font-medium">
              {embedUrl.includes('vimeo') ? 'Vimeo' : 'YouTube'}
            </div>
          </Button>
        )}
      </div>
    </Card>
  );
}

export default YouTubeEmbed;
