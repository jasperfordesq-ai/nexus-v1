// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useMemo, useState, type ReactNode } from 'react';
import { FlatList, Linking, Pressable, RefreshControl, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface } from 'heroui-native';
import { useTranslation } from 'react-i18next';
import * as Haptics from '@/lib/haptics';

import {
  getOrganisations,
  type Organisation,
  type OrganisationsResponse,
} from '@/lib/api/organisations';
import { useDebounce } from '@/lib/hooks/useDebounce';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

function extractOrganisationPage(response: OrganisationsResponse) {
  return {
    items: response.data,
    cursor: response.meta.cursor,
    hasMore: response.meta.has_more,
  };
}

function logoFor(org: Organisation) {
  return org.logo ?? org.logo_url ?? null;
}

function opportunityCount(org: Organisation) {
  return org.opportunity_count ?? org.listings_count ?? 0;
}

function volunteerCount(org: Organisation) {
  return org.volunteer_count ?? org.members_count ?? 0;
}

function isVerified(org: Organisation) {
  return org.verified === true || org.status === 'approved' || org.status === 'active';
}

function formatRating(value: number | null | undefined) {
  return typeof value === 'number' && value > 0 ? value.toFixed(1) : null;
}

function StatTile({
  icon,
  label,
  value,
  tone,
  theme,
}: {
  icon: IoniconName;
  label: string;
  value: string;
  tone: string;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <Surface variant="secondary" className="min-w-[46%] flex-1 gap-2 rounded-panel-inner p-4">
      <View className="size-9 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(tone, 0.14) }}>
        <Ionicons name={icon} size={18} color={tone} />
      </View>
      <Text className="text-2xl font-bold" style={{ color: theme.text }} numberOfLines={1}>
        {value}
      </Text>
      <Text className="text-[11px] font-semibold uppercase" style={{ color: theme.textSecondary }} numberOfLines={1}>
        {label}
      </Text>
    </Surface>
  );
}

function OrganisationsHero({
  organisations,
  primary,
  theme,
  t,
  onRegister,
}: {
  organisations: Organisation[];
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onRegister: () => void;
}) {
  const verifiedCount = organisations.filter(isVerified).length;

  return (
    <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
      <View className="h-1.5" style={{ backgroundColor: primary }} />
      <HeroCard.Body className="gap-5 p-4 pt-0">
        <View className="flex-row items-start gap-3">
          <View className="size-12 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
            <Ionicons name="business-outline" size={24} color={primary} />
          </View>
          <View className="min-w-0 flex-1 gap-1">
            <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
              {t('heroEyebrow')}
            </Text>
            <Text className="text-2xl font-bold" style={{ color: theme.text }} numberOfLines={1}>
              {t('title')}
            </Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
              {t('subtitle')}
            </Text>
          </View>
        </View>

        <View className="flex-row flex-wrap gap-3">
          <StatTile icon="business-outline" label={t('stats.organisations')} value={String(organisations.length)} tone={primary} theme={theme} />
          <StatTile icon="checkmark-circle-outline" label={t('stats.verified')} value={String(verifiedCount)} tone="#22c55e" theme={theme} />
        </View>

        <HeroButton variant="primary" onPress={onRegister} style={{ backgroundColor: primary }}>
          <Ionicons name="add-circle-outline" size={16} color="#ffffff" />
          <HeroButton.Label>{t('registerButton')}</HeroButton.Label>
        </HeroButton>
      </HeroCard.Body>
    </HeroCard>
  );
}

function MetaPill({
  icon,
  children,
  tone,
  theme,
}: {
  icon: IoniconName;
  children: ReactNode;
  tone: string;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <View className="flex-row items-center gap-1 rounded-full px-2.5 py-1.5" style={{ backgroundColor: withAlpha(tone, 0.12) }}>
      <Ionicons name={icon} size={13} color={tone} />
      <Text className="text-xs font-medium" style={{ color: theme.text }} numberOfLines={1}>
        {children}
      </Text>
    </View>
  );
}

