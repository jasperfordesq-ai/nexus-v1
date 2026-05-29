// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  FlatList,
  View,
  Text,
  Pressable,
  RefreshControl,
  ScrollView,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import {
  getJobs,
  getMyApplications,
  getMyPostings,
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
import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import Input from '@/components/ui/Input';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

// ---------------------------------------------------------------------------
// Type filter options
// ---------------------------------------------------------------------------

const JOB_TYPES = ['', 'paid', 'volunteer', 'timebank'] as const;
const COMMITMENT_TYPES = ['', 'full_time', 'part_time', 'flexible', 'one_off'] as const;
type JobsTab = 'browse' | 'myApplications' | 'myPostings';

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
  const successColor = theme.success ?? '#22c55e';
  const warningColor = theme.warning ?? '#f59e0b';
  const typeColor =
    item.type === 'paid'
      ? successColor
      : item.type === 'volunteer'
        ? primary
        : warningColor;

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
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={item.title}
    >
      <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
        <View className="h-1.5" style={{ backgroundColor: item.is_featured ? warningColor : typeColor }} />
        <HeroCard.Body className="gap-3 p-4">
          <View className="flex-row items-start gap-3">
            <View className="size-12 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(typeColor, 0.14) }}>
              <Ionicons name="briefcase-outline" size={23} color={typeColor} />
            </View>
            <View className="min-w-0 flex-1 gap-1">
              <View className="flex-row flex-wrap gap-2">
                {item.is_featured ? (
                  <Chip size="sm" variant="secondary" color="warning">
                    <Ionicons name="star-outline" size={12} color={warningColor} />
                    <Chip.Label>{t('card.featured')}</Chip.Label>
                  </Chip>
                ) : null}
                <Chip size="sm" variant="secondary">
                  <Chip.Label>{t(`filters.type.${item.type}`)}</Chip.Label>
                </Chip>
              </View>
              <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={2}>
                {item.title}
              </Text>
              <Text className="text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                {displayName}
              </Text>
            </View>
            <Ionicons name="chevron-forward-outline" size={18} color={primary} />
          </View>

          <View className="flex-row flex-wrap gap-2">
            {item.is_remote ? (
              <Chip size="sm" variant="secondary">
                <Ionicons name="wifi-outline" size={12} color={primary} />
                <Chip.Label>{t('card.remote')}</Chip.Label>
              </Chip>
            ) : item.location ? (
              <Chip size="sm" variant="secondary">
                <Ionicons name="location-outline" size={12} color={primary} />
                <Chip.Label>{item.location}</Chip.Label>
              </Chip>
            ) : null}

            {salaryStr ? (
              <Chip size="sm" variant="secondary">
                <Ionicons name="cash-outline" size={12} color={successColor} />
                <Chip.Label>{salaryStr}</Chip.Label>
              </Chip>
            ) : null}

            {deadlineStr ? (
              <Chip size="sm" variant="secondary">
                <Ionicons name="calendar-outline" size={12} color={theme.textSecondary} />
                <Chip.Label>{deadlineStr}</Chip.Label>
              </Chip>
            ) : null}

            <Chip size="sm" variant="secondary">
              <Ionicons name="people-outline" size={12} color={theme.textSecondary} />
              <Chip.Label>{t('card.applications', { count: item.applications_count })}</Chip.Label>
            </Chip>
          </View>

          {visibleSkills.length > 0 ? (
            <View className="flex-row flex-wrap gap-1.5">
              {visibleSkills.map((skill) => (
                <Chip key={skill} size="sm" variant="secondary">
                  <Chip.Label>{skill}</Chip.Label>
                </Chip>
              ))}
              {(item.skills_required ?? []).length > 3 ? (
                <Chip size="sm" variant="secondary">
                  <Chip.Label>+{item.skills_required.length - 3}</Chip.Label>
                </Chip>
              ) : null}
            </View>
          ) : null}
        </HeroCard.Body>
      </HeroCard>
    </Pressable>
  );
}

