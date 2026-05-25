// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  RefreshControl,
  Pressable,
  Linking,
  Alert,
  Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { getOrganisation } from '@/lib/api/organisations';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function OrganisationDetailScreen() {
  const { t } = useTranslation('organisations');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('detail.title') });
  }, [navigation, t]);

  const orgId = Number(id);
  const safeId = isNaN(orgId) || orgId <= 0 ? 0 : orgId;

  const { data, isLoading, refresh } = useApi(
    () => getOrganisation(safeId),
    [safeId],
    { enabled: safeId > 0 },
  );

  const organisation = data?.data ?? null;

  if (isNaN(orgId) || orgId <= 0) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.invalidId')}</Text>
        <Pressable onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!organisation) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.notFound')}</Text>
        <Pressable onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  async function handleShare() {
    if (!organisation) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: `${organisation.name} — ${WEB_URL}/organisations/${organisation.id}`,
      });
    } catch { /* ignore */ }
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

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />
        }
      >
        {/* Header: logo + name + verified + share */}
        <View className="flex-row items-center gap-4 mb-8">
          <Avatar uri={organisation.logo} name={organisation.name} size={72} />
          <View className="flex-1 gap-1">
            <View className="flex-row items-start gap-2">
              <Text className="flex-1 text-xl font-bold text-foreground">{organisation.name}</Text>
              <Pressable
                onPress={() => void handleShare()}
                style={{ padding: 4 }}
                accessibilityLabel={t('detail.share')}
                accessibilityRole="button"
              >
                <Ionicons name="share-outline" size={22} color={primary} />
              </Pressable>
            </View>
            {organisation.verified ? (
              <View
                className="flex-row items-center gap-1 self-start rounded px-2 py-[3px]"
                style={{ backgroundColor: withAlpha(primary, 0.10) }}
              >
                <Ionicons name="checkmark-circle" size={14} color={primary} />
                <Text className="text-xs font-semibold" style={{ color: primary }}>{t('verified')}</Text>
              </View>
            ) : null}
          </View>
        </View>

        {/* Stats row */}
        <View className="flex-row bg-surface rounded-xl p-4 mb-4 border border-border/50">
          <View className="flex-1 items-center gap-1">
            <Ionicons name="people-outline" size={20} color={primary} />
            <Text className="text-xl font-bold text-foreground">{organisation.members_count}</Text>
            <Text className="text-xs text-muted-foreground text-center">
              {t('members', { count: organisation.members_count })}
            </Text>
          </View>
          <View className="w-px bg-border my-1" />
          <View className="flex-1 items-center gap-1">
            <Ionicons name="list-outline" size={20} color={primary} />
            <Text className="text-xl font-bold text-foreground">{organisation.listings_count}</Text>
            <Text className="text-xs text-muted-foreground text-center">
              {t('listings', { count: organisation.listings_count })}
            </Text>
          </View>
        </View>

        {/* Location */}
        {organisation.location ? (
          <View className="bg-surface rounded-xl p-4 border border-border/50 mb-4">
            <View className="flex-row items-center gap-[10px]">
              <Ionicons name="location-outline" size={16} color={theme.textSecondary} />
              <Text className="flex-1 text-sm font-medium text-foreground">{organisation.location}</Text>
            </View>
          </View>
        ) : null}

        {/* About */}
        {organisation.description ? (
          <View className="mb-8">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wide mb-[10px]">
              {t('detail.about')}
            </Text>
            <Text className="text-sm text-foreground">{organisation.description}</Text>
          </View>
        ) : null}

        {/* Website button */}
        {organisation.website ? (
          <Pressable
            className="flex-row items-center justify-center gap-2 rounded-xl py-[13px] border-[1.5px] mt-1"
            style={{ borderColor: primary }}
            onPress={() => void handleOpenWebsite()}
            accessibilityRole="link"
            accessibilityLabel={t('website')}
          >
            <Ionicons name="globe-outline" size={18} color={primary} />
            <Text className="font-semibold" style={{ color: primary }}>{t('website')}</Text>
            <Ionicons name="open-outline" size={14} color={primary} />
          </Pressable>
        ) : null}
      </ScrollView>
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}
