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
  if (cred.authenticator_type === 'platform') return t('biometric.label_builtin');
  if (cred.authenticator_type === 'cross-platform') return t('biometric.label_external');
  return t('biometric.label_passkey');
}

function getPlatformInstructions(platform: DevicePlatform, t: (key: string, options?: Record<string, unknown>) => string): { title: string; steps: string[] } {
  const instructions: Record<DevicePlatform, { title: string; steps: string[] }> = {
    windows: {
      title: t('biometric.platform_windows_title'),
      steps: [
        t('biometric.platform_windows_step1'),
        t('biometric.platform_windows_step2'),
        t('biometric.platform_windows_step3'),
        t('biometric.platform_windows_step4'),
      ],
    },
    mac: {
      title: t('biometric.platform_mac_title'),
      steps: [
        t('biometric.platform_mac_step1'),
        t('biometric.platform_mac_step2'),
        t('biometric.platform_mac_step3'),
      ],
    },
    iphone: {
      title: t('biometric.platform_iphone_title'),
      steps: [
        t('biometric.platform_iphone_step1'),
        t('biometric.platform_iphone_step2'),
        t('biometric.platform_iphone_step3'),
      ],
    },
    ipad: {
      title: t('biometric.platform_ipad_title'),
      steps: [
        t('biometric.platform_ipad_step1'),
        t('biometric.platform_ipad_step2'),
        t('biometric.platform_ipad_step3'),
      ],
    },
    android: {
      title: t('biometric.platform_android_title'),
      steps: [
        t('biometric.platform_android_step1'),
        t('biometric.platform_android_step2'),
        t('biometric.platform_android_step3'),
      ],
    },
    linux: {
      title: t('biometric.platform_linux_title'),
      steps: [
        t('biometric.platform_linux_step1'),
        t('biometric.platform_linux_step2'),
      ],
    },
    unknown: {
      title: t('biometric.platform_unknown_title'),
      steps: [
        t('biometric.platform_unknown_step1'),
        t('biometric.platform_unknown_step2'),
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
      toast.success(t('biometric_registered'));
      loadCredentials();
    } else {
      toast.error(result.error || t('passkey_registration_failed'));
    }
  };

  const handleRemove = async (credentialId: string) => {
    setRemovingId(credentialId);
    const success = await removeWebAuthnCredential(credentialId);
    setRemovingId(null);

    if (success) {
      toast.success(t('biometric_removed'));
      setCredentials(prev => prev.filter(c => c.credential_id !== credentialId));
    } else {
      toast.error(t('passkey_remove_failed'));
    }
  };

  const handleRemoveAll = async () => {
    setRemovingAll(true);
    const result = await removeAllWebAuthnCredentials();
    setRemovingAll(false);

    if (result.success) {
      toast.success(
        t('biometric_all_removed', {
          count: result.removedCount,
        }),
      );
      setCredentials([]);
    } else {
      toast.error(t('passkey_remove_all_failed'));
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
      toast.success(t('passkey_renamed'));
    } else {
      toast.error(t('passkey_rename_failed'));
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
              {t('biometric_title')}
            </p>
            <p className="text-sm text-theme-subtle">
              {t('biometric_not_supported')}
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
              {t('biometric_checking')}
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
              {t('biometric_title')}
            </p>
            <p className="text-sm text-theme-subtle">
              {hasCredentials ? (
                <span className="flex items-center gap-1">
                  <CheckCircle className="w-3 h-3 text-emerald-500" aria-hidden="true" />
                  {t('biometric_enabled', {
                    count: credentials.length,
                  })}
                </span>
              ) : (
                t('biometric_not_enabled')
              )}
            </p>
          </div>
        </div>

        <Tooltip content={t('passkey_setup_tooltip')}>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            className="text-theme-subtle"
            onPress={() => setShowInstructions(!showInstructions)}
            aria-label={t('passkey_show_instructions')}
          >
            <Info className="w-4 h-4" />
          </Button>
        </Tooltip>
      </div>

      {/* Platform-specific instructions */}
      {showInstructions && (
        <div className="p-3 rounded-lg bg-indigo-500/5 border border-indigo-500/20 space-y-2">
          <p className="text-sm font-medium text-indigo-700 dark:text-indigo-300">
            {instructions.title} - {t('passkey_setup_subtitle')}
          </p>
          <ol className="text-sm text-theme-subtle space-y-1 list-decimal list-inside">
            {instructions.steps.map((step, i) => (
              <li key={i}>{step}</li>
            ))}
          </ol>
          <div className="pt-2 border-t border-indigo-500/10">
            <p className="text-xs text-theme-muted">
              {t('passkey_multi_device_note')}
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
        {hasCredentials ? t('passkey_add_another') : t('passkey_create')}
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
                        aria-label={t('passkey_rename_input')}
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
                        {t('biometric_registered_on')}{' '}
                        {new Date(cred.created_at).toLocaleDateString()}
                      </span>
                      {cred.last_used_at && (
                        <>
                          <span>&middot;</span>
                          <span>
                            {t('biometric_last_used')}{' '}
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
                    aria-label={t('passkey_rename')}
                  >
                    <Pencil className="w-3.5 h-3.5" />
                  </Button>
                  <Button
                    isIconOnly
                    size="sm"
                    variant="light"
                    className="text-[var(--color-error)] hover:bg-red-500/10"
                    onPress={() => handleRemove(cred.credential_id)}
                    isLoading={removingId === cred.credential_id}
                    aria-label={t('biometric_remove')}
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
                className="bg-red-500/10 text-[var(--color-error)]"
                onPress={removeAllConfirm.onOpen}
                isLoading={removingAll}
                startContent={!removingAll ? <Trash2 className="w-3 h-3" /> : undefined}
              >
                {t('biometric_remove_all')}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Multi-device tip */}
      {!showInstructions && (
        <p className="text-xs text-theme-muted">
          {t('passkey_device_tip')}{' '}
          <Button
            variant="light"
            size="sm"
            className="text-indigo-500 hover:underline h-auto p-0 min-w-0"
            onPress={() => setShowInstructions(true)}
          >
            {t('passkey_setup_guide')}
          </Button>
        </p>
      )}

      {/* Remove All confirmation modal */}
      <Modal isOpen={removeAllConfirm.isOpen} onOpenChange={removeAllConfirm.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex flex-col gap-1">
                {t('passkey_remove_all_title')}
              </ModalHeader>
              <ModalBody>
                <p className="text-theme-subtle">
                  {t('passkey_remove_all_warning')}
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={onClose}>
                  {t('cancel')}
                </Button>
                <Button
                  color="danger"
                  onPress={() => {
                    handleRemoveAll();
                    onClose();
                  }}
                >
                  {t('passkey_remove_all_confirm')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
