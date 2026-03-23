// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import {
  FlatList,
  View,
  Text,
  TextInput,
  TouchableOpacity,
  RefreshControl,
  StyleSheet,
  SafeAreaView,
} from 'react-native';
import { router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';

import { useTranslation } from 'react-i18next';

import { getMembers, type Member, type MemberListResponse } from '@/lib/api/members';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { SkeletonBox } from '@/components/ui/Skeleton';

export default function MembersScreen() {
  const { t } = useTranslation('members');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);
  const Separator = useCallback(() => <View style={styles.separator} />, [styles]);
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
    usePaginatedApi<Member, MemberListResponse>(fetchFn, extractor);

  function renderItem({ item }: { item: Member }) {
    return (
      <TouchableOpacity
        style={styles.row}
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          router.push({
            pathname: '/(modals)/member-profile',
            params: { id: item.id, name: item.name },
          });
        }}
        activeOpacity={0.7}
        accessibilityRole="button"
        accessibilityLabel={t('memberCard.accessibilityLabel', { name: item.name })}
      >
        <Avatar uri={item.avatar_url} name={item.name} size={46} />
        <View style={styles.rowContent}>
          <Text style={styles.memberName} numberOfLines={1}>{item.name}</Text>
          {item.tagline ? (
            <Text style={styles.tagline} numberOfLines={1}>{item.tagline}</Text>
          ) : null}
        </View>
        <Ionicons name="chevron-forward" size={18} color={theme.textMuted} />
      </TouchableOpacity>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      {/* Search bar */}
      <View style={styles.searchBar}>
        <Ionicons name="search-outline" size={18} color={theme.textMuted} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
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
          <TouchableOpacity onPress={handleClear} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
            <Ionicons name="close-circle" size={18} color={theme.textMuted} />
          </TouchableOpacity>
        )}
      </View>

      <FlatList<Member>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderItem}
        ItemSeparatorComponent={Separator}
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
            <View style={styles.centered}>
              <Text style={styles.errorText}>{error}</Text>
            </View>
          ) : (
            <View style={styles.centered}>
              <Ionicons name="people-outline" size={40} color={theme.textMuted} />
              <Text style={styles.emptyText}>
                {committedSearch
                  ? t('empty.noResults', { query: committedSearch })
                  : t('empty.title')}
              </Text>
            </View>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View style={styles.footerLoader}>
              <LoadingSpinner />
            </View>
          ) : null
        }
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
  );
}

// ---------------------------------------------------------------------------
// Skeleton
// ---------------------------------------------------------------------------

function MemberRowSkeleton(): React.JSX.Element {
  const theme = useTheme();
  return (
    <View style={skeletonRowStyle}>
      <SkeletonBox width={46} height={46} borderRadius={23} />
      <View style={{ flex: 1, gap: 8 }}>
        <SkeletonBox width="55%" height={13} />
        <SkeletonBox width="35%" height={11} />
      </View>
    </View>
  );
}

const skeletonRowStyle = {
  flexDirection: 'row' as const,
  alignItems: 'center' as const,
  paddingHorizontal: 16,
  paddingVertical: 12,
  gap: 12,
};

function MemberListSkeleton(): React.JSX.Element {
  return (
    <>
      {Array.from({ length: 8 }).map((_, i) => (
        <MemberRowSkeleton key={i} />
      ))}
    </>
  );
}

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.surface },
    searchBar: {
      flexDirection: 'row',
      alignItems: 'center',
      marginHorizontal: 16,
      marginVertical: 12,
      paddingHorizontal: 12,
      height: 42,
      backgroundColor: theme.bg,
      borderRadius: 10,
      gap: 8,
    },
    searchIcon: { flexShrink: 0 },
    searchInput: {
      flex: 1,
      fontSize: 15,
      color: theme.text,
      paddingVertical: 0,
    },
    list: { flexGrow: 1 },
    row: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingHorizontal: 16,
      paddingVertical: 12,
      gap: 12,
    },
    rowContent: { flex: 1 },
    memberName: { fontSize: 15, fontWeight: '600', color: theme.text },
    tagline: { fontSize: 13, color: theme.textSecondary, marginTop: 2 },
    separator: { height: 1, backgroundColor: theme.bg, marginLeft: 74 },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center' },
    emptyText: { color: theme.textSecondary, fontSize: 15, textAlign: 'center', marginTop: 12 },
    footerLoader: { paddingVertical: 16 },
  });
}
