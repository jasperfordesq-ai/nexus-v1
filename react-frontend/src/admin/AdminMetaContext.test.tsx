// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
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

// ─── Mock @/contexts ─────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub PageMeta so no real SEO side-effects in jsdom ──────────────────────
vi.mock('@/components/seo', () => ({
  PageMeta: ({ title, description }: { title?: string; description?: string }) => (
    <div data-testid="page-meta" data-title={title} data-description={description} />
  ),
}));

// ─── Test suite ─────────────────────────────────────────────────────────────
describe('AdminMetaContext', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('provides default meta to consumers via useResolvedAdminMeta', async () => {
    const { AdminMetaProvider, useResolvedAdminMeta } = await import('./AdminMetaContext');

    function Consumer() {
      const meta = useResolvedAdminMeta();
      return <div data-testid="meta-title">{meta.title}</div>;
    }

    render(
      <AdminMetaProvider defaultMeta={{ title: 'Admin Dashboard', description: 'Manage your platform' }}>
        <Consumer />
      </AdminMetaProvider>
    );

    expect(screen.getByTestId('meta-title')).toHaveTextContent('Admin Dashboard');
  });

  it('merges page-level meta over default meta', async () => {
    const { AdminMetaProvider, useResolvedAdminMeta, useAdminPageMeta } = await import('./AdminMetaContext');

    function PageConsumer() {
      useAdminPageMeta({ title: 'User List', description: 'All users' });
      return null;
    }

    function Display() {
      const meta = useResolvedAdminMeta();
      return (
        <div>
          <span data-testid="title">{meta.title}</span>
          <span data-testid="desc">{meta.description}</span>
        </div>
      );
    }

    render(
      <AdminMetaProvider defaultMeta={{ title: 'Admin', description: 'Default description' }}>
        <PageConsumer />
        <Display />
      </AdminMetaProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('title')).toHaveTextContent('User List');
      expect(screen.getByTestId('desc')).toHaveTextContent('All users');
    });
  });

  it('falls back to defaultMeta when page meta provides no override', async () => {
    const { AdminMetaProvider, useResolvedAdminMeta, useAdminPageMeta } = await import('./AdminMetaContext');

    function PageConsumer() {
      // Only set a title, not description — description should come from default
      useAdminPageMeta({ title: 'Members' });
      return null;
    }

    function Display() {
      const meta = useResolvedAdminMeta();
      return (
        <div>
          <span data-testid="title">{meta.title}</span>
          <span data-testid="desc">{meta.description}</span>
        </div>
      );
    }

    render(
      <AdminMetaProvider defaultMeta={{ title: 'Admin', description: 'Platform admin' }}>
        <PageConsumer />
        <Display />
      </AdminMetaProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('title')).toHaveTextContent('Members');
      expect(screen.getByTestId('desc')).toHaveTextContent('Platform admin');
    });
  });

  it('resets page meta to defaultMeta when PageConsumer unmounts', async () => {
    const { AdminMetaProvider, useResolvedAdminMeta, useAdminPageMeta } = await import('./AdminMetaContext');

    function PageConsumer() {
      useAdminPageMeta({ title: 'Temporary Page', description: 'Temporary' });
      return null;
    }

    function Display() {
      const meta = useResolvedAdminMeta();
      return <span data-testid="title">{meta.title}</span>;
    }

    const { rerender } = render(
      <AdminMetaProvider defaultMeta={{ title: 'Default Title' }}>
        <PageConsumer />
        <Display />
      </AdminMetaProvider>
    );

    await waitFor(() =>
      expect(screen.getByTestId('title')).toHaveTextContent('Temporary Page')
    );

    // Unmount the page consumer — meta should reset to default
    rerender(
      <AdminMetaProvider defaultMeta={{ title: 'Default Title' }}>
        <Display />
      </AdminMetaProvider>
    );

    await waitFor(() =>
      expect(screen.getByTestId('title')).toHaveTextContent('Default Title')
    );
  });

  it('useResolvedAdminMeta throws when used outside AdminMetaProvider', async () => {
    const { useResolvedAdminMeta } = await import('./AdminMetaContext');

    function BadConsumer() {
      useResolvedAdminMeta();
      return null;
    }

    // Suppress the expected error boundary output in test runner
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => render(<BadConsumer />)).toThrow(
      'useResolvedAdminMeta must be used within AdminMetaProvider'
    );

    spy.mockRestore();
  });

  it('AdminMetaTags renders PageMeta with resolved title and description', async () => {
    const { AdminMetaProvider, AdminMetaTags, useAdminPageMeta } = await import('./AdminMetaContext');

    function Page() {
      useAdminPageMeta({ title: 'Reports Page', description: 'View all reports' });
      return null;
    }

    render(
      <AdminMetaProvider defaultMeta={{ title: 'Admin', description: 'Default' }}>
        <Page />
        <AdminMetaTags />
      </AdminMetaProvider>
    );

    await waitFor(() => {
      const meta = screen.getByTestId('page-meta');
      expect(meta).toHaveAttribute('data-title', 'Reports Page');
      expect(meta).toHaveAttribute('data-description', 'View all reports');
    });
  });

  it('useAdminPageMeta is a no-op when used outside a provider (context is null)', async () => {
    const { useAdminPageMeta } = await import('./AdminMetaContext');

    // Should not throw when context is missing — setPageMeta guard returns early
    function SafeConsumer() {
      useAdminPageMeta({ title: 'Orphan' });
      return <div data-testid="safe">ok</div>;
    }

    expect(() => render(<SafeConsumer />)).not.toThrow();
    expect(screen.getByTestId('safe')).toHaveTextContent('ok');
  });

  it('supports keywords and type in page meta', async () => {
    const { AdminMetaProvider, useResolvedAdminMeta, useAdminPageMeta } = await import('./AdminMetaContext');

    function PageConsumer() {
      useAdminPageMeta({ title: 'Settings', keywords: 'config, admin', type: 'website' });
      return null;
    }

    function Display() {
      const meta = useResolvedAdminMeta();
      return (
        <div>
          <span data-testid="keywords">{meta.keywords}</span>
          <span data-testid="type">{meta.type}</span>
        </div>
      );
    }

    render(
      <AdminMetaProvider defaultMeta={{}}>
        <PageConsumer />
        <Display />
      </AdminMetaProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('keywords')).toHaveTextContent('config, admin');
      expect(screen.getByTestId('type')).toHaveTextContent('website');
    });
  });
});
