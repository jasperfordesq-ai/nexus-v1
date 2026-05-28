// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Pressable, Text, View } from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { Card as HeroCard, Chip, Separator, Surface } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { type Member } from '@/lib/api/members';
import Avatar from '@/components/ui/Avatar';
import VerificationBadgeRow from '@/components/verification/VerificationBadgeRow';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';

interface MemberCardProps {
  member: Member;
}

export default function MemberCard({ member }: MemberCardProps) {
  const { t } = useTranslation(['members', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();

  const displayName = member.name?.trim() ||
    [member.first_name, member.last_name].filter(Boolean).join(' ') ||
    t('common:labels.member');
  const totalExchanged = (member.total_hours_given ?? 0) + (member.total_hours_received ?? 0);

  return (
    <Pressable
      className="mx-4 my-2"
      onPress={() => {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
        router.push({ pathname: '/(modals)/member-profile', params: { id: String(member.id) } });
      }}
      accessibilityRole="button"
      accessibilityLabel={t('memberCard.accessibilityLabel', { name: displayName })}
    >
      <HeroCard variant="default" className="w-full overflow-hidden">
        <View className="h-1 w-full" style={{ backgroundColor: primary }} />
        <HeroCard.Body className="gap-3 px-4 py-4">
          <View className="flex-row items-start gap-3">
            <Avatar uri={member.avatar ?? member.avatar_url ?? null} name={displayName} size={64} />
            <View className="min-w-0 flex-1 gap-2">
              <View className="flex-row flex-wrap items-center gap-2">
                <Text className="min-w-0 flex-1 text-base font-bold leading-6" style={{ color: theme.text }} numberOfLines={1}>
                  {displayName}
                </Text>
                <VerificationBadgeRow userId={member.id} showUnverified />
              </View>

              {member.tagline ? (
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={2}>
                  {member.tagline}
                </Text>
              ) : (
                <Text className="text-sm leading-5" style={{ color: theme.textMuted }} numberOfLines={2}>
                  {t('memberCard.noTagline')}
                </Text>
              )}

              <View className="flex-row flex-wrap gap-2">
                {member.location ? (
                  <Surface variant="secondary" className="flex-row max-w-full items-center gap-1 rounded-full px-3 py-1.5">
                    <Ionicons name="location-outline" size={13} color={theme.textMuted} />
                    <Text className="max-w-[170px] text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
                      {member.location}
                    </Text>
                  </Surface>
                ) : null}
                {member.rating != null ? (
                  <Surface variant="secondary" className="flex-row items-center gap-1 rounded-full px-3 py-1.5">
                    <Ionicons name="star" size={13} color={theme.warning} />
                    <Text className="text-xs" style={{ color: theme.textSecondary }}>
                      {member.rating.toFixed(1)}
                    </Text>
                  </Surface>
                ) : null}
              </View>
            </View>
          </View>
        </HeroCard.Body>

        <View className="mx-4">
          <Separator />
        </View>
        <HeroCard.Footer className="flex-row items-center justify-between gap-3 px-4 py-3">
          <View className="flex-row flex-wrap gap-2">
            <Chip size="sm" variant="soft" color="warning">
              <Ionicons name="arrow-up-outline" size={12} color={theme.warning} />
              <Chip.Label>{t('hoursGivenShort', { count: Math.round(member.total_hours_given ?? 0) })}</Chip.Label>
            </Chip>
            <Chip size="sm" variant="soft" color="default">
              <Ionicons name="swap-horizontal-outline" size={12} color={theme.textMuted} />
              <Chip.Label>{t('hoursTotalShort', { count: Math.round(totalExchanged) })}</Chip.Label>
            </Chip>
          </View>
          <Ionicons name="arrow-forward" size={17} color={primary} />
        </HeroCard.Footer>
      </HeroCard>
    </Pressable>
  );
}
