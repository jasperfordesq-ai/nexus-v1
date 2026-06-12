// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ReactionBar — emoji reaction picker for feed cards (web ReactionPicker parity).
 *
 * Renders a horizontal pill of the 8 platform reaction types. Opened by
 * long-pressing the like button; a quick tap on the like button still
 * toggles the default 'like' reaction without opening the bar.
 */

import { useEffect, useRef } from 'react';
import { Animated, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import type { ReactionType } from '@/lib/api/feed';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

export const REACTION_CONFIGS: { type: ReactionType; emoji: string; labelKey: string }[] = [
  { type: 'like', emoji: '\u{1F44D}', labelKey: 'reaction.like' },
  { type: 'love', emoji: '❤️', labelKey: 'reaction.love' },
  { type: 'laugh', emoji: '\u{1F602}', labelKey: 'reaction.laugh' },
  { type: 'wow', emoji: '\u{1F62E}', labelKey: 'reaction.wow' },
  { type: 'sad', emoji: '\u{1F622}', labelKey: 'reaction.sad' },
  { type: 'celebrate', emoji: '\u{1F389}', labelKey: 'reaction.celebrate' },
  { type: 'clap', emoji: '\u{1F44F}', labelKey: 'reaction.clap' },
  { type: 'time_credit', emoji: '⏰', labelKey: 'reaction.time_credit' },
];

export const REACTION_EMOJI_MAP: Partial<Record<string, string>> = Object.fromEntries(
  REACTION_CONFIGS.map((config) => [config.type, config.emoji]),
);

export default function ReactionBar({
  visible,
  userReaction,
  primary,
  onSelect,
  onDismiss,
}: {
  visible: boolean;
  userReaction: string | null;
  primary: string;
  onSelect: (type: ReactionType) => void;
  onDismiss: () => void;
}) {
  const { t } = useTranslation('home');
  const theme = useTheme();
  const opacity = useRef(new Animated.Value(0)).current;
  const scale = useRef(new Animated.Value(0.8)).current;

  useEffect(() => {
    if (!visible) return;
    opacity.setValue(0);
    scale.setValue(0.8);
    Animated.parallel([
      Animated.timing(opacity, { toValue: 1, duration: 120, useNativeDriver: true }),
      Animated.spring(scale, { toValue: 1, friction: 6, tension: 160, useNativeDriver: true }),
    ]).start();
  }, [opacity, scale, visible]);

  if (!visible) return null;

  return (
    <>
      {/* Backdrop that dismisses the bar when tapping anywhere else on the card */}
      <Pressable
        style={StyleSheet.absoluteFill}
        onPress={onDismiss}
        accessibilityLabel={t('reaction.dismiss')}
      />
      <Animated.View
        style={{ opacity, transform: [{ scale }] }}
        className="absolute bottom-14 left-3 z-10"
        accessibilityRole="menu"
      >
        <View
          className="flex-row items-center gap-0.5 rounded-full px-2 py-1.5"
          style={{
            backgroundColor: theme.surface,
            borderWidth: 1,
            borderColor: theme.borderSubtle,
            shadowColor: '#000',
            shadowOffset: { width: 0, height: 4 },
            shadowOpacity: 0.22,
            shadowRadius: 10,
            elevation: 8,
          }}
        >
          {REACTION_CONFIGS.map((config) => {
            const isActive = userReaction === config.type;
            return (
              <Pressable
                key={config.type}
                onPress={() => onSelect(config.type)}
                accessibilityLabel={t(config.labelKey)}
                accessibilityRole="menuitem"
                accessibilityState={{ selected: isActive }}
                className="size-11 items-center justify-center rounded-full"
                style={isActive ? { backgroundColor: withAlpha(primary, 0.18) } : undefined}
              >
                {config.type === 'time_credit' ? (
                  <Ionicons name="time-outline" size={20} color={primary} />
                ) : (
                  <Text style={{ fontSize: 20 }}>{config.emoji}</Text>
                )}
              </Pressable>
            );
          })}
        </View>
      </Animated.View>
    </>
  );
}
