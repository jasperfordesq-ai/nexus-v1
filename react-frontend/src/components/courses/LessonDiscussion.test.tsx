// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LessonDiscussion — threaded per-lesson discussion panel.
 * Covers: loading, empty, list render, posting a comment, and deleting a comment.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import type { CourseDiscussion } from '@/lib/api/courses';

// ── context + utility mocks ──────────────────────────────────────────────────

const AUTHED_USER = { id: 7, name: 'Alice Test', avatar_url: null };

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: AUTHED_USER,
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
  }),
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── coursesApi mock ──────────────────────────────────────────────────────────
vi.mock('@/lib/api/courses', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/api/courses')>();
  return {
    ...actual,
    coursesApi: {
      ...actual.coursesApi,
      listDiscussions: vi.fn(),
      postDiscussion: vi.fn(),
      deleteDiscussion: vi.fn(),
    },
  };
});

import { coursesApi } from '@/lib/api/courses';
import { LessonDiscussion } from './LessonDiscussion';

// ── fixtures ─────────────────────────────────────────────────────────────────

const makeComment = (overrides: Partial<CourseDiscussion> = {}): CourseDiscussion => ({
  id: 1,
  course_id: 10,
  lesson_id: 5,
  user_id: 7,
  parent_id: null,
  body: 'Great lesson!',
  status: 'approved',
  replies: [],
  user: { id: 7, name: 'Alice Test', avatar_url: null },
  ...overrides,
});

const DEFAULT_PROPS = { courseId: 10, lessonId: 5 };

