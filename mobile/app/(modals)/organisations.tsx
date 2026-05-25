// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import {
  FlatList,
  View,
  Text,
  TextInput,
  TouchableOpacity,
  RefreshControl,
  StyleSheet,
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
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
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
  theme,
  styles,
  t,
  onPress,
}: {
  item: Organisation;
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onPress: () => void;
}) {
  return (
    <TouchableOpacity style={styles.card} onPress={onPress} activeOpacity={0.75}>
      <View style={styles.cardHeader}>
        <Avatar uri={item.logo} name={item.name} size={46} />
        <View style={styles.cardHeaderContent}>
          <View style={styles.nameRow}>
            <Text style={styles.cardName} numberOfLines={1}>{item.name}</Text>
            {item.verified ? (
              <View style={[styles.verifiedBadge, { backgroundColor: withAlpha(primary, 0.10) }]}>
                <Ionicons name="checkmark-circle" size={12} color={primary} />
                <Text style={[styles.verifiedText, { color: primary }]}>{t('verified')}</Text>
              </View>
            ) : null}
          </View>
          {item.location ? (
            <View style={styles.locationRow}>
              <Ionicons name="location-outline" size={13} color={theme.textMuted} />
              <Text style={styles.locationText} numberOfLines={1}>{item.location}</Text>
            </View>
          ) : null}
        </View>
        <Ionicons name="chevron-forward" size={18} color={theme.textMuted} />
      </View>

      {/* Counts */}
      <View style={styles.countsRow}>
        <View style={styles.countItem}>
          <Ionicons name="people-outline" size={13} color={theme.textSecondary} />
          <Text style={styles.countText}>
            {t('members', { count: item.members_count })}
          </Text>
        </View>
        <View style={styles.countDot} />
        <View style={styles.countItem}>
          <Ionicons name="list-outline" size={13} color={theme.textSecondary} />
          <Text style={styles.countText}>
            {t('listings', { count: item.listings_count })}
          </Text>
        </View>
      </View>
    </TouchableOpacity>
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
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
        theme={theme}
        styles={styles}
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
    [primary, theme, styles, t],
  );

  return (
    <ModalErrorBoundary>
    <SafeAreaView style={styles.container} edges={['bottom']}>
      {/* Search bar */}
      <View style={styles.searchBar}>
        <Ionicons name="search-outline" size={18} color={theme.textMuted} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
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
          <TouchableOpacity onPress={handleClear} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }} accessibilityLabel={t('common:actions.clear', 'Clear search')} accessibilityRole="button">
            <Ionicons name="close-circle" size={18} color={theme.textMuted} />
          </TouchableOpacity>
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
            <View style={styles.centered}>
              <Text style={styles.errorText}>{error}</Text>
              <TouchableOpacity onPress={refresh} style={styles.retryButton}>
                <Text style={[styles.retryText, { color: primary }]}>{t('common:actions.retry', 'Retry')}</Text>
              </TouchableOpacity>
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
            <View style={styles.footerLoader}>
              <LoadingSpinner />
            </View>
          ) : null
        }
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    searchBar: {
      flexDirection: 'row',
      alignItems: 'center',
      marginHorizontal: SPACING.md,
      marginVertical: SPACING.sm + 4,
      paddingHorizontal: SPACING.sm + 4,
      height: 42,
      backgroundColor: theme.surface,
      borderRadius: RADIUS.md,
      gap: SPACING.sm,
    },
    searchIcon: { flexShrink: 0 },
    searchInput: {
      flex: 1,
      ...TYPOGRAPHY.body,
      color: theme.text,
      paddingVertical: 0,
    },
    list: { flexGrow: 1, paddingHorizontal: SPACING.md, paddingBottom: SPACING.xl },
    card: {
      backgroundColor: theme.surface,
      borderRadius: RADIUS.lg,
      padding: RADIUS.lg,
      marginBottom: SPACING.sm + 4,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      gap: SPACING.sm + 2,
    },
    cardHeader: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.sm + 4,
    },
    cardHeaderContent: {
      flex: 1,
      gap: SPACING.xs,
    },
    nameRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.sm - 2,
      flexWrap: 'wrap',
    },
    cardName: {
      ...TYPOGRAPHY.body,
      fontWeight: '600',
      color: theme.text,
      flexShrink: 1,
    },
    verifiedBadge: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 3,
      borderRadius: RADIUS.sm,
      paddingHorizontal: SPACING.sm - 2,
      paddingVertical: SPACING.xxs,
    },
    verifiedText: {
      fontSize: 11,
      fontWeight: '600',
    },
    locationRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.xs,
    },
    locationText: {
      ...TYPOGRAPHY.bodySmall,
      color: theme.textMuted,
      flex: 1,
    },
    countsRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.sm,
    },
    countItem: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.xs,
    },
    countText: {
      ...TYPOGRAPHY.caption,
      color: theme.textSecondary,
    },
    countDot: {
      width: 3,
      height: 3,
      borderRadius: 1.5,
      backgroundColor: theme.textMuted,
    },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
    errorText: { color: theme.error, ...TYPOGRAPHY.label, textAlign: 'center' },
    retryButton: { marginTop: SPACING.sm + 4 },
    retryText: { ...TYPOGRAPHY.button },
    footerLoader: { paddingVertical: SPACING.md },
  });
}
