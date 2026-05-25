// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useColorScheme } from 'react-native';

export const LIGHT = {
  bg: '#F8F9FA',
  surface: '#FFFFFF',
  border: '#E4E4E7',
  borderSubtle: '#F0F0F0',
  text: '#11181C',
  textSecondary: '#687076',
  textMuted: '#9BA1A6',
  onPrimary: '#FFFFFF',
  overlay: 'rgba(0,0,0,0.5)',
  error: '#DC2626',
  errorBg: '#FEE2E2',
  success: '#065F46',
  successBg: '#D1FAE5',
  info: '#1E40AF',
  infoBg: '#DBEAFE',
  warning: '#D97706',
} as const;

export const DARK = {
  bg: '#0F0F0F',
  surface: '#1C1C1E',
  border: '#2C2C2E',
  borderSubtle: '#2C2C2E',
  text: '#F2F2F7',
  textSecondary: '#98989F',
  textMuted: '#636366',
  onPrimary: '#FFFFFF',
  overlay: 'rgba(0,0,0,0.5)',
  error: '#FF453A',
  errorBg: '#2D1B1B',
  success: '#4ADE80',
  successBg: '#052E16',
  info: '#60A5FA',
  infoBg: '#1E3A5F',
  warning: '#FBBF24',
} as const;

export type Theme = { [K in keyof typeof LIGHT]: string };

export function useTheme(): Theme {
  const scheme = useColorScheme();
  return (scheme === 'dark' ? DARK : LIGHT) as Theme;
}
