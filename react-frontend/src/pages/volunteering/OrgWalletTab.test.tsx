// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for OrgWalletTab — the org credit record (deposit / history).
 *
 * The per-deposit cap must match the backend (1,000), rejecting larger amounts
 * client-side with a clear error instead of a generic server 4xx.
 *
 * Note: the auto-pay toggle was removed — under auto-mint, approving hours always
 * credits the volunteer, so there is nothing to toggle.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import React from 'react';

const { toastMock } = vi.hoisted(() => ({
  toastMock: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
}));

vi.mock('@/lib/motion', () => ({
  motion: new Proxy({}, {
    get: () => ({ children, ref, ...props }: Record<string, unknown> & { ref?: React.Ref<HTMLElement> }) => {
      const motionProps = ['variants', 'initial', 'animate', 'exit', 'transition', 'whileHover', 'whileTap', 'layout', 'layoutId', 'viewport'];
      const clean: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(props)) {
        if (!motionProps.includes(k)) clean[k] = v;
      }
      return React.createElement('div', { ...clean, ref }, children as React.ReactNode);
    },
  }),
  AnimatePresence: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [], meta: { cursor: null, has_more: false } }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/contexts', () => ({
  useToast: () => toastMock,
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useAuth: () => ({ user: null, isAuthenticated: true, login: vi.fn(), logout: vi.fn() }),
  useTenant: () => ({ tenant: { id: 2 }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, hasFeature: () => true, hasModule: () => true }),
}));

// The component imports useDisclosure from the SUBPATH '@/components/ui/useDisclosure'
// (not the barrel), so that is the module that must be overridden to render the
// deposit modal open and exercise the deposit-amount validation.
vi.mock('@/components/ui/useDisclosure', () => ({
  useDisclosure: () => ({ isOpen: true, onOpen: vi.fn(), onClose: vi.fn(), onOpenChange: vi.fn(), onToggle: vi.fn() }),
}));
// Render Modal children unconditionally so the deposit form is queryable in jsdom.
vi.mock('@/components/ui/Modal', () => {
  const Passthrough = ({ children }: { children?: React.ReactNode }) =>
    <div>{typeof children === 'function' ? (children as (arg: unknown) => React.ReactNode)(vi.fn()) : children}</div>;
  return {
    Modal: Passthrough,
    ModalContent: Passthrough,
    ModalHeader: Passthrough,
    ModalBody: Passthrough,
    ModalFooter: Passthrough,
  };
});

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import OrgWalletTab from './OrgWalletTab';
import { api } from '@/lib/api';

const baseProps = { orgId: 5, balance: 100, autoPay: false, onBalanceChange: vi.fn() };

describe('OrgWalletTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [], meta: { cursor: null, has_more: false } });
  });

  it('rejects a deposit above the 1,000 backend cap without calling the API', async () => {
    render(<OrgWalletTab {...baseProps} />);
    const amount = await screen.findByLabelText('Amount (hours)');
    fireEvent.change(amount, { target: { value: '1001' } });
    fireEvent.click(screen.getByRole('button', { name: 'Deposit' }));

    await waitFor(() => expect(toastMock.error).toHaveBeenCalled());
    expect(api.post).not.toHaveBeenCalled();
  });

  it('accepts a deposit at or below the cap and posts it', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true });
    render(<OrgWalletTab {...baseProps} />);
    const amount = await screen.findByLabelText('Amount (hours)');
    fireEvent.change(amount, { target: { value: '100' } });
    fireEvent.click(screen.getByRole('button', { name: 'Deposit' }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/volunteering/organisations/5/wallet/deposit', { amount: 100, note: null });
    });
  });

  // Fix 3: deposits are whole credits only server-side — reject fractional
  // amounts client-side before calling the API.
  it('rejects a fractional deposit without calling the API', async () => {
    render(<OrgWalletTab {...baseProps} />);
    const amount = await screen.findByLabelText('Amount (hours)');
    fireEvent.change(amount, { target: { value: '10.5' } });
    fireEvent.click(screen.getByRole('button', { name: 'Deposit' }));

    await waitFor(() => expect(toastMock.error).toHaveBeenCalled());
    expect(api.post).not.toHaveBeenCalled();
  });

  it('explains that volunteers are paid automatically (no auto-pay toggle)', async () => {
    render(<OrgWalletTab {...baseProps} />);
    expect(await screen.findByText('Volunteers are paid automatically')).toBeInTheDocument();
    // The old auto-pay switch is gone.
    expect(screen.queryByRole('checkbox', { name: /auto-pay/i })).not.toBeInTheDocument();
  });
});
