// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  RefreshControl,
  Pressable,
  Alert,
  Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { getOpportunity, expressInterest } from '@/lib/api/volunteering';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function VolunteeringDetailScreen() {
  const { t } = useTranslation('volunteering');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();

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
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.invalidId')}</Text>
        <Pressable onPress={() => router.back()} className="mt-3">
          <Text style={{ color: primary }} className="text-[15px] font-semibold">{t('detail.goBack')}</Text>
        </Pressable>
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
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!opportunity) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.notFound')}</Text>
        <Pressable onPress={() => router.back()} className="mt-3">
          <Text style={{ color: primary }} className="text-[15px] font-semibold">{t('detail.goBack')}</Text>
        </Pressable>
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
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />
        }
      >
        {/* Title + share + status */}
        <View className="flex-row items-start gap-2.5 mb-4">
          <Text className="flex-1 text-xl font-bold text-foreground">{opportunity.title}</Text>
          <Pressable
            onPress={() => void handleShare()}
            className="p-1"
            accessibilityLabel={t('detail.share')}
            accessibilityRole="button"
          >
            <Ionicons name="share-outline" size={22} color={primary} />
          </Pressable>
          <View style={{ backgroundColor: statusColor + '22' }} className="rounded px-2 py-0.5 self-start">
            <Text style={{ color: statusColor }} className="text-[11px] font-semibold">
              {t(`status.${opportunity.status}`)}
            </Text>
          </View>
        </View>

        {/* Organisation */}
        {opportunity.organisation ? (
          <View className="mb-5">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2">
              {t('detail.organisation')}
            </Text>
            <View className="flex-row items-center gap-4">
              <Avatar
                uri={opportunity.organisation.avatar}
                name={opportunity.organisation.name}
                size={36}
              />
              <Text className="text-sm font-semibold text-foreground">{opportunity.organisation.name}</Text>
            </View>
          </View>
        ) : null}

        {/* Meta card */}
        <View className="bg-surface rounded-2xl p-4 gap-2.5 border border-border/50 mb-5">
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
        {(opportunity.skills_needed ?? []).length > 0 ? (
          <View className="mb-5">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2">
              {t('skills')}
            </Text>
            <View className="flex-row flex-wrap gap-2">
              {(opportunity.skills_needed ?? []).map((skill) => (
                <View key={skill} className="rounded-lg px-2.5 py-1 border border-border bg-surface">
                  <Text className="text-xs text-foreground">{skill}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {/* Description */}
        {opportunity.description ? (
          <View className="mb-5">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2">
              {t('detail.about')}
            </Text>
            <Text className="text-sm text-foreground">{opportunity.description}</Text>
          </View>
        ) : null}

        {/* Express Interest button */}
        <Pressable
          className="flex-row items-center justify-center gap-2 rounded-xl py-4 mt-2"
          style={{
            backgroundColor: interestSent ? theme.success : primary,
            opacity: interestLoading || interestSent ? 0.75 : 1,
          }}
          onPress={() => void handleExpressInterest()}
          disabled={interestLoading || interestSent}
          accessibilityRole="button"
          accessibilityLabel={interestSent ? t('interestSent') : t('expressInterest')}
        >
          {interestSent ? (
            <Ionicons name="checkmark-circle" size={18} color="#fff" />
          ) : (
            <Ionicons name="hand-left-outline" size={18} color="#fff" />
          )}
          <Text className="text-base font-bold text-white">
            {interestSent ? t('interestSent') : t('expressInterest')}
          </Text>
        </Pressable>
      </ScrollView>
    </SafeAreaView>
    </ModalErrorBoundary>
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
  theme: ReturnType<typeof useTheme>;
  tint?: string;
}) {
  return (
    <View className="flex-row items-center gap-2.5">
      <Ionicons name={icon} size={16} color={tint ?? theme.textSecondary} />
      <Text style={{ color: tint ?? theme.text }} className="flex-1 text-sm">{text}</Text>
    </View>
  );
}
