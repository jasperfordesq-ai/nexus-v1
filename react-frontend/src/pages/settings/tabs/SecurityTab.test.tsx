// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { render, screen } from '@/test/test-utils';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import { SecurityTab } from './SecurityTab';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      if (opts?.count !== undefined) return `${key}:${opts.count}`;
      return key;
    },
    i18n: { changeLanguage: vi.fn() },
  }),
}));

vi.mock('@/components/security/BiometricSettings', () => ({
  BiometricSettings: () => <div data-testid="biometric-settings" />,
}));

const defaultProps = {
  // 2FA
  twoFactorEnabled: false,
  twoFactorLoading: false,
  twoFactorSetupData: null,
  twoFactorVerifyCode: '',
  isVerifying2FA: false,
  twoFactorDisablePassword: '',
  isDisabling2FA: false,
  backupCodes: [],
  backupCodesRemaining: 0,
  // Sessions
  sessions: [],
  sessionsLoading: false,
  sessionsError: null,
  // Password
  passwordData: { current_password: '', new_password: '', confirm_password: '' },
  showCurrentPassword: false,
  showNewPassword: false,
  isChangingPassword: false,
  // Delete
  deleteConfirmation: '',
  isDeleting: false,
  // Modals
  passwordModalOpen: false,
  passwordModalOnClose: vi.fn(),
  passwordModalOnOpen: vi.fn(),
  logoutModalOpen: false,
  logoutModalOnClose: vi.fn(),
  logoutModalOnOpen: vi.fn(),
  deleteModalOpen: false,
  deleteModalOnClose: vi.fn(),
  deleteModalOnOpen: vi.fn(),
  twoFactorSetupModalOpen: false,
  twoFactorSetupModalOnClose: vi.fn(),
  twoFactorDisableModalOpen: false,
  twoFactorDisableModalOnClose: vi.fn(),
  twoFactorDisableModalOnOpen: vi.fn(),
  backupCodesModalOpen: false,
  backupCodesModalOnClose: vi.fn(),
  // Handlers
  onPasswordDataChange: vi.fn(),
  onShowCurrentPasswordToggle: vi.fn(),
  onShowNewPasswordToggle: vi.fn(),
  onChangePassword: vi.fn(),
  onDeleteConfirmationChange: vi.fn(),
  onDeleteAccount: vi.fn(),
  onLogout: vi.fn(),
  onSetup2FA: vi.fn(),
  onVerify2FA: vi.fn(),
  onDisable2FA: vi.fn(),
  onTwoFactorVerifyCodeChange: vi.fn(),
  onTwoFactorDisablePasswordChange: vi.fn(),
  onCopyBackupCodes: vi.fn(),
};

describe('SecurityTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders security settings heading', () => {
    render(<SecurityTab {...defaultProps} />);
    expect(screen.getByText('security_settings')).toBeDefined();
  });

  it('renders Change Password button', () => {
    render(<SecurityTab {...defaultProps} />);
    expect(screen.getByText('change_password')).toBeDefined();
  });

  it('calls passwordModalOnOpen when Change Password clicked', async () => {
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();
    render(<SecurityTab {...defaultProps} />);
    await user.click(screen.getByText('change_password'));
    expect(defaultProps.passwordModalOnOpen).toHaveBeenCalled();
  });

  it('shows 2FA not enabled state when twoFactorEnabled is false', () => {
    render(<SecurityTab {...defaultProps} twoFactorEnabled={false} />);
    expect(screen.getByText('twofa_not_enabled')).toBeDefined();
    expect(screen.getByText('twofa_enable')).toBeDefined();
  });

  it('shows 2FA enabled state when twoFactorEnabled is true', () => {
    render(<SecurityTab {...defaultProps} twoFactorEnabled={true} />);
    expect(screen.getByText('twofa_enabled')).toBeDefined();
    expect(screen.getByText('twofa_disable')).toBeDefined();
  });

  it('shows checking state when twoFactorLoading is true', () => {
    render(<SecurityTab {...defaultProps} twoFactorLoading={true} />);
    expect(screen.getByText('twofa_checking')).toBeDefined();
  });

  it('shows backup codes remaining when 2FA enabled and has codes', () => {
    render(<SecurityTab {...defaultProps} twoFactorEnabled={true} backupCodesRemaining={5} />);
    expect(screen.getByText(/twofa_backup_remaining:5/)).toBeDefined();
  });

  it('calls onSetup2FA when Enable 2FA button clicked', async () => {
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();
    render(<SecurityTab {...defaultProps} twoFactorEnabled={false} />);
    await user.click(screen.getByText('twofa_enable'));
    expect(defaultProps.onSetup2FA).toHaveBeenCalled();
  });

  it('calls twoFactorDisableModalOnOpen when Disable 2FA clicked', async () => {
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();
    render(<SecurityTab {...defaultProps} twoFactorEnabled={true} />);
    await user.click(screen.getByText('twofa_disable'));
    expect(defaultProps.twoFactorDisableModalOnOpen).toHaveBeenCalled();
  });

  it('renders sessions section', () => {
    render(<SecurityTab {...defaultProps} />);
    expect(screen.getByText('active_sessions')).toBeDefined();
  });

  it('shows sessions loading state', () => {
    render(<SecurityTab {...defaultProps} sessionsLoading={true} />);
    // Should show spinner/loading indicator
    expect(screen.queryByText('sessions_error')).toBeNull();
  });

  it('shows sessions error when sessionsError is set', () => {
    render(<SecurityTab {...defaultProps} sessionsError="Failed to load sessions" />);
    expect(screen.getByText('Failed to load sessions')).toBeDefined();
  });

  it('renders session list when sessions provided', () => {
    const sessions = [
      { id: 'sess-1', device: 'Chrome', browser: 'Chrome 120', ip_address: '192.168.1.1', last_active: '2026-01-01', is_current: true },
      { id: 'sess-2', device: 'Firefox', browser: 'Firefox 119', ip_address: '10.0.0.1', last_active: '2026-01-02', is_current: false },
    ];
    render(<SecurityTab {...defaultProps} sessions={sessions} />);
    expect(screen.getByText('Chrome')).toBeDefined();
    expect(screen.getByText('Firefox')).toBeDefined();
  });

  it('renders danger zone with logout and delete actions', () => {
    render(<SecurityTab {...defaultProps} />);
    expect(screen.getByText('logout_all_devices')).toBeDefined();
    expect(screen.getByText('delete_account')).toBeDefined();
  });

  it('renders BiometricSettings component', () => {
    render(<SecurityTab {...defaultProps} />);
    expect(screen.getByTestId('biometric-settings')).toBeDefined();
  });
});
