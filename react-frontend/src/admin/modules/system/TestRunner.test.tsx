// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Stable hoisted mock data ──────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));
const mockRunHealthCheck = vi.hoisted(() => vi.fn());

// ── Module mocks ──────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

vi.mock('../../api/adminApi', () => ({
  adminTools: {
    runHealthCheck: mockRunHealthCheck,
  },
}));

// usePageTitle sets document.title — stub it out
vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

import { TestRunner } from './TestRunner';

// ── Fixtures ──────────────────────────────────────────────────────────────────
const ALL_PASS_RESULTS = [
  { code: 'api_health', status: 'pass', duration_ms: 10 },
  { code: 'database', status: 'pass', duration_ms: 20 },
  { code: 'redis', status: 'pass', duration_ms: 5 },
  { code: 'auth_token', status: 'pass', duration_ms: 15 },
  { code: 'tenant_bootstrap', status: 'pass', duration_ms: 30 },
  { code: 'file_upload', status: 'pass', duration_ms: 50 },
  { code: 'email_service', status: 'pass', duration_ms: 100 },
  { code: 'pusher', status: 'pass', duration_ms: 200 },
];

const MIXED_RESULTS = ALL_PASS_RESULTS.map((r, i) =>
  i === 1 ? { ...r, status: 'fail', error: 'Connection refused' } : r,
);

describe('TestRunner', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── Initial render (all pending) ──────────────────────────────────────────
  it('renders the Run All Tests button', () => {
    render(<TestRunner />);
    expect(screen.getByRole('button', { name: /run all tests/i })).toBeInTheDocument();
  });

  it('renders all 8 initial test names in pending state', () => {
    render(<TestRunner />);
    expect(screen.getByText('API health check')).toBeInTheDocument();
    expect(screen.getByText('Database connection')).toBeInTheDocument();
    expect(screen.getByText('Redis connection')).toBeInTheDocument();
    expect(screen.getByText('Pusher WebSocket')).toBeInTheDocument();
  });

  it('does not show pass/fail chips before tests have run', () => {
    render(<TestRunner />);
    expect(screen.queryByText(/passed/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/failed/i)).not.toBeInTheDocument();
  });

  // ── All-pass scenario ──────────────────────────────────────────────────────
  it('calls runHealthCheck when button is clicked', async () => {
    mockRunHealthCheck.mockResolvedValue({ data: { tests: ALL_PASS_RESULTS } });
    render(<TestRunner />);

    await userEvent.click(screen.getByRole('button', { name: /run all tests/i }));

    await waitFor(() => {
      expect(mockRunHealthCheck).toHaveBeenCalledTimes(1);
    });
  });

  it('shows success toast when all checks pass', async () => {
    mockRunHealthCheck.mockResolvedValue({ data: { tests: ALL_PASS_RESULTS } });
    render(<TestRunner />);

    await userEvent.click(screen.getByRole('button', { name: /run all tests/i }));

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows pass count chip after all tests pass', async () => {
    mockRunHealthCheck.mockResolvedValue({ data: { tests: ALL_PASS_RESULTS } });
    render(<TestRunner />);

    await userEvent.click(screen.getByRole('button', { name: /run all tests/i }));

    await waitFor(() => {
      expect(screen.getByText(/8 passed/i)).toBeInTheDocument();
    });
  });

  it('shows duration (ms) for each test result', async () => {
    mockRunHealthCheck.mockResolvedValue({ data: { tests: ALL_PASS_RESULTS } });
    render(<TestRunner />);

    await userEvent.click(screen.getByRole('button', { name: /run all tests/i }));

    await waitFor(() => {
      expect(screen.getByText('10ms')).toBeInTheDocument();
    });
  });

  // ── Mixed pass/fail scenario ──────────────────────────────────────────────
  it('shows warning toast and fail chip when some tests fail', async () => {
    mockRunHealthCheck.mockResolvedValue({ data: { tests: MIXED_RESULTS } });
    render(<TestRunner />);

    await userEvent.click(screen.getByRole('button', { name: /run all tests/i }));

    await waitFor(() => {
      expect(mockToast.warning).toHaveBeenCalled();
    });

    expect(screen.getByText(/1 failed/i)).toBeInTheDocument();
  });

  it('renders a localized safe error for a failed test', async () => {
    mockRunHealthCheck.mockResolvedValue({ data: { tests: MIXED_RESULTS } });
    render(<TestRunner />);

    await userEvent.click(screen.getByRole('button', { name: /run all tests/i }));

    await waitFor(() => {
      expect(screen.getByText('Health Check failed')).toBeInTheDocument();
    });
  });

  // ── null / empty data from API ────────────────────────────────────────────
  it('marks all tests pass when API returns non-array data', async () => {
    mockRunHealthCheck.mockResolvedValue({ data: null });
    render(<TestRunner />);

    await userEvent.click(screen.getByRole('button', { name: /run all tests/i }));

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  // ── API error ────────────────────────────────────────────────────────────
  it('shows error toast and marks all tests failed when API throws', async () => {
    mockRunHealthCheck.mockRejectedValue(new Error('Unreachable'));
    render(<TestRunner />);

    await userEvent.click(screen.getByRole('button', { name: /run all tests/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });

    // All 8 should be fail — fail count chip appears
    expect(screen.getByText(/8 failed/i)).toBeInTheDocument();
  });

  it('renders error message "Health check API unavailable" for each test on API throw', async () => {
    mockRunHealthCheck.mockRejectedValue(new Error('Unreachable'));
    render(<TestRunner />);

    await userEvent.click(screen.getByRole('button', { name: /run all tests/i }));

    await waitFor(() => {
      const msgs = screen.getAllByText('Health check API unavailable');
      expect(msgs.length).toBeGreaterThanOrEqual(1);
    });
  });

  // ── Extra results from API are appended ───────────────────────────────────
  it('appends extra API result names not in the initial list', async () => {
    const extra = [...ALL_PASS_RESULTS, { code: 'custom_integration', status: 'pass', duration_ms: 12 }];
    mockRunHealthCheck.mockResolvedValue({ data: { tests: extra } });
    render(<TestRunner />);

    await userEvent.click(screen.getByRole('button', { name: /run all tests/i }));

    await waitFor(() => {
      expect(screen.getByText('Diagnostic: custom_integration')).toBeInTheDocument();
    });
  });
});
