// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Branding Picker
 * Color picker for group custom branding (primary + accent colors).
 *
 * Uses the HeroUI v3 ColorPicker (branded popover: area + hue slider + hex field)
 * instead of the native <input type="color">. Values are stored as hex strings;
 * parseColor() adapts them to the Color objects the picker works with, and
 * Color.toString('hex') converts back on change.
 */

import { useState, useCallback, type ReactNode } from 'react';
import Paintbrush from 'lucide-react/icons/paintbrush';
import { GlassCard, ColorPicker } from '@/components/ui';
import {
  ColorArea,
  ColorField,
  ColorSlider,
  ColorSwatch,
  Label,
  parseColor,
} from '@heroui/react';
import { useTranslation } from 'react-i18next';

interface GroupBrandingPickerProps {
  primaryColor: string | null;
  accentColor: string | null;
  onChange: (primary: string, accent: string) => void;
}

const DEFAULT_PRIMARY = '#0070f3';
const DEFAULT_ACCENT = '#7928ca';

/** A single labelled brand-colour picker. */
function BrandColor({ label, value, onChange }: { label: ReactNode; value: string; onChange: (hex: string) => void }) {
  const { t } = useTranslation('groups');
  return (
    <ColorPicker value={parseColor(value)} onChange={(color) => onChange(color.toString('hex'))}>
      <ColorPicker.Trigger className="flex items-center gap-3">
        <ColorSwatch size="lg" className="h-10 w-10 rounded-lg border border-border" />
        <span className="text-left">
          <Label className="block cursor-pointer text-sm font-medium text-foreground">{label}</Label>
          <span className="font-mono text-sm uppercase text-muted">{value}</span>
        </span>
      </ColorPicker.Trigger>
      <ColorPicker.Popover className="gap-2">
        <ColorArea
          aria-label={typeof label === 'string' ? label : undefined}
          className="max-w-full"
          colorSpace="hsb"
          xChannel="saturation"
          yChannel="brightness"
        >
          <ColorArea.Thumb />
        </ColorArea>
        <ColorSlider channel="hue" className="gap-1 px-1" colorSpace="hsb" aria-label={t('branding.hue')}>
          <ColorSlider.Track>
            <ColorSlider.Thumb />
          </ColorSlider.Track>
        </ColorSlider>
        <ColorField aria-label={typeof label === 'string' ? label : undefined}>
          <ColorField.Group variant="secondary">
            <ColorField.Prefix>
              <ColorSwatch size="xs" />
            </ColorField.Prefix>
            <ColorField.Input />
          </ColorField.Group>
        </ColorField>
      </ColorPicker.Popover>
    </ColorPicker>
  );
}

export function GroupBrandingPicker({ primaryColor, accentColor, onChange }: GroupBrandingPickerProps) {
  const { t } = useTranslation('groups');

  const [primary, setPrimary] = useState(primaryColor ?? DEFAULT_PRIMARY);
  const [accent, setAccent] = useState(accentColor ?? DEFAULT_ACCENT);

  const handlePrimaryChange = useCallback(
    (value: string) => {
      setPrimary(value);
      onChange(value, accent);
    },
    [accent, onChange]
  );

  const handleAccentChange = useCallback(
    (value: string) => {
      setAccent(value);
      onChange(primary, value);
    },
    [primary, onChange]
  );

  return (
    <GlassCard className="p-5 space-y-5">
      <div className="flex items-center gap-2 mb-1">
        <Paintbrush aria-hidden="true" size={18} className="text-accent" />
        <h3 className="text-base font-semibold text-foreground">
          {t('branding.title')}
        </h3>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {/* Primary Color */}
        <div className="space-y-2">
          <BrandColor label={t('branding.primary_color')} value={primary} onChange={handlePrimaryChange} />
        </div>

        {/* Accent Color */}
        <div className="space-y-2">
          <BrandColor label={t('branding.accent_color')} value={accent} onChange={handleAccentChange} />
        </div>
      </div>

      {/* Preview swatch strip */}
      <div className="space-y-2">
        <p className="text-xs text-muted">
          {t('branding.preview')}
        </p>
        <div className="flex rounded-lg overflow-hidden h-8">
          <div className="flex-1" style={{ backgroundColor: primary }} />
          <div
            className="flex-1"
            style={{
              background: `linear-gradient(90deg, ${primary}, ${accent})`,
            }}
          />
          <div className="flex-1" style={{ backgroundColor: accent }} />
        </div>
      </div>
    </GlassCard>
  );
}

export default GroupBrandingPicker;
