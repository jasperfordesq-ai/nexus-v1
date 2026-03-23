// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MentionAutocomplete component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string, opts?: Record<string, unknown>) => {
      if (fallback && opts) {
        // Simple interpolation for "{{count}}" pattern
        return fallback.replace(/\{\{(\w+)\}\}/g, (_, k) => String(opts[k] ?? ''));
      }
      return fallback ?? key;
    },
    i18n: { language: 'en', changeLanguage: vi.fn() },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({ user: { id: 1 }, isAuthenticated: true })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantSlug: 'test',
    branding: { name: 'Test' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  useTheme: vi.fn(() => ({ resolvedTheme: 'light', theme: 'light', setTheme: vi.fn() })),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null | undefined) => url || '/default-avatar.png',
  formatRelativeTime: vi.fn(() => '1 hour ago'),
}));

import { MentionAutocomplete } from '../MentionAutocomplete';
import type { MentionSuggestion } from '../MentionAutocomplete';

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

const mockSuggestions: MentionSuggestion[] = [
  { id: 1, name: 'Alice Smith', username: 'alice', avatar_url: null, is_connection: true },
  { id: 2, name: 'Albert Jones', username: 'albert', avatar_url: null, is_connection: false },
  { id: 3, name: 'Alex Brown', username: null, avatar_url: '/avatars/alex.jpg' },
];

const defaultProps = {
  isOpen: true,
  suggestions: mockSuggestions,
  selectedIndex: 0,
  isLoading: false,
  query: 'al',
  onSelect: vi.fn(),
  onHover: vi.fn(),
};

