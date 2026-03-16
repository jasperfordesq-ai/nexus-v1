// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  Image,
  ActivityIndicator,
  StyleSheet,
  SafeAreaView,
} from 'react-native';
import { router } from 'expo-router';

import { useTranslation } from 'react-i18next';

import { listTenants, type TenantListItem } from '@/lib/api/tenant';
import { useApi } from '@/lib/hooks/useApi';
import { useTenant, usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';

/**
 * Tenant picker — shown before login when the user needs to select
 * which timebank community they belong to.
 *
 * Route: /(auth)/select-tenant
 * Navigated to from: login screen "Not your timebank?" link
 */
export default function SelectTenantScreen() {
  const { t } = useTranslation('auth');
  const { setTenantSlug, tenantSlug } = useTenant();
  const primary = usePrimaryColor();
  const { data, isLoading, error, refresh } = useApi(() => listTenants());
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme, primary), [theme, primary]);
  const Separator = useCallback(() => <View style={styles.separator} />, [styles]);

  const tenants = data?.data ?? [];

  async function handleSelect(tenant: TenantListItem) {
    await setTenantSlug(tenant.slug);
    router.back();
  }

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>{t('selectTenant.title')}</Text>
        <Text style={styles.subtitle}>{t('selectTenant.subtitle')}</Text>
      </View>

      {isLoading && (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={primary} />
        </View>
      )}

      {error && (
        <View style={styles.centered}>
          <Text style={styles.errorText}>{error}</Text>
          <TouchableOpacity onPress={() => void refresh()} style={{ marginTop: 12, paddingHorizontal: 20, paddingVertical: 10 }}>
            <Text style={{ color: primary, fontWeight: '600', fontSize: 15 }}>{t('common:buttons.retry')}</Text>
          </TouchableOpacity>
        </View>
      )}

      <FlatList<TenantListItem>
        data={tenants}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <TouchableOpacity
            style={[
              styles.tenantRow,
              item.slug === tenantSlug && styles.tenantRowActive,
            ]}
            onPress={() => void handleSelect(item)}
            activeOpacity={0.7}
          >
            {item.logo_url ? (
              <Image source={{ uri: item.logo_url }} style={styles.logo} resizeMode="contain" />
            ) : (
              <View style={styles.logoPlaceholder}>
                <Text style={styles.logoInitial}>{item.name.charAt(0).toUpperCase()}</Text>
              </View>
            )}
            <Text style={styles.tenantName}>{item.name}</Text>
            {item.slug === tenantSlug && (
              <Text style={styles.checkmark}>✓</Text>
            )}
          </TouchableOpacity>
        )}
        ItemSeparatorComponent={Separator}
        ListEmptyComponent={
          !isLoading && !error ? (
            <View style={styles.centered}>
              <Text style={styles.emptyText}>{t('selectTenant.empty')}</Text>
            </View>
          ) : null
        }
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme, primary: string) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.surface },
    header: { padding: 24, paddingBottom: 8 },
    title: { fontSize: 22, fontWeight: '700', color: theme.text },
    subtitle: { fontSize: 14, color: theme.textSecondary, marginTop: 4 },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 },
    errorText: { color: theme.error, fontSize: 14 },
    emptyText: { color: theme.textSecondary, fontSize: 15, textAlign: 'center' },
    list: { paddingHorizontal: 16 },
    tenantRow: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingVertical: 14,
      gap: 12,
    },
    tenantRowActive: { backgroundColor: theme.infoBg, borderRadius: 10, paddingHorizontal: 8 },
    logo: { width: 40, height: 40, borderRadius: 8 },
    logoPlaceholder: {
      width: 40,
      height: 40,
      borderRadius: 8,
      backgroundColor: primary,
      justifyContent: 'center',
      alignItems: 'center',
    },
    logoInitial: { color: '#fff', fontWeight: '700', fontSize: 18 },
    tenantName: { flex: 1, fontSize: 16, fontWeight: '500', color: theme.text },
    checkmark: { color: primary, fontSize: 18, fontWeight: '700' },
    separator: { height: 1, backgroundColor: theme.borderSubtle },
  });
}