function OrganisationCard({
  item,
  primary,
  theme,
  t,
  onPress,
}: {
  item: Organisation;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onPress: () => void;
}) {
  const opportunities = opportunityCount(item);
  const volunteers = volunteerCount(item);
  const hours = item.total_hours ?? 0;
  const rating = formatRating(item.average_rating);
  const verified = isVerified(item);

  async function openWebsite() {
    if (!item.website) return;
    const url = item.website.startsWith('http') ? item.website : `https://${item.website}`;
    await Linking.openURL(url);
  }

  return (
    <Pressable className="mx-4 my-2" onPress={onPress} accessibilityRole="button" accessibilityLabel={item.name}>
      <HeroCard className="min-h-[188px] w-full rounded-panel p-0">
        <HeroCard.Body className="gap-4 p-4">
          <View className="flex-row items-start gap-3">
            <Avatar uri={logoFor(item)} name={item.name} size={56} />
            <View className="min-w-0 flex-1 gap-2">
              <View className="flex-row items-start gap-2">
                <View className="min-w-0 flex-1">
                  <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={2}>
                    {item.name}
                  </Text>
                  {item.location ? (
                    <View className="mt-1 flex-row items-center gap-1">
                      <Ionicons name="location-outline" size={13} color={theme.textSecondary} />
                      <Text className="min-w-0 flex-1 text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
                        {item.location}
                      </Text>
                    </View>
                  ) : null}
                </View>
                {verified ? (
                  <Chip size="sm" variant="secondary" color="success">
                    <Ionicons name="checkmark-circle-outline" size={12} color="#22c55e" />
                    <Chip.Label>{t('verified')}</Chip.Label>
                  </Chip>
                ) : null}
              </View>
            </View>
          </View>

          <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={3}>
            {item.description || t('noDescription')}
          </Text>

          <View className="flex-row flex-wrap gap-2">
            {opportunities > 0 ? (
              <MetaPill icon="heart-outline" tone="#f43f5e" theme={theme}>
                {t('opportunities', { count: opportunities })}
              </MetaPill>
            ) : null}
            {volunteers > 0 ? (
              <MetaPill icon="people-outline" tone="#6366f1" theme={theme}>
                {t('volunteers', { count: volunteers })}
              </MetaPill>
            ) : null}
            {hours > 0 ? (
              <MetaPill icon="time-outline" tone="#22c55e" theme={theme}>
                {t('hoursLogged', { hours })}
              </MetaPill>
            ) : null}
            {rating ? (
              <MetaPill icon="star-outline" tone="#f59e0b" theme={theme}>
                {rating}
              </MetaPill>
            ) : null}
          </View>

          <View className="flex-row items-center gap-2">
            {item.website ? (
              <HeroButton
                className="flex-1"
                size="sm"
                variant="secondary"
                accessibilityLabel={t('website')}
                onPress={() => void openWebsite()}
              >
                <Ionicons name="globe-outline" size={15} color={primary} />
                <HeroButton.Label>{t('website')}</HeroButton.Label>
              </HeroButton>
            ) : null}
            <HeroButton className="flex-1" size="sm" variant="primary" onPress={onPress}>
              <HeroButton.Label>{t('viewOrganisation')}</HeroButton.Label>
              <Ionicons name="chevron-forward-outline" size={15} color="#ffffff" />
            </HeroButton>
          </View>
        </HeroCard.Body>
      </HeroCard>
    </Pressable>
  );
}

function OrganisationCardSkeleton() {
  return (
    <HeroCard className="mx-4 my-2 rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row gap-3">
          <Surface variant="secondary" className="size-14 rounded-2xl" />
          <View className="flex-1 gap-2">
            <Surface variant="secondary" className="h-4 w-3/4 rounded-full" />
            <Surface variant="secondary" className="h-3 w-1/2 rounded-full" />
          </View>
        </View>
        <Surface variant="secondary" className="h-3 w-full rounded-full" />
        <Surface variant="secondary" className="h-3 w-2/3 rounded-full" />
      </HeroCard.Body>
    </HeroCard>
  );
}

