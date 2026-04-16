// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Clock,
  Users,
  Zap,
  UserPlus,
  Search,
  Handshake,
  Coins,
  Heart,
  Shield,
  Star,
  Globe,
  BookOpen,
  MessageCircle,
  Award,
  Target,
  ThumbsUp,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { LandingIconId } from '@/types';

/** Maps LandingIconId strings to their corresponding Lucide React icon components */
export const iconMap: Record<LandingIconId, LucideIcon> = {
  clock: Clock,
  users: Users,
  zap: Zap,
  'user-plus': UserPlus,
  search: Search,
  handshake: Handshake,
  coins: Coins,
  heart: Heart,
  shield: Shield,
  star: Star,
  globe: Globe,
  'book-open': BookOpen,
  'message-circle': MessageCircle,
  award: Award,
  target: Target,
  'thumbs-up': ThumbsUp,
};

/**
 * Resolve a LandingIconId to a Lucide icon component.
 * Returns the mapped icon, or the fallback (defaults to Clock) if the id
 * is undefined or unrecognised.
 */
export function getIcon(id?: LandingIconId, fallback: LucideIcon = Clock): LucideIcon {
  if (id && id in iconMap) {
    return iconMap[id];
  }
  return fallback;
}
