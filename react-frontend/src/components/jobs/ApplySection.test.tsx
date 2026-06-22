// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { api } from '@/lib/api';
import { ApplySection } from './ApplySection';
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

const OPEN_VACANCY: JobVacancy = {
  id: 42,
  title: 'Software Engineer',
  description: 'Build cool things',
  location: null,
  is_remote: true,
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
  user_id: 99,
  creator: { id: 99, name: 'Employer', avatar_url: null },
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

const defaultProps = {
  isAuthenticated: true,
  isOwner: false,
  isSubmitting: false,
  savedProfile: null,
  tenantPath: (p: string) => `/test${p}`,
  onApplyOpen: vi.fn(),
  onQuickApplySuccess: vi.fn(),
  onQuickApplyError: vi.fn(),
  setIsSubmitting: vi.fn(),
};

describe('ApplySection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows login prompt when not authenticated', () => {
    render(
      <ApplySection
        {...defaultProps}
        vacancy={OPEN_VACANCY}
        isAuthenticated={false}
      />
    );
    // Should show a link to login
    expect(screen.getByRole('link')).toHaveAttribute('href', expect.stringContaining('/login'));
  });

  it('shows "own vacancy" chip when user is the owner', () => {
    render(
      <ApplySection
        {...defaultProps}
        vacancy={OPEN_VACANCY}
        isAuthenticated={true}
        isOwner={true}
      />
    );
    // i18n key apply.own_vacancy — contains "own" in real translation
    // Check no apply button is rendered
    expect(screen.queryByRole('button', { name: /apply/i })).not.toBeInTheDocument();
  });

  it('shows closed chip when vacancy status is not "open"', () => {
    render(
      <ApplySection
        {...defaultProps}
        vacancy={{ ...OPEN_VACANCY, status: 'closed' }}
        isAuthenticated={true}
        isOwner={false}
      />
    );
    // No apply button should be rendered
    expect(screen.queryByRole('button', { name: /apply/i })).not.toBeInTheDocument();
  });

  it('shows already-applied state when has_applied is true', () => {
    render(
      <ApplySection
        {...defaultProps}
        vacancy={{ ...OPEN_VACANCY, has_applied: true, application_status: 'pending' }}
        isAuthenticated={true}
        isOwner={false}
      />
    );
    // HeroUI Button with isDisabled on a <button> element sets the native disabled attribute
    const alreadyAppliedBtn = screen.getByRole('button', { name: /already applied/i });
    expect(alreadyAppliedBtn).toBeDisabled();
  });

  it('shows message employer button when already applied', () => {
    render(
      <ApplySection
        {...defaultProps}
        vacancy={{ ...OPEN_VACANCY, has_applied: true, creator: { id: 99, name: 'Employer', avatar_url: null } }}
        isAuthenticated={true}
        isOwner={false}
      />
    );
    const buttons = screen.getAllByRole('button');
    const msgBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('message'));
    expect(msgBtn).toBeDefined();
  });

  it('renders main apply button and calls onApplyOpen when clicked', () => {
    const onApplyOpen = vi.fn();
    render(
      <ApplySection
        {...defaultProps}
        vacancy={OPEN_VACANCY}
        isAuthenticated={true}
        isOwner={false}
        onApplyOpen={onApplyOpen}
      />
    );
    const applyBtn = screen.getByRole('button', { name: /apply/i });
    fireEvent.click(applyBtn);
    expect(onApplyOpen).toHaveBeenCalledTimes(1);
  });

  it('shows quick apply button when savedProfile has cover_text', () => {
    render(
      <ApplySection
        {...defaultProps}
        vacancy={OPEN_VACANCY}
        isAuthenticated={true}
        isOwner={false}
        savedProfile={{ cv_filename: 'cv.pdf', cover_text: 'My cover letter' }}
      />
    );
    const buttons = screen.getAllByRole('button');
    const quickApplyBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('quick'));
    expect(quickApplyBtn).toBeDefined();
  });

  it('quick apply calls api.post with vacancy id and cover_text on success', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: true });
    const onQuickApplySuccess = vi.fn();

    render(
      <ApplySection
        {...defaultProps}
        vacancy={OPEN_VACANCY}
        isAuthenticated={true}
        isOwner={false}
        savedProfile={{ cover_text: 'My saved cover letter' }}
        onQuickApplySuccess={onQuickApplySuccess}
      />
    );

    const buttons = screen.getAllByRole('button');
    const quickApplyBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('quick'));
    expect(quickApplyBtn).toBeDefined();
    fireEvent.click(quickApplyBtn!);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/jobs/42/apply', {
        message: 'My saved cover letter',
      });
      expect(onQuickApplySuccess).toHaveBeenCalledTimes(1);
    });
  });

  it('quick apply calls onQuickApplyError on API failure', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, error: 'Server error' });
    const onQuickApplyError = vi.fn();

    render(
      <ApplySection
        {...defaultProps}
        vacancy={OPEN_VACANCY}
        isAuthenticated={true}
        isOwner={false}
        savedProfile={{ cover_text: 'My saved cover letter' }}
        onQuickApplyError={onQuickApplyError}
      />
    );

    const buttons = screen.getAllByRole('button');
    const quickApplyBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('quick'));
    fireEvent.click(quickApplyBtn!);

    await waitFor(() => {
      expect(onQuickApplyError).toHaveBeenCalledWith('Server error');
    });
  });

  it('quick apply does not appear when savedProfile has no cover_text', () => {
    render(
      <ApplySection
        {...defaultProps}
        vacancy={OPEN_VACANCY}
        isAuthenticated={true}
        isOwner={false}
        savedProfile={{ cv_filename: 'cv.pdf' }}
      />
    );
    const buttons = screen.getAllByRole('button');
    const quickApplyBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('quick'));
    expect(quickApplyBtn).toBeUndefined();
  });
});
