// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Input,
  Spinner,
  Tooltip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import Fingerprint from 'lucide-react/icons/fingerprint';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Monitor from 'lucide-react/icons/monitor';
import Smartphone from 'lucide-react/icons/smartphone';
import Tablet from 'lucide-react/icons/tablet';
import Laptop from 'lucide-react/icons/laptop';
import Info from 'lucide-react/icons/info';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import {
  isBiometricAvailable,
  registerBiometric,
  getWebAuthnCredentials,
  removeWebAuthnCredential,
  renameWebAuthnCredential,
  removeAllWebAuthnCredentials,
  detectPlatform,
  type DevicePlatform,
  type AuthenticatorAttachment,
} from '@/lib/webauthn';

interface Credential {
  credential_id: string;
  device_name: string | null;
  authenticator_type: string | null;
  created_at: string;
  last_used_at: string | null;
}

function getDeviceIcon(cred: Credential) {
  const name = (cred.device_name || '').toLowerCase();
  if (name.includes('iphone') || name.includes('android')) return Smartphone;
  if (name.includes('ipad') || name.includes('tablet')) return Tablet;
  if (name.includes('mac') || name.includes('laptop')) return Laptop;
  return Monitor;
}

function getDeviceLabel(cred: Credential, t: (key: string, options?: Record<string, unknown>) => string): string {
  if (cred.device_name) return cred.device_name;
  // Fallback for credentials registered before device_name was added
  if (cred.authenticator_type === 'platform') return t('biometric.label_builtin', { defaultValue: 'Built-in authenticator' });
  if (cred.authenticator_type === 'cross-platform') return t('biometric.label_external', { defaultValue: 'External authenticator' });
  return t('biometric.label_passkey', { defaultValue: 'Passkey' });
}

function getPlatformInstructions(platform: DevicePlatform, t: (key: string, options?: Record<string, unknown>) => string): { title: string; steps: string[] } {
  const instructions: Record<DevicePlatform, { title: string; steps: string[] }> = {
    windows: {
      title: t('biometric.platform_windows_title', { defaultValue: 'Setting up on Windows' }),
      steps: [
        t('biometric.platform_windows_step1', { defaultValue: 'Click "This PC" to create a passkey stored on this computer. You\'ll confirm with your Windows Hello PIN, fingerprint, or face.' }),
        t('biometric.platform_windows_step2', { defaultValue: 'Requirement: You must have Windows Hello set up first. Go to Windows Settings > Accounts > Sign-in options > PIN to set it up.' }),
        t('biometric.platform_windows_step3', { defaultValue: 'Or click "Phone, tablet, or security key" to scan a QR code with your phone instead.' }),
        t('biometric.platform_windows_step4', { defaultValue: 'To set up passkeys on your phone too, open this page on your phone and tap "This device".' }),
      ],
    },
    mac: {
      title: t('biometric.platform_mac_title', { defaultValue: 'Setting up on Mac' }),
      steps: [
        t('biometric.platform_mac_step1', { defaultValue: 'Click "This Mac" — your browser will prompt Touch ID or your Mac password.' }),
        t('biometric.platform_mac_step2', { defaultValue: 'The passkey syncs via iCloud Keychain to your iPhone, iPad, and other Macs automatically.' }),
        t('biometric.platform_mac_step3', { defaultValue: 'Or click "Phone, tablet, or security key" to register a different device.' }),
      ],
    },
    iphone: {
      title: t('biometric.platform_iphone_title', { defaultValue: 'Setting up on iPhone' }),
      steps: [
        t('biometric.platform_iphone_step1', { defaultValue: 'Tap "This device" to create a passkey using Face ID or Touch ID.' }),
        t('biometric.platform_iphone_step2', { defaultValue: 'The passkey is saved to iCloud Keychain and works on all your Apple devices.' }),
        t('biometric.platform_iphone_step3', { defaultValue: 'You can also tap "Phone, tablet, or security key" to register a security key.' }),
      ],
    },
    ipad: {
      title: t('biometric.platform_ipad_title', { defaultValue: 'Setting up on iPad' }),
      steps: [
        t('biometric.platform_ipad_step1', { defaultValue: 'Tap "This device" to create a passkey using Face ID or Touch ID.' }),
        t('biometric.platform_ipad_step2', { defaultValue: 'The passkey is saved to iCloud Keychain and works on all your Apple devices.' }),
        t('biometric.platform_ipad_step3', { defaultValue: 'You can also tap "Phone, tablet, or security key" to register a security key.' }),
      ],
    },
    android: {
      title: t('biometric.platform_android_title', { defaultValue: 'Setting up on Android' }),
      steps: [
        t('biometric.platform_android_step1', { defaultValue: 'Tap "This device" to create a passkey using your fingerprint, face, or screen lock.' }),
        t('biometric.platform_android_step2', { defaultValue: 'The passkey is saved to Google Password Manager and works on all your Android devices and Chrome browsers.' }),
        t('biometric.platform_android_step3', { defaultValue: 'You can also tap "Phone, tablet, or security key" to register a security key.' }),
      ],
    },
    linux: {
      title: t('biometric.platform_linux_title', { defaultValue: 'Setting up on Linux' }),
      steps: [
        t('biometric.platform_linux_step1', { defaultValue: 'Click "This device" — your browser will use its built-in passkey manager.' }),
        t('biometric.platform_linux_step2', { defaultValue: 'Or click "Phone, tablet, or security key" to use a USB security key or scan a QR code with your phone.' }),
      ],
    },
    unknown: {
      title: t('biometric.platform_unknown_title', { defaultValue: 'Setting up a passkey' }),
      steps: [
        t('biometric.platform_unknown_step1', { defaultValue: 'Click "This device" to create a passkey on the device you\'re using now.' }),
        t('biometric.platform_unknown_step2', { defaultValue: 'Or click "Phone, tablet, or security key" to register a different device.' }),
      ],
    },
  };
  return instructions[platform];
}

