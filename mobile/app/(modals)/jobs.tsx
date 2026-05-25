// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  FlatList,
  View,
  Text,
  TextInput,
  Pressable,
  RefreshControl,
  ScrollView,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import {
  getJobs,
  getMyApplications,
  acceptInterview,
  declineInterview,
  acceptOffer,
  rejectOffer,
  type JobVacancy,
  type JobApplication,
  type JobsResponse,
  type ApplicationsResponse,
} from '@/lib/api/jobs';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

// ---------------------------------------------------------------------------
// Type filter options
// ---------------------------------------------------------------------------

const JOB_TYPES = ['', 'paid', 'volunteer', 'timebank'] as const;
const COMMITMENT_TYPES = ['', 'full_time', 'part_time', 'flexible', 'one_off'] as const;

// ---------------------------------------------------------------------------
// Job card component
// ---------------------------------------------------------------------------

function JobCard({
  item,
  primary,
  theme,
  t,
  onPress,
}: {
  item: JobVacancy;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onPress: () => void;
}) {
  const typeColor =
    item.type === 'paid'
      ? theme.success
      : item.type === 'volunteer'
        ? primary
        : theme.warning;

  const deadlineStr = item.deadline
    ? t('card.deadline', {
        date: new Date(item.deadline).toLocaleDateString('default', {
          month: 'short',
          day: 'numeric',
          year: 'numeric',
        }),
      })
    : null;

  const displayName = item.organization?.name ?? item.creator.name;

  const salaryStr = (() => {
    if (item.salary_min !== null && item.salary_max !== null && item.salary_type) {
      const currency = item.salary_currency ?? '€';
      const fmt = (n: number) =>
        n >= 1000 ? `${currency}${Math.round(n / 1000)}k` : `${currency}${n}`;
      const typeKey =
        item.salary_type === 'annual'
          ? 'yr'
          : item.salary_type === 'monthly'
            ? 'mo'
            : 'hr';
      return `${fmt(item.salary_min)} – ${fmt(item.salary_max)} / ${typeKey}`;
    }
    return null;
  })();

  const visibleSkills = (item.skills_required ?? []).slice(0, 3);

  return (
    <Pressable
      className="bg-surface rounded-2xl p-4 mb-3 border border-border/50 gap-2"
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={item.title}
    >
      {/* Featured badge */}
      {item.is_featured ? (
        <View className="self-start rounded bg-warning/20 px-2 py-0.5">
          <Text className="text-[11px] font-bold text-warning uppercase tracking-[0.4px]">
            {t('card.featured')}
          </Text>
        </View>
      ) : null}

      {/* Title row */}
      <View className="flex-row items-start gap-2">
        <Text className="flex-1 text-sm font-semibold text-foreground" numberOfLines={2}>
          {item.title}
        </Text>
        <View style={{ backgroundColor: typeColor + '22' }} className="rounded px-2 py-0.5 self-start">
          <Text style={{ color: typeColor }} className="text-[11px] font-semibold">
            {t(`filters.type.${item.type}`)}
          </Text>
        </View>
      </View>

      {/* Organisation / creator */}
      <Text className="text-xs text-muted-foreground" numberOfLines={1}>
        {displayName}
      </Text>

      {/* Meta row */}
      <View className="flex-row flex-wrap gap-2 items-center">
        {item.is_remote ? (
          <View style={{ backgroundColor: withAlpha(primary, 0.10) }} className="rounded px-2 py-0.5">
            <Text style={{ color: primary }} className="text-[11px] font-semibold">
              {t('card.remote')}
            </Text>
          </View>
        ) : item.location ? (
          <View className="flex-row items-center gap-1">
            <Ionicons name="location-outline" size={13} color={theme.textMuted} />
            <Text className="text-[11px] text-muted-foreground" numberOfLines={1}>
              {item.location}
            </Text>
          </View>
        ) : null}

        {salaryStr ? (
          <View className="flex-row items-center gap-1">
            <Ionicons name="cash-outline" size={13} color={theme.textMuted} />
            <Text className="text-[11px] text-muted-foreground">{salaryStr}</Text>
          </View>
        ) : null}

        {deadlineStr ? (
          <View className="flex-row items-center gap-1">
            <Ionicons name="calendar-outline" size={13} color={theme.textMuted} />
            <Text className="text-[11px] text-muted-foreground">{deadlineStr}</Text>
          </View>
        ) : null}

        <View className="flex-row items-center gap-1">
          <Ionicons name="people-outline" size={13} color={theme.textMuted} />
          <Text className="text-[11px] text-muted-foreground">
            {t('card.applications', { count: item.applications_count })}
          </Text>
        </View>
      </View>

      {/* Skills */}
      {visibleSkills.length > 0 ? (
        <View className="flex-row flex-wrap gap-1.5">
          {visibleSkills.map((skill) => (
            <View key={skill} className="rounded px-2 py-0.5 border border-border bg-background">
              <Text className="text-[11px] text-muted-foreground">{skill}</Text>
            </View>
          ))}
          {(item.skills_required ?? []).length > 3 ? (
            <View className="rounded px-2 py-0.5 border border-border bg-background">
              <Text className="text-[11px] text-muted-foreground">+{item.skills_required.length - 3}</Text>
            </View>
          ) : null}
        </View>
      ) : null}
    </Pressable>
  );
}

