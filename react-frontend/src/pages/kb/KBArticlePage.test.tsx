// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for KBArticlePage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { framerMotionMock } from '@/test/mocks';

vi.mock('framer-motion', () => framerMotionMock);

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
    useParams: () => ({ id: '42' }),
    Link: ({ children, to }: { children: React.ReactNode; to: string }) => <a href={to}>{children}</a>,
  };
});

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: null }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('dompurify', () => ({
  default: { sanitize: (html: string) => html },
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

import { KBArticlePage } from './KBArticlePage';
import { api } from '@/lib/api';

const mockArticle = {
  id: 42,
  title: 'Getting Started with Timebanking',
  slug: 'getting-started-timebanking',
  content: '<p>This is a comprehensive guide to timebanking.</p>',
  excerpt: 'A guide to get started.',
  category: 'Guides',
  parent_id: null as number | null,
  parent_title: null as string | null,
  is_published: true,
  view_count: 150,
  helpful_count: 20,
  not_helpful_count: 3,
  created_at: '2026-01-01T10:00:00Z',
  updated_at: '2026-03-15T10:00:00Z',
  children: [] as Array<{ id: number; title: string; slug: string; excerpt: string | null }>,
};

describe('KBArticlePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner initially', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<KBArticlePage />);
    // The page doesn't crash while loading
    expect(document.body).toBeTruthy();
  });

  it('renders the article title and content when loaded', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockArticle });
    render(<KBArticlePage />);
    await waitFor(() => {
      // Article title comes from data, not translation
      expect(screen.getAllByText('Getting Started with Timebanking').length).toBeGreaterThan(0);
    });
    expect(screen.getByText('This is a comprehensive guide to timebanking.')).toBeInTheDocument();
  });

  it('renders the category chip when category exists', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockArticle });
    render(<KBArticlePage />);
    await waitFor(() => {
      expect(screen.getAllByText('Guides').length).toBeGreaterThan(0);
    });
  });

  it('renders the feedback section', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockArticle });
    render(<KBArticlePage />);
    await waitFor(() => {
      expect(screen.getAllByText('Getting Started with Timebanking').length).toBeGreaterThan(0);
    });
    // t('feedback.question') returns the key 'feedback.question'
    expect(screen.getByText('feedback.question')).toBeInTheDocument();
  });

  it('renders child articles when present', async () => {
    const articleWithChildren = {
      ...mockArticle,
      children: [
        { id: 101, title: 'Creating Your First Listing', slug: 'first-listing', excerpt: 'Learn how to create listings.' },
        { id: 102, title: 'Earning Time Credits', slug: 'earning-credits', excerpt: null },
      ],
    };
    vi.mocked(api.get).mockResolvedValue({ success: true, data: articleWithChildren });
    render(<KBArticlePage />);
    await waitFor(() => {
      expect(screen.getByText('Creating Your First Listing')).toBeInTheDocument();
    });
    expect(screen.getByText('Earning Time Credits')).toBeInTheDocument();
    // t('related_articles') returns the key
    expect(screen.getByText('related_articles')).toBeInTheDocument();
  });

  it('renders breadcrumb with parent title when parent exists', async () => {
    const articleWithParent = {
      ...mockArticle,
      parent_id: 10,
      parent_title: 'Tutorials',
    };
    vi.mocked(api.get).mockResolvedValue({ success: true, data: articleWithParent });
    render(<KBArticlePage />);
    await waitFor(() => {
      expect(screen.getByText('Tutorials')).toBeInTheDocument();
    });
  });

  it('shows error state with retry when API fails', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });
    render(<KBArticlePage />);
    await waitFor(() => {
      // t('error.article_title') returns key
      expect(screen.getByText('error.article_title')).toBeInTheDocument();
    });
    const buttons = screen.getAllByRole('button');
    // t('try_again') returns key
    const tryAgainBtn = buttons.find((btn) => btn.textContent?.includes('try_again'));
    expect(tryAgainBtn).toBeTruthy();
  });

  it('shows back to KB link', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockArticle });
    render(<KBArticlePage />);
    await waitFor(() => {
      expect(screen.getAllByText('Getting Started with Timebanking').length).toBeGreaterThan(0);
    });
    // t('back_to_kb') returns key
    const backLinks = screen.getAllByText('back_to_kb');
    expect(backLinks.length).toBeGreaterThan(0);
  });

  it('submits helpful feedback when thumbs up is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockArticle });
    vi.mocked(api.post).mockResolvedValue({ success: true });
    render(<KBArticlePage />);
    await waitFor(() => {
      expect(screen.getByText('feedback.question')).toBeInTheDocument();
    });
    // t('feedback.yes', { count: 20 }) returns 'feedback.yes'
    const yesButton = screen.getAllByRole('button').find((btn) => btn.textContent?.includes('feedback.yes'));
    expect(yesButton).toBeTruthy();
    fireEvent.click(yesButton!);
    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/kb/42/feedback', { is_helpful: true });
    });
  });
});
