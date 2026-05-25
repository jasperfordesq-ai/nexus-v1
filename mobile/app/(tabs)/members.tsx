// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useState } from 'react';
import { FlatList, Pressable, RefreshControl, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Spinner } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { getMembers, type Member, type MemberListResponse } from '@/lib/api/members';
import { useDebounce } from '@/lib/hooks/useDebounce';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import MemberCard from '@/components/MemberCard';
import { SkeletonBox } from '@/components/ui/Skeleton';

function MemberCardSkeleton() {
  return (
    <View className="flex-row items-center px-4 py-3 bg-surface">
      <SkeletonBox width={48} height={48} borderRadius={24} />
      <View className="flex-1 ml-3 gap-1.5">
        <SkeletonBox width="60%" height={14} />
        <SkeletonBox width="40%" height={11} />
      </View>
    </View>
  );
}

function extractMembersPage(response: MemberListResponse) {
  const nextOffset = response.meta.offset + response.data.length;
  return {
    items: response.data,
    cursor: response.meta.has_more ? String(nextOffset) : null,
    hasMore: response.meta.has_more,
  };
}

export default function MembersScreen() {
  const { t } = useTranslation('members');
  const primary = usePrimaryColor();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 400);

  const fetchMembers = useCallback(
    (cursor: string | null) => {
      const offset = cursor ? Number(cursor) : 0;
      return getMembers(Number.isFinite(offset) ? offset : 0, debouncedSearch || undefined);
    },
    [debouncedSearch],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Member, MemberListResponse>(fetchMembers, extractMembersPage, [debouncedSearch]);

  return (
    <SafeAreaView className="flex-1 bg-background">
      <View className="px-4 pt-4 pb-2">
        <Text className="text-xl font-bold text-foreground">{t('title')}</Text>
      </View>

      {/* Search bar */}
      <View className="flex-row items-center bg-surface mx-4 mb-2 rounded-xl border border-border px-3">
        <Ionicons name="search-outline" size={18} className="text-muted-foreground mr-2" />
        <TextInput
          className="flex-1 py-2.5 text-base text-foreground"
          value={search}
          onChangeText={setSearch}
          placeholder={t('search.placeholder')}
          returnKeyType="search"
          clearButtonMode="while-editing"
          accessibilityLabel={t('search.placeholder')}
        />
      </View>

      <FlatList<Member>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => <MemberCard member={item} />}
        refreshControl={
          <RefreshControl
            refreshing={isLoading && items.length > 0}
            onRefresh={refresh}
            tintColor={primary}
            colors={[primary]}
          />
        }
        onEndReached={() => { if (hasMore) loadMore(); }}
        onEndReachedThreshold={0.3}
        ListEmptyComponent={
          isLoading ? (
            <>
              <MemberCardSkeleton />
              <MemberCardSkeleton />
              <MemberCardSkeleton />
              <MemberCardSkeleton />
              <MemberCardSkeleton />
            </>
          ) : error ? (
            <View className="flex-1 items-center justify-center p-8">
              <Text className="text-danger text-sm text-center mb-3">{error}</Text>
              <Pressable onPress={() => void refresh()} className="px-5 py-2.5">
                <Text className="font-semibold" style={{ color: primary }}>{t('common:buttons.retry')}</Text>
              </Pressable>
            </View>
          ) : (
            <View className="flex-1 items-center justify-center p-8">
              <Text className="text-muted-foreground text-sm text-center">{t('empty.title')}</Text>
              <Text className="text-muted-foreground text-[13px] text-center mt-1">{t('empty.subtitle')}</Text>
            </View>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View className="py-4 items-center"><Spinner size="sm" /></View>
          ) : !hasMore && items.length > 0 && !isLoading ? (
            <View className="py-4 items-center">
              <Text className="text-xs text-muted-foreground">{t('common:endOfList')}</Text>
            </View>
          ) : null
        }
        contentContainerStyle={{ paddingBottom: 24 }}
      />
    </SafeAreaView>
  );
}
