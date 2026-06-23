// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ─────────────────────────────────────────────────────────────────
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
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (s: unknown) => (s ? String(s) : null),
  formatRelativeTime: (s: string) => s,
}));

// ─── Context mocks ─────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Poll Author', first_name: 'Poll', avatar: null, role: 'user' },
      isAuthenticated: true,
      login: vi.fn(), logout: vi.fn(), register: vi.fn(),
      updateUser: vi.fn(), refreshUser: vi.fn(),
      status: 'idle' as const, error: null,
    }),
    useToast: () => mockToast,
  })
);
vi.mock('@/contexts/ToastContext', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/contexts/ToastContext')>();
  return { ...orig, useToast: () => mockToast };
});

// ─── Stub hooks ───────────────────────────────────────────────────────────────
vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
  useDraftPersistence: (key: string, initial: { question: string; options: string[] }) => {
    const React = require('react');
    const [val, setVal] = React.useState(initial);
    const clear = vi.fn(() => setVal(initial));
    return [val, setVal, clear];
  },
}));

vi.mock('@/hooks/useMediaQuery', () => ({ useMediaQuery: () => false }));

// ─── Stub ComposeSubmitContext ─────────────────────────────────────────────────
vi.mock('../ComposeSubmitContext', () => ({
  useComposeSubmit: () => ({
    registration: null,
    register: vi.fn(),
    unregister: vi.fn(),
  }),
}));

// ─── Stub heavy HeroUI + sub-components ──────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Button: ({ children, onPress, isLoading, isDisabled, startContent, 'aria-label': ariaLabel }: {
      children?: React.ReactNode; onPress?: () => void; isLoading?: boolean;
      isDisabled?: boolean; startContent?: React.ReactNode; 'aria-label'?: string;
    }) =>
      <button
        type="button"
        onClick={() => !isDisabled && onPress?.()}
        disabled={isDisabled}
        aria-busy={isLoading}
        aria-label={ariaLabel ?? (typeof children === 'string' ? children : undefined)}
      >
        {startContent}{children}
      </button>,
    Input: ({ value, onChange, placeholder, 'aria-label': ariaLabel, onValueChange }: {
      value?: string; onChange?: React.ChangeEventHandler<HTMLInputElement>;
      placeholder?: string; 'aria-label'?: string;
      onValueChange?: (v: string) => void;
    }) =>
      <input
        aria-label={ariaLabel ?? placeholder}
        placeholder={placeholder}
        value={value ?? ''}
        onChange={(e) => { onChange?.(e); onValueChange?.(e.target.value); }}
      />,
    Avatar: ({ name, src }: { name?: string; src?: string | null }) =>
      <img alt={name ?? 'avatar'} src={src ?? ''} data-testid="avatar" />,
    DatePicker: ({ label, description }: { label?: string; description?: string; [key: string]: unknown }) =>
      <div data-testid="date-picker" aria-label={label}>{description && <span>{description}</span>}</div>,
  };
});

vi.mock('../shared/CharacterCount', () => ({
  CharacterCount: ({ current, max }: { current: number; max: number }) =>
    <span data-testid="char-count">{current}/{max}</span>,
}));

vi.mock('../shared/EmojiPicker', () => ({
  EmojiPicker: ({ onSelect }: { onSelect: (e: string) => void }) =>
    <button type="button" data-testid="emoji-picker" onClick={() => onSelect('😊')}>Emoji</button>,
}));

// ─── Props factory ────────────────────────────────────────────────────────────
const makeProps = (overrides = {}) => ({
  onSuccess: vi.fn(),
  onClose: vi.fn(),
  groupId: null,
  templateData: null,
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('PollTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.post.mockResolvedValue({ success: true });
  });

  it('renders question input', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    const questionInput = screen.getByPlaceholderText(/question/i);
    expect(questionInput).toBeInTheDocument();
  });

  it('renders two option inputs by default', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    const optionInputs = screen.getAllByPlaceholderText(/option/i);
    expect(optionInputs.length).toBe(2);
  });

  it('renders the character count helper', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    expect(screen.getByTestId('char-count')).toBeInTheDocument();
  });

  it('shows the emoji picker trigger', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    expect(screen.getByTestId('emoji-picker')).toBeInTheDocument();
  });

  it('shows the date picker for poll end date', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    expect(screen.getByTestId('date-picker')).toBeInTheDocument();
  });

  it('renders "Add option" button', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    const addBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('add'));
    expect(addBtn).toBeInTheDocument();
  });

  it('adds a third option when Add option is clicked', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    const addBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('add'))!;
    fireEvent.click(addBtn);
    const optionInputs = screen.getAllByPlaceholderText(/option/i);
    expect(optionInputs.length).toBe(3);
  });

  it('does not show remove buttons when only 2 options exist', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    const removeButtons = screen.queryAllByRole('button').filter((b) => b.getAttribute('aria-label')?.toLowerCase().includes('remove'));
    expect(removeButtons.length).toBe(0);
  });

  it('shows remove button after a 3rd option is added', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    const addBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('add'))!;
    fireEvent.click(addBtn);
    const removeButtons = screen.getAllByRole('button').filter((b) => b.getAttribute('aria-label')?.toLowerCase().includes('remove'));
    expect(removeButtons.length).toBeGreaterThan(0);
  });

  it('renders cancel button on desktop (non-mobile)', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    const cancelBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('cancel'));
    expect(cancelBtn).toBeInTheDocument();
  });

  it('calls onClose when cancel is clicked', async () => {
    const onClose = vi.fn();
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps({ onClose })} />);
    const cancelBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('cancel'))!;
    fireEvent.click(cancelBtn);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('shows create poll submit button on desktop', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    const submitBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('poll') || b.textContent?.toLowerCase().includes('create')
    );
    expect(submitBtn).toBeInTheDocument();
  });

  it('submit button is disabled when question is empty', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    const submitBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('poll') || b.textContent?.toLowerCase().includes('create')
    );
    expect(submitBtn).toBeDisabled();
  });

  it('does not call api.post when question is blank (submit button disabled)', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    // Submit button should be disabled — clicking it should not trigger API
    const submitBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('poll') || b.textContent?.toLowerCase().includes('create')
    )!;
    expect(submitBtn).toBeDisabled();
    fireEvent.click(submitBtn);
    expect(mockApi.post).not.toHaveBeenCalled();
  });

  it('shows the user avatar', async () => {
    const { PollTab } = await import('./PollTab');
    render(<PollTab {...makeProps()} />);
    expect(screen.getByTestId('avatar')).toBeInTheDocument();
  });
});
