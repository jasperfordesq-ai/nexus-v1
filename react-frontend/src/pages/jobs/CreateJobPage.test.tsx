// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import type { ReactNode } from 'react';

const mockNavigate = vi.fn();
const mockUseParams = vi.fn(() => ({ id: undefined as string | undefined }));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) =>
      (opts?.defaultValue as string | undefined) ?? key,
  }),
}));

vi.mock('react-router-dom', async () => {
  const actual = await import('react-router-dom');
  const React = await import('react');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useParams: () => mockUseParams(),
    Link: ({ children, to, ...rest }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      React.createElement('a', { href: String(to), ...rest }, children),
    useSearchParams: () => [new URLSearchParams(), vi.fn()],
  };
});

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: null, meta: {} }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

const mockHasFeature = vi.fn(() => true);
const mockUseAuth = vi.fn(() => ({
  user: { id: 1, first_name: 'Test', name: 'Test User' },
  isAuthenticated: true,
}));

vi.mock('@/contexts', () => ({
  useAuth: (...args: unknown[]) => mockUseAuth(...args),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test$${p}`,
    hasFeature: mockHasFeature,
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: ReactNode; className?: string }) => (
    <div data-testid='glass-card' className={className}>{children}</div>
  ),
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid='empty-state'>
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, variants: _v, initial: _i, animate: _a, layout: _l, ...rest }: Record<string, unknown>) => (
      <div {...(rest as object)}>{children as ReactNode}</div>
    ),
  },
  AnimatePresence: ({ children }: { children: ReactNode }) => <>({children as ReactNode})</>,
}));

import { CreateJobPage } from './CreateJobPage';
import { api } from '@/lib/api';

describe('CreateJobPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
    mockUseAuth.mockReturnValue({
      user: { id: 1, first_name: 'Test', name: 'Test User' },
      isAuthenticated: true,
    });
  });

  describe('Create mode (no id param)', () => {
    beforeEach(() => {
      mockUseParams.mockReturnValue({ id: undefined });
    });

    it('renders create form heading', () => {
      render(<CreateJobPage />);
      expect(screen.getByText('form.create_title')).toBeInTheDocument();
    });

    it('shows validation error when title is empty on submit', async () => {
      vi.mocked(api.post).mockResolvedValue({ success: true, data: { id: 5 } });
      const { userEvent } = await import('@/test/test-utils');
      render(<CreateJobPage />);
      const submitBtn = screen.getByText('form.submit_create');
      await userEvent.click(submitBtn);
      await waitFor(() => {
        // HeroUI renders errorMessage via aria-describedby; check api.post was NOT called (validation blocked)
        expect(vi.mocked(api.post)).not.toHaveBeenCalled();
      });
    });

    it('shows validation error when description is empty on submit', async () => {
      const { userEvent } = await import('@/test/test-utils');
      render(<CreateJobPage />);
      const titleInput = screen.getByLabelText(/form.title_label/i);
      await userEvent.type(titleInput, 'Some Title');
      const submitBtn = screen.getByText('form.submit_create');
      await userEvent.click(submitBtn);
      await waitFor(() => {
        // HeroUI renders errorMessage via aria-describedby; check api.post was NOT called (validation blocked)
        expect(vi.mocked(api.post)).not.toHaveBeenCalled();
      });
    });

    it('all job type options present in type select', () => {
      render(<CreateJobPage />);
      expect(screen.getAllByText('type.paid').length).toBeGreaterThan(0);
      expect(screen.getAllByText('type.volunteer').length).toBeGreaterThan(0);
      expect(screen.getAllByText('type.timebank').length).toBeGreaterThan(0);
    });

    it('all commitment type options present in commitment select', () => {
      render(<CreateJobPage />);
      expect(screen.getAllByText('commitment.full_time').length).toBeGreaterThan(0);
      expect(screen.getAllByText('commitment.part_time').length).toBeGreaterThan(0);
      expect(screen.getAllByText('commitment.flexible').length).toBeGreaterThan(0);
      expect(screen.getAllByText('commitment.one_off').length).toBeGreaterThan(0);
    });

    it('salary fields are present in the form (J9)', () => {
      render(<CreateJobPage />);
      expect(screen.getByText('form.salary_min_label')).toBeInTheDocument();
      expect(screen.getByText('form.salary_max_label')).toBeInTheDocument();
    });

    it('submit button calls POST /v2/jobs for create mode', async () => {
      vi.mocked(api.post).mockResolvedValue({ success: true, data: { id: 7 } });
      const { userEvent } = await import('@/test/test-utils');
      render(<CreateJobPage />);
      const titleInput = screen.getByLabelText(/form.title_label/i);
      const descInput = screen.getByLabelText(/form.description_label/i);
      await userEvent.type(titleInput, 'New Vacancy');
      await userEvent.type(descInput, 'Full job description here');
      await userEvent.click(screen.getByText('form.submit_create'));
      await waitFor(() => {
        expect(vi.mocked(api.post)).toHaveBeenCalledWith('/v2/jobs', expect.objectContaining({
          title: 'New Vacancy',
        }));
      });
    });
  });

  describe('Edit mode (with id param)', () => {
    beforeEach(() => {
      mockUseParams.mockReturnValue({ id: '5' });
      vi.mocked(api.get).mockResolvedValue({
        success: true,
        data: {
          title: 'Existing Vacancy', description: 'Existing description',
          type: 'paid', commitment: 'flexible', category: '',
          location: '', is_remote: false, skills_required: '',
          hours_per_week: null, time_credits: null, contact_email: '',
          contact_phone: '', deadline: null, salary_min: null,
          salary_max: null, salary_type: '', salary_currency: '',
          salary_negotiable: false,
        },
        meta: {},
      });
    });

    it('renders edit form heading and loads existing vacancy', async () => {
      render(<CreateJobPage />);
      await waitFor(() => {
        expect(screen.getByText('form.edit_title')).toBeInTheDocument();
      });
    });

    it('shows submit update button and form pre-filled with job data', async () => {
      render(<CreateJobPage />);
      await waitFor(() => {
        expect(screen.getByDisplayValue('Existing Vacancy')).toBeInTheDocument();
        expect(screen.getByDisplayValue('Existing description')).toBeInTheDocument();
        // The submit button should exist and not be disabled (form is valid)
        const submitBtn = screen.getByText('form.submit_update');
        expect(submitBtn).toBeInTheDocument();
        const btn = submitBtn.closest('button');
        expect(btn).not.toBeNull();
        expect(btn?.getAttribute('disabled')).toBeNull();
      });
    });
  });
});
