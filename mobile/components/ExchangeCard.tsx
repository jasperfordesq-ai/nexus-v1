// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { router } from 'expo-router';

import { type Exchange } from '@/lib/api/exchanges';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import Card from '@/components/ui/Card';

interface ExchangeCardProps {
  exchange: Exchange;
}

export default function ExchangeCard({ exchange }: ExchangeCardProps) {
  const primary = usePrimaryColor();
  const theme = useTheme();

  function openDetail() {
    router.push({ pathname: '/(modals)/exchange-detail', params: { id: exchange.id } });
  }

  const hours = exchange.hours_estimate ?? 0;

  return (
    <TouchableOpacity
      style={styles.wrapper}
      onPress={openDetail}
      activeOpacity={0.85}
    >
      <Card style={styles.card}>
        {/* Header row */}
        <View style={styles.header}>
          <View
            style={[
              styles.typeBadge,
              { backgroundColor: exchange.type === 'offer' ? theme.successBg : theme.infoBg },
            ]}
          >
            <Text style={[styles.typeBadgeText, { color: theme.textSecondary }]}>
              {exchange.type === 'offer' ? 'Offering' : 'Requesting'}
            </Text>
          </View>
          {hours > 0 && (
            <Text style={[styles.credits, { color: primary }]}>
              {hours} hr{hours !== 1 ? 's' : ''}
            </Text>
          )}
        </View>

        {/* Title */}
        <Text style={[styles.title, { color: theme.text }]} numberOfLines={2}>
          {exchange.title}
        </Text>

        {/* Footer: user info + category */}
        <View style={styles.footer}>
          <Avatar uri={exchange.user.avatar_url} name={exchange.user.name} size={24} />
          <Text style={[styles.userName, { color: theme.textSecondary }]} numberOfLines={1}>
            {exchange.user.name}
          </Text>
          {exchange.category_name && (
            <Text style={[styles.category, { color: theme.textMuted }]}>
              {exchange.category_name}
            </Text>
          )}
        </View>
      </Card>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  wrapper: { marginHorizontal: 16, marginVertical: 6 },
  card: { gap: 8 },
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  typeBadge: {
    borderRadius: 6,
    paddingHorizontal: 8,
    paddingVertical: 3,
  },
  typeBadgeText: { fontSize: 11, fontWeight: '600' },
  credits: { fontSize: 15, fontWeight: '700' },
  title: { fontSize: 16, fontWeight: '600' },
  footer: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  userName: { fontSize: 13, flex: 1 },
  category: { fontSize: 12 },
});
