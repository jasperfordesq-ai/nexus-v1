// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useNetworkStatus } from '@/lib/hooks/useNetworkStatus';

/**
 * Renders a sticky warning banner when the device has no connectivity.
 * Respects light/dark theme via NativeWind tokens.
 */
export default function OfflineBanner() {
  const { t } = useTranslation('common');
  const { isOnline } = useNetworkStatus();

  if (isOnline) return null;

  return (
    <View className="border-b border-warning/30 bg-warning/10 px-4 py-2 items-center z-[100]">
      <Text className="text-sm font-semibold text-warning">{t('offline')}</Text>
    </View>
  );
}
