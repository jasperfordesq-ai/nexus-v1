// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * WhyShown — Popover explaining why a post appears in the user's feed.
 *
 * Infers top reasons from visible item data (engagement, type, freshness, media).
 * Renders as a subtle info icon that opens a HeroUI Popover with bullet points.
 * Only renders in "For You" (ranking) mode, not chronological.
 */

import { Popover, PopoverTrigger, PopoverContent, Button } from '@heroui/react';
import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { FeedItem } from './types';

interface WhyShownProps {
  item: FeedItem;
  feedMode: 'ranking' | 'recent';
}

/** Infer top reasons from visible item attributes */
function inferReasons(item: FeedItem): string[] {
  const reasons: Array<{ key: string; weight: number }> = [];

  // High engagement
  if ((item.likes_count + item.comments_count) >= 5) {
    reasons.push({ key: 'why_shown.popular', weight: 3 });
  } else if ((item.likes_count + item.comments_count) >= 2) {
    reasons.push({ key: 'why_shown.some_engagement', weight: 1.5 });
  }

  // Content type boost
  const communityTypes = ['event', 'challenge', 'poll', 'volunteer', 'goal'];
  if (communityTypes.includes(item.type)) {
    reasons.push({ key: 'why_shown.community_content', weight: 2 });
  }

  // Freshness (< 4 hours)
  const hoursAgo = (Date.now() - new Date(item.created_at).getTime()) / (1000 * 60 * 60);
  if (hoursAgo < 4) {
    reasons.push({ key: 'why_shown.fresh', weight: 2.5 });
  }

  // Rich media
  if (item.media && item.media.length > 0) {
    reasons.push({ key: 'why_shown.has_media', weight: 1.2 });
  }

  // Has reactions (diverse engagement)
  if (item.reactions && item.reactions.total >= 3) {
    reasons.push({ key: 'why_shown.reactions', weight: 1.8 });
  }

  // Sort by weight, take top 3
  reasons.sort((a, b) => b.weight - a.weight);
  return reasons.slice(0, 3).map((r) => r.key);
}

export function WhyShown({ item, feedMode }: WhyShownProps) {
  const { t } = useTranslation('feed');

  // Only show in ranking mode
  if (feedMode !== 'ranking') return null;

  const reasons = item.ranking_reasons ?? inferReasons(item);
  if (reasons.length === 0) return null;

  return (
    <Popover placement="bottom" offset={4}>
      <PopoverTrigger>
        <Button
          isIconOnly
          variant="light"
          size="sm"
          className="text-[var(--text-subtle)] hover:text-[var(--text-muted)] transition-colors opacity-0 group-hover:opacity-100 focus-visible:opacity-100 min-w-0 min-h-0 w-auto h-auto p-0.5"
          aria-label={t('why_shown.label', 'Why am I seeing this?')}
        >
          <Info className="w-3.5 h-3.5" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="p-3 max-w-[240px]">
        <p className="text-xs font-semibold text-[var(--text-primary)] mb-2">
          {t('why_shown.title', 'Why you\'re seeing this')}
        </p>
        <ul className="space-y-1">
          {reasons.map((reason, i) => (
            <li key={i} className="text-xs text-[var(--text-muted)] flex items-start gap-1.5">
              <span className="text-[var(--color-primary)] mt-0.5">•</span>
              <span>{t(reason)}</span>
            </li>
          ))}
        </ul>
      </PopoverContent>
    </Popover>
  );
}
