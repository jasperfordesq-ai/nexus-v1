// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Alert,
  Linking,
  RefreshControl,
  ScrollView,
  Share,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { getOrganisation } from '@/lib/api/organisations';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';
const TRANSLATABLE_STATUSES = new Set(['approved', 'active', 'pending', 'declined']);

function getStatusLabel(status: string | null | undefined, t: (key: string) => string): string | null {
  if (!status) {
    return null;
  }

  const normalized = status.toLowerCase();
  if (!TRANSLATABLE_STATUSES.has(normalized)) {
    return status;
  }

  return t(`status.${normalized}`);
}

export default function OrganisationDetailScreen() {
  const { t } = useTranslation('organisations');
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();

  const orgId = Number(id);
  const safeId = isNaN(orgId) || orgId <= 0 ? 0 : orgId;

  const { data, isLoading, refresh } = useApi(
    () => getOrganisation(safeId),
    [safeId],
    { enabled: safeId > 0 },
  );

  const organisation = data?.data ?? null;

  if (safeId === 0) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detailTitle')} backLabel={t('common:back')} fallbackHref="/(modals)/organisations" />
        <EmptyState
          icon="business-outline"
          title={t('detail.invalidId')}
          subtitle={t('detail.invalidIdHint')}
          actionLabel={t('detail.browseOrganisations')}
          onAction={() => router.replace('/(modals)/organisations')}
        />
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detailTitle')} backLabel={t('common:back')} fallbackHref="/(modals)/organisations" />
        <View className="flex-1 items-center justify-center">
          <LoadingSpinner />
        </View>
      </SafeAreaView>
    );
  }

  if (!organisation) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detailTitle')} backLabel={t('common:back')} fallbackHref="/(modals)/organisations" />
        <EmptyState
          icon="business-outline"
          title={t('detail.notFound')}
          subtitle={t('detail.notFoundHint')}
          actionLabel={t('detail.browseOrganisations')}
          onAction={() => router.replace('/(modals)/organisations')}
        />
      </SafeAreaView>
    );
  }

  async function handleShare() {
    if (!organisation) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: `${organisation.name} - ${WEB_URL}/organisations/${organisation.id}`,
      });
    } catch {
      // Share sheet dismissed.
    }
  }

  async function handleOpenWebsite() {
    if (!organisation?.website) return;
    const supported = await Linking.canOpenURL(organisation.website);
    if (supported) {
      await Linking.openURL(organisation.website);
    } else {
      Alert.alert(t('common:errors.alertTitle'), organisation.website);
    }
  }

  const membersCount = organisation.members_count ?? 0;
  const listingsCount = organisation.listings_count ?? 0;
  const opportunityCount = organisation.opportunity_count ?? 0;
  const totalHours = organisation.total_hours ?? 0;
  const statusLabel = getStatusLabel(organisation.status, t);

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar
          title={t('detailTitle')}
          backLabel={t('common:back')}
          fallbackHref="/(modals)/organisations"
          rightAction={{
            accessibilityLabel: t('detail.share'),
            icon: 'share-outline',
            onPress: handleShare,
          }}
        />
        <ScrollView
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 40 }}
          refreshControl={
            <RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />
          }
        >
          <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
            <View className="h-1.5" style={{ backgroundColor: primary }} />
            <HeroCard.Body className="gap-4 p-4">
              <View className="flex-row items-start gap-4">
                <Avatar uri={organisation.logo ?? organisation.logo_url ?? null} name={organisation.name} size={72} />
                <View className="min-w-0 flex-1 gap-2">
                  <View className="flex-row flex-wrap gap-2">
                    {organisation.verified ? (
                      <Chip size="sm" variant="secondary">
                        <Ionicons name="checkmark-circle-outline" size={12} color={primary} />
                        <Chip.Label>{t('verified')}</Chip.Label>
                      </Chip>
                    ) : null}
                    {statusLabel ? (
                      <Chip size="sm" variant="secondary">
                        <Chip.Label>{statusLabel}</Chip.Label>
                      </Chip>
                    ) : null}
                  </View>
                  <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }}>
                    {organisation.name}
                  </Text>
                  {organisation.description ? (
                    <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={3}>
                      {organisation.description}
                    </Text>
                  ) : null}
                </View>
              </View>

              <View className="flex-row gap-2">
                {organisation.website ? (
                  <HeroButton className="flex-1" variant="secondary" accessibilityLabel={t('website')} onPress={() => void handleOpenWebsite()}>
                    <Ionicons name="globe-outline" size={17} color={primary} />
                    <HeroButton.Label>{t('website')}</HeroButton.Label>
                  </HeroButton>
                ) : null}
                <HeroButton className="flex-1" variant="secondary" accessibilityLabel={t('detail.share')} onPress={() => void handleShare()}>
                  <Ionicons name="share-outline" size={17} color={primary} />
                  <HeroButton.Label>{t('detail.share')}</HeroButton.Label>
                </HeroButton>
              </View>
            </HeroCard.Body>
          </HeroCard>

          <View className="mb-4 flex-row flex-wrap gap-3">
            <StatTile icon="people-outline" value={membersCount} label={t('members', { count: membersCount })} primary={primary} theme={theme} />
            <StatTile icon="list-outline" value={listingsCount} label={t('listings', { count: listingsCount })} primary={primary} theme={theme} />
            <StatTile icon="heart-outline" value={opportunityCount} label={t('opportunities', { count: opportunityCount })} primary={primary} theme={theme} />
            <StatTile icon="time-outline" value={totalHours} label={t('hoursLogged', { hours: totalHours })} primary={primary} theme={theme} />
          </View>

          {organisation.location || organisation.contact_email ? (
            <HeroCard className="mb-4 rounded-panel p-0">
              <HeroCard.Body className="gap-3 p-4">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>
                  {t('detail.contact')}
                </Text>
                {organisation.location ? (
                  <InfoRow icon="location-outline" text={organisation.location} theme={theme} />
                ) : null}
                {organisation.contact_email ? (
                  <InfoRow icon="mail-outline" text={organisation.contact_email} theme={theme} />
                ) : null}
              </HeroCard.Body>
            </HeroCard>
          ) : null}

          <HeroCard className="mb-4 rounded-panel p-0">
            <HeroCard.Body className="gap-3 p-4">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>
                {t('detail.about')}
              </Text>
              <Text className="text-sm leading-6" style={{ color: theme.text }}>
                {organisation.description ?? t('noDescription')}
              </Text>
            </HeroCard.Body>
          </HeroCard>
        </ScrollView>
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function StatTile({
  icon,
  value,
  label,
  primary,
  theme,
}: {
  icon: React.ComponentProps<typeof Ionicons>['name'];
  value: number;
  label: string;
  primary: string;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <Surface variant="secondary" className="min-w-[47%] flex-1 rounded-panel-inner p-3">
      <View className="mb-3 size-9 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
        <Ionicons name={icon} size={18} color={primary} />
      </View>
      <Text className="text-xl font-bold" style={{ color: theme.text }}>{value}</Text>
      <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={2}>{label}</Text>
    </Surface>
  );
}

function InfoRow({
  icon,
  text,
  theme,
}: {
  icon: React.ComponentProps<typeof Ionicons>['name'];
  text: string;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <View className="flex-row items-center gap-2.5">
      <Ionicons name={icon} size={16} color={theme.textSecondary} />
      <Text className="flex-1 text-sm" style={{ color: theme.text }}>{text}</Text>
    </View>
  );
}
