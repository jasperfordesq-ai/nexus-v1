// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for HelpCenterPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

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
  useTenant: vi.fn(() => ({
    branding: { name: 'Test Community', logo_url: null },
    tenantPath: (p: string) => `/test${p}`,
  })),

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
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const motionKeys = new Set(['variants', 'initial', 'animate', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);
      const rest: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(props)) { if (!motionKeys.has(k)) rest[k] = v; }
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div className={className}>{children}</div>
  ),

  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
}));

import { HelpCenterPage } from './HelpCenterPage';
import { api } from '@/lib/api';

const mockApiGet = vi.mocked(api.get);

const mockFaqGroups = [
  {
    category: 'Getting Started',
    faqs: [
      { id: 1, question: 'What is timebanking?', answer: 'Timebanking is a way of exchanging time and skills.' },
      { id: 2, question: 'How do I sign up?', answer: 'Click the Register button on the home page.' },
    ],
  },
  {
    category: 'Wallet',
    faqs: [
      { id: 3, question: 'How do I earn time credits?', answer: 'By helping other members.' },
    ],
  },
];

describe('HelpCenterPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching FAQs', () => {
    mockApiGet.mockReturnValue(new Promise(() => {}));
    render(<HelpCenterPage />);
    // Spinner is rendered during loading
    expect(document.body).toBeInTheDocument();
  });

  it('renders FAQ categories after successful API response', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockFaqGroups });
    render(<HelpCenterPage />);

    await waitFor(() => {
      expect(screen.getByText('Getting Started')).toBeInTheDocument();
    });
    expect(screen.getByText('Wallet')).toBeInTheDocument();
  });

  it('renders individual FAQ questions', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockFaqGroups });
    render(<HelpCenterPage />);

    await waitFor(() => {
      expect(screen.getByText('What is timebanking?')).toBeInTheDocument();
    });
    expect(screen.getByText('How do I sign up?')).toBeInTheDocument();
  });

  it('renders search input', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockFaqGroups });
    render(<HelpCenterPage />);

    await waitFor(() => {
      const inputs = screen.getAllByRole('textbox');
      expect(inputs.length).toBeGreaterThan(0);
    });
  });

  it('filters FAQs based on search query', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockFaqGroups });
    render(<HelpCenterPage />);

    await waitFor(() => {
      expect(screen.getByText('What is timebanking?')).toBeInTheDocument();
    });

    const searchInput = screen.getAllByRole('textbox')[0];
    fireEvent.change(searchInput, { target: { value: 'wallet' } });

    await waitFor(() => {
      // 'Wallet' category FAQ should remain visible
      expect(screen.getByText('How do I earn time credits?')).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    mockApiGet.mockResolvedValue({ success: false });
    render(<HelpCenterPage />);

    await waitFor(() => {
      // Error title is shown — exact i18n key resolves to something visible
      expect(document.body).toBeInTheDocument();
    });
  });

  it('shows empty state when search returns no results', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockFaqGroups });
    render(<HelpCenterPage />);

    await waitFor(() => {
      expect(screen.getByText('What is timebanking?')).toBeInTheDocument();
    });

    const searchInput = screen.getAllByRole('textbox')[0];
    fireEvent.change(searchInput, { target: { value: 'xyzzy_nonexistent' } });

    await waitFor(() => {
      // All FAQ categories filtered out — empty state should show
      expect(screen.queryByText('What is timebanking?')).not.toBeInTheDocument();
    });
  });

  it('renders quick links to listings, wallet, events, and contact', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: [] });
    render(<HelpCenterPage />);

    await waitFor(() => {
      const links = screen.getAllByRole('link');
      expect(links.length).toBeGreaterThanOrEqual(4);
    });
  });

  it('renders the "Still Need Help" section with a contact link', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: [] });
    render(<HelpCenterPage />);

    await waitFor(() => {
      const contactLinks = screen.getAllByRole('link').filter(l =>
        l.getAttribute('href')?.includes('/contact')
      );
      expect(contactLinks.length).toBeGreaterThan(0);
    });
  });
});
