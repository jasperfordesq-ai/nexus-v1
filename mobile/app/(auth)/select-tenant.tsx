// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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

import { listTenants, type TenantListItem } from '@/lib/api/tenant';
import { useApi } from '@/lib/hooks/useApi';
import { useTenant } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';

/**
 * Tenant picker — shown before login when the user needs to select
 * which timebank community they belong to.
 *
 * Route: /(auth)/select-tenant
 * Navigated to from: login screen "Not your timebank?" link
 */
export default function SelectTenantScreen() {
  const { setTenantSlug, tenantSlug } = useTenant();
  const { data, isLoading, error } = useApi(() => listTenants());
  const theme = useTheme();
  const styles = makeStyles(theme);

  const tenants = data?.data ?? [];

  async function handleSelect(tenant: TenantListItem) {
    await setTenantSlug(tenant.slug);
    router.back();
  }

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Select your timebank</Text>
        <Text style={styles.subtitle}>Choose the community you belong to</Text>
      </View>

      {isLoading && (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color="#006FEE" />
        </View>
      )}

      {error && (
        <View style={styles.centered}>
          <Text style={styles.errorText}>{error}</Text>
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
        ItemSeparatorComponent={() => <View style={styles.separator} />}
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.surface },
    header: { padding: 24, paddingBottom: 8 },
    title: { fontSize: 22, fontWeight: '700', color: theme.text },
    subtitle: { fontSize: 14, color: theme.textSecondary, marginTop: 4 },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 },
    errorText: { color: theme.error, fontSize: 14 },
    list: { paddingHorizontal: 16 },
    tenantRow: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingVertical: 14,
      gap: 12,
    },
    tenantRowActive: { backgroundColor: '#EFF6FF', borderRadius: 10, paddingHorizontal: 8 },
    logo: { width: 40, height: 40, borderRadius: 8 },
    logoPlaceholder: {
      width: 40,
      height: 40,
      borderRadius: 8,
      backgroundColor: '#006FEE',
      justifyContent: 'center',
      alignItems: 'center',
    },
    logoInitial: { color: '#fff', fontWeight: '700', fontSize: 18 },
    tenantName: { flex: 1, fontSize: 16, fontWeight: '500', color: theme.text },
    checkmark: { color: '#006FEE', fontSize: 18, fontWeight: '700' },
    separator: { height: 1, backgroundColor: theme.borderSubtle },
  });
}
