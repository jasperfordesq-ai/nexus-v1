// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LinkPreview component
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, act } from '@/test/test-utils';
import { LinkPreview } from './LinkPreview';

// Mock react-i18next
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const labels: Record<string, string> = {
        'compose.link_preview_loading': 'Loading link preview',
        'compose.link_preview_remove': 'Remove link preview',
        'compose.emoji_search': 'Search emoji',
      };
      return labels[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

// Mock api
const mockGet = vi.fn();

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
  },
}));

describe('LinkPreview', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers({ shouldAdvanceTime: true });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('returns null when no URL in content', () => {
    render(<LinkPreview content="Hello world, no links here" />);
    // No loading skeleton or preview card should be rendered
    expect(screen.queryByLabelText('Loading link preview')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Remove link preview')).not.toBeInTheDocument();
  });

  it('returns null for empty content', () => {
    render(<LinkPreview content="" />);
    expect(screen.queryByLabelText('Loading link preview')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Remove link preview')).not.toBeInTheDocument();
  });

  it('detects URL in content string and triggers fetch', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: {
        url: 'https://example.com',
        title: 'Example Site',
        description: 'An example website',
      },
    });

    render(<LinkPreview content="Check out https://example.com for details" />);

    // Advance past the debounce timer (800ms)
    await act(async () => {
      vi.advanceTimersByTime(900);
    });

    expect(mockGet).toHaveBeenCalledWith(
      expect.stringContaining('/v2/link-preview?url='),
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    );
  });

  it('shows loading skeleton when fetching', async () => {
    // Create a promise that we control
    let resolvePromise: (value: unknown) => void;
    const pendingPromise = new Promise((resolve) => {
      resolvePromise = resolve;
    });
    mockGet.mockReturnValue(pendingPromise);

    render(<LinkPreview content="Visit https://example.com today" />);

    // Advance past the debounce timer
    await act(async () => {
      vi.advanceTimersByTime(900);
    });

    // Should show loading skeleton
    expect(screen.getByLabelText('Loading link preview')).toBeInTheDocument();

    // Cleanup: resolve the pending promise
    await act(async () => {
      resolvePromise!({
        success: true,
        data: { url: 'https://example.com', title: 'Example' },
      });
    });
  });

  it('renders preview card with title and description', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: {
        url: 'https://example.com',
        title: 'Example Domain',
        description: 'This domain is for use in illustrative examples.',
        siteName: 'Example',
      },
    });

    render(<LinkPreview content="Check https://example.com" />);

    // Advance past debounce
    await act(async () => {
      vi.advanceTimersByTime(900);
    });

    await waitFor(() => {
      expect(screen.getByText('Example Domain')).toBeInTheDocument();
      expect(screen.getByText('This domain is for use in illustrative examples.')).toBeInTheDocument();
      expect(screen.getByText('Example')).toBeInTheDocument();
    });
  });

  it('renders preview card with image when provided', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: {
        url: 'https://example.com',
        title: 'Example With Image',
        image: 'https://example.com/og-image.jpg',
      },
    });

    render(<LinkPreview content="See https://example.com" />);

    await act(async () => {
      vi.advanceTimersByTime(900);
    });

    await waitFor(() => {
      const img = screen.getByRole('img');
      expect(img).toHaveAttribute('src', 'https://example.com/og-image.jpg');
      expect(img).toHaveAttribute('alt', 'Example With Image');
    });
  });

  it('shows dismiss button on preview card', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: {
        url: 'https://example.com',
        title: 'Dismissible Preview',
      },
    });

    render(<LinkPreview content="Visit https://example.com" />);

    await act(async () => {
      vi.advanceTimersByTime(900);
    });

    await waitFor(() => {
      expect(screen.getByLabelText('Remove link preview')).toBeInTheDocument();
    });
  });

  it('extracts domain from URL when siteName is not provided', async () => {
    mockGet.mockResolvedValue({
      success: true,
      data: {
        url: 'https://www.example.com/path/to/page',
        title: 'Some Page',
      },
    });

    render(<LinkPreview content="Read https://www.example.com/path/to/page" />);

    await act(async () => {
      vi.advanceTimersByTime(900);
    });

    await waitFor(() => {
      // Should display "example.com" (www. stripped)
      expect(screen.getByText('example.com')).toBeInTheDocument();
    });
  });

  it('returns null when API returns unsuccessful response', async () => {
    mockGet.mockResolvedValue({
      success: false,
      data: null,
    });

    render(<LinkPreview content="Check https://example.com" />);

    await act(async () => {
      vi.advanceTimersByTime(900);
    });

    // Wait for loading to finish, then verify nothing is rendered
    await waitFor(() => {
      expect(screen.queryByLabelText('Loading link preview')).not.toBeInTheDocument();
    });

    // No preview card should be shown
    expect(screen.queryByText('example.com')).not.toBeInTheDocument();
  });

  it('calls onPreviewData callback with preview data', async () => {
    const onPreviewData = vi.fn();
    const previewData = {
      url: 'https://example.com',
      title: 'Example',
      description: 'A description',
    };

    mockGet.mockResolvedValue({
      success: true,
      data: previewData,
    });

    render(<LinkPreview content="Visit https://example.com" onPreviewData={onPreviewData} />);

    await act(async () => {
      vi.advanceTimersByTime(900);
    });

    await waitFor(() => {
      expect(onPreviewData).toHaveBeenCalledWith(
        expect.objectContaining({
          url: 'https://example.com',
          title: 'Example',
          description: 'A description',
        }),
      );
    });
  });
});
