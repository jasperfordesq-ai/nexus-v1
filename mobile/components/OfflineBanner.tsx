// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { StyleSheet, Text, View } from 'react-native';
import { useNetworkStatus } from '@/lib/hooks/useNetworkStatus';

/**
 * Renders a sticky yellow warning banner when the device has no connectivity.
 *
 * Place this component at the top of a screen's layout (inside SafeAreaView,
 * before the main content) — it renders nothing when the user is online.
 *
 * Example:
 *   <SafeAreaView style={styles.container}>
 *     <OfflineBanner />
 *     <FlatList ... />
 *   </SafeAreaView>
 */
export default function OfflineBanner() {
  const { isOnline } = useNetworkStatus();

  if (isOnline) return null;

  return (
    <View style={styles.banner}>
      <Text style={styles.text}>⚠ No internet connection</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    backgroundColor: '#FEF3C7',
    borderBottomWidth: 1,
    borderBottomColor: '#F59E0B',
    paddingVertical: 8,
    paddingHorizontal: 16,
    alignItems: 'center',
    zIndex: 100,
  },
  text: {
    color: '#92400E',
    fontSize: 13,
    fontWeight: '600',
  },
});
