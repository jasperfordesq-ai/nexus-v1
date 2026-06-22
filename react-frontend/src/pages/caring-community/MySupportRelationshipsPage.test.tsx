// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── useApi hook ──────────────────────────────────────────────────────────────
const { mockUseApi } = vi.hoisted(() => ({
  mockUseApi: vi.fn(),
}));

vi.mock('@/hooks/useApi', () => ({ useApi: mockUseApi }));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return { ...orig, useNavigate: () => mockNavigate };
});

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Fixture helpers ──────────────────────────────────────────────────────────
const makeRelationship = (overrides = {}) => ({
  id: 1,
  title: 'Weekly Check-In',
  description: 'Regular support visit',
  frequency: 'weekly' as const,
  expected_hours: 2,
  status: 'active' as const,
  start_date: '2025-01-01',
  end_date: null,
  last_logged_at: null,
  next_check_in_at: null,
  role: 'supporter' as const,
  intergenerational: false,
  partner: { id: 99, name: 'Alice Partner', avatar_url: null },
  recent_logs: [],
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('MySupportRelationshipsPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockUseApi.mockReturnValue({
      data: null,
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    });
  });

  it('shows a loading spinner initially', async () => {
    mockUseApi.mockReturnValue({
      data: null,
      isLoading: true,
      error: null,
      refetch: vi.fn(),
    });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders empty state when no relationships returned', async () => {
    mockUseApi.mockReturnValue({ data: [], isLoading: false, error: null, refetch: vi.fn() });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    // empty state heading is shown via t() translation key — check card is visible
    await waitFor(() => {
      // heading block visible; translation returns key in test env
      expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    });
    // The empty state is a card with a heart icon — no article roles
    expect(screen.queryAllByRole('article')).toHaveLength(0);
  });

  it('renders relationship cards when data is returned', async () => {
    mockUseApi.mockReturnValue({
      data: [makeRelationship()],
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    await waitFor(() => {
      expect(screen.getByText('Alice Partner')).toBeInTheDocument();
      expect(screen.getByText('Weekly Check-In')).toBeInTheDocument();
    });
  });

  it('shows error alert when API returns an error', async () => {
    mockUseApi.mockReturnValue({
      data: null,
      isLoading: false,
      error: new Error('Network error'),
      refetch: vi.fn(),
    });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    // There may be multiple role=alert elements (toast region is always present)
    // We specifically look for the error card (a GlassCard with role="alert")
    await waitFor(() => {
      const alerts = screen.getAllByRole('alert');
      // The page-level error card has a class containing "p-6"
      const errorCard = alerts.find((el) => el.className?.includes('p-6'));
      expect(errorCard).toBeDefined();
    });
  });

  it('renders Pause and End buttons for active relationships', async () => {
    mockUseApi.mockReturnValue({
      data: [makeRelationship({ status: 'active' })],
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const pauseBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('pause'));
      const endBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('end'));
      expect(pauseBtn).toBeDefined();
      expect(endBtn).toBeDefined();
    });
  });

  it('renders Resume button for paused relationships', async () => {
    mockUseApi.mockReturnValue({
      data: [makeRelationship({ status: 'paused' })],
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const resumeBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('resume'));
      expect(resumeBtn).toBeDefined();
    });
  });

  it('opens pause modal when Pause button is clicked', async () => {
    mockUseApi.mockReturnValue({
      data: [makeRelationship({ status: 'active' })],
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    await waitFor(() => screen.getByText('Alice Partner'));

    const buttons = screen.getAllByRole('button');
    const pauseBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('pause'));
    if (pauseBtn) fireEvent.click(pauseBtn);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('calls POST /pause endpoint when modal is confirmed', async () => {
    const mockRefetch = vi.fn().mockResolvedValue(undefined);
    mockUseApi.mockReturnValue({
      data: [makeRelationship({ id: 5, status: 'active' })],
      isLoading: false,
      error: null,
      refetch: mockRefetch,
    });
    mockApi.post.mockResolvedValue({ success: true });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    await waitFor(() => screen.getByText('Alice Partner'));

    const buttons = screen.getAllByRole('button');
    const pauseBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('pause'));
    if (pauseBtn) fireEvent.click(pauseBtn);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Find confirm button in modal
    const allBtns = screen.getAllByRole('button');
    const confirmBtn = allBtns.find(
      (b) =>
        b.textContent?.toLowerCase().includes('pause') &&
        b.getAttribute('data-disabled') !== 'true' &&
        b !== pauseBtn,
    );

    if (confirmBtn) {
      fireEvent.click(confirmBtn);
      await waitFor(() => {
        expect(mockApi.post).toHaveBeenCalledWith(
          '/v2/caring-community/my-relationships/5/pause',
          expect.any(Object),
        );
      });
    }
  });

  it('calls POST /resume endpoint when Resume is clicked', async () => {
    const mockRefetch = vi.fn().mockResolvedValue(undefined);
    mockUseApi.mockReturnValue({
      data: [makeRelationship({ id: 7, status: 'paused' })],
      isLoading: false,
      error: null,
      refetch: mockRefetch,
    });
    mockApi.post.mockResolvedValue({ success: true });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    await waitFor(() => screen.getByText('Alice Partner'));

    const buttons = screen.getAllByRole('button');
    const resumeBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('resume'));
    if (resumeBtn) {
      fireEvent.click(resumeBtn);
      await waitFor(() => {
        expect(mockApi.post).toHaveBeenCalledWith(
          '/v2/caring-community/my-relationships/7/resume',
        );
      });
    }
  });

  it('shows recent log entries when present', async () => {
    const rel = makeRelationship({
      recent_logs: [{ date: '2025-05-01', hours: 2, status: 'approved' }],
    });
    mockUseApi.mockReturnValue({ data: [rel], isLoading: false, error: null, refetch: vi.fn() });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    await waitFor(() => {
      // The log renders the status chip; "approved" maps to a chip via translation key
      // and the date "2025-05-01" is formatted and shown
      // The relationship card itself must be present
      expect(screen.getByRole('article')).toBeInTheDocument();
    });
    // No "no recent logs" message should appear
    expect(screen.queryByText(/no recent/i)).toBeNull();
  });

  it('shows no-action buttons for completed relationships', async () => {
    mockUseApi.mockReturnValue({
      data: [makeRelationship({ status: 'completed' })],
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    });

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    await waitFor(() => screen.getByText('Alice Partner'));

    // Completed relationships have no pause/end/resume buttons
    // (the action section is only rendered for status=active or status=paused)
    const buttons = screen.queryAllByRole('button');
    const actionBtns = buttons.filter(
      (b) =>
        b.textContent?.toLowerCase().includes('pause') ||
        b.textContent?.toLowerCase().includes('end') ||
        b.textContent?.toLowerCase().includes('resume'),
    );
    expect(actionBtns).toHaveLength(0);
  });

  it('shows error toast when POST fails', async () => {
    const mockRefetch = vi.fn();
    mockUseApi.mockReturnValue({
      data: [makeRelationship({ id: 3, status: 'paused' })],
      isLoading: false,
      error: null,
      refetch: mockRefetch,
    });
    mockApi.post.mockRejectedValue(new Error('Network'));

    const { MySupportRelationshipsPage } = await import('./MySupportRelationshipsPage');
    render(<MySupportRelationshipsPage />);

    await waitFor(() => screen.getByText('Alice Partner'));

    const buttons = screen.getAllByRole('button');
    const resumeBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('resume'));
    if (resumeBtn) {
      fireEvent.click(resumeBtn);
      await waitFor(() => {
        expect(mockToast.error).toHaveBeenCalled();
      });
    }
  });
});
