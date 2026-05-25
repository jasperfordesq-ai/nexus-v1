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
  RefreshControl,
  ActivityIndicator,
  Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import * as Haptics from 'expo-haptics';

import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import { getExchange, type Exchange } from '@/lib/api/exchanges';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { useAuth } from '@/lib/hooks/useAuth';
import { APP_URL } from '@/lib/constants';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

export default function ExchangeDetailModal() {
  return (
    <ModalErrorBoundary>
      <ExchangeDetailModalInner />
    </ModalErrorBoundary>
  );
}

function ExchangeDetailModalInner() {
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

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    refresh();
    // refresh() triggers a state update that re-runs the fetch effect;
    // isLoading will become true then false once data arrives.
    // Use a short timer to clear the refreshing indicator since refresh()
    // is synchronous (just bumps a counter).
    setTimeout(() => setIsRefreshing(false), 1200);
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

  // Support both { data: Exchange } wrapper and bare Exchange responses
  const exchange: Exchange | undefined = (data as { data?: Exchange })?.data ?? (data as Exchange | null) ?? undefined;

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

  // Guard against missing user object — API may return exchange without nested user
  const exchangeUser = exchange.user ?? { id: 0, name: '?', avatar_url: null };

  async function handleShare() {
    try {
      await Share.share({
        title: exchange!.title,
        message: `${exchange!.title}\n${APP_URL}/listings/${exchange!.id}`,
        url: `${APP_URL}/listings/${exchange!.id}`,
      });
    } catch {
      // User cancelled or share failed — silently ignore
    }
  }

  return (
    <SafeAreaView style={styles.container} edges={['bottom']}>
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
        {/* Top row: type badge + share */}
        <View style={styles.topRow}>
          <View style={[styles.typeBadge, exchange.type === 'offer' ? styles.offerBadge : styles.requestBadge]}>
            <Text style={styles.typeBadgeText}>
              {exchange.type === 'offer' ? t('offering') : t('requesting')}
            </Text>
          </View>
          <TouchableOpacity
            onPress={() => void handleShare()}
            accessibilityLabel={t('detail.share')}
            accessibilityRole="button"
            hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
          >
            <Ionicons name="share-outline" size={22} color={theme.textSecondary} />
          </TouchableOpacity>
        </View>

        <Text style={styles.title}>{exchange.title ?? ''}</Text>
        <Text style={styles.description}>{exchange.description ?? ''}</Text>

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
        <TouchableOpacity
          style={styles.postedBy}
          onPress={() => {
            if (exchangeUser.id > 0) {
              router.push({ pathname: '/(modals)/member-profile', params: { id: String(exchangeUser.id) } });
            }
          }}
          activeOpacity={0.7}
          accessibilityRole="button"
          accessibilityLabel={exchangeUser.name}
        >
          <Avatar uri={exchangeUser.avatar_url} name={exchangeUser.name} size={40} />
          <View style={styles.postedByText}>
            <Text style={styles.postedByLabel}>{t('detail.postedBy')}</Text>
            <Text style={styles.postedByName}>{exchangeUser.name}</Text>
          </View>
        </TouchableOpacity>

        {/* Action -- hidden if you are the poster */}
        {currentUser?.id !== exchangeUser.id && exchangeUser.id > 0 && (
          <TouchableOpacity
            style={[styles.actionButton, { backgroundColor: primary, opacity: isSubmitting ? 0.7 : 1 }]}
            activeOpacity={0.8}
            disabled={isSubmitting}
            accessibilityLabel={
              exchange.type === 'offer' ? t('detail.requestService') : t('detail.offerHelp')
            }
            accessibilityRole="button"
            onPress={() => handleAction(exchangeUser.id, exchangeUser.name)}
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
    content: { padding: SPACING.lg },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: SPACING.lg },
    topRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 },
    typeBadge: { alignSelf: 'flex-start', borderRadius: RADIUS.sm, paddingHorizontal: 10, paddingVertical: 4 },
    offerBadge: { backgroundColor: theme.successBg },
    requestBadge: { backgroundColor: theme.infoBg },
    typeBadgeText: { ...TYPOGRAPHY.caption, fontWeight: '600', color: theme.textSecondary },
    title: { ...TYPOGRAPHY.h2, color: theme.text, marginBottom: 12 },
    description: { ...TYPOGRAPHY.body, color: theme.textSecondary, marginBottom: SPACING.lg },
    creditsCard: {
      borderWidth: 2,
      borderRadius: 12,
      padding: SPACING.md,
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      marginBottom: SPACING.lg,
    },
    creditsLabel: { ...TYPOGRAPHY.label, color: theme.textSecondary },
    creditsValue: { fontSize: 24, fontWeight: '700' },
    postedBy: { flexDirection: 'row', alignItems: 'center', marginBottom: SPACING.xl },
    postedByText: { marginLeft: 12 },
    postedByLabel: { ...TYPOGRAPHY.caption, color: theme.textMuted },
    postedByName: { ...TYPOGRAPHY.body, fontWeight: '600', color: theme.text },
    actionButton: { borderRadius: 12, paddingVertical: RADIUS.lg, alignItems: 'center' },
    actionButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' }, // contrast on primary
    errorText: { ...TYPOGRAPHY.label, color: theme.error, marginBottom: SPACING.md },
    backLink: { ...TYPOGRAPHY.body, fontWeight: '600' },
  });
}
