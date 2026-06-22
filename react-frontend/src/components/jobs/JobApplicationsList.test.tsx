// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
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

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { JobApplicationsList } from './JobApplicationsList';
import type { JobVacancy, Application } from './JobDetailTypes';

const MOCK_VACANCY: JobVacancy = {
  id: 1,
  title: 'Software Engineer',
  description: 'A great role',
  location: 'Dublin',
  is_remote: false,
  type: 'paid',
  commitment: 'full_time',
  category: 'Technology',
  skills: ['TypeScript', 'React'],
  skills_required: null,
  hours_per_week: 40,
  time_credits: null,
  contact_email: 'hr@example.com',
  contact_phone: null,
  deadline: null,
  status: 'active',
  views_count: 50,
  applications_count: 3,
  created_at: '2026-01-01T00:00:00Z',
  user_id: 10,
  creator: { id: 10, name: 'Alice', avatar_url: null },
  organization: null,
  has_applied: false,
  application_id: null,
  application_status: null,
  application_stage: null,
  is_saved: false,
  is_featured: false,
  featured_until: null,
  tagline: null,
  video_url: null,
  benefits: null,
  company_size: null,
  salary_min: null,
  salary_max: null,
  salary_type: null,
  salary_currency: null,
  salary_negotiable: false,
  expired_at: null,
  renewed_at: null,
  renewal_count: 0,
  blind_hiring: false,
};

const MOCK_APPLICATION: Application = {
  id: 101,
  vacancy_id: 1,
  user_id: 20,
  message: 'I am interested in this role.',
  status: 'applied',
  stage: 'applied',
  reviewer_notes: null,
  created_at: '2026-01-15T10:00:00Z',
  applicant: {
    id: 20,
    name: 'Bob Smith',
    avatar_url: null,
    email: 'bob@example.com',
  },
};

const DEFAULT_PROPS = {
  vacancy: MOCK_VACANCY,
  applications: [],
  isLoadingApps: false,
  showApplications: false,
  onToggleShow: vi.fn(),
  onUpdateStatus: vi.fn(),
  onRefresh: vi.fn(),
  tenantPath: (p: string) => `/test${p}`,
  navigateFn: vi.fn(),
};

describe('JobApplicationsList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the toggle button with applications count', () => {
    render(<JobApplicationsList {...DEFAULT_PROPS} />);
    // The heading shows the count from vacancy.applications_count (3)
    expect(screen.getByText(/3/)).toBeInTheDocument();
  });

  it('applications panel is hidden when showApplications is false', () => {
    render(<JobApplicationsList {...DEFAULT_PROPS} showApplications={false} />);
    // The panel div itself should not exist (it's conditionally rendered)
    expect(document.getElementById('job-applications-panel')).toBeNull();
    expect(screen.queryByText('Bob Smith')).not.toBeInTheDocument();
  });

  it('calls onToggleShow when the toggle button is pressed', () => {
    const onToggleShow = vi.fn();
    render(<JobApplicationsList {...DEFAULT_PROPS} onToggleShow={onToggleShow} />);
    fireEvent.click(screen.getByRole('button', { name: /applications/i }));
    expect(onToggleShow).toHaveBeenCalledTimes(1);
  });

  it('renders loading skeleton when showApplications=true and isLoadingApps=true', () => {
    render(
      <JobApplicationsList
        {...DEFAULT_PROPS}
        showApplications={true}
        isLoadingApps={true}
        applications={[]}
      />
    );
    // The loading region in the applications panel has aria-busy="true"
    // Use getAllByRole since the Toast provider also emits a role="status" region
    const statusEls = screen.getAllByRole('status');
    const loadingEl = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(loadingEl).toBeDefined();
    expect(loadingEl).toHaveAttribute('aria-busy', 'true');
  });

  it('renders empty state when showApplications=true, loaded, and no applications', () => {
    render(
      <JobApplicationsList
        {...DEFAULT_PROPS}
        showApplications={true}
        isLoadingApps={false}
        applications={[]}
      />
    );
    // Empty state shows refresh button
    expect(screen.getByRole('button', { name: /refresh/i })).toBeInTheDocument();
  });

  it('calls onRefresh when the refresh button in empty state is pressed', () => {
    const onRefresh = vi.fn();
    render(
      <JobApplicationsList
        {...DEFAULT_PROPS}
        showApplications={true}
        isLoadingApps={false}
        applications={[]}
        onRefresh={onRefresh}
      />
    );
    fireEvent.click(screen.getByRole('button', { name: /refresh/i }));
    expect(onRefresh).toHaveBeenCalledTimes(1);
  });

  it('renders application cards when showApplications=true and applications are provided', () => {
    render(
      <JobApplicationsList
        {...DEFAULT_PROPS}
        showApplications={true}
        isLoadingApps={false}
        applications={[MOCK_APPLICATION]}
      />
    );
    expect(screen.getByText('Bob Smith')).toBeInTheDocument();
  });

  it('renders application message when present', () => {
    render(
      <JobApplicationsList
        {...DEFAULT_PROPS}
        showApplications={true}
        isLoadingApps={false}
        applications={[MOCK_APPLICATION]}
      />
    );
    expect(screen.getByText('I am interested in this role.')).toBeInTheDocument();
  });

  it('renders applicant email when present', () => {
    render(
      <JobApplicationsList
        {...DEFAULT_PROPS}
        showApplications={true}
        isLoadingApps={false}
        applications={[MOCK_APPLICATION]}
      />
    );
    expect(screen.getByText('bob@example.com')).toBeInTheDocument();
  });

  it('toggle button has correct aria-expanded attribute', () => {
    const { rerender } = render(
      <JobApplicationsList {...DEFAULT_PROPS} showApplications={false} />
    );
    const btn = screen.getByRole('button', { name: /applications/i });
    expect(btn).toHaveAttribute('aria-expanded', 'false');

    rerender(
      <JobApplicationsList
        {...DEFAULT_PROPS}
        showApplications={true}
        applications={[]}
        isLoadingApps={false}
      />
    );
    expect(btn).toHaveAttribute('aria-expanded', 'true');
  });

  it('renders multiple application cards when multiple applications are present', () => {
    const secondApp: Application = {
      ...MOCK_APPLICATION,
      id: 102,
      applicant: { id: 21, name: 'Carol Jones', avatar_url: null, email: null },
    };

    render(
      <JobApplicationsList
        {...DEFAULT_PROPS}
        showApplications={true}
        isLoadingApps={false}
        applications={[MOCK_APPLICATION, secondApp]}
      />
    );
    expect(screen.getByText('Bob Smith')).toBeInTheDocument();
    expect(screen.getByText('Carol Jones')).toBeInTheDocument();
  });
});
