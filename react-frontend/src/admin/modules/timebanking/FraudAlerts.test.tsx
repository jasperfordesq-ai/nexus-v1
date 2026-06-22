// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mocks ──────────────────────────────────────────────────────────────────────
vi.mock('../../api/adminApi', () => ({
  adminTimebanking: {
    getAlerts: vi.fn(),
    updateAlertStatus: vi.fn(),
  },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

// AdminMetaContext — useAdminPageMeta just sets page title; mock as no-op
vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

import { adminTimebanking } from '../../api/adminApi';
import { FraudAlerts } from './FraudAlerts';
import type { FraudAlert } from '../../api/types';

const MOCK_ALERTS: FraudAlert[] = [
  {
    id: 10,
    user_id: 101,
    user_name: 'Eve Hacker',
    alert_type: 'rapid_transfers',
    severity: 'high',
    status: 'new',
    description: 'Suspicious rapid transfers detected',
    created_at: '2026-06-01T12:00:00Z',
  },
  {
    id: 11,
    user_id: 102,
    user_name: 'Charlie Suspect',
    alert_type: 'balance_anomaly',
    severity: 'medium',
    status: 'reviewing',
    description: 'Balance anomaly',
    created_at: '2026-06-02T08:00:00Z',
  },
];

describe('FraudAlerts', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner initially', () => {
    vi.mocked(adminTimebanking.getAlerts).mockReturnValue(new Promise(() => {}));
    render(<FraudAlerts />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeInTheDocument();
  });

  it('renders alert rows after data loads', async () => {
    vi.mocked(adminTimebanking.getAlerts).mockResolvedValue({
      success: true,
      data: MOCK_ALERTS,
    });
    render(<FraudAlerts />);
    await waitFor(() => {
      expect(screen.getByText('Eve Hacker')).toBeInTheDocument();
    });
    expect(screen.getByText('Charlie Suspect')).toBeInTheDocument();
  });

  it('renders status tab bar', async () => {
    vi.mocked(adminTimebanking.getAlerts).mockResolvedValue({
      success: true,
      data: [],
    });
    render(<FraudAlerts />);
    await waitFor(() => {
      expect(adminTimebanking.getAlerts).toHaveBeenCalled();
    });
    // Tabs for status filter should be present
    expect(screen.getByRole('tab', { name: /all/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /new/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /resolved/i })).toBeInTheDocument();
  });

  it('switches tab and re-fetches with status filter', async () => {
    vi.mocked(adminTimebanking.getAlerts).mockResolvedValue({
      success: true,
      data: [],
    });
    render(<FraudAlerts />);
    await waitFor(() => {
      expect(adminTimebanking.getAlerts).toHaveBeenCalledTimes(1);
    });

    const newTab = screen.getByRole('tab', { name: /^new$/i });
    await userEvent.click(newTab);

    await waitFor(() => {
      expect(adminTimebanking.getAlerts).toHaveBeenCalledTimes(2);
      expect(adminTimebanking.getAlerts).toHaveBeenLastCalledWith(
        expect.objectContaining({ status: 'new' })
      );
    });
  });

  it('shows error toast when API throws', async () => {
    vi.mocked(adminTimebanking.getAlerts).mockRejectedValue(new Error('Network error'));
    render(<FraudAlerts />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls updateAlertStatus when an action is selected from the dropdown', async () => {
    vi.mocked(adminTimebanking.getAlerts).mockResolvedValue({
      success: true,
      data: MOCK_ALERTS,
    });
    vi.mocked(adminTimebanking.updateAlertStatus).mockResolvedValue({ success: true });

    render(<FraudAlerts />);
    await waitFor(() => {
      expect(screen.getByText('Eve Hacker')).toBeInTheDocument();
    });

    // Open the actions dropdown for first row
    const actionButtons = screen.getAllByRole('button', {
      name: /actions/i,
    });
    await userEvent.click(actionButtons[0]);

    // Click the Resolve action (may appear multiple times for different rows — click first)
    const resolveItems = await screen.findAllByText(/^resolve$/i);
    await userEvent.click(resolveItems[0]);

    await waitFor(() => {
      expect(adminTimebanking.updateAlertStatus).toHaveBeenCalledWith(10, 'resolved');
      expect(mockToast.success).toHaveBeenCalled();
    });
  });
});
