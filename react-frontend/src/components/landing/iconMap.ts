// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import Clock from 'lucide-react/icons/clock';
import Users from 'lucide-react/icons/users';
import Zap from 'lucide-react/icons/zap';
import UserPlus from 'lucide-react/icons/user-plus';
import Search from 'lucide-react/icons/search';
import Handshake from 'lucide-react/icons/handshake';
import Coins from 'lucide-react/icons/coins';
import Heart from 'lucide-react/icons/heart';
import Shield from 'lucide-react/icons/shield';
import Star from 'lucide-react/icons/star';
import Globe from 'lucide-react/icons/globe';
import BookOpen from 'lucide-react/icons/book-open';
import MessageCircle from 'lucide-react/icons/message-circle';
import Award from 'lucide-react/icons/award';
import Target from 'lucide-react/icons/target';
import ThumbsUp from 'lucide-react/icons/thumbs-up';
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
