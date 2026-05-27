// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import {
  expressInterest,
  getHoursSummary,
  getMyApplications,
  getMyOrganisations,
  getOpportunities,
  logVolunteerHours,
  withdrawApplication,
  type MyOrganisationsResponse,
  type VolunteerApplication,
  type VolunteerApplicationsResponse,
  type VolunteerHoursSummary,
  type VolunteerOpportunity,
  type VolunteeringOrganisation,
  type VolunteeringResponse,
} from '@/lib/api/volunteering';
import { useAuth } from '@/lib/hooks/useAuth';
import { useApi } from '@/lib/hooks/useApi';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type TabKey = 'opportunities' | 'applications' | 'hours';
type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

function formatDate(value?: string | null) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}

function opportunityOrg(item: VolunteerOpportunity) {
  const mixed = item as VolunteerOpportunity & {
    organization?: VolunteeringOrganisation | null;
  };
  return item.organisation ?? mixed.organization ?? null;
}

function normalizeSkills(skills: unknown): string[] {
  if (Array.isArray(skills)) return skills;
  if (typeof skills === 'string') return skills.split(',').map((skill: string) => skill.trim()).filter(Boolean);
  return [];
}

function statusLabelKey(status: VolunteerOpportunity['status'] | string) {
  return ['open', 'closed', 'filled'].includes(status) ? `status.${status}` : 'status.open';
}

function StatusChip({ label, tone, icon }: { label: string; tone: string; icon?: IoniconName }) {
  return (
    <Chip size="sm" variant="secondary" color="default">
      {icon ? <Ionicons name={icon} size={12} color={tone} /> : null}
      <Chip.Label>{label}</Chip.Label>
    </Chip>
  );
}

function StatTile({
  label,
  value,
  tone,
}: {
  label: string;
  value: string;
  tone: string;
}) {
  const theme = useTheme();
  return (
    <Surface variant="secondary" className="min-w-[46%] flex-1 gap-1 rounded-panel-inner p-4">
      <Text className="text-[11px] font-semibold uppercase" style={{ color: theme.textSecondary }} numberOfLines={1}>
        {label}
      </Text>
      <View className="flex-row items-end justify-between gap-2">
        <Text className="text-2xl font-bold" style={{ color: theme.text }} numberOfLines={1}>
          {value}
        </Text>
        <View className="h-1.5 w-10 rounded-full" style={{ backgroundColor: tone }} />
      </View>
    </Surface>
  );
}

