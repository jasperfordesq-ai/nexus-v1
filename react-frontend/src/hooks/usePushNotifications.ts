// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Push Notifications Hook (Capacitor / FCM)
 *
 * Handles native push notification registration when running inside
 * a Capacitor WebView. No-ops gracefully on regular browsers.
 *
 * Flow:
 *  1. Detect Capacitor native environment
 *  2. Request notification permission (Android 13+ POST_NOTIFICATIONS)
 *  3. Register with FCM → receive device token
 *  4. Send token to backend: POST /api/push/register-device
 *  5. Listen for incoming notifications and handle taps
 */

import { useEffect, useRef, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, tokenManager } from '@/lib/api';
import { useTenant } from '@/contexts/TenantContext';
import { logDebug, logError } from '@/lib/logger';

// Capacitor types — we import dynamically to avoid build errors on web
type PermissionStatus = { receive: 'prompt' | 'prompt-with-rationale' | 'granted' | 'denied' };
type PushToken = { value: string };
type PushNotificationSchema = {
  title?: string;
  body?: string;
  data: Record<string, string>;
};
type ActionPerformed = {
  notification: PushNotificationSchema;
};

/**
 * Check if we're running inside a Capacitor native app
 */
function isNativeApp(): boolean {
  try {
    // Capacitor injects window.Capacitor when running in a native WebView
    return !!window.Capacitor?.isNativePlatform?.();
  } catch {
    return false;
  }
}

/**
 * Dynamically get the PushNotifications plugin from Capacitor
 * Returns null if not available (web browser)
 */
async function getPushPlugin() {
  try {
    if (!isNativeApp()) return null;
    // Use variable import so TypeScript doesn't try to resolve the module at build time.
    // This package only exists in the Capacitor native app, not in the web bundle.
    const modulePath = '@capacitor/push-notifications';
    const mod = await import(/* @vite-ignore */ modulePath);
    return mod.PushNotifications;
  } catch (e) {
    logError('[Push] Failed to load PushNotifications plugin', e);
    return null;
  }
}

/**
 * Hook to manage native push notifications.
 * Call this once the user is authenticated.
 *
 * @param userId - Current user's ID (null if not logged in)
 */
export function usePushNotifications(userId: number | null) {
  const registeredRef = useRef(false);
  const navigate = useNavigate();
  const { tenantPath } = useTenant();

  // Register device token with backend
  const registerToken = useCallback(async (token: string) => {
    try {
      const platform = (() => {
        try { return window.Capacitor?.getPlatform?.() || 'android'; }
        catch { return 'android'; }
      })();
      await api.post('/push/register-device', {
        token,
        platform,
      });
      logDebug('[Push] Device token registered with server');
    } catch (err) {
      logError('[Push] Failed to register device token', err);
    }
  }, []);

  // Handle notification tap — navigate to the linked page.
  // The URL comes from notification.data which is attacker-influenceable if
  // the push payload is ever tampered with or crafted via a rogue integration.
  // Only accept same-origin, non-protocol-prefixed paths (e.g. "/dashboard"),
  // never absolute URLs, schemes, or protocol-relative paths.
  const handleNotificationTap = useCallback(
    (notification: PushNotificationSchema) => {
      const raw = notification.data?.url;
      if (typeof raw !== 'string' || raw.length === 0) return;
      // Reject anything that isn't a same-origin path. This blocks
      // "https://evil.com", "//evil.com", "javascript:...", "mailto:...".
      if (!raw.startsWith('/') || raw.startsWith('//')) return;

      // If the user's session has expired since the notification was delivered,
      // redirect them to login. Encode the intended path so LoginPage can redirect
      // back after a successful login (if it supports ?next= in future).
      const isAuthenticated = tokenManager.hasAccessToken() || tokenManager.hasRefreshToken();
      if (!isAuthenticated) {
        logDebug('[Push] Session expired — redirecting to login before navigating to', raw);
        navigate(tenantPath('/login'));
        return;
      }

      navigate(tenantPath(raw));
    },
    [navigate, tenantPath]
  );

  useEffect(() => {
    // Only run for authenticated users in native app
    if (!userId || !isNativeApp() || registeredRef.current) return;

    let cleanup: (() => void) | undefined;

    const initPush = async () => {
      const PushNotifications = await getPushPlugin();
      if (!PushNotifications) return;

      try {
        // 1. Check current permission status
        const permStatus: PermissionStatus = await PushNotifications.checkPermissions();

        if (permStatus.receive === 'denied') {
          // User permanently denied — Android won't show the dialog again.
          // They must enable it manually in Settings > Apps > Project NEXUS > Notifications.
          logDebug('[Push] Notifications permanently denied — user must enable in Settings');
          return;
        }

        // 2. Request permission if not yet granted.
        // 'prompt-with-rationale' means the user previously dismissed the dialog;
        // Android will show the system dialog again with its built-in rationale text.
        // 'prompt' means first ask — system dialog appears immediately.
        // Both are handled identically here since the system dialog is always shown.
        if (permStatus.receive !== 'granted') {
          logDebug('[Push] Requesting notification permission (status:', permStatus.receive, ')');
          const requestResult: PermissionStatus = await PushNotifications.requestPermissions();
          if (requestResult.receive !== 'granted') {
            logDebug('[Push] User declined notification permission');
            return;
          }
        }

        // 3. Register with FCM
        await PushNotifications.register();

        // 4. Listen for registration success
        const regListener = await PushNotifications.addListener(
          'registration',
          (token: PushToken) => {
            logDebug('[Push] FCM token received', token.value.substring(0, 20) + '...');
            registerToken(token.value);
          }
        );

        // 5. Listen for registration errors
        const errListener = await PushNotifications.addListener(
          'registrationError',
          (error: unknown) => {
            logError('[Push] Registration error', error);
          }
        );

        // 6. Listen for incoming notifications (foreground)
        const recvListener = await PushNotifications.addListener(
          'pushNotificationReceived',
          (notification: PushNotificationSchema) => {
            logDebug('[Push] Notification received in foreground', notification.title);
            // Don't navigate — the user sees it as a system notification
            // The in-app notification bell also updates via Pusher
          }
        );

        // 7. Listen for notification taps (background/killed → user taps)
        const tapListener = await PushNotifications.addListener(
          'pushNotificationActionPerformed',
          (action: ActionPerformed) => {
            logDebug('[Push] Notification tapped', action.notification.title);
            handleNotificationTap(action.notification);
          }
        );

        registeredRef.current = true;

        cleanup = () => {
          regListener.remove();
          errListener.remove();
          recvListener.remove();
          tapListener.remove();
        };
      } catch (err) {
        logError('[Push] Init error', err);
      }
    };

    initPush();

    return () => {
      cleanup?.();
    };
  }, [userId, registerToken, handleNotificationTap]);
}
