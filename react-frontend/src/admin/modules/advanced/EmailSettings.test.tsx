// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─── adminApi mock ─────────────────────────────────────────────────────────────
const { mockAdminSettings } = vi.hoisted(() => ({
  mockAdminSettings: {
    getEmailConfig: vi.fn(),
    updateEmailConfig: vi.fn(),
    testEmailProvider: vi.fn(),
    // unused stubs to prevent import errors
    get: vi.fn(),
    update: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminSettings: mockAdminSettings,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── contexts ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── admin sub-components ─────────────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({ title, description }: { title: string; description?: string }) => (
    <div>
      <h1>{title}</h1>
      {description && <p>{description}</p>}
    </div>
  ),
}));

// ─── fixtures ─────────────────────────────────────────────────────────────────
const makeEmailConfig = (overrides = {}) => ({
  success: true,
  data: {
    provider: 'platform_default',
    webhook_url: '',
    platform_default: { provider: 'sendgrid' },
    sendgrid: { from_email: 'noreply@example.com', from_name: 'MyTimebank', api_key_set: false },
    gmail_api: { client_id: '', client_secret_set: false, refresh_token_set: false, sender_email: '', sender_name: '' },
    smtp: { host: '', port: '587', user: '', password_set: false, encryption: 'tls', from_email: '', from_name: '' },
    ...overrides,
  },
});

// ─── tests ────────────────────────────────────────────────────────────────────
describe('EmailSettings', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminSettings.getEmailConfig.mockResolvedValue(makeEmailConfig());
  });

  it('shows a spinner while loading', async () => {
    mockAdminSettings.getEmailConfig.mockImplementationOnce(() => new Promise(() => {}));
    const { EmailSettings } = await import('./EmailSettings');
    render(<EmailSettings />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('hides spinner and renders the page after load', async () => {
    const { EmailSettings } = await import('./EmailSettings');
    render(<EmailSettings />);
    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    // At minimum the save button is present after load
    const saveBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('save'),
    );
    expect(saveBtn).toBeDefined();
  });

  it('shows error toast when config fails to load', async () => {
    mockAdminSettings.getEmailConfig.mockRejectedValue(new Error('network'));
    const { EmailSettings } = await import('./EmailSettings');
    render(<EmailSettings />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders platform_default info section when provider is platform_default', async () => {
    const { EmailSettings } = await import('./EmailSettings');
    render(<EmailSettings />);
    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    // Platform default chip shows the provider from the config
    expect(screen.getByText('sendgrid')).toBeInTheDocument();
  });

  it('calls updateEmailConfig when save button is clicked', async () => {
    mockAdminSettings.updateEmailConfig.mockResolvedValue({ data: { success: true } });
    const { EmailSettings } = await import('./EmailSettings');
    render(<EmailSettings />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    const saveBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('save'),
    );
    expect(saveBtn).toBeDefined();
    await userEvent.click(saveBtn!);

    await waitFor(() => {
      expect(mockAdminSettings.updateEmailConfig).toHaveBeenCalled();
    });
  });

  it('shows success toast when save succeeds', async () => {
    mockAdminSettings.updateEmailConfig.mockResolvedValue({ data: { success: true } });
    const { EmailSettings } = await import('./EmailSettings');
    render(<EmailSettings />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    const saveBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('save'),
    );
    await userEvent.click(saveBtn!);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when save fails', async () => {
    mockAdminSettings.updateEmailConfig.mockRejectedValue(new Error('server error'));
    const { EmailSettings } = await import('./EmailSettings');
    render(<EmailSettings />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    const saveBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('save'),
    );
    await userEvent.click(saveBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders test email button', async () => {
    const { EmailSettings } = await import('./EmailSettings');
    render(<EmailSettings />);
    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    const testBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('test') ||
             b.textContent?.toLowerCase().includes('send test'),
    );
    expect(testBtn).toBeDefined();
  });

  it('shows error toast when test email is sent with empty address', async () => {
    const { EmailSettings } = await import('./EmailSettings');
    render(<EmailSettings />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    const testBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('test') ||
             b.textContent?.toLowerCase().includes('send test'),
    );
    expect(testBtn).toBeDefined();
    await userEvent.click(testBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls testEmailProvider when address is filled and test is clicked', async () => {
    mockAdminSettings.testEmailProvider.mockResolvedValue({ data: { success: true, provider: 'sendgrid' } });
    const { EmailSettings } = await import('./EmailSettings');
    render(<EmailSettings />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    // Find the test email address input
    const inputs = document.querySelectorAll('input');
    const emailInput = Array.from(inputs).find(
      (inp) => inp.getAttribute('type') === 'email' || inp.getAttribute('placeholder')?.includes('test'),
    );
    if (emailInput) {
      await userEvent.type(emailInput, 'test@example.com');
    } else {
      // Fall back: first visible text input that could be the test email field
      const allInputs = Array.from(inputs).filter((inp) => !inp.hasAttribute('readonly'));
      if (allInputs.length > 0) await userEvent.type(allInputs[allInputs.length - 1], 'test@example.com');
    }

    const testBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('test') ||
             b.textContent?.toLowerCase().includes('send test'),
    );
    expect(testBtn).toBeDefined();
    await userEvent.click(testBtn!);

    await waitFor(() => {
      expect(mockAdminSettings.testEmailProvider).toHaveBeenCalledWith(
        expect.objectContaining({ to: 'test@example.com' }),
      );
    });
  });
});
