// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { View, Text, Image } from 'react-native';

import { useTenant, usePrimaryColor } from '@/lib/hooks/useTenant';
import { withAlpha } from '@/lib/utils/color';

/**
 * Displays the current tenant's name and logo in a compact header banner.
 * Used at the top of the Home screen to reinforce community identity.
 */
export default function TenantBanner() {
  const { tenant } = useTenant();
  const primary = usePrimaryColor();

  if (!tenant) return null;

  return (
    <View
      className="flex-row items-center px-4 py-2.5 gap-3 border-b bg-surface"
      style={{ borderBottomColor: withAlpha(primary, 0.13) }}
    >
      {tenant.branding.logo_url ? (
        <Image
          source={{ uri: tenant.branding.logo_url }}
          style={{ width: 36, height: 36 }}
          resizeMode="contain"
          accessibilityLabel={`${tenant.name} logo`}
        />
      ) : (
        <View
          className="w-9 h-9 rounded-lg justify-center items-center"
          style={{ backgroundColor: primary }}
        >
          <Text className="text-lg font-bold text-white">
            {tenant.name.charAt(0).toUpperCase()}
          </Text>
        </View>
      )}
      <View>
        <Text className="text-base font-bold text-foreground">{tenant.name}</Text>
        {tenant.tagline && (
          <Text className="text-xs text-muted-foreground">{tenant.tagline}</Text>
        )}
      </View>
    </View>
  );
}
