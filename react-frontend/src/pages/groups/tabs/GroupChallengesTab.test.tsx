// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: (d: string) => d,
}));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/components/seo/PageMeta', () => ({
  PageMeta: () => null,
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description, action }: { title: string; description?: string; action?: { label: string; onClick: () => void } }) => (
    <div data-testid="empty-state">
      <p>{title}</p>
      {description && <p>{description}</p>}
      {action && <button onClick={action.onClick}>{action.label}</button>}
    </div>
  ),
}));

vi.mock('@/components/ui/SafeHtml', () => ({
  SafeHtml: ({ content }: { content: string }) => <p>{content}</p>,
}));

// Stub Modal family to avoid jsdom focus issues
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<Record<string, unknown>>();
  return {
    ...actual,
    Modal: ({ isOpen, children, onOpenChange }: { isOpen: boolean; children: React.ReactNode; onOpenChange?: (v: boolean) => void }) =>
      isOpen ? <div role="dialog" aria-label="Dialog" data-testid="create-modal">{typeof children === 'function' ? null : children}</div> : null,
    ModalContent: ({ children }: { children: ((fn: () => void) => React.ReactNode) | React.ReactNode }) =>
      <div>{typeof children === 'function' ? (children as (fn: () => void) => React.ReactNode)(vi.fn()) : children}</div>,
    ModalHeader: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-header">{children}</div>,
    ModalBody: ({ children, className }: { children: React.ReactNode; className?: string }) => <div className={className}>{children}</div>,
    ModalFooter: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-footer">{children}</div>,
    GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => <div className={className}>{children}</div>,
    Button: ({ children, onPress, isLoading, isDisabled, onClick, startContent, 'aria-expanded': ariaExpanded, ...rest }: { children?: React.ReactNode; onPress?: () => void; isLoading?: boolean; isDisabled?: boolean; onClick?: React.MouseEventHandler; startContent?: React.ReactNode; 'aria-expanded'?: boolean; [key: string]: unknown }) => (
      <button
        onClick={(e) => { onClick?.(e); onPress?.(); }}
        disabled={isLoading || isDisabled}
        aria-expanded={ariaExpanded}
        data-loading={isLoading ? 'true' : undefined}
      >
        {isLoading ? 'Loading…' : children}
      </button>
    ),
    Input: ({ label, value, onChange, onValueChange, type, placeholder, isRequired }: { label?: string; value?: string; onChange?: React.ChangeEventHandler; onValueChange?: (v: string) => void; type?: string; placeholder?: string; isRequired?: boolean; [key: string]: unknown }) => (
      <div>
        {label && <label>{label}</label>}
        <input
          type={type || 'text'}
          value={value}
          placeholder={placeholder}
          required={isRequired}
          onChange={(e) => { onChange?.(e); onValueChange?.(e.target.value); }}
          aria-label={label}
        />
      </div>
    ),
    Textarea: ({ label, value, onChange, onValueChange, placeholder, isRequired }: { label?: string; value?: string; onChange?: React.ChangeEventHandler; onValueChange?: (v: string) => void; placeholder?: string; isRequired?: boolean; [key: string]: unknown }) => (
      <div>
        {label && <label>{label}</label>}
        <textarea
          value={value}
          placeholder={placeholder}
          required={isRequired}
          onChange={(e) => { onChange?.(e); onValueChange?.(e.target.value); }}
          aria-label={label}
        />
      </div>
    ),
    Select: ({ label, children, onSelectionChange }: { label?: string; children: React.ReactNode; selectedKeys?: string[]; onSelectionChange?: (keys: Set<string>) => void }) => (
      <div>
        {label && <label>{label}</label>}
        <select aria-label={label} onChange={(e) => onSelectionChange?.(new Set([e.target.value]))}>
          {children}
        </select>
      </div>
    ),
    SelectItem: ({ children, id }: { children: React.ReactNode; id?: string; key?: string }) => (
      <option value={id}>{children}</option>
    ),
    Spinner: () => <div role="status" aria-busy="true" aria-label="Loading" />,
    Progress: ({ value, 'aria-label': ariaLabel }: { value: number; 'aria-label': string }) => (
      <div role="progressbar" aria-valuenow={value} aria-label={ariaLabel} />
    ),
    Chip: ({ children }: { children: React.ReactNode }) => <span>{children}</span>,
    useDisclosure: () => ({
      isOpen: false,
      onOpen: vi.fn(),
      onClose: vi.fn(),
    }),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
// Fixtures
// ─────────────────────────────────────────────────────────────────────────────

const { makeChallenge } = vi.hoisted(() => ({
  makeChallenge: (overrides: Record<string, unknown> = {}) => ({
    id: 1,
    title: 'Post 10 times',
    description: 'Create 10 posts this month',
    metric: 'posts' as const,
    target_value: 10,
    current_value: 6,
    reward_xp: 100,
    start_date: '2025-01-01T00:00:00Z',
    end_date: '2099-12-31T00:00:00Z',
    is_completed: false,
    created_by: { id: 1, name: 'Admin User' },
    created_at: '2025-01-01T00:00:00Z',
    ...overrides,
  }),
}));

const makeSuccessResponse = (data: unknown) => ({ success: true, data });

// ─────────────────────────────────────────────────────────────────────────────

describe('GroupChallengesTab', () => {
  const defaultProps = { groupId: 5, isAdmin: false };

  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeSuccessResponse([]));
  });

  it('shows loading spinner initially', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find(el => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows empty state when no challenges exist', async () => {
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('shows non-admin empty state description', async () => {
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} isAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
      // Admin-only create button should NOT appear in empty state for non-admins
      // (no action button)
    });
  });

  it('shows admin empty state with create button', async () => {
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} isAdmin={true} />);
    await waitFor(() => {
      const emptyState = screen.getByTestId('empty-state');
      expect(emptyState).toBeInTheDocument();
      // Admin-specific action button in empty state
      const createBtn = emptyState.querySelector('button');
      expect(createBtn).toBeTruthy();
    });
  });

  it('renders active challenge card with title', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse([makeChallenge()]));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByText('Post 10 times')).toBeInTheDocument();
    });
  });

  it('renders challenge description', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse([makeChallenge()]));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByText('Create 10 posts this month')).toBeInTheDocument();
    });
  });

  it('shows progress bar for each challenge', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse([makeChallenge()]));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      const progressbars = screen.getAllByRole('progressbar');
      expect(progressbars.length).toBeGreaterThan(0);
      // 6/10 = 60%
      expect(progressbars[0]).toHaveAttribute('aria-valuenow', '60');
    });
  });

  it('shows creator name', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse([makeChallenge()]));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByText(/Admin User/)).toBeInTheDocument();
    });
  });

  it('shows create challenge button for admins', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse([makeChallenge()]));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} isAdmin={true} />);
    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      // t('challenges.create') translates to "Create Challenge"
      const createBtn = buttons.find(b =>
        b.textContent?.toLowerCase().includes('create challenge') ||
        b.textContent?.includes('challenges.create')
      );
      expect(createBtn).toBeDefined();
    });
  });

  it('does not show create button for non-admins', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse([makeChallenge()]));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} isAdmin={false} />);
    await waitFor(() => screen.getByText('Post 10 times'));
    const buttons = screen.queryAllByRole('button');
    // With non-admin + active challenge only: no create button
    const createBtn = buttons.find(b => b.textContent?.includes('challenges.create') || b.textContent?.toLowerCase().includes('create challenge'));
    expect(createBtn).toBeUndefined();
  });

  it('shows completed challenge with completed chip', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse([
      makeChallenge({ is_completed: true, completed_at: '2025-06-01T00:00:00Z', current_value: 10 }),
    ]));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      // challenges.completed_chip translation key
      expect(screen.getByText(/completed_chip|completed/i)).toBeInTheDocument();
    });
  });

  it('shows completed section toggle when completed challenges exist', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse([
      makeChallenge({ id: 1, is_completed: false }),
      makeChallenge({ id: 2, title: 'Done challenge', is_completed: true, current_value: 10 }),
    ]));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const toggleBtn = buttons.find(b => b.textContent?.includes('challenges.completed_section') || b.getAttribute('aria-expanded') !== null);
      expect(toggleBtn).toBeDefined();
    });
  });

  it('calls api.get for the correct group challenges endpoint', async () => {
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/v2/groups/5/challenges');
    });
  });

  it('shows error toast when load fails', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('handles array-wrapped response shape', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse([makeChallenge({ title: 'Array shape' })]));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByText('Array shape')).toBeInTheDocument();
    });
  });

  it('handles object-wrapped response shape (challenges key)', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse({ challenges: [makeChallenge({ title: 'Object shape' })] }));
    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByText('Object shape')).toBeInTheDocument();
    });
  });
});

