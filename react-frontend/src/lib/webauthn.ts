// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * WebAuthn/Biometric Authentication helpers
 *
 * Wraps @simplewebauthn/browser to work with our PHP backend.
 * The PHP API returns its own challenge format, so we map it to
 * SimpleWebAuthn's expected JSON types.
 */

import {
  startRegistration,
  startAuthentication,
  browserSupportsWebAuthn,
  platformAuthenticatorIsAvailable,
} from '@simplewebauthn/browser';
import type {
  PublicKeyCredentialCreationOptionsJSON,
  PublicKeyCredentialRequestOptionsJSON,
  RegistrationResponseJSON,
  AuthenticationResponseJSON,
} from '@simplewebauthn/browser';
import { api } from '@/lib/api';

// ─────────────────────────────────────────────────────────────────────────────
// Feature Detection
// ─────────────────────────────────────────────────────────────────────────────

/** Check if WebAuthn is supported at all (for login page passkey button) */
export function isWebAuthnSupported(): boolean {
  return browserSupportsWebAuthn();
}

/** Check if a platform authenticator is available (Windows Hello, Touch ID, etc.) */
export async function isPlatformAuthenticatorAvailable(): Promise<boolean> {
  if (!browserSupportsWebAuthn()) return false;
  return platformAuthenticatorIsAvailable();
}

/** Check if passkey features should be shown (any WebAuthn support) */
export async function isBiometricAvailable(): Promise<boolean> {
  // Return true if browser supports WebAuthn at all — cross-device passkeys
  // work even without a platform authenticator
  return browserSupportsWebAuthn();
}

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface WebAuthnCredential {
  credential_id: string;
  device_name: string | null;
  authenticator_type: string | null;
  created_at: string;
  last_used_at: string | null;
}

