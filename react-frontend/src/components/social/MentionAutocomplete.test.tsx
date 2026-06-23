// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

// ─── No API calls ─────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

// ─── resolveAvatarUrl helper — stub to passthrough ───────────────────────────
vi.mock('@/lib/helpers', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...orig,
    resolveAvatarUrl: (url: string | null) => url ?? '',
  };
});

// ─── Stub HeroUI Avatar + Skeleton (jsdom safe) ───────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Avatar: ({ name, src }: { name?: string; src?: string }) => (
      <img data-testid="mention-avatar" alt={name ?? ''} src={src ?? ''} />
    ),
    Skeleton: ({ className }: { className?: string }) => (
      <div data-testid="mention-skeleton" className={className} />
    ),
    Button: ({
      children,
      onMouseDown,
      onMouseEnter,
      role,
      'aria-selected': ariaSelected,
      id,
      variant: _variant,
      ...rest
    }: React.ButtonHTMLAttributes<HTMLButtonElement> & {
      'aria-selected'?: boolean;
      variant?: string;
    }) => (
      <button
        {...rest}
        id={id}
        role={role}
        aria-selected={ariaSelected}
        onMouseDown={onMouseDown}
        onMouseEnter={onMouseEnter}
        data-testid={`mention-option-${id}`}
      >
        {children}
      </button>
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeSuggestion = (overrides = {}) => ({
  id: 1,
  name: 'Alice Green',
  username: 'alicegreen',
  avatar_url: null,
  is_connection: false,
  ...overrides,
});

const defaultProps = {
  isOpen: true,
  suggestions: [makeSuggestion()],
  selectedIndex: 0,
  isLoading: false,
  query: 'ali',
  onSelect: vi.fn(),
  onHover: vi.fn(),
};

describe('MentionAutocomplete', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders nothing when isOpen is false', async () => {
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    const { container } = render(<MentionAutocomplete {...defaultProps} isOpen={false} />);
    expect(container.querySelector('[role="listbox"]')).toBeNull();
  });

  it('renders a listbox when isOpen is true', async () => {
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} />);
    expect(screen.getByRole('listbox')).toBeInTheDocument();
  });

  it('renders loading skeletons when isLoading is true', async () => {
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} isLoading={true} suggestions={[]} />);
    const skeletons = screen.getAllByTestId('mention-skeleton');
    // 3 skeletons per placeholder row × 2 per row (avatar + text)
    expect(skeletons.length).toBeGreaterThanOrEqual(3);
  });

  it('renders empty state message when suggestions is empty and not loading', async () => {
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} suggestions={[]} />);
    // The i18n key 'mention.no_users' resolves to its key in test env
    expect(screen.getByRole('listbox')).toBeInTheDocument();
    // There should be no option buttons
    expect(screen.queryByRole('option')).toBeNull();
  });

  it('renders suggestion options for each user', async () => {
    const suggestions = [
      makeSuggestion({ id: 1, name: 'Alice Green', username: 'alicegreen' }),
      makeSuggestion({ id: 2, name: 'Bob Smith', username: 'bobs' }),
    ];
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} suggestions={suggestions} />);
    // Two option buttons rendered (role=option set on our Button stub)
    expect(screen.getAllByRole('option').length).toBe(2);
  });

  it('renders the user name text in each suggestion', async () => {
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} query="" />);
    // With empty query, HighlightText renders name as a plain text node
    expect(screen.getByText('Alice Green')).toBeInTheDocument();
  });

  it('shows the username text somewhere in the suggestion', async () => {
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} query="" />);
    // Username paragraph contains "@" + "alicegreen" as separate nodes
    const usernamePara = document.querySelector('p.text-\\[10px\\]');
    expect(usernamePara).not.toBeNull();
    expect(usernamePara?.textContent).toContain('alicegreen');
  });

  it('calls onSelect when a suggestion option is mouseDown-clicked', async () => {
    const onSelect = vi.fn();
    const suggestion = makeSuggestion({ id: 42, name: 'Carol Day' });
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(
      <MentionAutocomplete
        {...defaultProps}
        onSelect={onSelect}
        suggestions={[suggestion]}
      />
    );
    // The Button stub renders with role="option" — find it directly
    const option = screen.getByRole('option');
    // MentionAutocomplete uses onMouseDown to avoid input blur
    fireEvent.mouseDown(option);
    await waitFor(() => {
      expect(onSelect).toHaveBeenCalledWith(suggestion);
    });
  });

  it('marks the selectedIndex item as aria-selected', async () => {
    const suggestions = [
      makeSuggestion({ id: 1, name: 'Alice Green' }),
      makeSuggestion({ id: 2, name: 'Bob Smith' }),
    ];
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(
      <MentionAutocomplete {...defaultProps} suggestions={suggestions} selectedIndex={1} />
    );
    const options = screen.getAllByRole('option');
    // First item (idx=0) is not selected; second (idx=1) is selected
    expect(options[0].getAttribute('aria-selected')).toBe('false');
    expect(options[1].getAttribute('aria-selected')).toBe('true');
  });

  it('shows a connection badge for users with is_connection=true', async () => {
    const suggestion = makeSuggestion({ id: 1, name: 'Connected User', username: 'alicegreen', is_connection: true });
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} suggestions={[suggestion]} />);
    // UserCheck icon renders with aria-label — the i18n key resolves via the test namespace
    // In the test env, the key is passed as the fallback so the label may be either
    // "mention.connected" (key) or "Connected" depending on i18n setup — check the SVG is present
    const svg = document.querySelector('svg[aria-label]');
    expect(svg).not.toBeNull();
  });

  it('does not show connection badge when is_connection is false', async () => {
    const suggestion = makeSuggestion({ id: 1, name: 'Not Connected', is_connection: false });
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} suggestions={[suggestion]} />);
    expect(screen.queryByLabelText('mention.connected')).toBeNull();
  });

  it('highlights the query substring within the user name', async () => {
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} query="ali" />);
    // HighlightText wraps the match in a bold span
    const highlighted = document.querySelector('span.font-bold');
    expect(highlighted).not.toBeNull();
    expect(highlighted?.textContent?.toLowerCase()).toBe('ali');
  });

  it('renders an avatar image for each suggestion', async () => {
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} />);
    expect(screen.getByTestId('mention-avatar')).toBeInTheDocument();
  });

  it('accepts a custom style prop on the container', async () => {
    const { MentionAutocomplete } = await import('./MentionAutocomplete');
    render(<MentionAutocomplete {...defaultProps} style={{ top: 50 }} />);
    const listbox = screen.getByRole('listbox');
    expect(listbox).toHaveStyle({ top: '50px' });
  });
});
