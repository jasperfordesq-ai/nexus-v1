// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Button,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Chip,
  Spinner,
} from '@heroui/react';
import {
  Lock,
  Key,
  LogOut,
  Trash2,
  AlertTriangle,
  Eye,
  EyeOff,
  Monitor,
  QrCode,
  ShieldCheck,
  ShieldOff,
  Copy,
  CheckCircle,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { BiometricSettings } from '@/components/security/BiometricSettings';
import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface SessionInfo {
  id: string;
  device: string;
  browser: string;
  ip_address: string;
  last_active: string;
  is_current: boolean;
}

export interface TwoFactorSetup {
  qr_code_url: string;
  secret: string;
  backup_codes: string[];
}

interface SecurityTabProps {
  // 2FA
  twoFactorEnabled: boolean;
  twoFactorLoading: boolean;
  twoFactorSetupData: TwoFactorSetup | null;
  twoFactorVerifyCode: string;
  isVerifying2FA: boolean;
  twoFactorDisablePassword: string;
  isDisabling2FA: boolean;
  backupCodes: string[];
  backupCodesRemaining: number;
  // Sessions
  sessions: SessionInfo[];
  sessionsLoading: boolean;
  sessionsError: string | null;
  // Password
  passwordData: { current_password: string; new_password: string; confirm_password: string };
  showCurrentPassword: boolean;
  showNewPassword: boolean;
  isChangingPassword: boolean;
  // Delete
  deleteConfirmation: string;
  isDeleting: boolean;
  // Modal disclosure objects
  passwordModalOpen: boolean;
  passwordModalOnClose: () => void;
  passwordModalOnOpen: () => void;
  logoutModalOpen: boolean;
  logoutModalOnClose: () => void;
  logoutModalOnOpen: () => void;
  deleteModalOpen: boolean;
  deleteModalOnClose: () => void;
  deleteModalOnOpen: () => void;
  twoFactorSetupModalOpen: boolean;
  twoFactorSetupModalOnClose: () => void;
  twoFactorDisableModalOpen: boolean;
  twoFactorDisableModalOnClose: () => void;
  twoFactorDisableModalOnOpen: () => void;
  backupCodesModalOpen: boolean;
  backupCodesModalOnClose: () => void;
  // Handlers
  onPasswordDataChange: (updater: (prev: { current_password: string; new_password: string; confirm_password: string }) => { current_password: string; new_password: string; confirm_password: string }) => void;
  onShowCurrentPasswordToggle: () => void;
  onShowNewPasswordToggle: () => void;
  onChangePassword: () => void;
  onDeleteConfirmationChange: (value: string) => void;
  onDeleteAccount: () => void;
  onLogout: () => void;
  onSetup2FA: () => void;
  onVerify2FA: () => void;
  onDisable2FA: () => void;
  onTwoFactorVerifyCodeChange: (value: string) => void;
  onTwoFactorDisablePasswordChange: (value: string) => void;
  onCopyBackupCodes: () => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// Common style classNames
// ─────────────────────────────────────────────────────────────────────────────

const inputClassNames = {
  input: 'bg-transparent text-theme-primary',
  inputWrapper: 'bg-theme-elevated border-theme-default',
  label: 'text-theme-muted',
};

const modalClassNames = {
  base: 'bg-content1 border border-theme-default',
  header: 'border-b border-theme-default',
  body: 'py-6',
  footer: 'border-t border-theme-default',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function SecurityTab({
  twoFactorEnabled,
  twoFactorLoading,
  twoFactorSetupData,
  twoFactorVerifyCode,
  isVerifying2FA,
  twoFactorDisablePassword,
  isDisabling2FA,
  backupCodes,
  backupCodesRemaining,
  sessions,
  sessionsLoading,
  sessionsError,
  passwordData,
  showCurrentPassword,
  showNewPassword,
  isChangingPassword,
  deleteConfirmation,
  isDeleting,
  passwordModalOpen,
  passwordModalOnClose,
  passwordModalOnOpen,
  logoutModalOpen,
  logoutModalOnClose,
  logoutModalOnOpen,
  deleteModalOpen,
  deleteModalOnClose,
  deleteModalOnOpen,
  twoFactorSetupModalOpen,
  twoFactorSetupModalOnClose,
  twoFactorDisableModalOpen,
  twoFactorDisableModalOnClose,
  twoFactorDisableModalOnOpen,
  backupCodesModalOpen,
  backupCodesModalOnClose,
  onPasswordDataChange,
  onShowCurrentPasswordToggle,
  onShowNewPasswordToggle,
  onChangePassword,
  onDeleteConfirmationChange,
  onDeleteAccount,
  onLogout,
  onSetup2FA,
  onVerify2FA,
  onDisable2FA,
  onTwoFactorVerifyCodeChange,
  onTwoFactorDisablePasswordChange,
  onCopyBackupCodes,
}: SecurityTabProps) {
  const { t } = useTranslation('settings');

  return (
    <>
      <div className="space-y-6">
        {/* Password & 2FA */}
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-6">{t('security_settings')}</h2>

          <div className="space-y-4">
            {/* Change Password */}
            <Button
              variant="light"
              className="w-full flex items-center justify-between p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover h-auto text-left"
              onPress={passwordModalOnOpen}
            >
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-indigo-500/20">
                  <Lock className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="font-medium text-theme-primary">{t('change_password')}</p>
                  <p className="text-sm text-theme-subtle">{t('change_password_subtitle')}</p>
                </div>
              </div>
            </Button>

            {/* Two-Factor Authentication */}
            <div className="w-full p-4 rounded-lg bg-theme-elevated text-left">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="p-2 rounded-lg bg-emerald-500/20">
                    <Key className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                  </div>
                  <div>
                    <p className="font-medium text-theme-primary">{t('twofa_title')}</p>
                    <p className="text-sm text-theme-subtle">
                      {twoFactorLoading ? (
                        t('twofa_checking')
                      ) : twoFactorEnabled ? (
                        <span className="flex items-center gap-1">
                          <ShieldCheck className="w-3 h-3 text-emerald-500" aria-hidden="true" />
                          {t('twofa_enabled')}
                          {backupCodesRemaining > 0 && (
                            <span className="text-theme-subtle">
                              {' '}&mdash; {t('twofa_backup_remaining', { count: backupCodesRemaining })}
                            </span>
                          )}
                        </span>
                      ) : (
                        <span className="flex items-center gap-1">
                          <ShieldOff className="w-3 h-3 text-amber-500" aria-hidden="true" />
                          {t('twofa_not_enabled')}
                        </span>
                      )}
                    </p>
                  </div>
                </div>
                {!twoFactorLoading && (
                  <div>
                    {twoFactorEnabled ? (
                      <Button
                        size="sm"
                        variant="flat"
                        className="bg-red-500/10 text-red-500"
                        onPress={twoFactorDisableModalOnOpen}
                      >
                        {t('twofa_disable')}
                      </Button>
                    ) : (
                      <Button
                        size="sm"
                        className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
                        onPress={onSetup2FA}
                      >
                        {t('twofa_enable')}
                      </Button>
                    )}
                  </div>
                )}
              </div>
            </div>

            {/* Biometric / Passkey Authentication */}
            <BiometricSettings />
          </div>
        </GlassCard>

        {/* Active Sessions */}
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Monitor className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
            {t('active_sessions')}
          </h2>

          {sessionsLoading ? (
            <div className="flex items-center justify-center py-8">
              <Spinner size="lg" />
            </div>
          ) : sessionsError ? (
            <div className="text-center py-6">
              <Monitor className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
              <p className="text-theme-muted">{sessionsError}</p>
            </div>
          ) : sessions.length > 0 ? (
            <div className="space-y-3">
              {sessions.map((session) => (
                <div
                  key={session.id}
                  className="flex items-center justify-between p-3 rounded-lg bg-theme-elevated"
                >
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-theme-hover">
                      <Monitor className="w-4 h-4 text-theme-muted" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="text-sm font-medium text-theme-primary flex items-center gap-2">
                        {session.browser} {t('session_on')} {session.device}
                        {session.is_current && (
                          <Chip size="sm" color="success" variant="flat">{t('session_current')}</Chip>
                        )}
                      </p>
                      <p className="text-xs text-theme-subtle">
                        {session.ip_address} &mdash; {t('session_last_active')} {new Date(session.last_active).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-6">
              <Monitor className="w-10 h-10 text-theme-subtle mx-auto mb-3" aria-hidden="true" />
              <p className="text-theme-muted">{t('sessions_coming_soon')}</p>
            </div>
          )}
        </GlassCard>

        {/* Account Actions */}
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-4">{t('account_actions')}</h2>

          <div className="space-y-3">
            <Button
              variant="flat"
              className="w-full justify-start bg-theme-elevated text-theme-primary"
              startContent={<LogOut className="w-4 h-4" aria-hidden="true" />}
              onPress={logoutModalOnOpen}
            >
              {t('log_out')}
            </Button>

            <Button
              variant="flat"
              className="w-full justify-start bg-red-500/10 text-red-400"
              startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
              onPress={deleteModalOnOpen}
            >
              {t('delete_account')}
            </Button>
          </div>
        </GlassCard>
      </div>

      {/* ─── Modals ─────────────────────────────────────────────────────────── */}

      {/* Change Password Modal */}
      <Modal isOpen={passwordModalOpen} onClose={passwordModalOnClose} classNames={modalClassNames}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">Change Password</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                type={showCurrentPassword ? 'text' : 'password'}
                label="Current Password"
                value={passwordData.current_password}
                onChange={(e) => onPasswordDataChange((prev) => ({ ...prev, current_password: e.target.value }))}
                endContent={
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="min-w-0 w-auto h-auto p-0"
                    onPress={onShowCurrentPasswordToggle}
                    aria-label={showCurrentPassword ? 'Hide current password' : 'Show current password'}
                  >
                    {showCurrentPassword ? <EyeOff className="w-4 h-4 text-theme-subtle" aria-hidden="true" /> : <Eye className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  </Button>
                }
                classNames={inputClassNames}
              />
              <Input
                type={showNewPassword ? 'text' : 'password'}
                label="New Password"
                value={passwordData.new_password}
                onChange={(e) => onPasswordDataChange((prev) => ({ ...prev, new_password: e.target.value }))}
                endContent={
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="min-w-0 w-auto h-auto p-0"
                    onPress={onShowNewPasswordToggle}
                    aria-label={showNewPassword ? 'Hide new password' : 'Show new password'}
                  >
                    {showNewPassword ? <EyeOff className="w-4 h-4 text-theme-subtle" aria-hidden="true" /> : <Eye className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  </Button>
                }
                classNames={inputClassNames}
              />
              <Input
                type="password"
                label="Confirm New Password"
                value={passwordData.confirm_password}
                onChange={(e) => onPasswordDataChange((prev) => ({ ...prev, confirm_password: e.target.value }))}
                classNames={inputClassNames}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={passwordModalOnClose}>
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={onChangePassword}
              isLoading={isChangingPassword}
            >
              Change Password
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Logout Confirmation Modal */}
      <Modal isOpen={logoutModalOpen} onClose={logoutModalOnClose} classNames={modalClassNames}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">Log Out</ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              Are you sure you want to log out of your account?
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={logoutModalOnClose}>
              Cancel
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={onLogout}
            >
              Log Out
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Account Modal */}
      <Modal isOpen={deleteModalOpen} onClose={deleteModalOnClose} classNames={modalClassNames}>
        <ModalContent>
          <ModalHeader className="text-red-600 dark:text-red-400 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5" aria-hidden="true" />
            Delete Account
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div className="p-4 rounded-lg bg-red-500/10 border border-red-500/20">
                <p className="text-red-600 dark:text-red-400 font-medium">Warning: This action cannot be undone</p>
                <p className="text-theme-muted text-sm mt-1">
                  All your data, including listings, messages, and transaction history will be permanently deleted.
                </p>
              </div>
              <div>
                <p className="text-theme-muted mb-2">
                  Type <span className="font-mono text-red-600 dark:text-red-400">DELETE</span> to confirm:
                </p>
                <Input
                  value={deleteConfirmation}
                  onChange={(e) => onDeleteConfirmationChange(e.target.value)}
                  placeholder="DELETE"
                  aria-label="Type DELETE to confirm account deletion"
                  classNames={{
                    input: 'bg-transparent text-theme-primary font-mono',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                  }}
                />
              </div>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={deleteModalOnClose}>
              Cancel
            </Button>
            <Button
              className="bg-red-500 text-white"
              onPress={onDeleteAccount}
              isLoading={isDeleting}
              isDisabled={deleteConfirmation !== 'DELETE'}
            >
              Delete My Account
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* 2FA Setup Modal */}
      <Modal
        isOpen={twoFactorSetupModalOpen}
        onClose={twoFactorSetupModalOnClose}
        size="lg"
        classNames={modalClassNames}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary flex items-center gap-2">
            <QrCode className="w-5 h-5 text-emerald-500" aria-hidden="true" />
            {t('twofa_setup_title')}
          </ModalHeader>
          <ModalBody>
            {!twoFactorSetupData ? (
              <div className="flex items-center justify-center py-12">
                <Spinner size="lg" />
              </div>
            ) : (
              <div className="space-y-6">
                {/* What is an authenticator app? */}
                <div className="p-4 rounded-lg bg-indigo-500/10 border border-indigo-500/20">
                  <p className="text-sm font-medium text-theme-primary mb-2">{t('twofa_what_is')}</p>
                  <p className="text-xs text-theme-muted mb-3">
                    {t('twofa_what_is_desc')}
                  </p>
                  <p className="text-xs text-theme-muted mb-2">
                    {t('twofa_download_prompt')}
                  </p>
                  <div className="flex flex-wrap gap-2">
                    <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" rel="noopener noreferrer" className="text-xs px-2.5 py-1 rounded-full bg-theme-elevated text-indigo-500 hover:bg-theme-hover transition-colors">
                      Google Authenticator
                    </a>
                    <a href="https://www.microsoft.com/en-us/security/mobile-authenticator-app" target="_blank" rel="noopener noreferrer" className="text-xs px-2.5 py-1 rounded-full bg-theme-elevated text-indigo-500 hover:bg-theme-hover transition-colors">
                      Microsoft Authenticator
                    </a>
                    <a href="https://authy.com/download/" target="_blank" rel="noopener noreferrer" className="text-xs px-2.5 py-1 rounded-full bg-theme-elevated text-indigo-500 hover:bg-theme-hover transition-colors">
                      Authy
                    </a>
                  </div>
                </div>

                <div className="text-center">
                  <p className="text-theme-muted mb-4">
                    {t('twofa_scan_qr')}
                  </p>
                  <div className="inline-block p-4 bg-white rounded-xl">
                    <img
                      src={twoFactorSetupData.qr_code_url}
                      alt="2FA QR Code"
                      className="w-48 h-48"
                      loading="lazy"
                    />
                  </div>
                </div>

                <div className="p-3 rounded-lg bg-theme-elevated">
                  <p className="text-xs text-theme-subtle mb-1">{t('twofa_manual_key')}</p>
                  <p className="font-mono text-sm text-theme-primary break-all select-all">
                    {twoFactorSetupData.secret}
                  </p>
                </div>

                <Input
                  label={t('twofa_verification_code')}
                  placeholder={t('twofa_enter_code')}
                  value={twoFactorVerifyCode}
                  onChange={(e) => onTwoFactorVerifyCodeChange(e.target.value.replace(/\D/g, '').slice(0, 6))}
                  maxLength={6}
                  classNames={inputClassNames}
                  description={t('twofa_code_description')}
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={twoFactorSetupModalOnClose}>
              {t('twofa_cancel')}
            </Button>
            <Button
              className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
              onPress={onVerify2FA}
              isLoading={isVerifying2FA}
              isDisabled={!twoFactorSetupData || twoFactorVerifyCode.length < 6}
            >
              {t('twofa_verify_enable')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* 2FA Disable Modal */}
      <Modal isOpen={twoFactorDisableModalOpen} onClose={twoFactorDisableModalOnClose} classNames={modalClassNames}>
        <ModalContent>
          <ModalHeader className="text-red-600 dark:text-red-400 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5" aria-hidden="true" />
            {t('twofa_disable_title')}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div className="p-4 rounded-lg bg-amber-500/10 border border-amber-500/20">
                <p className="text-amber-600 dark:text-amber-400 font-medium">{t('twofa_disable_warning')}</p>
                <p className="text-theme-muted text-sm mt-1">
                  {t('twofa_disable_desc')}
                </p>
              </div>
              <Input
                type="password"
                label={t('twofa_confirm_password')}
                value={twoFactorDisablePassword}
                onChange={(e) => onTwoFactorDisablePasswordChange(e.target.value)}
                classNames={inputClassNames}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={twoFactorDisableModalOnClose}>
              {t('twofa_cancel')}
            </Button>
            <Button
              className="bg-red-500 text-white"
              onPress={onDisable2FA}
              isLoading={isDisabling2FA}
              isDisabled={!twoFactorDisablePassword}
            >
              {t('twofa_disable_confirm')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Backup Codes Modal */}
      <Modal isOpen={backupCodesModalOpen} onClose={backupCodesModalOnClose} size="lg" classNames={modalClassNames}>
        <ModalContent>
          <ModalHeader className="text-theme-primary flex items-center gap-2">
            <CheckCircle className="w-5 h-5 text-emerald-500" aria-hidden="true" />
            {t('backup_codes_title')}
          </ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <div className="p-4 rounded-lg bg-amber-500/10 border border-amber-500/20">
                <p className="text-amber-600 dark:text-amber-400 font-medium">{t('backup_codes_warning')}</p>
                <p className="text-theme-muted text-sm mt-1">
                  {t('backup_codes_desc')}
                </p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 p-4 rounded-lg bg-theme-elevated">
                {backupCodes.map((code, index) => (
                  <p key={index} className="font-mono text-sm text-theme-primary text-center py-1">
                    {code}
                  </p>
                ))}
              </div>

              <Button
                variant="flat"
                className="w-full bg-theme-elevated text-theme-primary"
                startContent={<Copy className="w-4 h-4" aria-hidden="true" />}
                onPress={onCopyBackupCodes}
              >
                {t('backup_codes_copy')}
              </Button>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={backupCodesModalOnClose}
            >
              {t('backup_codes_saved')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* GDPR Request Modal is kept in SettingsPage (triggered from PrivacyTab) */}
    </>
  );
}
