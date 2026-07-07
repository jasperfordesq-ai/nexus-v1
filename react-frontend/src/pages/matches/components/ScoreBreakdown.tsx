// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import { Disclosure, DisclosureTrigger, DisclosureContent, DisclosureIndicator } from '@/components/ui/Disclosure';
import { Meter } from '@/components/ui/Meter';
import type { ScoreBreakdown as ScoreBreakdownData } from '../types';

export interface ScoreBreakdownProps {
  breakdown: ScoreBreakdownData;
}

function toPercent(value: number | undefined): number {
  if (typeof value !== 'number' || !Number.isFinite(value)) return 0;
  return Math.round(Math.min(Math.max(value, 0), 1) * 100);
}

interface MeterRowProps {
  label: string;
  value: number | undefined;
  indent?: boolean;
}

function MeterRow({ label, value, indent }: MeterRowProps) {
  const percent = toPercent(value);
  return (
    <div className={indent ? 'pl-4' : undefined}>
      <Meter value={percent} minValue={0} maxValue={100} aria-label={label}>
        <div className="flex items-center justify-between gap-2 mb-1">
          <span className={indent ? 'text-xs text-theme-subtle' : 'text-sm font-medium text-theme-primary'}>{label}</span>
          <span className={indent ? 'text-xs text-theme-subtle' : 'text-sm text-theme-secondary'}>{percent}%</span>
        </div>
        <Meter.Track className={indent ? 'h-1' : 'h-1.5'}>
          <Meter.Fill />
        </Meter.Track>
      </Meter>
    </div>
  );
}

/**
 * "Why this score" disclosure — three pillar meters (relevance, feasibility,
 * trust) each with nested per-signal meters. Only meaningful when the API
 * returns score_breakdown, so callers should only render this when present.
 */
export function ScoreBreakdown({ breakdown }: ScoreBreakdownProps) {
  const { t } = useTranslation('matches');

  const pillars = breakdown.pillars ?? { relevance: 0, feasibility: 0, trust: 0 };
  const signals = breakdown.signals;

  return (
    <Disclosure className="border-t border-theme-default pt-2 mt-2">
      <DisclosureTrigger className="flex items-center justify-between gap-2 text-xs font-medium text-theme-subtle hover:text-theme-primary transition-colors">
        {t('breakdown.title')}
        <DisclosureIndicator />
      </DisclosureTrigger>
      <DisclosureContent>
        <div className="space-y-3 pt-3">
          <div className="space-y-1.5">
            <MeterRow label={t('breakdown.pillars.relevance')} value={pillars.relevance} />
            {signals?.relevance ? (
              <div className="space-y-1.5">
                {signals.relevance.category != null && (
                  <MeterRow label={t('breakdown.signals.category')} value={signals.relevance.category} indent />
                )}
                {signals.relevance.skill != null && (
                  <MeterRow label={t('breakdown.signals.skill')} value={signals.relevance.skill} indent />
                )}
                {signals.relevance.semantic != null && (
                  <MeterRow label={t('breakdown.signals.semantic')} value={signals.relevance.semantic} indent />
                )}
              </div>
            ) : null}
          </div>

          <div className="space-y-1.5">
            <MeterRow label={t('breakdown.pillars.feasibility')} value={pillars.feasibility} />
            {signals?.feasibility ? (
              <div className="space-y-1.5">
                {signals.feasibility.proximity != null && (
                  <MeterRow label={t('breakdown.signals.proximity')} value={signals.feasibility.proximity} indent />
                )}
                {signals.feasibility.availability != null && (
                  <MeterRow label={t('breakdown.signals.availability')} value={signals.feasibility.availability} indent />
                )}
                {signals.feasibility.activity != null && (
                  <MeterRow label={t('breakdown.signals.activity')} value={signals.feasibility.activity} indent />
                )}
              </div>
            ) : null}
          </div>

          <div className="space-y-1.5">
            <MeterRow label={t('breakdown.pillars.trust')} value={pillars.trust} />
            {signals?.trust ? (
              <div className="space-y-1.5">
                {signals.trust.reviews != null && (
                  <MeterRow label={t('breakdown.signals.reviews')} value={signals.trust.reviews} indent />
                )}
                {signals.trust.trust_tier != null && (
                  <MeterRow label={t('breakdown.signals.trust_tier')} value={signals.trust.trust_tier} indent />
                )}
                {signals.trust.completion != null && (
                  <MeterRow label={t('breakdown.signals.completion')} value={signals.trust.completion} indent />
                )}
              </div>
            ) : null}
          </div>
        </div>
      </DisclosureContent>
    </Disclosure>
  );
}

export default ScoreBreakdown;
