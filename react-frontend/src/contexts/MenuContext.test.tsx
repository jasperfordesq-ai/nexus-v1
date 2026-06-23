// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, act } from '@/test/test-utils';
import React from 'react';

// ─── Mock menuApi (used inside useMenus) ─────────────────────────────────────
const { mockMenuApi } = vi.hoisted(() => ({
  mockMenuApi: { getMenus: vi.fn(), getMobileMenu: vi.fn() },
}));

vi.mock('@/lib/api', () => ({
  menuApi: mockMenuApi,
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

// ─── Mock AuthContext (imported as './AuthContext' inside MenuContext.tsx) ────
vi.mock('./AuthContext', () => ({
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
  AuthProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ─── Mock TenantContext (imported as './TenantContext' inside MenuContext.tsx) ─
vi.mock('./TenantContext', () => ({
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
  TenantProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeApiMenu = (overrides = {}) => ({
  id: 10,
  name: 'Main Nav',
  slug: 'main-nav',
  location: 'header-main',
  items: [{ id: 100, label: 'Home', url: '/', order: 1, children: [] }],
  ...overrides,
});

const makeMenusByLocation = (menus: ReturnType<typeof makeApiMenu>[]) => {
  const result: Record<string, ReturnType<typeof makeApiMenu>[]> = {};
  for (const m of menus) {
    const loc = m.location;
    if (!result[loc]) result[loc] = [];
    result[loc].push(m);
  }
  return result;
};

const emptyMenusResponse = { success: true, data: {}, error: null };
const makeMenusResponse = (menus: ReturnType<typeof makeApiMenu>[]) => ({
  success: true,
  data: makeMenusByLocation(menus),
  error: null,
});

// ─── Small consumer that reads from the real useMenuContext ───────────────────
// Imported dynamically per test so each test gets a fresh module + isolated state.
async function buildConsumer() {
  const { useMenuContext, MenuProvider } = await import('./MenuContext');

  function Consumer() {
    const ctx = useMenuContext();
    return (
      <div>
        <span data-testid="loading">{ctx.isLoading ? 'loading' : 'done'}</span>
        <span data-testid="has-custom">{ctx.hasCustomMenus ? 'yes' : 'no'}</span>
        <span data-testid="header-count">{ctx.headerMenus.length}</span>
        <span data-testid="mobile-count">{ctx.mobileMenus.length}</span>
        <span data-testid="footer-count">{ctx.footerMenus.length}</span>
        <button onClick={() => void ctx.refreshMenus()} data-testid="refresh">refresh</button>
      </div>
    );
  }

  return { Consumer, MenuProvider };
}

// ─────────────────────────────────────────────────────────────────────────────
describe('MenuContext / MenuProvider / useMenuContext', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockMenuApi.getMenus.mockResolvedValue(emptyMenusResponse);
  });

  it('provides isLoading=true initially (before fetch resolves)', async () => {
    mockMenuApi.getMenus.mockImplementation(() => new Promise(() => {}));
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    expect(screen.getByTestId('loading').textContent).toBe('loading');
  });

  it('transitions to done after fetch resolves', async () => {
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('done');
    });
  });

  it('provides empty arrays when API returns empty object', async () => {
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('done');
    });
    expect(screen.getByTestId('header-count').textContent).toBe('0');
    expect(screen.getByTestId('mobile-count').textContent).toBe('0');
    expect(screen.getByTestId('footer-count').textContent).toBe('0');
  });

  it('populates headerMenus from header-main location', async () => {
    mockMenuApi.getMenus.mockResolvedValue(
      makeMenusResponse([makeApiMenu({ location: 'header-main' })])
    );
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('header-count').textContent).toBe('1');
    });
  });

  it('populates mobileMenus from mobile location', async () => {
    mockMenuApi.getMenus.mockResolvedValue(
      makeMenusResponse([makeApiMenu({ id: 11, location: 'mobile', slug: 'mob-nav' })])
    );
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('mobile-count').textContent).toBe('1');
    });
  });

  it('populates footerMenus from footer location', async () => {
    mockMenuApi.getMenus.mockResolvedValue(
      makeMenusResponse([makeApiMenu({ id: 12, location: 'footer', slug: 'footer-nav' })])
    );
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('footer-count').textContent).toBe('1');
    });
  });

  it('sets hasCustomMenus=true when API returns real numeric-id menus with items', async () => {
    mockMenuApi.getMenus.mockResolvedValue(
      makeMenusResponse([makeApiMenu({ id: 99, slug: 'real-menu', location: 'header-main' })])
    );
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('has-custom').textContent).toBe('yes');
    });
  });

  it('sets hasCustomMenus=false when menus have default- slugs', async () => {
    mockMenuApi.getMenus.mockResolvedValue(
      makeMenusResponse([
        makeApiMenu({ id: 'default-main', slug: 'default-main-nav', location: 'header-main' }),
      ])
    );
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('has-custom').textContent).toBe('no');
    });
  });

  it('refreshMenus re-fetches menus from API', async () => {
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    await waitFor(() => expect(screen.getByTestId('loading').textContent).toBe('done'));

    const callsBefore = mockMenuApi.getMenus.mock.calls.length;

    await act(async () => {
      screen.getByTestId('refresh').click();
    });

    await waitFor(() => {
      expect(mockMenuApi.getMenus.mock.calls.length).toBeGreaterThan(callsBefore);
    });
  });

  it('handles API error gracefully — stays done without throwing', async () => {
    mockMenuApi.getMenus.mockRejectedValue(new Error('network error'));
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading').textContent).toBe('done');
    });
    expect(screen.getByTestId('has-custom').textContent).toBe('no');
  });

  it('throws when useMenuContext is used outside a MenuProvider', async () => {
    const { useMenuContext } = await import('./MenuContext');
    function BareConsumer() {
      useMenuContext();
      return null;
    }
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
    expect(() => render(<BareConsumer />)).toThrow(/MenuProvider/);
    spy.mockRestore();
  });

  it('groups array-format API response by location correctly', async () => {
    // API can return a flat array instead of a keyed object
    mockMenuApi.getMenus.mockResolvedValue({
      success: true,
      data: [
        makeApiMenu({ id: 20, location: 'header-main', slug: 'h-nav' }),
        makeApiMenu({ id: 21, location: 'footer', slug: 'f-nav' }),
      ],
      error: null,
    });
    const { Consumer, MenuProvider } = await buildConsumer();

    render(
      <MenuProvider>
        <Consumer />
      </MenuProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('header-count').textContent).toBe('1');
      expect(screen.getByTestId('footer-count').textContent).toBe('1');
    });
  });
});