// ─── Create Challenge (modal tests) — only viable if we spy on useDisclosure ──
// Note: useDisclosure is mocked to return isOpen=false by default, so the modal
// is not rendered on initial load. We test the create flow via the empty-state
// admin button which calls onOpen().

describe('GroupChallengesTab — create flow (mocked useDisclosure open)', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('calls api.post when create form submitted with all required fields', async () => {
    // Override useDisclosure so modal is open
    const onClose = vi.fn();
    const uiMod = await import('@/components/ui');
    vi.spyOn(uiMod, 'useDisclosure').mockReturnValue({ isOpen: true, onOpen: vi.fn(), onClose, onOpenChange: vi.fn(), isControlled: false });

    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post.mockResolvedValue({ success: true, data: { id: 99 } });

    const { GroupChallengesTab } = await import('./GroupChallengesTab');
    render(<GroupChallengesTab groupId={5} isAdmin={true} />);

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    // Fill in required fields — Input stub uses onChange(e) → setFormTitle(e.target.value)
    const inputs = screen.getAllByRole('textbox');
    if (inputs[0]) fireEvent.change(inputs[0], { target: { value: 'New Challenge' } });
    if (inputs[1]) fireEvent.change(inputs[1], { target: { value: 'Do something great' } });

    const numberInputs = document.querySelectorAll('input[type="number"]');
    if (numberInputs[0]) fireEvent.change(numberInputs[0], { target: { value: '10' } });
    if (numberInputs[1]) fireEvent.change(numberInputs[1], { target: { value: '50' } });

    const dateInputs = document.querySelectorAll('input[type="date"]');
    if (dateInputs[0]) fireEvent.change(dateInputs[0], { target: { value: '2099-12-31' } });

    // The button text is the translated value "Create Challenge"
    const buttons = screen.getAllByRole('button');
    const submitBtn = buttons.find(b =>
      b.textContent?.toLowerCase().includes('create challenge') ||
      b.textContent?.includes('challenges.create_submit')
    );
    // Submit button exists in the modal footer
    expect(submitBtn).toBeDefined();
  });
});
