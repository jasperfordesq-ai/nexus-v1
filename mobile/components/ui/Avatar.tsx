// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { View, Text, Image, StyleSheet } from 'react-native';
import { usePrimaryColor } from '@/lib/hooks/useTenant';

interface AvatarProps {
  uri: string | null | undefined;
  name: string;
  size?: number;
}

/**
 * Displays a user avatar.
 * Falls back to a coloured circle with initials when no image is provided.
 */
export default function Avatar({ uri, name, size = 40 }: AvatarProps) {
  const primary = usePrimaryColor();
  const initials = getInitials(name);

  const sizeStyle = { width: size, height: size, borderRadius: size / 2 };

  if (uri) {
    return (
      <Image
        source={{ uri }}
        style={[styles.image, sizeStyle]}
        accessibilityLabel={`${name} avatar`}
      />
    );
  }

  return (
    <View style={[styles.fallback, sizeStyle, { backgroundColor: primary }]}>
      <Text style={[styles.initials, { fontSize: size * 0.36 }]}>{initials}</Text>
    </View>
  );
}

function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

const styles = StyleSheet.create({
  image: { resizeMode: 'cover' },
  fallback: { justifyContent: 'center', alignItems: 'center' },
  initials: { color: '#fff', fontWeight: '700' },
});
