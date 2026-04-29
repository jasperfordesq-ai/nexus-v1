// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AppearanceSettings — Accent color, font size, density, and high contrast controls.
 *
 * All changes apply immediately (live preview) and are persisted via ThemeContext
 * to both localStorage and the backend API.
 */

import { Button, ButtonGroup, Switch } from '@heroui/react';
import Check from 'lucide-react/icons/check';
import { useTheme } from '@/contexts';
import { useTranslation } from 'react-i18next';
import type { FontSize, Density } from '@/contexts/ThemeContext';

// ─────────────────────────────────────────────────────────────────────────────
// Accent color presets
// ─────────────────────────────────────────────────────────────────────────────

const ACCENT_COLORS = [
  { name: 'indigo', hex: '#6366f1' },
  { name: 'purple', hex: '#a855f7' },
  { name: 'blue', hex: '#3b82f6' },
  { name: 'teal', hex: '#14b8a6' },
  { name: 'green', hex: '#22c55e' },
  { name: 'amber', hex: '#f59e0b' },
  { name: 'orange', hex: '#f97316' },
  { name: 'rose', hex: '#f43f5e' },
  { name: 'pink', hex: '#ec4899' },
  { name: 'cyan', hex: '#06b6d4' },
] as const;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function AppearanceSettings() {
  const { t } = useTranslation('settings');
  const {
    accentColor,
    fontSize,
    density,
    largeText,
    highContrast,
    reducedMotion,
    simplifiedLayout,
    setAccentColor,
    setFontSize,
    setDensity,
    setLargeText,
    setHighContrast,
    setReducedMotion,
    setSimplifiedLayout,
  } = useTheme();

  return (
    <div className="space-y-6">
      {/* ── Accent Color ── */}
      <div>
        <p className="text-sm font-medium text-theme-primary mb-1">
          {t('appearance_prefs.accent_color')}
        </p>
        <p className="text-xs text-theme-muted mb-3">
          {t('appearance_prefs.accent_color_desc')}
        </p>
        <div className="flex flex-wrap gap-2">
          {ACCENT_COLORS.map((color) => {
            const isSelected = accentColor === color.hex;
            return (
              <Button
                key={color.name}
                isIconOnly
                size="sm"
                aria-label={t('appearance_prefs.select_color', { color: color.name })}
                className="w-9 h-9 min-w-0 rounded-full border-2 transition-transform"
                style={{
                  backgroundColor: color.hex,
                  borderColor: isSelected ? color.hex : 'transparent',
                  transform: isSelected ? 'scale(1.15)' : 'scale(1)',
                  boxShadow: isSelected ? `0 0 0 2px var(--background), 0 0 0 4px ${color.hex}` : 'none',
                }}
                onPress={() => setAccentColor(color.hex)}
              >
                {isSelected && (
                  <Check className="w-4 h-4 text-white drop-shadow-sm" aria-hidden="true" />
                )}
              </Button>
            );
          })}
        </div>
      </div>

      {/* ── Font Size ── */}
      <div className="pt-4 border-t border-theme-default">
        <p className="text-sm font-medium text-theme-primary mb-1">
          {t('appearance_prefs.font_size')}
        </p>
        <p className="text-xs text-theme-muted mb-3">
          {t('appearance_prefs.font_size_desc')}
        </p>
        <ButtonGroup>
          {(['small', 'medium', 'large'] as const).map((size) => (
            <Button
              key={size}
              size="sm"
              variant={fontSize === size ? 'solid' : 'flat'}
              className={fontSize === size ? 'bg-indigo-500 text-white' : 'text-theme-secondary'}
              onPress={() => setFontSize(size as FontSize)}
            >
              {t(`appearance_prefs.font_${size}`)}
            </Button>
          ))}
        </ButtonGroup>
      </div>

      {/* ── Density ── */}
      <div className="pt-4 border-t border-theme-default">
        <p className="text-sm font-medium text-theme-primary mb-1">
          {t('appearance_prefs.density')}
        </p>
        <p className="text-xs text-theme-muted mb-3">
          {t('appearance_prefs.density_desc')}
        </p>
        <ButtonGroup>
          {(['compact', 'comfortable', 'spacious'] as const).map((d) => (
            <Button
              key={d}
              size="sm"
              variant={density === d ? 'solid' : 'flat'}
              className={density === d ? 'bg-indigo-500 text-white' : 'text-theme-secondary'}
              onPress={() => setDensity(d as Density)}
            >
              {t(`appearance_prefs.density_${d}`)}
            </Button>
          ))}
        </ButtonGroup>
      </div>

      {/* ── High Contrast ── */}
      <div className="pt-4 border-t border-theme-default">
        <p className="text-sm font-medium text-theme-primary mb-1">
          {t('appearance_prefs.accessibility_profile')}
        </p>
        <p className="text-xs text-theme-muted mb-4">
          {t('appearance_prefs.accessibility_profile_desc')}
        </p>
        <div className="space-y-4">
          {[
            {
              key: 'large_text',
              selected: largeText,
              onChange: setLargeText,
            },
            {
              key: 'high_contrast',
              selected: highContrast,
              onChange: setHighContrast,
            },
            {
              key: 'reduced_motion',
              selected: reducedMotion,
              onChange: setReducedMotion,
            },
            {
              key: 'simplified_layout',
              selected: simplifiedLayout,
              onChange: setSimplifiedLayout,
            },
          ].map(({ key, selected, onChange }) => (
            <div key={key} className="flex items-center justify-between gap-4">
              <div>
                <p className="text-sm font-medium text-theme-primary">
                  {t(`appearance_prefs.${key}`)}
                </p>
                <p className="text-xs text-theme-muted mt-0.5">
                  {t(`appearance_prefs.${key}_desc`)}
                </p>
              </div>
              <Switch
                isSelected={selected}
                onValueChange={onChange}
                aria-label={t(`appearance_prefs.${key}`)}
                size="sm"
              />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export default AppearanceSettings;
