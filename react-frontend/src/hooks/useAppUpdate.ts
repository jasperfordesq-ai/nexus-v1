/**
 * App Update Hook (Capacitor native apps only)
 *
 * Checks the server for a newer app version on mount.
 * If an update is available, exposes the update info so a modal can be shown.
 * No-ops on regular browsers (non-Capacitor).
 *
 * API: POST /api/app/check-version { version, platform }
 */

import { useState, useEffect } from 'react';
import { api } from '@/lib/api';

// Hardcoded to match capacitor/android/app/build.gradle versionName
// Must be updated here when releasing a new APK
const APP_VERSION = '1.1';

export interface AppUpdateInfo {
  updateAvailable: boolean;
  forceUpdate: boolean;
  currentVersion: string;
  clientVersion: string;
  updateUrl: string;
  updateMessage: string;
  releaseNotes: Record<string, string[]>;
}

function isNativeApp(): boolean {
  try {
    return !!(window as any).Capacitor?.isNativePlatform?.();
  } catch {
    return false;
  }
}

export function useAppUpdate() {
  const [updateInfo, setUpdateInfo] = useState<AppUpdateInfo | null>(null);
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    if (!isNativeApp()) return;

    // Don't check more than once per session
    const checked = sessionStorage.getItem('nexus_update_checked');
    if (checked) return;

    const checkUpdate = async () => {
      try {
        const res = await api.post('/api/app/check-version', {
          version: APP_VERSION,
          platform: 'android',
        });

        const data = (res as any).data || res;
        sessionStorage.setItem('nexus_update_checked', '1');

        if (data?.update_available) {
          setUpdateInfo({
            updateAvailable: true,
            forceUpdate: !!data.force_update,
            currentVersion: data.current_version ?? '',
            clientVersion: data.client_version ?? APP_VERSION,
            updateUrl: data.update_url ?? '',
            updateMessage: data.update_message || 'A new version is available.',
            releaseNotes: data.release_notes || {},
          });
        }
      } catch (e) {
        // Silently fail — don't block the app over an update check
        console.warn('[AppUpdate] Version check failed:', e);
      }
    };

    // Small delay so the app loads first
    const timer = setTimeout(checkUpdate, 3000);
    return () => clearTimeout(timer);
  }, []);

  return {
    updateInfo: dismissed ? null : updateInfo,
    dismiss: () => setDismissed(true),
    isForceUpdate: updateInfo?.forceUpdate ?? false,
  };
}
