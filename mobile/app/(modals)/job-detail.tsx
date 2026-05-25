// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  Pressable,
  Alert,
  Modal,
  TextInput,
  KeyboardAvoidingView,
  Platform,
  Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { getJobDetail, applyToJob, saveJob, unsaveJob, getSavedProfile } from '@/lib/api/jobs';
import type { JobVacancy } from '@/lib/api/jobs';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function JobDetailScreen() {
  const { t } = useTranslation('jobs');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);

  const jobId = Number(id);
  const safeId = isNaN(jobId) || jobId <= 0 ? 0 : jobId;

  const { data, isLoading } = useApi(
    () => getJobDetail(safeId),
    [safeId],
    { enabled: safeId > 0 },
  );

  const job = data?.data ?? null;

  const [isSaved, setIsSaved] = useState(false);
  const [saveLoading, setSaveLoading] = useState(false);
  const [applyModalVisible, setApplyModalVisible] = useState(false);
  const [coverMessage, setCoverMessage] = useState('');
  const [applyLoading, setApplyLoading] = useState(false);
  const [applySuccess, setApplySuccess] = useState(false);
  const [hasApplied, setHasApplied] = useState(false);

  // Saved profile (one-click apply)
  const [savedProfile, setSavedProfile] = useState<{ cv_filename?: string; cover_text?: string } | null>(null);

  // Sync saved/applied state from fetched job
  useEffect(() => {
    if (job) {
      setIsSaved(job.is_saved ?? false);
      setHasApplied(job.has_applied ?? false);
    }
  }, [job]);

  // Load saved profile when apply modal opens
  useEffect(() => {
    if (!applyModalVisible) return;
    getSavedProfile().then((profile) => {
      setSavedProfile(profile);
    }).catch(() => {
      // Silently ignore — saved profile is optional
    });
  }, [applyModalVisible]);

  if (safeId === 0) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.invalidId', 'Invalid job ID.')}</Text>
        <Pressable onPress={() => router.back()} className="mt-3">
          <Text style={{ color: primary }} className="text-[15px] font-semibold">
            {t('detail.goBack', 'Go Back')}
          </Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!job) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.notFound', 'Job not found.')}</Text>
        <Pressable onPress={() => router.back()} className="mt-3">
          <Text style={{ color: primary }} className="text-[15px] font-semibold">
            {t('detail.goBack', 'Go Back')}
          </Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  async function handleToggleSave() {
    if (!job || saveLoading) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setSaveLoading(true);
    try {
      if (isSaved) {
        await unsaveJob(job.id);
        setIsSaved(false);
      } else {
        await saveJob(job.id);
        setIsSaved(true);
      }
    } catch {
      Alert.alert(t('common:errors.alertTitle', 'Error'), t('detail.saveError', 'Could not update saved state.'));
    } finally {
      setSaveLoading(false);
    }
  }

  async function handleSubmitApplication() {
    if (!job || applyLoading || !coverMessage.trim()) return;
    setApplyLoading(true);
    try {
      await applyToJob(job.id, coverMessage.trim());
      setApplySuccess(true);
      setHasApplied(true);
    } catch {
      Alert.alert(t('common:errors.alertTitle', 'Error'), t('apply.error'));
    } finally {
      setApplyLoading(false);
    }
  }

  function handleCloseModal() {
    setApplyModalVisible(false);
    setCoverMessage('');
    setApplySuccess(false);
  }

  async function handleShare() {
    if (!job) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        title: job.title,
        message: `${job.title}\n\n${WEB_URL}/jobs/${job.id}`,
        url: `${WEB_URL}/jobs/${job.id}`,
      });
    } catch {
      // User dismissed share sheet — no error needed
    }
  }

  const typeColor: Record<JobVacancy['type'], string> = {
    paid: theme.success,
    volunteer: primary,
    timebank: theme.warning,
  };

  const deadlineDaysLeft = (() => {
    if (!job.deadline) return null;
    const diff = Math.ceil(
      (new Date(job.deadline).getTime() - Date.now()) / (1000 * 60 * 60 * 24),
    );
    return diff;
  })();

  const deadlineLabel = (() => {
    if (deadlineDaysLeft === null) return null;
    if (deadlineDaysLeft < 0) return t('detail.closedBadge');
    if (deadlineDaysLeft === 0) return t('detail.closesToday', 'Closes today');
    return t('detail.closesIn', 'Closes in {{count}} days', { count: deadlineDaysLeft });
  })();

  const isClosed = job.status !== 'open';

  const matchPct = job.match_percentage ?? null;
  const matchColor =
    matchPct !== null
      ? matchPct >= 70
        ? theme.success
        : matchPct >= 40
          ? theme.warning
          : theme.error
      : primary;

  const salaryLabel = (() => {
    if (
      job.salary_min !== null &&
      job.salary_max !== null &&
      job.salary_type
    ) {
      const currency = job.salary_currency ?? '€';
      const fmt = (n: number) => `${currency}${n.toLocaleString()}`;
      const typeLabel =
        job.salary_type === 'annual'
          ? t('detail.salaryAnnual', 'year')
          : job.salary_type === 'monthly'
            ? t('detail.salaryMonthly', 'month')
            : t('detail.salaryHourly', 'hour');
      return `${fmt(job.salary_min)} – ${fmt(job.salary_max)} / ${typeLabel}`;
    }
    return null;
  })();

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      <ScrollView contentContainerStyle={{ padding: 20, paddingBottom: 48 }}>
        {/* Title */}
        <View className="flex-row items-start gap-2.5 mb-4">
          <Text className="flex-1 text-xl font-bold text-foreground">{job.title}</Text>
          {matchPct !== null ? (
            <View style={{ backgroundColor: matchColor + '22', borderColor: matchColor }} className="rounded-lg px-2 py-1 self-start border">
              <Text style={{ color: matchColor }} className="text-xs font-bold">
                {t('detail.matchPercentage', { percentage: matchPct })}
              </Text>
            </View>
          ) : null}
        </View>

        {/* Organisation / creator */}
        <View className="mb-5">
          <View className="flex-row items-center gap-3">
            <Avatar
              uri={job.organization?.logo_url ?? job.creator.avatar_url}
              name={job.organization?.name ?? job.creator.name}
              size={36}
            />
            <View>
              <Text className="text-sm font-semibold text-foreground">
                {job.organization?.name ?? job.creator.name}
              </Text>
              {isClosed ? (
                <View className="rounded bg-danger/10 px-2 py-0.5 self-start mt-1">
                  <Text className="text-[11px] font-semibold text-danger">
                    {t('detail.closedBadge')}
                  </Text>
                </View>
              ) : null}
            </View>
          </View>
        </View>

        {/* Meta card */}
        <View className="bg-surface rounded-2xl p-4 gap-2.5 border border-border/50 mb-5">
          {/* Type chip */}
          <MetaRow
            icon="briefcase-outline"
            text={t(`filters.type.${job.type}`)}
            tint={typeColor[job.type]}
            theme={theme}
          />

          {/* Commitment */}
          <MetaRow
            icon="repeat-outline"
            text={t(`filters.commitment.${job.commitment}`)}
            theme={theme}
          />

          {/* Location */}
          {job.is_remote ? (
            <MetaRow icon="wifi-outline" text={t('card.remote')} theme={theme} tint={primary} />
          ) : job.location ? (
            <MetaRow icon="location-outline" text={job.location} theme={theme} />
          ) : null}

          {/* Salary */}
          {salaryLabel ? (
            <MetaRow icon="cash-outline" text={salaryLabel} theme={theme} tint={theme.success} />
          ) : job.time_credits !== null ? (
            <MetaRow
              icon="time-outline"
              text={t('detail.timeCredits', '{{count}} time credits', { count: job.time_credits })}
              theme={theme}
              tint={theme.warning}
            />
          ) : null}

          {/* Deadline */}
          {deadlineLabel ? (
            <MetaRow
              icon="calendar-outline"
              text={deadlineLabel}
              theme={theme}
              tint={deadlineDaysLeft !== null && deadlineDaysLeft < 7 ? theme.error : undefined}
            />
          ) : null}

          {/* Applications count */}
          <MetaRow
            icon="people-outline"
            text={t('card.applications', { count: job.applications_count })}
            theme={theme}
          />
        </View>

        {/* Skills */}
        {(job.skills_required ?? []).length > 0 ? (
          <View className="mb-5">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2">
              {t('detail.skills')}
            </Text>
            <View className="flex-row flex-wrap gap-2">
              {(job.skills_required ?? []).map((skill) => (
                <View
                  key={skill}
                  className="rounded-lg px-2.5 py-1 border border-border bg-surface"
                >
                  <Text className="text-xs text-foreground">{skill}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {/* Description */}
        {job.description ? (
          <View className="mb-5">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2">
              {t('detail.description')}
            </Text>
            <Text className="text-sm text-foreground">{job.description}</Text>
          </View>
        ) : null}

        {/* Footer action buttons */}
        <View className="flex-row gap-3 mt-2">
          <Pressable
            className="flex-1 flex-row items-center justify-center gap-2 rounded-xl py-3.5 border border-border"
            onPress={() => void handleShare()}
            accessibilityRole="button"
            accessibilityLabel={t('detail.share', 'Share')}
          >
            <Ionicons name="share-outline" size={18} color={theme.textSecondary} />
          </Pressable>
          <Pressable
            className="flex-1 flex-row items-center justify-center gap-2 rounded-xl py-3.5 border"
            style={{ borderColor: primary }}
            onPress={() => void handleToggleSave()}
            disabled={saveLoading}
            accessibilityRole="button"
            accessibilityLabel={isSaved ? t('detail.saved') : t('detail.save')}
          >
            <Ionicons
              name={isSaved ? 'bookmark' : 'bookmark-outline'}
              size={18}
              color={primary}
            />
            <Text style={{ color: primary }} className="text-sm font-semibold">
              {isSaved ? t('detail.saved') : t('detail.save')}
            </Text>
          </Pressable>

          <Pressable
            className="flex-[2] flex-row items-center justify-center gap-2 rounded-xl py-3.5"
            style={{ backgroundColor: hasApplied || isClosed ? theme.textMuted : primary }}
            onPress={() => {
              if (!hasApplied && !isClosed) setApplyModalVisible(true);
            }}
            disabled={hasApplied || isClosed}
            accessibilityRole="button"
            accessibilityLabel={hasApplied ? t('detail.applied') : t('detail.apply')}
          >
            <Ionicons
              name={hasApplied ? 'checkmark-circle' : 'send-outline'}
              size={18}
              color="#fff" // contrast on primary
            />
            <Text className="text-base font-bold text-white">
              {hasApplied ? t('detail.applied') : t('detail.apply')}
            </Text>
          </Pressable>
        </View>
      </ScrollView>

      {/* Apply Modal */}
      <Modal
        visible={applyModalVisible}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={handleCloseModal}
      >
        <KeyboardAvoidingView
          style={{ flex: 1, backgroundColor: theme.bg }}
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        >
          <SafeAreaView className="flex-1">
            <View className="flex-row items-center justify-between px-5 py-3 border-b border-border/50">
              <Pressable onPress={handleCloseModal} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                <Ionicons name="close" size={24} color={theme.text} />
              </Pressable>
              <Text className="text-base font-bold text-foreground flex-1 text-center mx-2">
                {t('apply.title', { jobTitle: job.title })}
              </Text>
              <View style={{ width: 24 }} />
            </View>

            {applySuccess ? (
              <View className="flex-1 items-center justify-center p-10">
                <Ionicons name="checkmark-circle" size={64} color={theme.success} />
                <Text className="text-xl font-bold text-foreground mt-5">{t('apply.success')}</Text>
                <Text className="text-sm text-muted-foreground text-center mt-2">{t('apply.successMessage')}</Text>
                <Pressable
                  className="flex-row items-center justify-center gap-2 rounded-xl py-3.5 mt-6 w-full"
                  style={{ backgroundColor: primary }}
                  onPress={handleCloseModal}
                >
                  <Text className="text-base font-bold text-white">{t('detail.goBack', 'Go Back')}</Text>
                </Pressable>
              </View>
            ) : (
              <ScrollView
                contentContainerStyle={{ padding: 20, paddingBottom: 48 }}
                keyboardShouldPersistTaps="handled"
              >
                {/* Saved profile one-click apply */}
                {savedProfile?.cover_text ? (
                  <Pressable
                    style={{
                      flexDirection: 'row',
                      alignItems: 'center',
                      gap: 8,
                      paddingHorizontal: 12,
                      paddingVertical: 8,
                      borderRadius: 20,
                      backgroundColor: withAlpha(primary, 0.10),
                      alignSelf: 'flex-start',
                      marginBottom: 12,
                    }}
                    onPress={() => setCoverMessage(savedProfile.cover_text ?? '')}
                  >
                    <Ionicons name="flash-outline" size={14} color={primary} />
                    <Text style={{ fontSize: 13, fontWeight: '600', color: primary }}>
                      {t('saved_profile.use', 'Use Saved Cover Letter')}
                    </Text>
                  </Pressable>
                ) : null}

                <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2">
                  {t('apply.messageLabel')}
                </Text>
                <TextInput
                  style={{ color: theme.text, borderColor: theme.border, backgroundColor: theme.surface }}
                  className="border rounded-xl p-3 text-sm min-h-[140px] mt-2"
                  placeholder={t('apply.messagePlaceholder')}
                  placeholderTextColor={theme.textMuted}
                  value={coverMessage}
                  onChangeText={setCoverMessage}
                  multiline
                  numberOfLines={6}
                  textAlignVertical="top"
                  autoFocus
                />

                <Pressable
                  className="flex-row items-center justify-center gap-2 rounded-xl py-3.5 mt-5"
                  style={{
                    backgroundColor:
                      coverMessage.trim().length === 0 || applyLoading
                        ? theme.textMuted
                        : primary,
                  }}
                  onPress={() => void handleSubmitApplication()}
                  disabled={coverMessage.trim().length === 0 || applyLoading}
                >
                  {applyLoading ? (
                    <LoadingSpinner />
                  ) : (
                    <Text className="text-base font-bold text-white">{t('apply.submit')}</Text>
                  )}
                </Pressable>
              </ScrollView>
            )}
          </SafeAreaView>
        </KeyboardAvoidingView>
      </Modal>
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
