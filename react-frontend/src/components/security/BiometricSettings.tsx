// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { Button, Spinner } from '@heroui/react';
import { Fingerprint, Trash2, Plus, AlertTriangle, CheckCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import {
  isBiometricAvailable,
  registerBiometric,
  getWebAuthnCredentials,
  removeWebAuthnCredential,
  removeAllWebAuthnCredentials,
} from '@/lib/webauthn';

interface Credential {
  credential_id: string;
  created_at: string;
  last_used_at: string | null;
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
      toast.success(t('biometric_registered', { defaultValue: 'Biometric login registered successfully!' }));
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
      toast.success(t('biometric_removed', { defaultValue: 'Biometric credential removed.' }));
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
          defaultValue: `Removed ${result.removedCount} credential(s).`,
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
              {t('biometric_title', { defaultValue: 'Biometric Login' })}
            </p>
            <p className="text-sm text-theme-subtle">
              {t('biometric_not_supported', {
                defaultValue: 'Your device or browser does not support biometric authentication.',
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
              {t('biometric_checking', { defaultValue: 'Checking biometric support...' })}
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
              {t('biometric_title', { defaultValue: 'Biometric Login' })}
            </p>
            <p className="text-sm text-theme-subtle">
              {hasCredentials ? (
                <span className="flex items-center gap-1">
                  <CheckCircle className="w-3 h-3 text-emerald-500" aria-hidden="true" />
                  {t('biometric_enabled', {
                    defaultValue: `${credentials.length} device(s) registered`,
                    count: credentials.length,
                  })}
                </span>
              ) : (
                t('biometric_not_enabled', {
                  defaultValue: 'Use fingerprint or face recognition to sign in',
                })
              )}
            </p>
          </div>
        </div>

        <Button
          size="sm"
          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
          onPress={handleRegister}
          isLoading={registering}
          startContent={!registering ? <Plus className="w-3.5 h-3.5" /> : undefined}
        >
          {hasCredentials
            ? t('biometric_add_device', { defaultValue: 'Add Device' })
            : t('biometric_enable', { defaultValue: 'Set Up' })}
        </Button>
      </div>

      {/* Registered credentials list */}
      {hasCredentials && (
        <div className="space-y-2 pt-2 border-t border-theme-default">
          {credentials.map((cred) => (
            <div
              key={cred.credential_id}
              className="flex items-center justify-between p-2.5 rounded-lg bg-theme-hover/50"
            >
              <div className="flex items-center gap-3 min-w-0">
                <Fingerprint className="w-4 h-4 text-theme-muted flex-shrink-0" aria-hidden="true" />
                <div className="min-w-0">
                  <p className="text-sm font-medium text-theme-primary truncate">
                    {t('biometric_credential', { defaultValue: 'Passkey' })}{' '}
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
                aria-label={t('biometric_remove', { defaultValue: 'Remove credential' })}
              >
                <Trash2 className="w-3.5 h-3.5" />
              </Button>
            </div>
          ))}

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
                {t('biometric_remove_all', { defaultValue: 'Remove All Devices' })}
              </Button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
