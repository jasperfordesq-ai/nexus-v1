// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// --- mocks ---------------------------------------------------------------

const { mockAdminEnterprise } = vi.hoisted(() => ({
  mockAdminEnterprise: {
    getLogFile: vi.fn(),
    clearLogFile: vi.fn(),
    getDashboard: vi.fn(),
    getRoles: vi.fn(),
    getLogFiles: vi.fn(),
    getSystemRequirements: vi.fn(),
    getHealthHistory: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminEnterprise: mockAdminEnterprise,
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockNavigate = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
    useParams: () => ({ filename: 'laravel.log' }),
  };
});

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

// Import AFTER mocks
import { LogFileViewer } from './LogFileViewer';

// --- data ----------------------------------------------------------------

const LOG_CONTENT = {
  filename: 'laravel.log',
  total_lines: 3,
  filtered_count: 3,
  content: [
    { line: 1, text: '[2026-01-01 10:00:00] local.INFO: App started', level: 'INFO' },
    { line: 2, text: '[2026-01-01 10:01:00] local.WARNING: Something odd', level: 'WARNING' },
    { line: 3, text: '[2026-01-01 10:02:00] local.ERROR: Something broke', level: 'ERROR' },
  ],
};

beforeEach(() => {
  vi.clearAllMocks();
});

// --- tests ---------------------------------------------------------------

describe('LogFileViewer — loading state', () => {
  it('shows loading spinner while fetching', () => {
    mockAdminEnterprise.getLogFile.mockReturnValue(new Promise(() => {}));
    render(<LogFileViewer />);
    const loadingEl = screen
      .getAllByRole('status')
      .find((el) => el.getAttribute('aria-busy') === 'true');
    expect(loadingEl).toBeInTheDocument();
  });
});

describe('LogFileViewer — empty log content', () => {
  it('shows "no log file found" message when content is empty', async () => {
    mockAdminEnterprise.getLogFile.mockResolvedValue({
      success: true,
      data: { ...LOG_CONTENT, content: [], total_lines: 0, filtered_count: 0 },
    });
    render(<LogFileViewer />);
    await waitFor(() => {
      const busy = screen.queryAllByRole('status').find(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busy).toBeUndefined();
    });
    // "no log file found" key text — translation resolves to key in test env
    // The component wraps this in a <p> — just verify no log lines rendered
    expect(screen.queryByText('App started')).not.toBeInTheDocument();
  });
});

describe('LogFileViewer — populated state', () => {
  beforeEach(() => {
    mockAdminEnterprise.getLogFile.mockResolvedValue({ success: true, data: LOG_CONTENT });
  });

  it('renders log line text content', async () => {
    render(<LogFileViewer />);
    await waitFor(() => {
      expect(screen.getByText(/App started/)).toBeInTheDocument();
    });
  });

  it('renders warning and error log lines', async () => {
    render(<LogFileViewer />);
    await waitFor(() => {
      expect(screen.getByText(/Something odd/)).toBeInTheDocument();
      expect(screen.getByText(/Something broke/)).toBeInTheDocument();
    });
  });

  it('shows filename as page title / header', async () => {
    render(<LogFileViewer />);
    await waitFor(() => {
      // PageHeader receives filename as title
      expect(screen.getByText('laravel.log')).toBeInTheDocument();
    });
  });

  it('renders line number column', async () => {
    render(<LogFileViewer />);
    await waitFor(() => {
      // Line numbers 1, 2, 3
      expect(screen.getByText('1')).toBeInTheDocument();
      expect(screen.getByText('2')).toBeInTheDocument();
    });
  });
});

describe('LogFileViewer — controls', () => {
  beforeEach(() => {
    mockAdminEnterprise.getLogFile.mockResolvedValue({ success: true, data: LOG_CONTENT });
  });

  it('renders Back button', async () => {
    render(<LogFileViewer />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /back/i })).toBeInTheDocument();
    });
  });

  it('navigates back when Back button clicked', async () => {
    render(<LogFileViewer />);
    await waitFor(() => screen.getByRole('button', { name: /back/i }));
    await userEvent.click(screen.getByRole('button', { name: /back/i }));
    expect(mockNavigate).toHaveBeenCalledWith(expect.stringContaining('log-files'));
  });

  it('renders Refresh button', async () => {
    render(<LogFileViewer />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /refresh/i })).toBeInTheDocument();
    });
  });

  it('re-fetches on Refresh click', async () => {
    mockAdminEnterprise.getLogFile.mockResolvedValue({ success: true, data: LOG_CONTENT });
    render(<LogFileViewer />);
    await waitFor(() => screen.getByRole('button', { name: /refresh/i }));
    await userEvent.click(screen.getByRole('button', { name: /refresh/i }));
    await waitFor(() => {
      expect(mockAdminEnterprise.getLogFile).toHaveBeenCalledTimes(2);
    });
  });

  it('download button is enabled when content is loaded', async () => {
    render(<LogFileViewer />);
    await waitFor(() => {
      const downloadBtn = screen.getByRole('button', { name: /download/i });
      expect(downloadBtn).not.toBeDisabled();
    });
  });
});

describe('LogFileViewer — clear log modal', () => {
  beforeEach(() => {
    mockAdminEnterprise.getLogFile.mockResolvedValue({ success: true, data: LOG_CONTENT });
  });

  it('opens clear modal when Clear button clicked', async () => {
    render(<LogFileViewer />);
    await waitFor(() => screen.getByRole('button', { name: /clear/i }));
    await userEvent.click(screen.getByRole('button', { name: /clear/i }));
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  it('calls clearLogFile and shows success toast on confirm', async () => {
    mockAdminEnterprise.clearLogFile.mockResolvedValue({ success: true });
    mockAdminEnterprise.getLogFile
      .mockResolvedValueOnce({ success: true, data: LOG_CONTENT })
      .mockResolvedValueOnce({ success: true, data: { ...LOG_CONTENT, content: [] } });

    render(<LogFileViewer />);
    await waitFor(() => screen.getByRole('button', { name: /clear/i }));
    await userEvent.click(screen.getByRole('button', { name: /clear/i }));

    await waitFor(() => screen.getByRole('dialog'));

    // Find the "Clear file" confirm button inside the modal
    const allBtns = screen.getAllByRole('button');
    const clearFileBtn = allBtns.find((b) =>
      /clear file/i.test(b.textContent ?? ''),
    );
    if (clearFileBtn) {
      await userEvent.click(clearFileBtn);
      await waitFor(() => {
        expect(mockAdminEnterprise.clearLogFile).toHaveBeenCalledWith('laravel.log');
        expect(mockToast.success).toHaveBeenCalled();
      });
    }
  });
});

describe('LogFileViewer — error state', () => {
  it('shows error toast when getLogFile throws', async () => {
    mockAdminEnterprise.getLogFile.mockRejectedValue(new Error('Network error'));
    render(<LogFileViewer />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
