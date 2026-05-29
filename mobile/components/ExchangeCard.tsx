// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Text, View } from 'react-native';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Separator, Surface } from 'heroui-native';

import { type Exchange } from '@/lib/api/exchanges';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';
import { formatRelativeTime } from '@/lib/utils/formatRelativeTime';
import Avatar from '@/components/ui/Avatar';

interface ExchangeCardProps {
  exchange: Exchange;
}

export default function ExchangeCard({ exchange }: ExchangeCardProps) {
  const { t } = useTranslation('exchanges');
  const primary = usePrimaryColor();
  const theme = useTheme();

  function openDetail() {
    router.push({ pathname: '/(modals)/exchange-detail', params: { id: String(exchange.id) } });
  }

  const hours = exchange.hours_estimate ?? 0;
  const user = exchange.user ?? { id: 0, name: '?', avatar_url: null };
  const imageUrl = resolveImageUrl(exchange.image_url);
  const isOffer = exchange.type === 'offer';
  const accent = isOffer ? '#10B981' : '#F59E0B';
  const accentSoft = isOffer ? 'rgba(16, 185, 129, 0.14)' : 'rgba(245, 158, 11, 0.14)';

  return (
    <HeroButton
      variant="ghost"
      feedbackVariant="scale"
      className="mx-4 my-2"
      onPress={openDetail}
      accessibilityLabel={exchange.title ?? ''}
    >
      <HeroCard variant="default" className="w-full overflow-hidden">
        <View className="h-1 w-full" style={{ backgroundColor: accent }} />
        {imageUrl ? (
          <Image source={{ uri: imageUrl }} style={{ width: '100%', height: 150 }} contentFit="cover" />
        ) : null}

        <HeroCard.Header className="flex-row items-start justify-between gap-3 px-4 pb-2 pt-4">
          <View className="min-w-0 flex-1 gap-2">
            <View className="flex-row flex-wrap items-center gap-2">
              <Chip size="sm" variant="soft" color={isOffer ? 'success' : 'warning'}>
                <Ionicons name={isOffer ? 'gift-outline' : 'hand-left-outline'} size={12} color={accent} />
                <Chip.Label>{isOffer ? t('offering') : t('requesting')}</Chip.Label>
              </Chip>
              {exchange.category_name ? (
                <Chip size="sm" variant="soft" color="default">
                  <Chip.Label>{exchange.category_name}</Chip.Label>
                </Chip>
              ) : null}
            </View>
            <Text className="text-lg font-bold leading-6" style={{ color: theme.text }} numberOfLines={2}>
              {exchange.title ?? ''}
            </Text>
          </View>
          <Surface variant="secondary" className="size-9 items-center justify-center rounded-full">
            <Ionicons name="arrow-forward" size={18} color={primary} />
          </Surface>
        </HeroCard.Header>

        <HeroCard.Body className="gap-3 px-4 pb-4 pt-0">
          {exchange.description ? (
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={3}>
              {exchange.description}
            </Text>
          ) : null}

          <View className="flex-row flex-wrap gap-2">
            {hours > 0 ? (
              <Surface variant="secondary" className="flex-row items-center gap-1 rounded-full px-3 py-1.5" style={{ backgroundColor: accentSoft }}>
                <Ionicons name="time-outline" size={14} color={accent} />
                <Text className="text-xs font-semibold" style={{ color: accent }}>
                  {t('detail.hours', { count: hours })}
                </Text>
              </Surface>
            ) : null}
            {exchange.location ? (
              <Surface variant="secondary" className="flex-row max-w-full items-center gap-1 rounded-full px-3 py-1.5">
                <Ionicons name="location-outline" size={14} color={theme.textMuted} />
                <Text className="max-w-[210px] text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
                  {exchange.location}
                </Text>
              </Surface>
            ) : null}
          </View>
        </HeroCard.Body>

        <View className="mx-4">
          <Separator />
        </View>

        <HeroCard.Footer className="flex-row items-center gap-3 px-4 py-3">
          <Avatar uri={user.avatar_url} name={user.name} size={28} />
          <View className="min-w-0 flex-1">
            <Text className="text-sm font-semibold" style={{ color: theme.text }} numberOfLines={1}>
              {user.name}
            </Text>
            <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
              {formatRelativeTime(exchange.created_at)}
            </Text>
          </View>
          <Surface variant="secondary" className="flex-row items-center gap-2 rounded-full px-3 py-1.5">
            <Text className="text-xs font-semibold" style={{ color: primary }}>
              {t('viewDetails')}
            </Text>
            <Ionicons name="chevron-forward-outline" size={14} color={primary} />
          </Surface>
        </HeroCard.Footer>
      </HeroCard>
    </HeroButton>
  );
}
