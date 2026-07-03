// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * YouTubeEmbed — Inline media-player embed for feed link previews.
 *
 * Despite the name (kept for import stability), this renders players for every
 * supported provider: YouTube, Vimeo, Twitch and TikTok (click-to-play video),
 * plus Spotify and SoundCloud (direct audio players). The provider is detected
 * from the embed URL that the backend LinkPreviewService builds from the
 * original link — no server-side scraping required.
 *
 * Performance: video iframes are NOT loaded until the user clicks play,
 * reducing initial page weight for feeds with many video links.
 */

import { useState, useCallback } from 'react';

import Play from 'lucide-react/icons/play';
import { useTranslation } from 'react-i18next';
import { Button, Card } from '@/components/ui';

/* ───────────────────────── Types ───────────────────────── */

interface YouTubeEmbedProps {
  /** The embed URL (iframe src) built by the backend, e.g. https://www.youtube-nocookie.com/embed/VIDEO_ID */
  embedUrl: string;
  /** Thumbnail image URL (optional; YouTube derives one from the video id) */
  thumbnailUrl?: string;
  /** Media title for accessibility */
  title?: string;
}

type Provider = 'youtube' | 'vimeo' | 'spotify' | 'soundcloud' | 'twitch' | 'tiktok' | 'other';

/* ───────────────────────── Helpers ───────────────────────── */

function detectProvider(embedUrl: string): Provider {
  if (/youtube(?:-nocookie)?\.com|youtu\.be/i.test(embedUrl)) return 'youtube';
  if (/player\.vimeo\.com/i.test(embedUrl)) return 'vimeo';
  if (/open\.spotify\.com\/embed/i.test(embedUrl)) return 'spotify';
  if (/w\.soundcloud\.com\/player/i.test(embedUrl)) return 'soundcloud';
  if (/player\.twitch\.tv|clips\.twitch\.tv\/embed/i.test(embedUrl)) return 'twitch';
  if (/tiktok\.com\/embed/i.test(embedUrl)) return 'tiktok';
  return 'other';
}

const PROVIDER_LABEL: Record<Provider, string> = {
  youtube: 'YouTube',
  vimeo: 'Vimeo',
  spotify: 'Spotify',
  soundcloud: 'SoundCloud',
  twitch: 'Twitch',
  tiktok: 'TikTok',
  other: 'Video',
};

/** Extract a YouTube video ID from an embed URL. */
function extractVideoId(embedUrl: string): string | null {
  const match = embedUrl.match(
    /(?:youtube(?:-nocookie)?\.com\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/
  );
  return match?.[1] ?? null;
}

function getYouTubeThumbnail(videoId: string): string {
  return `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`;
}

/** Current host — required as the `parent` param for Twitch embeds to load. */
function currentHost(): string {
  return typeof window !== 'undefined' ? window.location.hostname : 'localhost';
}

/** Spotify tracks/episodes use a compact player; albums/playlists a tall one. */
function spotifyHeight(embedUrl: string): number {
  return /\/embed\/(?:track|episode)\//i.test(embedUrl) ? 152 : 352;
}

/* ───────────────────────── Component ───────────────────────── */

export function YouTubeEmbed({ embedUrl, thumbnailUrl, title }: YouTubeEmbedProps) {
  const { t } = useTranslation('feed');
  const [isPlaying, setIsPlaying] = useState(false);
  const videoTitle = title ?? t('video.default_title');
  const provider = detectProvider(embedUrl);

  const handlePlay = useCallback(() => {
    setIsPlaying(true);
  }, []);

  // ── Audio providers: lightweight, render the player directly (no click-to-play).
  if (provider === 'spotify' || provider === 'soundcloud') {
    const height = provider === 'spotify' ? spotifyHeight(embedUrl) : 166;
    return (
      <Card className="overflow-hidden border border-[var(--border-default)] bg-[var(--surface-elevated)]">
        <iframe
          src={embedUrl}
          title={videoTitle}
          className="w-full block"
          style={{ height }}
          allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
          loading="lazy"
        />
      </Card>
    );
  }

  // ── Video providers: click-to-play to keep the feed light.
  const videoId = provider === 'youtube' ? extractVideoId(embedUrl) : null;
  const thumbnail = thumbnailUrl || (videoId ? getYouTubeThumbnail(videoId) : null);
  // TikTok is a vertical (portrait) player; the rest are 16:9.
  const aspectClass = provider === 'tiktok'
    ? 'aspect-[9/16] max-w-[325px] mx-auto'
    : 'aspect-video';

  // Build the iframe src, adding autoplay when played and the required
  // per-provider params (Twitch needs &parent=<host> to render at all).
  const buildSrc = (): string => {
    switch (provider) {
      case 'youtube':
        return isPlaying
          ? `${embedUrl}?autoplay=1&rel=0&cc_load_policy=1`
          : `${embedUrl}?cc_load_policy=1`;
      case 'vimeo':
        return isPlaying ? `${embedUrl}?autoplay=1` : embedUrl;
      case 'twitch': {
        const parent = `&parent=${encodeURIComponent(currentHost())}`;
        return `${embedUrl}${parent}&autoplay=${isPlaying ? 'true' : 'false'}`;
      }
      case 'tiktok':
        return embedUrl;
      default:
        return isPlaying ? `${embedUrl}?autoplay=1` : embedUrl;
    }
  };

  return (
    <Card className="overflow-hidden border border-[var(--border-default)] bg-[var(--surface-elevated)]">
      <div className={`relative w-full ${aspectClass}`}>
        {isPlaying ? (
          <iframe
            src={buildSrc()}
            title={videoTitle}
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
            aria-label={t('video.play_label', { title: videoTitle })}
          >
            {/* Thumbnail */}
            {thumbnail && (
              <img
                src={thumbnail}
                alt={t('video.thumbnail_alt', { title: videoTitle })}
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

            {/* Provider branding in corner */}
            <div className="absolute bottom-2 right-2 px-2 py-0.5 rounded bg-black/60 text-white text-[10px] font-medium">
              {PROVIDER_LABEL[provider]}
            </div>
          </Button>
        )}
      </div>
    </Card>
  );
}

export default YouTubeEmbed;
