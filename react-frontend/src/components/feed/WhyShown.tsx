// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * WhyShown — Popover explaining why a post appears in the user's feed.
 *
 * Shows backend-provided ranking_reasons when available. Falls back to a single
 * neutral "Ranked for you based on your activity" message — never fabricates
 * reasons from client-side engagement heuristics.
 * Only renders in "For You" (ranking) mode, not chronological.
 */

import { Popover, PopoverTrigger, PopoverContent, Button } from '@heroui/react';
import Info from 'lucide-react/icons/info';
import { useTranslation } from 'react-i18next';
import type { FeedItem } from './types';

interface WhyShownProps {
  item: FeedItem;
  feedMode: 'ranking' | 'recent';
}

export function WhyShown({ item, feedMode }: WhyShownProps) {
  const { t } = useTranslation('feed');

  // Only show in ranking mode
  if (feedMode !== 'ranking') return null;

  // Use backend-provided reasons exclusively. If absent or empty, show a single
  // neutral reason — never fabricate signals from client-side engagement data.
  const backendReasons = item.ranking_reasons && item.ranking_reasons.length > 0
    ? item.ranking_reasons
    : null;

  const reasons: string[] = backendReasons ?? [t('why_shown.default_reason')];

  return (
    <Popover placement="bottom" offset={4}>
      <PopoverTrigger>
        <Button
          isIconOnly
          variant="light"
          size="sm"
          className="text-[var(--text-subtle)] hover:text-[var(--text-muted)] transition-colors opacity-70 sm:opacity-40 sm:group-hover:opacity-100 focus-visible:opacity-100 min-w-0 min-h-0 w-auto h-auto p-0.5"
          aria-label={t('why_shown.label')}
        >
          <Info className="w-3.5 h-3.5" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="p-3 max-w-[240px]">
        <p className="text-xs font-semibold text-[var(--text-primary)] mb-2">
          {t('why_shown.title')}
        </p>
        <ul className="space-y-1">
          {reasons.map((reason, i) => (
            <li key={i} className="text-xs text-[var(--text-muted)] flex items-start gap-1.5">
              <span className="text-[var(--color-primary)] mt-0.5">•</span>
              {/* Backend reasons are pre-translated strings; fallback is already translated above */}
              <span>{reason}</span>
            </li>
          ))}
        </ul>
      </PopoverContent>
    </Popover>
  );
}
