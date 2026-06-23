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

// ─── Safe storage (no-op in tests) ───────────────────────────────────────────
vi.mock('@/lib/safeStorage', () => ({
  safeLocalStorageGetJSON: vi.fn(() => []),
  safeLocalStorageSetJSON: vi.fn(),
  safeLocalStorageRemove: vi.fn(),
}));

// ─── Toast / Auth / Tenant / Theme ───────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return { ...orig, useNavigate: () => mockNavigate };
});

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Test User' },
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
    useTheme: () => ({
      resolvedTheme: 'light' as const,
      theme: 'system' as const,
      toggleTheme: vi.fn(),
      setTheme: vi.fn(),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub @/components/ui to avoid HeroUI jsdom issues ───────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  const KbdContent = ({ children }: { children: React.ReactNode }) => <kbd>{children}</kbd>;
  const KbdComponent = ({ children }: { children: React.ReactNode }) => <kbd>{children}</kbd>;
  const KbdWithContent = Object.assign(KbdComponent, { Content: KbdContent });
  return {
    ...orig,
    Button: ({ children, onPress, 'aria-label': al, isIconOnly: _io, ...rest }: { children?: React.ReactNode; onPress?: () => void; 'aria-label'?: string; isIconOnly?: boolean; [key: string]: unknown }) => (
      <button onClick={onPress} aria-label={al} {...(rest as Record<string, unknown>)}>{children}</button>
    ),
    Kbd: KbdWithContent,
  };
});

// ─── @react-aria/focus stub ──────────────────────────────────────────────────
vi.mock('@react-aria/focus', () => ({
  FocusScope: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ─────────────────────────────────────────────────────────────────────────────
describe('SearchOverlay', () => {
  const onClose = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue({ success: false, data: null });
  });

  it('renders nothing when isOpen=false', async () => {
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={false} onClose={onClose} />);
    expect(document.querySelector('[role="dialog"]')).toBeNull();
  });

  it('renders dialog when isOpen=true', async () => {
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    expect(document.querySelector('[role="dialog"]')).toBeTruthy();
  });

  it('shows search input placeholder text', async () => {
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    const input = document.querySelector('input[type="text"]');
    expect(input).toBeTruthy();
  });

  it('shows quick links section by default', async () => {
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    // At least one quick-link button should be present
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('calls onClose when ESC key is pressed', async () => {
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    fireEvent.keyDown(document, { key: 'Escape', code: 'Escape' });
    await waitFor(() => {
      expect(onClose).toHaveBeenCalled();
    });
  });

  it('calls onClose when close button is clicked', async () => {
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    const closeBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('close') ||
             b.getAttribute('aria-label')?.toLowerCase().includes('esc')
    );
    if (closeBtn) fireEvent.click(closeBtn);
    await waitFor(() => {
      expect(onClose).toHaveBeenCalled();
    });
  });

  it('shows action mode results when query starts with >', async () => {
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    const input = document.querySelector('input[type="text"]') as HTMLInputElement;
    fireEvent.change(input, { target: { value: '>' } });
    // Action mode text should appear
    await waitFor(() => {
      expect(document.body.textContent?.toLowerCase()).toMatch(/action/i);
    });
  });

  it('shows loading spinner while fetching suggestions', async () => {
    // Never resolves to keep loading state
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    const input = document.querySelector('input[type="text"]') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'hello' } });

    // Wait for debounce (250ms) + loading to appear
    await waitFor(
      () => {
        const spinners = screen.queryAllByRole('status');
        const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
        expect(busy).toBeDefined();
      },
      { timeout: 1000 }
    );
  });

  it('renders suggestions returned by API', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: {
        listings: [{ id: 10, title: 'Garden Help', type: 'listing' }],
        users: [],
        events: [],
        groups: [],
      },
    });
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    const input = document.querySelector('input[type="text"]') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'garden' } });

    await waitFor(() => {
      expect(screen.getByText('Garden Help')).toBeInTheDocument();
    }, { timeout: 1000 });
  });

  it('navigates when a suggestion is clicked', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: {
        listings: [{ id: 10, title: 'Garden Help', type: 'listing' }],
        users: [],
        events: [],
        groups: [],
      },
    });
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    const input = document.querySelector('input[type="text"]') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'garden' } });

    await waitFor(() => screen.getByText('Garden Help'), { timeout: 1000 });
    fireEvent.click(screen.getByText('Garden Help'));

    expect(mockNavigate).toHaveBeenCalledWith(expect.stringContaining('10'));
  });

  it('shows no-results message when API returns empty', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: { listings: [], users: [], events: [], groups: [] } });
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    const input = document.querySelector('input[type="text"]') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'zzznotfound' } });

    await waitFor(() => {
      expect(document.body.textContent).toMatch(/zzznotfound/);
    }, { timeout: 1000 });
  });

  it('shows clear button when query is non-empty', async () => {
    const { SearchOverlay } = await import('./SearchOverlay');
    render(<SearchOverlay isOpen={true} onClose={onClose} />);
    const input = document.querySelector('input[type="text"]') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'something' } });

    // aria-label on clear button
    await waitFor(() => {
      const clearBtn = screen.queryAllByRole('button').find(
        (b) => b.getAttribute('aria-label')?.toLowerCase().includes('clear')
      );
      expect(clearBtn).toBeTruthy();
    });
  });
});
