// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';
import React from 'react';

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

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle' as const, error: null }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Stub @/components/ui ────────────────────────────────────────────────────
// We stub Modal/ModalContent/ModalHeader/ModalBody so the picker opens in jsdom.
// ICON_MAP and ICON_NAMES are re-exported from the real module.
// Input gets a working onValueChange.
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();

  return {
    ...orig,
    // Button: minimal click-able stub
    Button: ({
      children,
      onPress,
      'aria-label': ariaLabel,
      title,
      ...rest
    }: React.ButtonHTMLAttributes<HTMLButtonElement> & {
      onPress?: () => void;
      children?: React.ReactNode;
      isIconOnly?: boolean;
      title?: string;
      variant?: string;
      size?: string;
    }) => (
      <button
        aria-label={ariaLabel}
        title={title}
        onClick={onPress}
        {...(rest as object)}
      >
        {children}
      </button>
    ),
    // Input: controlled via onValueChange
    Input: ({
      value,
      onValueChange,
      placeholder,
      'aria-label': ariaLabel,
      autoFocus,
    }: {
      value?: string;
      onValueChange?: (val: string) => void;
      placeholder?: string;
      'aria-label'?: string;
      autoFocus?: boolean;
      startContent?: React.ReactNode;
      size?: string;
    }) => (
      <input
        aria-label={ariaLabel}
        placeholder={placeholder}
        value={value ?? ''}
        autoFocus={autoFocus}
        onChange={(e) => onValueChange?.(e.target.value)}
      />
    ),
    // Modal: renders children when isOpen=true
    Modal: ({
      isOpen,
      children,
    }: {
      isOpen?: boolean;
      onClose?: () => void;
      children?: React.ReactNode;
      size?: string;
      scrollBehavior?: string;
    }) => isOpen ? <div role="dialog" aria-label="Dialog" data-testid="icon-modal">{children}</div> : null,
    // ModalContent: render-prop pattern (ModalContent can have function children)
    ModalContent: ({ children }: { children: React.ReactNode | ((onClose: () => void) => React.ReactNode) }) => (
      <div>{typeof children === 'function' ? children(() => {}) : children}</div>
    ),
    ModalHeader: ({ children }: { children: React.ReactNode }) => (
      <div data-testid="modal-header">{children}</div>
    ),
    ModalBody: ({ children }: { children: React.ReactNode }) => (
      <div data-testid="modal-body">{children}</div>
    ),
    // DynamicIcon: render a named span so we can check the selected value display
    DynamicIcon: ({ name, className }: { name: string; className?: string }) => (
      <span data-testid="dynamic-icon" className={className}>{name}</span>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────

describe('IconPicker', () => {
  const mockOnChange = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the trigger button with placeholder text when no value is set', async () => {
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value={null} onChange={mockOnChange} />);

    // The i18n key `icon_picker.search_for_icon` should appear (or its key fallback)
    const btn = screen.getAllByRole('button')[0];
    expect(btn).toBeInTheDocument();
  });

  it('shows the selected icon name in the trigger when value is set', async () => {
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value="Home" onChange={mockOnChange} />);

    // DynamicIcon renders the name; it should appear in trigger area
    expect(screen.getByTestId('dynamic-icon')).toHaveTextContent('Home');
    // Both the icon placeholder and the label span render the name — getAllByText is safe
    const homeNodes = screen.getAllByText('Home');
    expect(homeNodes.length).toBeGreaterThanOrEqual(1);
  });

  it('shows a clear button when a value is selected', async () => {
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value="Bell" onChange={mockOnChange} />);

    // Clear button has aria-label from i18n `icon_picker.clear_icon`
    const clearBtn = screen.getAllByRole('button').find(
      (btn) => btn.getAttribute('aria-label')?.toLowerCase().includes('clear')
    );
    expect(clearBtn).toBeDefined();
  });

  it('does not show a clear button when value is null', async () => {
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value={null} onChange={mockOnChange} />);

    const clearBtn = screen.getAllByRole('button').find(
      (btn) => btn.getAttribute('aria-label')?.toLowerCase().includes('clear')
    );
    expect(clearBtn).toBeUndefined();
  });

  it('calls onChange(null) when the clear button is clicked', async () => {
    const user = userEvent.setup();
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value="Bell" onChange={mockOnChange} />);

    const clearBtn = screen.getAllByRole('button').find(
      (btn) => btn.getAttribute('aria-label')?.toLowerCase().includes('clear')
    );
    if (clearBtn) await user.click(clearBtn);

    expect(mockOnChange).toHaveBeenCalledWith(null);
  });

  it('opens the modal when the trigger button is clicked', async () => {
    const user = userEvent.setup();
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value={null} onChange={mockOnChange} />);

    expect(screen.queryByTestId('icon-modal')).toBeNull();

    const triggerBtn = screen.getAllByRole('button')[0];
    await user.click(triggerBtn);

    await waitFor(() => {
      expect(screen.getByTestId('icon-modal')).toBeInTheDocument();
    });
  });

  it('shows an icon grid when the modal is open', async () => {
    const user = userEvent.setup();
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value={null} onChange={mockOnChange} />);

    await user.click(screen.getAllByRole('button')[0]);

    await waitFor(() => {
      // The grid is populated — there should be many icon buttons
      const iconBtns = screen.getAllByRole('button');
      // At least the search input + multiple icon buttons
      expect(iconBtns.length).toBeGreaterThan(2);
    });
  });

  it('filters icons when text is typed into the search input', async () => {
    const user = userEvent.setup();
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value={null} onChange={mockOnChange} />);

    await user.click(screen.getAllByRole('button')[0]);

    await waitFor(() => screen.getByTestId('icon-modal'));

    const searchInput = screen.getByRole('textbox');
    const beforeCount = screen.getAllByRole('button').length;

    await user.type(searchInput, 'zzznomatch');

    // After typing a non-matching query the count should drop (fewer icon buttons)
    const afterCount = screen.getAllByRole('button').length;
    expect(afterCount).toBeLessThan(beforeCount);
  });

  it('shows empty state message when search yields no results', async () => {
    const user = userEvent.setup();
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value={null} onChange={mockOnChange} />);

    await user.click(screen.getAllByRole('button')[0]);
    await waitFor(() => screen.getByTestId('icon-modal'));

    const searchInput = screen.getByRole('textbox');
    await user.type(searchInput, 'zzznomatch');

    // i18n key `icon_picker.no_icons_found` or fallback text
    await waitFor(() => {
      const noResult = screen.queryByText(/no.*icon|zzznomatch/i);
      expect(noResult).toBeInTheDocument();
    });
  });

  it('calls onChange with icon name when an icon is selected', async () => {
    const user = userEvent.setup();
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value={null} onChange={mockOnChange} />);

    await user.click(screen.getAllByRole('button')[0]);
    await waitFor(() => screen.getByTestId('icon-modal'));

    // Find a button with a title attribute (icon button) — click the first one
    const iconBtn = screen.getAllByRole('button').find(
      (btn) => btn.getAttribute('title') !== null
    );
    expect(iconBtn).toBeDefined();
    if (iconBtn) {
      await user.click(iconBtn);
      expect(mockOnChange).toHaveBeenCalledWith(expect.any(String));
      // The value passed should be the icon name (title attribute)
      expect(mockOnChange).toHaveBeenCalledWith(iconBtn.getAttribute('title'));
    }
  });

  it('closes the modal after selecting an icon', async () => {
    const user = userEvent.setup();
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value={null} onChange={mockOnChange} />);

    await user.click(screen.getAllByRole('button')[0]);
    await waitFor(() => screen.getByTestId('icon-modal'));

    const iconBtn = screen.getAllByRole('button').find(
      (btn) => btn.getAttribute('title') !== null
    );
    if (iconBtn) await user.click(iconBtn);

    await waitFor(() => {
      expect(screen.queryByTestId('icon-modal')).toBeNull();
    });
  });

  it('uses the provided label prop instead of the default', async () => {
    const { IconPicker } = await import('./IconPicker');
    render(<IconPicker value={null} onChange={mockOnChange} label="Pick your icon" />);

    expect(screen.getByText('Pick your icon')).toBeInTheDocument();
  });
});
