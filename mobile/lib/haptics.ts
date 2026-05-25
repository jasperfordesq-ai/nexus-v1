// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import * as Haptics from 'expo-haptics';
import { Platform } from 'react-native';

function isNativePlatform(): boolean {
  return Platform.OS === 'ios' || Platform.OS === 'android';
}

export const ImpactFeedbackStyle = Haptics.ImpactFeedbackStyle;
export const NotificationFeedbackType = Haptics.NotificationFeedbackType;

export async function impactAsync(style: Haptics.ImpactFeedbackStyle): Promise<void> {
  if (!isNativePlatform()) return;

  try {
    await Haptics.impactAsync(style);
  } catch {
    // Haptics are non-essential and may be unavailable in previews or simulators.
  }
}

export async function notificationAsync(type: Haptics.NotificationFeedbackType): Promise<void> {
  if (!isNativePlatform()) return;

  try {
    await Haptics.notificationAsync(type);
  } catch {
    // Haptics are non-essential and may be unavailable in previews or simulators.
  }
}

export async function selectionAsync(): Promise<void> {
  if (!isNativePlatform()) return;

  try {
    await Haptics.selectionAsync();
  } catch {
    // Haptics are non-essential and may be unavailable in previews or simulators.
  }
}
