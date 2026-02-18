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
import { api } from '@/lib/api';
import { useTenant } from '@/contexts/TenantContext';

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
    return !!(window as any).Capacitor?.isNativePlatform?.();
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
    const { PushNotifications } = await import('@capacitor/push-notifications');
    return PushNotifications;
  } catch (e) {
    console.warn('[Push] Failed to load PushNotifications plugin:', e);
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
      await api.post('/api/push/register-device', {
        token,
        platform: 'android',
      });
      console.log('[Push] Device token registered with server');
    } catch (err) {
      console.error('[Push] Failed to register device token:', err);
    }
  }, []);

  // Handle notification tap — navigate to the linked page
  const handleNotificationTap = useCallback(
    (notification: PushNotificationSchema) => {
      const url = notification.data?.url;
      if (url) {
        // Navigate within the app using React Router
        navigate(tenantPath(url));
      }
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
          console.log('[Push] Notifications denied by user');
          return;
        }

        // 2. Request permission if not yet granted
        if (permStatus.receive !== 'granted') {
          const requestResult: PermissionStatus = await PushNotifications.requestPermissions();
          if (requestResult.receive !== 'granted') {
            console.log('[Push] User declined notification permission');
            return;
          }
        }

        // 3. Register with FCM
        await PushNotifications.register();

        // 4. Listen for registration success
        const regListener = await PushNotifications.addListener(
          'registration',
          (token: PushToken) => {
            console.log('[Push] FCM token received:', token.value.substring(0, 20) + '...');
            registerToken(token.value);
          }
        );

        // 5. Listen for registration errors
        const errListener = await PushNotifications.addListener(
          'registrationError',
          (error: any) => {
            console.error('[Push] Registration error:', error);
          }
        );

        // 6. Listen for incoming notifications (foreground)
        const recvListener = await PushNotifications.addListener(
          'pushNotificationReceived',
          (notification: PushNotificationSchema) => {
            console.log('[Push] Notification received in foreground:', notification.title);
            // Don't navigate — the user sees it as a system notification
            // The in-app notification bell also updates via Pusher
          }
        );

        // 7. Listen for notification taps (background/killed → user taps)
        const tapListener = await PushNotifications.addListener(
          'pushNotificationActionPerformed',
          (action: ActionPerformed) => {
            console.log('[Push] Notification tapped:', action.notification.title);
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
        console.error('[Push] Init error:', err);
      }
    };

    initPush();

    return () => {
      cleanup?.();
    };
  }, [userId, registerToken, handleNotificationTap]);
}