// ---------------------------------------------------------------------------
// Application card component
// ---------------------------------------------------------------------------

function ApplicationCard({
  item,
  theme,
  t,
  primary,
  onInterviewAccepted,
  onInterviewDeclined,
  onOfferAccepted,
  onOfferRejected,
}: {
  item: JobApplication;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
  primary: string;
  onInterviewAccepted: (interviewId: number) => void;
  onInterviewDeclined: (interviewId: number) => void;
  onOfferAccepted: (offerId: number) => void;
  onOfferRejected: (offerId: number) => void;
}) {
  const [actionLoading, setActionLoading] = useState(false);

  const statusColor: Record<JobApplication['status'], string> = {
    pending: theme.warning,
    screening: theme.info,
    reviewed: theme.info,
    interview: theme.info,
    offer: theme.warning,
    accepted: theme.success,
    rejected: theme.error,
    withdrawn: theme.textMuted,
  };

  const color = statusColor[item.status];
  const jobTitle = item.vacancy?.title ?? String(item.vacancy_id);
  const orgName = item.vacancy?.organization?.name ?? item.vacancy?.creator?.name ?? null;

  const appliedStr = t('applications.appliedOn', {
    date: new Date(item.created_at).toLocaleDateString('default', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    }),
  });

  const interview = item.interview ?? null;
  const offer = item.offer ?? null;

  return (
    <View className="bg-surface rounded-2xl p-4 mb-3 border border-border/50 gap-2">
      <View className="flex-row items-start gap-2">
        <Text className="flex-1 text-sm font-semibold text-foreground" numberOfLines={2}>
          {jobTitle}
        </Text>
        <View style={{ backgroundColor: color + '22' }} className="rounded px-2 py-0.5 self-start">
          <Text style={{ color }} className="text-[11px] font-semibold">
            {t(`applications.status.${item.status}`)}
          </Text>
        </View>
      </View>
      {orgName ? (
        <Text className="text-xs text-muted-foreground" numberOfLines={1}>
          {orgName}
        </Text>
      ) : null}
      <View className="flex-row items-center gap-1">
        <Ionicons name="calendar-outline" size={13} color={theme.textMuted} />
        <Text className="text-[11px] text-muted-foreground">{appliedStr}</Text>
      </View>

      {/* Interview actions */}
      {interview?.status === 'proposed' ? (
        <View className="mt-2.5 pt-2.5 border-t border-border">
          <Text className="text-xs font-medium text-muted-foreground mb-1.5">
            {t('applications.interview_proposed')}
          </Text>
          <View className="flex-row gap-2">
            <Pressable
              className="px-3 py-1.5 rounded-lg"
              style={{ backgroundColor: primary }}
              disabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await acceptInterview(interview.id);
                setActionLoading(false);
                if (ok) onInterviewAccepted(interview.id);
              }}
              accessibilityRole="button"
              accessibilityLabel={t('applications.accept_interview')}
            >
              <Text className="text-[13px] font-semibold text-white">{/* contrast on primary */}
                {t('applications.accept_interview')}
              </Text>
            </Pressable>
            <Pressable
              className="px-3 py-1.5 rounded-lg bg-surface border border-border"
              disabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await declineInterview(interview.id);
                setActionLoading(false);
                if (ok) onInterviewDeclined(interview.id);
              }}
              accessibilityRole="button"
              accessibilityLabel={t('applications.decline_interview')}
            >
              <Text className="text-[13px] font-semibold text-danger">
                {t('applications.decline_interview')}
              </Text>
            </Pressable>
          </View>
        </View>
      ) : interview?.status === 'accepted' ? (
        <View className="mt-2">
          <View style={{ backgroundColor: theme.success + '22' }} className="rounded px-2 py-0.5 self-start">
            <Text style={{ color: theme.success }} className="text-[11px] font-semibold">
              {t('applications.interview_confirmed')}
            </Text>
          </View>
        </View>
      ) : null}

      {/* Offer actions */}
      {offer?.status === 'pending' ? (
        <View className="mt-2.5 pt-2.5 border-t border-border">
          <Text className="text-xs font-medium text-muted-foreground mb-1.5">
            {t('applications.offer_received')}
          </Text>
          <View className="flex-row gap-2">
            <Pressable
              className="px-3 py-1.5 rounded-lg"
              style={{ backgroundColor: theme.success }}
              disabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await acceptOffer(offer.id);
                setActionLoading(false);
                if (ok) onOfferAccepted(offer.id);
              }}
              accessibilityRole="button"
              accessibilityLabel={t('applications.accept_offer')}
            >
              <Text className="text-[13px] font-semibold text-white">{/* contrast on primary */}
                {t('applications.accept_offer')}
              </Text>
            </Pressable>
            <Pressable
              className="px-3 py-1.5 rounded-lg bg-surface border border-border"
              disabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await rejectOffer(offer.id);
                setActionLoading(false);
                if (ok) onOfferRejected(offer.id);
              }}
              accessibilityRole="button"
              accessibilityLabel={t('applications.decline_offer')}
            >
              <Text className="text-[13px] font-semibold text-danger">
                {t('applications.decline_offer')}
              </Text>
            </Pressable>
          </View>
        </View>
      ) : offer?.status === 'accepted' ? (
        <View className="mt-2">
          <View style={{ backgroundColor: theme.success + '22' }} className="rounded px-2 py-0.5 self-start">
            <Text style={{ color: theme.success }} className="text-[11px] font-semibold">
              {t('applications.offer_accepted')}
            </Text>
          </View>
        </View>
      ) : null}
    </View>
  );
}

