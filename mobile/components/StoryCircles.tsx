// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Pressable, ScrollView, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Surface } from 'heroui-native';

import { useTranslation } from 'react-i18next';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
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

  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-3 px-4 py-1.5">
      {user ? (
        <Pressable
          className="w-16 items-center"
          onPress={() => onPress(user.id)}
          accessibilityLabel={t('stories.you')}
          accessibilityRole="button"
          style={({ pressed }) => ({ opacity: pressed ? 0.78 : 1 })}
        >
          <Surface
            variant="secondary"
            className="relative h-16 w-16 items-center justify-center overflow-hidden rounded-full p-1"
            style={{ borderWidth: 1, borderColor: withAlpha(primary, 0.36) }}
          >
            <Avatar uri={user.avatar_url ?? null} name={displayName || null} size={56} />
            <View
              className="absolute -bottom-0.5 -right-0.5 h-5 w-5 items-center justify-center rounded-full border-2 border-background"
              style={{ backgroundColor: primary }}
            >
              <Ionicons name="add" size={14} color="#fff" />
            </View>
          </Surface>
          <Text className="mt-1 max-w-[60px] text-center text-[11px] font-semibold" style={{ color: theme.textSecondary }} numberOfLines={1}>
            {t('stories.you')}
          </Text>
        </Pressable>
      ) : null}

      {members.map((member) => (
        <Pressable
          key={member.id}
          className="w-16 items-center"
          onPress={() => onPress(member.id)}
          accessibilityLabel={member.name || t('stories.member')}
          accessibilityRole="button"
          style={({ pressed }) => ({ opacity: pressed ? 0.78 : 1 })}
        >
          <Surface
            variant="secondary"
            className="h-16 w-16 items-center justify-center overflow-hidden rounded-full p-1"
            style={{ borderWidth: 1, borderColor: theme.borderSubtle }}
          >
            <Avatar uri={member.avatar ?? null} name={member.name || null} size={56} />
          </Surface>
          <Text className="mt-1 max-w-[60px] text-center text-[11px] font-semibold" style={{ color: theme.textSecondary }} numberOfLines={1}>
            {(member.name || '').split(' ')[0] || t('stories.memberInitial')}
          </Text>
        </Pressable>
      ))}
    </ScrollView>
  );
}
