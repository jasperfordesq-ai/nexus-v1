// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  Pressable,
  SafeAreaView,
} from 'react-native';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';
import { Separator } from 'heroui-native';

import { listTenants, type TenantListItem } from '@/lib/api/tenant';
import { useApi } from '@/lib/hooks/useApi';
import { useTenant, usePrimaryColor } from '@/lib/hooks/useTenant';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import Button from '@/components/ui/Button';

export default function SelectTenantScreen() {
  const { t } = useTranslation('auth');
  const { setTenantSlug, tenantSlug } = useTenant();
  const primary = usePrimaryColor();
  const { data, isLoading, error, refresh } = useApi(() => listTenants());

  const tenants = data?.data ?? [];

  async function handleSelect(tenant: TenantListItem) {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    await setTenantSlug(tenant.slug);
    router.back();
  }

  const ItemSeparator = useCallback(() => <Separator />, []);

  return (
    <SafeAreaView className="flex-1 bg-background">
      <View className="px-6 pt-6 pb-2">
        <Text className="text-[22px] font-bold text-foreground">{t('selectTenant.title')}</Text>
        <Text className="text-sm text-muted-foreground mt-1">{t('selectTenant.subtitle')}</Text>
      </View>

      {isLoading ? (
        <LoadingSpinner />
      ) : null}

      {error ? (
        <View className="flex-1 items-center justify-center p-8">
          <Text className="text-danger text-sm mb-3">{error}</Text>
          <Button onPress={() => void refresh()} variant="ghost">
            {t('common:buttons.retry')}
          </Button>
        </View>
      ) : null}

      {!isLoading && !error ? (
        <FlatList<TenantListItem>
          data={tenants}
          keyExtractor={(item) => String(item.id)}
          ItemSeparatorComponent={ItemSeparator}
          contentContainerStyle={{ paddingHorizontal: 16 }}
          renderItem={({ item }) => {
            const isActive = item.slug === tenantSlug;
            return (
              <Pressable
                className={`flex-row items-center py-3.5 gap-3${isActive ? ' bg-accent/10 rounded-xl px-2' : ''}`}
                onPress={() => void handleSelect(item)}
                accessibilityRole="button"
                accessibilityLabel={item.name}
                accessibilityState={{ selected: isActive }}
              >
                {item.logo_url ? (
                  <Image
                    source={{ uri: item.logo_url }}
                    style={{ width: 40, height: 40, borderRadius: 8 }}
                    contentFit="contain"
                  />
                ) : (
                  <View
                    className="w-10 h-10 rounded-lg items-center justify-center"
                    style={{ backgroundColor: primary }}
                  >
                    <Text className="text-white font-bold text-lg">
                      {item.name.charAt(0).toUpperCase()}
                    </Text>
                  </View>
                )}
                <Text className="flex-1 text-base font-medium text-foreground">{item.name}</Text>
                {isActive ? (
                  <Ionicons name="checkmark" size={18} color={primary} />
                ) : null}
              </Pressable>
            );
          }}
          ListEmptyComponent={
            <View className="items-center justify-center p-8">
              <Text className="text-muted-foreground text-[15px] text-center">
                {t('selectTenant.empty')}
              </Text>
            </View>
          }
        />
      ) : null}
    </SafeAreaView>
  );
}
