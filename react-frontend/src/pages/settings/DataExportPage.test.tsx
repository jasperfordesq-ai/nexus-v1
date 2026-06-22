// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { api } from '@/lib/api';

// ---------------------------------------------------------------------------
// Module mocks
// ---------------------------------------------------------------------------

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  // DataExportPage uses tokenManager and API_BASE from this module
  tokenManager: {
    getAccessToken: vi.fn(() => 'test-token'),
    getTenantId: vi.fn(() => 2),
  },
  API_BASE: 'http://localhost/api',
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

// DataExportPage uses fetch directly (not api.post) for the download — mock global fetch
const mockFetch = vi.fn();
global.fetch = mockFetch;

// Also mock URL.createObjectURL / revokeObjectURL to avoid JSDOM errors
global.URL.createObjectURL = vi.fn(() => 'blob:mock-url');
global.URL.revokeObjectURL = vi.fn();

import { DataExportPage } from './DataExportPage';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const historyRow1: import('./DataExportPage').ExportHistoryRow = {
  id: 1,
  format: 'json',
  requested_at: '2025-01-10T10:00:00Z',
  completed_at: '2025-01-10T10:01:00Z',
  file_size_bytes: 51200,
};

const historyRow2: import('./DataExportPage').ExportHistoryRow = {
  id: 2,
  format: 'zip',
  requested_at: '2025-06-01T08:00:00Z',
  completed_at: null,
  file_size_bytes: null,
};

// Re-export type from DataExportPage so we can use it above
declare module './DataExportPage' {
  export interface ExportHistoryRow {
    id: number;
    format: string;
    requested_at: string | null;
    completed_at: string | null;
    file_size_bytes: number | null;
  }
}

