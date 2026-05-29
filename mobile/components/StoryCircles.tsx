// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { ScrollView, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Surface } from 'heroui-native';

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
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-3 px-4 py-1">
      {user ? (
        <HeroButton
          className="w-16 items-center"
          variant="ghost"
          feedbackVariant="scale"
          onPress={() => onPress(user.id)}
          accessibilityLabel={t('stories.you')}
        >
          <Surface variant="default" className="relative h-16 w-16 items-center justify-center rounded-full p-1">
            <Avatar uri={user.avatar_url ?? null} name={displayName || null} size={56} />
            <View
              className="absolute -bottom-0.5 -right-0.5 h-5 w-5 items-center justify-center rounded-full border-2 border-background"
              style={{ backgroundColor: primary }}
            >
              <Ionicons name="add" size={14} color="#fff" />
            </View>
          </Surface>
          <Text className="mt-1 max-w-[60px] text-center text-[11px] text-muted-foreground" numberOfLines={1}>
            {t('stories.you')}
          </Text>
        </HeroButton>
      ) : null}

      {members.map((member) => (
        <HeroButton
          key={member.id}
          className="w-16 items-center"
          variant="ghost"
          feedbackVariant="scale"
          onPress={() => onPress(member.id)}
          accessibilityLabel={member.name || t('stories.member')}
        >
          <Surface variant="default" className="h-16 w-16 items-center justify-center rounded-full p-1">
            <Avatar uri={member.avatar ?? null} name={member.name || null} size={56} />
          </Surface>
          <Text className="mt-1 max-w-[60px] text-center text-[11px] text-muted-foreground" numberOfLines={1}>
            {(member.name || '').split(' ')[0] || t('stories.memberInitial')}
          </Text>
        </HeroButton>
      ))}
    </ScrollView>
  );
}