export default function OrganisationsScreen() {
  const { t } = useTranslation(['organisations', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 350);

  const fetchFn = useCallback(
    (cursor: string | null) => getOrganisations(cursor, debouncedSearch || undefined),
    [debouncedSearch],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Organisation, OrganisationsResponse>(fetchFn, extractOrganisationPage, [debouncedSearch]);

  const organisations = useMemo(() => items, [items]);

  const openOrganisation = useCallback((id: number) => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    router.push({
      pathname: '/(modals)/organisation-detail',
      params: { id: String(id) },
    });
  }, []);

  const openRegistration = useCallback(() => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    router.push('/(modals)/new-organisation' as Href);
  }, []);

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar
          title={t('title')}
          backLabel={t('common:back')}
          fallbackHref="/(tabs)/home"
        />

        <FlatList<Organisation>
          data={organisations}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => (
            <OrganisationCard
              item={item}
              primary={primary}
              theme={theme}
              t={t}
              onPress={() => openOrganisation(item.id)}
            />
          )}
          refreshControl={
            <RefreshControl
              refreshing={isLoading && organisations.length > 0}
              onRefresh={refresh}
              tintColor={primary}
              colors={[primary]}
            />
          }
          onEndReached={() => { if (hasMore) loadMore(); }}
          onEndReachedThreshold={0.3}
          ListHeaderComponent={
            <View className="gap-3 px-4 pb-3">
              <OrganisationsHero organisations={organisations} primary={primary} theme={theme} t={t} onRegister={openRegistration} />

              <Surface variant="default" className="gap-3 rounded-panel p-3">
                <View className="flex-row items-center rounded-panel-inner border border-border bg-background px-3">
                  <Ionicons name="search-outline" size={18} color={theme.textMuted} />
                  <TextInput
                    className="min-w-0 flex-1 px-2 py-3 text-base"
                    style={{ color: theme.text }}
                    placeholder={t('searchPlaceholder')}
                    placeholderTextColor={theme.textMuted}
                    value={search}
                    onChangeText={setSearch}
                    returnKeyType="search"
                    clearButtonMode="while-editing"
                    autoCorrect={false}
                    accessibilityLabel={t('searchPlaceholder')}
                  />
                  {search.length > 0 ? (
                    <Pressable onPress={() => setSearch('')} accessibilityRole="button" accessibilityLabel={t('clearSearch')}>
                      <Ionicons name="close-circle" size={18} color={theme.textMuted} />
                    </Pressable>
                  ) : null}
                </View>
              </Surface>
            </View>
          }
          ListEmptyComponent={
            isLoading ? (
              <><OrganisationCardSkeleton /><OrganisationCardSkeleton /><OrganisationCardSkeleton /></>
            ) : error ? (
              <Surface variant="secondary" className="mx-4 items-center gap-3 rounded-panel p-6">
                <Ionicons name="alert-circle-outline" size={28} color={theme.error} />
                <Text className="text-center text-sm" style={{ color: theme.text }}>
                  {error}
                </Text>
                <HeroButton variant="secondary" onPress={() => void refresh()}>
                  <HeroButton.Label>{t('common:buttons.retry')}</HeroButton.Label>
                </HeroButton>
              </Surface>
            ) : (
              <View className="px-4 pt-4">
                <EmptyState
                  icon="business-outline"
                  title={t('emptyTitle')}
                  subtitle={debouncedSearch ? t('tryDifferentSearch') : t('empty')}
                />
              </View>
            )
          }
          ListFooterComponent={
            isLoadingMore ? (
              <View className="items-center py-4"><Spinner size="sm" /></View>
            ) : !hasMore && organisations.length > 0 && !isLoading ? (
              <View className="items-center py-4">
                <Text className="text-xs" style={{ color: theme.textMuted }}>{t('common:endOfList')}</Text>
              </View>
            ) : null
          }
          contentContainerStyle={{ paddingBottom: 28 }}
        />
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}
