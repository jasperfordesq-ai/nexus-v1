// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for the Universal Compose Hub
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

const mockGet = vi.fn().mockResolvedValue({ success: true, data: [], meta: {} });
const mockPost = vi.fn().mockResolvedValue({ success: true, data: { id: 1 } });

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
    upload: (...args: unknown[]) => mockPost(...args),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

const mockHasFeature = vi.fn(() => true);
const mockHasModule = vi.fn(() => true);

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', avatar: null },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: (...args: unknown[]) => mockHasFeature(...args),
    hasModule: (...args: unknown[]) => mockHasModule(...args),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url) => url || ''),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, string>) => {
      const map: Record<string, string> = {
        'compose.tab_post': 'Post',
        'compose.tab_poll': 'Poll',
        'compose.tab_listing': 'Listing',
        'compose.tab_event': 'Event',
        'compose.tab_goal': 'Goal',
        'compose.create_title': `Create ${opts?.type ?? ''}`,
        'compose.cancel': 'Cancel',
        'compose.post_button': 'Post',
        'compose.create_poll': 'Create Poll',
        'compose.create_listing': 'Create Listing',
        'compose.create_event': 'Create Event',
        'compose.create_goal': 'Create Goal',
        'compose.poll_question_placeholder': 'Ask a question...',
        'whats_on_your_mind': "What's on your mind?",
        'compose.emoji_search': 'Search emoji',
        'compose.voice_input': 'Voice input',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en', changeLanguage: vi.fn() },
  }),
  Trans: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  initReactI18next: { type: '3rdParty', init: vi.fn() },
}));

vi.mock('@/components/location', () => ({
  PlaceAutocompleteInput: ({ label, ...props }: Record<string, unknown>) => (
    <input placeholder={label as string} {...props} />
  ),
}));

// Mock useMediaQuery to return false (desktop) by default
vi.mock('@/hooks/useMediaQuery', () => ({
  useMediaQuery: vi.fn(() => false),
}));

import { ComposeHub } from './ComposeHub';

describe('ComposeHub', () => {
  const defaultProps = {
    isOpen: true,
    onClose: vi.fn(),
    onSuccess: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
    mockHasModule.mockReturnValue(true);
    mockGet.mockResolvedValue({ success: true, data: [], meta: {} });
    mockPost.mockResolvedValue({ success: true, data: { id: 1 } });
  });

  it('renders without crashing when open', () => {
    render(<ComposeHub {...defaultProps} />);
    // Should render the "Create Post" title (default tab)
    expect(screen.getByText(/Create Post/)).toBeInTheDocument();
  });

  it('shows all 5 tab labels when all features enabled', () => {
    render(<ComposeHub {...defaultProps} />);
    for (const label of ['Post', 'Poll', 'Listing', 'Event', 'Goal']) {
      expect(screen.getAllByText(label).length).toBeGreaterThanOrEqual(1);
    }
  });

  it('hides Poll tab when polls feature is disabled', () => {
    mockHasFeature.mockImplementation((f: unknown) => f !== 'polls');
    render(<ComposeHub {...defaultProps} />);
    // Poll should not appear in the tabs
    // The word "Post" may appear multiple times (tab + button), but "Poll" should be absent
    const pollElements = screen.queryAllByText('Poll');
    expect(pollElements.length).toBe(0);
    // Listing should still exist
    expect(screen.getAllByText('Listing').length).toBeGreaterThanOrEqual(1);
  });

  it('hides Listing tab when listings module is disabled', () => {
    mockHasModule.mockImplementation((m: unknown) => m !== 'listings');
    render(<ComposeHub {...defaultProps} />);
    expect(screen.queryAllByText('Listing').length).toBe(0);
  });

  it('hides Event tab when events feature is disabled', () => {
    mockHasFeature.mockImplementation((f: unknown) => f !== 'events');
    render(<ComposeHub {...defaultProps} />);
    expect(screen.queryAllByText('Event').length).toBe(0);
    // Goal should still be visible
    expect(screen.getAllByText('Goal').length).toBeGreaterThanOrEqual(1);
  });

  it('hides Goal tab when goals feature is disabled', () => {
    mockHasFeature.mockImplementation((f: unknown) => f !== 'goals');
    render(<ComposeHub {...defaultProps} />);
    expect(screen.queryAllByText('Goal').length).toBe(0);
  });

  it('defaults to Post tab header', () => {
    render(<ComposeHub {...defaultProps} />);
    expect(screen.getByText(/Create Post/)).toBeInTheDocument();
  });

  it('opens to specified defaultTab', () => {
    render(<ComposeHub {...defaultProps} defaultTab="goal" />);
    // "Create Goal" appears in both header and submit button
    expect(screen.getAllByText(/Create Goal/).length).toBeGreaterThanOrEqual(1);
  });

  it('switches tabs when clicking a tab', async () => {
    const user = userEvent.setup();
    render(<ComposeHub {...defaultProps} />);

    expect(screen.getByText(/Create Post/)).toBeInTheDocument();

    // Click the Listing tab
    const listingTabs = screen.getAllByText('Listing');
    await user.click(listingTabs[0]);

    await waitFor(() => {
      // "Create Listing" appears in both header and submit button
      expect(screen.getAllByText(/Create Listing/).length).toBeGreaterThanOrEqual(1);
    });
  });

  it('does not render content when isOpen is false', () => {
    render(<ComposeHub {...defaultProps} isOpen={false} />);
    expect(screen.queryByText(/Create Post/)).not.toBeInTheDocument();
  });

  it('always shows Post tab regardless of feature gates', () => {
    mockHasFeature.mockReturnValue(false);
    mockHasModule.mockReturnValue(false);
    render(<ComposeHub {...defaultProps} />);
    // Post tab should still render (no gate)
    expect(screen.getByText(/Create Post/)).toBeInTheDocument();
    // Gated tabs should be hidden
    expect(screen.queryAllByText('Poll').length).toBe(0);
    expect(screen.queryAllByText('Listing').length).toBe(0);
    expect(screen.queryAllByText('Event').length).toBe(0);
    expect(screen.queryAllByText('Goal').length).toBe(0);
  });

  it('shows Goal tab content when selected', async () => {
    const user = userEvent.setup();
    render(<ComposeHub {...defaultProps} />);

    const goalTabs = screen.getAllByText('Goal');
    await user.click(goalTabs[0]);

    await waitFor(() => {
      // "Create Goal" appears in both header and submit button
      expect(screen.getAllByText(/Create Goal/).length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows Event tab content when selected', async () => {
    const user = userEvent.setup();
    render(<ComposeHub {...defaultProps} />);

    const eventTabs = screen.getAllByText('Event');
    await user.click(eventTabs[0]);

    await waitFor(() => {
      // "Create Event" appears in both header and submit button
      expect(screen.getAllByText(/Create Event/).length).toBeGreaterThanOrEqual(1);
    });
  });
});
