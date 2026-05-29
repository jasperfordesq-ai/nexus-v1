// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/** Fixed chart palette for categorical data that should stay stable across themes. */
export const CHART_COLORS = [
  '#6366f1', // indigo
  '#8b5cf6', // violet
  '#06b6d4', // cyan
  '#10b981', // emerald
  '#f59e0b', // amber
  '#ef4444', // red
  '#ec4899', // pink
  '#84cc16', // lime
];

/** Fixed semantic chart colors retained for reports that need export-stable colors. */
export const CHART_COLOR_MAP = {
  primary: '#6366f1',
  secondary: '#8b5cf6',
  success: '#10b981',
  warning: '#f59e0b',
  danger: '#ef4444',
  info: '#06b6d4',
  purple: '#7828c8',
  primaryLight: '#a78bfa',
  dangerAlt: '#f31260',
  warningAlt: '#f5a524',
} as const;

export const CHART_TOKEN_COLORS = {
  primary: 'var(--color-primary, #6366f1)',
  secondary: 'var(--color-secondary, #8b5cf6)',
  accent: 'var(--color-accent, #06b6d4)',
  success: 'var(--color-success, #10b981)',
  warning: 'var(--color-warning, #f59e0b)',
  danger: 'var(--color-error, #ef4444)',
  info: 'var(--color-info, #3b82f6)',
  muted: 'var(--text-muted, #64748b)',
  foreground: 'var(--foreground, #0f172a)',
  surface: 'var(--surface-solid, var(--surface-base, #ffffff))',
  surfaceAlt: 'var(--surface-elevated, #f8fafc)',
  border: 'var(--border-default, #e5e7eb)',
} as const;

export type ChartTokenColor = keyof typeof CHART_TOKEN_COLORS;

export function chartTokenColor(token: ChartTokenColor): string {
  return CHART_TOKEN_COLORS[token];
}
