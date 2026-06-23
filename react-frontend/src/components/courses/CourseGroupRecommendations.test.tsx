// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// coursesApi calls api.get internally; mock at the module level so forGroup resolves
vi.mock('@/lib/api/courses', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/lib/api/courses')>();
  return {
    ...orig,
    coursesApi: {
      ...orig.coursesApi,
      forGroup: vi.fn(),
    },
  };
});

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockHasFeature = vi.fn(() => true);

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: mockHasFeature,
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub CourseCard ──────────────────────────────────────────────────────────
vi.mock('@/components/courses/CourseCard', () => ({
  CourseCard: ({ course }: { course: { id: number; title: string } }) => (
    <div data-testid={`course-card-${course.id}`}>{course.title}</div>
  ),
}));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeCourse = (id: number, title: string) => ({
  id,
  title,
  slug: `course-${id}`,
  author_user_id: 1,
  category_id: null,
  level: 'beginner' as const,
  visibility: 'public' as const,
  enrollment_type: 'self_paced' as const,
  status: 'published' as const,
  moderation_status: 'approved' as const,
  credit_cost: 0,
  learner_credit_reward: 0,
  instructor_credit_reward: 0,
  enrollment_count: 5,
  completion_count: 2,
  rating_avg: '4.5',
  rating_count: 3,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('CourseGroupRecommendations', () => {
  let coursesApiMock: { forGroup: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    vi.resetAllMocks();
    mockHasFeature.mockReturnValue(true);
    const mod = await import('@/lib/api/courses');
    coursesApiMock = mod.coursesApi as unknown as { forGroup: ReturnType<typeof vi.fn> };
    coursesApiMock.forGroup.mockResolvedValue({ success: true, data: [] });
  });

  it('renders nothing when courses feature is off', async () => {
    mockHasFeature.mockReturnValue(false);
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    const { container } = render(<CourseGroupRecommendations groupId={1} />);
    // Wait to ensure no async side effects resolve
    await new Promise((r) => setTimeout(r, 50));
    // section should not be in the DOM
    expect(container.querySelector('section')).toBeNull();
  });

  it('renders nothing when API returns empty courses list', async () => {
    coursesApiMock.forGroup.mockResolvedValue({ success: true, data: [] });
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    const { container } = render(<CourseGroupRecommendations groupId={1} />);
    await waitFor(() => {
      expect(coursesApiMock.forGroup).toHaveBeenCalledWith(1);
    });
    expect(container.querySelector('section')).toBeNull();
  });

  it('calls coursesApi.forGroup with the provided groupId', async () => {
    coursesApiMock.forGroup.mockResolvedValue({ success: true, data: [makeCourse(10, 'Test Course')] });
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    render(<CourseGroupRecommendations groupId={42} />);
    await waitFor(() => {
      expect(coursesApiMock.forGroup).toHaveBeenCalledWith(42);
    });
  });

  it('renders the recommended courses section heading when courses exist', async () => {
    coursesApiMock.forGroup.mockResolvedValue({
      success: true,
      data: [makeCourse(1, 'Intro to Timebanking')],
    });
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    render(<CourseGroupRecommendations groupId={1} />);
    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 2 })).toBeInTheDocument();
    });
  });

  it('renders a CourseCard for each returned course', async () => {
    coursesApiMock.forGroup.mockResolvedValue({
      success: true,
      data: [makeCourse(1, 'Course Alpha'), makeCourse(2, 'Course Beta'), makeCourse(3, 'Course Gamma')],
    });
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    render(<CourseGroupRecommendations groupId={5} />);
    await waitFor(() => {
      expect(screen.getByTestId('course-card-1')).toBeInTheDocument();
      expect(screen.getByTestId('course-card-2')).toBeInTheDocument();
      expect(screen.getByTestId('course-card-3')).toBeInTheDocument();
    });
  });

  it('displays the course titles in the rendered cards', async () => {
    coursesApiMock.forGroup.mockResolvedValue({
      success: true,
      data: [makeCourse(7, 'Volunteering 101'), makeCourse(8, 'Community Basics')],
    });
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    render(<CourseGroupRecommendations groupId={3} />);
    await waitFor(() => {
      expect(screen.getByText('Volunteering 101')).toBeInTheDocument();
      expect(screen.getByText('Community Basics')).toBeInTheDocument();
    });
  });

  it('renders nothing when API response has success: false', async () => {
    coursesApiMock.forGroup.mockResolvedValue({ success: false, data: null });
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    const { container } = render(<CourseGroupRecommendations groupId={1} />);
    await waitFor(() => {
      expect(coursesApiMock.forGroup).toHaveBeenCalled();
    });
    expect(container.querySelector('section')).toBeNull();
  });

  it('renders nothing when groupId is 0 (falsy)', async () => {
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    const { container } = render(<CourseGroupRecommendations groupId={0} />);
    await new Promise((r) => setTimeout(r, 50));
    expect(coursesApiMock.forGroup).not.toHaveBeenCalled();
    expect(container.querySelector('section')).toBeNull();
  });

  it('does not call API when courses feature is disabled', async () => {
    mockHasFeature.mockReturnValue(false);
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    render(<CourseGroupRecommendations groupId={5} />);
    await new Promise((r) => setTimeout(r, 50));
    expect(coursesApiMock.forGroup).not.toHaveBeenCalled();
  });

  it('wraps courses in a grid layout container', async () => {
    coursesApiMock.forGroup.mockResolvedValue({
      success: true,
      data: [makeCourse(1, 'Grid Test Course')],
    });
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    const { container } = render(<CourseGroupRecommendations groupId={1} />);
    await waitFor(() => {
      expect(screen.getByText('Grid Test Course')).toBeInTheDocument();
    });
    const grid = container.querySelector('.grid');
    expect(grid).toBeInTheDocument();
  });

  it('renders nothing when API returns data: null (missing data field)', async () => {
    coursesApiMock.forGroup.mockResolvedValue({ success: true, data: null });
    const { CourseGroupRecommendations } = await import('./CourseGroupRecommendations');
    const { container } = render(<CourseGroupRecommendations groupId={1} />);
    await waitFor(() => {
      expect(coursesApiMock.forGroup).toHaveBeenCalled();
    });
    // data is null → setCourses not called → remains [] → section hidden
    expect(container.querySelector('section')).toBeNull();
  });
});
