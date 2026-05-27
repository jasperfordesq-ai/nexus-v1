// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for KnowledgeBasePage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { framerMotionMock } from '@/test/mocks';

vi.mock('@/lib/motion', () => framerMotionMock);

const stableT = (key: string, fallbackOrOpts?: string | Record<string, unknown>, _opts?: Record<string, unknown>) => {
  if (typeof fallbackOrOpts === 'string') return fallbackOrOpts;
  return key;
};
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: stableT,
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    Link: ({ children, to }: { children: React.ReactNode; to: string }) => <a href={to}>{children}</a>,
  };
});

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
  },
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/contexts/TenantContext', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test', slug: 'test', tagline: null },
    branding: { name: 'Test', logo_url: null },
    tenantSlug: 'test',
    tenantPath: (p: string) => '/test' + p,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  TenantProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
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

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description, action }: { title: string; description?: string; action?: React.ReactNode }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
      {action}
    </div>
  ),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { KnowledgeBasePage } from './KnowledgeBasePage';
import { api } from '@/lib/api';

const mockArticle = {
  id: 1,
  title: 'How to Create a Listing',
  slug: 'how-to-create-listing',
  content_type: 'markdown',
  category_id: 1,
  category_name: 'Getting Started',
  parent_article_id: null,
  is_published: true,
  views_count: 50,
  helpful_yes: 10,
  helpful_no: 1,
  created_at: '2026-01-01T10:00:00Z',
  updated_at: '2026-03-10T10:00:00Z',
  author: null,
  content_preview: 'Step-by-step guide to creating listings.',
};

const mockArticle2 = {
  id: 2,
  title: 'Understanding Time Credits',
  slug: 'understanding-time-credits',
  content_type: 'markdown',
  category_id: 2,
  category_name: 'Wallet',
  parent_article_id: null,
  is_published: true,
  views_count: 0,
  helpful_yes: 5,
  helpful_no: 0,
  created_at: '2026-02-01T10:00:00Z',
  updated_at: '2026-03-08T10:00:00Z',
  author: null,
};

describe('KnowledgeBasePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the page title and description', () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<KnowledgeBasePage />);
    // t('title') returns key 'title', t('description') returns key 'description'
    expect(screen.getByText('title')).toBeInTheDocument();
    expect(screen.getByText('description')).toBeInTheDocument();
  });

  it('renders the search input', () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<KnowledgeBasePage />);
    expect(screen.getByRole('textbox')).toBeInTheDocument();
  });

  it('shows empty state when no articles exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<KnowledgeBasePage />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('shows loading skeleton while data is being fetched', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<KnowledgeBasePage />);
    const cards = screen.getAllByTestId('glass-card');
    const pulsingCards = cards.filter((c) => c.className?.includes('animate-pulse'));
    expect(pulsingCards.length).toBeGreaterThan(0);
  });

  it('displays articles grouped by category when loaded', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [mockArticle, mockArticle2],
    });
    render(<KnowledgeBasePage />);
    await waitFor(() => {
      expect(screen.getByText('How to Create a Listing')).toBeInTheDocument();
    });
    expect(screen.getByText('Understanding Time Credits')).toBeInTheDocument();
    expect(screen.getAllByText('Getting Started').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Wallet').length).toBeGreaterThan(0);
  });

  it('displays article excerpts when present', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [mockArticle],
    });
    render(<KnowledgeBasePage />);
    await waitFor(() => {
      expect(screen.getByText('Step-by-step guide to creating listings.')).toBeInTheDocument();
    });
  });

  it('shows error state with retry when API fails', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });
    render(<KnowledgeBasePage />);
    await waitFor(() => {
      // t('error.title') returns key 'error.title'
      expect(screen.getByText('error.title')).toBeInTheDocument();
    });
    const buttons = screen.getAllByRole('button');
    // t('try_again') returns key 'try_again'
    const tryAgainBtn = buttons.find((btn) => btn.textContent?.includes('try_again'));
    expect(tryAgainBtn).toBeTruthy();
  });

  it('renders article links pointing to the correct paths', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [mockArticle],
    });
    render(<KnowledgeBasePage />);
    await waitFor(() => {
      expect(screen.getByText('How to Create a Listing')).toBeInTheDocument();
    });
    const link = screen.getByText('How to Create a Listing').closest('a');
    expect(link).toBeTruthy();
    expect(link?.getAttribute('href')).toBe('/test/kb/1');
  });
});
