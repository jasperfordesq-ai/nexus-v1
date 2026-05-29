// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps } from 'react';
import { useState } from 'react';
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
  documentKey?: string;
};

type SupportDocument = {
  key: string;
  icon: ComponentProps<typeof Ionicons>['name'];
  url: string;
};

const SUPPORT_ITEMS: SupportItem[] = [
  { key: 'help', icon: 'help-circle-outline', url: 'https://app.project-nexus.ie/help' },
  { key: 'resources', icon: 'library-outline', route: '/(modals)/resources' as Href },
  { key: 'about', icon: 'information-circle-outline', url: 'https://app.project-nexus.ie/about', documentKey: 'about' },
  { key: 'contact', icon: 'mail-outline', url: 'https://app.project-nexus.ie/contact', documentKey: 'contact' },
  { key: 'terms', icon: 'document-text-outline', url: 'https://app.project-nexus.ie/terms', documentKey: 'terms' },
  { key: 'privacy', icon: 'shield-checkmark-outline', url: 'https://app.project-nexus.ie/privacy', documentKey: 'privacy' },
  { key: 'cookies', icon: 'settings-outline', url: 'https://app.project-nexus.ie/cookies', documentKey: 'cookies' },
  { key: 'accessibility', icon: 'accessibility-outline', url: 'https://app.project-nexus.ie/accessibility', documentKey: 'accessibility' },
  { key: 'trust', icon: 'shield-outline', url: 'https://app.project-nexus.ie/trust-and-safety', documentKey: 'trust' },
];

const SUPPORT_DOCUMENTS: Record<string, SupportDocument> = SUPPORT_ITEMS.reduce((acc, item) => {
  if (item.documentKey && item.url) {
    acc[item.documentKey] = { key: item.documentKey, icon: item.icon, url: item.url };
  }
  return acc;
}, {} as Record<string, SupportDocument>);

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
  const [selectedDocumentKey, setSelectedDocumentKey] = useState<string | null>(null);
  const selectedDocument = selectedDocumentKey ? SUPPORT_DOCUMENTS[selectedDocumentKey] : null;

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

        {selectedDocument ? (
          <SupportDocumentCard
            document={selectedDocument}
            theme={theme}
            t={t}
            onClose={() => setSelectedDocumentKey(null)}
          />
        ) : null}

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
                <View className="flex-row gap-2">
                  {item.documentKey ? (
                    <HeroButton className="flex-1" variant="primary" onPress={() => setSelectedDocumentKey(item.documentKey ?? null)}>
                      <HeroButton.Label>{t('support.readInApp')}</HeroButton.Label>
                      <Ionicons name="reader-outline" size={16} color={theme.onPrimary} />
                    </HeroButton>
                  ) : null}
                  <HeroButton
                    className="flex-1"
                    variant="secondary"
                    onPress={() => item.route ? router.push(item.route) : void Linking.openURL(item.url ?? '')}
                  >
                    <HeroButton.Label>{t(item.route ? 'support.open' : 'support.openWeb')}</HeroButton.Label>
                    <Ionicons name={item.route ? 'chevron-forward-outline' : 'open-outline'} size={16} color={theme.info} />
                  </HeroButton>
                </View>
              </HeroCard.Body>
            </HeroCard>
          ))}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

function SupportDocumentCard({
  document,
  theme,
  t,
  onClose,
}: {
  document: SupportDocument;
  theme: ReturnType<typeof useTheme>;
  t: (key: string) => string;
  onClose: () => void;
}) {
  return (
    <HeroCard className="mb-4 rounded-panel">
      <HeroCard.Body className="gap-4 p-4">
        <View className="flex-row items-start gap-3">
          <View className="size-11 items-center justify-center rounded-panel-inner bg-accent-soft">
            <Ionicons name={document.icon} size={21} color={theme.info} />
          </View>
          <View className="min-w-0 flex-1">
            <Text className="text-lg font-bold" style={{ color: theme.text }}>
              {t(`support.docs.${document.key}.title`)}
            </Text>
            <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>
              {t(`support.docs.${document.key}.summary`)}
            </Text>
          </View>
        </View>
        {[1, 2, 3].map((section) => (
          <View key={section} className="gap-1 rounded-panel-inner bg-surface-secondary p-3">
            <Text className="text-sm font-semibold" style={{ color: theme.text }}>
              {t(`support.docs.${document.key}.section${section}Title`)}
            </Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
              {t(`support.docs.${document.key}.section${section}Body`)}
            </Text>
          </View>
        ))}
        <View className="flex-row gap-2">
          <HeroButton className="flex-1" variant="secondary" onPress={onClose}>
            <HeroButton.Label>{t('support.closeDocument')}</HeroButton.Label>
          </HeroButton>
          <HeroButton className="flex-1" variant="secondary" onPress={() => void Linking.openURL(document.url)}>
            <HeroButton.Label>{t('support.openWeb')}</HeroButton.Label>
            <Ionicons name="open-outline" size={16} color={theme.info} />
          </HeroButton>
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}
