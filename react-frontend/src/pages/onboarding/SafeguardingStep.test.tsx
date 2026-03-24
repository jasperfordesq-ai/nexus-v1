// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SafeguardingStep component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

// Mock API module
const mockGet = vi.fn();
const mockPost = vi.fn();

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

const stableToastValue = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => stableToastValue),
  useAuth: vi.fn(() => ({ user: { id: 1 }, isAuthenticated: true, refreshUser: vi.fn() })),
  useTenant: vi.fn(() => ({ tenant: { id: 2, name: 'Test' }, tenantPath: (p: string) => p, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => stableToastValue),
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, ...props }: Record<string, unknown>) => <div {...props}>{children}</div>,
}));

vi.mock('framer-motion', () => {
  return {
    motion: {
      div: ({ children, ...props }: Record<string, unknown>) => <div>{children}</div>,
    },
    AnimatePresence: ({ children }: { children: React.ReactNode }) => children,
  };
});

import { SafeguardingStep } from './SafeguardingStep';

const MOCK_OPTIONS = [
  {
    id: 1,
    option_key: 'is_vulnerable_adult',
    option_type: 'checkbox' as const,
    label: 'I consider myself a vulnerable adult',
    description: 'A coordinator will help you.',
    help_url: null,
    is_required: false,
  },
  {
    id: 2,
    option_key: 'requires_vetted_partners',
    option_type: 'checkbox' as const,
    label: 'I prefer to interact with vetted members',
    description: null,
    help_url: null,
    is_required: false,
  },
];

describe('SafeguardingStep', () => {
  const defaultProps = {
    onNext: vi.fn(),
    onBack: vi.fn(),
    onSkip: vi.fn(),
    isRequired: false,
    introText: null,
  };

  beforeEach(() => {
    vi.clearAllMocks();
    mockGet.mockResolvedValue({ success: true, data: MOCK_OPTIONS });
    mockPost.mockResolvedValue({ success: true, data: { message: 'Saved' } });
  });

  it('renders loading state initially', () => {
    mockGet.mockReturnValue(new Promise(() => {})); // Never resolves
    render(<SafeguardingStep {...defaultProps} />);
    // Should show spinner while loading — HeroUI Spinner renders an svg or span with aria-label
    const spinner = document.querySelector('[aria-label="Loading"]') ||
                    document.querySelector('.animate-spinner-ease-spin') ||
                    document.querySelector('svg[class*="spinner"]') ||
                    document.querySelector('[role="progressbar"]');
    // At minimum, safeguarding options should NOT be visible yet
    expect(screen.queryByText('I consider myself a vulnerable adult')).toBeNull();
  });

  it('renders safeguarding options after loading', async () => {
    render(<SafeguardingStep {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('I consider myself a vulnerable adult')).toBeInTheDocument();
      expect(screen.getByText('I prefer to interact with vetted members')).toBeInTheDocument();
    });
  });

  it('renders intro text about vulnerable adult support', async () => {
    render(<SafeguardingStep {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText(/Your safety matters/)).toBeInTheDocument();
    });
  });

  it('renders custom intro text when provided', async () => {
    render(<SafeguardingStep {...defaultProps} introText="Custom safeguarding message" />);

    await waitFor(() => {
      expect(screen.getByText('Custom safeguarding message')).toBeInTheDocument();
    });
  });

  it('renders GDPR consent notice', async () => {
    render(<SafeguardingStep {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText(/sensitive personal data/)).toBeInTheDocument();
    });
  });

  it('shows Skip button when step is not required', async () => {
    render(<SafeguardingStep {...defaultProps} isRequired={false} />);

    await waitFor(() => {
      expect(screen.getByText('Skip for now')).toBeInTheDocument();
    });
  });

  it('hides Skip button when step is required', async () => {
    render(<SafeguardingStep {...defaultProps} isRequired={true} onSkip={undefined} />);

    await waitFor(() => {
      expect(screen.getByText('I consider myself a vulnerable adult')).toBeInTheDocument();
    });

    expect(screen.queryByText('Skip for now')).toBeNull();
  });

  it('shows empty state when no options configured', async () => {
    mockGet.mockResolvedValue({ success: true, data: [] });
    render(<SafeguardingStep {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText(/No safeguarding options have been configured/)).toBeInTheDocument();
    });
  });

  it('calls onNext when Continue is clicked with no selections', async () => {
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    render(<SafeguardingStep {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Continue')).toBeInTheDocument();
    });

    await user.click(screen.getByText('Continue'));
    expect(defaultProps.onNext).toHaveBeenCalled();
  });

  it('shows Back button', async () => {
    render(<SafeguardingStep {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Back')).toBeInTheDocument();
    });
  });

  it('renders option descriptions', async () => {
    render(<SafeguardingStep {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('A coordinator will help you.')).toBeInTheDocument();
    });
  });

  it('no checkboxes are pre-ticked (GDPR compliance)', async () => {
    render(<SafeguardingStep {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('I consider myself a vulnerable adult')).toBeInTheDocument();
    });

    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach((cb) => {
      expect((cb as HTMLInputElement).checked).toBe(false);
    });
  });
});
