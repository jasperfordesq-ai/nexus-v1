// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// Mock @/lib/api/courses (the module CourseReviews actually calls)
vi.mock('@/lib/api/courses', () => ({
  coursesApi: {
    reviews: vi.fn(),
    review: vi.fn(),
  },
}));

vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
}));

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

// Import after mocks are set up
import { coursesApi } from '@/lib/api/courses';
import { CourseReviews } from './CourseReviews';

const REVIEW_1 = {
  id: 1,
  course_id: 42,
  user_id: 10,
  rating: 5,
  body: 'Excellent course!',
  status: 'approved',
  created_at: '2026-01-01T00:00:00Z',
  user: { id: 10, name: 'Alice', avatar_url: null },
};

const REVIEW_2 = {
  id: 2,
  course_id: 42,
  user_id: 11,
  rating: 3,
  body: null,
  status: 'approved',
  created_at: '2026-01-02T00:00:00Z',
  user: { id: 11, name: 'Bob', avatar_url: null },
};

describe('CourseReviews — list rendering', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner on initial render', () => {
    // Never resolve so we can inspect the loading state
    vi.mocked(coursesApi.reviews).mockReturnValue(new Promise(() => {}));
    render(<CourseReviews courseId={42} ratingAvg={4.5} ratingCount={2} canReview={false} />);
    // Multiple role="status" exist (Toast provider + spinner); find the aria-busy one
    const statuses = screen.getAllByRole('status', { hidden: true });
    const loadingSpinner = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(loadingSpinner).toBeInTheDocument();
  });

  it('renders list of reviews after load', async () => {
    vi.mocked(coursesApi.reviews).mockResolvedValue({ success: true, data: [REVIEW_1, REVIEW_2] });
    render(<CourseReviews courseId={42} ratingAvg={4} ratingCount={2} canReview={false} />);
    await waitFor(() => expect(screen.getByText('Alice')).toBeInTheDocument());
    expect(screen.getByText('Bob')).toBeInTheDocument();
    expect(screen.getByText('Excellent course!')).toBeInTheDocument();
  });

  it('shows empty state when no reviews', async () => {
    vi.mocked(coursesApi.reviews).mockResolvedValue({ success: true, data: [] });
    render(<CourseReviews courseId={42} ratingAvg={0} ratingCount={0} canReview={false} />);
    await waitFor(() => expect(screen.getByText('No reviews yet.')).toBeInTheDocument());
  });

  it('renders rating summary when ratingCount > 0', async () => {
    vi.mocked(coursesApi.reviews).mockResolvedValue({ success: true, data: [REVIEW_1] });
    render(<CourseReviews courseId={42} ratingAvg={5} ratingCount={1} canReview={false} />);
    await waitFor(() => expect(screen.getByText(/5\.0/)).toBeInTheDocument());
  });

  it('does not render rating summary when ratingCount is 0', async () => {
    vi.mocked(coursesApi.reviews).mockResolvedValue({ success: true, data: [] });
    render(<CourseReviews courseId={42} ratingAvg={0} ratingCount={0} canReview={false} />);
    await waitFor(() => expect(screen.getByText('No reviews yet.')).toBeInTheDocument());
    // "5.0" should not appear
    expect(screen.queryByText(/\d\.\d/)).not.toBeInTheDocument();
  });

  it('falls back to empty list when api returns success:false', async () => {
    vi.mocked(coursesApi.reviews).mockResolvedValue({ success: false });
    render(<CourseReviews courseId={42} ratingAvg={0} ratingCount={0} canReview={false} />);
    await waitFor(() => expect(screen.getByText('No reviews yet.')).toBeInTheDocument());
  });
});

describe('CourseReviews — review form (canReview=true)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(coursesApi.reviews).mockResolvedValue({ success: true, data: [] });
  });

  it('renders the write-a-review card when canReview=true', async () => {
    render(<CourseReviews courseId={42} ratingAvg={0} ratingCount={0} canReview={true} />);
    await waitFor(() => expect(screen.getByText('Your rating')).toBeInTheDocument());
  });

  it('does NOT render the review form when canReview=false', async () => {
    render(<CourseReviews courseId={42} ratingAvg={0} ratingCount={0} canReview={false} />);
    await waitFor(() => expect(screen.getByText('No reviews yet.')).toBeInTheDocument());
    expect(screen.queryByText('Your rating')).not.toBeInTheDocument();
  });

  it('shows error toast when submitting without a star rating', async () => {
    render(<CourseReviews courseId={42} ratingAvg={0} ratingCount={0} canReview={true} />);
    await waitFor(() => expect(screen.getByText('Submit review')).toBeInTheDocument());

    // Click Submit without picking a star (rating stays 0)
    fireEvent.click(screen.getByText('Submit review'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Please choose a star rating.');
    });
    expect(coursesApi.review).not.toHaveBeenCalled();
  });

  it('calls coursesApi.review and shows success toast on submit', async () => {
    vi.mocked(coursesApi.review).mockResolvedValue({ success: true });
    // Second reviews() call after reload
    vi.mocked(coursesApi.reviews).mockResolvedValue({ success: true, data: [] });

    render(<CourseReviews courseId={42} ratingAvg={0} ratingCount={0} canReview={true} />);
    await waitFor(() => expect(screen.getByText('Your rating')).toBeInTheDocument());

    // Pick 4 stars — each star is a button with aria-label "Rate N out of 5 stars"
    const starBtn = screen.getByRole('button', { name: /rate 4 out of 5/i });
    fireEvent.click(starBtn);

    fireEvent.click(screen.getByText('Submit review'));

    await waitFor(() => {
      expect(coursesApi.review).toHaveBeenCalledWith(42, 4, '');
      expect(mockToast.success).toHaveBeenCalledWith('Thanks for your review!');
    });
  });

  it('shows error toast when submit fails', async () => {
    vi.mocked(coursesApi.review).mockResolvedValue({ success: false });

    render(<CourseReviews courseId={42} ratingAvg={0} ratingCount={0} canReview={true} />);
    await waitFor(() => expect(screen.getByText('Your rating')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: /rate 3 out of 5/i }));
    fireEvent.click(screen.getByText('Submit review'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith("Couldn't submit your review. Please try again.");
    });
  });
});
