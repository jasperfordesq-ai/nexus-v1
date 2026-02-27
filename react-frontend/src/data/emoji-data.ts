// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Categorized emoji dataset — ~200 most-used Unicode emoji.
 * Used by the EmojiPicker component in ComposeHub.
 */

export interface EmojiCategory {
  key: string;
  label: string;
  icon: string;
  emojis: string[];
}

export const EMOJI_CATEGORIES: EmojiCategory[] = [
  {
    key: 'smileys',
    label: 'compose.emoji_smileys',
    icon: '😀',
    emojis: [
      '😀', '😂', '🤣', '😊', '😍', '🥰', '😘', '😗',
      '😋', '😛', '🤪', '🤨', '🧐', '😎', '🥳', '🤩',
      '😤', '😡', '🥺', '😢', '😭', '😱', '😨', '🤗',
      '🤔', '🫡', '😶', '🫠', '🤫', '🤥', '😬', '🙄',
      '😏', '😌', '😴', '💤', '😷', '🤧', '🤮',
    ],
  },
  {
    key: 'people',
    label: 'compose.emoji_people',
    icon: '👋',
    emojis: [
      '👋', '🤚', '✋', '🖖', '👌', '🤌', '🤏', '✌️',
      '🤞', '🫰', '🤟', '🤘', '🫵', '👈', '👉', '👆',
      '👇', '☝️', '👍', '👎', '✊', '👊', '🤛', '🤜',
      '👏', '🙌', '🫶', '👐', '🤝', '🙏', '💪', '🦾',
      '🫂',
    ],
  },
  {
    key: 'animals',
    label: 'compose.emoji_animals',
    icon: '🐱',
    emojis: [
      '🐱', '🐶', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮',
      '🐷', '🐸', '🐵', '🐔', '🐧', '🐦', '🦅', '🦉',
      '🐝', '🐛', '🦋', '🐌', '🐞', '🐜', '🪲', '🕷️',
      '🐢', '🐍', '🦎', '🐊', '🐬', '🐳', '🐟',
    ],
  },
  {
    key: 'food',
    label: 'compose.emoji_food',
    icon: '🍕',
    emojis: [
      '🍕', '🍔', '🍟', '🌭', '🥪', '🌮', '🌯', '🥗',
      '🥘', '🫕', '🍝', '🍜', '🍲', '🍛', '🍣', '🍱',
      '🥟', '🍤', '🍩', '🍪', '🎂', '🍰', '🧁', '🍫',
      '🍬', '🍭', '🍮', '☕', '🍵', '🥤', '🧃', '🍺',
      '🍻',
    ],
  },
  {
    key: 'travel',
    label: 'compose.emoji_travel',
    icon: '🌍',
    emojis: [
      '🌍', '🌎', '🌏', '🗺️', '🧭', '🏔️', '🌋', '🏕️',
      '🏖️', '🏜️', '🏝️', '🏠', '🏡', '🏢', '🏣', '🏤',
      '🏥', '🏦', '🏨', '🏩', '🏪', '🏫', '🏬', '🏭',
      '🏯', '🏰', '🗽', '🗼', '🏗️',
    ],
  },
  {
    key: 'activities',
    label: 'compose.emoji_activities',
    icon: '⚽',
    emojis: [
      '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉',
      '🥏', '🎱', '🏓', '🏸', '🏒', '🥅', '⛳', '🏹',
      '🎣', '🤿', '🥊', '🥋', '🎽', '⛸️', '🎿', '⛷️',
      '🏂', '🏋️', '🧘', '🤸', '🏊', '🚴',
    ],
  },
  {
    key: 'objects',
    label: 'compose.emoji_objects',
    icon: '💡',
    emojis: [
      '💡', '🔦', '🕯️', '🪔', '📱', '💻', '⌨️', '🖥️',
      '🖨️', '📷', '📹', '🎥', '📺', '📻', '📡', '🔭',
      '🔬', '🧲', '💊', '💉', '🩺', '🩹', '🏷️', '📦',
      '📫', '📬', '📭', '📮', '📝', '📄', '📃',
    ],
  },
  {
    key: 'symbols',
    label: 'compose.emoji_symbols',
    icon: '❤️',
    emojis: [
      '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍',
      '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖',
      '💘', '💝', '✨', '🌟', '💫', '⭐', '🔥', '💥',
      '💢', '💤', '💨', '💦', '🎵', '🎶', '✅', '❌',
    ],
  },
];
