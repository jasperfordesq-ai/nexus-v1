// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useNetworkStatus } from '@/lib/hooks/useNetworkStatus';
import { useTheme } from '@/lib/hooks/useTheme';

/**
 * Renders a sticky warning banner when the device has no connectivity.
 * Respects light/dark theme.
 */
export default function OfflineBanner() {
  const { t } = useTranslation('common');
  const { isOnline } = useNetworkStatus();
  const theme = useTheme();

  if (isOnline) return null;

  return (
    <View style={[styles.banner, { backgroundColor: theme.warning + '18', borderBottomColor: theme.warning }]}>
      <Text style={[styles.text, { color: theme.warning }]}>{t('offline')}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    borderBottomWidth: 1,
    paddingVertical: 8,
    paddingHorizontal: 16,
    alignItems: 'center',
    zIndex: 100,
  },
  text: {
    fontSize: 13,
    fontWeight: '600',
  },
});