function HeroHeader({
  activeCount,
  applicationsCount,
  verifiedHours,
}: {
  activeCount: number;
  applicationsCount: number;
  verifiedHours: number;
}) {
  const { t } = useTranslation('volunteering');
  const primary = usePrimaryColor();
  const theme = useTheme();

  return (
    <HeroCard className="overflow-hidden rounded-panel p-0">
      <View className="h-1.5" style={{ backgroundColor: '#e11d48' }} />
      <HeroCard.Body className="gap-5 p-5">
        <View className="flex-row items-start gap-3">
          <View className="size-12 items-center justify-center rounded-panel-inner" style={{ backgroundColor: withAlpha('#e11d48', 0.14) }}>
            <Ionicons name="heart-outline" size={24} color="#e11d48" />
          </View>
          <View className="min-w-0 flex-1">
            <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
              {t('heroEyebrow')}
            </Text>
            <Text className="mt-1 text-2xl font-bold" style={{ color: theme.text }} numberOfLines={2}>
              {t('title')}
            </Text>
            <Text className="mt-2 text-sm leading-5" style={{ color: theme.textSecondary }}>
              {t('subtitle')}
            </Text>
          </View>
        </View>

        <View className="flex-row flex-wrap gap-3">
          <StatTile label={t('stats.opportunities')} value={String(activeCount)} tone="#e11d48" />
          <StatTile label={t('stats.applications')} value={String(applicationsCount)} tone={primary} />
          <StatTile label={t('stats.hours')} value={String(verifiedHours)} tone="#22c55e" />
        </View>

        <View className="flex-row gap-2">
          <HeroButton
            className="flex-1"
            variant="primary"
            onPress={() => router.push('/(modals)/new-volunteering' as Href)}
            style={{ backgroundColor: primary }}
          >
            <Ionicons name="add-outline" size={16} color="#fff" />
            <HeroButton.Label>{t('createOpportunity')}</HeroButton.Label>
          </HeroButton>
          <HeroButton
            className="flex-1"
            variant="secondary"
            onPress={() => router.push('/(modals)/organisations')}
          >
            <Ionicons name="business-outline" size={16} color={primary} />
            <HeroButton.Label>{t('browseOrganisations')}</HeroButton.Label>
          </HeroButton>
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function OpportunityCard({
  item,
  onOpen,
  onApply,
  applying,
}: {
  item: VolunteerOpportunity;
  onOpen: () => void;
  onApply: () => void;
  applying: boolean;
}) {
  const { t } = useTranslation('volunteering');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const org = opportunityOrg(item);
  const skills = normalizeSkills(item.skills_needed);
  const statusColor = item.status === 'closed' ? theme.textMuted : item.status === 'filled' ? theme.warning : theme.success;
  const deadline = formatDate(item.deadline);

  return (
    <Pressable onPress={onOpen} accessibilityRole="button" accessibilityLabel={item.title}>
      <HeroCard className="mb-3 rounded-panel p-0">
        <HeroCard.Body className="gap-4 p-4" style={{ minHeight: 178 }}>
          <View className="flex-row items-start justify-between gap-3">
            <View className="min-w-0 flex-1">
              <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>
                {item.title}
              </Text>
              {org ? (
                <View className="mt-2 flex-row items-center gap-2">
                  <Avatar uri={org.avatar ?? org.logo_url ?? undefined} name={org.name} size={26} />
                  <Text className="min-w-0 flex-1 text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                    {org.name}
                  </Text>
                </View>
              ) : null}
            </View>
            <StatusChip label={t(statusLabelKey(item.status))} tone={statusColor} icon="radio-button-on-outline" />
          </View>

          <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={3}>
            {item.description ?? t('noDescription')}
          </Text>

          <View className="flex-row flex-wrap gap-2">
            {item.is_remote ? (
              <StatusChip label={t('remote')} tone={primary} icon="globe-outline" />
            ) : item.location ? (
              <StatusChip label={item.location} tone={theme.textMuted} icon="location-outline" />
            ) : null}
            {typeof item.hours_per_week === 'number' ? (
              <StatusChip label={t('hoursPerWeek', { hours: item.hours_per_week })} tone={theme.textMuted} icon="time-outline" />
            ) : null}
            {deadline ? (
              <StatusChip label={t('deadlineShort', { date: deadline })} tone={theme.textMuted} icon="calendar-outline" />
            ) : null}
          </View>

          {skills.length > 0 ? (
            <View className="flex-row flex-wrap gap-2">
              {skills.slice(0, 3).map((skill) => (
                <Chip key={skill} size="sm" variant="secondary" color="default">
                  <Chip.Label>{skill}</Chip.Label>
                </Chip>
              ))}
            </View>
          ) : null}

          <View className="flex-row gap-2">
            <HeroButton className="flex-1" size="sm" variant="secondary" onPress={onOpen}>
              <HeroButton.Label>{t('viewOpportunity')}</HeroButton.Label>
            </HeroButton>
            {item.status !== 'closed' && item.status !== 'filled' && !item.has_applied ? (
              <HeroButton className="flex-1" size="sm" isDisabled={applying} onPress={onApply}>
                {applying ? <Spinner size="sm" /> : <HeroButton.Label>{t('apply')}</HeroButton.Label>}
              </HeroButton>
            ) : null}
          </View>
        </HeroCard.Body>
      </HeroCard>
    </Pressable>
  );
}

function ApplicationsPanel({
  applications,
  isLoading,
  onRefresh,
}: {
  applications: VolunteerApplication[];
  isLoading: boolean;
  onRefresh: () => void;
}) {
  const { t } = useTranslation('volunteering');
  const theme = useTheme();
  const [withdrawingId, setWithdrawingId] = useState<number | null>(null);

  async function handleWithdraw(id: number) {
    setWithdrawingId(id);
    try {
      await withdrawApplication(id);
      onRefresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('withdrawError'));
    } finally {
      setWithdrawingId(null);
    }
  }

  if (isLoading) {
    return <LoadingSpinner />;
  }

  if (applications.length === 0) {
    return <EmptyState icon="send-outline" title={t('noApplications')} />;
  }

  return (
    <View className="gap-3">
      {applications.map((application) => {
        const statusTone = application.status === 'approved' ? theme.success : application.status === 'declined' ? theme.error : theme.warning;
        return (
          <HeroCard key={application.id} className="rounded-panel p-0">
            <HeroCard.Body className="gap-3 p-4" style={{ minHeight: 134 }}>
              <View className="flex-row items-start justify-between gap-3">
                <View className="min-w-0 flex-1">
                  <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>
                    {application.opportunity.title}
                  </Text>
                  <Text className="mt-1 text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                    {application.organization.name}
                  </Text>
                </View>
                <StatusChip label={t(`applicationStatus.${application.status}`)} tone={statusTone} icon="ellipse-outline" />
              </View>
              <Text className="text-xs" style={{ color: theme.textMuted }} numberOfLines={1}>
                {t('appliedOn', { date: formatDate(application.created_at) ?? '' })}
              </Text>
              {application.org_note ? (
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={3}>
                  {application.org_note}
                </Text>
              ) : null}
              {application.status === 'pending' ? (
                <HeroButton
                  size="sm"
                  variant="secondary"
                  isDisabled={withdrawingId === application.id}
                  onPress={() => void handleWithdraw(application.id)}
                >
                  {withdrawingId === application.id ? <Spinner size="sm" /> : <HeroButton.Label>{t('withdraw')}</HeroButton.Label>}
                </HeroButton>
              ) : null}
            </HeroCard.Body>
          </HeroCard>
        );
      })}
    </View>
  );
}

function HoursPanel({
  summary,
  organisations,
  isLoading,
  onRefresh,
}: {
  summary: VolunteerHoursSummary | null;
  organisations: VolunteeringOrganisation[];
  isLoading: boolean;
  onRefresh: () => void;
}) {
  const { t } = useTranslation('volunteering');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [selectedOrgId, setSelectedOrgId] = useState<number | null>(null);
  const [hours, setHours] = useState('');
  const [description, setDescription] = useState('');
  const [logging, setLogging] = useState(false);

  useEffect(() => {
    if (selectedOrgId === null && organisations.length > 0) {
      setSelectedOrgId(organisations[0]?.id ?? null);
    }
  }, [organisations, selectedOrgId]);

  async function handleLogHours() {
    const parsedHours = Number(hours);
    if (!selectedOrgId || !Number.isFinite(parsedHours) || parsedHours <= 0) {
      Alert.alert(t('common:errors.alertTitle'), t('hoursRequired'));
      return;
    }

    setLogging(true);
    try {
      await logVolunteerHours({
        organization_id: selectedOrgId,
        date: new Date().toISOString().split('T')[0],
        hours: parsedHours,
        description: description.trim() || undefined,
      });
      setHours('');
      setDescription('');
      onRefresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('hoursLogError'));
    } finally {
      setLogging(false);
    }
  }

  if (isLoading) {
    return <LoadingSpinner />;
  }

  const verified = summary?.total_verified ?? 0;
  const pending = summary?.total_pending ?? 0;
  const declined = summary?.total_declined ?? 0;

  return (
    <View className="gap-4">
      <View className="flex-row flex-wrap gap-3">
        <StatTile label={t('hoursStats.verified')} value={String(verified)} tone="#22c55e" />
        <StatTile label={t('hoursStats.pending')} value={String(pending)} tone="#f59e0b" />
        <StatTile label={t('hoursStats.declined')} value={String(declined)} tone="#ef4444" />
      </View>

      <HeroCard className="rounded-panel p-0">
        <HeroCard.Body className="gap-4 p-4">
          <View>
            <Text className="text-base font-semibold" style={{ color: theme.text }}>
              {t('logHours')}
            </Text>
            <Text className="mt-1 text-sm" style={{ color: theme.textSecondary }}>
              {organisations.length > 0 ? t('logHoursHint') : t('noLoggableOrganisations')}
            </Text>
          </View>

          {organisations.length > 0 ? (
            <>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
                {organisations.map((org) => {
                  const selected = selectedOrgId === org.id;
                  return (
                    <Pressable
                      key={org.id}
                      onPress={() => setSelectedOrgId(org.id)}
                      className="rounded-full px-4 py-2"
                      style={{ backgroundColor: selected ? withAlpha(primary, 0.18) : theme.surface }}
                    >
                      <Text className="text-sm font-semibold" style={{ color: selected ? primary : theme.textSecondary }}>
                        {org.name}
                      </Text>
                    </Pressable>
                  );
                })}
              </ScrollView>
              <TextInput
                value={hours}
                onChangeText={setHours}
                placeholder={t('hoursPlaceholder')}
                placeholderTextColor={theme.textMuted}
                keyboardType="decimal-pad"
                className="rounded-panel-inner px-4 py-3 text-base"
                style={{ backgroundColor: theme.surface, color: theme.text, borderColor: theme.border, borderWidth: 1 }}
              />
              <TextInput
                value={description}
                onChangeText={setDescription}
                placeholder={t('hoursDescriptionPlaceholder')}
                placeholderTextColor={theme.textMuted}
                multiline
                className="min-h-[92px] rounded-panel-inner px-4 py-3 text-base"
                style={{ backgroundColor: theme.surface, color: theme.text, borderColor: theme.border, borderWidth: 1, textAlignVertical: 'top' }}
              />
              <HeroButton isDisabled={logging} onPress={() => void handleLogHours()}>
                {logging ? <Spinner size="sm" /> : <HeroButton.Label>{t('submitHours')}</HeroButton.Label>}
              </HeroButton>
            </>
          ) : null}
        </HeroCard.Body>
      </HeroCard>

      {summary?.by_organization?.length ? (
        <HeroCard className="rounded-panel p-0">
          <HeroCard.Body className="gap-3 p-4">
            <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
              {t('byOrganisation')}
            </Text>
            {summary.by_organization.slice(0, 5).map((item) => (
              <View key={item.name} className="flex-row items-center justify-between gap-3">
                <Text className="min-w-0 flex-1 text-sm" style={{ color: theme.text }} numberOfLines={1}>
                  {item.name}
                </Text>
                <Text className="text-sm font-semibold" style={{ color: theme.text }}>
                  {t('hoursValue', { count: item.hours })}
                </Text>
              </View>
            ))}
          </HeroCard.Body>
        </HeroCard>
      ) : null}
    </View>
  );
}

