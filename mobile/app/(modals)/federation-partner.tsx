// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo } from 'react';
import {
  Linking,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { router, useLocalSearchParams, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { getFederationPartner } from '@/lib/api/federation';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

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
      <SafeAreaView style={styles.center}>
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
      <SafeAreaView style={styles.center}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!partner) {
    return (
      <SafeAreaView style={styles.center}>
        <Text style={styles.errorText}>{t('detail.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack')}
          </Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  const connectedDate = new Date(partner.connected_since).toLocaleDateString('default', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.content}>
        {/* Logo + name */}
        <View style={styles.heroSection}>
          <Avatar uri={partner.logo} name={partner.name} size={80} />
          <Text style={styles.partnerName}>{partner.name}</Text>
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
            <Text style={styles.statValue}>{partner.member_count.toLocaleString()}</Text>
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
            <Ionicons name="globe-outline" size={16} color="#fff" />
            <Text style={styles.websiteButtonText}>{t('visitWebsite')}</Text>
          </TouchableOpacity>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    content: { padding: 20, paddingBottom: 48 },
    heroSection: {
      alignItems: 'center',
      gap: 10,
      marginBottom: 20,
    },
    partnerName: {
      fontSize: 22,
      fontWeight: '700',
      color: theme.text,
      textAlign: 'center',
    },
    metaRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 4,
    },
    metaText: {
      fontSize: 13,
      color: theme.textSecondary,
    },
    statsCard: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderRadius: 14,
      padding: 16,
      marginBottom: 20,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    statItem: {
      flex: 1,
      alignItems: 'center',
      gap: 4,
    },
    statValue: {
      fontSize: 18,
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
      marginHorizontal: 8,
    },
    section: {
      marginBottom: 20,
    },
    sectionTitle: {
      fontSize: 12,
      fontWeight: '700',
      color: theme.textSecondary,
      textTransform: 'uppercase',
      letterSpacing: 0.6,
      marginBottom: 8,
    },
    description: {
      fontSize: 15,
      color: theme.text,
      lineHeight: 22,
    },
    websiteButton: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 8,
      borderRadius: 12,
      paddingVertical: 14,
      marginTop: 4,
    },
    websiteButtonText: {
      fontSize: 15,
      fontWeight: '600',
      color: '#fff',
    },
    errorText: {
      fontSize: 15,
      color: theme.textMuted,
    },
  });
}
