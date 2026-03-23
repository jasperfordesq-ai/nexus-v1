// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useMemo } from 'react';
import {
  View,
  Text,
  ScrollView,
  RefreshControl,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  Alert,
  Share,
} from 'react-native';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { getOpportunity, expressInterest } from '@/lib/api/volunteering';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

const WEB_URL = 'https://app.project-nexus.ie';

export default function VolunteeringDetailScreen() {
  const { t } = useTranslation('volunteering');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  useEffect(() => {
    navigation.setOptions({ title: t('detail.title') });
  }, [navigation, t]);

  const opportunityId = Number(id);
  const safeId = isNaN(opportunityId) || opportunityId <= 0 ? 0 : opportunityId;

  const { data, isLoading, refresh } = useApi(
    () => getOpportunity(safeId),
    [safeId],
    { enabled: safeId > 0 },
  );

  const opportunity = data?.data ?? null;

  const [interestSent, setInterestSent] = useState(false);
  const [interestLoading, setInterestLoading] = useState(false);

  async function handleShare() {
    if (!opportunity) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: `${opportunity.title} — ${WEB_URL}/volunteering/${opportunity.id}`,
      });
    } catch { /* ignore */ }
  }

  if (isNaN(opportunityId) || opportunityId <= 0) {
    return (
      <SafeAreaView style={styles.center}>
        <Text style={styles.errorText}>{t('detail.invalidId')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  async function handleExpressInterest() {
    if (!opportunity || interestSent || interestLoading) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setInterestLoading(true);
    try {
      await expressInterest(opportunity.id);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      setInterestSent(true);
      Alert.alert(t('interestSentTitle'), t('interestSentMessage'));
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('interestError'));
    } finally {
      setInterestLoading(false);
    }
  }

  if (isLoading) {
    return (
      <SafeAreaView style={styles.center}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!opportunity) {
    return (
      <SafeAreaView style={styles.center}>
        <Text style={styles.errorText}>{t('detail.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  const statusColor =
    opportunity.status === 'open'
      ? theme.success
      : opportunity.status === 'filled'
        ? theme.warning
        : theme.textMuted;

  const deadlineStr = opportunity.deadline
    ? t('deadline', {
        date: new Date(opportunity.deadline).toLocaleDateString('default', {
          month: 'long',
          day: 'numeric',
          year: 'numeric',
        }),
      })
    : null;

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />
        }
      >
        {/* Title + share + status */}
        <View style={styles.titleRow}>
          <Text style={styles.title}>{opportunity.title}</Text>
          <TouchableOpacity
            onPress={() => void handleShare()}
            style={{ padding: 4 }}
            activeOpacity={0.7}
            accessibilityLabel={t('detail.share')}
            accessibilityRole="button"
          >
            <Ionicons name="share-outline" size={22} color={primary} />
          </TouchableOpacity>
          <View style={[styles.statusBadge, { backgroundColor: statusColor + '22' }]}>
            <Text style={[styles.statusText, { color: statusColor }]}>
              {t(`status.${opportunity.status}`)}
            </Text>
          </View>
        </View>

        {/* Organisation */}
        {opportunity.organisation ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('detail.organisation')}</Text>
            <View style={styles.orgRow}>
              <Avatar
                uri={opportunity.organisation.avatar}
                name={opportunity.organisation.name}
                size={36}
              />
              <Text style={styles.orgName}>{opportunity.organisation.name}</Text>
            </View>
          </View>
        ) : null}

        {/* Meta card */}
        <View style={styles.metaCard}>
          {opportunity.is_remote ? (
            <MetaRow icon="wifi-outline" text={t('remote')} theme={theme} tint={primary} />
          ) : opportunity.location ? (
            <MetaRow icon="location-outline" text={opportunity.location} theme={theme} />
          ) : null}

          {opportunity.hours_per_week !== null ? (
            <MetaRow
              icon="time-outline"
              text={t('hoursPerWeek', { hours: opportunity.hours_per_week })}
              theme={theme}
            />
          ) : null}

          {opportunity.commitment ? (
            <MetaRow icon="repeat-outline" text={opportunity.commitment} theme={theme} />
          ) : null}

          {deadlineStr ? (
            <MetaRow icon="calendar-outline" text={deadlineStr} theme={theme} />
          ) : null}

          {opportunity.spots_available !== null ? (
            <MetaRow
              icon="people-outline"
              text={t('spots', { count: opportunity.spots_available })}
              theme={theme}
            />
          ) : null}
        </View>

        {/* Skills */}
        {opportunity.skills_needed.length > 0 ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('skills')}</Text>
            <View style={styles.skillsRow}>
              {opportunity.skills_needed.map((skill) => (
                <View key={skill} style={[styles.skillPill, { backgroundColor: theme.surface, borderColor: theme.border }]}>
                  <Text style={styles.skillText}>{skill}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {/* Description */}
        {opportunity.description ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('detail.about')}</Text>
            <Text style={styles.description}>{opportunity.description}</Text>
          </View>
        ) : null}

        {/* Express Interest button */}
        <TouchableOpacity
          style={[
            styles.interestButton,
            { backgroundColor: interestSent ? theme.success : primary },
            (interestLoading || interestSent) && styles.interestButtonDisabled,
          ]}
          onPress={() => void handleExpressInterest()}
          disabled={interestLoading || interestSent}
          activeOpacity={0.8}
          accessibilityRole="button"
          accessibilityLabel={interestSent ? t('interestSent') : t('expressInterest')}
        >
          {interestSent ? (
            <Ionicons name="checkmark-circle" size={18} color="#fff" />
          ) : (
            <Ionicons name="hand-left-outline" size={18} color="#fff" />
          )}
          <Text style={styles.interestButtonText}>
            {interestSent ? t('interestSent') : t('expressInterest')}
          </Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function MetaRow({
  icon,
  text,
  theme,
  tint,
}: {
  icon: React.ComponentProps<typeof Ionicons>['name'];
  text: string;
  theme: Theme;
  tint?: string;
}) {
  return (
    <View style={metaRowStyle}>
      <Ionicons name={icon} size={16} color={tint ?? theme.textSecondary} />
      <Text style={{ fontSize: 14, color: tint ?? theme.text, flex: 1 }}>{text}</Text>
    </View>
  );
}

const metaRowStyle = { flexDirection: 'row' as const, alignItems: 'center' as const, gap: 10 };

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    content: { padding: 20, paddingBottom: 48 },
    titleRow: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      gap: 10,
      marginBottom: 16,
    },
    title: { flex: 1, fontSize: 22, fontWeight: '700', color: theme.text },
    statusBadge: {
      borderRadius: 6,
      paddingHorizontal: 8,
      paddingVertical: 4,
      alignSelf: 'flex-start',
    },
    statusText: { fontSize: 11, fontWeight: '600' },
    metaCard: {
      backgroundColor: theme.surface,
      borderRadius: 14,
      padding: 14,
      gap: 10,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      marginBottom: 20,
    },
    section: { marginBottom: 20 },
    sectionTitle: {
      fontSize: 12,
      fontWeight: '700',
      color: theme.textSecondary,
      textTransform: 'uppercase',
      letterSpacing: 0.6,
      marginBottom: 10,
    },
    orgRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
    orgName: { fontSize: 15, fontWeight: '600', color: theme.text },
    skillsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    skillPill: {
      borderRadius: 8,
      paddingHorizontal: 10,
      paddingVertical: 5,
      borderWidth: 1,
    },
    skillText: { fontSize: 13, color: theme.text },
    description: { fontSize: 15, color: theme.text, lineHeight: 22 },
    interestButton: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 8,
      borderRadius: 12,
      paddingVertical: 14,
      marginTop: 8,
    },
    interestButtonDisabled: { opacity: 0.75 },
    interestButtonText: { fontSize: 16, fontWeight: '700', color: '#fff' }, // contrast on primary
    errorText: { fontSize: 15, color: theme.textMuted },
  });
}
