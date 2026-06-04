// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Image, Text, View } from 'react-native';
import { Surface } from 'heroui-native';

import { useTranslation } from 'react-i18next';
import { usePrimaryColor, useTenant } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';

export default function TenantBanner() {
  const { t } = useTranslation('home');
  const { tenant } = useTenant();
  const primary = usePrimaryColor();
  const theme = useTheme();

  if (!tenant) return null;

  return (
    <Surface
      variant="default"
      className="mx-3 mt-2 flex-row items-center gap-2 overflow-hidden rounded-panel px-3 py-2"
      style={{ borderWidth: 1, borderColor: theme.borderSubtle }}
    >
      {tenant.branding.logo_url ? (
        <Image
          source={{ uri: resolveImageUrl(tenant.branding.logo_url) ?? tenant.branding.logo_url }}
          style={{ width: 30, height: 30 }}
          resizeMode="contain"
          accessibilityLabel={t('tenant.logoLabel', { name: tenant.name })}
        />
      ) : (
        <View className="h-9 w-9 items-center justify-center rounded-2xl" style={{ backgroundColor: primary }}>
          <Text className="text-base font-bold text-white">{tenant.name.charAt(0).toUpperCase()}</Text>
        </View>
      )}
      <View className="min-w-0 flex-1">
        <Text className="text-sm font-bold leading-5" style={{ color: theme.text }} numberOfLines={1}>
          {tenant.name}
        </Text>
        {tenant.tagline ? (
          <Text className="text-xs leading-4" style={{ color: theme.textSecondary }} numberOfLines={1}>
            {tenant.tagline}
          </Text>
        ) : null}
      </View>
    </Surface>
  );
}
