// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  SafeAreaView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import { getMembers, type Member, type MemberListResponse } from '@/lib/api/members';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import MemberCard from '@/components/MemberCard';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

/** Extractor for offset-based MemberListResponse — encodes next offset as cursor string. */
function extractMembersPage(response: MemberListResponse) {
  const nextOffset = response.meta.offset + response.meta.per_page;
  return {
    items: response.data,
    cursor: response.meta.has_more ? String(nextOffset) : null,
    hasMore: response.meta.has_more,
  };
}

export default function MembersScreen() {
  const theme = useTheme();
  const styles = makeStyles(theme);
  const [search, setSearch] = useState('');

  const fetchMembers = useCallback(
    (cursor: string | null) => getMembers(cursor ? Number(cursor) : 0, search || undefined),
    [search],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Member, MemberListResponse>(fetchMembers, extractMembersPage);

  // Re-fetch from the start whenever the search term changes.
  const isFirstRender = useRef(true);
  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }
    refresh();
    // refresh is stable relative to search changes; we want this to fire on search change only.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Members</Text>
      </View>

      {/* Search bar */}
      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={18} color={theme.textMuted} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          value={search}
          onChangeText={setSearch}
          placeholder="Search members…"
          placeholderTextColor={theme.textMuted}
          returnKeyType="search"
          clearButtonMode="while-editing"
        />
      </View>

      <FlatList<Member>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => <MemberCard member={item} />}
        refreshControl={
          <RefreshControl refreshing={isLoading && items.length > 0} onRefresh={refresh} />
        }
        onEndReached={() => { if (hasMore) loadMore(); }}
        onEndReachedThreshold={0.3}
        ListEmptyComponent={
          isLoading ? (
            <LoadingSpinner />
          ) : error ? (
            <View style={styles.centered}>
              <Text style={styles.errorText}>{error}</Text>
            </View>
          ) : (
            <View style={styles.centered}>
              <Text style={styles.emptyText}>No members found.</Text>
            </View>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View style={styles.footer}>
              <ActivityIndicator size="small" color={theme.textMuted} />
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
      paddingHorizontal: 16,
      paddingTop: 16,
      paddingBottom: 8,
    },
    title: { fontSize: 22, fontWeight: '700', color: theme.text },
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
    list: { paddingBottom: 24 },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 },
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center' },
    emptyText: { color: theme.textMuted, fontSize: 14, textAlign: 'center' },
    footer: { paddingVertical: 16, alignItems: 'center' },
  });
}
