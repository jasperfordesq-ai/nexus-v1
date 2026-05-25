// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View } from 'react-native';
import { Avatar as HeroAvatar } from 'heroui-native';
import type { AvatarSize } from 'heroui-native';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';

interface AvatarProps {
  uri: string | null | undefined;
  name: string | null | undefined;
  size?: number;
  showOnline?: boolean;
}

function sizeToToken(px: number): AvatarSize {
  if (px <= 30) return 'sm';
  if (px <= 50) return 'md';
  return 'lg';
}

function getInitials(name: string | null | undefined): string {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

export default function Avatar({ uri, name, size = 40, showOnline = false }: AvatarProps) {
  const sizeToken = sizeToToken(size);
  const initials = getInitials(name);
  const resolvedUri = resolveImageUrl(uri);

  return (
    <View style={{ position: 'relative', alignSelf: 'flex-start' }}>
      <HeroAvatar size={sizeToken} accessibilityLabel={name ?? undefined}>
        {resolvedUri ? (
          <HeroAvatar.Image source={{ uri: resolvedUri }} />
        ) : null}
        <HeroAvatar.Fallback>{initials}</HeroAvatar.Fallback>
      </HeroAvatar>
      {showOnline ? (
        <View
          style={{
            position: 'absolute',
            right: 0,
            bottom: 0,
            width: 10,
            height: 10,
            borderRadius: 5,
            backgroundColor: '#22c55e',
            borderWidth: 2,
            borderColor: '#fff',
          }}
        />
      ) : null}
    </View>
  );
}
