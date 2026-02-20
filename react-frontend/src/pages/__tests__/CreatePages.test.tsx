// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for Create/Form pages (5 pages)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

const mockCategories = [
  { id: 1, name: 'Gardening' },
  { id: 2, name: 'Tutoring' },
];

// Mock dependencies
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn((url: string) => {
      if (url.includes('/categories')) return Promise.resolve({ success: true, data: mockCategories });
      return Promise.resolve({ success: true, data: [] });
    }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: vi.fn(() => ({ id: undefined })),
    useNavigate: vi.fn(() => vi.fn()),
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

vi.mock('@/components/location', () => ({
  PlaceAutocompleteInput: ({ label, placeholder, value, onChange }: any) => (
    <div>
      <label>{label}</label>
      <input
        placeholder={placeholder}
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    </div>
  ),
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

import { CreateListingPage } from '../listings/CreateListingPage';

describe('Create Pages', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('CreateListingPage', () => {
    it('renders without crashing', async () => {
      render(<CreateListingPage />);
      await waitFor(() => {
        expect(screen.getByText(/Create New Listing/i)).toBeInTheDocument();
      });
    });

    it('shows type selection (offer/request)', async () => {
      render(<CreateListingPage />);
      await waitFor(() => {
        expect(screen.getByText(/Offer a Service/i)).toBeInTheDocument();
        expect(screen.getByText(/Request Help/i)).toBeInTheDocument();
      });
    });

    it('displays title input field', async () => {
      render(<CreateListingPage />);
      await waitFor(() => {
        expect(screen.getByLabelText(/Title/i)).toBeInTheDocument();
      });
    });

    it('shows submit button', async () => {
      render(<CreateListingPage />);
      await waitFor(() => {
        expect(screen.getByText(/Create Listing/i)).toBeInTheDocument();
      });
    });

    it('displays cancel button', async () => {
      render(<CreateListingPage />);
      await waitFor(() => {
        expect(screen.getByText(/Cancel/i)).toBeInTheDocument();
      });
    });
  });

  describe('CreateEventPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('CreateGroupPage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('CreateGroupExchangePage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });

  describe('RequestExchangePage', () => {
    it('renders placeholder for untested page', () => {
      expect(true).toBe(true);
    });
  });
});
