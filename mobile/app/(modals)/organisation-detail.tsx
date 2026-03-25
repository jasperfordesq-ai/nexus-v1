// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo } from 'react';
import {
  View,
  Text,
  ScrollView,
  RefreshControl,
  StyleSheet,
  TouchableOpacity,
  Linking,
  Alert,
  Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { getOrganisation } from '@/lib/api/organisations';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function OrganisationDetailScreen() {
  const { t } = useTranslation('organisations');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  useEffect(() => {
    navigation.setOptions({ title: t('detail.title') });
  }, [navigation, t]);

  const orgId = Number(id);
  const safeId = isNaN(orgId) || orgId <= 0 ? 0 : orgId;

  const { data, isLoading, refresh } = useApi(
    () => getOrganisation(safeId),
    [safeId],
    { enabled: safeId > 0 },
  );

  const organisation = data?.data ?? null;

  if (isNaN(orgId) || orgId <= 0) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <Text style={styles.errorText}>{t('detail.invalidId')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
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

  if (!organisation) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <Text style={styles.errorText}>{t('detail.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  async function handleShare() {
    if (!organisation) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: `${organisation.name} — ${WEB_URL}/organisations/${organisation.id}`,
      });
    } catch { /* ignore */ }
  }

  async function handleOpenWebsite() {
    if (!organisation?.website) return;
    const supported = await Linking.canOpenURL(organisation.website);
    if (supported) {
      await Linking.openURL(organisation.website);
    } else {
      Alert.alert(t('common:errors.alertTitle'), organisation.website);
    }
  }

  return (
    <ModalErrorBoundary>
    <SafeAreaView style={styles.container} edges={['bottom']}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />
        }
      >
        {/* Header: logo + name + verified + share */}
        <View style={styles.header}>
          <Avatar uri={organisation.logo} name={organisation.name} size={72} />
          <View style={styles.headerText}>
            <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 8 }}>
              <Text style={[styles.name, { flex: 1 }]}>{organisation.name}</Text>
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
            {organisation.verified ? (
              <View style={[styles.verifiedBadge, { backgroundColor: withAlpha(primary, 0.10) }]}>
                <Ionicons name="checkmark-circle" size={14} color={primary} />
                <Text style={[styles.verifiedText, { color: primary }]}>{t('verified')}</Text>
              </View>
            ) : null}
          </View>
        </View>

        {/* Stats row */}
        <View style={styles.statsCard}>
          <View style={styles.statItem}>
            <Ionicons name="people-outline" size={20} color={primary} />
            <Text style={styles.statValue}>{organisation.members_count}</Text>
            <Text style={styles.statLabel}>
              {t('members', { count: organisation.members_count })}
            </Text>
          </View>
          <View style={styles.statDivider} />
          <View style={styles.statItem}>
            <Ionicons name="list-outline" size={20} color={primary} />
            <Text style={styles.statValue}>{organisation.listings_count}</Text>
            <Text style={styles.statLabel}>
              {t('listings', { count: organisation.listings_count })}
            </Text>
          </View>
        </View>

        {/* Location */}
        {organisation.location ? (
          <View style={styles.metaCard}>
            <View style={metaRowStyle}>
              <Ionicons name="location-outline" size={16} color={theme.textSecondary} />
              <Text style={[styles.metaText, { flex: 1 }]}>{organisation.location}</Text>
            </View>
          </View>
        ) : null}

        {/* About */}
        {organisation.description ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('detail.about')}</Text>
            <Text style={styles.description}>{organisation.description}</Text>
          </View>
        ) : null}

        {/* Website button */}
        {organisation.website ? (
          <TouchableOpacity
            style={[styles.websiteButton, { borderColor: primary }]}
            onPress={() => void handleOpenWebsite()}
            activeOpacity={0.8}
            accessibilityRole="link"
            accessibilityLabel={t('website')}
          >
            <Ionicons name="globe-outline" size={18} color={primary} />
            <Text style={[styles.websiteButtonText, { color: primary }]}>{t('website')}</Text>
            <Ionicons name="open-outline" size={14} color={primary} />
          </TouchableOpacity>
        ) : null}
      </ScrollView>
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const metaRowStyle = { flexDirection: 'row' as const, alignItems: 'center' as const, gap: 10 };

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    content: { padding: SPACING.xl - 12, paddingBottom: SPACING.xxl },
    header: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.md,
      marginBottom: SPACING.xl - 12,
    },
    headerText: {
      flex: 1,
      gap: SPACING.sm - 2,
    },
    name: { ...TYPOGRAPHY.h2, color: theme.text },
    verifiedBadge: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.xs,
      alignSelf: 'flex-start',
      borderRadius: RADIUS.sm,
      paddingHorizontal: SPACING.sm,
      paddingVertical: 3,
    },
    verifiedText: { ...TYPOGRAPHY.caption, fontWeight: '600' },
    statsCard: {
      flexDirection: 'row',
      backgroundColor: theme.surface,
      borderRadius: RADIUS.lg,
      padding: SPACING.md,
      marginBottom: SPACING.md,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    statItem: {
      flex: 1,
      alignItems: 'center',
      gap: SPACING.xs,
    },
    statValue: {
      fontSize: 20,
      fontWeight: '700',
      color: theme.text,
    },
    statLabel: {
      ...TYPOGRAPHY.caption,
      color: theme.textSecondary,
      textAlign: 'center',
    },
    statDivider: {
      width: 1,
      backgroundColor: theme.border,
      marginVertical: SPACING.xs,
    },
    metaCard: {
      backgroundColor: theme.surface,
      borderRadius: RADIUS.lg,
      padding: RADIUS.lg,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      marginBottom: SPACING.md,
    },
    metaText: { ...TYPOGRAPHY.label, color: theme.text },
    section: { marginBottom: SPACING.xl - 12 },
    sectionTitle: {
      ...TYPOGRAPHY.caption,
      fontWeight: '700',
      color: theme.textSecondary,
      textTransform: 'uppercase',
      letterSpacing: 0.6,
      marginBottom: SPACING.sm + 2,
    },
    description: { ...TYPOGRAPHY.body, color: theme.text },
    websiteButton: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: SPACING.sm,
      borderRadius: SPACING.sm + 4,
      paddingVertical: 13,
      borderWidth: 1.5,
      marginTop: SPACING.xs,
    },
    websiteButtonText: { ...TYPOGRAPHY.button },
    errorText: { ...TYPOGRAPHY.body, color: theme.textMuted },
  });
}
