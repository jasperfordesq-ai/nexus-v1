// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for the Universal Compose Hub
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, within } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

const mockGet = vi.fn().mockResolvedValue({ success: true, data: [], meta: {} });
const mockPost = vi.fn().mockResolvedValue({ success: true, data: { id: 1 } });

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
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

vi.mock('@/components/location', () => ({
  PlaceAutocompleteInput: ({ label, ...props }: Record<string, unknown>) => (
    <input placeholder={label as string} {...props} />
  ),
}));

import { ComposeHub } from './ComposeHub';

/** Helper: find the tab pills container (the flex-wrap div inside the modal header) */
function getTabPills() {
  // Tab pills are Chip elements with specific text — find by role
  return screen.queryAllByRole('button').filter(
    (el) => el.className.includes('chip') || el.closest('[class*="chip"]')
  );
}

/** Helper: find text that appears in the header span.font-semibold */
function getHeaderTitle() {
  const header = document.querySelector('header span.font-semibold');
  return header?.textContent?.trim() ?? '';
}

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

  it('renders without crashing', () => {
    render(<ComposeHub {...defaultProps} />);
    expect(getHeaderTitle()).toContain('Create');
  });

  it('shows all 5 tab labels when all features enabled', () => {
    render(<ComposeHub {...defaultProps} />);
    // Each tab label appears at least once (in the chip)
    for (const label of ['Post', 'Poll', 'Listing', 'Event', 'Goal']) {
      expect(screen.getAllByText(label).length).toBeGreaterThanOrEqual(1);
    }
  });

  it('hides Poll tab when polls feature is disabled', () => {
    mockHasFeature.mockImplementation((f: unknown) => f !== 'polls');
    render(<ComposeHub {...defaultProps} />);
    // "Poll" should not appear at all (not in tabs, not in header since default is Post)
    expect(screen.queryByText('Poll')).not.toBeInTheDocument();
    // Listing should still exist
    expect(screen.getAllByText('Listing').length).toBeGreaterThanOrEqual(1);
  });

  it('hides Listing tab when listings module is disabled', () => {
    mockHasModule.mockImplementation((m: unknown) => m !== 'listings');
    render(<ComposeHub {...defaultProps} />);
    expect(screen.queryByText('Listing')).not.toBeInTheDocument();
  });

  it('hides Event tab when events feature is disabled', () => {
    mockHasFeature.mockImplementation((f: unknown) => f !== 'events');
    render(<ComposeHub {...defaultProps} />);
    expect(screen.queryByText('Event')).not.toBeInTheDocument();
    // Goal should still be visible
    expect(screen.getAllByText('Goal').length).toBeGreaterThanOrEqual(1);
  });

  it('hides Goal tab when goals feature is disabled', () => {
    mockHasFeature.mockImplementation((f: unknown) => f !== 'goals');
    render(<ComposeHub {...defaultProps} />);
    expect(screen.queryByText('Goal')).not.toBeInTheDocument();
  });

  it('defaults to Post tab header', () => {
    render(<ComposeHub {...defaultProps} />);
    expect(getHeaderTitle()).toBe('Create Post');
  });

  it('opens to specified defaultTab', () => {
    render(<ComposeHub {...defaultProps} defaultTab="goal" />);
    expect(getHeaderTitle()).toBe('Create Goal');
  });

  it('switches tabs when clicking a tab pill', async () => {
    const user = userEvent.setup();
    render(<ComposeHub {...defaultProps} />);

    expect(getHeaderTitle()).toBe('Create Post');

    // Click the Listing chip — use getAllByText since "Listing" may appear in both chip and elsewhere
    const listingChips = screen.getAllByText('Listing');
    await user.click(listingChips[0]);

    await waitFor(() => {
      expect(getHeaderTitle()).toBe('Create Listing');
    });
  });

  it('shows Post tab content by default', () => {
    render(<ComposeHub {...defaultProps} />);
    expect(screen.getByPlaceholderText(/what's on your mind/i)).toBeInTheDocument();
  });

  it('shows Poll tab content when selected', async () => {
    const user = userEvent.setup();
    render(<ComposeHub {...defaultProps} />);

    const pollChips = screen.getAllByText('Poll');
    await user.click(pollChips[0]);

    await waitFor(() => {
      expect(screen.getByPlaceholderText(/ask a question/i)).toBeInTheDocument();
    });
  });

  it('does not render when isOpen is false', () => {
    render(<ComposeHub {...defaultProps} isOpen={false} />);
    expect(screen.queryByPlaceholderText(/what's on your mind/i)).not.toBeInTheDocument();
  });

  it('always shows Post tab regardless of feature gates', () => {
    mockHasFeature.mockReturnValue(false);
    mockHasModule.mockReturnValue(false);
    render(<ComposeHub {...defaultProps} />);
    // Post tab should still render (no gate)
    expect(getHeaderTitle()).toBe('Create Post');
    // Gated tabs should be hidden
    expect(screen.queryByText('Poll')).not.toBeInTheDocument();
    expect(screen.queryByText('Listing')).not.toBeInTheDocument();
    expect(screen.queryByText('Event')).not.toBeInTheDocument();
    expect(screen.queryByText('Goal')).not.toBeInTheDocument();
  });

  it('shows Goal tab content when selected', async () => {
    const user = userEvent.setup();
    render(<ComposeHub {...defaultProps} />);

    const goalChips = screen.getAllByText('Goal');
    await user.click(goalChips[0]);

    await waitFor(() => {
      expect(getHeaderTitle()).toBe('Create Goal');
    });
  });

  it('shows Event tab content when selected', async () => {
    const user = userEvent.setup();
    render(<ComposeHub {...defaultProps} />);

    const eventChips = screen.getAllByText('Event');
    await user.click(eventChips[0]);

    await waitFor(() => {
      expect(getHeaderTitle()).toBe('Create Event');
    });
  });
});
