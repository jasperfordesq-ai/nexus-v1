// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { RefreshControl, ScrollView, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Tabs } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import {
  getIdeationCategories,
  getIdeationChallenges,
  type IdeationCategory,
  type IdeationChallenge,
  type IdeationStatus,
} from '@/lib/api/ideation';
import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import Input from '@/components/ui/Input';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor, useTenant } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

type FilterStatus = 'all' | IdeationStatus;

const STATUS_FILTERS: FilterStatus[] = ['all', 'open', 'voting', 'evaluating', 'closed'];

export default function IdeationScreen() {
  const { t } = useTranslation(['ideation', 'common']);
  const { hasFeature } = useTenant();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [status, setStatus] = useState<FilterStatus>('all');
  const [search, setSearch] = useState('');
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const {
    data: challengesPage,
    isLoading,
    error,
    refresh: refreshChallenges,
  } = useApi(() => getIdeationChallenges({ status, search, categoryId }), [status, search, categoryId], {
    enabled: hasFeature('ideation_challenges'),
  });
  const {
    data: categories,
    refresh: refreshCategories,
  } = useApi(() => getIdeationCategories(), [], { enabled: hasFeature('ideation_challenges') });

  function refresh() {
    refreshChallenges();
    refreshCategories();
  }

  if (!hasFeature('ideation_challenges')) {
    return (
      <ModalErrorBoundary>
        <SafeAreaView className="flex-1 bg-background">
          <AppTopBar title={t('ideation:title')} backLabel={t('common:back')} fallbackHref="/(tabs)/profile" />
          <View className="px-4 py-8">
            <EmptyState icon="bulb-outline" title={t('ideation:disabledTitle')} subtitle={t('ideation:disabledSubtitle')} />
          </View>
        </SafeAreaView>
      </ModalErrorBoundary>
    );
  }

  const challenges = challengesPage?.items ?? [];

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('ideation:title')} backLabel={t('common:back')} fallbackHref="/(tabs)/profile" />
        <ScrollView
          contentContainerStyle={{ paddingBottom: 40 }}
          refreshControl={<RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />}
        >
          <View className="gap-3">
            <HeroCard variant="default" className="mx-4 overflow-hidden rounded-panel p-0">
              <View className="h-1 w-full" style={{ backgroundColor: primary }} />
              <HeroCard.Body className="gap-4 p-4">
                <View className="flex-row items-start gap-3">
                  <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                    <Ionicons name="bulb-outline" size={25} color={primary} />
                  </View>
                  <View className="min-w-0 flex-1">
                    <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }}>
                      {t('ideation:title')}
                    </Text>
                    <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>
                      {t('ideation:subtitle')}
                    </Text>
                  </View>
                </View>
              </HeroCard.Body>
            </HeroCard>

            <View className="mx-4">
              <Input
                label={t('ideation:searchLabel')}
                placeholder={t('ideation:searchPlaceholder')}
                value={search}
                onChangeText={setSearch}
                leftIcon={<Ionicons name="search-outline" size={18} className="text-muted-foreground" />}
                containerClassName="mb-0"
              />
            </View>

            <Surface variant="default" className="mx-4 rounded-panel-inner p-2">
              <Tabs value={status} onValueChange={(value) => setStatus(value as FilterStatus)} variant="secondary">
                <Tabs.List>
                  <Tabs.ScrollView scrollAlign="start" contentContainerClassName="gap-1">
                    <Tabs.Indicator />
                    {STATUS_FILTERS.map((item) => (
                      <Tabs.Trigger key={item} value={item}>
                        <Tabs.Label>{t(`ideation:filters.${item}`)}</Tabs.Label>
                      </Tabs.Trigger>
                    ))}
                  </Tabs.ScrollView>
                </Tabs.List>
              </Tabs>
            </Surface>

            <CategoryStrip categories={categories ?? []} selectedId={categoryId} onSelect={setCategoryId} />

            {isLoading ? (
              <View className="items-center justify-center py-14">
                <LoadingSpinner />
              </View>
            ) : error ? (
              <View className="px-4 py-8">
                <EmptyState icon="warning-outline" title={t('ideation:errorTitle')} subtitle={String(error)} actionLabel={t('common:buttons.retry')} onAction={refresh} />
              </View>
            ) : challenges.length > 0 ? (
              <View className="gap-3 px-4">
                {challenges.map((challenge) => <ChallengeCard key={challenge.id} challenge={challenge} />)}
              </View>
            ) : (
              <View className="px-4 py-8">
                <EmptyState icon="bulb-outline" title={t('ideation:emptyTitle')} subtitle={t('ideation:emptySubtitle')} />
              </View>
            )}
          </View>
        </ScrollView>
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function CategoryStrip({
  categories,
  selectedId,
  onSelect,
}: {
  categories: IdeationCategory[];
  selectedId: number | null;
  onSelect: (id: number | null) => void;
}) {
  const { t } = useTranslation(['ideation']);
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2 px-4">
      <HeroButton size="sm" variant={selectedId === null ? 'primary' : 'secondary'} onPress={() => onSelect(null)}>
        <HeroButton.Label>{t('ideation:allCategories')}</HeroButton.Label>
      </HeroButton>
      {categories.map((category) => (
        <HeroButton key={category.id} size="sm" variant={selectedId === category.id ? 'primary' : 'secondary'} onPress={() => onSelect(category.id)}>
          <HeroButton.Label>{category.name}</HeroButton.Label>
          <Chip size="sm" variant="secondary">
            <Chip.Label>{t('ideation:categoryCount', { count: category.challenges_count ?? 0 })}</Chip.Label>
          </Chip>
        </HeroButton>
      ))}
    </ScrollView>
  );
}

function ChallengeCard({ challenge }: { challenge: IdeationChallenge }) {
  const { t } = useTranslation(['ideation']);
  const theme = useTheme();
  return (
    <HeroCard variant="default" className="overflow-hidden rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row items-start gap-3">
          <View className="size-11 items-center justify-center rounded-panel-inner bg-surface-secondary">
            <Ionicons name="bulb-outline" size={22} color={theme.info} />
          </View>
          <View className="min-w-0 flex-1 gap-1">
            <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={2}>
              {challenge.title}
            </Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={3}>
              {stripHtml(challenge.description)}
            </Text>
            <View className="flex-row flex-wrap gap-2">
              <Chip size="sm" variant="secondary">
                <Chip.Label>{t(`ideation:status.${challenge.status}`)}</Chip.Label>
              </Chip>
              {challenge.category ? (
                <Chip size="sm" variant="secondary">
                  <Chip.Label>{challenge.category}</Chip.Label>
                </Chip>
              ) : null}
              <Chip size="sm" variant="secondary">
                <Chip.Label>{t('ideation:ideasCount', { count: challenge.ideas_count ?? 0 })}</Chip.Label>
              </Chip>
            </View>
          </View>
        </View>
        <HeroButton variant="secondary" onPress={() => router.push({ pathname: '/(modals)/ideation-detail', params: { id: String(challenge.id) } } as unknown as Href)}>
          <HeroButton.Label>{t('ideation:viewChallenge')}</HeroButton.Label>
          <Ionicons name="chevron-forward-outline" size={16} color={theme.info} />
        </HeroButton>
      </HeroCard.Body>
    </HeroCard>
  );
}

function stripHtml(value: string): string {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}
