// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CategorySelect component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
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

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test' }, tenantPath: (p: string) => '/test' + p, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { CategorySelect } from './CategorySelect';

const CATEGORIES = [
  { id: 1, slug: 'transport', name: 'Transport', color: '#3b82f6', sort_order: 1, is_system: false },
  { id: 2, slug: 'food', name: 'Food & Drink', color: '#10b981', sort_order: 2, is_system: false },
  { id: 3, slug: 'health', name: 'Health', color: null, sort_order: 3, is_system: true },
];

describe('CategorySelect — renders nothing while loading or empty', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // The test-utils wrapper always renders provider scaffolding (ToastProvider etc.) so
  // container.firstChild is never null. Instead we assert that no interactive control
  // (button / combobox / listbox) appears, which is the externally-visible behaviour
  // of returning null from the component.

  it('renders no interactive control while the API call is in flight', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<CategorySelect onChange={vi.fn()} />);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
  });

  it('renders no interactive control when the API returns an empty array', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
  });

  it('renders no interactive control when the API returns success:false', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: false });
    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
  });

  it('renders no interactive control when the API call throws', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));
    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
  });
});

describe('CategorySelect — fetching', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches from /v2/wallet/categories on mount', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: CATEGORIES });
    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/wallet/categories');
    });
  });

  it('handles the wrapped { items: [...] } response shape', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: CATEGORIES } });
    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      // Select trigger button should appear when categories load from items wrapper
      expect(screen.getByRole('button')).toBeInTheDocument();
    });
  });
});

describe('CategorySelect — populated render', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: CATEGORIES });
  });

  it('renders the select trigger button after categories load', async () => {
    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(screen.getByRole('button')).toBeInTheDocument();
    });
  });

  it('uses the custom label prop', async () => {
    render(<CategorySelect onChange={vi.fn()} label="Transaction type" />);
    await waitFor(() => {
      expect(screen.getByText('Transaction type')).toBeInTheDocument();
    });
  });

  it('uses the default label "Category" when none is provided', async () => {
    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => {
      expect(screen.getByText('Category')).toBeInTheDocument();
    });
  });

  it('shows category names in the listbox when the trigger is opened', async () => {
    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => expect(screen.getByRole('button')).toBeInTheDocument());

    // Open the listbox via the trigger button
    fireEvent.click(screen.getByRole('button'));

    // After opening, options appear as role="option" elements
    await waitFor(() => {
      expect(screen.getByRole('option', { name: /Transport/i })).toBeInTheDocument();
    });
  });

  it('renders all three category names when the listbox is open', async () => {
    render(<CategorySelect onChange={vi.fn()} />);
    await waitFor(() => expect(screen.getByRole('button')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(screen.getByRole('option', { name: /Transport/i })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: /Food & Drink/i })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: /Health/i })).toBeInTheDocument();
    });
  });
});

describe('CategorySelect — onChange callback', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: CATEGORIES });
  });

  it('calls onChange with the correct numeric id when an option is selected', async () => {
    const onChange = vi.fn();
    render(<CategorySelect onChange={onChange} />);

    await waitFor(() => expect(screen.getByRole('button')).toBeInTheDocument());

    // Open the listbox via the trigger button
    fireEvent.click(screen.getByRole('button'));

    // Wait for options to appear in the listbox popover
    await waitFor(() => expect(screen.getByRole('option', { name: /Transport/i })).toBeInTheDocument());

    // Click the Transport option (id=1)
    fireEvent.click(screen.getByRole('option', { name: /Transport/i }));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith(1);
    });
  });

  it('does not auto-fire onChange on initial mount', async () => {
    const onChange = vi.fn();
    render(<CategorySelect onChange={onChange} value={1} />);

    await waitFor(() => expect(screen.getByRole('button')).toBeInTheDocument());

    // onChange must NOT fire simply because the component mounted with a pre-selected value
    expect(onChange).not.toHaveBeenCalled();
  });

  it('calls onChange with the id of the second category when Food & Drink is selected', async () => {
    const onChange = vi.fn();
    render(<CategorySelect onChange={onChange} />);

    await waitFor(() => expect(screen.getByRole('button')).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button'));
    await waitFor(() => expect(screen.getByRole('option', { name: /Food & Drink/i })).toBeInTheDocument());

    fireEvent.click(screen.getByRole('option', { name: /Food & Drink/i }));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith(2);
    });
  });
});
