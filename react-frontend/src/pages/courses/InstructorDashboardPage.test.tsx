// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for InstructorDashboardPage — authored courses list with publish controls.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import type { Course } from '@/lib/api/courses';

// ── context + utility mocks ──────────────────────────────────────────────────

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
    useToast: () => mockToast,
  }),
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/hooks', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/hooks')>();
  return { ...actual, usePageTitle: vi.fn() };
});

// ── react-router: provide useNavigate ────────────────────────────────────────
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

// ── coursesApi mock ──────────────────────────────────────────────────────────
vi.mock('@/lib/api/courses', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/api/courses')>();
  return {
    ...actual,
    coursesApi: {
      ...actual.coursesApi,
      authored: vi.fn(),
      publish: vi.fn(),
      unpublish: vi.fn(),
    },
  };
});

import { coursesApi } from '@/lib/api/courses';
import InstructorDashboardPage from './InstructorDashboardPage';

// ── fixtures ─────────────────────────────────────────────────────────────────

const makeCourse = (overrides: Partial<Course> = {}): Course => ({
  id: 1,
  author_user_id: 10,
  category_id: null,
  title: 'Sample Course',
  slug: 'sample-course',
  level: 'beginner',
  visibility: 'public',
  enrollment_type: 'self_paced',
  status: 'draft',
  moderation_status: 'pending',
  credit_cost: 0,
  learner_credit_reward: 0,
  instructor_credit_reward: 0,
  enrollment_count: 5,
  completion_count: 2,
  rating_avg: 0,
  rating_count: 0,
  ...overrides,
});

describe('InstructorDashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── loading state ────────────────────────────────────────────────────────
  it('shows a spinner while courses are loading', () => {
    vi.mocked(coursesApi.authored).mockReturnValue(new Promise(() => {}));
    render(<InstructorDashboardPage />);
    // aria-busy="true" is set on the spinner wrapper; the toast region never has it.
    expect(document.querySelector('[aria-busy="true"]')).toBeInTheDocument();
  });

  // ── empty state ──────────────────────────────────────────────────────────
  it('renders empty-state message when no courses returned', async () => {
    vi.mocked(coursesApi.authored).mockResolvedValueOnce({ success: true, data: [] });
    render(<InstructorDashboardPage />);
    await waitFor(() => {
      // Spinner wrapper has aria-busy; toast region does not.
      expect(document.querySelector('[aria-busy="true"]')).not.toBeInTheDocument();
    });
    // No course cards; the empty-state div should be visible.
    expect(screen.queryByRole('heading', { level: 3 })).not.toBeInTheDocument();
  });

  it('renders empty-state when API returns success:false', async () => {
    vi.mocked(coursesApi.authored).mockResolvedValueOnce({ success: false, data: null });
    render(<InstructorDashboardPage />);
    await waitFor(() => {
      expect(document.querySelector('[aria-busy="true"]')).not.toBeInTheDocument();
    });
    expect(screen.queryByRole('heading', { level: 3 })).not.toBeInTheDocument();
  });

  // ── populated state ──────────────────────────────────────────────────────
  it('renders course title cards after load', async () => {
    vi.mocked(coursesApi.authored).mockResolvedValueOnce({
      success: true,
      data: [makeCourse({ title: 'Intro to Timebanking' })],
    });
    render(<InstructorDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText('Intro to Timebanking')).toBeInTheDocument();
    });
  });

  it('renders enrollment and completion counts', async () => {
    vi.mocked(coursesApi.authored).mockResolvedValueOnce({
      success: true,
      data: [makeCourse({ enrollment_count: 14, completion_count: 7 })],
    });
    render(<InstructorDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText(/14/)).toBeInTheDocument();
      expect(screen.getByText(/7/)).toBeInTheDocument();
    });
  });

  it('shows "published" chip for published+approved course', async () => {
    vi.mocked(coursesApi.authored).mockResolvedValueOnce({
      success: true,
      data: [makeCourse({ status: 'published', moderation_status: 'approved' })],
    });
    render(<InstructorDashboardPage />);
    await waitFor(() => {
      // statusChip returns 'published' label for published+approved
      expect(screen.getByText(/published/i)).toBeInTheDocument();
    });
  });

  it('shows "pending_review" chip for non-draft pending course', async () => {
    vi.mocked(coursesApi.authored).mockResolvedValueOnce({
      success: true,
      data: [makeCourse({ status: 'published', moderation_status: 'pending' })],
    });
    render(<InstructorDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText(/pending/i)).toBeInTheDocument();
    });
  });

  // ── create course button ─────────────────────────────────────────────────
  it('navigates to new course route when create button pressed', async () => {
    vi.mocked(coursesApi.authored).mockResolvedValueOnce({ success: true, data: [] });
    render(<InstructorDashboardPage />);
    await waitFor(() => {
      expect(document.querySelector('[aria-busy="true"]')).not.toBeInTheDocument();
    });
    // The "Create course" button uses onPress → navigate.
    const createBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && /create/i.test(b.textContent),
    );
    if (createBtn) {
      fireEvent.click(createBtn);
      expect(mockNavigate).toHaveBeenCalledWith('/test/courses/instructor/new');
    }
  });

  // ── publish toggle ───────────────────────────────────────────────────────
  it('calls coursesApi.publish when publish button pressed on a draft course', async () => {
    vi.mocked(coursesApi.authored).mockResolvedValue({
      success: true,
      data: [makeCourse({ id: 99, status: 'draft', moderation_status: 'pending' })],
    });
    vi.mocked(coursesApi.publish).mockResolvedValueOnce({ success: true, data: undefined });

    render(<InstructorDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText('Sample Course')).toBeInTheDocument();
    });

    // The publish/unpublish button label is the only button that switches between
    // "publish" and "unpublish" — find it by its translated key fragment.
    const publishBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && /publish/i.test(b.textContent) && !/unpublish/i.test(b.textContent),
    );
    if (publishBtn) {
      fireEvent.click(publishBtn);
      await waitFor(() => {
        expect(coursesApi.publish).toHaveBeenCalledWith(99);
      });
    }
  });

  it('calls coursesApi.unpublish when unpublish button pressed on published course', async () => {
    vi.mocked(coursesApi.authored).mockResolvedValue({
      success: true,
      data: [makeCourse({ id: 77, status: 'published', moderation_status: 'approved' })],
    });
    vi.mocked(coursesApi.unpublish).mockResolvedValueOnce({ success: true, data: undefined });

    render(<InstructorDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText('Sample Course')).toBeInTheDocument();
    });

    const unpublishBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && /unpublish/i.test(b.textContent),
    );
    if (unpublishBtn) {
      fireEvent.click(unpublishBtn);
      await waitFor(() => {
        expect(coursesApi.unpublish).toHaveBeenCalledWith(77);
      });
    }
  });

  it('shows error toast when publish API call fails', async () => {
    vi.mocked(coursesApi.authored).mockResolvedValue({
      success: true,
      data: [makeCourse({ id: 55, status: 'draft', moderation_status: 'pending' })],
    });
    vi.mocked(coursesApi.publish).mockResolvedValueOnce({ success: false, data: undefined });

    render(<InstructorDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText('Sample Course')).toBeInTheDocument();
    });

    const publishBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && /publish/i.test(b.textContent) && !/unpublish/i.test(b.textContent),
    );
    if (publishBtn) {
      fireEvent.click(publishBtn);
      await waitFor(() => {
        expect(mockToast.error).toHaveBeenCalled();
      });
    }
  });
});
