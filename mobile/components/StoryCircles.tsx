// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Pressable, ScrollView, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { useTranslation } from 'react-i18next';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
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

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerClassName="px-4 py-2 gap-3"
    >
      {/* "You" circle */}
      {user && (
        <Pressable
          className="items-center w-16"
          onPress={() => onPress(user.id)}
          accessibilityLabel={t('stories.you', { defaultValue: 'You' })}
          accessibilityRole="button"
        >
          <View
            className="w-16 h-16 rounded-full justify-center items-center relative"
            style={{ borderWidth: 2, borderColor: primary }}
          >
            <Avatar uri={user.avatar_url ?? null} name={displayName || null} size={56} />
            <View
              className="absolute -bottom-0.5 -right-0.5 w-5 h-5 rounded-full justify-center items-center border-2 border-background"
              style={{ backgroundColor: primary }}
            >
              <Ionicons name="add" size={14} color="#fff" />
            </View>
          </View>
          <Text className="text-[11px] text-muted-foreground text-center mt-1 max-w-[60px]" numberOfLines={1}>
            {t('stories.you', { defaultValue: 'You' })}
          </Text>
        </Pressable>
      )}

      {/* Member circles */}
      {members.map((member) => (
        <Pressable
          key={member.id}
          className="items-center w-16"
          onPress={() => onPress(member.id)}
          accessibilityLabel={member.name || 'Member'}
          accessibilityRole="button"
        >
          <View
            className="w-16 h-16 rounded-full justify-center items-center"
            style={{ borderWidth: 2, borderColor: primary }}
          >
            <Avatar uri={member.avatar ?? null} name={member.name || null} size={56} />
          </View>
          <Text className="text-[11px] text-muted-foreground text-center mt-1 max-w-[60px]" numberOfLines={1}>
            {(member.name || '').split(' ')[0] || '?'}
          </Text>
        </Pressable>
      ))}
    </ScrollView>
  );
}
