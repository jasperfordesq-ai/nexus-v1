// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import {
  FlatList,
  View,
  Text,
  TextInput,
  TouchableOpacity,
  RefreshControl,
  StyleSheet,
  SafeAreaView,
  ScrollView,
} from 'react-native';
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
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

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
  styles,
  t,
  onPress,
}: {
  item: JobVacancy;
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
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

  const visibleSkills = item.skills_required.slice(0, 3);

  return (
    <TouchableOpacity style={styles.card} onPress={onPress} activeOpacity={0.75}>
      {/* Featured badge */}
      {item.is_featured ? (
        <View style={styles.featuredBadge}>
          <Text style={styles.featuredText}>{t('card.featured')}</Text>
        </View>
      ) : null}

      {/* Title row */}
      <View style={styles.cardTitleRow}>
        <Text style={styles.cardTitle} numberOfLines={2}>
          {item.title}
        </Text>
        <View style={[styles.typeBadge, { backgroundColor: typeColor + '22' }]}>
          <Text style={[styles.typeBadgeText, { color: typeColor }]}>
            {t(`filters.type.${item.type}`)}
          </Text>
        </View>
      </View>

      {/* Organisation / creator */}
      <Text style={styles.cardOrg} numberOfLines={1}>
        {displayName}
      </Text>

      {/* Meta row */}
      <View style={styles.cardMeta}>
        {item.is_remote ? (
          <View style={[styles.remoteBadge, { backgroundColor: withAlpha(primary, 0.10) }]}>
            <Text style={[styles.remoteBadgeText, { color: primary }]}>
              {t('card.remote')}
            </Text>
          </View>
        ) : item.location ? (
          <View style={styles.metaItem}>
            <Ionicons name="location-outline" size={13} color={theme.textMuted} />
            <Text style={styles.metaText} numberOfLines={1}>
              {item.location}
            </Text>
          </View>
        ) : null}

        {salaryStr ? (
          <View style={styles.metaItem}>
            <Ionicons name="cash-outline" size={13} color={theme.textMuted} />
            <Text style={styles.metaText}>{salaryStr}</Text>
          </View>
        ) : null}

        {deadlineStr ? (
          <View style={styles.metaItem}>
            <Ionicons name="calendar-outline" size={13} color={theme.textMuted} />
            <Text style={styles.metaText}>{deadlineStr}</Text>
          </View>
        ) : null}

        <View style={styles.metaItem}>
          <Ionicons name="people-outline" size={13} color={theme.textMuted} />
          <Text style={styles.metaText}>
            {t('card.applications', { count: item.applications_count })}
          </Text>
        </View>
      </View>

      {/* Skills */}
      {visibleSkills.length > 0 ? (
        <View style={styles.skillsRow}>
          {visibleSkills.map((skill) => (
            <View key={skill} style={[styles.skillPill, { backgroundColor: theme.bg }]}>
              <Text style={styles.skillText}>{skill}</Text>
            </View>
          ))}
          {item.skills_required.length > 3 ? (
            <View style={[styles.skillPill, { backgroundColor: theme.bg }]}>
              <Text style={styles.skillText}>+{item.skills_required.length - 3}</Text>
            </View>
          ) : null}
        </View>
      ) : null}
    </TouchableOpacity>
  );
}

// ---------------------------------------------------------------------------
// Application card component
// ---------------------------------------------------------------------------

