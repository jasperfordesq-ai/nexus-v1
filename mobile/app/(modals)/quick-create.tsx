// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import { Button as HeroButton, Card as HeroCard, Text } from 'heroui-native';

import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import { usePrimaryColor, useTenant } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

interface QuickCreateOption {
  labelKey: string;
  descriptionKey: string;
  icon: IoniconName;
  route: string;
  tone: string;
  featureGate?: string;
  moduleGate?: string;
}

const QUICK_CREATE_OPTIONS: QuickCreateOption[] = [
  {
    labelKey: 'quickCreate.newExchange',
    descriptionKey: 'quickCreate.newExchangeDescription',
    icon: 'storefront-outline',
    route: '/(modals)/new-exchange',
    tone: '#0f766e',
    moduleGate: 'listings',
  },
  {
    labelKey: 'quickCreate.newEvent',
    descriptionKey: 'quickCreate.newEventDescription',
    icon: 'calendar-outline',
    route: '/(modals)/new-event',
    tone: '#f97316',
    featureGate: 'events',
  },
  {
    labelKey: 'quickCreate.newGroup',
    descriptionKey: 'quickCreate.newGroupDescription',
    icon: 'people-outline',
    route: '/(modals)/new-group',
    tone: '#8b5cf6',
    featureGate: 'groups',
  },
  {
    labelKey: 'quickCreate.newGoal',
    descriptionKey: 'quickCreate.newGoalDescription',
    icon: 'flag-outline',
    route: '/(modals)/goals',
    tone: '#2563eb',
    featureGate: 'goals',
  },
];

export default function QuickCreateRoute() {
  const { t } = useTranslation(['common']);
  const { hasFeature, hasModule } = useTenant();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const visibleOptions = QUICK_CREATE_OPTIONS.filter((option) => {
    if (option.featureGate && !hasFeature(option.featureGate)) return false;
    if (option.moduleGate && !hasModule(option.moduleGate)) return false;
    return true;
  });

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('quickCreate.title')} backLabel={t('buttons.back')} fallbackHref="/(tabs)/home" />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }} showsVerticalScrollIndicator={false}>
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: primary }} />
          <HeroCard.Body className="gap-2 p-4">
            <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
              {t('quickCreate.eyebrow')}
            </Text>
            <Text className="text-2xl font-bold" style={{ color: theme.text }}>
              {t('quickCreate.title')}
            </Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
              {t('quickCreate.subtitle')}
            </Text>
          </HeroCard.Body>
        </HeroCard>

        {visibleOptions.length > 0 ? (
          <View className="gap-3">
            {visibleOptions.map((option) => (
              <HeroButton
                key={option.labelKey}
                accessibilityLabel={t(option.labelKey)}
                className="h-auto justify-start rounded-panel p-0"
                variant="secondary"
                onPress={() => router.push(option.route as Href)}
              >
                <View className="w-full flex-row items-center gap-3 px-3 py-3">
                  <View className="size-12 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(option.tone, 0.14) }}>
                    <Ionicons name={option.icon} size={22} color={option.tone} />
                  </View>
                  <View className="min-w-0 flex-1 gap-0.5">
                    <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={1}>
                      {t(option.labelKey)}
                    </Text>
                    <Text className="text-xs leading-4" style={{ color: theme.textSecondary }} numberOfLines={2}>
                      {t(option.descriptionKey)}
                    </Text>
                  </View>
                  <Ionicons name="chevron-forward-outline" size={18} color={theme.textSecondary} />
                </View>
              </HeroButton>
            ))}
          </View>
        ) : (
          <EmptyState
            icon="add-circle-outline"
            title={t('quickCreate.emptyTitle')}
            subtitle={t('quickCreate.emptySubtitle')}
          />
        )}
      </ScrollView>
    </SafeAreaView>
  );
}
