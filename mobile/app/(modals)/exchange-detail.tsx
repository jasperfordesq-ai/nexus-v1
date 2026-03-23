// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo, useState, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  SafeAreaView,
  RefreshControl,
  ActivityIndicator,
  Share,
} from 'react-native';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import * as Haptics from 'expo-haptics';

import { getExchange } from '@/lib/api/exchanges';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { useAuth } from '@/lib/hooks/useAuth';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

const WEB_URL = 'https://app.project-nexus.ie';

export default function ExchangeDetailModal() {
  const { t } = useTranslation('exchanges');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);
  const { user: currentUser } = useAuth();
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    navigation.setOptions({ title: t('detail.title') });
  }, [navigation, t]);

  const exchangeId = Number(id);
  const safeExchangeId = isNaN(exchangeId) || exchangeId <= 0 ? 0 : exchangeId;

  const { data, isLoading, error, refresh } = useApi(
    () => getExchange(safeExchangeId),
    [safeExchangeId],
    { enabled: safeExchangeId > 0 },
  );

  const handleRefresh = useCallback(async () => {
    setIsRefreshing(true);
    try {
      await refresh();
    } finally {
      setIsRefreshing(false);
    }
  }, [refresh]);

  const handleAction = useCallback(
    (recipientId: number, recipientName: string) => {
      if (isSubmitting) return;
      setIsSubmitting(true);
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      router.push({
        pathname: '/(modals)/thread',
        params: { recipientId: String(recipientId), name: recipientName },
      });
      // Reset after navigation begins so the button is re-enabled if user returns
      setTimeout(() => setIsSubmitting(false), 600);
    },
    [isSubmitting],
  );

  if (isNaN(exchangeId) || exchangeId <= 0) {
    return (
      <SafeAreaView style={styles.centered}>
        <Text style={styles.errorText}>{t('detail.invalidId')}</Text>
        <TouchableOpacity onPress={() => router.back()}>
          <Text style={[styles.backLink, { color: primary }]}>{t('detail.goBack')}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  const exchange = data?.data;

  if (isLoading) return <LoadingSpinner />;

  if (error || !exchange) {
    return (
      <SafeAreaView style={styles.centered}>
        <Text style={styles.errorText}>{error ?? t('detail.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()}>
          <Text style={[styles.backLink, { color: primary }]}>{t('detail.goBack')}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={() => void handleRefresh()}
            tintColor={primary}
            colors={[primary]}
          />
        }
      >
        {/* Type badge */}
        <View style={[styles.typeBadge, exchange.type === 'offer' ? styles.offerBadge : styles.requestBadge]}>
          <Text style={styles.typeBadgeText}>
            {exchange.type === 'offer' ? t('offering') : t('requesting')}
          </Text>
        </View>

        <Text style={styles.title}>{exchange.title}</Text>
        <Text style={styles.description}>{exchange.description}</Text>

        {/* Time estimate */}
        {(exchange.hours_estimate ?? 0) > 0 && (
          <View style={[styles.creditsCard, { borderColor: primary }]}>
            <Text style={styles.creditsLabel}>{t('detail.timeEstimate')}</Text>
            <Text style={[styles.creditsValue, { color: primary }]}>
              {t('detail.hours', { count: exchange.hours_estimate ?? 0 })}
            </Text>
          </View>
        )}

        {/* Posted by */}
        <View style={styles.postedBy}>
          <Avatar uri={exchange.user.avatar_url} name={exchange.user.name} size={40} />
          <View style={styles.postedByText}>
            <Text style={styles.postedByLabel}>{t('detail.postedBy')}</Text>
            <Text style={styles.postedByName}>{exchange.user.name}</Text>
          </View>
        </View>

        {/* Action -- hidden if you are the poster */}
        {currentUser?.id !== exchange.user.id && (
          <TouchableOpacity
            style={[styles.actionButton, { backgroundColor: primary, opacity: isSubmitting ? 0.7 : 1 }]}
            activeOpacity={0.8}
            disabled={isSubmitting}
            accessibilityLabel={
              exchange.type === 'offer' ? t('detail.requestService') : t('detail.offerHelp')
            }
            accessibilityRole="button"
            onPress={() => handleAction(exchange.user.id, exchange.user.name)}
          >
            {isSubmitting ? (
              <ActivityIndicator color="#fff" size="small" />
            ) : (
              <Text style={styles.actionButtonText}>
                {exchange.type === 'offer' ? t('detail.requestService') : t('detail.offerHelp')}
              </Text>
            )}
          </TouchableOpacity>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.surface },
    content: { padding: 24 },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 },
    typeBadge: { alignSelf: 'flex-start', borderRadius: 6, paddingHorizontal: 10, paddingVertical: 4, marginBottom: 12 },
    offerBadge: { backgroundColor: theme.successBg },
    requestBadge: { backgroundColor: theme.infoBg },
    typeBadgeText: { fontSize: 12, fontWeight: '600', color: theme.textSecondary },
    title: { fontSize: 22, fontWeight: '700', color: theme.text, marginBottom: 12 },
    description: { fontSize: 15, color: theme.textSecondary, lineHeight: 22, marginBottom: 24 },
    creditsCard: {
      borderWidth: 2,
      borderRadius: 12,
      padding: 16,
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      marginBottom: 24,
    },
    creditsLabel: { fontSize: 14, color: theme.textSecondary },
    creditsValue: { fontSize: 24, fontWeight: '700' },
    postedBy: { flexDirection: 'row', alignItems: 'center', marginBottom: 32 },
    postedByText: { marginLeft: 12 },
    postedByLabel: { fontSize: 12, color: theme.textMuted },
    postedByName: { fontSize: 15, fontWeight: '600', color: theme.text },
    actionButton: { borderRadius: 12, paddingVertical: 14, alignItems: 'center' },
    actionButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' }, // contrast on primary
    errorText: { color: theme.error, fontSize: 14, marginBottom: 16 },
    backLink: { fontSize: 15, fontWeight: '600' },
  });
}
