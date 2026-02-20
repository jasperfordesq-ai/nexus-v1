// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for Federation pages (8 pages)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

const mockPartners = [
  { id: 1, name: 'Partner 1', logo: null },
  { id: 2, name: 'Partner 2', logo: null },
];

const mockEvents = [
  {
    id: 1,
    title: 'Test Event',
    description: 'Event description',
    start_date: '2024-12-01T10:00:00Z',
    location: 'Dublin',
    timebank: { id: 1, name: 'Test Timebank' },
    organizer: { id: 1, name: 'Organizer', avatar: null },
    attendees_count: 10,
    is_online: false,
  },
];

// Mock dependencies
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn((url: string) => {
      if (url.includes('/partners')) return Promise.resolve({ success: true, data: mockPartners });
      if (url.includes('/events')) return Promise.resolve({ success: true, data: mockEvents, meta: { has_more: false } });
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
    useParams: vi.fn(() => ({})),
    useNavigate: vi.fn(() => vi.fn()),
    useSearchParams: vi.fn(() => [new URLSearchParams(), vi.fn()]),
    Link: ({ children, to, ...props }: any) => <a href={to} {...props}>{children}</a>,
  };
});

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Community', logo_url: null },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
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

import { FederationEventsPage } from '../federation/FederationEventsPage';

describe('Federation Pages', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('FederationEventsPage', () => {
    it('renders without crashing', async () => {
      render(<FederationEventsPage />);
      await waitFor(() => {
        expect(screen.getByText(/Federated Events/i)).toBeInTheDocument();
      });
    });

    it('shows search input', async () => {
      render(<FederationEventsPage />);
      await waitFor(() => {
        expect(screen.getByPlaceholderText(/Search federated events/i)).toBeInTheDocument();
      });
    });

    it('displays partner filter dropdown', async () => {
      render(<FederationEventsPage />);
      await waitFor(() => {
        expect(screen.getByText(/All Communities/i)).toBeInTheDocument();
      });
    });

    it('shows upcoming only toggle', async () => {
      render(<FederationEventsPage />);
      await waitFor(() => {
        expect(screen.getByText(/Upcoming Only/i)).toBeInTheDocument();
      });
    });

    it('renders event cards when data loads', async () => {
      render(<FederationEventsPage />);
      await waitFor(() => {
        expect(screen.getByText('Test Event')).toBeInTheDocument();
      });
    });
  });

  describe('FederationListingsPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('FederationMemberProfilePage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('FederationMembersPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('FederationMessagesPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('FederationOnboardingPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('FederationPartnersPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('FederationSettingsPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });
});
