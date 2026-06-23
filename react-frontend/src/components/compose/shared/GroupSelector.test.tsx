// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Hoist all mocks — they must exist before any module resolution ───────────
const { mockApi, mockToast, mockUser } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
  mockToast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
    showToast: vi.fn(),
  },
  mockUser: { id: 5, name: 'Alice' },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts — useAuth must return a user with an id so fetch triggers ───────
vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: mockUser,
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
  }),
);

// ─── Stub Autocomplete + AutocompleteItem — both are React Aria backed and
//     crash in jsdom outside their collection context. Stub them fully.
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Autocomplete: ({
      label,
      placeholder,
      children,
    }: {
      label?: React.ReactNode;
      placeholder?: string;
      children?: React.ReactNode;
    }) => (
      <div data-testid="autocomplete">
        {label && <span data-testid="autocomplete-label">{label}</span>}
        <input
          data-testid="autocomplete-input"
          placeholder={placeholder}
          readOnly
          aria-label={typeof label === 'string' ? label : 'group selector'}
        />
        <div data-testid="autocomplete-items">{children}</div>
      </div>
    ),
    // AutocompleteItem is exported as ListBoxItem alias — stub BOTH to avoid collection error
    ListBoxItem: ({
      children,
      textValue,
    }: {
      children?: React.ReactNode;
      textValue?: string;
      id?: string;
    }) => (
      <div data-testid="autocomplete-item" data-value={textValue}>
        {children}
      </div>
    ),
    AutocompleteItem: ({
      children,
      textValue,
    }: {
      children?: React.ReactNode;
      textValue?: string;
      id?: string;
    }) => (
      <div data-testid="autocomplete-item" data-value={textValue}>
        {children}
      </div>
    ),
    Skeleton: ({ className }: { className?: string }) => (
      <div data-testid="skeleton" className={className} />
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeGroup = (overrides = {}) => ({
  id: 10,
  name: 'Neighbourhood Watch',
  member_count: 42,
  ...overrides,
});

const makeApiResponse = (groups: object[]) => ({
  success: true,
  data: groups,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('GroupSelector', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeApiResponse([]));
  });

  it('calls API to fetch groups on mount', async () => {
    const { GroupSelector } = await import('./GroupSelector');
    render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/v2/groups?member=me&per_page=50');
    });
  });

  it('renders nothing (no autocomplete) when groups are empty after load', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([]));
    const { GroupSelector } = await import('./GroupSelector');
    render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      // After load, groups=[] so component returns null
      expect(screen.queryByTestId('autocomplete')).not.toBeInTheDocument();
    });
  });

  it('shows loading skeletons while fetching', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {})); // never resolves
    const { GroupSelector } = await import('./GroupSelector');
    render(<GroupSelector value={null} onChange={vi.fn()} />);
    expect(screen.getAllByTestId('skeleton').length).toBeGreaterThanOrEqual(1);
  });

  it('renders autocomplete after groups load', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeGroup()]));
    const { GroupSelector } = await import('./GroupSelector');
    render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(screen.getByTestId('autocomplete')).toBeInTheDocument();
    });
  });

  it('renders group name in autocomplete items', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeGroup({ id: 10, name: 'Neighbourhood Watch' })]));
    const { GroupSelector } = await import('./GroupSelector');
    render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(screen.getByText('Neighbourhood Watch')).toBeInTheDocument();
    });
  });

  it('renders member count for groups that have it', async () => {
    mockApi.get.mockResolvedValue(makeApiResponse([makeGroup({ id: 10, name: 'Board Games Club', member_count: 7 })]));
    const { GroupSelector } = await import('./GroupSelector');
    render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      const items = screen.getByTestId('autocomplete-items');
      expect(items.textContent).toMatch(/7/);
    });
  });

  it('renders multiple groups as separate items', async () => {
    const groups = [
      makeGroup({ id: 10, name: 'Group Alpha' }),
      makeGroup({ id: 11, name: 'Group Beta' }),
      makeGroup({ id: 12, name: 'Group Gamma' }),
    ];
    mockApi.get.mockResolvedValue(makeApiResponse(groups));
    const { GroupSelector } = await import('./GroupSelector');
    render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(screen.getByText('Group Alpha')).toBeInTheDocument();
      expect(screen.getByText('Group Beta')).toBeInTheDocument();
      expect(screen.getByText('Group Gamma')).toBeInTheDocument();
    });
  });

  it('shows error toast when API call throws', async () => {
    mockApi.get.mockRejectedValue(new Error('network error'));
    const { GroupSelector } = await import('./GroupSelector');
    render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when API returns success=false', async () => {
    mockApi.get.mockResolvedValue({ success: false, error: 'Forbidden', data: null });
    const { GroupSelector } = await import('./GroupSelector');
    render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('handles non-array data gracefully (empty group list)', async () => {
    // API may return success=true but data is null/undefined in edge cases
    mockApi.get.mockResolvedValue({ success: true, data: null });
    const { GroupSelector } = await import('./GroupSelector');
    // Should not throw
    expect(() => render(<GroupSelector value={null} onChange={vi.fn()} />)).not.toThrow();
    await waitFor(() => {
      // groups.length=0 → null render
      expect(screen.queryByTestId('autocomplete')).not.toBeInTheDocument();
    });
  });
});