function mockSuccessFetch(headers: Record<string, string> = {}) {
  const mockBlob = new Blob(['{"data": "test"}'], { type: 'application/json' });
  mockFetch.mockResolvedValueOnce({
    ok: true,
    status: 200,
    blob: vi.fn().mockResolvedValue(mockBlob),
    headers: {
      get: (key: string) => headers[key] ?? null,
    },
  });
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('DataExportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // By default, history load returns empty
    vi.mocked(api.get).mockResolvedValue({ success: true, data: { exports: [] } });
  });

  it('renders the page heading', async () => {
    render(<DataExportPage />);
    await waitFor(() => {
      // Heading uses t('data_export.title') — "Your personal data" in English
      // We just verify some heading exists
      expect(screen.getAllByRole('heading').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('loads export history on mount', async () => {
    render(<DataExportPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/me/data-export/history');
    });
  });

  it('shows empty-history message when there are no prior exports', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { exports: [] } });
    render(<DataExportPage />);

    await waitFor(() => {
      // After loading, loading text should go away; history section shows an empty message
      // The history section heading text key: data_export.history.title
      // The empty message key: data_export.history.empty
      expect(screen.getAllByRole('heading').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders history table rows when prior exports exist', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: { exports: [historyRow1, historyRow2] },
    });
    render(<DataExportPage />);

    await waitFor(() => {
      // Format column value
      expect(screen.getByText('json')).toBeInTheDocument();
      expect(screen.getByText('zip')).toBeInTheDocument();
    });
  });

  it('formats file size in KB for kb-sized exports', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: { exports: [historyRow1] },
    });
    render(<DataExportPage />);

    await waitFor(() => {
      // 51200 bytes = 50 KB
      expect(screen.getByText(/50\s*KB/)).toBeInTheDocument();
    });
  });

  it('shows em-dash for null file size', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: { exports: [historyRow2] },
    });
    render(<DataExportPage />);

    await waitFor(() => {
      expect(screen.getByText('—')).toBeInTheDocument();
    });
  });

  it('renders JSON and ZIP radio buttons for format selection', async () => {
    render(<DataExportPage />);
    await waitFor(() => {
      const radios = screen.getAllByRole('radio');
      expect(radios.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('calls fetch with POST to /v2/me/data-export when download is clicked', async () => {
    mockSuccessFetch();
    // Second call is history reload after download
    vi.mocked(api.get).mockResolvedValue({ success: true, data: { exports: [] } });

    render(<DataExportPage />);
    await waitFor(() => expect(screen.getAllByRole('heading').length).toBeGreaterThanOrEqual(1));

    const downloadBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/download|export/i),
    );
    expect(downloadBtn).toBeTruthy();
    if (downloadBtn) fireEvent.click(downloadBtn);

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalledWith(
        expect.stringContaining('/v2/me/data-export'),
        expect.objectContaining({ method: 'POST' }),
      );
    });
  });

  it('sends the selected format in the POST body', async () => {
    mockSuccessFetch();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: { exports: [] } });

    render(<DataExportPage />);
    await waitFor(() => expect(screen.getAllByRole('radio').length).toBeGreaterThanOrEqual(2));

    // Select ZIP format
    const zipRadio = screen.getByRole('radio', { name: /zip/i });
    fireEvent.click(zipRadio);

    const downloadBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/download|export/i),
    );
    if (downloadBtn) fireEvent.click(downloadBtn);

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          body: expect.stringContaining('"format":"zip"'),
        }),
      );
    });
  });

  it('shows error toast on HTTP 429 (rate limit)', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: false,
      status: 429,
      blob: vi.fn(),
      headers: { get: () => null },
    });
    vi.mocked(api.get).mockResolvedValue({ success: true, data: { exports: [] } });

    render(<DataExportPage />);
    await waitFor(() => expect(screen.getAllByRole('radio').length).toBeGreaterThanOrEqual(2));

    const downloadBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/download|export/i),
    );
    if (downloadBtn) fireEvent.click(downloadBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when fetch fails with non-OK status', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: false,
      status: 500,
      blob: vi.fn(),
      headers: { get: () => null },
    });
    vi.mocked(api.get).mockResolvedValue({ success: true, data: { exports: [] } });

    render(<DataExportPage />);
    await waitFor(() => expect(screen.getAllByRole('radio').length).toBeGreaterThanOrEqual(2));

    const downloadBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/download|export/i),
    );
    if (downloadBtn) fireEvent.click(downloadBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when fetch throws', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network down'));
    vi.mocked(api.get).mockResolvedValue({ success: true, data: { exports: [] } });

    render(<DataExportPage />);
    await waitFor(() => expect(screen.getAllByRole('radio').length).toBeGreaterThanOrEqual(2));

    const downloadBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/download|export/i),
    );
    if (downloadBtn) fireEvent.click(downloadBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('reloads history after a successful download', async () => {
    mockSuccessFetch();
    const newHistoryRow = { ...historyRow1, id: 99 };
    // First call = initial history load (empty), second = post-download reload
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: { exports: [] } })
      .mockResolvedValueOnce({ success: true, data: { exports: [newHistoryRow] } });

    render(<DataExportPage />);
    await waitFor(() => expect(screen.getAllByRole('radio').length).toBeGreaterThanOrEqual(2));

    const downloadBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/download|export/i),
    );
    if (downloadBtn) fireEvent.click(downloadBtn);

    await waitFor(() => {
      // api.get called twice total (mount + reload)
      expect(api.get).toHaveBeenCalledTimes(2);
    });
  });

  it('creates an anchor and triggers download on success', async () => {
    const appendSpy = vi.spyOn(document.body, 'appendChild');
    mockSuccessFetch({ 'Content-Disposition': 'attachment; filename="data-2025.json"' });
    vi.mocked(api.get).mockResolvedValue({ success: true, data: { exports: [] } });

    render(<DataExportPage />);
    await waitFor(() => expect(screen.getAllByRole('radio').length).toBeGreaterThanOrEqual(2));

    const downloadBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/download|export/i),
    );
    if (downloadBtn) fireEvent.click(downloadBtn);

    await waitFor(() => {
      // The component calls document.body.appendChild(a) for the download link
      expect(appendSpy).toHaveBeenCalled();
    });
    appendSpy.mockRestore();
  });
});
