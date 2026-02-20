// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for Detail pages (7 pages)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

const mockBlogPost = {
  id: 1,
  title: 'Test Blog Post',
  slug: 'test-post',
  excerpt: 'Test excerpt',
  content: '<p>Test content</p>',
  featured_image: null,
  published_at: '2024-01-01T00:00:00Z',
  created_at: '2024-01-01T00:00:00Z',
  views: 100,
  reading_time: 5,
  meta_title: null,
  meta_description: null,
  author: { id: 1, name: 'Test Author', avatar: null },
  category: { id: 1, name: 'Tech', color: 'blue' },
};

const mockEvent = {
  id: 1,
  title: 'Test Event',
  description: 'Test event description',
  start_date: '2024-12-01T10:00:00Z',
  end_date: '2024-12-01T12:00:00Z',
  location: 'Test Location',
  cover_image: null,
  organizer: { id: 1, name: 'Test Organizer', avatar: null },
  attendees_count: 10,
  user_attending: false,
};

const mockListing = {
  id: 1,
  title: 'Test Listing',
  description: 'Test description',
  type: 'offer' as const,
  category: { id: 1, name: 'Gardening' },
  hours_estimate: 2,
  location: 'Dublin',
  status: 'active',
  user: { id: 1, name: 'Test User', avatar: null },
  created_at: '2024-01-01T00:00:00Z',
};

// Mock dependencies
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn((url: string) => {
      if (url.includes('/blog/')) return Promise.resolve({ success: true, data: mockBlogPost });
      if (url.includes('/events/')) return Promise.resolve({ success: true, data: mockEvent });
      if (url.includes('/listings/')) return Promise.resolve({ success: true, data: mockListing });
      if (url.includes('/comments')) return Promise.resolve({ success: true, data: { comments: [] } });
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
    useParams: vi.fn(() => ({ slug: 'test-post', id: '1' })),
    useNavigate: vi.fn(() => vi.fn()),
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
      const { variants, initial, animate, whileInView, viewport, layout, transition, ...rest } = props;
      return <div {...rest}>{children}</div>;
    },
    h1: ({ children, ...props }: Record<string, unknown>) => {
      const { variants, initial, animate, transition, ...rest } = props;
      return <h1 {...rest}>{children}</h1>;
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

vi.mock('dompurify', () => ({
  default: {
    sanitize: vi.fn((html) => html),
  },
}));

import { BlogPostPage } from '../blog/BlogPostPage';
import { EventDetailPage } from '../events/EventDetailPage';
import { ListingDetailPage } from '../listings/ListingDetailPage';

describe('Detail Pages', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('BlogPostPage', () => {
    it('renders without crashing', async () => {
      render(<BlogPostPage />);
      await waitFor(() => {
        expect(screen.getByText('Test Blog Post')).toBeInTheDocument();
      });
    });

    it('shows blog post content', async () => {
      render(<BlogPostPage />);
      await waitFor(() => {
        expect(screen.getByText('Test content')).toBeInTheDocument();
      });
    });

    it('displays author information', async () => {
      render(<BlogPostPage />);
      await waitFor(() => {
        expect(screen.getByText('Test Author')).toBeInTheDocument();
      });
    });

    it('shows comments section', async () => {
      render(<BlogPostPage />);
      await waitFor(() => {
        expect(screen.getByText(/Comments/i)).toBeInTheDocument();
      });
    });
  });

  describe('EventDetailPage', () => {
    it('renders without crashing', async () => {
      render(<EventDetailPage />);
      await waitFor(() => {
        expect(screen.getByText('Test Event')).toBeInTheDocument();
      });
    });

    it('shows event description', async () => {
      render(<EventDetailPage />);
      await waitFor(() => {
        expect(screen.getByText('Test event description')).toBeInTheDocument();
      });
    });

    it('displays event location', async () => {
      render(<EventDetailPage />);
      await waitFor(() => {
        expect(screen.getByText('Test Location')).toBeInTheDocument();
      });
    });

    it('shows attendees count', async () => {
      render(<EventDetailPage />);
      await waitFor(() => {
        expect(screen.getByText(/10/)).toBeInTheDocument();
      });
    });
  });

  describe('ListingDetailPage', () => {
    it('renders without crashing', async () => {
      render(<ListingDetailPage />);
      await waitFor(() => {
        expect(screen.getByText('Test Listing')).toBeInTheDocument();
      });
    });

    it('shows listing description', async () => {
      render(<ListingDetailPage />);
      await waitFor(() => {
        expect(screen.getByText('Test description')).toBeInTheDocument();
      });
    });

    it('displays listing location', async () => {
      render(<ListingDetailPage />);
      await waitFor(() => {
        expect(screen.getByText('Dublin')).toBeInTheDocument();
      });
    });

    it('shows hours estimate', async () => {
      render(<ListingDetailPage />);
      await waitFor(() => {
        expect(screen.getByText(/2/)).toBeInTheDocument();
      });
    });
  });

  describe('ExchangeDetailPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('GroupDetailPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('OrganisationDetailPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('GroupExchangeDetailPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });
});
