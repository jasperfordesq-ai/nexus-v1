// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LinkPreviewCard — Rich preview card for URLs with OG metadata.
 *
 * Two layout modes:
 * - Large (default): Full-width image on top, text content below (like Facebook/Twitter)
 * - Compact: Thumbnail on left, text on right (for multiple previews or messages)
 *
 * Renders title, description, domain, favicon, and image.
 * Clicking the card opens the URL in a new tab.
 */

import { useState } from 'react';
import { Card, Skeleton } from '@heroui/react';
import { ExternalLink, Globe } from 'lucide-react';
import { YouTubeEmbed } from './YouTubeEmbed';

/* ───────────────────────── Types ───────────────────────── */

export interface LinkPreview {
  url: string;
  title?: string | null;
  description?: string | null;
  image?: string | null;
  image_url?: string | null;
  siteName?: string | null;
  site_name?: string | null;
  favicon_url?: string | null;
  domain?: string | null;
  content_type?: string | null;
  embed_html?: string | null;
}

interface LinkPreviewCardProps {
  preview: LinkPreview;
  compact?: boolean;
}

/* ───────────────────────── Helpers ───────────────────────── */

function safeUrl(url: string): string {
  try {
    const parsed = new URL(url);
    if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') return '#';
    return url;
  } catch {
    return '#';
  }
}

function extractDomain(url: string): string {
  try {
    const hostname = new URL(url).hostname;
    return hostname.replace(/^www\./, '');
  } catch {
    return url;
  }
}

/* ───────────────────────── Skeleton ───────────────────────── */

