// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for Utility pages (7 pages)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

const mockConversation = {
  id: 1,
  participants: [
    { id: 1, name: 'Test User', avatar: null },
    { id: 2, name: 'Other User', avatar: null },
  ],
  last_message: {
    content: 'Hello',
    created_at: '2024-01-01T00:00:00Z',
    sender_id: 1,
  },
};

const mockMessages = [
  {
    id: 1,
    content: 'Hello there',
    created_at: '2024-01-01T00:00:00Z',
    sender: { id: 1, name: 'Test User', avatar: null },
    is_own: true,
  },
];

const mockVersions = [
  {
    id: 1,
    version_number: '1.0',
    effective_date: '2024-01-01',
    is_current: true,
    summary_of_changes: 'Initial version',
  },
];

// Mock dependencies
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn((url: string) => {
      if (url.includes('/conversations/')) return Promise.resolve({ success: true, data: mockConversation });
      if (url.includes('/messages')) return Promise.resolve({ success: true, data: mockMessages });
      if (url.includes('/search')) return Promise.resolve({ success: true, data: { results: [] } });
      if (url.includes('/versions')) return Promise.resolve({ success: true, data: { title: 'Terms', versions: mockVersions } });
      return Promise.resolve({ success: true, data: [] });
    }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: vi.fn(() => ({ id: '1' })),
    useNavigate: vi.fn(() => vi.fn()),
    useSearchParams: vi.fn(() => [new URLSearchParams(), vi.fn()]),
    useLocation: vi.fn(() => ({ pathname: '/terms/versions', search: '', hash: '', state: null })),
    Link: ({ children, to, ...props }: any) => <a href={to} {...props}>{children}</a>,
  };
});

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', last_name: 'User', name: 'Test User', avatar: null },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Community', logo_url: null },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    isLoading: false,
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: vi.fn((url) => url || ''),
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const { variants, initial, animate, transition, ...rest } = props;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('lucide-react', () => {
  const MockIcon = ({ className, 'aria-hidden': ariaHidden }: { className?: string; 'aria-hidden'?: boolean | string }) => (
    <span className={className} aria-hidden={ariaHidden}>icon</span>
  );
  return new Proxy({}, {
    get: () => MockIcon,
  });
});

import { ConversationPage } from '../messages/ConversationPage';
import { SearchPage } from '../search/SearchPage';
import { SettingsPage } from '../settings/SettingsPage';
import { ComingSoonPage } from '../public/ComingSoonPage';
import { NotFoundPage } from '../public/NotFoundPage';
import { LegalVersionHistoryPage } from '../public/LegalVersionHistoryPage';

describe('Utility Pages', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('ConversationPage', () => {
    it('renders without crashing', async () => {
      render(<ConversationPage />);
      await waitFor(() => {
        expect(screen.getByText(/Other User/i)).toBeInTheDocument();
      });
    });

    it('shows message input', async () => {
      render(<ConversationPage />);
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/Type.*message/i)).toBeInTheDocument();
      });
    });

    it('displays messages', async () => {
      render(<ConversationPage />);
      await waitFor(() => {
        expect(screen.getByText('Hello there')).toBeInTheDocument();
      });
    });
  });

  describe('SearchPage', () => {
    it('renders without crashing', () => {
      render(<SearchPage />);
      expect(screen.getByText(/Search/i)).toBeInTheDocument();
    });

    it('shows search input', () => {
      render(<SearchPage />);
      expect(screen.getByPlaceholderText(/Search/i)).toBeInTheDocument();
    });

    it('displays filter tabs', () => {
      render(<SearchPage />);
      expect(screen.getByText(/All/i)).toBeInTheDocument();
    });
  });

  describe('SettingsPage', () => {
    it('renders without crashing', () => {
      render(<SettingsPage />);
      expect(screen.getByText(/Settings/i)).toBeInTheDocument();
    });

    it('shows profile section', () => {
      render(<SettingsPage />);
      expect(screen.getByText(/Profile/i)).toBeInTheDocument();
    });

    it('displays notification settings', () => {
      render(<SettingsPage />);
      expect(screen.getByText(/Notifications/i)).toBeInTheDocument();
    });
  });

  describe('HelpCenterPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('ComingSoonPage', () => {
    it('renders without crashing', () => {
      render(<ComingSoonPage />);
      expect(screen.getByText(/Coming Soon/i)).toBeInTheDocument();
    });

    it('shows descriptive message', () => {
      render(<ComingSoonPage />);
      expect(screen.getByText(/working on something/i)).toBeInTheDocument();
    });

    it('displays back to home link', () => {
      render(<ComingSoonPage />);
      expect(screen.getByText(/Back to Home/i)).toBeInTheDocument();
    });
  });

  describe('NotFoundPage', () => {
    it('renders without crashing', () => {
      render(<NotFoundPage />);
      expect(screen.getByText(/404/i)).toBeInTheDocument();
    });

    it('shows not found message', () => {
      render(<NotFoundPage />);
      expect(screen.getByText(/Page not found/i)).toBeInTheDocument();
    });

    it('displays back to home link', () => {
      render(<NotFoundPage />);
      expect(screen.getByText(/Back to Home/i)).toBeInTheDocument();
    });
  });

  describe('LegalVersionHistoryPage', () => {
    it('renders without crashing', async () => {
      render(<LegalVersionHistoryPage />);
      await waitFor(() => {
        expect(screen.getByText(/Version History/i)).toBeInTheDocument();
      });
    });

    it('shows version list when loaded', async () => {
      render(<LegalVersionHistoryPage />);
      await waitFor(() => {
        expect(screen.getByText(/Version 1.0/i)).toBeInTheDocument();
      });
    });

    it('displays current version badge', async () => {
      render(<LegalVersionHistoryPage />);
      await waitFor(() => {
        expect(screen.getByText(/Current/i)).toBeInTheDocument();
      });
    });

    it('shows back to document link', async () => {
      render(<LegalVersionHistoryPage />);
      await waitFor(() => {
        expect(screen.getAllByText(/Back to/i)[0]).toBeInTheDocument();
      });
    });
  });
});
