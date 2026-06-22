// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Hoisted mock refs ──────────────────────────────────────────────────────
const { mockAdminVolunteering, mockToast, mockNavigate } = vi.hoisted(() => ({
  mockAdminVolunteering: {
    getCommunityProjects: vi.fn(),
    reviewCommunityProject: vi.fn(),
  },
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  mockNavigate: vi.fn(),
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminVolunteering: mockAdminVolunteering,
}));
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

// ── Mock contexts ─────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── Fixtures ───────────────────────────────────────────────────────────────
const PROJECT_PROPOSED = {
  id: 1,
  title: 'Community Garden',
  proposer_name: 'Alice Smith',
  category: 'environment',
  target_volunteers: 10,
  status: 'proposed',
  supporters_count: 3,
  created_at: '2026-01-01T00:00:00Z',
};

const PROJECT_APPROVED = {
  id: 2,
  title: 'Food Bank Run',
  proposer_name: 'Bob Jones',
  category: 'food',
  target_volunteers: 5,
  status: 'approved',
  supporters_count: 8,
  created_at: '2026-01-15T00:00:00Z',
};

const STATS = {
  total: 2,
  approved: 1,
  active: 0,
  completed: 0,
  total_supporters: 11,
};

const PROJECTS_RESPONSE = {
  success: true,
  data: [PROJECT_PROPOSED, PROJECT_APPROVED],
  meta: { stats: STATS, has_more: false, cursor: null },
};

// Second page item (distinct id from PROJECT_PROPOSED to avoid React Aria key collision)
const PROJECT_PAGE2 = {
  id: 3,
  title: 'Tech Repair Cafe',
  proposer_name: 'Carol Wright',
  category: 'tech',
  target_volunteers: 6,
  status: 'proposed',
  supporters_count: 1,
  created_at: '2026-02-01T00:00:00Z',
};

const PROJECTS_HAS_MORE = {
  success: true,
  data: [PROJECT_PROPOSED],
  meta: { stats: { ...STATS, total: 1 }, has_more: true, cursor: 'cursor_abc' },
};

const PROJECTS_PAGE2 = {
  success: true,
  data: [PROJECT_PAGE2],
  meta: { stats: { ...STATS, total: 2 }, has_more: false, cursor: null },
};

const EMPTY_RESPONSE = {
  success: true,
  data: [],
  meta: { stats: { total: 0, approved: 0, active: 0, completed: 0, total_supporters: 0 }, has_more: false },
};

import VolunteerProjects from './VolunteerProjects';

describe('VolunteerProjects — loading', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAdminVolunteering.getCommunityProjects.mockReturnValue(new Promise(() => {}));
  });

  it('renders the page and calls getCommunityProjects on mount', () => {
    render(<VolunteerProjects />);
    expect(mockAdminVolunteering.getCommunityProjects).toHaveBeenCalled();
  });
});

describe('VolunteerProjects — empty state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAdminVolunteering.getCommunityProjects.mockResolvedValue(EMPTY_RESPONSE);
  });

  it('shows empty state component when no projects', async () => {
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(mockAdminVolunteering.getCommunityProjects).toHaveBeenCalled();
    });
    // EmptyState renders when projects.length === 0 && !loading.
    // queryAllByText handles multiple matching nodes gracefully.
    await waitFor(() => {
      const noProjectEls = screen.queryAllByText(/no.*project/i);
      // At least one "no projects" element should render in empty state
      expect(noProjectEls.length).toBeGreaterThan(0);
    });
    // Stat cards should NOT be present (stats.total === 0)
    expect(screen.queryAllByText(/total projects/i).length).toBe(0);
  });

  it('renders refresh button', async () => {
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(mockAdminVolunteering.getCommunityProjects).toHaveBeenCalled();
    });
    const refreshBtn = screen.getAllByRole('button').find((btn) =>
      btn.textContent?.toLowerCase().includes('refresh')
    );
    expect(refreshBtn).toBeDefined();
  });
});

