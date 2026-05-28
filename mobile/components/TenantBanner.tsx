// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Image, Text, View } from 'react-native';
import { Surface } from 'heroui-native';

import { useTranslation } from 'react-i18next';
import { usePrimaryColor, useTenant } from '@/lib/hooks/useTenant';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';

export default function TenantBanner() {
  const { t } = useTranslation('home');
  const { tenant } = useTenant();
  const primary = usePrimaryColor();

  if (!tenant) return null;

  return (
    <Surface variant="default" className="mx-4 mt-3 flex-row items-center gap-3 rounded-panel-inner p-3">
      {tenant.branding.logo_url ? (
        <Image
          source={{ uri: resolveImageUrl(tenant.branding.logo_url) ?? tenant.branding.logo_url }}
          style={{ width: 38, height: 38 }}
          resizeMode="contain"
          accessibilityLabel={t('tenant.logoLabel', { name: tenant.name })}
        />
      ) : (
        <View className="h-10 w-10 items-center justify-center rounded-xl" style={{ backgroundColor: primary }}>
          <Text className="text-lg font-bold text-white">{tenant.name.charAt(0).toUpperCase()}</Text>
        </View>
      )}
      <View className="min-w-0 flex-1">
        <Text className="text-base font-bold text-foreground" numberOfLines={1}>
          {tenant.name}
        </Text>
        {tenant.tagline ? (
          <Text className="text-xs text-muted-foreground" numberOfLines={1}>
            {tenant.tagline}
          </Text>
        ) : null}
      </View>
    </Surface>
  );
}
