// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { GlassCard } from '@/components/ui/GlassCard';
import type { EventVenueAccessibility } from '@/lib/events-api';
import Accessibility from 'lucide-react/icons/accessibility';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import CircleHelp from 'lucide-react/icons/circle-help';
import XCircle from 'lucide-react/icons/circle-x';
import { useTranslation } from 'react-i18next';

const FEATURES: Array<{
  key: keyof Pick<EventVenueAccessibility,
    | 'step_free_access'
    | 'accessible_toilet'
    | 'hearing_loop'
    | 'quiet_space'
    | 'seating_available'
    | 'accessible_parking'>;
  label: string;
}> = [
  { key: 'step_free_access', label: 'features.step_free_access' },
  { key: 'accessible_toilet', label: 'features.accessible_toilet' },
  { key: 'hearing_loop', label: 'features.hearing_loop' },
  { key: 'quiet_space', label: 'features.quiet_space' },
  { key: 'seating_available', label: 'features.seating_available' },
  { key: 'accessible_parking', label: 'features.accessible_parking' },
];

function state(value: boolean | null): 'yes' | 'no' | 'unknown' {
  if (value === true) return 'yes';
  if (value === false) return 'no';
  return 'unknown';
}

function StateIcon({ value }: { value: boolean | null }) {
  if (value === true) return <CheckCircle className="h-4 w-4 text-success" aria-hidden="true" />;
  if (value === false) return <XCircle className="h-4 w-4 text-danger" aria-hidden="true" />;
  return <CircleHelp className="h-4 w-4 text-theme-subtle" aria-hidden="true" />;
}

interface EventVenueAccessibilityCardProps {
  profile?: EventVenueAccessibility;
}

export function EventVenueAccessibilityCard({ profile }: EventVenueAccessibilityCardProps) {
  const { t } = useTranslation('event_accessibility');
  if (!profile?.provided) return null;

  const details = [
    ['parking_details', profile.parking_details],
    ['transit_details', profile.transit_details],
    ['assistance_contact', profile.assistance_contact],
    ['notes', profile.notes],
  ] as const;

  return (
    <section aria-labelledby="event-venue-accessibility-title">
      <GlassCard className="space-y-4 p-5">
      <div>
        <h2 id="event-venue-accessibility-title" className="flex items-center gap-2 text-lg font-semibold text-theme-primary">
          <Accessibility className="h-5 w-5 text-primary" aria-hidden="true" />
          {t('detail.title')}
        </h2>
        <p className="mt-1 text-sm text-theme-muted">{t('detail.intro')}</p>
      </div>

      <ul className="grid gap-2 sm:grid-cols-2" aria-label={t('detail.features_label')}>
        {FEATURES.map((feature) => {
          const value = profile[feature.key];
          const status = state(value);
          return (
            <li key={feature.key} className="flex items-start gap-2 rounded-lg bg-theme-elevated p-3">
              <StateIcon value={value} />
              <span className="min-w-0">
                <span className="block text-sm font-medium text-theme-primary">{t(feature.label)}</span>
                <span className="block text-xs text-theme-muted">{t(`status.${status}`)}</span>
              </span>
            </li>
          );
        })}
      </ul>

      {details.some(([, value]) => value !== null) && (
        <dl className="space-y-3 border-t border-theme-default pt-4">
          {details.map(([key, value]) => value ? (
            <div key={key}>
              <dt className="text-sm font-medium text-theme-primary">{t(`detail.${key}`)}</dt>
              <dd className="mt-1 whitespace-pre-wrap text-sm text-theme-muted">{value}</dd>
            </div>
          ) : null)}
        </dl>
      )}
      </GlassCard>
    </section>
  );
}
