// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { JobMetadataSidebar } from './JobMetadataSidebar';
import type { JobVacancy } from './JobDetailTypes';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  API_BASE: '/api',
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

const BASE_VACANCY: JobVacancy = {
  id: 1,
  title: 'Test Job',
  description: 'A test description',
  location: null,
  is_remote: false,
  type: 'paid',
  commitment: 'full_time',
  category: null,
  skills: [],
  skills_required: null,
  hours_per_week: null,
  time_credits: null,
  contact_email: null,
  contact_phone: null,
  deadline: null,
  status: 'open',
  views_count: 0,
  applications_count: 0,
  created_at: '2024-01-01T00:00:00Z',
  user_id: 1,
  creator: { id: 1, name: 'Owner', avatar_url: null },
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

describe('JobMetadataSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders category when provided', () => {
    render(
      <JobMetadataSidebar
        vacancy={{ ...BASE_VACANCY, category: 'Engineering' }}
        isOwner={false}
        benchmark={null}
        formatSalary={() => null}
      />
    );
    expect(screen.getByText('Engineering')).toBeInTheDocument();
  });

  it('does not render category section when category is null', () => {
    render(
      <JobMetadataSidebar
        vacancy={{ ...BASE_VACANCY, category: null }}
        isOwner={false}
        benchmark={null}
        formatSalary={() => null}
      />
    );
    // "Engineering" should not be present
    expect(screen.queryByText('Engineering')).not.toBeInTheDocument();
  });

  it('renders location when not remote', () => {
    render(
      <JobMetadataSidebar
        vacancy={{ ...BASE_VACANCY, location: 'Dublin, Ireland', is_remote: false }}
        isOwner={false}
        benchmark={null}
        formatSalary={() => null}
      />
    );
    expect(screen.getByText('Dublin, Ireland')).toBeInTheDocument();
  });

  it('hides location when is_remote is true', () => {
    render(
      <JobMetadataSidebar
        vacancy={{ ...BASE_VACANCY, location: 'Dublin, Ireland', is_remote: true }}
        isOwner={false}
        benchmark={null}
        formatSalary={() => null}
      />
    );
    expect(screen.queryByText('Dublin, Ireland')).not.toBeInTheDocument();
  });

  it('renders salary when formatSalary returns a value', () => {
    render(
      <JobMetadataSidebar
        vacancy={BASE_VACANCY}
        isOwner={false}
        benchmark={null}
        formatSalary={() => '€50,000 / year'}
      />
    );
    expect(screen.getByText('€50,000 / year')).toBeInTheDocument();
  });

  it('renders contact email as mailto link', () => {
    render(
      <JobMetadataSidebar
        vacancy={{ ...BASE_VACANCY, contact_email: 'hr@example.com' }}
        isOwner={false}
        benchmark={null}
        formatSalary={() => null}
      />
    );
    const link = screen.getByRole('link', { name: 'hr@example.com' });
    expect(link).toHaveAttribute('href', 'mailto:hr@example.com');
  });

  it('renders contact phone as tel link', () => {
    render(
      <JobMetadataSidebar
        vacancy={{ ...BASE_VACANCY, contact_phone: '+353 1 234 5678' }}
        isOwner={false}
        benchmark={null}
        formatSalary={() => null}
      />
    );
    const link = screen.getByRole('link', { name: '+353 1 234 5678' });
    expect(link).toHaveAttribute('href', 'tel:+353 1 234 5678');
  });

  it('shows benchmark section only for owners when benchmark provided', () => {
    render(
      <JobMetadataSidebar
        vacancy={BASE_VACANCY}
        isOwner={true}
        benchmark={{
          role_keyword: 'Engineer',
          salary_min: 40000,
          salary_max: 60000,
          salary_median: 50000,
          salary_type: 'annual',
          currency: 'EUR',
        }}
        formatSalary={() => null}
      />
    );
    // benchmark text contains the role keyword
    expect(screen.getByText(/Engineer/)).toBeInTheDocument();
  });

  it('hides benchmark section for non-owners', () => {
    const { container } = render(
      <JobMetadataSidebar
        vacancy={BASE_VACANCY}
        isOwner={false}
        benchmark={{
          role_keyword: 'Engineer',
          salary_min: 40000,
          salary_max: 60000,
          salary_median: 50000,
          salary_type: 'annual',
          currency: 'EUR',
        }}
        formatSalary={() => null}
      />
    );
    // The benchmark mentions "Engineer" — should not be visible for non-owners
    // (category is null here so it won't appear from another field)
    expect(container.querySelector('[class*="bg-accent"]')).toBeNull();
  });
});