// ---------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------

export default function JobsScreen() {
  const { t } = useTranslation('jobs');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);

  const [activeTab, setActiveTab] = useState<'browse' | 'myApplications'>('browse');
  const [search, setSearch] = useState('');
  const [committedSearch, setCommittedSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [commitmentFilter, setCommitmentFilter] = useState('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  function handleSearchChange(text: string) {
    setSearch(text);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      setCommittedSearch(text.trim());
    }, 400);
  }

  function handleClear() {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    setSearch('');
    setCommittedSearch('');
  }

  // Clean up debounce timer on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  // Browse tab — paginated job list
  const jobFetchFn = useCallback(
    (cursor: string | null) =>
      getJobs({
        cursor,
        search: committedSearch || undefined,
        type: typeFilter || undefined,
        commitment: commitmentFilter || undefined,
      }),
    [committedSearch, typeFilter, commitmentFilter],
  );

  const jobExtractor = useCallback(
    (response: JobsResponse) => ({
      items: response.data,
      cursor: response.meta.cursor,
      hasMore: response.meta.has_more,
    }),
    [],
  );

  const {
    items: jobs,
    isLoading: jobsLoading,
    isLoadingMore: jobsLoadingMore,
    error: jobsError,
    hasMore: jobsHasMore,
    loadMore: loadMoreJobs,
    refresh: refreshJobs,
  } = usePaginatedApi<JobVacancy, JobsResponse>(jobFetchFn, jobExtractor, [
    committedSearch,
    typeFilter,
    commitmentFilter,
  ]);

  // My Applications tab — paginated
  const appFetchFn = useCallback(
    (cursor: string | null) => getMyApplications({ cursor }),
    [],
  );

  const appExtractor = useCallback(
    (response: ApplicationsResponse) => ({
      items: response.data,
      cursor: response.meta.cursor,
      hasMore: response.meta.has_more,
    }),
    [],
  );

  const {
    items: applications,
    isLoading: appsLoading,
    isLoadingMore: appsLoadingMore,
    error: appsError,
    hasMore: appsHasMore,
    loadMore: loadMoreApps,
    refresh: refreshApps,
  } = usePaginatedApi<JobApplication, ApplicationsResponse>(appFetchFn, appExtractor, []);

  const renderJob = useCallback(
    ({ item }: { item: JobVacancy }) => (
      <JobCard
        item={item}
        primary={primary}
        theme={theme}
        t={t}
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          router.push({
            pathname: '/(modals)/job-detail',
            params: { id: String(item.id) },
          });
        }}
      />
    ),
    [primary, theme, t],
  );

  // Update an application's interview status locally (avoid full refresh)
  const handleInterviewAccepted = useCallback((interviewId: number) => {
    // The FlatList data comes from usePaginatedApi — trigger a refresh
    void refreshApps();
    void interviewId; // used to potentially update local state in future
  }, [refreshApps]);

  const handleInterviewDeclined = useCallback((interviewId: number) => {
    void refreshApps();
    void interviewId;
  }, [refreshApps]);

  const handleOfferAccepted = useCallback((offerId: number) => {
    void refreshApps();
    void offerId;
  }, [refreshApps]);

  const handleOfferRejected = useCallback((offerId: number) => {
    void refreshApps();
    void offerId;
  }, [refreshApps]);

  const renderApplication = useCallback(
    ({ item }: { item: JobApplication }) => (
      <ApplicationCard
        item={item}
        theme={theme}
        t={t}
        primary={primary}
        onInterviewAccepted={handleInterviewAccepted}
        onInterviewDeclined={handleInterviewDeclined}
        onOfferAccepted={handleOfferAccepted}
        onOfferRejected={handleOfferRejected}
      />
    ),
    [theme, t, primary, handleInterviewAccepted, handleInterviewDeclined, handleOfferAccepted, handleOfferRejected],
  );

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      {/* Tab bar */}
      <View className="flex-row border-b border-border/50 bg-surface">
        <Pressable
          className="flex-1 py-3 items-center border-b-2"
          style={{ borderBottomColor: activeTab === 'browse' ? primary : 'transparent' }}
          onPress={() => setActiveTab('browse')}
          accessibilityRole="tab"
          accessibilityState={{ selected: activeTab === 'browse' }}
          accessibilityLabel={t('tabs.browse')}
        >
          <Text
            className="text-sm font-semibold"
            style={{ color: activeTab === 'browse' ? primary : theme.textSecondary }}
          >
            {t('tabs.browse')}
          </Text>
        </Pressable>
        <Pressable
          className="flex-1 py-3 items-center border-b-2"
          style={{ borderBottomColor: activeTab === 'myApplications' ? primary : 'transparent' }}
          onPress={() => setActiveTab('myApplications')}
          accessibilityRole="tab"
          accessibilityState={{ selected: activeTab === 'myApplications' }}
          accessibilityLabel={t('tabs.myApplications')}
        >
          <Text
            className="text-sm font-semibold"
            style={{ color: activeTab === 'myApplications' ? primary : theme.textSecondary }}
          >
            {t('tabs.myApplications')}
          </Text>
        </Pressable>
      </View>

      {activeTab === 'browse' ? (
        <>
          {/* Search bar */}
          <View className="flex-row items-center mx-4 my-3 px-3 h-[42px] bg-surface rounded-xl gap-2">
            <Ionicons name="search-outline" size={18} color={theme.textMuted} />
            <TextInput
              className="flex-1 text-sm text-foreground py-0"
              style={{ color: theme.text }}
              placeholder={t('search.placeholder')}
              placeholderTextColor={theme.textMuted}
              value={search}
              onChangeText={handleSearchChange}
              returnKeyType="search"
              clearButtonMode="never"
              autoCorrect={false}
              autoCapitalize="none"
              accessibilityLabel={t('search.placeholder')}
            />
            {search.length > 0 && (
              <Pressable
                onPress={handleClear}
                hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                accessibilityLabel={t('common:actions.clear', 'Clear search')}
                accessibilityRole="button"
              >
                <Ionicons name="close-circle" size={18} color={theme.textMuted} />
              </Pressable>
            )}
          </View>

          {/* Filter row */}
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 8, gap: 8, flexDirection: 'row', alignItems: 'center' }}
          >
            {JOB_TYPES.map((type) => (
              <Pressable
                key={type || 'all-type'}
                className="px-3 py-1 rounded-full border"
                style={{
                  backgroundColor: typeFilter === type ? primary : theme.surface,
                  borderColor: typeFilter === type ? primary : theme.border,
                }}
                onPress={() => setTypeFilter(type)}
              >
                <Text
                  className="text-xs font-semibold"
                  style={{ color: typeFilter === type ? '#fff' : theme.textSecondary }} // contrast on primary
                >
                  {t(type ? `filters.type.${type}` : 'filters.type.all')}
                </Text>
              </Pressable>
            ))}
            <View className="w-px h-5 bg-border mx-1" />
            {COMMITMENT_TYPES.map((commitment) => (
              <Pressable
                key={commitment || 'all-commitment'}
                className="px-3 py-1 rounded-full border"
                style={{
                  backgroundColor: commitmentFilter === commitment ? primary : theme.surface,
                  borderColor: commitmentFilter === commitment ? primary : theme.border,
                }}
                onPress={() => setCommitmentFilter(commitment)}
              >
                <Text
                  className="text-xs font-semibold"
                  style={{ color: commitmentFilter === commitment ? '#fff' : theme.textSecondary }} // contrast on primary
                >
                  {t(
                    commitment
                      ? `filters.commitment.${commitment}`
                      : 'filters.commitment.all',
                  )}
                </Text>
              </Pressable>
            ))}
          </ScrollView>

          <FlatList<JobVacancy>
            data={jobs}
            keyExtractor={(item) => String(item.id)}
            renderItem={renderJob}
            onEndReached={jobsHasMore ? loadMoreJobs : undefined}
            onEndReachedThreshold={0.3}
            refreshControl={
              <RefreshControl
                refreshing={jobsLoading && jobs.length > 0}
                onRefresh={refreshJobs}
                tintColor={primary}
              />
            }
            ListEmptyComponent={
              jobsLoading ? (
                <LoadingSpinner />
              ) : jobsError ? (
                <View className="flex-1 justify-center items-center p-10">
                  <Text className="text-sm text-danger text-center">{jobsError}</Text>
                  <Pressable onPress={refreshJobs} className="mt-3">
                    <Text style={{ color: primary }} className="text-sm font-semibold">{t('retry', 'Retry')}</Text>
                  </Pressable>
                </View>
              ) : (
                <EmptyState
                  icon="briefcase-outline"
                  title={t('empty')}
                  subtitle={t('emptyHint')}
                />
              )
            }
            ListFooterComponent={
              jobsLoadingMore ? (
                <View className="py-4">
                  <LoadingSpinner />
                </View>
              ) : null
            }
            contentContainerStyle={{ flexGrow: 1, paddingHorizontal: 16, paddingBottom: 32, paddingTop: 4 }}
          />
        </>
      ) : (
        <FlatList<JobApplication>
          data={applications}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderApplication}
          onEndReached={appsHasMore ? loadMoreApps : undefined}
          onEndReachedThreshold={0.3}
          refreshControl={
            <RefreshControl
              refreshing={appsLoading && applications.length > 0}
              onRefresh={refreshApps}
              tintColor={primary}
            />
          }
          ListEmptyComponent={
            appsLoading ? (
              <LoadingSpinner />
            ) : appsError ? (
              <View className="flex-1 justify-center items-center p-10">
                <Text className="text-sm text-danger text-center">{appsError}</Text>
                <Pressable onPress={refreshApps} className="mt-3">
                  <Text style={{ color: primary }} className="text-sm font-semibold">{t('retry', 'Retry')}</Text>
                </Pressable>
              </View>
            ) : (
              <EmptyState
                icon="document-text-outline"
                title={t('applications.empty')}
                subtitle={t('applications.emptyHint')}
              />
            )
          }
          ListFooterComponent={
            appsLoadingMore ? (
              <View className="py-4">
                <LoadingSpinner />
              </View>
            ) : null
          }
          contentContainerStyle={{ flexGrow: 1, paddingHorizontal: 16, paddingBottom: 32, paddingTop: 4 }}
        />
      )}
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}
