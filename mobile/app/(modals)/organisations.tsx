// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  FlatList,
  View,
  Text,
  TextInput,
  Pressable,
  RefreshControl,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import {
  getOrganisations,
  type Organisation,
  type OrganisationsResponse,
} from '@/lib/api/organisations';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

// ---------------------------------------------------------------------------
// Inline card component
// ---------------------------------------------------------------------------

function OrganisationCard({
  item,
  primary,
  textMuted,
  textSecondary,
  t,
  onPress,
}: {
  item: Organisation;
  primary: string;
  textMuted: string;
  textSecondary: string;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onPress: () => void;
}) {
  return (
    <Pressable
      className="bg-surface rounded-xl p-4 mb-[14px] border border-border/50 gap-[10px]"
      onPress={onPress}
    >
      {/* Card header */}
      <View className="flex-row items-center gap-[14px]">
        <Avatar uri={item.logo} name={item.name} size={46} />
        <View className="flex-1 gap-1">
          <View className="flex-row items-center gap-[6px] flex-wrap">
            <Text className="text-sm font-semibold text-foreground flex-shrink" numberOfLines={1}>{item.name}</Text>
            {item.verified ? (
              <View
                className="flex-row items-center gap-[3px] rounded px-[6px] py-[2px]"
                style={{ backgroundColor: withAlpha(primary, 0.10) }}
              >
                <Ionicons name="checkmark-circle" size={12} color={primary} />
                <Text className="text-[11px] font-semibold" style={{ color: primary }}>{t('verified')}</Text>
              </View>
            ) : null}
          </View>
          {item.location ? (
            <View className="flex-row items-center gap-1">
              <Ionicons name="location-outline" size={13} color={textMuted} />
              <Text className="text-xs text-muted-foreground flex-1" numberOfLines={1}>{item.location}</Text>
            </View>
          ) : null}
        </View>
        <Ionicons name="chevron-forward" size={18} color={textMuted} />
      </View>

      {/* Counts */}
      <View className="flex-row items-center gap-2">
        <View className="flex-row items-center gap-1">
          <Ionicons name="people-outline" size={13} color={textSecondary} />
          <Text className="text-xs text-muted-foreground">
            {t('members', { count: item.members_count })}
          </Text>
        </View>
        <View className="w-[3px] h-[3px] rounded-full bg-muted-foreground" />
        <View className="flex-row items-center gap-1">
          <Ionicons name="list-outline" size={13} color={textSecondary} />
          <Text className="text-xs text-muted-foreground">
            {t('listings', { count: item.listings_count })}
          </Text>
        </View>
      </View>
    </Pressable>
  );
}

// ---------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------

export default function OrganisationsScreen() {
  const { t } = useTranslation('organisations');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);

  const [search, setSearch] = useState('');
  const [committedSearch, setCommittedSearch] = useState('');
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

  // Clean up debounce timer on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const fetchFn = useCallback(
    (cursor: string | null) => getOrganisations(cursor, committedSearch || undefined),
    [committedSearch],
  );

  const extractor = useCallback(
    (response: OrganisationsResponse) => ({
      items: response.data,
      cursor: response.meta.cursor,
      hasMore: response.meta.has_more,
    }),
    [],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Organisation, OrganisationsResponse>(fetchFn, extractor, [committedSearch]);

  const renderItem = useCallback(
    ({ item }: { item: Organisation }) => (
      <OrganisationCard
        item={item}
        primary={primary}
        textMuted={theme.textMuted}
        textSecondary={theme.textSecondary}
        t={t}
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          router.push({
            pathname: '/(modals)/organisation-detail',
            params: { id: String(item.id) },
          });
        }}
      />
    ),
    [primary, theme.textMuted, theme.textSecondary, t],
  );

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      {/* Search bar */}
      <View className="flex-row items-center mx-4 my-[12px] px-[12px] h-[42px] bg-surface rounded-lg gap-2">
        <Ionicons name="search-outline" size={18} color={theme.textMuted} style={{ flexShrink: 0 }} />
        <TextInput
          className="flex-1 text-sm text-foreground py-0"
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
        {search.length > 0 && (
          <Pressable onPress={handleClear} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }} accessibilityLabel={t('common:actions.clear', 'Clear search')} accessibilityRole="button">
            <Ionicons name="close-circle" size={18} color={theme.textMuted} />
          </Pressable>
        )}
      </View>

      <FlatList<Organisation>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderItem}
        onEndReached={hasMore ? loadMore : undefined}
        onEndReachedThreshold={0.3}
        refreshControl={
          <RefreshControl
            refreshing={isLoading && items.length > 0}
            onRefresh={refresh}
            tintColor={primary}
          />
        }
        ListEmptyComponent={
          isLoading ? (
            <LoadingSpinner />
          ) : error ? (
            <View className="flex-1 justify-center items-center p-10">
              <Text className="text-sm font-medium text-danger text-center">{error}</Text>
              <Pressable onPress={refresh} className="mt-[14px]">
                <Text className="font-semibold" style={{ color: primary }}>{t('common:actions.retry', 'Retry')}</Text>
              </Pressable>
            </View>
          ) : (
            <EmptyState
              icon="business-outline"
              title={t('empty')}
            />
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View className="py-4">
              <LoadingSpinner />
            </View>
          ) : null
        }
        contentContainerStyle={{ flexGrow: 1, paddingHorizontal: 16, paddingBottom: 32 }}
      />
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}
