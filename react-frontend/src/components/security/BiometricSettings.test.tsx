// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// Mock webauthn module
const mockIsBiometricAvailable = vi.fn();
const mockRegisterBiometric = vi.fn();
const mockGetWebAuthnCredentials = vi.fn();
const mockRemoveWebAuthnCredential = vi.fn();
const mockRemoveAllWebAuthnCredentials = vi.fn();
const mockDetectPlatform = vi.fn();

vi.mock('@/lib/webauthn', () => ({
  isBiometricAvailable: (...args: unknown[]) => mockIsBiometricAvailable(...args),
  registerBiometric: (...args: unknown[]) => mockRegisterBiometric(...args),
  getWebAuthnCredentials: (...args: unknown[]) => mockGetWebAuthnCredentials(...args),
  removeWebAuthnCredential: (...args: unknown[]) => mockRemoveWebAuthnCredential(...args),
  removeAllWebAuthnCredentials: (...args: unknown[]) => mockRemoveAllWebAuthnCredentials(...args),
  detectPlatform: (...args: unknown[]) => mockDetectPlatform(...args),
}));

// Mock toast context
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn() };
vi.mock('@/contexts', () => ({
  useToast: () => mockToast,
}));

// Mock i18next
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: { defaultValue?: string }) => opts?.defaultValue || key,
  }),
}));

// Mock HeroUI components minimally
vi.mock('@heroui/react', async () => {
  const React = await import('react');
  return {
    Button: ({ children, onPress, isDisabled, isLoading, ...props }: Record<string, unknown>) =>
      React.createElement('button', {
        onClick: onPress as (() => void) | undefined,
        disabled: isDisabled || isLoading,
        'data-testid': props['data-testid'],
      }, isLoading ? 'Loading...' : children),
    Spinner: () => React.createElement('div', { 'data-testid': 'spinner' }, 'Loading...'),
    Tooltip: ({ children }: { children: React.ReactNode }) => React.createElement(React.Fragment, null, children),
    Modal: ({ isOpen, children }: { isOpen: boolean; children: React.ReactNode }) =>
      isOpen ? React.createElement('div', { 'data-testid': 'modal', role: 'dialog' }, children) : null,
    ModalContent: ({ children }: { children: ((onClose: () => void) => React.ReactNode) | React.ReactNode }) =>
      React.createElement('div', null, typeof children === 'function' ? children(() => {}) : children),
    ModalHeader: ({ children }: { children: React.ReactNode }) =>
      React.createElement('div', { 'data-testid': 'modal-header' }, children),
    ModalBody: ({ children }: { children: React.ReactNode }) =>
      React.createElement('div', { 'data-testid': 'modal-body' }, children),
    ModalFooter: ({ children }: { children: React.ReactNode }) =>
      React.createElement('div', { 'data-testid': 'modal-footer' }, children),
    useDisclosure: () => {
      const [isOpen, setIsOpen] = React.useState(false);
      return {
        isOpen,
        onOpen: () => setIsOpen(true),
        onOpenChange: (open: boolean) => setIsOpen(open),
        onClose: () => setIsOpen(false),
      };
    },
  };
});

import { BiometricSettings } from './BiometricSettings';

describe('BiometricSettings', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockDetectPlatform.mockReturnValue('windows');
    mockIsBiometricAvailable.mockResolvedValue(true);
    // getWebAuthnCredentials returns Credential[] directly (not {credentials, count})
    mockGetWebAuthnCredentials.mockResolvedValue([]);
  });

  it('renders loading state initially', () => {
    mockGetWebAuthnCredentials.mockReturnValue(new Promise(() => {})); // never resolves
    render(<BiometricSettings />);
    expect(screen.getByTestId('spinner')).toBeDefined();
  });

  it('renders empty state when no credentials', async () => {
    render(<BiometricSettings />);
    await waitFor(() => {
      expect(screen.getByText('Create a passkey')).toBeDefined();
    });
  });

  it('shows "Add another passkey" when credentials exist', async () => {
    mockGetWebAuthnCredentials.mockResolvedValue([
      { credential_id: 'abc123', device_name: 'Windows Hello', authenticator_type: 'platform', created_at: '2026-01-01', last_used_at: null },
    ]);
    render(<BiometricSettings />);
    await waitFor(() => {
      expect(screen.getByText('Add another passkey')).toBeDefined();
    });
  });

  it('calls registerBiometric when create button is clicked', async () => {
    mockRegisterBiometric.mockResolvedValue({ success: true });
    const user = userEvent.setup();

    render(<BiometricSettings />);
    await waitFor(() => {
      expect(screen.getByText('Create a passkey')).toBeDefined();
    });

    await user.click(screen.getByText('Create a passkey'));
    expect(mockRegisterBiometric).toHaveBeenCalled();
  });

  it('shows success toast on registration', async () => {
    mockRegisterBiometric.mockResolvedValue({ success: true });
    const user = userEvent.setup();

    render(<BiometricSettings />);
    await waitFor(() => {
      expect(screen.getByText('Create a passkey')).toBeDefined();
    });

    await user.click(screen.getByText('Create a passkey'));
    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalledWith('Passkey registered successfully!');
    });
  });

  it('shows error toast on registration failure', async () => {
    mockRegisterBiometric.mockResolvedValue({ success: false, error: 'Aborted' });
    const user = userEvent.setup();

    render(<BiometricSettings />);
    await waitFor(() => {
      expect(screen.getByText('Create a passkey')).toBeDefined();
    });

    await user.click(screen.getByText('Create a passkey'));
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Aborted');
    });
  });

  it('shows confirmation modal when "Remove All Passkeys" is clicked', async () => {
    mockGetWebAuthnCredentials.mockResolvedValue([
      { credential_id: 'abc', device_name: 'Test', authenticator_type: 'platform', created_at: '2026-01-01', last_used_at: null },
      { credential_id: 'def', device_name: 'Test 2', authenticator_type: 'cross-platform', created_at: '2026-01-02', last_used_at: null },
    ]);
    const user = userEvent.setup();

    render(<BiometricSettings />);
    // Wait for credentials to load (button only shows when > 1 credential)
    await waitFor(() => {
      expect(screen.getByText('Test')).toBeDefined();
    });

    // The button text is "Remove All Passkeys"
    const removeAllBtn = screen.getByText('Remove All Passkeys');
    await user.click(removeAllBtn);
    await waitFor(() => {
      expect(screen.getByTestId('modal')).toBeDefined();
    });
  });

  it('renders not-supported message when WebAuthn unavailable', async () => {
    mockIsBiometricAvailable.mockResolvedValue(false);

    render(<BiometricSettings />);
    await waitFor(() => {
      expect(screen.getByText(/does not support passkeys/i)).toBeDefined();
    });
  });

  it('shows credential device name', async () => {
    mockGetWebAuthnCredentials.mockResolvedValue([
      { credential_id: 'a', device_name: 'Device A', authenticator_type: 'platform', created_at: '2026-01-01', last_used_at: null },
    ]);

    render(<BiometricSettings />);
    await waitFor(() => {
      expect(screen.getByText('Device A')).toBeDefined();
    });
  });

  it('shows setup instructions by default', async () => {
    render(<BiometricSettings />);
    await waitFor(() => {
      // showInstructions defaults to true, so multi-device note is visible
      expect(screen.getByText(/iCloud Keychain syncs across Apple devices/)).toBeDefined();
    });
  });
});