interface WebAuthnStatus {
  registered: boolean;
  count: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Device / Platform Detection
// ─────────────────────────────────────────────────────────────────────────────

export type DevicePlatform = 'windows' | 'mac' | 'iphone' | 'ipad' | 'android' | 'linux' | 'unknown';

export function detectPlatform(): DevicePlatform {
  const ua = navigator.userAgent.toLowerCase();
  if (/iphone/.test(ua)) return 'iphone';
  if (/ipad/.test(ua) || (/macintosh/.test(ua) && navigator.maxTouchPoints > 1)) return 'ipad';
  if (/android/.test(ua)) return 'android';
  if (/windows/.test(ua)) return 'windows';
  if (/macintosh|mac os/.test(ua)) return 'mac';
  if (/linux/.test(ua)) return 'linux';
  return 'unknown';
}

export function getDefaultDeviceName(): string {
  const platform = detectPlatform();
  const names: Record<DevicePlatform, string> = {
    windows: 'Windows PC',
    mac: 'Mac',
    iphone: 'iPhone',
    ipad: 'iPad',
    android: 'Android device',
    linux: 'Linux PC',
    unknown: 'Device',
  };
  return names[platform];
}

// ─────────────────────────────────────────────────────────────────────────────
// Registration (Enroll a new biometric credential)
// ─────────────────────────────────────────────────────────────────────────────

export type AuthenticatorAttachment = 'platform' | 'cross-platform' | undefined;

export async function registerBiometric(
  deviceName?: string,
  attachment?: AuthenticatorAttachment,
): Promise<{ success: boolean; error?: string }> {
  try {
    // Step 1: Get registration challenge from server
    const challengeRes = await api.post<{
      challenge: string;
      challenge_id: string;
      rp: { name: string; id: string };
      user: { id: string; name: string; displayName: string };
      pubKeyCredParams: Array<{ type: 'public-key'; alg: number }>;
      authenticatorSelection: {
        authenticatorAttachment?: string;
        userVerification?: string;
        residentKey?: string;
      };
      timeout: number;
      attestation: string;
      excludeCredentials: Array<{ type: 'public-key'; id: string; transports?: string[] }>;
    }>('/webauthn/register-challenge', {});

    if (!challengeRes.success || !challengeRes.data) {
      return { success: false, error: challengeRes.error || 'Failed to get registration challenge' };
    }

    const serverOptions = challengeRes.data;

    // Step 2: Map server response to SimpleWebAuthn format
    // Allow frontend to override authenticatorAttachment for "this device" vs "other device"
    const authSelection = {
      ...serverOptions.authenticatorSelection,
      ...(attachment ? { authenticatorAttachment: attachment } : {}),
    };

    // WebAuthn Level 3 "hints" tell the browser which UI to prioritize:
    // 'client-device' = Windows Hello / Touch ID / platform authenticator
    // 'hybrid' = phone/tablet via QR code
    // 'security-key' = USB security key
    let hints: Array<'client-device' | 'hybrid' | 'security-key'> | undefined;
    if (attachment === 'platform') {
      hints = ['client-device'];
    } else if (attachment === 'cross-platform') {
      hints = ['hybrid', 'security-key'];
    }

    const optionsJSON: PublicKeyCredentialCreationOptionsJSON = {
      challenge: serverOptions.challenge,
      rp: serverOptions.rp,
      user: serverOptions.user,
      pubKeyCredParams: serverOptions.pubKeyCredParams,
      authenticatorSelection: authSelection as PublicKeyCredentialCreationOptionsJSON['authenticatorSelection'],
      timeout: serverOptions.timeout,
      attestation: serverOptions.attestation as PublicKeyCredentialCreationOptionsJSON['attestation'],
      excludeCredentials: (serverOptions.excludeCredentials ?? []) as PublicKeyCredentialCreationOptionsJSON['excludeCredentials'],
      ...(hints ? { hints } : {}),
    };

    // Step 3: Trigger browser biometric prompt
    // If platform attachment fails (Windows Hello unavailable), retry without it
    // so the browser shows the generic picker (phone/security key options)
    let credential: RegistrationResponseJSON;
    try {
      credential = await startRegistration({ optionsJSON });
    } catch (firstErr: unknown) {
      const msg = firstErr instanceof Error ? firstErr.message : '';
      const isNotAllowed = msg.includes('NotAllowedError') || msg.includes('not allowed') || msg.includes('timed out');
      if (isNotAllowed && attachment === 'platform') {
        // Platform authenticator not available — retry without restriction
        const fallbackOptions: PublicKeyCredentialCreationOptionsJSON = {
          ...optionsJSON,
          authenticatorSelection: {
            ...optionsJSON.authenticatorSelection,
            authenticatorAttachment: undefined,
          },
          hints: undefined,
        };
        credential = await startRegistration({ optionsJSON: fallbackOptions });
      } else {
        throw firstErr;
      }
    }

    // Step 4: Send credential to server for verification + storage
    const verifyRes = await api.post('/webauthn/register-verify', {
      challenge_id: serverOptions.challenge_id,
      id: credential.id,
      rawId: credential.rawId,
      type: credential.type,
      response: credential.response,
      authenticatorAttachment: credential.authenticatorAttachment,
      device_name: deviceName || getDefaultDeviceName(),
    });

    if (!verifyRes.success) {
      return { success: false, error: verifyRes.error || 'Failed to verify registration' };
    }

    return { success: true };
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Biometric registration failed';
    // User cancelled or browser error
    if (message.includes('NotAllowedError') || message.includes('cancelled') || message.includes('denied')) {
      return { success: false, error: 'Biometric registration was cancelled.' };
    }
    return { success: false, error: message };
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Authentication (Login with biometric)
// ─────────────────────────────────────────────────────────────────────────────

export async function authenticateWithBiometric(
  email?: string,
): Promise<{
  success: boolean;
  data?: {
    user: { id: number; first_name: string; last_name: string; email: string };
    access_token: string;
    refresh_token: string;
    expires_in: number;
  };
  error?: string;
}> {
  try {
    // Step 1: Get authentication challenge from server
    const challengeRes = await api.post<{
      challenge: string;
      challenge_id: string;
      rpId: string;
      timeout: number;
      userVerification: string;
      allowCredentials?: Array<{ type: 'public-key'; id: string; transports?: string[] }>;
    }>('/webauthn/auth-challenge', { email }, { skipAuth: true });

    if (!challengeRes.success || !challengeRes.data) {
      return { success: false, error: challengeRes.error || 'Failed to get authentication challenge' };
    }

    const serverOptions = challengeRes.data;

    // Step 2: Map to SimpleWebAuthn format
    // No hints restriction — let the browser show all available options
    // (platform authenticator, cross-device/phone, security key)
    const optionsJSON: PublicKeyCredentialRequestOptionsJSON = {
      challenge: serverOptions.challenge,
      rpId: serverOptions.rpId,
      timeout: serverOptions.timeout,
      userVerification: serverOptions.userVerification as PublicKeyCredentialRequestOptionsJSON['userVerification'],
      allowCredentials: serverOptions.allowCredentials?.map(c => ({
        id: c.id,
        type: c.type,
        ...(c.transports ? { transports: c.transports } : {}),
      })) as PublicKeyCredentialRequestOptionsJSON['allowCredentials'],
    };

    // Step 3: Trigger browser passkey prompt
    const assertion: AuthenticationResponseJSON = await startAuthentication({ optionsJSON });

    // Step 4: Send assertion to server for verification
    const verifyRes = await api.post<{
      success: boolean;
      user: { id: number; first_name: string; last_name: string; email: string };
      access_token: string;
      refresh_token: string;
      expires_in: number;
    }>('/webauthn/auth-verify', {
      challenge_id: serverOptions.challenge_id,
      id: assertion.id,
      rawId: assertion.rawId,
      type: assertion.type,
      response: assertion.response,
      authenticatorAttachment: assertion.authenticatorAttachment,
    }, { skipAuth: true });

    if (!verifyRes.success || !verifyRes.data) {
      return { success: false, error: verifyRes.error || 'Biometric authentication failed' };
    }

    return { success: true, data: verifyRes.data };
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Biometric authentication failed';
    if (message.includes('NotAllowedError') || message.includes('cancelled') || message.includes('denied')) {
      return { success: false, error: 'Biometric login was cancelled.' };
    }
    return { success: false, error: message };
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Conditional Mediation (Passkey Autofill)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check if conditional mediation (passkey autofill) is supported.
 * Requires: autocomplete="username webauthn" on the email/username input.
 */
export async function isConditionalMediationAvailable(): Promise<boolean> {
  if (!browserSupportsWebAuthn()) return false;
  if (typeof PublicKeyCredential === 'undefined') return false;
  if (typeof PublicKeyCredential.isConditionalMediationAvailable !== 'function') return false;
  try {
    return await PublicKeyCredential.isConditionalMediationAvailable();
  } catch {
    return false;
  }
}

/**
 * Start conditional mediation — browser will show passkey suggestions
 * in the username field's autofill dropdown. Call on page load.
 *
 * Returns the auth result if a passkey is selected, or null if aborted/unavailable.
 * The caller should pass an AbortController signal to cancel when unmounting.
 */
export async function startConditionalAuthentication(
  abortSignal?: AbortSignal,
): Promise<{
  success: boolean;
  data?: {
    user: { id: number; first_name: string; last_name: string; email: string };
    access_token: string;
    refresh_token: string;
    expires_in: number;
  };
  error?: string;
} | null> {
  try {
    // Get challenge from server (no email — discoverable credential flow)
    const challengeRes = await api.post<{
      challenge: string;
      challenge_id: string;
      rpId: string;
      timeout: number;
      userVerification: string;
      allowCredentials?: Array<{ type: 'public-key'; id: string; transports?: string[] }>;
    }>('/webauthn/auth-challenge', {}, { skipAuth: true });

    if (!challengeRes.success || !challengeRes.data) {
      return null;
    }

    const serverOptions = challengeRes.data;

    const optionsJSON: PublicKeyCredentialRequestOptionsJSON = {
      challenge: serverOptions.challenge,
      rpId: serverOptions.rpId,
      timeout: serverOptions.timeout,
      userVerification: serverOptions.userVerification as PublicKeyCredentialRequestOptionsJSON['userVerification'],
      // Empty allowCredentials for discoverable credential flow
    };

    // Start conditional authentication — this waits for user to interact
    // with the autofill dropdown
    const assertion: AuthenticationResponseJSON = await startAuthentication({
      optionsJSON,
      useBrowserAutofill: true,
    });

    // If we got here and the signal was aborted, bail
    if (abortSignal?.aborted) return null;

    // Verify with server
    const verifyRes = await api.post<{
      success: boolean;
      user: { id: number; first_name: string; last_name: string; email: string };
      access_token: string;
      refresh_token: string;
      expires_in: number;
    }>('/webauthn/auth-verify', {
      challenge_id: serverOptions.challenge_id,
      id: assertion.id,
      rawId: assertion.rawId,
      type: assertion.type,
      response: assertion.response,
      authenticatorAttachment: assertion.authenticatorAttachment,
    }, { skipAuth: true });

    if (!verifyRes.success || !verifyRes.data) {
      return { success: false, error: verifyRes.error || 'Passkey authentication failed' };
    }

    return { success: true, data: verifyRes.data };
  } catch (err: unknown) {
    // AbortError is expected when component unmounts or user navigates away
    if (err instanceof Error && err.name === 'AbortError') return null;
    const message = err instanceof Error ? err.message : '';
    if (message.includes('NotAllowedError') || message.includes('cancelled')) return null;
    // Conditional mediation failures are not user-facing errors
    return null;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Management (list / remove credentials)
// ─────────────────────────────────────────────────────────────────────────────

export async function getWebAuthnStatus(): Promise<WebAuthnStatus> {
  const res = await api.get<WebAuthnStatus>('/webauthn/status');
  return res.data ?? { registered: false, count: 0 };
}

export async function getWebAuthnCredentials(): Promise<WebAuthnCredential[]> {
  const res = await api.get<{ credentials: WebAuthnCredential[]; count: number }>('/webauthn/credentials');
  return res.data?.credentials ?? [];
}

export async function removeWebAuthnCredential(credentialId: string): Promise<boolean> {
  const res = await api.post('/webauthn/remove', { credential_id: credentialId });
  return res.success;
}

export async function removeAllWebAuthnCredentials(): Promise<{ success: boolean; removedCount: number }> {
  const res = await api.post<{ removed_count: number }>('/webauthn/remove-all', {});
  return { success: res.success, removedCount: res.data?.removed_count ?? 0 };
}
