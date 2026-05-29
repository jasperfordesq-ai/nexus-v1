// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps } from 'react';
import { ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Linking from 'expo-linking';
import { useTranslation } from 'react-i18next';
import { Button as HeroButton, Card as HeroCard, Text } from 'heroui-native';

import AppTopBar from '@/components/ui/AppTopBar';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import { useTheme } from '@/lib/hooks/useTheme';

type SupportItem = {
  key: string;
  icon: ComponentProps<typeof Ionicons>['name'];
  url?: string;
  route?: Href;
};

const SUPPORT_ITEMS: SupportItem[] = [
  { key: 'help', icon: 'help-circle-outline', url: 'https://app.project-nexus.ie/help' },
  { key: 'resources', icon: 'library-outline', route: '/(modals)/resources' as Href },
  { key: 'about', icon: 'information-circle-outline', url: 'https://app.project-nexus.ie/about' },
  { key: 'contact', icon: 'mail-outline', url: 'https://app.project-nexus.ie/contact' },
  { key: 'terms', icon: 'document-text-outline', url: 'https://app.project-nexus.ie/terms' },
  { key: 'privacy', icon: 'shield-checkmark-outline', url: 'https://app.project-nexus.ie/privacy' },
  { key: 'cookies', icon: 'settings-outline', url: 'https://app.project-nexus.ie/cookies' },
  { key: 'accessibility', icon: 'accessibility-outline', url: 'https://app.project-nexus.ie/accessibility' },
];

export default function SupportRoute() {
  return (
    <ModalErrorBoundary>
      <SupportScreen />
    </ModalErrorBoundary>
  );
}

function SupportScreen() {
  const { t } = useTranslation(['profile', 'common']);
  const theme = useTheme();

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('support.title')} backLabel={t('common:back')} fallbackHref="/(tabs)/profile" />
      <ScrollView className="flex-1" contentContainerClassName="px-4 pb-8">
        <HeroCard className="mb-4 rounded-panel">
          <HeroCard.Body className="gap-3 p-4">
            <View className="flex-row items-center gap-3">
              <View className="size-12 items-center justify-center rounded-panel-inner bg-accent-soft">
                <Ionicons name="compass-outline" size={24} color={theme.info} />
              </View>
              <View className="min-w-0 flex-1">
                <Text className="text-xl font-bold" style={{ color: theme.text }}>
                  {t('support.heading')}
                </Text>
                <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>
                  {t('support.description')}
                </Text>
              </View>
            </View>
          </HeroCard.Body>
        </HeroCard>

        <View className="gap-3">
          {SUPPORT_ITEMS.map((item) => (
            <HeroCard key={item.key} className="rounded-panel">
              <HeroCard.Body className="gap-3 p-4">
                <View className="flex-row items-center gap-3">
                  <View className="size-11 items-center justify-center rounded-panel-inner bg-surface-secondary">
                    <Ionicons name={item.icon} size={21} color={theme.info} />
                  </View>
                  <View className="min-w-0 flex-1">
                    <Text className="text-base font-semibold" style={{ color: theme.text }}>
                      {t(`support.items.${item.key}.title`)}
                    </Text>
                    <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>
                      {t(`support.items.${item.key}.description`)}
                    </Text>
                  </View>
                </View>
                <HeroButton variant="secondary" onPress={() => item.route ? router.push(item.route) : void Linking.openURL(item.url ?? '')}>
                  <HeroButton.Label>{t('support.open')}</HeroButton.Label>
                  <Ionicons name={item.route ? 'chevron-forward-outline' : 'open-outline'} size={16} color={theme.info} />
                </HeroButton>
              </HeroCard.Body>
            </HeroCard>
          ))}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}
