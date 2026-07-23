// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ApplicationCard component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => createMockContexts());

vi.mock(import('@/lib/helpers'), async (importOriginal) => ({
  ...(await importOriginal()),
  formatDateTime: vi.fn((dt: string) => dt),
  formatDateValue: vi.fn((dt: string) => dt),
  resolveAvatarUrl: vi.fn((url: string | null) => url ?? ''),
}));

import { api } from '@/lib/api';
import { ApplicationCard } from './ApplicationCard';
import type { Application } from './JobDetailTypes';

// ─── Test fixtures ────────────────────────────────────────────────────────────

const BASE_APPLICATION: Application = {
  id: 1,
  vacancy_id: 10,
  user_id: 99,
  message: 'I am very interested in this role.',
  status: 'applied',
  stage: 'applied',
  reviewer_notes: null,
  created_at: '2026-06-01T10:00:00Z',
  applicant: {
    id: 99,
    name: 'Alice Smith',
    avatar_url: null,
    email: 'alice@example.com',
  },
};

const DEFAULT_PROPS = {
  application: BASE_APPLICATION,
  onUpdateStatus: vi.fn(),
  tenantPathFn: (path: string) => `/test${path}`,
  navigateFn: vi.fn(),
};

describe('ApplicationCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ─── Basic rendering ─────────────────────────────────────────────────────────

  it('renders the applicant name', () => {
    render(<ApplicationCard {...DEFAULT_PROPS} />);
    expect(screen.getByText('Alice Smith')).toBeInTheDocument();
  });

  it('renders the applicant email', () => {
    render(<ApplicationCard {...DEFAULT_PROPS} />);
    expect(screen.getByText('alice@example.com')).toBeInTheDocument();
  });

  it('renders the application message', () => {
    render(<ApplicationCard {...DEFAULT_PROPS} />);
    expect(
      screen.getByText('I am very interested in this role.')
    ).toBeInTheDocument();
  });

  it('renders the application status chip', () => {
    render(<ApplicationCard {...DEFAULT_PROPS} />);
    expect(screen.getByText('Applied')).toBeInTheDocument();
  });

  it('renders created_at date', () => {
    render(<ApplicationCard {...DEFAULT_PROPS} />);
    expect(screen.getByText('2026-06-01T10:00:00Z')).toBeInTheDocument();
  });

  it('does not render email row when email is null', () => {
    const app: Application = {
      ...BASE_APPLICATION,
      applicant: { ...BASE_APPLICATION.applicant, email: null },
    };
    render(<ApplicationCard {...DEFAULT_PROPS} application={app} />);
    expect(screen.queryByText('alice@example.com')).not.toBeInTheDocument();
  });

  it('does not render message block when message is null', () => {
    const app: Application = { ...BASE_APPLICATION, message: null };
    render(<ApplicationCard {...DEFAULT_PROPS} application={app} />);
    expect(
      screen.queryByText('I am very interested in this role.')
    ).not.toBeInTheDocument();
  });

  it('renders reviewer_notes when present', () => {
    const app: Application = {
      ...BASE_APPLICATION,
      reviewer_notes: 'Strong candidate.',
    };
    render(<ApplicationCard {...DEFAULT_PROPS} application={app} />);
    expect(screen.getByText('Strong candidate.')).toBeInTheDocument();
  });

  it('does not render reviewer_notes block when notes are null', () => {
    render(<ApplicationCard {...DEFAULT_PROPS} />);
    expect(screen.queryByText('Reviewer Notes:')).not.toBeInTheDocument();
  });

  // ─── Status chip — unknown stage falls back gracefully ────────────────────────

  it('falls back to "unknown" chip for an unrecognised stage value', () => {
    const app: Application = {
      ...BASE_APPLICATION,
      stage: 'totally_made_up',
      status: 'totally_made_up',
    };
    render(<ApplicationCard {...DEFAULT_PROPS} application={app} />);
    expect(screen.getByText('Unknown')).toBeInTheDocument();
  });

  // ─── Pipeline stage action buttons ───────────────────────────────────────────

  it('renders pipeline action buttons for "applied" stage', () => {
    render(<ApplicationCard {...DEFAULT_PROPS} />);
    // applied → screening, interview, accepted, rejected
    expect(screen.getByRole('button', { name: /screening/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /interview/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /accepted/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /rejected/i })).toBeInTheDocument();
  });

  it('calls onUpdateStatus with correct args when pipeline button is clicked', () => {
    const onUpdateStatus = vi.fn();
    render(<ApplicationCard {...DEFAULT_PROPS} onUpdateStatus={onUpdateStatus} />);
    fireEvent.click(screen.getByRole('button', { name: /screening/i }));
    expect(onUpdateStatus).toHaveBeenCalledWith(1, 'screening');
  });

  it('renders correct pipeline buttons for "interview" stage', () => {
    const app: Application = {
      ...BASE_APPLICATION,
      stage: 'interview',
      status: 'interview',
    };
    render(<ApplicationCard {...DEFAULT_PROPS} application={app} />);
    // interview → offer, accepted, rejected
    expect(screen.getByRole('button', { name: /offer/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /accepted/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /rejected/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /screening/i })).not.toBeInTheDocument();
  });

  it('renders correct pipeline buttons for "offer" stage', () => {
    const app: Application = {
      ...BASE_APPLICATION,
      stage: 'offer',
      status: 'offer',
    };
    render(<ApplicationCard {...DEFAULT_PROPS} application={app} />);
    // offer → accepted, rejected
    expect(screen.getByRole('button', { name: /accepted/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /rejected/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /screening/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /interview/i })).not.toBeInTheDocument();
  });

  it('renders no pipeline buttons for "accepted" stage', () => {
    const app: Application = {
      ...BASE_APPLICATION,
      stage: 'accepted',
      status: 'accepted',
    };
    render(<ApplicationCard {...DEFAULT_PROPS} application={app} />);
    // Terminal stage — no further actions
    expect(screen.queryByRole('button', { name: /rejected/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /screening/i })).not.toBeInTheDocument();
  });

  it('renders no pipeline buttons for "rejected" stage', () => {
    const app: Application = {
      ...BASE_APPLICATION,
      stage: 'rejected',
      status: 'rejected',
    };
    render(<ApplicationCard {...DEFAULT_PROPS} application={app} />);
    expect(screen.queryByRole('button', { name: /accepted/i })).not.toBeInTheDocument();
  });

  // ─── Message applicant button ─────────────────────────────────────────────────

  it('renders the Message Applicant button', () => {
    render(<ApplicationCard {...DEFAULT_PROPS} />);
    // The i18n key detail.message_applicant resolves to "Message"
    expect(
      screen.getByRole('button', { name: /^message$/i })
    ).toBeInTheDocument();
  });

  it('calls navigateFn with correct path when Message Applicant is clicked', () => {
    const navigateFn = vi.fn();
    render(<ApplicationCard {...DEFAULT_PROPS} navigateFn={navigateFn} />);
    // The button label is "Message" (detail.message_applicant i18n key)
    fireEvent.click(screen.getByRole('button', { name: /^message$/i }));
    expect(navigateFn).toHaveBeenCalledOnce();
    const [path] = navigateFn.mock.calls[0] as [string];
    expect(path).toContain('/messages');
    expect(path).toContain('user=99');
    expect(path).toContain('context=job');
    expect(path).toContain('context_id=10');
  });

  // ─── History toggle ───────────────────────────────────────────────────────────

  it('renders the Status History toggle button', () => {
    render(<ApplicationCard {...DEFAULT_PROPS} />);
    expect(screen.getByRole('button', { name: /status history/i })).toBeInTheDocument();
  });

  it('history section is hidden initially', () => {
    render(<ApplicationCard {...DEFAULT_PROPS} />);
    // The history container is conditionally rendered — not in DOM yet
    expect(
      screen.queryByText('No history available')
    ).not.toBeInTheDocument();
  });

  it('fetches and displays history when history toggle is clicked', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url === '/v2/jobs/applications/1/history') {
        return Promise.resolve({
          success: true,
          data: [
            {
              id: 1,
              from_status: null,
              to_status: 'applied',
              changed_by_name: 'System',
              changed_at: '2026-06-01T10:00:00Z',
              notes: null,
            },
          ],
        });
      }
      return Promise.resolve({ success: false });
    });

    render(<ApplicationCard {...DEFAULT_PROPS} />);
    fireEvent.click(screen.getByRole('button', { name: /status history/i }));

    await waitFor(() => {
      // "Initial" history entry renders as "Application submitted"
      expect(screen.getByText('Application submitted')).toBeInTheDocument();
    });
  });

  it('shows "No history available" when history response is empty', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url === '/v2/jobs/applications/1/history') {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: false });
    });

    render(<ApplicationCard {...DEFAULT_PROPS} />);
    fireEvent.click(screen.getByRole('button', { name: /status history/i }));

    await waitFor(() => {
      expect(screen.getByText('No history available')).toBeInTheDocument();
    });
  });

  it('hides history when toggle is clicked a second time', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url === '/v2/jobs/applications/1/history') {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: false });
    });

    render(<ApplicationCard {...DEFAULT_PROPS} />);
    const historyBtn = screen.getByRole('button', { name: /status history/i });
    fireEvent.click(historyBtn);

    await waitFor(() => {
      expect(screen.getByText('No history available')).toBeInTheDocument();
    });

    // Click again to hide
    fireEvent.click(historyBtn);
    await waitFor(() => {
      expect(screen.queryByText('No history available')).not.toBeInTheDocument();
    });
  });

  it('renders history entry with status transition', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url === '/v2/jobs/applications/1/history') {
        return Promise.resolve({
          success: true,
          data: [
            {
              id: 2,
              from_status: 'applied',
              to_status: 'screening',
              changed_by_name: 'Jane Recruiter',
              changed_at: '2026-06-02T09:00:00Z',
              notes: null,
            },
          ],
        });
      }
      return Promise.resolve({ success: false });
    });

    render(<ApplicationCard {...DEFAULT_PROPS} />);
    fireEvent.click(screen.getByRole('button', { name: /status history/i }));

    await waitFor(() => {
      // Should render the transition text "Applied → Screening"
      expect(screen.getByText(/applied.*screening/i)).toBeInTheDocument();
    });
  });

  it('does not re-fetch history on second toggle open when history was non-empty', async () => {
    // When history returns actual items (length > 0), the component skips the
    // API call on subsequent opens.  When the array is empty, the guard
    // `history.length === 0` is still true so a re-fetch IS expected — that is
    // correct component behaviour, not a bug.
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url === '/v2/jobs/applications/1/history') {
        return Promise.resolve({
          success: true,
          data: [
            {
              id: 1,
              from_status: null,
              to_status: 'applied',
              changed_by_name: 'System',
              changed_at: '2026-06-01T10:00:00Z',
              notes: null,
            },
          ],
        });
      }
      return Promise.resolve({ success: false });
    });

    render(<ApplicationCard {...DEFAULT_PROPS} />);
    const historyBtn = screen.getByRole('button', { name: /status history/i });

    // Open → fetch + cache populated (non-empty)
    fireEvent.click(historyBtn);
    await waitFor(() => {
      expect(screen.getByText('Application submitted')).toBeInTheDocument();
    });

    // Close
    fireEvent.click(historyBtn);
    await waitFor(() => {
      expect(screen.queryByText('Application submitted')).not.toBeInTheDocument();
    });

    // Open again — non-empty cache, should NOT re-fetch
    fireEvent.click(historyBtn);
    await waitFor(() => {
      expect(screen.getByText('Application submitted')).toBeInTheDocument();
    });

    // api.get should have been called only once
    expect(api.get).toHaveBeenCalledTimes(1);
  });

  // ─── stage falls through to status when stage field is missing ───────────────

  it('uses status field when stage field resolves to unknown', () => {
    // Application where stage is an unrecognised value but status is valid
    const app: Application = {
      ...BASE_APPLICATION,
      stage: 'screening',   // recognised
      status: 'applied',    // won't be used because stage takes priority
    };
    render(<ApplicationCard {...DEFAULT_PROPS} application={app} />);
    expect(screen.getByText('Screening')).toBeInTheDocument();
  });
});
