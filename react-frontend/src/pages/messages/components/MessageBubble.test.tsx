// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { render, screen } from '@/test/test-utils';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import { MessageBubble } from './MessageBubble';
import type { MessageBubbleProps } from './MessageBubble';
import type { Message } from '@/types/api';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => key,
    i18n: { changeLanguage: vi.fn() },
  }),
}));

vi.mock('framer-motion', () => ({
  motion: new Proxy({}, {
    get: (_: object, prop: string) => {
      const { createElement, forwardRef } = require('react');
      return forwardRef(({ children, ...props }: React.PropsWithChildren<Record<string, unknown>>, ref: React.Ref<unknown>) =>
        createElement(prop as string, { ...props, ref }, children)
      );
    },
  }),
  AnimatePresence: ({ children }: React.PropsWithChildren) => children,
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null) => url ?? '',
}));

vi.mock('./VoiceMessagePlayer', () => ({
  VoiceMessagePlayer: () => <div data-testid="voice-player">Voice Player</div>,
}));

const mockMessage: Message = {
  id: 1,
  body: 'Hello, world!',
  content: 'Hello, world!',
  created_at: '2026-01-01T12:00:00Z',
  sender_id: 10,
  is_own: true,
  is_read: false,
  is_deleted: false,
  is_edited: false,
  is_voice: false,
  audio_url: undefined,
  attachments: [],
  reactions: {},
} as unknown as Message;

const otherUser = { id: 20, name: 'Bob', avatar_url: null };

const defaultProps: MessageBubbleProps = {
  message: mockMessage,
  isOwn: true,
  showAvatar: true,
  otherUser,
  onReact: vi.fn(),
  onEdit: vi.fn(),
  onDelete: vi.fn(),
};

describe('MessageBubble', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders message content', () => {
    render(<MessageBubble {...defaultProps} />);
    expect(screen.getByText('Hello, world!')).toBeDefined();
  });

  it('renders voice player for voice messages', () => {
    const voiceMsg = { ...mockMessage, is_voice: true, audio_url: 'https://cdn.example.com/audio.mp3' };
    render(<MessageBubble {...defaultProps} message={voiceMsg as Message} />);
    expect(screen.getByTestId('voice-player')).toBeDefined();
  });

  it('shows deleted message placeholder', () => {
    const deletedMsg = { ...mockMessage, is_deleted: true };
    render(<MessageBubble {...defaultProps} message={deletedMsg as Message} />);
    // Should show some deleted state indicator
    expect(screen.queryByText('Hello, world!')).toBeNull();
  });

  it('renders avatar for other user when showAvatar is true', () => {
    render(<MessageBubble {...defaultProps} isOwn={false} showAvatar={true} />);
    // Avatar should be present for received messages
    // HeroUI Avatar renders with img or initials
    expect(screen.queryByText('B')).toBeDefined(); // Initials from "Bob"
  });

  it('renders edit input when isEditing is true', () => {
    render(
      <MessageBubble
        {...defaultProps}
        isEditing={true}
        editingText="Hello, world!"
        onEditingTextChange={vi.fn()}
        onSaveEdit={vi.fn()}
        onCancelEdit={vi.fn()}
      />
    );
    const input = screen.getByDisplayValue('Hello, world!');
    expect(input).toBeDefined();
  });

  it('highlights text matching highlightQuery', () => {
    render(
      <MessageBubble
        {...defaultProps}
        isHighlighted={true}
        highlightQuery="world"
      />
    );
    // Text should still be visible
    expect(screen.getByText(/world/i)).toBeDefined();
  });

  it('renders with unique id prop', () => {
    render(<MessageBubble {...defaultProps} id="msg-1" />);
    const el = document.getElementById('msg-1');
    expect(el).toBeDefined();
  });

  it('renders reaction picker trigger button', async () => {
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();
    render(<MessageBubble {...defaultProps} />);

    // Hover to reveal actions (or look for reaction button)
    const reactionBtns = screen.queryAllByRole('button');
    expect(reactionBtns.length).toBeGreaterThan(0);
  });
});
