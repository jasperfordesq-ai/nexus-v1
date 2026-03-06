// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { Button, Spinner, Tooltip } from '@heroui/react';
import {
  Fingerprint,
  Trash2,
  Plus,
  AlertTriangle,
  CheckCircle,
  Monitor,
  Smartphone,
  Tablet,
  Laptop,
  Info,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import {
  isBiometricAvailable,
  registerBiometric,
  getWebAuthnCredentials,
  removeWebAuthnCredential,
  removeAllWebAuthnCredentials,
  detectPlatform,
  type DevicePlatform,
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

function getDeviceLabel(cred: Credential): string {
  if (cred.device_name) return cred.device_name;
  // Fallback for credentials registered before device_name was added
  if (cred.authenticator_type === 'platform') return 'Built-in authenticator';
  if (cred.authenticator_type === 'cross-platform') return 'External authenticator';
  return 'Passkey';
}

const PLATFORM_INSTRUCTIONS: Record<DevicePlatform, { title: string; steps: string[] }> = {
  windows: {
    title: 'Windows Hello',
    steps: [
      'Click "Add Passkey" below — your browser will show the Windows Hello prompt.',
      'Choose PIN, fingerprint, or face recognition to create your passkey.',
      'If prompted to choose between "This device" and "A phone or tablet", pick "This device" for Windows Hello.',
    ],
  },
  mac: {
    title: 'Touch ID / iCloud Keychain',
    steps: [
      'Click "Add Passkey" — your browser will prompt Touch ID.',
      'Use your fingerprint or enter your Mac password to confirm.',
      'The passkey syncs via iCloud Keychain to your other Apple devices.',
    ],
  },
  iphone: {
    title: 'Face ID / Touch ID',
    steps: [
      'Tap "Add Passkey" — your iPhone will prompt Face ID or Touch ID.',
      'Confirm with your biometric to create the passkey.',
      'The passkey syncs via iCloud Keychain to your other Apple devices.',
    ],
  },
  ipad: {
    title: 'Face ID / Touch ID',
    steps: [
      'Tap "Add Passkey" — your iPad will prompt Face ID or Touch ID.',
      'Confirm with your biometric to create the passkey.',
      'The passkey syncs via iCloud Keychain to your other Apple devices.',
    ],
  },
  android: {
    title: 'Google Password Manager',
    steps: [
      'Tap "Add Passkey" — Android will show the passkey creation dialog.',
      'Confirm with fingerprint, face, or screen lock.',
      'The passkey is saved to your Google account and available on all your Android devices and Chrome browsers.',
    ],
  },
  linux: {
    title: 'Security Key / Browser Passkey',
    steps: [
      'Click "Add Passkey" — your browser will prompt you.',
      'You can use a USB security key or your browser\'s built-in passkey manager.',
    ],
  },
  unknown: {
    title: 'Passkey',
    steps: [
      'Click "Add Passkey" — your browser will guide you through the setup.',
      'You may use a built-in authenticator (fingerprint, face, PIN) or a phone/security key.',
    ],
  },
};

export function BiometricSettings() {
  const { t } = useTranslation('settings');
  const toast = useToast();

  const [supported, setSupported] = useState<boolean | null>(null);
  const [credentials, setCredentials] = useState<Credential[]>([]);
  const [loading, setLoading] = useState(true);
  const [registering, setRegistering] = useState(false);
  const [removingId, setRemovingId] = useState<string | null>(null);
  const [removingAll, setRemovingAll] = useState(false);
  const [showInstructions, setShowInstructions] = useState(false);

  const platform = detectPlatform();
  const instructions = PLATFORM_INSTRUCTIONS[platform];

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
    isBiometricAvailable().then(setSupported);
    loadCredentials();
  }, [loadCredentials]);

  const handleRegister = async () => {
    setRegistering(true);
    const result = await registerBiometric();
    setRegistering(false);

    if (result.success) {
      toast.success(t('biometric_registered', { defaultValue: 'Passkey registered successfully!' }));
      loadCredentials();
    } else {
      toast.error(result.error || 'Registration failed');
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
      toast.error('Failed to remove credential');
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
      toast.error('Failed to remove credentials');
    }
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

        <div className="flex items-center gap-2">
          <Tooltip content="How to set up passkeys">
            <Button
              isIconOnly
              size="sm"
              variant="light"
              className="text-theme-subtle"
              onPress={() => setShowInstructions(!showInstructions)}
              aria-label="Show setup instructions"
            >
              <Info className="w-4 h-4" />
            </Button>
          </Tooltip>
          <Button
            size="sm"
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            onPress={handleRegister}
            isLoading={registering}
            startContent={!registering ? <Plus className="w-3.5 h-3.5" /> : undefined}
          >
            {hasCredentials
              ? t('biometric_add_device', { defaultValue: 'Add Passkey' })
              : t('biometric_enable', { defaultValue: 'Set Up' })}
          </Button>
        </div>
      </div>

      {/* Platform-specific instructions */}
      {showInstructions && (
        <div className="p-3 rounded-lg bg-indigo-500/5 border border-indigo-500/20 space-y-2">
          <p className="text-sm font-medium text-indigo-700 dark:text-indigo-300">
            {instructions.title} — Setup for this device
          </p>
          <ol className="text-sm text-theme-subtle space-y-1 list-decimal list-inside">
            {instructions.steps.map((step, i) => (
              <li key={i}>{step}</li>
            ))}
          </ol>
          <div className="pt-2 border-t border-indigo-500/10">
            <p className="text-xs text-theme-muted">
              You can register passkeys on multiple devices. Each device needs its own passkey unless your passkey provider syncs them (e.g., iCloud Keychain syncs across Apple devices, Google Password Manager syncs across Android and Chrome).
            </p>
          </div>
        </div>
      )}

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
                    <p className="text-sm font-medium text-theme-primary truncate">
                      {getDeviceLabel(cred)}{' '}
                      <span className="text-theme-subtle font-mono text-xs">
                        ...{cred.credential_id.slice(-8)}
                      </span>
                    </p>
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
            );
          })}

          {/* Remove all button */}
          {credentials.length > 1 && (
            <div className="pt-1">
              <Button
                size="sm"
                variant="flat"
                className="bg-red-500/10 text-red-500"
                onPress={handleRemoveAll}
                isLoading={removingAll}
                startContent={!removingAll ? <Trash2 className="w-3 h-3" /> : undefined}
              >
                {t('biometric_remove_all', { defaultValue: 'Remove All Passkeys' })}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Cross-device hint when no credentials yet */}
      {!hasCredentials && !showInstructions && (
        <p className="text-xs text-theme-muted">
          Works with Windows Hello, Touch ID, Face ID, Android biometrics, and security keys.{' '}
          <button
            type="button"
            className="text-indigo-500 hover:underline"
            onClick={() => setShowInstructions(true)}
          >
            Learn how
          </button>
        </p>
      )}
    </div>
  );
}
