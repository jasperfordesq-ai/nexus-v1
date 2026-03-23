// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useState, useMemo } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  SafeAreaView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { getGroups, type Group, type GroupsResponse } from '@/lib/api/groups';
import { useDebounce } from '@/lib/hooks/useDebounce';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import OfflineBanner from '@/components/OfflineBanner';
import { SkeletonBox } from '@/components/ui/Skeleton';

type FilterValue = 'all' | 'public' | 'private';

/** Extractor for cursor-based GroupsResponse. */
function extractGroupPage(response: GroupsResponse) {
  return {
    items: response.data,
    cursor: response.meta.cursor,
    hasMore: response.meta.has_more,
  };
}

// ---------------------------------------------------------------------------
// GroupCardSkeleton
// ---------------------------------------------------------------------------

function GroupCardSkeleton({ theme }: { theme: Theme }) {
  return (
    <View style={{ backgroundColor: theme.surface, borderRadius: 14, padding: 14, marginHorizontal: 16, marginVertical: 6 }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 }}>
        <SkeletonBox width="55%" height={16} />
        <SkeletonBox width={48} height={16} />
      </View>
      <SkeletonBox width="100%" height={12} style={{ marginBottom: 4 }} />
      <SkeletonBox width="70%" height={12} style={{ marginBottom: 10 }} />
      <SkeletonBox width={80} height={12} />
    </View>
  );
}

// ---------------------------------------------------------------------------
// GroupCard
// ---------------------------------------------------------------------------

interface GroupCardProps {
  item: Group;
  primary: string;
  theme: Theme;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onPress: () => void;
}

function GroupCard({ item, primary, theme, t, onPress }: GroupCardProps) {
  return (
    <TouchableOpacity
      style={[
        {
          backgroundColor: theme.surface,
          borderRadius: 14,
          padding: 14,
          marginHorizontal: 16,
          marginVertical: 6,
          borderWidth: 1,
          borderColor: theme.border,
        },
      ]}
      onPress={onPress}
      activeOpacity={0.8}
      accessibilityRole="button"
      accessibilityLabel={item.name}
    >
      {/* Name row */}
      <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 6 }}>
        <Ionicons
          name={item.visibility === 'private' ? 'lock-closed-outline' : 'globe-outline'}
          size={14}
          color={theme.textMuted}
          style={{ marginRight: 5 }}
        />
        <Text
          style={{ flex: 1, fontSize: 16, fontWeight: '700', color: theme.text }}
          numberOfLines={1}
        >
          {item.name}
        </Text>
        {/* Badges */}
        <View style={{ flexDirection: 'row', gap: 6, marginLeft: 8 }}>
          {item.is_featured && (
            <View
              style={{
                backgroundColor: withAlpha(primary, 0.13),
                borderRadius: 6,
                paddingHorizontal: 7,
                paddingVertical: 2,
              }}
            >
              <Text style={{ fontSize: 11, fontWeight: '600', color: primary }}>
                {t('featured')}
              </Text>
            </View>
          )}
          {item.is_member && (
            <View
              style={{
                backgroundColor: theme.successBg,
                borderRadius: 6,
                paddingHorizontal: 7,
                paddingVertical: 2,
              }}
            >
              <Text style={{ fontSize: 11, fontWeight: '600', color: theme.success }}>
                {t('joined')}
              </Text>
            </View>
          )}
        </View>
      </View>

      {/* Description */}
      {item.description ? (
        <Text
          style={{ fontSize: 14, color: theme.textSecondary, lineHeight: 20, marginBottom: 8 }}
          numberOfLines={2}
        >
          {item.description}
        </Text>
      ) : null}

      {/* Meta: member count */}
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
        <Ionicons name="people-outline" size={13} color={theme.textMuted} />
        <Text style={{ fontSize: 13, color: theme.textMuted }}>
          {t('members', { count: item.member_count })}
        </Text>
      </View>
    </TouchableOpacity>
  );
}

// ---------------------------------------------------------------------------
// GroupsScreen
// ---------------------------------------------------------------------------

