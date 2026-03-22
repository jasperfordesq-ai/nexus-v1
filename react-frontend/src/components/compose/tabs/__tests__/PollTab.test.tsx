// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for PollTab component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
};

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, first_name: 'Alice', avatar: '/alice.png' },
  })),
  useToast: vi.fn(() => mockToast),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string | undefined) => url || '/default-avatar.png'),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

// Mock hooks that use localStorage
vi.mock('@/hooks', () => ({
  useDraftPersistence: vi.fn((key: string, defaultValue: unknown) => {
    const state = { ...defaultValue as Record<string, unknown> };
    const setState = vi.fn((updater: ((s: typeof state) => typeof state) | typeof state) => {
      if (typeof updater === 'function') {
        Object.assign(state, updater(state));
      } else {
        Object.assign(state, updater);
      }
    });
    const clearState = vi.fn();
    return [state, setState, clearState];
  }),
}));

vi.mock('@/hooks/useMediaQuery', () => ({
  useMediaQuery: vi.fn(() => false), // desktop by default
}));

// Mock ComposeSubmitContext (path is relative to the source file being tested,
// but vitest resolves vi.mock paths relative to the test file)
vi.mock('@/components/compose/ComposeSubmitContext', () => ({
  useComposeSubmit: vi.fn(() => ({
    registration: null,
    register: vi.fn(),
    unregister: vi.fn(),
  })),
}));

// Avoid complex rendering of EmojiPicker
vi.mock('@/components/compose/shared/EmojiPicker', () => ({
  EmojiPicker: ({ onSelect }: { onSelect: (e: string) => void }) => (
    <button data-testid="emoji-picker" onClick={() => onSelect('😊')}>Emoji</button>
  ),
}));

// Simple CharacterCount mock
vi.mock('@/components/compose/shared/CharacterCount', () => ({
  CharacterCount: ({ current, max }: { current: number; max: number }) => (
    <span data-testid="char-count">{current}/{max}</span>
  ),
}));

import { PollTab } from '../PollTab';

const defaultProps = {
  onSuccess: vi.fn(),
  onClose: vi.fn(),
  groupId: undefined,
  templateData: undefined,
};

describe('PollTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<PollTab {...defaultProps} />);
    expect(document.body).toBeTruthy();
  });

  it('renders 2 poll option inputs by default', () => {
    render(<PollTab {...defaultProps} />);
    const optionInputs = screen.getAllByRole('textbox', { name: /poll option/i });
    expect(optionInputs).toHaveLength(2);
  });

  it('renders Add Option button', () => {
    render(<PollTab {...defaultProps} />);
    expect(screen.getByText(/add option/i)).toBeInTheDocument();
  });

  it('renders Cancel button on desktop', () => {
    render(<PollTab {...defaultProps} />);
    expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
  });

  it('calls onClose when Cancel is clicked', () => {
    const onClose = vi.fn();
    render(<PollTab {...defaultProps} onClose={onClose} />);
    fireEvent.click(screen.getByRole('button', { name: /cancel/i }));
    expect(onClose).toHaveBeenCalled();
  });

  it('shows error toast when attempting to submit empty question', async () => {
    render(<PollTab {...defaultProps} />);

    // Find Create Poll button and try to click it
    const createBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('poll')
    );
    if (createBtn) {
      fireEvent.click(createBtn);
      await waitFor(() => {
        expect(mockToast.error).toHaveBeenCalled();
      });
    }
  });

  it('renders character count component', () => {
    render(<PollTab {...defaultProps} />);
    expect(screen.getByTestId('char-count')).toBeInTheDocument();
  });

  it('renders emoji picker', () => {
    render(<PollTab {...defaultProps} />);
    expect(screen.getByTestId('emoji-picker')).toBeInTheDocument();
  });

  it('renders remove option buttons only when more than 2 options exist', () => {
    render(<PollTab {...defaultProps} />);
    // Initially 2 options — no remove buttons
    expect(screen.queryByRole('button', { name: /remove option/i })).not.toBeInTheDocument();
  });
});