export default function VolunteeringScreen() {
  return (
    <ModalErrorBoundary>
      <VolunteeringScreenInner />
    </ModalErrorBoundary>
  );
}

function VolunteeringScreenInner() {
  const { t } = useTranslation(['volunteering', 'common']);
  const { isAuthenticated } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [activeTab, setActiveTab] = useState<TabKey>('opportunities');
  const [search, setSearch] = useState('');
  const [committedSearch, setCommittedSearch] = useState('');
  const [applyingId, setApplyingId] = useState<number | null>(null);
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

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const fetchFn = useCallback(
    (cursor: string | null) => getOpportunities(cursor, committedSearch || undefined),
    [committedSearch],
  );

  const extractor = useCallback(
    (response: VolunteeringResponse) => ({
      items: response.data,
      cursor: response.meta.cursor,
      hasMore: response.meta.has_more,
    }),
    [],
  );

  const opportunitiesApi = usePaginatedApi<VolunteerOpportunity, VolunteeringResponse>(fetchFn, extractor, [committedSearch]);
  const applicationsApi = useApi<VolunteerApplicationsResponse>(() => getMyApplications(), [], { enabled: isAuthenticated });
  const hoursApi = useApi<{ data: VolunteerHoursSummary }>(() => getHoursSummary(), [], { enabled: isAuthenticated });
  const organisationsApi = useApi<MyOrganisationsResponse>(() => getMyOrganisations(), [], { enabled: isAuthenticated });

  const opportunities = opportunitiesApi.items;
  const applications = applicationsApi.data?.data ?? [];
  const summary = hoursApi.data?.data ?? null;
  const organisations = organisationsApi.data?.data ?? [];
  const verifiedHours = summary?.total_verified ?? 0;

  async function handleApply(item: VolunteerOpportunity) {
    if (!isAuthenticated) {
      Alert.alert(t('signInRequiredTitle'), t('signInRequiredMessage'));
      return;
    }
    setApplyingId(item.id);
    try {
      await expressInterest(item.id);
      opportunitiesApi.refresh();
      applicationsApi.refresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('applyError'));
    } finally {
      setApplyingId(null);
    }
  }

  const tabs: Array<{ key: TabKey; label: string; icon: IoniconName; requiresAuth?: boolean }> = [
    { key: 'opportunities', label: t('tabs.opportunities'), icon: 'briefcase-outline' },
    { key: 'applications', label: t('tabs.applications'), icon: 'send-outline', requiresAuth: true },
    { key: 'hours', label: t('tabs.hours'), icon: 'time-outline', requiresAuth: true },
  ];

  const visibleTabs = tabs.filter((tab) => !tab.requiresAuth || isAuthenticated);

  useEffect(() => {
    if (!visibleTabs.some((tab) => tab.key === activeTab)) {
      setActiveTab('opportunities');
    }
  }, [activeTab, visibleTabs]);

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('title')} backLabel={t('common:back')} fallbackHref="/(tabs)/home" />
      <FlatList<VolunteerOpportunity>
        data={activeTab === 'opportunities' ? opportunities : []}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <OpportunityCard
            item={item}
            applying={applyingId === item.id}
            onOpen={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push({ pathname: '/(modals)/volunteering-detail', params: { id: String(item.id) } });
            }}
            onApply={() => void handleApply(item)}
          />
        )}
        onEndReached={activeTab === 'opportunities' && opportunitiesApi.hasMore ? opportunitiesApi.loadMore : undefined}
        onEndReachedThreshold={0.3}
        refreshControl={
          <RefreshControl
            refreshing={activeTab === 'opportunities' && opportunitiesApi.isLoading && opportunities.length > 0}
            onRefresh={() => {
              opportunitiesApi.refresh();
              applicationsApi.refresh();
              hoursApi.refresh();
              organisationsApi.refresh();
            }}
            tintColor={primary}
            colors={[primary]}
          />
        }
        ListHeaderComponent={
          <View className="gap-4 pt-3">
            <HeroHeader
              activeCount={opportunities.length}
              applicationsCount={applications.length}
              verifiedHours={verifiedHours}
            />

            <Surface variant="secondary" className="rounded-panel p-1">
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-1">
                {visibleTabs.map((tab) => {
                  const selected = activeTab === tab.key;
                  return (
                    <Pressable
                      key={tab.key}
                      onPress={() => {
                        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                        setActiveTab(tab.key);
                      }}
                      className="h-11 min-w-[132px] flex-row items-center justify-center gap-2 rounded-panel-inner px-4"
                      style={{ backgroundColor: selected ? withAlpha(primary, 0.18) : 'transparent' }}
                    >
                      <Ionicons name={tab.icon} size={16} color={selected ? primary : theme.textSecondary} />
                      <Text className="text-sm font-semibold" style={{ color: selected ? primary : theme.textSecondary }} numberOfLines={1}>
                        {tab.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </ScrollView>
            </Surface>

            {activeTab === 'opportunities' ? (
              <View className="flex-row items-center px-3 h-[48px] rounded-panel gap-2" style={{ backgroundColor: theme.surface }}>
                <Ionicons name="search-outline" size={18} color={theme.textMuted} />
                <TextInput
                  className="flex-1 text-sm py-0"
                  style={{ color: theme.text }}
                  placeholder={t('searchPlaceholder')}
                  placeholderTextColor={theme.textMuted}
                  value={search}
                  onChangeText={handleSearchChange}
                  returnKeyType="search"
                  clearButtonMode="never"
                  autoCorrect={false}
                  autoCapitalize="none"
                  accessibilityLabel={t('searchPlaceholder')}
                />
                {search.length > 0 ? (
                  <Pressable
                    onPress={handleClear}
                    hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                    accessibilityLabel={t('clearSearch')}
                    accessibilityRole="button"
                  >
                    <Ionicons name="close-circle" size={18} color={theme.textMuted} />
                  </Pressable>
                ) : null}
              </View>
            ) : null}

            {activeTab === 'applications' ? (
              <ApplicationsPanel
                applications={applications}
                isLoading={applicationsApi.isLoading}
                onRefresh={applicationsApi.refresh}
              />
            ) : null}

            {activeTab === 'hours' ? (
              <HoursPanel
                summary={summary}
                organisations={organisations}
                isLoading={hoursApi.isLoading || organisationsApi.isLoading}
                onRefresh={() => {
                  hoursApi.refresh();
                  organisationsApi.refresh();
                }}
              />
            ) : null}

            {activeTab === 'opportunities' && opportunitiesApi.error ? (
              <HeroCard className="rounded-panel p-0">
                <HeroCard.Body className="items-center gap-3 p-6">
                  <Ionicons name="warning-outline" size={28} color={theme.error} />
                  <Text className="text-center text-sm" style={{ color: theme.textSecondary }}>
                    {opportunitiesApi.error}
                  </Text>
                  <HeroButton variant="secondary" onPress={opportunitiesApi.refresh}>
                    <HeroButton.Label>{t('tryAgain')}</HeroButton.Label>
                  </HeroButton>
                </HeroCard.Body>
              </HeroCard>
            ) : null}
          </View>
        }
        ListEmptyComponent={
          activeTab === 'opportunities' ? (
            opportunitiesApi.isLoading ? (
              <View className="py-10">
                <LoadingSpinner />
              </View>
            ) : !opportunitiesApi.error ? (
              <EmptyState icon="heart-outline" title={t('empty')} />
            ) : null
          ) : null
        }
        ListFooterComponent={
          activeTab === 'opportunities' && opportunitiesApi.isLoadingMore ? (
            <View className="py-4">
              <LoadingSpinner />
            </View>
          ) : null
        }
        contentContainerStyle={{ flexGrow: 1, paddingHorizontal: 16, paddingBottom: 32 }}
      />
    </SafeAreaView>
  );
}
