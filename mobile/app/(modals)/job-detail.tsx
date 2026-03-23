// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useMemo } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  Alert,
  Modal,
  TextInput,
  KeyboardAvoidingView,
  Platform,
  Share,
} from 'react-native';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { getJobDetail, applyToJob, saveJob, unsaveJob, getSavedProfile } from '@/lib/api/jobs';
import type { JobVacancy } from '@/lib/api/jobs';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

const WEB_URL = 'https://app.project-nexus.ie';

export default function JobDetailScreen() {
  const { t } = useTranslation('jobs');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
    });
  }, [applyModalVisible]);

  if (safeId === 0) {
    return (
      <SafeAreaView style={styles.center}>
        <Text style={styles.errorText}>{t('detail.invalidId', 'Invalid job ID.')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack', 'Go back')}
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

  if (!job) {
    return (
      <SafeAreaView style={styles.center}>
        <Text style={styles.errorText}>{t('detail.notFound', 'Job not found.')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack', 'Go back')}
          </Text>
        </TouchableOpacity>
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
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.content}>
        {/* Title */}
        <View style={styles.titleRow}>
          <Text style={styles.title}>{job.title}</Text>
          {matchPct !== null ? (
            <View style={[styles.matchBadge, { backgroundColor: matchColor + '22', borderColor: matchColor }]}>
              <Text style={[styles.matchText, { color: matchColor }]}>
                {t('detail.matchPercentage', { percentage: matchPct })}
              </Text>
            </View>
          ) : null}
        </View>

        {/* Organisation / creator */}
        <View style={styles.section}>
          <View style={styles.orgRow}>
            <Avatar
              uri={job.organization?.logo_url ?? job.creator.avatar_url}
              name={job.organization?.name ?? job.creator.name}
              size={36}
            />
            <View>
              <Text style={styles.orgName}>
                {job.organization?.name ?? job.creator.name}
              </Text>
              {isClosed ? (
                <View style={[styles.statusBadge, { backgroundColor: theme.errorBg }]}>
                  <Text style={[styles.statusText, { color: theme.error }]}>
                    {t('detail.closedBadge')}
                  </Text>
                </View>
              ) : null}
            </View>
          </View>
        </View>

        {/* Meta card */}
        <View style={styles.metaCard}>
          {/* Type chip */}
          <MetaRow
            icon="briefcase-outline"
            text={t(`filters.type.${job.type}`)}
            theme={theme}
            tint={typeColor[job.type]}
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
        {job.skills_required.length > 0 ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('detail.skills')}</Text>
            <View style={styles.skillsRow}>
              {job.skills_required.map((skill) => (
                <View
                  key={skill}
                  style={[styles.skillPill, { backgroundColor: theme.surface, borderColor: theme.border }]}
                >
                  <Text style={styles.skillText}>{skill}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {/* Description */}
        {job.description ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('detail.description')}</Text>
            <Text style={styles.description}>{job.description}</Text>
          </View>
        ) : null}

        {/* Footer action buttons */}
        <View style={styles.actions}>
          <TouchableOpacity
            style={[styles.saveButton, { borderColor: theme.border }]}
            onPress={() => void handleShare()}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel={t('detail.share', 'Share')}
          >
            <Ionicons name="share-outline" size={18} color={theme.textSecondary} />
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.saveButton, { borderColor: primary }]}
            onPress={() => void handleToggleSave()}
            disabled={saveLoading}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel={isSaved ? t('detail.saved') : t('detail.save')}
          >
            <Ionicons
              name={isSaved ? 'bookmark' : 'bookmark-outline'}
              size={18}
              color={primary}
            />
            <Text style={[styles.saveButtonText, { color: primary }]}>
              {isSaved ? t('detail.saved') : t('detail.save')}
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.applyButton,
              { backgroundColor: hasApplied || isClosed ? theme.textMuted : primary },
            ]}
            onPress={() => {
              if (!hasApplied && !isClosed) setApplyModalVisible(true);
            }}
            disabled={hasApplied || isClosed}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel={hasApplied ? t('detail.applied') : t('detail.apply')}
          >
            <Ionicons
              name={hasApplied ? 'checkmark-circle' : 'send-outline'}
              size={18}
              color="#fff" // contrast on primary
            />
            <Text style={styles.applyButtonText}>
              {hasApplied ? t('detail.applied') : t('detail.apply')}
            </Text>
          </TouchableOpacity>
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
          <SafeAreaView style={{ flex: 1 }}>
            <View style={styles.modalHeader}>
              <TouchableOpacity onPress={handleCloseModal} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                <Ionicons name="close" size={24} color={theme.text} />
              </TouchableOpacity>
              <Text style={styles.modalTitle}>
                {t('apply.title', { jobTitle: job.title })}
              </Text>
              <View style={{ width: 24 }} />
            </View>

            {applySuccess ? (
              <View style={styles.successContainer}>
                <Ionicons name="checkmark-circle" size={64} color={theme.success} />
                <Text style={styles.successTitle}>{t('apply.success')}</Text>
                <Text style={styles.successMessage}>{t('apply.successMessage')}</Text>
                <TouchableOpacity
                  style={[styles.applyButton, { backgroundColor: primary, marginTop: 24 }]}
                  onPress={handleCloseModal}
                >
                  <Text style={styles.applyButtonText}>{t('detail.goBack', 'Done')}</Text>
                </TouchableOpacity>
              </View>
            ) : (
              <ScrollView
                contentContainerStyle={styles.modalContent}
                keyboardShouldPersistTaps="handled"
              >
                {/* Saved profile one-click apply */}
                {savedProfile?.cover_text ? (
                  <TouchableOpacity
                    style={{
                      flexDirection: 'row',
                      alignItems: 'center',
                      gap: 8,
                      paddingHorizontal: 12,
                      paddingVertical: 8,
                      borderRadius: 20,
                      backgroundColor: primary + '1a',
                      alignSelf: 'flex-start',
                      marginBottom: 12,
                    }}
                    onPress={() => setCoverMessage(savedProfile.cover_text ?? '')}
                    activeOpacity={0.8}
                  >
                    <Ionicons name="flash-outline" size={14} color={primary} />
                    <Text style={{ fontSize: 13, fontWeight: '600', color: primary }}>
                      {t('saved_profile.use', 'Use Saved Cover Letter')}
                    </Text>
                  </TouchableOpacity>
                ) : null}

                <Text style={styles.sectionTitle}>{t('apply.messageLabel')}</Text>
                <TextInput
                  style={[styles.textarea, { color: theme.text, borderColor: theme.border, backgroundColor: theme.surface }]}
                  placeholder={t('apply.messagePlaceholder')}
                  placeholderTextColor={theme.textMuted}
                  value={coverMessage}
                  onChangeText={setCoverMessage}
                  multiline
                  numberOfLines={6}
                  textAlignVertical="top"
                  autoFocus
                />

                <TouchableOpacity
                  style={[
                    styles.applyButton,
                    {
                      backgroundColor:
                        coverMessage.trim().length === 0 || applyLoading
                          ? theme.textMuted
                          : primary,
                      marginTop: 20,
                    },
                  ]}
                  onPress={() => void handleSubmitApplication()}
                  disabled={coverMessage.trim().length === 0 || applyLoading}
                  activeOpacity={0.8}
                >
                  {applyLoading ? (
                    <LoadingSpinner />
                  ) : (
                    <Text style={styles.applyButtonText}>{t('apply.submit')}</Text>
                  )}
                </TouchableOpacity>
              </ScrollView>
            )}
          </SafeAreaView>
        </KeyboardAvoidingView>
      </Modal>
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

const metaRowStyle = {
  flexDirection: 'row' as const,
  alignItems: 'center' as const,
  gap: 10,
};

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
    matchBadge: {
      borderRadius: 8,
      paddingHorizontal: 8,
      paddingVertical: 5,
      alignSelf: 'flex-start',
      borderWidth: 1,
    },
    matchText: { fontSize: 12, fontWeight: '700' },
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
    statusBadge: {
      borderRadius: 6,
      paddingHorizontal: 8,
      paddingVertical: 3,
      alignSelf: 'flex-start',
      marginTop: 4,
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
    skillsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    skillPill: {
      borderRadius: 8,
      paddingHorizontal: 10,
      paddingVertical: 5,
      borderWidth: 1,
    },
    skillText: { fontSize: 13, color: theme.text },
    description: { fontSize: 15, color: theme.text, lineHeight: 22 },
    actions: {
      flexDirection: 'row',
      gap: 12,
      marginTop: 8,
    },
    saveButton: {
      flex: 1,
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 8,
      borderRadius: 12,
      paddingVertical: 14,
      borderWidth: 1.5,
    },
    saveButtonText: { fontSize: 15, fontWeight: '600' },
    applyButton: {
      flex: 2,
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 8,
      borderRadius: 12,
      paddingVertical: 14,
    },
    applyButtonText: { fontSize: 16, fontWeight: '700', color: '#fff' }, // contrast on primary
    errorText: { fontSize: 15, color: theme.textMuted },
    // Modal
    modalHeader: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      paddingHorizontal: 20,
      paddingVertical: 16,
      borderBottomWidth: 1,
      borderBottomColor: theme.borderSubtle,
    },
    modalTitle: {
      fontSize: 16,
      fontWeight: '700',
      color: theme.text,
      flex: 1,
      textAlign: 'center',
      marginHorizontal: 8,
    },
    modalContent: {
      padding: 20,
      paddingBottom: 48,
    },
    textarea: {
      borderWidth: 1,
      borderRadius: 12,
      padding: 12,
      fontSize: 15,
      minHeight: 140,
      marginTop: 8,
    },
    successContainer: {
      flex: 1,
      alignItems: 'center',
      justifyContent: 'center',
      padding: 40,
    },
    successTitle: {
      fontSize: 22,
      fontWeight: '700',
      color: theme.text,
      marginTop: 20,
    },
    successMessage: {
      fontSize: 15,
      color: theme.textSecondary,
      textAlign: 'center',
      marginTop: 8,
    },
  });
}