function JobsHero({
  primary,
  theme,
  t,
}: {
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string) => string;
}) {
  return (
    <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
      <View className="h-1.5" style={{ backgroundColor: primary }} />
      <HeroCard.Body className="gap-4 p-4 pt-0">
        <View className="flex-row items-start gap-3">
          <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
            <Ionicons name="briefcase-outline" size={25} color={primary} />
          </View>
          <View className="min-w-0 flex-1 gap-1">
            <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('eyebrow')}</Text>
            <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('title')}</Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('subtitle')}</Text>
          </View>
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function FilterPill({
  label,
  selected,
  onPress,
  primary,
  theme,
}: {
  label: string;
  selected: boolean;
  onPress: () => void;
  primary: string;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <HeroButton
      size="sm"
      variant={selected ? 'primary' : 'secondary'}
      onPress={onPress}
      style={selected ? { backgroundColor: primary } : undefined}
    >
      <HeroButton.Label>{label}</HeroButton.Label>
      {selected ? <Ionicons name="checkmark-outline" size={13} color="#fff" /> : <Ionicons name="add-outline" size={13} color={theme.textSecondary} />}
    </HeroButton>
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
            <HeroButton
              size="sm"
              variant="primary"
              className="rounded-lg"
              style={{ backgroundColor: primary }}
              isDisabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await acceptInterview(interview.id);
                setActionLoading(false);
                if (ok) onInterviewAccepted(interview.id);
              }}
              accessibilityLabel={t('applications.accept_interview')}
            >
              <HeroButton.Label>{t('applications.accept_interview')}</HeroButton.Label>
            </HeroButton>
            <HeroButton
              size="sm"
              variant="danger"
              className="rounded-lg"
              isDisabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await declineInterview(interview.id);
                setActionLoading(false);
                if (ok) onInterviewDeclined(interview.id);
              }}
              accessibilityLabel={t('applications.decline_interview')}
            >
              <HeroButton.Label>{t('applications.decline_interview')}</HeroButton.Label>
            </HeroButton>
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
            <HeroButton
              size="sm"
              variant="primary"
              className="rounded-lg"
              style={{ backgroundColor: theme.success }}
              isDisabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await acceptOffer(offer.id);
                setActionLoading(false);
                if (ok) onOfferAccepted(offer.id);
              }}
              accessibilityLabel={t('applications.accept_offer')}
            >
              <HeroButton.Label>{t('applications.accept_offer')}</HeroButton.Label>
            </HeroButton>
            <HeroButton
              size="sm"
              variant="danger"
              className="rounded-lg"
              isDisabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await rejectOffer(offer.id);
                setActionLoading(false);
                if (ok) onOfferRejected(offer.id);
              }}
              accessibilityLabel={t('applications.decline_offer')}
            >
              <HeroButton.Label>{t('applications.decline_offer')}</HeroButton.Label>
            </HeroButton>
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
  const { t } = useTranslation(['jobs', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();

  const [activeTab, setActiveTab] = useState<JobsTab>('browse');
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

  // My Postings tab — owner-facing parity with the React web jobs page.
  const postingsFetchFn = useCallback(
    (cursor: string | null) => getMyPostings({ cursor }),
    [],
  );

  const {
    items: postings,
    isLoading: postingsLoading,
    isLoadingMore: postingsLoadingMore,
    error: postingsError,
    hasMore: postingsHasMore,
    loadMore: loadMorePostings,
    refresh: refreshPostings,
  } = usePaginatedApi<JobVacancy, JobsResponse>(postingsFetchFn, jobExtractor, []);

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
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar
        title={t('title')}
        backLabel={t('common:back')}
        fallbackHref="/(tabs)/profile"
        rightAction={{
          accessibilityLabel: t('createJob'),
          icon: 'add-outline',
          onPress: () => router.push('/(modals)/new-job' as Href),
        }}
      />

      <View className="px-4">
        <JobsHero primary={primary} theme={theme} t={t} />
      </View>

      <Surface variant="secondary" className="mx-4 mb-3 rounded-panel-inner p-1">
        {/* Tab bar */}
        <View className="min-w-0 flex-row gap-1">
          {(['browse', 'myApplications', 'myPostings'] as const).map((tab) => {
            const selected = activeTab === tab;
            return (
              <HeroButton
                key={tab}
                size="sm"
                variant={selected ? 'primary' : 'ghost'}
                className="min-w-0 flex-1 rounded-panel-inner"
                style={{ backgroundColor: selected ? primary : 'transparent' }}
                onPress={() => setActiveTab(tab)}
                accessibilityRole="tab"
                accessibilityState={{ selected }}
                accessibilityLabel={t(`tabs.${tab}`)}
              >
                <HeroButton.Label
                  style={{ color: selected ? '#fff' : theme.textSecondary }}
                  numberOfLines={1}
                >
                  {t(`tabs.${tab}`)}
                </HeroButton.Label>
              </HeroButton>
            );
          })}
        </View>
      </Surface>

      {activeTab === 'browse' ? (
        <>
          {/* Search bar */}
          <Surface variant="secondary" className="mx-4 mb-3 rounded-panel-inner px-3 pt-3">
            <Input
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
              leftIcon={<Ionicons name="search-outline" size={18} color={theme.textMuted} />}
              rightIcon={search.length > 0 ? (
                <HeroButton
                  isIconOnly
                  size="sm"
                  variant="ghost"
                  accessibilityLabel={t('common:actions.clear', 'Clear search')}
                  onPress={handleClear}
                >
                  <Ionicons name="close-circle" size={18} color={theme.textMuted} />
                </HeroButton>
              ) : null}
            />
          </Surface>

          {/* Filter row */}
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 8, gap: 8, flexDirection: 'row', alignItems: 'center' }}
          >
            {JOB_TYPES.map((type) => (
              <FilterPill
                key={type || 'all-type'}
                label={t(type ? `filters.type.${type}` : 'filters.type.all')}
                selected={typeFilter === type}
                onPress={() => setTypeFilter(type)}
                primary={primary}
                theme={theme}
              />
            ))}
            <View className="w-px h-5 bg-border mx-1" />
            {COMMITMENT_TYPES.map((commitment) => (
              <FilterPill
                key={commitment || 'all-commitment'}
                label={t(
                    commitment
                      ? `filters.commitment.${commitment}`
                      : 'filters.commitment.all',
                )}
                selected={commitmentFilter === commitment}
                onPress={() => setCommitmentFilter(commitment)}
                primary={primary}
                theme={theme}
              />
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
                  <HeroButton variant="secondary" size="sm" onPress={refreshJobs} className="mt-3">
                    <HeroButton.Label>{t('retry')}</HeroButton.Label>
                  </HeroButton>
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
      ) : activeTab === 'myApplications' ? (
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
                <HeroButton variant="secondary" size="sm" onPress={refreshApps} className="mt-3">
                  <HeroButton.Label>{t('retry')}</HeroButton.Label>
                </HeroButton>
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
      ) : (
        <FlatList<JobVacancy>
          data={postings}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderJob}
          onEndReached={postingsHasMore ? loadMorePostings : undefined}
          onEndReachedThreshold={0.3}
          refreshControl={
            <RefreshControl
              refreshing={postingsLoading && postings.length > 0}
              onRefresh={refreshPostings}
              tintColor={primary}
            />
          }
          ListEmptyComponent={
            postingsLoading ? (
              <LoadingSpinner />
            ) : postingsError ? (
              <View className="flex-1 justify-center items-center p-10">
                <Text className="text-sm text-danger text-center">{postingsError}</Text>
                <HeroButton variant="secondary" size="sm" onPress={refreshPostings} className="mt-3">
                  <HeroButton.Label>{t('retry')}</HeroButton.Label>
                </HeroButton>
              </View>
            ) : (
              <EmptyState
                icon="briefcase-outline"
                title={t('postings.empty')}
                subtitle={t('postings.emptyHint')}
              />
            )
          }
          ListFooterComponent={
            postingsLoadingMore ? (
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
