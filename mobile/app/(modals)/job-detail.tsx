// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  Alert,
  Modal,
  TextInput,
  KeyboardAvoidingView,
  Platform,
  Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { getJobDetail, applyToJob, saveJob, unsaveJob, getSavedProfile } from '@/lib/api/jobs';
import type { JobVacancy } from '@/lib/api/jobs';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function JobDetailScreen() {
  const { t } = useTranslation('jobs');
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();

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
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detailTitle')} backLabel={t('common:back')} fallbackHref="/(modals)/jobs" />
        <EmptyState
          icon="briefcase-outline"
          title={t('detail.invalidId')}
          subtitle={t('detail.invalidIdHint')}
          actionLabel={t('detail.browseJobs')}
          onAction={() => router.replace('/(modals)/jobs')}
        />
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detailTitle')} backLabel={t('common:back')} fallbackHref="/(modals)/jobs" />
        <View className="flex-1 items-center justify-center">
          <LoadingSpinner />
        </View>
      </SafeAreaView>
    );
  }

  if (!job) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detailTitle')} backLabel={t('common:back')} fallbackHref="/(modals)/jobs" />
        <EmptyState
          icon="briefcase-outline"
          title={t('detail.notFound')}
          subtitle={t('detail.notFoundHint')}
          actionLabel={t('detail.browseJobs')}
          onAction={() => router.replace('/(modals)/jobs')}
        />
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
      Alert.alert(t('common:errors.alertTitle'), t('detail.saveError'));
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
      Alert.alert(t('common:errors.alertTitle'), t('apply.error'));
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

  const successColor = theme.success ?? '#22c55e';
  const warningColor = theme.warning ?? '#f59e0b';
  const typeColor: Record<JobVacancy['type'], string> = {
    paid: successColor,
    volunteer: primary,
    timebank: warningColor,
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
    if (deadlineDaysLeft === 0) return t('detail.closesToday');
    return t('detail.closesIn', { count: deadlineDaysLeft });
  })();

  const isClosed = job.status !== 'open';

  const matchPct = job.match_percentage ?? null;
  const matchColor =
    matchPct !== null
      ? matchPct >= 70
        ? successColor
        : matchPct >= 40
          ? warningColor
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
          ? t('detail.salaryAnnual')
          : job.salary_type === 'monthly'
            ? t('detail.salaryMonthly')
            : t('detail.salaryHourly');
      return `${fmt(job.salary_min)} – ${fmt(job.salary_max)} / ${typeLabel}`;
    }
    return null;
  })();
  const displayName = job.organization?.name ?? job.creator.name;
  const displayAvatar = job.organization?.logo_url ?? job.creator.avatar_url;
  const compensationLabel = salaryLabel
    ?? (job.time_credits !== null ? t('detail.timeCredits', { count: job.time_credits }) : null);

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar
        title={t('detailTitle')}
        backLabel={t('common:back')}
        fallbackHref="/(modals)/jobs"
        rightAction={{
          accessibilityLabel: t('detail.share'),
          icon: 'share-outline',
          onPress: handleShare,
        }}
      />

      <ScrollView contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}>
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: isClosed ? theme.textMuted : typeColor[job.type] }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-14 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(typeColor[job.type], 0.14) }}>
                <Ionicons name="briefcase-outline" size={27} color={typeColor[job.type]} />
              </View>
              <View className="min-w-0 flex-1 gap-2">
                <View className="flex-row flex-wrap gap-2">
                  <Chip size="sm" variant="secondary">
                    <Chip.Label>{t(`filters.type.${job.type}`)}</Chip.Label>
                  </Chip>
                  <Chip size="sm" variant="secondary">
                    <Chip.Label>{t(`filters.commitment.${job.commitment}`)}</Chip.Label>
                  </Chip>
                  {job.is_featured ? (
                    <Chip size="sm" variant="secondary" color="warning">
                      <Ionicons name="star-outline" size={12} color={warningColor} />
                      <Chip.Label>{t('card.featured')}</Chip.Label>
                    </Chip>
                  ) : null}
                  {isClosed ? (
                    <Chip size="sm" variant="secondary" color="danger">
                      <Chip.Label>{t('detail.closedBadge')}</Chip.Label>
                    </Chip>
                  ) : null}
                </View>
                <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }}>
                  {job.title}
                </Text>
              </View>
            </View>

            <Surface variant="secondary" className="flex-row items-center gap-3 rounded-panel-inner p-3">
              <Avatar uri={displayAvatar} name={displayName} size={40} />
              <View className="min-w-0 flex-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>
                  {t('detail.postedBy')}
                </Text>
                <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={1}>
                  {displayName}
                </Text>
              </View>
            </Surface>

            <View className="flex-row flex-wrap gap-2">
              {matchPct !== null ? (
                <Chip size="sm" variant="secondary">
                  <Ionicons name="sparkles-outline" size={12} color={matchColor} />
                  <Chip.Label>{t('detail.matchPercentage', { percentage: matchPct })}</Chip.Label>
                </Chip>
              ) : null}
              <Chip size="sm" variant="secondary">
                <Ionicons name="people-outline" size={12} color={theme.textSecondary} />
                <Chip.Label>{t('card.applications', { count: job.applications_count })}</Chip.Label>
              </Chip>
              <Chip size="sm" variant="secondary">
                <Ionicons name="eye-outline" size={12} color={theme.textSecondary} />
                <Chip.Label>{t('detail.views', { count: job.views_count })}</Chip.Label>
              </Chip>
            </View>
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="mb-4 rounded-panel p-0">
          <HeroCard.Body className="gap-3 p-4">
            <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>
              {t('detail.keyDetails')}
            </Text>
            <MetaRow icon="briefcase-outline" text={t(`filters.type.${job.type}`)} tint={typeColor[job.type]} theme={theme} />
            <MetaRow icon="repeat-outline" text={t(`filters.commitment.${job.commitment}`)} theme={theme} />
            {job.is_remote ? (
              <MetaRow icon="wifi-outline" text={t('card.remote')} theme={theme} tint={primary} />
            ) : job.location ? (
              <MetaRow icon="location-outline" text={job.location} theme={theme} />
            ) : null}
            {compensationLabel ? (
              <MetaRow
                icon={salaryLabel ? 'cash-outline' : 'time-outline'}
                text={compensationLabel}
                theme={theme}
                tint={salaryLabel ? successColor : warningColor}
              />
            ) : null}
            {deadlineLabel ? (
              <MetaRow
                icon="calendar-outline"
                text={deadlineLabel}
                theme={theme}
                tint={deadlineDaysLeft !== null && deadlineDaysLeft < 7 ? theme.error : undefined}
              />
            ) : null}
            {job.category ? (
              <MetaRow icon="folder-open-outline" text={job.category} theme={theme} />
            ) : null}
          </HeroCard.Body>
        </HeroCard>

        {(job.skills_required ?? []).length > 0 ? (
          <DetailSection title={t('detail.skills')} theme={theme}>
            <View className="flex-row flex-wrap gap-2">
              {(job.skills_required ?? []).map((skill) => (
                <Chip key={skill} size="sm" variant="secondary">
                  <Chip.Label>{skill}</Chip.Label>
                </Chip>
              ))}
            </View>
          </DetailSection>
        ) : null}

        {job.description ? (
          <DetailSection title={t('detail.description')} theme={theme}>
            <Text className="text-sm leading-6" style={{ color: theme.text }}>
              {job.description}
            </Text>
          </DetailSection>
        ) : null}
      </ScrollView>

      <Surface variant="default" className="absolute bottom-0 left-0 right-0 gap-3 border-t border-border px-4 pb-5 pt-3">
        <View className="flex-row gap-2">
          <HeroButton
            className="flex-1"
            variant="secondary"
            accessibilityLabel={t('detail.share')}
            onPress={() => void handleShare()}
          >
            <Ionicons name="share-outline" size={17} color={theme.textSecondary} />
            <HeroButton.Label>{t('detail.share')}</HeroButton.Label>
          </HeroButton>
          <HeroButton
            className="flex-1"
            variant="secondary"
            accessibilityLabel={isSaved ? t('detail.saved') : t('detail.save')}
            isDisabled={saveLoading}
            onPress={() => void handleToggleSave()}
          >
            <Ionicons name={isSaved ? 'bookmark' : 'bookmark-outline'} size={17} color={primary} />
            <HeroButton.Label>{isSaved ? t('detail.saved') : t('detail.save')}</HeroButton.Label>
          </HeroButton>
        </View>
        <HeroButton
          variant="primary"
          accessibilityLabel={hasApplied ? t('detail.applied') : t('detail.apply')}
          isDisabled={hasApplied || isClosed}
          onPress={() => {
            if (!hasApplied && !isClosed) setApplyModalVisible(true);
          }}
          style={hasApplied || isClosed ? { backgroundColor: theme.textMuted } : { backgroundColor: primary }}
        >
          <Ionicons name={hasApplied ? 'checkmark-circle' : 'send-outline'} size={18} color="#fff" />
          <HeroButton.Label>{hasApplied ? t('detail.applied') : t('detail.apply')}</HeroButton.Label>
        </HeroButton>
      </Surface>

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
              <HeroButton isIconOnly variant="secondary" onPress={handleCloseModal} accessibilityLabel={t('common:close')}>
                <Ionicons name="close" size={24} color={theme.text} />
              </HeroButton>
              <Text className="text-base font-bold text-foreground flex-1 text-center mx-2">
                {t('apply.title', { jobTitle: job.title })}
              </Text>
              <View style={{ width: 44 }} />
            </View>

            {applySuccess ? (
              <View className="flex-1 items-center justify-center p-10">
                <Ionicons name="checkmark-circle" size={64} color={successColor} />
                <Text className="text-xl font-bold text-foreground mt-5">{t('apply.success')}</Text>
                <Text className="text-sm text-muted-foreground text-center mt-2">{t('apply.successMessage')}</Text>
                <HeroButton
                  className="mt-6 w-full"
                  variant="primary"
                  style={{ backgroundColor: primary }}
                  onPress={handleCloseModal}
                >
                  <HeroButton.Label>{t('detail.goBack')}</HeroButton.Label>
                </HeroButton>
              </View>
            ) : (
              <ScrollView
                contentContainerStyle={{ padding: 20, paddingBottom: 48 }}
                keyboardShouldPersistTaps="handled"
              >
                {/* Saved profile one-click apply */}
                {savedProfile?.cover_text ? (
                  <HeroButton
                    variant="secondary"
                    style={{
                      backgroundColor: withAlpha(primary, 0.10),
                      alignSelf: 'flex-start',
                      marginBottom: 12,
                    }}
                    onPress={() => setCoverMessage(savedProfile.cover_text ?? '')}
                  >
                    <Ionicons name="flash-outline" size={14} color={primary} />
                    <HeroButton.Label>{t('saved_profile.use')}</HeroButton.Label>
                  </HeroButton>
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

                <HeroButton
                  className="mt-5"
                  variant="primary"
                  style={{
                    backgroundColor:
                      coverMessage.trim().length === 0 || applyLoading
                        ? theme.textMuted
                        : primary,
                  }}
                  onPress={() => void handleSubmitApplication()}
                  isDisabled={coverMessage.trim().length === 0 || applyLoading}
                >
                  {applyLoading ? (
                    <LoadingSpinner />
                  ) : (
                    <HeroButton.Label>{t('apply.submit')}</HeroButton.Label>
                  )}
                </HeroButton>
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

function DetailSection({
  title,
  children,
  theme,
}: {
  title: string;
  children: React.ReactNode;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <HeroCard className="mb-4 rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>
          {title}
        </Text>
        {children}
      </HeroCard.Body>
    </HeroCard>
  );
}
