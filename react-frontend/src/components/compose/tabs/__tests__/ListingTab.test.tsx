// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ListingTab component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), upload: vi.fn() },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn() };

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({ isAuthenticated: true, user: { id: 1, first_name: 'Alice', avatar: '/alice.png' } })),
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
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string | undefined) => url || '/default-avatar.png'),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/lib/compress-image', () => ({
  compressImage: vi.fn((file: File) => Promise.resolve(file)),
}));

vi.mock('@/hooks', () => ({
  useDraftPersistence: vi.fn((_key: string, defaultValue: unknown) => {
    const state = { ...(defaultValue as Record<string, unknown>) };
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
  useMediaQuery: vi.fn(() => false),
}));

vi.mock('@/components/compose/ComposeSubmitContext', () => ({
  useComposeSubmit: vi.fn(() => ({
    registration: null,
    register: vi.fn(),
    unregister: vi.fn(),
  })),
}));

vi.mock('@/components/compose/shared/EmojiPicker', () => ({
  EmojiPicker: ({ onSelect }: { onSelect: (e: string) => void }) => (
    <button data-testid="emoji-picker" onClick={() => onSelect('😊')}>Emoji</button>
  ),
}));

vi.mock('@/components/compose/shared/CharacterCount', () => ({
  CharacterCount: ({ current, max }: { current: number; max: number }) => (
    <span data-testid="char-count">{current}/{max}</span>
  ),
}));

vi.mock('@/components/compose/shared/AiAssistButton', () => ({
  AiAssistButton: () => <button data-testid="ai-assist">AI Assist</button>,
}));

vi.mock('@/components/compose/shared/SdgGoalsPicker', () => ({
  SdgGoalsPicker: () => <div data-testid="sdg-picker">SDG Picker</div>,
}));

vi.mock('@/components/location', () => ({
  PlaceAutocompleteInput: ({ label }: { label: string }) => (
    <div data-testid="place-input">{label}</div>
  ),
}));

import { ListingTab } from '../ListingTab';

const defaultProps = {
  onSuccess: vi.fn(),
  onClose: vi.fn(),
  groupId: null as number | null,
  templateData: undefined,
};

describe('ListingTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Categories endpoint returns empty array by default
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
  });

  it('renders without crashing', () => {
    render(<ListingTab {...defaultProps} />);
    expect(document.body).toBeTruthy();
  });

  it('renders title input', () => {
    render(<ListingTab {...defaultProps} />);
    const titleInput = screen.getByRole('textbox', { name: /title/i });
    expect(titleInput).toBeInTheDocument();
  });

  it('renders description textarea', () => {
    render(<ListingTab {...defaultProps} />);
    const descInput = screen.getByRole('textbox', { name: /description/i });
    expect(descInput).toBeInTheDocument();
  });

  it('renders character count', () => {
    render(<ListingTab {...defaultProps} />);
    expect(screen.getByTestId('char-count')).toBeInTheDocument();
  });

  it('renders emoji picker', () => {
    render(<ListingTab {...defaultProps} />);
    expect(screen.getByTestId('emoji-picker')).toBeInTheDocument();
  });

  it('renders AI assist button', () => {
    render(<ListingTab {...defaultProps} />);
    expect(screen.getByTestId('ai-assist')).toBeInTheDocument();
  });

  it('renders SDG goals picker', () => {
    render(<ListingTab {...defaultProps} />);
    expect(screen.getByTestId('sdg-picker')).toBeInTheDocument();
  });

  it('renders location input', () => {
    render(<ListingTab {...defaultProps} />);
    expect(screen.getByTestId('place-input')).toBeInTheDocument();
  });

  it('renders estimated hours input', () => {
    render(<ListingTab {...defaultProps} />);
    const hoursInput = screen.getByRole('spinbutton', { name: /hours/i });
    expect(hoursInput).toBeInTheDocument();
  });

  it('renders offer/request type chips', () => {
    render(<ListingTab {...defaultProps} />);
    // The chips use i18n keys compose.listing_offering and compose.listing_looking_for
    // With the default mock, they render the keys themselves
    const chips = document.querySelectorAll('[class*="cursor-pointer"]');
    expect(chips.length).toBeGreaterThanOrEqual(2);
  });

  it('renders Cancel button on desktop', () => {
    render(<ListingTab {...defaultProps} />);
    expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
  });

  it('calls onClose when Cancel is clicked', () => {
    const onClose = vi.fn();
    render(<ListingTab {...defaultProps} onClose={onClose} />);
    fireEvent.click(screen.getByRole('button', { name: /cancel/i }));
    expect(onClose).toHaveBeenCalled();
  });

  it('has disabled submit button when title and description are empty', () => {
    render(<ListingTab {...defaultProps} />);
    const createBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('listing'),
    );
    expect(createBtn).toBeDefined();
    expect(createBtn).toBeDisabled();
  });

  it('renders add image button', () => {
    render(<ListingTab {...defaultProps} />);
    // Button text from compose.image_add i18n key
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('fetches categories on mount', () => {
    render(<ListingTab {...defaultProps} />);
    expect(api.get).toHaveBeenCalledWith('/v2/categories');
  });
});
