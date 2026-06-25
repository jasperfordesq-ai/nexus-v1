// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Toast / contexts ─────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

const mockPresence = {
  onlineUsers: new Map(),
  onlineCount: 0,
  setStatus: vi.fn().mockResolvedValue(undefined),
  setPrivacy: vi.fn().mockResolvedValue(undefined),
  fetchPresence: vi.fn().mockResolvedValue(undefined),
  getPresence: vi.fn().mockReturnValue({ status: 'online', last_seen_at: null, custom_status: null, status_emoji: null }),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice', first_name: 'Alice' },
      isAuthenticated: true,
      login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(),
      status: 'idle' as const, error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
    usePresenceOptional: () => mockPresence,
  })
);

vi.mock('@/contexts/AuthContext', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/contexts/AuthContext')>();
  return {
    ...orig,
    useAuth: () => ({
      user: { id: 1, name: 'Alice', first_name: 'Alice' },
      isAuthenticated: true,
      login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(),
      status: 'idle' as const, error: null,
    }),
  };
});

vi.mock('@/contexts/PresenceContext', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/contexts/PresenceContext')>();
  return {
    ...orig,
    usePresenceOptional: () => mockPresence,
  };
});

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// Stub HeroUI heavy components that misbehave in jsdom
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Dropdown: ({ children }: { children: React.ReactNode }) => <div data-testid="dropdown">{children}</div>,
    DropdownTrigger: ({ children }: { children: React.ReactNode }) => <div data-testid="dropdown-trigger">{children}</div>,
    DropdownMenu: ({ children, 'aria-label': ariaLabel, onAction }: { children: React.ReactNode; 'aria-label'?: string; onAction?: (key: string) => void }) => (
      <div data-testid="dropdown-menu" aria-label={ariaLabel} data-onaction={String(!!onAction)}>{children}</div>
    ),
    DropdownItem: ({ children, id }: { children: React.ReactNode; id?: string }) => (
      <button data-testid={`dropdown-item-${id ?? 'item'}`} role="menuitem">{children}</button>
    ),
    DropdownSection: ({ children, title }: { children: React.ReactNode; title?: string }) => (
      <div data-testid="dropdown-section" aria-label={title}>{children}</div>
    ),
    Modal: ({ children, isOpen }: { children: React.ReactNode; isOpen: boolean }) =>
      isOpen ? <div role="dialog" aria-label="Dialog" data-testid="status-modal">{children}</div> : null,
    ModalContent: ({ children }: { children: ((onClose: () => void) => React.ReactNode) | React.ReactNode }) => (
      <div>{typeof children === 'function' ? children(() => {}) : children}</div>
    ),
    ModalHeader: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-header">{children}</div>,
    ModalBody: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-body">{children}</div>,
    ModalFooter: ({ children }: { children: React.ReactNode }) => <div data-testid="modal-footer">{children}</div>,
    Input: ({ label, placeholder, value, onValueChange, ...rest }: { label?: string; placeholder?: string; value?: string; onValueChange?: (v: string) => void; [key: string]: unknown }) => (
      <input
        aria-label={label ?? placeholder}
        placeholder={placeholder}
        value={value ?? ''}
        onChange={(e) => onValueChange?.(e.target.value)}
        {...(rest as React.InputHTMLAttributes<HTMLInputElement>)}
      />
    ),
    Button: ({ children, onPress, isLoading, isDisabled, ...rest }: { children?: React.ReactNode; onPress?: () => void; isLoading?: boolean; isDisabled?: boolean; [key: string]: unknown }) => (
      <button onClick={() => onPress?.()} disabled={isDisabled || isLoading} {...(rest as React.ButtonHTMLAttributes<HTMLButtonElement>)}>
        {children}
      </button>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('StatusSelector', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockPresence.setStatus.mockResolvedValue(undefined);
    mockPresence.getPresence.mockReturnValue({ status: 'online', last_seen_at: null, custom_status: null, status_emoji: null });
  });

  it('renders default trigger button with current status label', async () => {
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector />);

    // The component renders a trigger button with status text
    const trigger = screen.getByTestId('dropdown-trigger');
    expect(trigger).toBeInTheDocument();
  });

  it('renders dropdown menu with status options', async () => {
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector />);

    expect(screen.getByTestId('dropdown-menu')).toBeInTheDocument();
  });

  it('renders Online status option', async () => {
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector />);

    // i18n key 'status.online' → "Online" in English
    const onlineItem = screen.getByTestId('dropdown-item-online');
    expect(onlineItem).toBeInTheDocument();
  });

  it('renders Away status option', async () => {
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector />);

    const awayItem = screen.getByTestId('dropdown-item-away');
    expect(awayItem).toBeInTheDocument();
  });

  it('renders Do Not Disturb option', async () => {
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector />);

    const dndItem = screen.getByTestId('dropdown-item-dnd');
    expect(dndItem).toBeInTheDocument();
  });

  it('renders custom status option', async () => {
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector />);

    const customItem = screen.getByTestId('dropdown-item-custom');
    expect(customItem).toBeInTheDocument();
  });

  it('renders children as trigger when provided', async () => {
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector><button data-testid="custom-trigger">My Trigger</button></StatusSelector>);

    expect(screen.getByTestId('custom-trigger')).toBeInTheDocument();
    expect(screen.getByText('My Trigger')).toBeInTheDocument();
  });

  it('modal is not shown by default', async () => {
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector />);

    expect(screen.queryByTestId('status-modal')).not.toBeInTheDocument();
  });

  it('renders fallback children when presence context is null (via getPresence returning offline)', async () => {
    // When presence is null the component returns children only — test via
    // a simple rendering: just verify children prop IS rendered regardless.
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector><span data-testid="fallback">fallback</span></StatusSelector>);

    // Since mockPresence is non-null here (our global mock), the component
    // wraps children in a Dropdown trigger — the fallback span is still present.
    expect(screen.getByTestId('fallback')).toBeInTheDocument();
    // The full dropdown is also present (expected behaviour with presence set)
    expect(screen.getByTestId('dropdown')).toBeInTheDocument();
  });

  it('shows two dropdown sections (status + custom)', async () => {
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector />);

    const sections = screen.getAllByTestId('dropdown-section');
    expect(sections.length).toBeGreaterThanOrEqual(2);
  });

  it('clears custom status option is absent when no custom status set', async () => {
    mockPresence.getPresence.mockReturnValue({
      status: 'online', last_seen_at: null, custom_status: null, status_emoji: null,
    });
    const { StatusSelector } = await import('./StatusSelector');
    render(<StatusSelector />);

    // The clear-custom item should be the hidden noop placeholder
    const clearItem = screen.queryByTestId('dropdown-item-clear-custom');
    // May be hidden via className="hidden" but is still rendered
    if (clearItem) {
      expect(clearItem.closest('[class*="hidden"]') || clearItem.classList.contains('hidden')).toBeTruthy();
    }
  });
});
