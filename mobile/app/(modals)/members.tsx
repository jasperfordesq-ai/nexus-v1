// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useState, useEffect, useCallback, useRef } from 'react';
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
import * as Haptics from '@/lib/haptics';

import { useTranslation } from 'react-i18next';

import { getMembers, type Member, type MemberListResponse } from '@/lib/api/members';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { SkeletonBox } from '@/components/ui/Skeleton';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

export default function MembersScreen() {
  const { t } = useTranslation('members');
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
    }, 300);
  }

  function handleClear() {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    setSearch('');
    setCommittedSearch('');
  }

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const fetchFn = useCallback(
    (cursor: string | null) => {
      const offset = cursor ? Number(cursor) : 0;
      return getMembers(offset, committedSearch || undefined);
    },
    [committedSearch],
  );

  const extractor = useCallback(
    (response: MemberListResponse) => {
      const { has_more, offset, per_page } = response.meta;
      return {
        items: response.data,
        cursor: has_more ? String(offset + per_page) : null,
        hasMore: has_more,
      };
    },
    [],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Member, MemberListResponse>(fetchFn, extractor, [committedSearch]);

  function renderItem({ item }: { item: Member }) {
    return (
      <Pressable
        className="flex-row items-center px-4 py-3 gap-3"
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          router.push({
            pathname: '/(modals)/member-profile',
            params: { id: String(item.id), name: item.name },
          });
        }}
        accessibilityRole="button"
        accessibilityLabel={t('memberCard.accessibilityLabel', { name: item.name })}
      >
        <Avatar uri={item.avatar_url} name={item.name} size={46} />
        <View className="flex-1">
          <Text className="text-[15px] font-semibold text-foreground" numberOfLines={1}>{item.name}</Text>
          {item.tagline ? (
            <Text className="text-[13px] text-muted-foreground mt-0.5" numberOfLines={1}>{item.tagline}</Text>
          ) : null}
        </View>
        <Ionicons name="chevron-forward" size={18} color={theme.textMuted} />
      </Pressable>
    );
  }

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-surface">
      {/* Search bar */}
      <View className="flex-row items-center mx-4 my-3 px-3 h-[42px] bg-background rounded-[10px] gap-2">
        <Ionicons name="search-outline" size={18} color={theme.textMuted} style={{ flexShrink: 0 }} />
        <TextInput
          className="flex-1 text-[15px] text-foreground py-0"
          placeholder={t('search.placeholder')}
          placeholderTextColor={theme.textMuted}
          value={search}
          onChangeText={handleSearchChange}
          returnKeyType="search"
          clearButtonMode="never"
          autoCorrect={false}
          autoCapitalize="none"
          accessibilityLabel={t('search.placeholder')}
        />
        {search.length > 0 && (
          <Pressable onPress={handleClear} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }} accessibilityLabel={t('common:actions.clear', 'Clear search')} accessibilityRole="button">
            <Ionicons name="close-circle" size={18} color={theme.textMuted} />
          </Pressable>
        )}
      </View>

      <FlatList<Member>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderItem}
        ItemSeparatorComponent={() => <View className="h-px bg-background ml-[74px]" />}
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
            <MemberListSkeleton />
          ) : error ? (
            <View className="flex-1 justify-center items-center p-10">
              <Text className="text-[14px] text-danger text-center">{error}</Text>
            </View>
          ) : (
            <View className="flex-1 justify-center items-center p-10">
              <Ionicons name="people-outline" size={40} color={theme.textMuted} />
              <Text className="text-[15px] text-muted-foreground text-center mt-3">
                {committedSearch
                  ? t('empty.noResults', { query: committedSearch })
                  : t('empty.title')}
              </Text>
            </View>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View className="py-4">
              <LoadingSpinner />
            </View>
          ) : null
        }
        contentContainerStyle={{ flexGrow: 1 }}
      />
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}

// ---------------------------------------------------------------------------
// Skeleton
// ---------------------------------------------------------------------------

function MemberRowSkeleton(): React.JSX.Element {
  return (
    <View className="flex-row items-center px-4 py-3 gap-3">
      <SkeletonBox width={46} height={46} borderRadius={23} />
      <View style={{ flex: 1, gap: 8 }}>
        <SkeletonBox width="55%" height={13} />
        <SkeletonBox width="35%" height={11} />
      </View>
    </View>
  );
}

function MemberListSkeleton(): React.JSX.Element {
  return (
    <>
      {Array.from({ length: 8 }).map((_, i) => (
        <MemberRowSkeleton key={i} />
      ))}
    </>
  );
}