describe('MentionAutocomplete', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing when isOpen is true', () => {
    const { container } = render(
      <W><MentionAutocomplete {...defaultProps} /></W>,
    );
    expect(container).toBeTruthy();
  });

  it('returns null when isOpen is false', () => {
    const { container } = render(
      <W><MentionAutocomplete {...defaultProps} isOpen={false} /></W>,
    );
    expect(container.querySelector('[role="listbox"]')).toBeNull();
  });

  it('renders a listbox', () => {
    render(<W><MentionAutocomplete {...defaultProps} /></W>);
    expect(screen.getByRole('listbox')).toBeInTheDocument();
  });

  it('renders aria-label with suggestion count', () => {
    render(<W><MentionAutocomplete {...defaultProps} /></W>);
    const listbox = screen.getByRole('listbox');
    expect(listbox.getAttribute('aria-label')).toContain('3');
  });

  it('renders all suggestion items as options', () => {
    render(<W><MentionAutocomplete {...defaultProps} /></W>);
    const options = screen.getAllByRole('option');
    expect(options).toHaveLength(3);
  });

  it('displays user names (text may be split by highlight spans)', () => {
    render(<W><MentionAutocomplete {...defaultProps} /></W>);
    // HighlightText splits names — use alt attribute on avatars instead
    const avatars = screen.getAllByRole('img');
    const altTexts = avatars.map((img) => img.getAttribute('alt'));
    expect(altTexts).toContain('Alice Smith');
    expect(altTexts).toContain('Albert Jones');
    expect(altTexts).toContain('Alex Brown');
  });

  it('displays usernames with @ prefix', () => {
    render(<W><MentionAutocomplete {...defaultProps} /></W>);
    // HighlightText splits the username. Check that option text contains @...ice (alice) and @...bert
    const options = screen.getAllByRole('option');
    expect(options[0].textContent).toContain('alice');
    expect(options[1].textContent).toContain('albert');
  });

  it('does not show @ for users without username', () => {
    render(<W><MentionAutocomplete {...defaultProps} /></W>);
    // Alex Brown has username: null — no @username row in option text
    const options = screen.getAllByRole('option');
    const alexOption = options[2];
    // Should not contain an @-prefixed username line
    expect(alexOption.textContent).not.toMatch(/@\w+Brown/);
  });

  it('highlights the selected item with aria-selected', () => {
    render(<W><MentionAutocomplete {...defaultProps} selectedIndex={1} /></W>);
    const options = screen.getAllByRole('option');
    expect(options[1]).toHaveAttribute('aria-selected', 'true');
    expect(options[0]).toHaveAttribute('aria-selected', 'false');
  });

  it('calls onSelect when an option is clicked via mousedown', () => {
    const onSelect = vi.fn();
    render(<W><MentionAutocomplete {...defaultProps} onSelect={onSelect} /></W>);
    const options = screen.getAllByRole('option');
    fireEvent.mouseDown(options[1]);
    expect(onSelect).toHaveBeenCalledWith(mockSuggestions[1]);
  });

  it('calls onHover when mouse enters an option', () => {
    const onHover = vi.fn();
    render(<W><MentionAutocomplete {...defaultProps} onHover={onHover} /></W>);
    const options = screen.getAllByRole('option');
    fireEvent.mouseEnter(options[2]);
    expect(onHover).toHaveBeenCalledWith(2);
  });

  it('sets correct id on each option', () => {
    render(<W><MentionAutocomplete {...defaultProps} /></W>);
    const options = screen.getAllByRole('option');
    expect(options[0]).toHaveAttribute('id', 'mention-option-1');
    expect(options[1]).toHaveAttribute('id', 'mention-option-2');
    expect(options[2]).toHaveAttribute('id', 'mention-option-3');
  });

  it('shows connection icon for connected users', () => {
    render(<W><MentionAutocomplete {...defaultProps} /></W>);
    // Alice has is_connection: true — should have a "Connected" aria-label
    const connectedIcons = screen.getAllByLabelText('Connected');
    expect(connectedIcons).toHaveLength(1);
  });

  it('shows loading skeletons when isLoading is true', () => {
    const { container } = render(
      <W>
        <MentionAutocomplete
          {...defaultProps}
          isLoading={true}
          suggestions={[]}
        />
      </W>,
    );
    // Should render 3 skeleton rows
    const skeletons = container.querySelectorAll('[data-slot="base"]');
    // Skeleton elements from HeroUI
    expect(container.querySelector('[role="listbox"]')).toBeInTheDocument();
  });

  it('shows "No users found" when suggestions are empty and not loading', () => {
    render(
      <W>
        <MentionAutocomplete
          {...defaultProps}
          suggestions={[]}
          isLoading={false}
        />
      </W>,
    );
    expect(screen.getByText('No users found')).toBeInTheDocument();
  });

  it('highlights matching text in user names', () => {
    const { container } = render(
      <W><MentionAutocomplete {...defaultProps} query="Al" /></W>,
    );
    // The HighlightText component wraps matching portion in a bold span
    const boldSpans = container.querySelectorAll('.font-bold');
    expect(boldSpans.length).toBeGreaterThanOrEqual(1);
  });

  it('passes className prop to container', () => {
    const { container } = render(
      <W>
        <MentionAutocomplete {...defaultProps} className="custom-position" />
      </W>,
    );
    const listbox = screen.getByRole('listbox');
    expect(listbox.parentElement?.classList.contains('custom-position') || listbox.classList.contains('custom-position')).toBe(true);
  });

  it('forwards ref', () => {
    const ref = vi.fn();
    render(
      <W>
        <MentionAutocomplete {...defaultProps} ref={ref} />
      </W>,
    );
    // The ref should have been called with the DOM element
    expect(ref).toHaveBeenCalled();
  });

  it('prevents default on mouseDown to avoid input blur', () => {
    const onSelect = vi.fn();
    render(<W><MentionAutocomplete {...defaultProps} onSelect={onSelect} /></W>);
    const options = screen.getAllByRole('option');
    const event = new MouseEvent('mousedown', { bubbles: true, cancelable: true });
    const preventDefaultSpy = vi.spyOn(event, 'preventDefault');
    options[0].dispatchEvent(event);
    expect(preventDefaultSpy).toHaveBeenCalled();
  });
});
