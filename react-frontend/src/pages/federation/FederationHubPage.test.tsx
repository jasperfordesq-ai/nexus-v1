// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for FederationHubPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

// Mock API module
const mockApiGet = vi.fn();
const mockApiPost = vi.fn();

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockApiGet(...args),
    post: (...args: unknown[]) => mockApiPost(...args),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

// Mock contexts - must include ToastProvider since test-utils.tsx uses it
vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, ...props }: Record<string, unknown>) => <div {...props}>{children}</div>,
}));

vi.mock('@/components/navigation', () => ({
  Breadcrumbs: ({ items }: { items: { label: string }[] }) => (
    <nav data-testid="breadcrumbs">
      {items.map((item, i) => (
        <span key={i}>{item.label}</span>
      ))}
    </nav>
  ),
}));

vi.mock('@/components/seo', () => ({ PageMeta: () => null }));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const { variants, initial, animate, layout, transition, custom, ...rest } = props;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import FederationHubPage from './FederationHubPage';

describe('FederationHubPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state initially', () => {
    // API never resolves, so loading stays
    mockApiGet.mockReturnValue(new Promise(() => {}));

    render(<FederationHubPage />);
    expect(screen.getByText('Loading federation data...')).toBeInTheDocument();
  });

  it('shows breadcrumbs', () => {
    mockApiGet.mockReturnValue(new Promise(() => {}));

    render(<FederationHubPage />);
    expect(screen.getByTestId('breadcrumbs')).toBeInTheDocument();
    expect(screen.getByText('Federation')).toBeInTheDocument();
  });

  it('shows error state when API fails', async () => {
    mockApiGet.mockRejectedValueOnce(new Error('Network error'));

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('Unable to Load')).toBeInTheDocument();
    });
    expect(screen.getByText('Something went wrong. Please try again.')).toBeInTheDocument();
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });

  it('shows Federation Not Available when tenant federation is disabled', async () => {
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: {
        enabled: false,
        tenant_federation_enabled: false,
        partnerships_count: 0,
      },
    });

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('Federation Not Available')).toBeInTheDocument();
    });
  });

  it('shows hero section with Enable Federation button when not opted in', async () => {
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: {
        enabled: false,
        tenant_federation_enabled: true,
        partnerships_count: 0,
      },
    });

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('Federation Hub')).toBeInTheDocument();
    });
    expect(screen.getByText('Connect Beyond Your Community')).toBeInTheDocument();
    expect(screen.getByText('Enable Federation')).toBeInTheDocument();
  });

  it('shows How It Works cards when not opted in', async () => {
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: {
        enabled: false,
        tenant_federation_enabled: true,
        partnerships_count: 0,
      },
    });

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('How It Works')).toBeInTheDocument();
    });
    expect(screen.getByText('Discover Partners')).toBeInTheDocument();
    expect(screen.getByText('Connect with Members')).toBeInTheDocument();
    expect(screen.getByText('Exchange Across Communities')).toBeInTheDocument();
  });

  it('shows dashboard when opted in with partner communities', async () => {
    // First call: status
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: {
        enabled: true,
        tenant_federation_enabled: true,
        partnerships_count: 2,
      },
    });
    // Second call: partners
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: [
        {
          id: 1,
          name: 'Partner Community A',
          logo: null,
          location: 'Dublin',
          tagline: 'A great community',
          federation_level: 2,
          federation_level_name: 'Standard',
          member_count: 50,
        },
      ],
    });
    // Third call: activity
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: [],
    });

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('Federation Hub')).toBeInTheDocument();
    });
    // Quick links section
    expect(screen.getByText('Explore the Network')).toBeInTheDocument();
    // Partner communities section
    expect(screen.getByText('Partner Community A')).toBeInTheDocument();
  });

  it('shows quick navigation links when opted in', async () => {
    mockApiGet.mockResolvedValueOnce({
      success: true,
      data: {
        enabled: true,
        tenant_federation_enabled: true,
        partnerships_count: 0,
      },
    });
    mockApiGet.mockResolvedValueOnce({ success: true, data: [] });
    mockApiGet.mockResolvedValueOnce({ success: true, data: [] });

    render(<FederationHubPage />);

    await waitFor(() => {
      expect(screen.getByText('Explore the Network')).toBeInTheDocument();
    });
    // Quick link titles - some may appear in both title and description,
    // so use getAllByText where needed
    expect(screen.getByText('Federated Members')).toBeInTheDocument();
    expect(screen.getAllByText(/Federated Messages/).length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Federated Listings')).toBeInTheDocument();
    expect(screen.getByText('Federated Events')).toBeInTheDocument();
    expect(screen.getByText('Federation Settings')).toBeInTheDocument();
  });
});
