// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';
import {
  Linking,
  ScrollView,
  Share,
  Text,
  Pressable,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { getFederationPartner } from '@/lib/api/federation';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function FederationPartnerScreen() {
  const { t } = useTranslation('federation');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('detail.title') });
  }, [navigation, t]);

  const partnerId = Number(id);
  const safeId = isNaN(partnerId) || partnerId <= 0 ? 0 : partnerId;

  const { data, isLoading } = useApi(
    () => getFederationPartner(safeId),
    [safeId],
    { enabled: safeId > 0 },
  );

  const partner = data?.data ?? null;

  if (safeId === 0) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.notFound')}</Text>
        <Pressable onPress={() => router.back()} className="mt-3">
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack')}
          </Text>
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

  if (!partner) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.notFound')}</Text>
        <Pressable onPress={() => router.back()} className="mt-3">
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>
            {t('detail.goBack')}
          </Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  async function handleShare() {
    if (!partner) return;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    try {
      await Share.share({
        message: `${partner.name} — ${WEB_URL}/federation/${partner.id}`,
      });
    } catch { /* ignore */ }
  }

  const connectedDate = new Date(partner.connected_since).toLocaleDateString('default', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      <ScrollView contentContainerStyle={{ padding: 20, paddingBottom: 48 }}>
        {/* Logo + name + share */}
        <View className="items-center gap-2.5 mb-5">
          <Avatar uri={partner.logo} name={partner.name} size={80} />
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <Text className="text-xl font-bold text-foreground text-center" style={{ flex: 1 }}>{partner.name}</Text>
            <Pressable
              onPress={() => void handleShare()}
              style={{ padding: 4 }}
              accessibilityLabel={t('detail.share')}
              accessibilityRole="button"
            >
              <Ionicons name="share-outline" size={22} color={primary} />
            </Pressable>
          </View>
          {partner.location ? (
            <View className="flex-row items-center gap-1">
              <Ionicons name="location-outline" size={14} color={theme.textSecondary} />
              <Text className="text-xs text-muted-foreground">{partner.location}</Text>
            </View>
          ) : null}
        </View>

        {/* Stats card */}
        <View className="flex-row items-center bg-surface rounded-xl p-4 mb-5 border border-border/50">
          <View className="flex-1 items-center gap-1">
            <Ionicons name="people-outline" size={20} color={primary} />
            <Text className="text-lg font-bold text-foreground text-center">
              {(partner.member_count ?? 0).toLocaleString()}
            </Text>
            <Text style={{ fontSize: 11, color: theme.textSecondary }}>{t('detail.members')}</Text>
          </View>
          <View style={{ width: 1, height: 40, backgroundColor: theme.border, marginHorizontal: 8 }} />
          <View className="flex-1 items-center gap-1">
            <Ionicons name="link-outline" size={20} color={primary} />
            <Text style={{ fontSize: 12, fontWeight: '500', color: theme.text, textAlign: 'center' }}>
              {t('connectedSince', { date: connectedDate })}
            </Text>
          </View>
        </View>

        {/* Description */}
        {partner.description ? (
          <View className="mb-5">
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2">
              {t('detail.about')}
            </Text>
            <Text className="text-sm text-foreground">{partner.description}</Text>
          </View>
        ) : null}

        {/* Visit website */}
        {partner.website ? (
          <Pressable
            style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderRadius: 12, paddingVertical: 14, marginTop: 4, backgroundColor: primary }}
            onPress={async () => {
              const url = partner.website!;
              const supported = await Linking.canOpenURL(url);
              if (supported) {
                await Linking.openURL(url);
              }
            }}
            accessibilityLabel={t('visitWebsite')}
            accessibilityRole="button"
          >
            <Ionicons name="globe-outline" size={16} color="#fff" />{/* contrast on primary */}
            <Text style={{ color: '#fff', fontSize: 15, fontWeight: '600' }}>{t('visitWebsite')}</Text>
          </Pressable>
        ) : null}
      </ScrollView>
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}
