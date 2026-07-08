// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock api ────────────────────────────────────────────────────────────────
const mockApi = vi.hoisted(() => ({
  get: vi.fn(),
  put: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

// ─── Mock AdminMetaContext ────────────────────────────────────────────────────
vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

// ─── Mock contexts ───────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Sample data ─────────────────────────────────────────────────────────────
// Use real data_type values that match the actual i18n keys in public/locales/en/admin_system.json
// Keys: type_activity_log, type_notifications, etc.
const POLICIES = [
  {
    data_type: 'activity_log',
    retention_days: 90,
    action: 'delete',
    is_enabled: false,
    updated_at: null,
  },
  {
    data_type: 'notifications',
    retention_days: 365,
    action: 'delete',
    is_enabled: true,
    updated_at: '2026-01-01T00:00:00Z',
  },
];

const LIMITS = { min_days: 30, max_days: 3650, actions: ['delete'] };

const RUNS = [
  {
    id: 1,
    data_type: 'notifications',
    action: 'delete',
    retention_days: 365,
    affected_rows: 42,
    status: 'completed',
    error: null,
    ran_at: '2026-06-01T03:00:00Z',
  },
];

function setupSuccess() {
  mockApi.get.mockImplementation((url: string) => {
    if (url.includes('policies')) {
      return Promise.resolve({ success: true, data: { policies: POLICIES, limits: LIMITS } });
    }
    if (url.includes('runs')) {
      return Promise.resolve({ success: true, data: { runs: RUNS } });
    }
    return Promise.resolve({ success: false });
  });
}

import { RetentionPolicies } from './RetentionPolicies';

describe('RetentionPolicies', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner initially', async () => {
    // Keep the promise pending
    mockApi.get.mockReturnValue(new Promise(() => {}));

    render(<RetentionPolicies />);

    const spinner = screen
      .getAllByRole('status')
      .find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeInTheDocument();
  });

  it('renders policy section heading after loading', async () => {
    setupSuccess();
    render(<RetentionPolicies />);

    // i18n: retention.policies_title → "Retention policies"
    await waitFor(() => {
      expect(screen.getByText('Retention policies')).toBeInTheDocument();
    });

    // Loading spinner gone
    expect(
      screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')
    ).toBeUndefined();
  });

  it('renders a row per policy data type', async () => {
    setupSuccess();
    render(<RetentionPolicies />);

    // i18n: retention.type_activity_log → "Activity log"
    await waitFor(() => {
      expect(screen.getAllByText('Activity log').length).toBeGreaterThan(0);
      // notifications
      expect(screen.getAllByText('Notifications').length).toBeGreaterThan(0);
    });
  });

  it('shows error toast when load fails', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'));
    render(<RetentionPolicies />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows empty runs message when runs array is empty', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('policies')) {
        return Promise.resolve({ success: true, data: { policies: [], limits: LIMITS } });
      }
      return Promise.resolve({ success: true, data: { runs: [] } });
    });

    render(<RetentionPolicies />);

    // i18n: retention.no_runs → "No disposal runs yet…"
    await waitFor(() => {
      expect(screen.getByText(/No disposal runs yet/i)).toBeInTheDocument();
    });
  });

  it('renders run history entries', async () => {
    setupSuccess();
    render(<RetentionPolicies />);

    // i18n: retention.status_completed → "Completed"
    await waitFor(() => {
      expect(screen.getByText('Completed')).toBeInTheDocument();
    });
  });

  it('calls PUT when Save button is pressed for a policy', async () => {
    setupSuccess();
    mockApi.put.mockResolvedValue({
      success: true,
      data: { policy: { ...POLICIES[0], is_enabled: false } },
    });

    const user = userEvent.setup();
    render(<RetentionPolicies />);

    // Wait for "Save" buttons to appear (i18n: retention.save → "Save")
    await waitFor(() => expect(screen.getAllByRole('button', { name: 'Save' }).length).toBeGreaterThan(0));

    const saveBtns = screen.getAllByRole('button', { name: 'Save' });
    await user.click(saveBtns[0]);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        expect.stringContaining('activity_log'),
        expect.objectContaining({ is_enabled: false })
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('calls PUT with toggled is_enabled when Switch is toggled', async () => {
    setupSuccess();
    mockApi.put.mockResolvedValue({
      success: true,
      data: { policy: { ...POLICIES[0], is_enabled: true } },
    });

    const user = userEvent.setup();
    render(<RetentionPolicies />);

    await waitFor(() => expect(screen.getAllByRole('switch').length).toBeGreaterThan(0));

    // activity_log is first (is_enabled:false → toggling to true)
    const switches = screen.getAllByRole('switch');
    await user.click(switches[0]);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        expect.stringContaining('activity_log'),
        expect.objectContaining({ is_enabled: true })
      );
    });
  });

  it('shows error toast when save fails', async () => {
    setupSuccess();
    mockApi.put.mockResolvedValue({ success: false, error: 'Server error' });

    const user = userEvent.setup();
    render(<RetentionPolicies />);

    await waitFor(() => expect(screen.getAllByRole('button', { name: 'Save' }).length).toBeGreaterThan(0));

    const saveBtns = screen.getAllByRole('button', { name: 'Save' });
    await user.click(saveBtns[0]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('updates the day input value when changed', async () => {
    setupSuccess();
    const user = userEvent.setup();
    render(<RetentionPolicies />);

    await waitFor(() => expect(screen.getAllByRole('spinbutton').length).toBeGreaterThan(0));

    // The first input corresponds to activity_log (retention_days: 90)
    const inputs = screen.getAllByRole('spinbutton');
    expect(inputs[0]).toHaveValue(90);

    await user.clear(inputs[0]);
    await user.type(inputs[0], '180');

    expect(inputs[0]).toHaveValue(180);
  });

  it('renders runs section heading', async () => {
    setupSuccess();
    render(<RetentionPolicies />);

    // i18n: retention.runs_title → "Recent disposal runs"
    await waitFor(() => {
      expect(screen.getByText('Recent disposal runs')).toBeInTheDocument();
    });
  });
});
