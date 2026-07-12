// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Input } from '@/components/ui/Input';
import { Select, SelectItem } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import Accessibility from 'lucide-react/icons/accessibility';
import { useTranslation } from 'react-i18next';

export interface VenueAccessibilityDraft {
  step_free_access: boolean | null;
  accessible_toilet: boolean | null;
  hearing_loop: boolean | null;
  quiet_space: boolean | null;
  seating_available: boolean | null;
  accessible_parking: boolean | null;
  parking_details: string;
  transit_details: string;
  assistance_contact: string;
  notes: string;
}

export const EMPTY_VENUE_ACCESSIBILITY: VenueAccessibilityDraft = {
  step_free_access: null,
  accessible_toilet: null,
  hearing_loop: null,
  quiet_space: null,
  seating_available: null,
  accessible_parking: null,
  parking_details: '',
  transit_details: '',
  assistance_contact: '',
  notes: '',
};

const FEATURES: Array<{
  key: keyof Pick<VenueAccessibilityDraft,
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

function statusKey(value: boolean | null): 'yes' | 'no' | 'unknown' {
  if (value === true) return 'yes';
  if (value === false) return 'no';
  return 'unknown';
}

function statusValue(value: string): boolean | null {
  if (value === 'yes') return true;
  if (value === 'no') return false;
  return null;
}

interface EventVenueAccessibilityFieldsProps {
  value: VenueAccessibilityDraft;
  onChange: (value: VenueAccessibilityDraft) => void;
  isDisabled?: boolean;
}

export function EventVenueAccessibilityFields({
  value,
  onChange,
  isDisabled = false,
}: EventVenueAccessibilityFieldsProps) {
  const { t } = useTranslation('event_accessibility');
  const update = <Key extends keyof VenueAccessibilityDraft>(
    key: Key,
    next: VenueAccessibilityDraft[Key],
  ) => onChange({ ...value, [key]: next });

  return (
    <fieldset
      className="space-y-5 rounded-xl border border-theme-default bg-theme-elevated/40 p-4"
      aria-describedby="event-venue-accessibility-hint"
    >
      <legend className="px-1 text-base font-semibold text-theme-primary">
        <span className="inline-flex items-center gap-2">
          <Accessibility className="h-5 w-5 text-primary" aria-hidden="true" />
          {t('form.title')}
        </span>
      </legend>
      <p id="event-venue-accessibility-hint" className="text-sm text-theme-muted">
        {t('form.hint')}
      </p>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {FEATURES.map((feature) => (
          <Select
            key={feature.key}
            label={t(feature.label)}
            aria-label={t(feature.label)}
            selectedKeys={[statusKey(value[feature.key])]}
            onChange={(event) => update(feature.key, statusValue(event.target.value))}
            isDisabled={isDisabled}
            classNames={{
              trigger: 'bg-theme-surface border-theme-default',
              value: 'text-theme-primary',
              label: 'text-theme-muted',
            }}
          >
            <SelectItem key="unknown" id="unknown">{t('status.unknown')}</SelectItem>
            <SelectItem key="yes" id="yes">{t('status.yes')}</SelectItem>
            <SelectItem key="no" id="no">{t('status.no')}</SelectItem>
          </Select>
        ))}
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Textarea
          label={t('form.parking_details')}
          description={t('form.parking_details_hint')}
          value={value.parking_details}
          onChange={(event) => update('parking_details', event.target.value)}
          maxLength={1000}
          isDisabled={isDisabled}
          classNames={{
            input: 'text-theme-primary',
            inputWrapper: 'bg-theme-surface border-theme-default',
            label: 'text-theme-muted',
          }}
        />
        <Textarea
          label={t('form.transit_details')}
          description={t('form.transit_details_hint')}
          value={value.transit_details}
          onChange={(event) => update('transit_details', event.target.value)}
          maxLength={1000}
          isDisabled={isDisabled}
          classNames={{
            input: 'text-theme-primary',
            inputWrapper: 'bg-theme-surface border-theme-default',
            label: 'text-theme-muted',
          }}
        />
      </div>

      <Input
        label={t('form.assistance_contact')}
        description={t('form.assistance_contact_hint')}
        value={value.assistance_contact}
        onChange={(event) => update('assistance_contact', event.target.value)}
        maxLength={500}
        isDisabled={isDisabled}
        classNames={{
          input: 'text-theme-primary',
          inputWrapper: 'bg-theme-surface border-theme-default',
          label: 'text-theme-muted',
        }}
      />

      <Textarea
        label={t('form.notes')}
        description={t('form.notes_hint')}
        value={value.notes}
        onChange={(event) => update('notes', event.target.value)}
        maxLength={4000}
        isDisabled={isDisabled}
        classNames={{
          input: 'text-theme-primary',
          inputWrapper: 'bg-theme-surface border-theme-default',
          label: 'text-theme-muted',
        }}
      />

      <p className="text-xs text-theme-subtle">{t('form.privacy_note')}</p>
    </fieldset>
  );
}
