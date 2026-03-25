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
  getOpportunities,
  type VolunteerOpportunity,
  type VolunteeringResponse,
} from '@/lib/api/volunteering';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

// ---------------------------------------------------------------------------
// Inline card component
// ---------------------------------------------------------------------------

function OpportunityCard({
  item,
  primary,
  theme,
  styles,
  t,
  onPress,
}: {
  item: VolunteerOpportunity;
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onPress: () => void;
}) {
  const statusColor =
    item.status === 'open'
      ? theme.success
      : item.status === 'filled'
        ? theme.warning
        : theme.textMuted;

  const deadlineStr = item.deadline
    ? t('deadline', { date: new Date(item.deadline).toLocaleDateString('default', { month: 'short', day: 'numeric', year: 'numeric' }) })
    : null;

  const visibleSkills = (item.skills_needed ?? []).slice(0, 3);

  return (
    <TouchableOpacity style={styles.card} onPress={onPress} activeOpacity={0.75} accessibilityRole="button" accessibilityLabel={item.title}>
      {/* Title row */}
      <View style={styles.cardTitleRow}>
        <Text style={styles.cardTitle} numberOfLines={2}>{item.title}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor + '22' }]}>
          <Text style={[styles.statusText, { color: statusColor }]}>
            {t(`status.${item.status}`)}
          </Text>
        </View>
      </View>

      {/* Organisation */}
      {item.organisation ? (
        <Text style={styles.cardOrg} numberOfLines={1}>{item.organisation.name}</Text>
      ) : null}

      {/* Meta row: location / remote, hours */}
      <View style={styles.cardMeta}>
        {item.is_remote ? (
          <View style={[styles.remoteBadge, { backgroundColor: withAlpha(primary, 0.10) }]}>
            <Text style={[styles.remoteBadgeText, { color: primary }]}>{t('remote')}</Text>
          </View>
        ) : item.location ? (
          <View style={styles.metaItem}>
            <Ionicons name="location-outline" size={13} color={theme.textMuted} />
            <Text style={styles.metaText} numberOfLines={1}>{item.location}</Text>
          </View>
        ) : null}

        {item.hours_per_week !== null ? (
          <View style={styles.metaItem}>
            <Ionicons name="time-outline" size={13} color={theme.textMuted} />
            <Text style={styles.metaText}>{t('hoursPerWeek', { hours: item.hours_per_week })}</Text>
          </View>
        ) : null}

        {deadlineStr ? (
          <View style={styles.metaItem}>
            <Ionicons name="calendar-outline" size={13} color={theme.textMuted} />
            <Text style={styles.metaText}>{deadlineStr}</Text>
          </View>
        ) : null}
      </View>

      {/* Skills */}
      {visibleSkills.length > 0 ? (
        <View style={styles.skillsRow}>
          {visibleSkills.map((skill) => (
            <View key={skill} style={[styles.skillPill, { backgroundColor: theme.bg }]}>
              <Text style={styles.skillText}>{skill}</Text>
            </View>
          ))}
          {(item.skills_needed ?? []).length > 3 ? (
            <View style={[styles.skillPill, { backgroundColor: theme.bg }]}>
              <Text style={styles.skillText}>+{item.skills_needed.length - 3}</Text>
            </View>
          ) : null}
        </View>
      ) : null}
    </TouchableOpacity>
  );
}

// ---------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------

export default function VolunteeringScreen() {
  const { t } = useTranslation('volunteering');
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
    (cursor: string | null) => getOpportunities(cursor, committedSearch || undefined),
    [committedSearch],
  );

  const extractor = useCallback(
    (response: VolunteeringResponse) => ({
      items: response.data,
      cursor: response.meta.cursor,
      hasMore: response.meta.has_more,
    }),
    [],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<VolunteerOpportunity, VolunteeringResponse>(fetchFn, extractor, [committedSearch]);

  const renderItem = useCallback(
    ({ item }: { item: VolunteerOpportunity }) => (
      <OpportunityCard
        item={item}
        primary={primary}
        theme={theme}
        styles={styles}
        t={t}
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          router.push({
            pathname: '/(modals)/volunteering-detail',
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

      <FlatList<VolunteerOpportunity>
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
              icon="heart-outline"
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
      gap: SPACING.sm,
    },
    cardTitleRow: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      gap: SPACING.sm,
    },
    cardTitle: {
      flex: 1,
      ...TYPOGRAPHY.body,
      fontWeight: '600',
      color: theme.text,
    },
    statusBadge: {
      borderRadius: RADIUS.sm,
      paddingHorizontal: SPACING.sm,
      paddingVertical: 3,
      alignSelf: 'flex-start',
    },
    statusText: {
      fontSize: 11,
      fontWeight: '600',
    },
    cardOrg: {
      ...TYPOGRAPHY.bodySmall,
      color: theme.textSecondary,
    },
    cardMeta: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: SPACING.sm,
      alignItems: 'center',
    },
    remoteBadge: {
      borderRadius: RADIUS.sm,
      paddingHorizontal: SPACING.sm,
      paddingVertical: 3,
    },
    remoteBadgeText: {
      fontSize: 11,
      fontWeight: '600',
    },
    metaItem: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.xs,
    },
    metaText: {
      ...TYPOGRAPHY.caption,
      color: theme.textMuted,
    },
    skillsRow: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: SPACING.sm - 2,
    },
    skillPill: {
      borderRadius: RADIUS.sm,
      paddingHorizontal: SPACING.sm,
      paddingVertical: 3,
      borderWidth: 1,
      borderColor: theme.border,
    },
    skillText: {
      fontSize: 11,
      color: theme.textSecondary,
    },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
    errorText: { color: theme.error, ...TYPOGRAPHY.label, textAlign: 'center' },
    retryButton: { marginTop: SPACING.sm + 4 },
    retryText: { ...TYPOGRAPHY.button },
    footerLoader: { paddingVertical: SPACING.md },
  });
}