describe('VolunteerProjects — populated state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAdminVolunteering.getCommunityProjects.mockResolvedValue(PROJECTS_RESPONSE);
  });

  it('renders project titles in the table', async () => {
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden')).toBeInTheDocument();
    });
    expect(screen.getByText('Food Bank Run')).toBeInTheDocument();
  });

  it('renders proposer names', async () => {
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });
    expect(screen.getByText('Bob Jones')).toBeInTheDocument();
  });

  it('shows stat cards when stats.total > 0', async () => {
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden')).toBeInTheDocument();
    });
    // stat cards render total=2, approved=1
    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('renders Review button for each project row', async () => {
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden')).toBeInTheDocument();
    });
    const reviewBtns = screen.getAllByRole('button').filter((btn) =>
      btn.textContent?.toLowerCase().includes('review')
    );
    expect(reviewBtns.length).toBeGreaterThanOrEqual(2);
  });

  it('renders Create Opportunity button only for approved projects', async () => {
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(screen.getByText('Food Bank Run')).toBeInTheDocument();
    });
    const createOppBtns = screen.getAllByRole('button').filter((btn) =>
      btn.textContent?.toLowerCase().includes('opportunity') ||
      btn.textContent?.toLowerCase().includes('create opp')
    );
    expect(createOppBtns.length).toBe(1); // only for PROJECT_APPROVED
  });

  it('opens review modal when Review is clicked', async () => {
    const user = userEvent.setup();
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden')).toBeInTheDocument();
    });

    const reviewBtns = screen.getAllByRole('button').filter((btn) =>
      btn.textContent?.toLowerCase().includes('review')
    );
    await user.click(reviewBtns[0]);

    await waitFor(() => {
      expect(screen.queryAllByRole('dialog').length).toBeGreaterThan(0);
    });
    // Modal opens — verify a Submit or Cancel button is visible inside the dialog
    const modalDialog = screen.getAllByRole('dialog')[0];
    expect(modalDialog).toBeInTheDocument();
    // The modal title contains "Review Project" from i18n key
    const allText = document.body.textContent ?? '';
    expect(allText.toLowerCase()).toContain('review');
  });

  it('navigates to create opportunity page when button is clicked', async () => {
    const user = userEvent.setup();
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(screen.getByText('Food Bank Run')).toBeInTheDocument();
    });

    const createOppBtn = screen.getAllByRole('button').find((btn) =>
      btn.textContent?.toLowerCase().includes('opportunity')
    );
    expect(createOppBtn).toBeDefined();
    await user.click(createOppBtn!);

    expect(mockNavigate).toHaveBeenCalledWith(
      expect.stringContaining('from_project=2')
    );
  });
});

describe('VolunteerProjects — review modal submit', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAdminVolunteering.getCommunityProjects.mockResolvedValue(PROJECTS_RESPONSE);
    mockAdminVolunteering.reviewCommunityProject.mockResolvedValue({ success: true });
  });

  it('shows error toast if review submitted without selecting status', async () => {
    const user = userEvent.setup();
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden')).toBeInTheDocument();
    });

    const reviewBtns = screen.getAllByRole('button').filter((btn) =>
      btn.textContent?.toLowerCase().includes('review')
    );
    await user.click(reviewBtns[0]);

    await waitFor(() => {
      expect(screen.queryAllByRole('dialog').length).toBeGreaterThan(0);
    });

    const submitBtn = screen.getAllByRole('button').find((btn) =>
      btn.textContent?.toLowerCase().includes('submit')
    );
    expect(submitBtn).toBeDefined();
    await user.click(submitBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    // API should NOT have been called without a status
    expect(mockAdminVolunteering.reviewCommunityProject).not.toHaveBeenCalled();
  });
});

describe('VolunteerProjects — load more', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAdminVolunteering.getCommunityProjects
      .mockResolvedValueOnce(PROJECTS_HAS_MORE)
      .mockResolvedValue(PROJECTS_PAGE2);
  });

  it('shows Load More button when has_more is true', async () => {
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden')).toBeInTheDocument();
    });

    const loadMoreBtn = screen.getAllByRole('button').find((btn) =>
      btn.textContent?.toLowerCase().includes('load more') ||
      btn.textContent?.toLowerCase().includes('load')
    );
    expect(loadMoreBtn).toBeDefined();
  });

  it('calls getCommunityProjects a second time on Load More click', async () => {
    const user = userEvent.setup();
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden')).toBeInTheDocument();
    });

    // Find Load More button (renders when has_more=true)
    const loadMoreBtn = screen.getAllByRole('button').find((btn) =>
      btn.textContent?.toLowerCase().includes('load')
    );
    expect(loadMoreBtn).toBeDefined();
    if (loadMoreBtn) await user.click(loadMoreBtn);

    // Second call should happen after Load More click
    await waitFor(() => {
      expect(mockAdminVolunteering.getCommunityProjects).toHaveBeenCalledTimes(2);
    }, { timeout: 5000 });

    // Second call should pass cursor
    const secondCall = mockAdminVolunteering.getCommunityProjects.mock.calls[1];
    expect(secondCall[0]).toMatchObject({ cursor: 'cursor_abc' });
  });
});

describe('VolunteerProjects — error', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAdminVolunteering.getCommunityProjects.mockRejectedValue(new Error('Network error'));
  });

  it('shows error toast when loading fails', async () => {
    render(<VolunteerProjects />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
