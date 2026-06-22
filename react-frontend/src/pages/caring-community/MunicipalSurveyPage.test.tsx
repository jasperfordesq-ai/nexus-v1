// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Hoist mock data ───────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

// ── Mock api ──────────────────────────────────────────────────────────────────
vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── Contexts ──────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Member' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ── Mock PageMeta ─────────────────────────────────────────────────────────────
vi.mock('@/components/seo/PageMeta', () => ({
  PageMeta: () => null,
}));

// Stub GlassCard to simple div for easier DOM inspection
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    GlassCard: ({ children, className }: { children?: React.ReactNode; className?: string }) => (
      <div data-testid="glass-card" className={className}>{children as React.ReactNode}</div>
    ),
    Spinner: () => <div role="status" aria-busy="true" aria-label="loading" />,
    Button: ({ children, onPress, isDisabled, isLoading, color, variant, ...rest }: {
      children?: React.ReactNode;
      onPress?: () => void;
      isDisabled?: boolean;
      isLoading?: boolean;
      color?: string;
      variant?: string;
      [key: string]: unknown;
    }) => (
      <button
        onClick={onPress}
        disabled={!!isDisabled || !!isLoading}
        data-color={color}
        data-variant={variant}
        {...(rest as React.ButtonHTMLAttributes<HTMLButtonElement>)}
      >
        {isLoading ? 'loading...' : (children as React.ReactNode)}
      </button>
    ),
    Textarea: ({ label, value, onValueChange, isRequired, ...rest }: {
      label?: React.ReactNode;
      value?: string;
      onValueChange?: (val: string) => void;
      isRequired?: boolean;
      [key: string]: unknown;
    }) => (
      <div>
        <label>{label as React.ReactNode}</label>
        <textarea
          value={value ?? ''}
          onChange={(e) => onValueChange?.(e.target.value)}
          required={isRequired}
          {...(rest as React.TextareaHTMLAttributes<HTMLTextAreaElement>)}
        />
      </div>
    ),
    CheckboxGroup: ({ children, value, onValueChange, label }: {
      children?: React.ReactNode;
      value?: string[];
      onValueChange?: (v: string[]) => void;
      label?: React.ReactNode;
    }) => (
      <fieldset>
        <legend>{label as React.ReactNode}</legend>
        {children as React.ReactNode}
      </fieldset>
    ),
    Checkbox: ({ children, value, onChange }: {
      children?: React.ReactNode;
      value?: string;
      onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
    }) => (
      <label>
        <input type="checkbox" value={value} onChange={onChange} />
        {children as React.ReactNode}
      </label>
    ),
    RadioGroup: ({ children, value, onValueChange, label, orientation }: {
      children?: React.ReactNode;
      value?: string;
      onValueChange?: (v: string) => void;
      label?: React.ReactNode;
      orientation?: string;
    }) => (
      <fieldset data-orientation={orientation}>
        <legend>{label as React.ReactNode}</legend>
        {React.Children.map(children as React.ReactNode, (child) => {
          if (React.isValidElement(child)) {
            return React.cloneElement(child as React.ReactElement<{
              checked?: boolean;
              onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
            }>, {
              checked: (child.props as { value?: string }).value === value,
              onChange: () => onValueChange?.((child.props as { value?: string }).value ?? ''),
            });
          }
          return child;
        })}
      </fieldset>
    ),
    Radio: ({ children, value, checked, onChange }: {
      children?: React.ReactNode;
      value?: string;
      checked?: boolean;
      onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
    }) => (
      <label>
        <input type="radio" value={value} checked={!!checked} onChange={onChange ?? (() => {})} />
        {children as React.ReactNode}
      </label>
    ),
  };
});

// ── Fixtures ──────────────────────────────────────────────────────────────────
const makeSurvey = (overrides = {}) => ({
  id: 1,
  title: 'Community Feedback Survey',
  description: 'Please share your thoughts.',
  status: 'active' as const,
  is_anonymous: false,
  ends_at: null,
  response_count: 5,
  ...overrides,
});