export function LinkPreviewSkeleton({ compact }: { compact?: boolean }) {
  if (compact) {
    return (
      <div className="flex gap-3 rounded-lg border border-[var(--border-default)] overflow-hidden bg-[var(--surface-elevated)] p-3">
        <Skeleton className="w-20 h-20 rounded-lg flex-shrink-0" />
        <div className="flex-1 min-w-0 space-y-2">
          <Skeleton className="h-4 w-3/4 rounded" />
          <Skeleton className="h-3 w-full rounded" />
          <Skeleton className="h-3 w-1/3 rounded" />
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-[var(--border-default)] overflow-hidden bg-[var(--surface-elevated)]">
      <Skeleton className="w-full h-40 rounded-none" />
      <div className="p-3 space-y-2">
        <Skeleton className="h-3 w-1/4 rounded" />
        <Skeleton className="h-4 w-3/4 rounded" />
        <Skeleton className="h-3 w-full rounded" />
        <Skeleton className="h-3 w-1/2 rounded" />
      </div>
    </div>
  );
}

/* ───────────────────────── Component ───────────────────────── */

export function LinkPreviewCard({ preview, compact = false }: LinkPreviewCardProps) {
  const [imageError, setImageError] = useState(false);

  const title = preview.title;
  const description = preview.description;
  const imageUrl = preview.image || preview.image_url;
  const siteName = preview.siteName || preview.site_name;
  const domain = preview.domain || extractDomain(preview.url);
  const faviconUrl = preview.favicon_url;
  const contentType = preview.content_type;
  const embedHtml = preview.embed_html;

  // If this is a video embed (YouTube), render the embed component
  if (contentType === 'video' && embedHtml) {
    return (
      <YouTubeEmbed
        embedUrl={embedHtml}
        thumbnailUrl={imageUrl || undefined}
        title={title || 'Video'}
      />
    );
  }

  const showImage = imageUrl && !imageError;

  if (compact) {
    return (
      <a
        href={safeUrl(preview.url)}
        target="_blank"
        rel="noopener noreferrer"
        className="block group/link"
        onClick={(e) => e.stopPropagation()}
      >
        <Card
          shadow="none"
          className="flex-row overflow-hidden border border-[var(--border-default)] bg-[var(--surface-elevated)] hover:shadow-md hover:-translate-y-0.5 transition-all duration-200"
        >
          {/* Thumbnail */}
          {showImage && (
            <div className="w-20 h-20 flex-shrink-0 overflow-hidden">
              <img
                src={safeUrl(imageUrl!)}
                alt={title ? `Preview for ${title}` : `Preview from ${domain}`}
                className="w-full h-full object-cover"
                loading="lazy"
                onError={() => setImageError(true)}
              />
            </div>
          )}

          {/* Text */}
          <div className="flex-1 min-w-0 p-2.5">
            {/* Site name + favicon */}
            <div className="flex items-center gap-1.5 mb-1">
              {faviconUrl ? (
                <img
                  src={safeUrl(faviconUrl!)}
                  alt={`${siteName || domain} icon`}
                  className="w-3.5 h-3.5 rounded-sm flex-shrink-0"
                  loading="lazy"
                  onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                />
              ) : (
                <Globe className="w-3 h-3 text-[var(--text-subtle)] flex-shrink-0" aria-hidden="true" />
              )}
              <span className="text-[10px] text-[var(--text-subtle)] uppercase tracking-wide truncate">
                {siteName || domain}
              </span>
            </div>

            {title && (
              <p className="text-xs font-semibold text-[var(--text-primary)] line-clamp-2 leading-snug">
                {title}
              </p>
            )}

            {description && (
              <p className="text-[11px] text-[var(--text-muted)] line-clamp-1 mt-0.5">
                {description}
              </p>
            )}
          </div>

          {/* External link icon */}
          <div className="flex items-center pr-2.5 opacity-0 group-hover/link:opacity-100 transition-opacity">
            <ExternalLink className="w-3.5 h-3.5 text-[var(--text-subtle)]" aria-hidden="true" />
          </div>
        </Card>
      </a>
    );
  }

  // Large layout
  return (
    <a
      href={safeUrl(preview.url)}
      target="_blank"
      rel="noopener noreferrer"
      className="block group/link"
      onClick={(e) => e.stopPropagation()}
    >
      <Card
        shadow="none"
        className="overflow-hidden border border-[var(--border-default)] bg-[var(--surface-elevated)] hover:shadow-md hover:-translate-y-0.5 transition-all duration-200"
      >
        {/* Image */}
        {showImage && (
          <div className="w-full overflow-hidden" style={{ aspectRatio: '2 / 1' }}>
            <img
              src={safeUrl(imageUrl!)}
              alt={title ? `Preview for ${title}` : `Preview from ${domain}`}
              className="w-full h-full object-cover group-hover/link:scale-[1.02] transition-transform duration-500"
              loading="lazy"
              onError={() => setImageError(true)}
            />
          </div>
        )}

        {/* Text content */}
        <div className="p-3">
          {/* Site name + favicon */}
          <div className="flex items-center gap-1.5 mb-1.5">
            {faviconUrl ? (
              <img
                src={safeUrl(faviconUrl)}
                alt={`${siteName || domain} icon`}
                className="w-4 h-4 rounded-sm flex-shrink-0"
                loading="lazy"
                onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
              />
            ) : (
              <Globe className="w-3.5 h-3.5 text-[var(--text-subtle)] flex-shrink-0" aria-hidden="true" />
            )}
            <span className="text-[11px] text-[var(--text-subtle)] uppercase tracking-wide truncate">
              {siteName || domain}
            </span>
          </div>

          {title && (
            <p className="text-sm font-semibold text-[var(--text-primary)] line-clamp-2 leading-snug group-hover/link:text-[var(--color-primary)] transition-colors">
              {title}
            </p>
          )}

          {description && (
            <p className="text-xs text-[var(--text-muted)] line-clamp-3 mt-1 leading-relaxed">
              {description}
            </p>
          )}

          {/* Domain line */}
          <div className="flex items-center gap-1.5 mt-2">
            <ExternalLink className="w-3 h-3 text-[var(--text-subtle)]" aria-hidden="true" />
            <span className="text-[11px] text-[var(--text-subtle)]">
              {domain}
            </span>
          </div>
        </div>
      </Card>
    </a>
  );
}

export default LinkPreviewCard;
