// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  SafeAreaView,
} from 'react-native';
import { useLocalSearchParams, useNavigation, router } from 'expo-router';
import { useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { getMember } from '@/lib/api/members';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

/** Extended member shape returned by the single-member endpoint */
interface MemberProfile {
  id: number;
  name: string;
  bio: string | null;
  avatar_url: string | null;
  location: string | null;
  time_balance: number;
  skills: string[];
  joined_at: string;
  last_active_at: string | null;
  total_hours_given?: number;
  total_hours_received?: number;
  rating?: number | null;
  is_verified?: boolean;
}

export default function MemberProfileScreen() {
  const { t } = useTranslation('members');
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);
  const navigation = useNavigation();

  const memberId = Number(id);
  const safeMemberId = isNaN(memberId) || memberId <= 0 ? 0 : memberId;

  const { data, isLoading, error } = useApi(
    () => getMember(safeMemberId),
    [safeMemberId],
    { enabled: safeMemberId > 0 },
  );

  const member = data?.data as MemberProfile | undefined;

  useEffect(() => {
    if (member?.name) {
      navigation.setOptions({ title: member.name });
    }
  }, [member?.name, navigation]);

  if (isNaN(memberId) || memberId <= 0) {
    return (
      <SafeAreaView style={styles.centered}>
        <Text style={styles.errorText}>{t('common:errors.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('common:buttons.back')}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  if (isLoading && !data) {
    return (
      <SafeAreaView style={styles.centered}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (error || !member) {
    return (
      <SafeAreaView style={styles.centered}>
        <Text style={styles.errorText}>{t('profile.loadError')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('common:buttons.back')}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>

        {/* Avatar + identity */}
        <View style={styles.heroSection}>
          <Avatar uri={member.avatar_url} name={member.name} size={80} />

          <View style={styles.identityRow}>
            <Text style={styles.name}>{member.name}</Text>
            {member.is_verified && (
              <View style={styles.verifiedBadge}>
                <Text style={styles.verifiedText}>{t('profile.verified')}</Text>
              </View>
            )}
          </View>

          {member.rating != null && (
            <Text style={styles.rating}>{member.rating.toFixed(1)} ★</Text>
          )}

          {member.bio && (
            <Text style={styles.bio}>{member.bio}</Text>
          )}

          {member.location && (
            <Text style={styles.location}>{member.location}</Text>
          )}
        </View>

        {/* Stats row */}
        <View style={styles.statsRow}>
          <View style={styles.statItem}>
            <Text style={[styles.statValue, { color: primary }]}>
              {(member.total_hours_given ?? member.time_balance).toFixed(0)}
            </Text>
            <Text style={styles.statLabel}>{t('profile.hoursGiven')}</Text>
          </View>
          <View style={styles.statDivider} />
          <View style={styles.statItem}>
            <Text style={[styles.statValue, { color: primary }]}>
              {(member.total_hours_received ?? 0).toFixed(0)}
            </Text>
            <Text style={styles.statLabel}>{t('profile.hoursReceived')}</Text>
          </View>
        </View>

        {/* Skills */}
        {member.skills.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('profile.skills')}</Text>
            <View style={styles.skillsWrap}>
              {member.skills.map((skill) => (
                <View key={skill} style={[styles.skillChip, { borderColor: primary }]}>
                  <Text style={[styles.skillText, { color: primary }]}>{skill}</Text>
                </View>
              ))}
            </View>
          </View>
        )}

        {/* Member since */}
        <Text style={styles.joinedText}>
          {t('profile.memberSince', { date: formatDate(member.joined_at) })}
        </Text>

      </ScrollView>

      {/* Send message CTA */}
      <View style={styles.footer}>
        <TouchableOpacity
          style={[styles.messageButton, { backgroundColor: primary }]}
          activeOpacity={0.85}
          onPress={() =>
            router.push({
              pathname: '/(modals)/thread',
              params: { id: String(member.id), name: member.name },
            })
          }
        >
          <Text style={styles.messageButtonText}>{t('profile.sendMessage')}</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'long',
  });
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.surface },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 },
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center' },
    scroll: { paddingBottom: 24 },
    heroSection: {
      alignItems: 'center',
      paddingTop: 24,
      paddingHorizontal: 24,
      paddingBottom: 16,
      gap: 8,
    },
    identityRow: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap', justifyContent: 'center' },
    name: { fontSize: 22, fontWeight: '700', color: theme.text, textAlign: 'center' },
    verifiedBadge: {
      backgroundColor: theme.successBg,
      borderRadius: 12,
      paddingHorizontal: 10,
      paddingVertical: 3,
    },
    verifiedText: { color: theme.success, fontSize: 12, fontWeight: '600' },
    rating: { fontSize: 16, color: theme.warning, fontWeight: '600' },
    bio: {
      fontSize: 14,
      color: theme.textSecondary,
      textAlign: 'center',
      lineHeight: 20,
    },
    location: { fontSize: 13, color: theme.textMuted },
    statsRow: {
      flexDirection: 'row',
      marginHorizontal: 24,
      marginTop: 8,
      marginBottom: 16,
      borderWidth: 1,
      borderColor: theme.border,
      borderRadius: 12,
      overflow: 'hidden',
    },
    statItem: { flex: 1, alignItems: 'center', paddingVertical: 16 },
    statDivider: { width: 1, backgroundColor: theme.border },
    statValue: { fontSize: 24, fontWeight: '700' },
    statLabel: { fontSize: 12, color: theme.textMuted, marginTop: 2 },
    section: { paddingHorizontal: 24, marginBottom: 16 },
    sectionTitle: { fontSize: 15, fontWeight: '600', color: theme.text, marginBottom: 10 },
    skillsWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    skillChip: {
      borderWidth: 1,
      borderRadius: 8,
      paddingHorizontal: 12,
      paddingVertical: 4,
    },
    skillText: { fontSize: 13, fontWeight: '500' },
    joinedText: {
      fontSize: 12,
      color: theme.textMuted,
      textAlign: 'center',
      paddingHorizontal: 24,
    },
    footer: {
      padding: 16,
      borderTopWidth: 1,
      borderTopColor: theme.border,
      backgroundColor: theme.surface,
    },
    messageButton: {
      height: 48,
      borderRadius: 12,
      justifyContent: 'center',
      alignItems: 'center',
    },
    messageButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  });
}