describe('LessonDiscussion', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── loading state ────────────────────────────────────────────────────────
  it('shows a loading spinner while discussions are fetching', () => {
    vi.mocked(coursesApi.listDiscussions).mockReturnValue(new Promise(() => {}));
    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    // The spinner wrapper div has aria-busy="true"; the toast region never sets it.
    expect(document.querySelector('[aria-busy="true"]')).toBeInTheDocument();
  });

  // ── empty state ──────────────────────────────────────────────────────────
  it('renders empty message when no comments returned', async () => {
    vi.mocked(coursesApi.listDiscussions).mockResolvedValueOnce({ success: true, data: [] });
    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    await waitFor(() => {
      // Spinner wrapper (aria-busy) is gone; toast region (no aria-busy) persists
      expect(document.querySelector('[aria-busy="true"]')).not.toBeInTheDocument();
    });
    // The "no comments" paragraph should now be visible
    expect(document.querySelector('p.text-sm.text-muted')).toBeInTheDocument();
  });

  // ── populated list ────────────────────────────────────────────────────────
  it('renders comment body text', async () => {
    vi.mocked(coursesApi.listDiscussions).mockResolvedValueOnce({
      success: true,
      data: [makeComment({ body: 'Excellent overview!' })],
    });
    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByText('Excellent overview!')).toBeInTheDocument();
    });
  });

  it('renders commenter display name', async () => {
    vi.mocked(coursesApi.listDiscussions).mockResolvedValueOnce({
      success: true,
      data: [makeComment({ user: { id: 7, name: 'Alice Test', avatar_url: null } })],
    });
    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByText('Alice Test')).toBeInTheDocument();
    });
  });

  it('renders nested replies', async () => {
    const reply: CourseDiscussion = makeComment({
      id: 2,
      user_id: 8,
      body: 'I agree!',
      parent_id: 1,
      replies: [],
      user: { id: 8, name: 'Bob Test', avatar_url: null },
    });
    const comment = makeComment({ replies: [reply] });
    vi.mocked(coursesApi.listDiscussions).mockResolvedValueOnce({ success: true, data: [comment] });
    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByText('I agree!')).toBeInTheDocument();
      expect(screen.getByText('Bob Test')).toBeInTheDocument();
    });
  });

  it('renders multiple comments', async () => {
    vi.mocked(coursesApi.listDiscussions).mockResolvedValueOnce({
      success: true,
      data: [
        makeComment({ id: 1, body: 'First comment' }),
        makeComment({ id: 2, body: 'Second comment', user_id: 9, user: { id: 9, name: 'Carol', avatar_url: null } }),
      ],
    });
    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByText('First comment')).toBeInTheDocument();
      expect(screen.getByText('Second comment')).toBeInTheDocument();
    });
  });

  // ── posting a comment ─────────────────────────────────────────────────────
  it('calls coursesApi.postDiscussion with the typed text', async () => {
    vi.mocked(coursesApi.listDiscussions).mockResolvedValue({ success: true, data: [] });
    vi.mocked(coursesApi.postDiscussion).mockResolvedValueOnce({ success: true, data: makeComment() });

    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(document.querySelector('[aria-busy="true"]')).not.toBeInTheDocument();
    });

    // Textarea is identified by its aria-label (placeholder text)
    const textarea = screen.getByRole('textbox');
    fireEvent.change(textarea, { target: { value: 'My new comment' } });

    // The Post button has primary color
    const postBtn = screen.getByRole('button', { name: /post|discussion\.post/i });
    fireEvent.click(postBtn);

    await waitFor(() => {
      expect(coursesApi.postDiscussion).toHaveBeenCalledWith(
        10,
        5,
        'My new comment',
        undefined,
      );
    });
  });

  it('does not post when textarea is empty or whitespace-only', async () => {
    vi.mocked(coursesApi.listDiscussions).mockResolvedValue({ success: true, data: [] });

    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(document.querySelector('[aria-busy="true"]')).not.toBeInTheDocument();
    });

    const postBtn = screen.getByRole('button', { name: /post|discussion\.post/i });
    fireEvent.click(postBtn);

    expect(coursesApi.postDiscussion).not.toHaveBeenCalled();
  });

  it('reloads discussions after a successful post', async () => {
    vi.mocked(coursesApi.listDiscussions).mockResolvedValue({ success: true, data: [] });
    vi.mocked(coursesApi.postDiscussion).mockResolvedValueOnce({ success: true, data: makeComment() });

    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(document.querySelector('[aria-busy="true"]')).not.toBeInTheDocument();
    });

    const textarea = screen.getByRole('textbox');
    fireEvent.change(textarea, { target: { value: 'Reloads after post' } });

    const postBtn = screen.getByRole('button', { name: /post|discussion\.post/i });
    fireEvent.click(postBtn);

    await waitFor(() => {
      // listDiscussions called twice: initial load + reload after post
      expect(coursesApi.listDiscussions).toHaveBeenCalledTimes(2);
    });
  });

  // ── delete a comment ──────────────────────────────────────────────────────
  it('shows delete button only for own comments (matching user.id)', async () => {
    // Comment with user_id matching authenticated user (id=7)
    const ownComment = makeComment({ user_id: 7, body: 'My own comment' });
    const otherComment = makeComment({ id: 2, user_id: 99, body: 'Someone elses comment', user: { id: 99, name: 'Other', avatar_url: null } });
    vi.mocked(coursesApi.listDiscussions).mockResolvedValueOnce({
      success: true,
      data: [ownComment, otherComment],
    });
    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByText('My own comment')).toBeInTheDocument();
    });
    // Only one delete icon button should appear (for own comment)
    const deleteBtns = screen.getAllByRole('button').filter(
      (b) => b.getAttribute('aria-label') && /delete|discussion\.delete/i.test(b.getAttribute('aria-label')!),
    );
    expect(deleteBtns).toHaveLength(1);
  });

  it('calls coursesApi.deleteDiscussion with the comment id', async () => {
    vi.mocked(coursesApi.listDiscussions).mockResolvedValue({
      success: true,
      data: [makeComment({ id: 123, user_id: 7 })],
    });
    vi.mocked(coursesApi.deleteDiscussion).mockResolvedValueOnce({ success: true, data: undefined });

    render(<LessonDiscussion {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByText('Great lesson!')).toBeInTheDocument();
    });

    const deleteBtn = screen.getByRole('button', {
      name: /delete|discussion\.delete/i,
    });
    fireEvent.click(deleteBtn);

    await waitFor(() => {
      expect(coursesApi.deleteDiscussion).toHaveBeenCalledWith(123);
    });
  });

  // ── listDiscussions called with correct ids ────────────────────────────────
  it('passes courseId and lessonId to listDiscussions', async () => {
    vi.mocked(coursesApi.listDiscussions).mockResolvedValueOnce({ success: true, data: [] });
    render(<LessonDiscussion courseId={20} lessonId={99} />);
    await waitFor(() => {
      expect(coursesApi.listDiscussions).toHaveBeenCalledWith(20, 99);
    });
  });
});