export function BiometricSettings() {
  const { t } = useTranslation('settings');
  const toast = useToast();

  const [supported, setSupported] = useState<boolean | null>(null);
  const [credentials, setCredentials] = useState<Credential[]>([]);
  const [loading, setLoading] = useState(true);
  const [registering, setRegistering] = useState(false);
  const [removingId, setRemovingId] = useState<string | null>(null);
  const [removingAll, setRemovingAll] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editName, setEditName] = useState('');
  const [showInstructions, setShowInstructions] = useState(true); // Auto-show on first visit

  const platform = detectPlatform();
  const instructions = getPlatformInstructions(platform, (key, options) => t(key, options));
  const removeAllConfirm = useDisclosure();

  const loadCredentials = useCallback(async () => {
    try {
      const creds = await getWebAuthnCredentials();
      setCredentials(creds);
    } catch {
      // silently fail — user might not have any credentials
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    isBiometricAvailable().then(setSupported).catch(() => setSupported(false));
    loadCredentials();
  }, [loadCredentials]);

  const handleRegister = async (attachment?: AuthenticatorAttachment) => {
    setRegistering(true);
    const result = await registerBiometric(undefined, attachment);
    setRegistering(false);

    if (result.success) {
      toast.success(t('biometric_registered', { defaultValue: 'Passkey registered successfully!' }));
      loadCredentials();
    } else {
      toast.error(result.error || t('passkey_registration_failed', { defaultValue: 'Registration failed' }));
    }
  };

  const handleRemove = async (credentialId: string) => {
    setRemovingId(credentialId);
    const success = await removeWebAuthnCredential(credentialId);
    setRemovingId(null);

    if (success) {
      toast.success(t('biometric_removed', { defaultValue: 'Passkey removed.' }));
      setCredentials(prev => prev.filter(c => c.credential_id !== credentialId));
    } else {
      toast.error(t('passkey_remove_failed', { defaultValue: 'Failed to remove credential' }));
    }
  };

  const handleRemoveAll = async () => {
    setRemovingAll(true);
    const result = await removeAllWebAuthnCredentials();
    setRemovingAll(false);

    if (result.success) {
      toast.success(
        t('biometric_all_removed', {
          defaultValue: `Removed ${result.removedCount} passkey(s).`,
          count: result.removedCount,
        }),
      );
      setCredentials([]);
    } else {
      toast.error(t('passkey_remove_all_failed', { defaultValue: 'Failed to remove credentials' }));
    }
  };

  const handleRename = async (credentialId: string) => {
    const trimmed = editName.trim();
    if (!trimmed) {
      setEditingId(null);
      return;
    }
    const success = await renameWebAuthnCredential(credentialId, trimmed);
    if (success) {
      setCredentials(prev =>
        prev.map(c => c.credential_id === credentialId ? { ...c, device_name: trimmed } : c),
      );
      toast.success(t('passkey_renamed', { defaultValue: 'Passkey renamed.' }));
    } else {
      toast.error(t('passkey_rename_failed', { defaultValue: 'Failed to rename passkey' }));
    }
    setEditingId(null);
  };

  // Not supported — show info message
  if (supported === false) {
    return (
      <div className="w-full p-4 rounded-lg bg-theme-elevated text-left">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-amber-500/20">
            <AlertTriangle className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
          </div>
          <div>
            <p className="font-medium text-theme-primary">
              {t('biometric_title', { defaultValue: 'Passkey Login' })}
            </p>
            <p className="text-sm text-theme-subtle">
              {t('biometric_not_supported', {
                defaultValue: 'Your device or browser does not support passkeys. Try using a modern browser like Chrome, Edge, Safari, or Firefox.',
              })}
            </p>
          </div>
        </div>
      </div>
    );
  }

  // Still checking
  if (supported === null || loading) {
    return (
      <div className="w-full p-4 rounded-lg bg-theme-elevated text-left">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-indigo-500/20">
            <Fingerprint className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          </div>
          <div className="flex items-center gap-2">
            <Spinner size="sm" />
            <span className="text-sm text-theme-muted">
              {t('biometric_checking', { defaultValue: 'Checking passkey support...' })}
            </span>
          </div>
        </div>
      </div>
    );
  }

  const hasCredentials = credentials.length > 0;

  return (
    <div className="w-full p-4 rounded-lg bg-theme-elevated text-left space-y-4">
      {/* Header row */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-indigo-500/20">
            <Fingerprint className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          </div>
          <div>
            <p className="font-medium text-theme-primary">
              {t('biometric_title', { defaultValue: 'Passkey Login' })}
            </p>
            <p className="text-sm text-theme-subtle">
              {hasCredentials ? (
                <span className="flex items-center gap-1">
                  <CheckCircle className="w-3 h-3 text-emerald-500" aria-hidden="true" />
                  {t('biometric_enabled', {
                    defaultValue: `${credentials.length} passkey(s) registered`,
                    count: credentials.length,
                  })}
                </span>
              ) : (
                t('biometric_not_enabled', {
                  defaultValue: 'Sign in faster with fingerprint, face, or PIN',
                })
              )}
            </p>
          </div>
        </div>

        <Tooltip content={t('passkey_setup_tooltip', { defaultValue: 'How to set up passkeys' })}>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            className="text-theme-subtle"
            onPress={() => setShowInstructions(!showInstructions)}
            aria-label={t('passkey_show_instructions', { defaultValue: 'Show setup instructions' })}
          >
            <Info className="w-4 h-4" />
          </Button>
        </Tooltip>
      </div>

      {/* Platform-specific instructions */}
      {showInstructions && (
        <div className="p-3 rounded-lg bg-indigo-500/5 border border-indigo-500/20 space-y-2">
          <p className="text-sm font-medium text-indigo-700 dark:text-indigo-300">
            {t('passkey_setup_title', { defaultValue: instructions.title })} — {t('passkey_setup_subtitle', { defaultValue: 'Setup for this device' })}
          </p>
          <ol className="text-sm text-theme-subtle space-y-1 list-decimal list-inside">
            {instructions.steps.map((step, i) => (
              <li key={i}>{step}</li>
            ))}
          </ol>
          <div className="pt-2 border-t border-indigo-500/10">
            <p className="text-xs text-theme-muted">
              {t('passkey_multi_device_note', { defaultValue: 'You can register passkeys on multiple devices. Each device needs its own passkey unless your passkey provider syncs them (e.g., iCloud Keychain syncs across Apple devices, Google Password Manager syncs across Android and Chrome).' })}
            </p>
          </div>
        </div>
      )}

      {/* Registration button — no attachment restriction, let browser show all options */}
      <Button
        size="md"
        className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
        onPress={() => handleRegister(undefined)}
        isLoading={registering}
        startContent={!registering ? <Fingerprint className="w-4 h-4" /> : undefined}
      >
        {hasCredentials ? t('passkey_add_another', { defaultValue: 'Add another passkey' }) : t('passkey_create', { defaultValue: 'Create a passkey' })}
      </Button>

      {/* Registered credentials list */}
      {hasCredentials && (
        <div className="space-y-2 pt-2 border-t border-theme-default">
          {credentials.map((cred) => {
            const DeviceIcon = getDeviceIcon(cred);
            return (
              <div
                key={cred.credential_id}
                className="flex items-center justify-between p-2.5 rounded-lg bg-theme-hover/50"
              >
                <div className="flex items-center gap-3 min-w-0">
                  <DeviceIcon className="w-4 h-4 text-theme-muted flex-shrink-0" aria-hidden="true" />
                  <div className="min-w-0">
                    {editingId === cred.credential_id ? (
                      <Input
                        size="sm"
                        variant="underlined"
                        value={editName}
                        onValueChange={setEditName}
                        autoFocus
                        className="max-w-[200px]"
                        aria-label={t('passkey_rename_input', { defaultValue: 'New passkey name' })}
                        onBlur={() => handleRename(cred.credential_id)}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') handleRename(cred.credential_id);
                          if (e.key === 'Escape') setEditingId(null);
                        }}
                      />
                    ) : (
                      <p className="text-sm font-medium text-theme-primary truncate">
                        {getDeviceLabel(cred, (key, options) => t(key, options))}{' '}
                        <span className="text-theme-subtle font-mono text-xs">
                          ...{cred.credential_id.slice(-8)}
                        </span>
                      </p>
                    )}
                    <div className="flex items-center gap-2 text-xs text-theme-subtle">
                      <span>
                        {t('biometric_registered_on', { defaultValue: 'Registered' })}{' '}
                        {new Date(cred.created_at).toLocaleDateString()}
                      </span>
                      {cred.last_used_at && (
                        <>
                          <span>&middot;</span>
                          <span>
                            {t('biometric_last_used', { defaultValue: 'Last used' })}{' '}
                            {new Date(cred.last_used_at).toLocaleDateString()}
                          </span>
                        </>
                      )}
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-1">
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="text-theme-subtle hover:bg-theme-hover"
                    onPress={() => {
                      setEditingId(cred.credential_id);
                      setEditName(getDeviceLabel(cred, (key, options) => t(key, options)));
                    }}
                    aria-label={t('passkey_rename', { defaultValue: 'Rename passkey' })}
                  >
                    <Pencil className="w-3.5 h-3.5" />
                  </Button>
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="text-red-500 hover:bg-red-500/10"
                    onPress={() => handleRemove(cred.credential_id)}
                    isLoading={removingId === cred.credential_id}
                    aria-label={t('biometric_remove', { defaultValue: 'Remove passkey' })}
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </Button>
                </div>
              </div>
            );
          })}

          {/* Remove all button */}
          {credentials.length > 1 && (
            <div className="pt-1">
              <Button
                size="sm"
                variant="flat"
                className="bg-red-500/10 text-red-500"
                onPress={removeAllConfirm.onOpen}
                isLoading={removingAll}
                startContent={!removingAll ? <Trash2 className="w-3 h-3" /> : undefined}
              >
                {t('biometric_remove_all', { defaultValue: 'Remove All Passkeys' })}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Multi-device tip */}
      {!showInstructions && (
        <p className="text-xs text-theme-muted">
          {t('passkey_device_tip', { defaultValue: 'Register a passkey on each device you use. To add your phone, open this page on your phone.' })}{' '}
          <Button
            variant="light"
            size="sm"
            className="text-indigo-500 hover:underline h-auto p-0 min-w-0"
            onPress={() => setShowInstructions(true)}
          >
            {t('passkey_setup_guide', { defaultValue: 'Setup guide' })}
          </Button>
        </p>
      )}

      {/* Remove All confirmation modal */}
      <Modal isOpen={removeAllConfirm.isOpen} onOpenChange={removeAllConfirm.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex flex-col gap-1">
                {t('passkey_remove_all_title', { defaultValue: 'Remove All Passkeys' })}
              </ModalHeader>
              <ModalBody>
                <p className="text-theme-subtle">
                  {t('passkey_remove_all_warning', {
                    defaultValue:
                      "Are you sure you want to remove all passkeys? You'll need to set them up again on each device.",
                  })}
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={onClose}>
                  {t('cancel', { defaultValue: 'Cancel' })}
                </Button>
                <Button
                  color="danger"
                  onPress={() => {
                    handleRemoveAll();
                    onClose();
                  }}
                >
                  {t('passkey_remove_all_confirm', { defaultValue: 'Remove All' })}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
