// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo } from 'react';
import { ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { useTranslation } from 'react-i18next';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { SPACING } from '@/lib/styles/spacing';
import Avatar from '@/components/ui/Avatar';

interface StoryMember {
  id: number;
  name: string;
  avatar?: string | null;
}

interface StoryCirclesProps {
  members: StoryMember[];
  onPress: (memberId: number) => void;
}

export default function StoryCircles({ members, onPress }: StoryCirclesProps) {
  const { t } = useTranslation('home');
  const { user, displayName } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme, primary), [theme, primary]);

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.container}
    >
      {/* "You" circle */}
      {user && (
        <TouchableOpacity
          style={styles.circleWrapper}
          onPress={() => onPress(user.id)}
          activeOpacity={0.7}
          accessibilityLabel={t('stories.you', { defaultValue: 'You' })}
          accessibilityRole="button"
        >
          <View style={styles.ring}>
            <Avatar uri={user.avatar_url ?? null} name={displayName || null} size={56} />
            <View style={styles.plusBadge}>
              <Ionicons name="add" size={14} color="#fff" />
            </View>
          </View>
          <Text style={styles.nameText} numberOfLines={1}>
            {t('stories.you', { defaultValue: 'You' })}
          </Text>
        </TouchableOpacity>
      )}

      {/* Member circles */}
      {members.map((member) => (
        <TouchableOpacity
          key={member.id}
          style={styles.circleWrapper}
          onPress={() => onPress(member.id)}
          activeOpacity={0.7}
          accessibilityLabel={member.name || 'Member'}
          accessibilityRole="button"
        >
          <View style={styles.ring}>
            <Avatar uri={member.avatar ?? null} name={member.name || null} size={56} />
          </View>
          <Text style={styles.nameText} numberOfLines={1}>
            {(member.name || '').split(' ')[0] || '?'}
          </Text>
        </TouchableOpacity>
      ))}
    </ScrollView>
  );
}

function makeStyles(theme: Theme, primary: string) {
  return StyleSheet.create({
    container: {
      paddingHorizontal: SPACING.md,
      paddingVertical: SPACING.sm,
      gap: SPACING.sm + 4,
    },
    circleWrapper: {
      alignItems: 'center',
      width: 64,
    },
    ring: {
      width: 64,
      height: 64,
      borderRadius: 32,
      borderWidth: 2,
      borderColor: primary,
      justifyContent: 'center',
      alignItems: 'center',
      position: 'relative',
    },
    plusBadge: {
      position: 'absolute',
      bottom: -2,
      right: -2,
      width: 20,
      height: 20,
      borderRadius: 10,
      backgroundColor: primary,
      justifyContent: 'center',
      alignItems: 'center',
      borderWidth: 2,
      borderColor: theme.bg,
    },
    nameText: {
      fontSize: 11,
      color: theme.textSecondary,
      textAlign: 'center',
      marginTop: SPACING.xs,
      maxWidth: 60,
    },
  });
}
