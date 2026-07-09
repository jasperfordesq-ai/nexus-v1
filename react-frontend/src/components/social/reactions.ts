// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export type ReactionType = 'love' | 'like' | 'laugh' | 'wow' | 'sad' | 'celebrate' | 'clap' | 'time_credit';

export interface ReactionConfig {
  type: ReactionType;
  emoji: string;
  label: string;
}

export const REACTION_CONFIGS: ReactionConfig[] = [
  { type: 'like', emoji: '\uD83D\uDC4D', label: 'reaction.like' },
  { type: 'love', emoji: '\u2764\uFE0F', label: 'reaction.love' },
  { type: 'laugh', emoji: '\uD83D\uDE02', label: 'reaction.laugh' },
  { type: 'wow', emoji: '\uD83D\uDE2E', label: 'reaction.wow' },
  { type: 'sad', emoji: '\uD83D\uDE22', label: 'reaction.sad' },
  { type: 'celebrate', emoji: '\uD83C\uDF89', label: 'reaction.celebrate' },
  { type: 'clap', emoji: '\uD83D\uDC4F', label: 'reaction.clap' },
  { type: 'time_credit', emoji: '\u23F0', label: 'reaction.time_credit' },
];

export const REACTION_EMOJI_MAP: Record<ReactionType, string> = Object.fromEntries(
  REACTION_CONFIGS.map((reaction) => [reaction.type, reaction.emoji])
) as Record<ReactionType, string>;

export const REACTION_LABEL_MAP: Record<ReactionType, string> = Object.fromEntries(
  REACTION_CONFIGS.map((reaction) => [reaction.type, reaction.label])
) as Record<ReactionType, string>;