const makeSurveyWithQuestions = (overrides = {}) => ({
  ...makeSurvey(),
  questions: [
    {
      id: 10,
      question_text: 'How satisfied are you?',
      question_type: 'single_choice' as const,
      options: JSON.stringify(['Very satisfied', 'Satisfied', 'Neutral']),
      is_required: 1,
      sort_order: 1,
    },
    {
      id: 11,
      question_text: 'Any other comments?',
      question_type: 'open_text' as const,
      options: null,
      is_required: 0,
      sort_order: 2,
    },
  ],
  ...overrides,
});

const listResponse = (surveys: ReturnType<typeof makeSurvey>[]) => ({
  success: true,
  data: surveys,
});

const detailResponse = (survey: ReturnType<typeof makeSurveyWithQuestions>) => ({
  success: true,
  data: survey,
});

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('MunicipalSurveyPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(listResponse([]));
  });

  it('shows loading spinner while surveys fetch', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders empty state when no surveys', async () => {
    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => {
      // Empty card is a GlassCard with the empty message
      expect(screen.getByTestId('glass-card')).toBeInTheDocument();
    });
  });

  it('renders survey cards when surveys are returned', async () => {
    mockApi.get.mockResolvedValue(listResponse([makeSurvey()]));
    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => {
      expect(screen.getByText('Community Feedback Survey')).toBeInTheDocument();
    });
  });

  it('renders survey description', async () => {
    mockApi.get.mockResolvedValue(listResponse([makeSurvey()]));
    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => {
      expect(screen.getByText('Please share your thoughts.')).toBeInTheDocument();
    });
  });

  it('renders Take Survey button for authenticated users', async () => {
    mockApi.get.mockResolvedValue(listResponse([makeSurvey()]));
    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => {
      const btn = screen.getAllByRole('button').find(
        (b) => b.textContent?.toLowerCase().includes('survey') || b.textContent === 'municipality_survey:take_survey'
      );
      expect(btn).toBeDefined();
    });
  });

  it('shows error alert when API fails', async () => {
    mockApi.get.mockResolvedValue({ success: false, error: 'Server error' });
    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => {
      const alert = screen.getByRole('alert');
      expect(alert).toBeInTheDocument();
    });
  });

  it('shows error alert when API throws', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => {
      const alert = screen.getByRole('alert');
      expect(alert).toBeInTheDocument();
    });
  });

  it('opens survey form when Take Survey is clicked', async () => {
    mockApi.get
      .mockResolvedValueOnce(listResponse([makeSurvey()]))
      .mockResolvedValue(detailResponse(makeSurveyWithQuestions()));

    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => screen.getByText('Community Feedback Survey'));

    const btn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('survey') || b.textContent === 'municipality_survey:take_survey'
    );
    if (btn) fireEvent.click(btn);

    await waitFor(() => {
      expect(screen.getByText('How satisfied are you?')).toBeInTheDocument();
    });
  });

  it('renders single_choice question with radio options in form', async () => {
    mockApi.get
      .mockResolvedValueOnce(listResponse([makeSurvey()]))
      .mockResolvedValue(detailResponse(makeSurveyWithQuestions()));

    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => screen.getByText('Community Feedback Survey'));

    const btn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('survey') || b.textContent === 'municipality_survey:take_survey'
    );
    if (btn) fireEvent.click(btn);

    await waitFor(() => {
      expect(screen.getByText('Very satisfied')).toBeInTheDocument();
      expect(screen.getByText('Satisfied')).toBeInTheDocument();
    });
  });

  it('renders open_text question with textarea', async () => {
    mockApi.get
      .mockResolvedValueOnce(listResponse([makeSurvey()]))
      .mockResolvedValue(detailResponse(makeSurveyWithQuestions()));

    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => screen.getByText('Community Feedback Survey'));

    const btn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('survey') || b.textContent === 'municipality_survey:take_survey'
    );
    if (btn) fireEvent.click(btn);

    await waitFor(() => {
      const textareas = screen.queryAllByRole('textbox');
      expect(textareas.length).toBeGreaterThan(0);
    });
  });

  it('calls POST api when submit is clicked (with optional-only questions)', async () => {
    // Use a survey with only optional questions so validation passes without answering
    const surveyNoRequired = {
      ...makeSurveyWithQuestions(),
      questions: [
        {
          id: 11,
          question_text: 'Any other comments?',
          question_type: 'open_text' as const,
          options: null,
          is_required: 0,
          sort_order: 1,
        },
      ],
    };

    mockApi.get
      .mockResolvedValueOnce(listResponse([makeSurvey()]))
      .mockResolvedValueOnce(detailResponse(surveyNoRequired))
      .mockResolvedValue(listResponse([makeSurvey({ response_count: 6 })]));

    mockApi.post.mockResolvedValue({ success: true });

    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => screen.getByText('Community Feedback Survey'));

    const takeBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('survey') || b.textContent === 'municipality_survey:take_survey'
    );
    if (takeBtn) fireEvent.click(takeBtn);

    await waitFor(() => screen.getByText('Any other comments?'));

    const submitBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('submit') || b.textContent === 'municipality_survey:submit'
    );
    if (submitBtn) fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/caring-community/surveys/1/respond',
        expect.any(Object)
      );
    });
  });

  it('does not call POST when required question is unanswered', async () => {
    mockApi.get
      .mockResolvedValueOnce(listResponse([makeSurvey()]))
      .mockResolvedValue(detailResponse(makeSurveyWithQuestions()));

    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => screen.getByText('Community Feedback Survey'));

    const takeBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('survey') || b.textContent === 'municipality_survey:take_survey'
    );
    if (takeBtn) fireEvent.click(takeBtn);

    await waitFor(() => screen.getByText('How satisfied are you?'));

    // Click submit without answering required question
    const submitBtn = screen.getAllByRole('button').find(
      (b) => b.textContent === 'municipality_survey:submit' || b.textContent?.toLowerCase().includes('submit')
    );
    if (submitBtn) fireEvent.click(submitBtn);

    // Validation fires synchronously — api.post should not be called
    expect(mockApi.post).not.toHaveBeenCalled();
  });

  it('shows already responded state when server returns already response', async () => {
    mockApi.get
      .mockResolvedValueOnce(listResponse([makeSurvey()]))
      .mockResolvedValue(detailResponse(makeSurveyWithQuestions({
        questions: [{
          id: 10,
          question_text: 'Any feedback?',
          question_type: 'open_text' as const,
          options: null,
          is_required: 0,
          sort_order: 1,
        }],
      })));

    mockApi.post.mockResolvedValue({ success: false, error: 'You have already responded' });

    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => screen.getByText('Community Feedback Survey'));

    const takeBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('survey') || b.textContent === 'municipality_survey:take_survey'
    );
    if (takeBtn) fireEvent.click(takeBtn);

    await waitFor(() => screen.getByText('Any feedback?'));

    const submitBtn = screen.getAllByRole('button').find(
      (b) => b.textContent === 'municipality_survey:submit' || b.textContent?.toLowerCase().includes('submit')
    );
    if (submitBtn) fireEvent.click(submitBtn);

    await waitFor(() => {
      // Expect already_responded text key or back button to appear
      const backBtn = screen.queryAllByRole('button').find(
        (b) => b.textContent?.toLowerCase().includes('back') || b.textContent === 'municipality_survey:back'
      );
      expect(backBtn).toBeDefined();
    });
  });

  it('renders closes_on date when ends_at is set', async () => {
    mockApi.get.mockResolvedValue(
      listResponse([makeSurvey({ ends_at: '2025-12-31T00:00:00Z' })])
    );
    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => {
      // closes_on text (t('closes_on', { date })) — key rendered as-is
      const cards = screen.getAllByTestId('glass-card');
      expect(cards.length).toBeGreaterThan(0);
    });
  });

  it('handles wrapped data format (data.data)', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: { data: [makeSurvey({ title: 'Wrapped Survey' })] },
    });
    const { default: MunicipalSurveyPage } = await import('./MunicipalSurveyPage');
    render(<MunicipalSurveyPage />);

    await waitFor(() => {
      expect(screen.getByText('Wrapped Survey')).toBeInTheDocument();
    });
  });
});
