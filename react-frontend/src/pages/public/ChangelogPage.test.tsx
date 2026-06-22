// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// PageMeta is globally mocked to a no-op in src/test/setup.ts.
// MarkdownRenderer is a real component; mock it to avoid markdown-parsing overhead.
vi.mock('@/components/content/MarkdownRenderer', () => ({
  MarkdownRenderer: ({ content }: { content: string }) => (
    <div data-testid="markdown-renderer">{content}</div>
  ),
}));

vi.mock('@/contexts', () => createMockContexts());

// usePageTitle sets document.title — mock the hook to keep tests simple.
vi.mock('@/hooks/usePageTitle', () => ({
  usePageTitle: vi.fn(),
}));

import { ChangelogPage } from './ChangelogPage';

function stubFetch(response: Partial<Response>) {
  vi.stubGlobal(
    'fetch',
    vi.fn().mockResolvedValue({
      ok: true,
      text: async () => '',
      ...response,
    } as Response)
  );
}

describe('ChangelogPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.unstubAllGlobals();
  });

  it('shows a loading spinner/status while fetch is pending', () => {
    vi.stubGlobal('fetch', vi.fn().mockReturnValue(new Promise(() => {})));
    const { container } = render(<ChangelogPage />);
    // The loading container carries both role="status" AND aria-busy="true".
    // We look for that specific element rather than using getByRole (which would
    // find multiple status elements — ToastProvider and the HeroUI Spinner also
    // render role="status" in the tree).
    const loadingDiv = container.querySelector('[role="status"][aria-busy="true"]');
    expect(loadingDiv).toBeInTheDocument();
    expect(screen.getByText(/loading changelog/i)).toBeInTheDocument();
  });

  it('renders the page heading', () => {
    vi.stubGlobal('fetch', vi.fn().mockReturnValue(new Promise(() => {})));
    render(<ChangelogPage />);
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(/changelog/i);
  });

  it('renders the GitHub link', () => {
    vi.stubGlobal('fetch', vi.fn().mockReturnValue(new Promise(() => {})));
    render(<ChangelogPage />);
    const githubLink = screen.getByRole('link', { name: /view on github/i });
    expect(githubLink).toHaveAttribute('href', expect.stringContaining('github.com'));
    expect(githubLink).toHaveAttribute('target', '_blank');
  });

  it('renders the MarkdownRenderer with fetched content on success', async () => {
    const markdownContent = '# v1.0.0\n\nFirst release.';
    stubFetch({ ok: true, text: async () => markdownContent });
    render(<ChangelogPage />);
    await waitFor(() => {
      const renderer = screen.getByTestId('markdown-renderer');
      expect(renderer).toBeInTheDocument();
      expect(renderer).toHaveTextContent('# v1.0.0');
    });
  });

  it('renders an error message when fetch returns a non-OK status', async () => {
    stubFetch({ ok: false, status: 404, text: async () => '' });
    render(<ChangelogPage />);
    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
    // i18n key: changelog_page.error
    expect(
      screen.getByText(/could not load the changelog/i)
    ).toBeInTheDocument();
  });

  it('renders an error message when fetch rejects', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network error')));
    render(<ChangelogPage />);
    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
    // The error message itself appears in the monospace <p>
    expect(screen.getByText('Network error')).toBeInTheDocument();
  });

  it('does NOT show the loading indicator after a successful fetch', async () => {
    stubFetch({ ok: true, text: async () => '# Done' });
    const { container } = render(<ChangelogPage />);
    await waitFor(() => {
      expect(screen.getByTestId('markdown-renderer')).toBeInTheDocument();
    });
    // After load completes, the element with BOTH role="status" AND aria-busy="true"
    // (the changelog loading div) should be gone. The ToastProvider also emits a
    // role="status" element which persists — we specifically target aria-busy="true".
    expect(container.querySelector('[role="status"][aria-busy="true"]')).toBeNull();
  });
});