export default function GroupsScreen() {
  const { t } = useTranslation('groups');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 400);
  const [filter, setFilter] = useState<FilterValue>('all');

  const fetchGroups = useCallback(
    (cursor: string | null) => {
      const params: { search?: string; visibility?: string } = {};
      if (debouncedSearch) params.search = debouncedSearch;
      if (filter !== 'all') params.visibility = filter;
      return getGroups(cursor, params);
    },
    [debouncedSearch, filter],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Group, GroupsResponse>(fetchGroups, extractGroupPage, [debouncedSearch, filter]);

  const filterOptions: { value: FilterValue; label: string }[] = [
    { value: 'all', label: t('filter.all') },
    { value: 'public', label: t('filter.public') },
    { value: 'private', label: t('filter.private') },
  ];

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.title}>{t('title')}</Text>
        <TouchableOpacity
          style={[styles.newButton, { backgroundColor: primary }]}
          onPress={() => {
            void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            router.push('/(modals)/group-detail');
          }}
          activeOpacity={0.8}
          accessibilityLabel={t('newGroup')}
          accessibilityRole="button"
        >
          {/* #fff = contrast on primary */}
          <Ionicons name="add" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      {/* Search bar */}
      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={18} color={theme.textMuted} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          value={search}
          onChangeText={setSearch}
          placeholder={t('searchPlaceholder')}
          placeholderTextColor={theme.textMuted}
          returnKeyType="search"
          clearButtonMode="while-editing"
          accessibilityLabel={t('searchPlaceholder')}
        />
      </View>

      {/* Filter pills */}
      <View style={styles.filterRow}>
        {filterOptions.map((opt) => (
          <TouchableOpacity
            key={opt.value}
            style={[
              styles.filterPill,
              filter === opt.value
                ? { backgroundColor: primary, borderColor: primary }
                : { backgroundColor: theme.surface, borderColor: theme.border },
            ]}
            onPress={() => setFilter(opt.value)}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel={opt.label}
          >
            <Text
              style={[
                styles.filterPillText,
                // '#fff' = contrast on primary
                { color: filter === opt.value ? '#fff' : theme.textSecondary },
              ]}
            >
              {opt.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <OfflineBanner />

      <FlatList<Group>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <GroupCard
            item={item}
            primary={primary}
            theme={theme}
            t={t}
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push({ pathname: '/(modals)/group-detail', params: { id: String(item.id) } });
            }}
          />
        )}
        refreshControl={
          <RefreshControl refreshing={isLoading && items.length > 0} onRefresh={refresh} tintColor={primary} colors={[primary]} />
        }
        onEndReached={() => { if (hasMore) loadMore(); }}
        onEndReachedThreshold={0.3}
        ListEmptyComponent={
          isLoading ? (
            <>
              <GroupCardSkeleton theme={theme} />
              <GroupCardSkeleton theme={theme} />
              <GroupCardSkeleton theme={theme} />
            </>
          ) : error ? (
            <View style={styles.centered}>
              <Text style={styles.errorText}>{error}</Text>
              <TouchableOpacity onPress={() => void refresh()} style={styles.retryBtn}>
                <Text style={{ color: primary, fontWeight: '600', fontSize: 15 }}>
                  {t('common:buttons.retry')}
                </Text>
              </TouchableOpacity>
            </View>
          ) : (
            <View style={styles.centered}>
              <Text style={styles.emptyText}>{t('empty')}</Text>
            </View>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View style={styles.footer}>
              <ActivityIndicator size="small" color={theme.textMuted} />
            </View>
          ) : !hasMore && items.length > 0 && !isLoading ? (
            <View style={styles.footer}>
              <Text style={styles.endOfListText}>{t('common:endOfList')}</Text>
            </View>
          ) : null
        }
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    header: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      paddingHorizontal: 16,
      paddingTop: 16,
      paddingBottom: 8,
    },
    title: { fontSize: 22, fontWeight: '700', color: theme.text },
    newButton: {
      width: 36,
      height: 36,
      borderRadius: 18,
      justifyContent: 'center',
      alignItems: 'center',
    },
    searchContainer: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      marginHorizontal: 16,
      marginBottom: 8,
      borderRadius: 10,
      borderWidth: 1,
      borderColor: theme.border,
      paddingHorizontal: 12,
    },
    searchIcon: { marginRight: 8 },
    searchInput: { flex: 1, paddingVertical: 10, fontSize: 15, color: theme.text },
    filterRow: {
      flexDirection: 'row',
      gap: 8,
      paddingHorizontal: 16,
      marginBottom: 8,
    },
    filterPill: {
      borderRadius: 20,
      borderWidth: 1,
      paddingHorizontal: 14,
      paddingVertical: 6,
    },
    filterPillText: { fontSize: 13, fontWeight: '600' },
    list: { paddingBottom: 24 },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 },
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
    emptyText: { color: theme.textMuted, fontSize: 14, textAlign: 'center' },
    footer: { paddingVertical: 16, alignItems: 'center' },
    endOfListText: { fontSize: 13, color: theme.textMuted },
  });
}
