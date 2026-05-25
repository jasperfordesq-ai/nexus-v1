// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Pressable, View, Text } from 'react-native';
import { router } from 'expo-router';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { type Member } from '@/lib/api/members';
import Avatar from '@/components/ui/Avatar';
import Card from '@/components/ui/Card';
import { usePrimaryColor } from '@/lib/hooks/useTenant';

interface MemberCardProps {
  member: Member;
}

export default function MemberCard({ member }: MemberCardProps) {
  const { t } = useTranslation('members');
  const primary = usePrimaryColor();

  const displayName = member.name?.trim() ||
    [member.first_name, member.last_name].filter(Boolean).join(' ') ||
    t('common:labels.member');

  return (
    <Pressable
      className="mx-4 my-1.5"
      onPress={() => {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
        router.push({ pathname: '/(modals)/member-profile', params: { id: String(member.id) } });
      }}
      accessibilityRole="button"
      accessibilityLabel={displayName}
    >
      <Card>
        <View className="flex-row items-start gap-3">
          <Avatar uri={member.avatar ?? member.avatar_url ?? null} name={displayName} size={52} />
          <View className="flex-1 gap-1">
            <Text className="text-base font-semibold text-foreground">{displayName}</Text>
            {member.tagline && (
              <Text className="text-sm text-muted-foreground" numberOfLines={2}>{member.tagline}</Text>
            )}
          </View>
          <View className="items-center min-w-[48px]">
            <Text className="text-xl font-bold" style={{ color: primary }}>
              {(member.total_hours_given ?? 0).toFixed(0)}
            </Text>
            <Text className="text-[11px] text-muted-foreground">{t('hrsGiven')}</Text>
          </View>
        </View>
      </Card>
    </Pressable>
  );
}
