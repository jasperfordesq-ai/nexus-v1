// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AccessibilityTab
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { framerMotionMock } from '@/test/mocks';

vi.mock('framer-motion', () => framerMotionMock);

const stableT = (_key: string, fallback: string, _opts?: object) => fallback ?? _key;
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: stableT,
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    put: vi.fn().mockResolvedValue({ success: true }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
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

import { AccessibilityTab } from './AccessibilityTab';
import { api } from '@/lib/api';

const mockNeed = {
  id: 1,
  need_type: 'mobility' as const,
  description: 'Wheelchair access required',
  accommodations_required: 'Ground floor venues only',
  emergency_contact_name: 'Jane Doe',
  emergency_contact_phone: '+1 555 123 4567',
};

describe('AccessibilityTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the heading and Save Changes button', () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<AccessibilityTab />);
    expect(screen.getByText('Accessibility & Accommodations')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Save Changes/i })).toBeInTheDocument();
  });

  it('renders the info banner text', () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<AccessibilityTab />);
    expect(
      screen.getByText(/This information helps organizations provide appropriate support/),
    ).toBeInTheDocument();
  });

  it('shows empty state when no needs exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<AccessibilityTab />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
      expect(screen.getByText('No accessibility needs added')).toBeInTheDocument();
    });
  });

  it('shows loading skeleton while data is being fetched', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<AccessibilityTab />);
    const cards = screen.getAllByTestId('glass-card');
    const pulsingCards = cards.filter((c) => c.className?.includes('animate-pulse'));
    expect(pulsingCards.length).toBeGreaterThan(0);
  });

  it('displays accessibility needs when data is loaded', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockNeed] });
    render(<AccessibilityTab />);
    await waitFor(() => {
      expect(screen.getAllByText('mobility').length).toBeGreaterThan(0);
    });
  });

  it('shows Add Your First Need button in empty state', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<AccessibilityTab />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Add Your First Need/i })).toBeInTheDocument();
    });
  });

  it('shows error state and Try Again button when API fails', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });
    render(<AccessibilityTab />);
    await waitFor(() => {
      expect(screen.getByText('Unable to load accessibility needs.')).toBeInTheDocument();
    });
    const buttons = screen.getAllByRole('button');
    const tryAgainBtn = buttons.find((btn) => btn.textContent?.includes('Try Again'));
    expect(tryAgainBtn).toBeTruthy();
  });

  it('retries loading when Try Again is clicked', async () => {
    let callCount = 0;
    vi.mocked(api.get).mockImplementation(() => {
      callCount++;
      if (callCount === 1) return Promise.resolve({ success: false, data: null });
      return Promise.resolve({ success: true, data: [] });
    });
    render(<AccessibilityTab />);
    await waitFor(() => {
      expect(screen.getByText('Unable to load accessibility needs.')).toBeInTheDocument();
    });
    const buttons = screen.getAllByRole('button');
    const tryAgainBtn = buttons.find((btn) => btn.textContent?.includes('Try Again'));
    fireEvent.click(tryAgainBtn!);
    await waitFor(() => {
      expect(callCount).toBe(2);
    });
  });

  it('calls api.put when Save Changes is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockNeed] });
    vi.mocked(api.put).mockResolvedValue({ success: true });
    render(<AccessibilityTab />);
    await waitFor(() => {
      expect(screen.getAllByText('mobility').length).toBeGreaterThan(0);
    });
    fireEvent.click(screen.getByRole('button', { name: /Save Changes/i }));
    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith('/v2/volunteering/accessibility-needs', {
        needs: [mockNeed],
      });
    });
  });

  it('shows Add Another Need button when needs exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockNeed] });
    render(<AccessibilityTab />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Add Another Need/i })).toBeInTheDocument();
    });
  });
});