function ApplicationCard({
  item,
  theme,
  styles,
  t,
  primary,
  onInterviewAccepted,
  onInterviewDeclined,
  onOfferAccepted,
  onOfferRejected,
}: {
  item: JobApplication;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
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
    <View style={styles.appCard}>
      <View style={styles.cardTitleRow}>
        <Text style={styles.cardTitle} numberOfLines={2}>
          {jobTitle}
        </Text>
        <View style={[styles.typeBadge, { backgroundColor: color + '22' }]}>
          <Text style={[styles.typeBadgeText, { color }]}>
            {t(`applications.status.${item.status}`)}
          </Text>
        </View>
      </View>
      {orgName ? (
        <Text style={styles.cardOrg} numberOfLines={1}>
          {orgName}
        </Text>
      ) : null}
      <View style={styles.metaItem}>
        <Ionicons name="calendar-outline" size={13} color={theme.textMuted} />
        <Text style={styles.metaText}>{appliedStr}</Text>
      </View>

      {/* Interview actions */}
      {interview?.status === 'proposed' ? (
        <View style={{ marginTop: 10, paddingTop: 10, borderTopWidth: 1, borderTopColor: theme.border }}>
          <Text style={{ fontSize: 12, fontWeight: '500', color: theme.textSecondary, marginBottom: 6 }}>
            {t('applications.interview_proposed')}
          </Text>
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <TouchableOpacity
              style={{ paddingHorizontal: 12, paddingVertical: 7, borderRadius: 8, backgroundColor: primary }}
              disabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await acceptInterview(interview.id);
                setActionLoading(false);
                if (ok) onInterviewAccepted(interview.id);
              }}
              activeOpacity={0.8}
            >
              <Text style={{ fontSize: 13, fontWeight: '600', color: '#fff' }}>{/* contrast on primary */}
                {t('applications.accept_interview')}
              </Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={{ paddingHorizontal: 12, paddingVertical: 7, borderRadius: 8, backgroundColor: theme.surface, borderWidth: 1, borderColor: theme.border }}
              disabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await declineInterview(interview.id);
                setActionLoading(false);
                if (ok) onInterviewDeclined(interview.id);
              }}
              activeOpacity={0.8}
            >
              <Text style={{ fontSize: 13, fontWeight: '600', color: theme.error }}>
                {t('applications.decline_interview')}
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      ) : interview?.status === 'accepted' ? (
        <View style={{ marginTop: 8 }}>
          <View style={[styles.typeBadge, { backgroundColor: theme.success + '22', alignSelf: 'flex-start' }]}>
            <Text style={[styles.typeBadgeText, { color: theme.success }]}>
              {t('applications.interview_confirmed')}
            </Text>
          </View>
        </View>
      ) : null}

      {/* Offer actions */}
      {offer?.status === 'pending' ? (
        <View style={{ marginTop: 10, paddingTop: 10, borderTopWidth: 1, borderTopColor: theme.border }}>
          <Text style={{ fontSize: 12, fontWeight: '500', color: theme.textSecondary, marginBottom: 6 }}>
            {t('applications.offer_received')}
          </Text>
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <TouchableOpacity
              style={{ paddingHorizontal: 12, paddingVertical: 7, borderRadius: 8, backgroundColor: theme.success }}
              disabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await acceptOffer(offer.id);
                setActionLoading(false);
                if (ok) onOfferAccepted(offer.id);
              }}
              activeOpacity={0.8}
            >
              <Text style={{ fontSize: 13, fontWeight: '600', color: '#fff' }}>{/* contrast on primary */}
                {t('applications.accept_offer')}
              </Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={{ paddingHorizontal: 12, paddingVertical: 7, borderRadius: 8, backgroundColor: theme.surface, borderWidth: 1, borderColor: theme.border }}
              disabled={actionLoading}
              onPress={async () => {
                setActionLoading(true);
                const ok = await rejectOffer(offer.id);
                setActionLoading(false);
                if (ok) onOfferRejected(offer.id);
              }}
              activeOpacity={0.8}
            >
              <Text style={{ fontSize: 13, fontWeight: '600', color: theme.error }}>
                {t('applications.decline_offer')}
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      ) : offer?.status === 'accepted' ? (
        <View style={{ marginTop: 8 }}>
          <View style={[styles.typeBadge, { backgroundColor: theme.success + '22', alignSelf: 'flex-start' }]}>
            <Text style={[styles.typeBadgeText, { color: theme.success }]}>
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
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
        styles={styles}
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
    [primary, theme, styles, t],
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
        styles={styles}
        t={t}
        primary={primary}
        onInterviewAccepted={handleInterviewAccepted}
        onInterviewDeclined={handleInterviewDeclined}
        onOfferAccepted={handleOfferAccepted}
        onOfferRejected={handleOfferRejected}
      />
    ),
    [theme, styles, t, primary, handleInterviewAccepted, handleInterviewDeclined, handleOfferAccepted, handleOfferRejected],
  );

  return (
    <SafeAreaView style={styles.container}>
      {/* Tab bar */}
      <View style={styles.tabBar}>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'browse' && { borderBottomColor: primary, borderBottomWidth: 2 }]}
          onPress={() => setActiveTab('browse')}
        >
          <Text style={[styles.tabText, activeTab === 'browse' && { color: primary }]}>
            {t('tabs.browse')}
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[
            styles.tab,
            activeTab === 'myApplications' && { borderBottomColor: primary, borderBottomWidth: 2 },
          ]}
          onPress={() => setActiveTab('myApplications')}
        >
          <Text style={[styles.tabText, activeTab === 'myApplications' && { color: primary }]}>
            {t('tabs.myApplications')}
          </Text>
        </TouchableOpacity>
      </View>

      {activeTab === 'browse' ? (
        <>
          {/* Search bar */}
          <View style={styles.searchBar}>
            <Ionicons
              name="search-outline"
              size={18}
              color={theme.textMuted}
              style={styles.searchIcon}
            />
            <TextInput
              style={styles.searchInput}
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
              <TouchableOpacity
                onPress={handleClear}
                hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
              >
                <Ionicons name="close-circle" size={18} color={theme.textMuted} />
              </TouchableOpacity>
            )}
          </View>

          {/* Filter row */}
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            style={styles.filtersScroll}
            contentContainerStyle={styles.filtersContent}
          >
            {JOB_TYPES.map((type) => (
              <TouchableOpacity
                key={type || 'all-type'}
                style={[
                  styles.filterChip,
                  typeFilter === type && { backgroundColor: primary, borderColor: primary },
                ]}
                onPress={() => setTypeFilter(type)}
              >
                <Text
                  style={[
                    styles.filterChipText,
                    typeFilter === type && { color: '#fff' }, // contrast on primary
                  ]}
                >
                  {t(type ? `filters.type.${type}` : 'filters.type.all')}
                </Text>
              </TouchableOpacity>
            ))}
            <View style={styles.filterDivider} />
            {COMMITMENT_TYPES.map((commitment) => (
              <TouchableOpacity
                key={commitment || 'all-commitment'}
                style={[
                  styles.filterChip,
                  commitmentFilter === commitment && {
                    backgroundColor: primary,
                    borderColor: primary,
                  },
                ]}
                onPress={() => setCommitmentFilter(commitment)}
              >
                <Text
                  style={[
                    styles.filterChipText,
                    commitmentFilter === commitment && { color: '#fff' }, // contrast on primary
                  ]}
                >
                  {t(
                    commitment
                      ? `filters.commitment.${commitment}`
                      : 'filters.commitment.all',
                  )}
                </Text>
              </TouchableOpacity>
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
                <View style={styles.centered}>
                  <Text style={styles.errorText}>{jobsError}</Text>
                  <TouchableOpacity onPress={refreshJobs} style={styles.retryButton}>
                    <Text style={[styles.retryText, { color: primary }]}>{t('retry')}</Text>
                  </TouchableOpacity>
                </View>
              ) : (
                <View style={styles.centered}>
                  <Text style={styles.emptyText}>{t('empty')}</Text>
                  <Text style={styles.emptyHint}>{t('emptyHint')}</Text>
                </View>
              )
            }
            ListFooterComponent={
              jobsLoadingMore ? (
                <View style={styles.footerLoader}>
                  <LoadingSpinner />
                </View>
              ) : null
            }
            contentContainerStyle={styles.list}
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
              <View style={styles.centered}>
                <Text style={styles.errorText}>{appsError}</Text>
                <TouchableOpacity onPress={refreshApps} style={styles.retryButton}>
                  <Text style={[styles.retryText, { color: primary }]}>{t('retry')}</Text>
                </TouchableOpacity>
              </View>
            ) : (
              <View style={styles.centered}>
                <Text style={styles.emptyText}>{t('applications.empty')}</Text>
                <Text style={styles.emptyHint}>{t('applications.emptyHint')}</Text>
              </View>
            )
          }
          ListFooterComponent={
            appsLoadingMore ? (
              <View style={styles.footerLoader}>
                <LoadingSpinner />
              </View>
            ) : null
          }
          contentContainerStyle={styles.list}
        />
      )}
    </SafeAreaView>
  );
}

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    tabBar: {
      flexDirection: 'row',
      borderBottomWidth: 1,
      borderBottomColor: theme.borderSubtle,
      backgroundColor: theme.surface,
    },
    tab: {
      flex: 1,
      paddingVertical: 12,
      alignItems: 'center',
      borderBottomWidth: 2,
      borderBottomColor: 'transparent',
    },
    tabText: {
      fontSize: 14,
      fontWeight: '600',
      color: theme.textSecondary,
    },
    searchBar: {
      flexDirection: 'row',
      alignItems: 'center',
      marginHorizontal: 16,
      marginVertical: 12,
      paddingHorizontal: 12,
      height: 42,
      backgroundColor: theme.surface,
      borderRadius: 10,
      gap: 8,
    },
    searchIcon: { flexShrink: 0 },
    searchInput: {
      flex: 1,
      fontSize: 15,
      color: theme.text,
      paddingVertical: 0,
    },
    filtersScroll: { flexShrink: 0 },
    filtersContent: {
      paddingHorizontal: 16,
      paddingBottom: 10,
      gap: 8,
      flexDirection: 'row',
      alignItems: 'center',
    },
    filterChip: {
      paddingHorizontal: 12,
      paddingVertical: 6,
      borderRadius: 20,
      borderWidth: 1,
      borderColor: theme.border,
      backgroundColor: theme.surface,
    },
    filterChipText: {
      fontSize: 12,
      fontWeight: '600',
      color: theme.textSecondary,
    },
    filterDivider: {
      width: 1,
      height: 20,
      backgroundColor: theme.border,
      marginHorizontal: 4,
    },
    list: { flexGrow: 1, paddingHorizontal: 16, paddingBottom: 32, paddingTop: 4 },
    card: {
      backgroundColor: theme.surface,
      borderRadius: 14,
      padding: 14,
      marginBottom: 12,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      gap: 8,
    },
    appCard: {
      backgroundColor: theme.surface,
      borderRadius: 14,
      padding: 14,
      marginBottom: 12,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      gap: 8,
    },
    featuredBadge: {
      alignSelf: 'flex-start',
      backgroundColor: theme.warning + '33',
      borderRadius: 6,
      paddingHorizontal: 8,
      paddingVertical: 3,
    },
    featuredText: {
      fontSize: 11,
      fontWeight: '700',
      color: theme.warning,
      textTransform: 'uppercase',
      letterSpacing: 0.4,
    },
    cardTitleRow: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      gap: 8,
    },
    cardTitle: {
      flex: 1,
      fontSize: 15,
      fontWeight: '600',
      color: theme.text,
    },
    typeBadge: {
      borderRadius: 6,
      paddingHorizontal: 8,
      paddingVertical: 3,
      alignSelf: 'flex-start',
    },
    typeBadgeText: {
      fontSize: 11,
      fontWeight: '600',
    },
    cardOrg: {
      fontSize: 13,
      color: theme.textSecondary,
    },
    cardMeta: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: 8,
      alignItems: 'center',
    },
    remoteBadge: {
      borderRadius: 6,
      paddingHorizontal: 8,
      paddingVertical: 3,
    },
    remoteBadgeText: {
      fontSize: 11,
      fontWeight: '600',
    },
    metaItem: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 4,
    },
    metaText: {
      fontSize: 12,
      color: theme.textMuted,
    },
    skillsRow: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: 6,
    },
    skillPill: {
      borderRadius: 6,
      paddingHorizontal: 8,
      paddingVertical: 3,
      borderWidth: 1,
      borderColor: theme.border,
    },
    skillText: {
      fontSize: 11,
      color: theme.textSecondary,
    },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center' },
    emptyText: { color: theme.textSecondary, fontSize: 15, textAlign: 'center' },
    emptyHint: { color: theme.textMuted, fontSize: 13, textAlign: 'center', marginTop: 6 },
    retryButton: { marginTop: 12 },
    retryText: { fontSize: 15, fontWeight: '600' },
    footerLoader: { paddingVertical: 16 },
  });
}
