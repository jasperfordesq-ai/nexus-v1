// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Phone-mode CommentsSection behavior: the inline variant replaces its
 * composer with a pill that opens the sheet-variant thread in a BottomSheet
 * (composer pinned at the bottom, autofocused). Kept separate from
 * CommentsSection.test.tsx because it force-mocks useMediaQuery to phone.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import { CommentsSection, type CommentsSectionProps } from './CommentsSection';

vi.mock('@/hooks/useMediaQuery', () => ({
  useMediaQuery: () => true,
}));

vi.mock(import('@/lib/helpers'), async (importOriginal) => ({
  ...(await importOriginal()),
  resolveAvatarUrl: (url?: string | null) => url ?? '',
  formatRelativeTime: () => '5 minutes ago',
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
    useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  })
);

vi.mock('@/lib/motion', () => ({
  motion: {
    div: ({ children, ...rest }: { children: React.ReactNode; [key: string]: unknown }) => (
      <div {...(rest as object)}>{children}</div>
    ),
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('./MentionRenderer', () => ({
  MentionRenderer: ({ text }: { text: string }) => <span>{text}</span>,
}));

vi.mock('./UserHoverCard', () => ({
  UserHoverCard: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/hooks/useSocialInteractions', () => ({
  AVAILABLE_REACTIONS: ['like', 'love', 'laugh', 'wow', 'sad', 'celebrate'],
  COMMENT_REACTION_EMOJI_MAP: { like: '👍', love: '❤️', laugh: '😂', wow: '😮', sad: '😢', celebrate: '🎉' },
}));

function baseProps(overrides: Partial<CommentsSectionProps> = {}): CommentsSectionProps {
  return {
    comments: [],
    commentsCount: 0,
    commentsLoading: false,
    commentsLoaded: true,
    loadComments: vi.fn(async () => {}),
    submitComment: vi.fn(async () => true),
    editComment: vi.fn(async () => true),
    deleteComment: vi.fn(async () => true),
    toggleReaction: vi.fn(async () => {}),
    isAuthenticated: true,
    currentUserId: 1,
    currentUserName: 'Test User',
    ...overrides,
  };
}

describe('CommentsSection on phones (inline variant)', () => {
  it('shows a composer pill instead of the inline input', () => {
    render(<CommentsSection {...baseProps()} />);

    expect(screen.getByRole('button', { name: 'Write a comment...' })).toBeInTheDocument();
    expect(screen.queryByPlaceholderText('Write a comment...')).not.toBeInTheDocument();
  });

  it('opens the composer sheet with a pinned input when the pill is pressed', async () => {
    render(<CommentsSection {...baseProps()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Write a comment...' }));

    await waitFor(() => {
      expect(screen.getByPlaceholderText('Write a comment...')).toBeInTheDocument();
    });
  });

  it('keeps the plain inline composer for unauthenticated users (nothing rendered)', () => {
    render(<CommentsSection {...baseProps({ isAuthenticated: false })} />);

    expect(screen.queryByRole('button', { name: 'Write a comment...' })).not.toBeInTheDocument();
    expect(screen.queryByPlaceholderText('Write a comment...')).not.toBeInTheDocument();
  });

  it('does not double-wrap when already rendered as the sheet variant', () => {
    render(<CommentsSection {...baseProps({ variant: 'sheet' })} />);

    // Sheet variant keeps its real composer input and never shows the pill.
    expect(screen.getByPlaceholderText('Write a comment...')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Write a comment...' })).not.toBeInTheDocument();
  });
});
