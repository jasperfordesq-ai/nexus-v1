// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo } from 'react';
import {
  Linking,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { getFederationPartner } from '@/lib/api/federation';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function FederationPartnerScreen() {
  const { t } = useTranslation('federation');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  useEffect(() => {
    navigation.setOptions({ title: t('detail.title') });
  }, [navigation, t]);

  const partnerId = Number(id);
  const safeId = isNaN(partnerId) || partnerId <= 0 ? 0 : partnerId;

  const { data, isLoading } = useApi(
    () => getFederationPartner(safeId),
    [safeId],
    { enabled: safeId > 0 },
  );

  const partner = data?.data ?? null;

  if (safeId === 0) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <Text style={styles.errorText}>{t('detail.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack')}
          </Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!partner) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <Text style={styles.errorText}>{t('detail.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack')}
          </Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  async function handleShare() {
    if (!partner) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: `${partner.name} — ${WEB_URL}/federation/${partner.id}`,
      });
    } catch { /* ignore */ }
  }

  const connectedDate = new Date(partner.connected_since).toLocaleDateString('default', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

  return (
    <ModalErrorBoundary>
    <SafeAreaView style={styles.container} edges={['bottom']}>
      <ScrollView contentContainerStyle={styles.content}>
        {/* Logo + name + share */}
        <View style={styles.heroSection}>
          <Avatar uri={partner.logo} name={partner.name} size={80} />
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <Text style={[styles.partnerName, { flex: 1 }]}>{partner.name}</Text>
            <TouchableOpacity
              onPress={() => void handleShare()}
              style={{ padding: 4 }}
              activeOpacity={0.7}
              accessibilityLabel={t('detail.share')}
              accessibilityRole="button"
            >
              <Ionicons name="share-outline" size={22} color={primary} />
            </TouchableOpacity>
          </View>
          {partner.location ? (
            <View style={styles.metaRow}>
              <Ionicons name="location-outline" size={14} color={theme.textSecondary} />
              <Text style={styles.metaText}>{partner.location}</Text>
            </View>
          ) : null}
        </View>

        {/* Stats card */}
        <View style={styles.statsCard}>
          <View style={styles.statItem}>
            <Ionicons name="people-outline" size={20} color={primary} />
            <Text style={styles.statValue}>{(partner.member_count ?? 0).toLocaleString()}</Text>
            <Text style={styles.statLabel}>{t('detail.members')}</Text>
          </View>
          <View style={styles.statDivider} />
          <View style={styles.statItem}>
            <Ionicons name="link-outline" size={20} color={primary} />
            <Text style={[styles.statValue, { fontSize: 12, fontWeight: '500' }]}>
              {t('connectedSince', { date: connectedDate })}
            </Text>
          </View>
        </View>

        {/* Description */}
        {partner.description ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('detail.about')}</Text>
            <Text style={styles.description}>{partner.description}</Text>
          </View>
        ) : null}

        {/* Visit website */}
        {partner.website ? (
          <TouchableOpacity
            style={[styles.websiteButton, { backgroundColor: primary }]}
            onPress={async () => {
              const url = partner.website!;
              const supported = await Linking.canOpenURL(url);
              if (supported) {
                await Linking.openURL(url);
              }
            }}
            accessibilityLabel={t('visitWebsite')}
            accessibilityRole="button"
          >
            <Ionicons name="globe-outline" size={16} color="#fff" />{/* contrast on primary */}
            <Text style={styles.websiteButtonText}>{t('visitWebsite')}</Text>
          </TouchableOpacity>
        ) : null}
      </ScrollView>
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    content: { padding: SPACING.xl - 12, paddingBottom: SPACING.xxl },
    heroSection: {
      alignItems: 'center',
      gap: SPACING.sm + 2,
      marginBottom: SPACING.xl - 12,
    },
    partnerName: {
      ...TYPOGRAPHY.h2,
      color: theme.text,
      textAlign: 'center',
    },
    metaRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.xs,
    },
    metaText: {
      ...TYPOGRAPHY.bodySmall,
      color: theme.textSecondary,
    },
    statsCard: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderRadius: RADIUS.lg,
      padding: SPACING.md,
      marginBottom: SPACING.xl - 12,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    statItem: {
      flex: 1,
      alignItems: 'center',
      gap: SPACING.xs,
    },
    statValue: {
      ...TYPOGRAPHY.h3,
      fontWeight: '700',
      color: theme.text,
      textAlign: 'center',
    },
    statLabel: {
      fontSize: 11,
      color: theme.textSecondary,
    },
    statDivider: {
      width: 1,
      height: 40,
      backgroundColor: theme.border,
      marginHorizontal: SPACING.sm,
    },
    section: {
      marginBottom: SPACING.xl - 12,
    },
    sectionTitle: {
      ...TYPOGRAPHY.caption,
      fontWeight: '700',
      color: theme.textSecondary,
      textTransform: 'uppercase',
      letterSpacing: 0.6,
      marginBottom: SPACING.sm,
    },
    description: {
      ...TYPOGRAPHY.body,
      color: theme.text,
    },
    websiteButton: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: SPACING.sm,
      borderRadius: SPACING.sm + 4,
      paddingVertical: RADIUS.lg,
      marginTop: SPACING.xs,
    },
    websiteButtonText: {
      ...TYPOGRAPHY.button,
      color: '#fff', // contrast on primary
    },
    errorText: {
      ...TYPOGRAPHY.body,
      color: theme.textMuted,
    },
  });
}
