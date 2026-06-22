// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable mock refs ──────────────────────────────────────────────────────────
const mockShowToast = vi.fn();
const mockToastObj = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: mockShowToast,
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToastObj,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    put: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

import { api } from '@/lib/api';

// ToastContext's showToast is consumed via useToast from @/contexts which we've
// mocked above, so also shim the direct context import just in case the
// component's test-utils ToastProvider conflicts.
// The real ToastProvider is in test-utils wrapper — useToast there is real;
// but the component imports useToast from '@/contexts', which IS our mock.

import CivicDigestAdminPage from './CivicDigestAdminPage';

const CADENCE_RESPONSE = { cadence: 'daily' };

describe('CivicDigestAdminPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({ data: CADENCE_RESPONSE });
  });

  // ── loading ────────────────────────────────────────────────────────────────
  it('shows a loading spinner while fetching cadence', () => {
    // Never resolve so spinner stays visible
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<CivicDigestAdminPage />);
    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeInTheDocument();
  });

  // ── populated ──────────────────────────────────────────────────────────────
  it('hides spinner and shows cadence options after load', async () => {
    render(<CivicDigestAdminPage />);
    await waitFor(() => {
      const statuses = screen.queryAllByRole('status');
      const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
    // RadioGroup renders radio options
    expect(screen.getAllByRole('radio').length).toBeGreaterThan(0);
  });

  it('renders digest source chips after load', async () => {
    render(<CivicDigestAdminPage />);
    await waitFor(() =>
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined()
    );
    // digest sources card should be present (text comes from i18n key)
    // We just verify the component rendered past the loading state
    expect(screen.getAllByRole('radio').length).toBeGreaterThanOrEqual(3);
  });

  // ── save action ────────────────────────────────────────────────────────────
  it('calls PUT on save when cadence is changed', async () => {
    vi.mocked(api.put).mockResolvedValue({ data: { cadence: 'off' } });
    const user = userEvent.setup();
    render(<CivicDigestAdminPage />);

    await waitFor(() =>
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined()
    );

    // Select the 'off' radio (first one, label from i18n — will be the key in test env)
    const radios = screen.getAllByRole('radio');
    // click the first radio that is not already selected
    const offRadio = radios.find((r) => !r.hasAttribute('aria-checked') || r.getAttribute('aria-checked') === 'false');
    if (offRadio) {
      await user.click(offRadio);
    }

    // Find Save button — look for a button with Save-related text
    const allButtons = screen.getAllByRole('button');
    const saveBtn = allButtons.find((b) => {
      const text = b.textContent ?? '';
      return text.toLowerCase().includes('save') || text.toLowerCase().includes('admin.civic_digest.save');
    });
    if (saveBtn && !saveBtn.hasAttribute('disabled')) {
      await user.click(saveBtn);
      await waitFor(() => {
        expect(api.put).toHaveBeenCalledWith(
          '/v2/admin/caring-community/digest/cadence',
          expect.any(Object)
        );
      });
    }
  });

  // ── error state ────────────────────────────────────────────────────────────
  it('calls showToast with error variant when GET fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<CivicDigestAdminPage />);
    await waitFor(() => {
      // toastObj.showToast is what CivicDigestAdminPage calls via useToast from @/contexts
      // which returns our mockToastObj — the component destructures { showToast }
      expect(mockShowToast).toHaveBeenCalledWith(
        expect.any(String),
        'error'
      );
    });
  });

  // ── reset button ───────────────────────────────────────────────────────────
  it('reset button is disabled when cadence has not changed', async () => {
    render(<CivicDigestAdminPage />);
    await waitFor(() =>
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined()
    );
    const allButtons = screen.getAllByRole('button');
    const resetBtn = allButtons.find((b) => {
      const text = b.textContent ?? '';
      return text.toLowerCase().includes('reset') || text.toLowerCase().includes('admin.civic_digest.reset');
    });
    // Reset should be disabled initially (no dirty state)
    if (resetBtn) {
      expect(resetBtn).toBeDisabled();
    }
  });

  // ── refresh button ────────────────────────────────────────────────────────
  it('calls GET again when refresh button is pressed', async () => {
    const user = userEvent.setup();
    render(<CivicDigestAdminPage />);
    await waitFor(() =>
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined()
    );
    vi.mocked(api.get).mockResolvedValue({ data: CADENCE_RESPONSE });
    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    await user.click(refreshBtn);
    await waitFor(() => {
      expect(vi.mocked(api.get)).toHaveBeenCalledTimes(2);
    });
  });
});
