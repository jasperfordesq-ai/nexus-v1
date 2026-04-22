// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Branding Picker
 * Color picker for group custom branding (primary + accent colors).
 */

import { useState, useCallback } from 'react';
import Paintbrush from 'lucide-react/icons/paintbrush';
import { GlassCard } from '@/components/ui';
import { useTranslation } from 'react-i18next';

interface GroupBrandingPickerProps {
  primaryColor: string | null;
  accentColor: string | null;
  onChange: (primary: string, accent: string) => void;
}

const DEFAULT_PRIMARY = '#0070f3';
const DEFAULT_ACCENT = '#7928ca';

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
        <Paintbrush size={18} className="text-primary" />
        <h3 className="text-base font-semibold text-foreground">
          {t('branding.title', 'Group Branding')}
        </h3>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {/* Primary Color */}
        <div className="space-y-2">
          <label
            htmlFor="group-primary-color"
            className="block text-sm font-medium text-default-700"
          >
            {t('branding.primary_color', 'Primary Color')}
          </label>
          <div className="flex items-center gap-3">
            <input
              id="group-primary-color"
              type="color"
              value={primary}
              onChange={(e) => handlePrimaryChange(e.target.value)}
              className="w-10 h-10 rounded-lg border border-default-200 cursor-pointer"
              aria-label={t('branding.primary_color', 'Primary Color')}
            />
            <span className="text-sm text-default-500 font-mono uppercase">
              {primary}
            </span>
          </div>
        </div>

        {/* Accent Color */}
        <div className="space-y-2">
          <label
            htmlFor="group-accent-color"
            className="block text-sm font-medium text-default-700"
          >
            {t('branding.accent_color', 'Accent Color')}
          </label>
          <div className="flex items-center gap-3">
            <input
              id="group-accent-color"
              type="color"
              value={accent}
              onChange={(e) => handleAccentChange(e.target.value)}
              className="w-10 h-10 rounded-lg border border-default-200 cursor-pointer"
              aria-label={t('branding.accent_color', 'Accent Color')}
            />
            <span className="text-sm text-default-500 font-mono uppercase">
              {accent}
            </span>
          </div>
        </div>
      </div>

      {/* Preview swatch strip */}
      <div className="space-y-2">
        <p className="text-xs text-default-400">
          {t('branding.preview', 'Preview')}
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
