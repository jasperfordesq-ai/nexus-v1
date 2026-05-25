// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo } from 'react';
import { View, Text, Image, StyleSheet } from 'react-native';

import { useTenant, usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING } from '@/lib/styles/spacing';

/**
 * Displays the current tenant's name and logo in a compact header banner.
 * Used at the top of the Home screen to reinforce community identity.
 */
export default function TenantBanner() {
  const { tenant } = useTenant();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  if (!tenant) return null;

  return (
    <View style={[styles.banner, { borderBottomColor: withAlpha(primary, 0.13) }]}>
      {tenant.branding.logo_url ? (
        <Image
          source={{ uri: tenant.branding.logo_url }}
          style={styles.logo}
          resizeMode="contain"
          accessibilityLabel={`${tenant.name} logo`}
        />
      ) : (
        <View style={[styles.logoPlaceholder, { backgroundColor: primary }]}>
          <Text style={styles.logoInitial}>
            {tenant.name.charAt(0).toUpperCase()}
          </Text>
        </View>
      )}
      <View>
        <Text style={styles.tenantName}>{tenant.name}</Text>
        {tenant.tagline && (
          <Text style={styles.tagline}>{tenant.tagline}</Text>
        )}
      </View>
    </View>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    banner: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingHorizontal: SPACING.md,
      paddingVertical: SPACING.sm + 2,
      gap: SPACING.sm + 4,
      borderBottomWidth: 1,
      backgroundColor: theme.surface,
    },
    logo: { width: 36, height: 36 },
    logoPlaceholder: {
      width: 36,
      height: 36,
      borderRadius: SPACING.sm,
      justifyContent: 'center',
      alignItems: 'center',
    },
    logoInitial: { ...TYPOGRAPHY.h3, color: '#fff' },
    tenantName: { ...TYPOGRAPHY.body, fontWeight: '700', color: theme.text },
    tagline: { ...TYPOGRAPHY.caption, color: theme.textSecondary },
  });
}
