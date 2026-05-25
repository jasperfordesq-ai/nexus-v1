// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Pressable, View, Text } from 'react-native';
import { router } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { type Exchange } from '@/lib/api/exchanges';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import Avatar from '@/components/ui/Avatar';
import Card from '@/components/ui/Card';

interface ExchangeCardProps {
  exchange: Exchange;
}

export default function ExchangeCard({ exchange }: ExchangeCardProps) {
  const { t } = useTranslation('exchanges');
  const primary = usePrimaryColor();

  function openDetail() {
    router.push({ pathname: '/(modals)/exchange-detail', params: { id: String(exchange.id) } });
  }

  const hours = exchange.hours_estimate ?? 0;
  const user = exchange.user ?? { id: 0, name: '?', avatar_url: null };

  return (
    <Pressable
      className="mx-4 my-1.5"
      onPress={openDetail}
      accessibilityRole="button"
      accessibilityLabel={exchange.title ?? ''}
    >
      <Card className="gap-2">
        {/* Header row */}
        <View className="flex-row items-center justify-between">
          <View
            className={exchange.type === 'offer' ? 'rounded-md px-2 py-[3px] bg-success/10' : 'rounded-md px-2 py-[3px] bg-primary/10'}
          >
            <Text className="text-[11px] font-semibold text-muted-foreground">
              {exchange.type === 'offer' ? t('offering') : t('requesting')}
            </Text>
          </View>
          {hours > 0 && (
            <Text className="text-[15px] font-bold" style={{ color: primary }}>
              {t('detail.hours', { count: hours })}
            </Text>
          )}
        </View>

        {/* Title */}
        <Text className="text-base font-semibold text-foreground" numberOfLines={2}>
          {exchange.title ?? ''}
        </Text>

        {/* Footer: user info + category */}
        <View className="flex-row items-center gap-2">
          <Avatar uri={user.avatar_url} name={user.name} size={24} />
          <Text className="text-[13px] flex-1 text-muted-foreground" numberOfLines={1}>
            {user.name}
          </Text>
          {exchange.category_name ? (
            <Text className="text-xs text-muted-foreground">
              {exchange.category_name}
            </Text>
          ) : null}
        </View>
      </Card>
    </Pressable>
  );
}
