// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { View, Text, Image, StyleSheet } from 'react-native';
import { usePrimaryColor } from '@/lib/hooks/useTenant';

interface AvatarProps {
  uri: string | null | undefined;
  name: string | null | undefined;
  size?: number;
  showOnline?: boolean;
}

export default function Avatar({ uri, name, size = 40, showOnline = false }: AvatarProps) {
  const primary = usePrimaryColor();
  const initials = getInitials(name);

  const sizeStyle = { width: size, height: size, borderRadius: size / 2 };

  const onlineDot = showOnline ? (
    <View
      style={[
        styles.onlineDot,
        {
          right: 0,
          bottom: 0,
        },
      ]}
    />
  ) : null;

  if (uri) {
    return (
      <View style={sizeStyle}>
        <Image
          source={{ uri }}
          style={[styles.image, sizeStyle]}
          accessibilityLabel={`${name} avatar`}
        />
        {onlineDot}
      </View>
    );
  }

  return (
    <View
      style={[styles.fallback, sizeStyle, { backgroundColor: primary }]}
      accessibilityLabel={name ?? undefined}
      accessibilityRole="image"
    >
      <Text style={[styles.initials, { fontSize: size * 0.36 }]}>{initials}</Text>
      {onlineDot}
    </View>
  );
}

function getInitials(name: string | null | undefined): string {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

const styles = StyleSheet.create({
  image: { resizeMode: 'cover' },
  fallback: { justifyContent: 'center', alignItems: 'center' },
  initials: { color: '#fff', fontWeight: '700' },
  onlineDot: {
    position: 'absolute',
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#22c55e',
    borderWidth: 2,
    borderColor: '#fff',
  },
});
