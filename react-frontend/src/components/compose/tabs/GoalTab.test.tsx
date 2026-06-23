// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

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
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Toast / Auth / Tenant ────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
  useDraftPersistence: (
    _key: string,
    initial: { title: string; description: string },
  ) => {
    // NOTE: return React's stable `setVal` dispatch directly (it already supports
    // both value and updater-function forms). An inline arrow setter here is a NEW
    // function every render, which makes GoalTab's `[templateData, setDraft]` effect
    // re-fire infinitely (setDraft → new object → re-render → new setter → …).
    const [val, setVal] = React.useState(initial);
    const clear = React.useCallback(() => setVal(initial), []);
    return [val, setVal, clear];
  },
}));

vi.mock('@/hooks/useMediaQuery', () => ({
  useMediaQuery: vi.fn(() => false), // desktop by default
}));

// Stub heavy children
vi.mock('../shared/CharacterCount', () => ({
  CharacterCount: ({ current, max }: { current: number; max: number }) => (
    <span data-testid="char-count">{current}/{max}</span>
  ),
}));

vi.mock('../shared/EmojiPicker', () => ({
  EmojiPicker: ({ onSelect }: { onSelect: (e: string) => void }) => (
    <button data-testid="emoji-picker" onClick={() => onSelect('😊')}>
      Emoji
    </button>
  ),
}));

// Stub DatePicker to avoid jsdom calendar issues
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    DatePicker: ({ label }: { label: string }) => (
      <div data-testid="date-picker">{label}</div>
    ),
  };
});

// ─── Props helpers ────────────────────────────────────────────────────────────
const makeProps = (overrides = {}) => ({
  onSuccess: vi.fn(),
  onClose: vi.fn(),
  groupId: null,
  templateData: null,
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('GoalTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.post.mockResolvedValue({ success: true, data: { id: 99 } });
  });

  it('renders the title input with required marker', async () => {
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps()} />);
    const titleInput = screen.getByRole('textbox', { name: /title/i });
    expect(titleInput).toBeInTheDocument();
  });

  it('renders description textarea', async () => {
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps()} />);
    // Textarea has no implicit role but has label
    const desc = screen.getByRole('textbox', { name: /description/i });
    expect(desc).toBeInTheDocument();
  });

  it('renders character count component', async () => {
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps()} />);
    expect(screen.getByTestId('char-count')).toBeInTheDocument();
  });

  it('shows date picker for deadline', async () => {
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps()} />);
    expect(screen.getByTestId('date-picker')).toBeInTheDocument();
  });

  it('renders a make-public switch section', async () => {
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps()} />);
    // The public switch aria-label contains the translated key
    const sw = screen.getByRole('switch');
    expect(sw).toBeInTheDocument();
  });

  it('disables submit button when title is empty', async () => {
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps()} />);
    const submitBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('goal'),
    );
    // Button should be disabled when no title
    expect(submitBtn).toBeDisabled();
  });

  it('enables submit button when title is typed', async () => {
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps()} />);
    const titleInput = screen.getByRole('textbox', { name: /title/i });
    await userEvent.type(titleInput, 'My Test Goal');
    const submitBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('goal') && !b.textContent?.toLowerCase().includes('emoji'),
    );
    await waitFor(() => expect(submitBtn).not.toBeDisabled());
  });

  it('calls api.post to /v2/goals on submit', async () => {
    const onSuccess = vi.fn();
    const onClose = vi.fn();
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps({ onSuccess, onClose })} />);

    const titleInput = screen.getByRole('textbox', { name: /title/i });
    await userEvent.type(titleInput, 'Exercise Daily');

    const submitBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('goal') && !b.textContent?.toLowerCase().includes('emoji'),
    );
    await waitFor(() => expect(submitBtn).not.toBeDisabled());
    fireEvent.click(submitBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/goals',
        expect.objectContaining({ title: 'Exercise Daily' }),
      );
    });
  });

  it('calls onClose and onSuccess after successful submit', async () => {
    const onSuccess = vi.fn();
    const onClose = vi.fn();
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps({ onSuccess, onClose })} />);

    const titleInput = screen.getByRole('textbox', { name: /title/i });
    await userEvent.type(titleInput, 'Read a Book');

    const submitBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('goal') && !b.textContent?.toLowerCase().includes('emoji'),
    );
    await waitFor(() => expect(submitBtn).not.toBeDisabled());
    fireEvent.click(submitBtn!);

    await waitFor(() => {
      expect(onClose).toHaveBeenCalled();
      expect(onSuccess).toHaveBeenCalledWith('goal');
    });
  });

  it('shows error toast when api.post fails', async () => {
    mockApi.post.mockResolvedValue({ success: false });
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps()} />);

    const titleInput = screen.getByRole('textbox', { name: /title/i });
    await userEvent.type(titleInput, 'Fail Goal');

    const submitBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('goal') && !b.textContent?.toLowerCase().includes('emoji'),
    );
    await waitFor(() => expect(submitBtn).not.toBeDisabled());
    fireEvent.click(submitBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when api.post throws', async () => {
    mockApi.post.mockRejectedValue(new Error('network'));
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps()} />);

    const titleInput = screen.getByRole('textbox', { name: /title/i });
    await userEvent.type(titleInput, 'Error Goal');

    const submitBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('goal') && !b.textContent?.toLowerCase().includes('emoji'),
    );
    await waitFor(() => expect(submitBtn).not.toBeDisabled());
    fireEvent.click(submitBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('sends description in payload when provided', async () => {
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps()} />);

    const titleInput = screen.getByRole('textbox', { name: /title/i });
    await userEvent.type(titleInput, 'Goal With Desc');

    const descInput = screen.getByRole('textbox', { name: /description/i });
    await userEvent.type(descInput, 'Some motivation');

    const submitBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('goal') && !b.textContent?.toLowerCase().includes('emoji'),
    );
    await waitFor(() => expect(submitBtn).not.toBeDisabled());
    fireEvent.click(submitBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/goals',
        expect.objectContaining({ description: 'Some motivation' }),
      );
    });
  });

  it('calls onClose when Cancel button is clicked', async () => {
    const onClose = vi.fn();
    const { GoalTab } = await import('./GoalTab');
    render(<GoalTab {...makeProps({ onClose })} />);

    const cancelBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('cancel'),
    );
    if (cancelBtn) fireEvent.click(cancelBtn);
    expect(onClose).toHaveBeenCalled();
  });

  it('applies templateData title and content when provided', async () => {
    const { GoalTab } = await import('./GoalTab');
    render(
      <GoalTab
        {...makeProps({
          templateData: { title: 'Template Title', content: 'Template description' },
        })}
      />,
    );
    await waitFor(() => {
      const titleInput = screen.getByRole('textbox', { name: /title/i });
      expect((titleInput as HTMLInputElement).value).toBe('Template Title');
    });
  });
});
