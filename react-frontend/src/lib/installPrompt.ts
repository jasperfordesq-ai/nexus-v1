// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PWA install prompt singleton.
 *
 * Captures the `beforeinstallprompt` event at module load (imported once from
 * main.tsx) so it isn't lost — Chrome only fires the event once per page load
 * and doesn't fire it again, so we MUST capture eagerly. Subscribers (the
 * useInstallPrompt hook) read the captured value via getSnapshot.
 */

import { useSyncExternalStore } from 'react';

interface BeforeInstallPromptEvent extends Event {
  readonly platforms: ReadonlyArray<string>;
  readonly userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
  prompt(): Promise<void>;
}

let capturedEvent: BeforeInstallPromptEvent | null = null;
let installed = false;
let cachedSnapshot: InstallPromptState | null = null;
const listeners = new Set<() => void>();

function emit(): void {
  cachedSnapshot = null;
  for (const fn of listeners) fn();
}

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    capturedEvent = e as BeforeInstallPromptEvent;
    emit();
  });
  window.addEventListener('appinstalled', () => {
    capturedEvent = null;
    installed = true;
    emit();
  });
}

function isStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  if (window.matchMedia?.('(display-mode: standalone)').matches) return true;
  // iOS Safari exposes this non-standard flag when launched from the home screen.
  const nav = window.navigator as Navigator & { standalone?: boolean };
  return nav.standalone === true;
}

function isIos(): boolean {
  if (typeof window === 'undefined') return false;
  const ua = window.navigator.userAgent;
  // iPad on iOS 13+ reports as Mac — also check touch points to distinguish.
  const isIpadOS = /Macintosh/.test(ua) && (window.navigator.maxTouchPoints ?? 0) > 1;
  return /iPad|iPhone|iPod/.test(ua) || isIpadOS;
}

function isSafari(): boolean {
  if (typeof window === 'undefined') return false;
  const ua = window.navigator.userAgent;
  return /Safari/.test(ua) && !/Chrome|CriOS|FxiOS|EdgiOS/.test(ua);
}

export interface InstallPromptState {
  /** Native prompt is available — call promptInstall() to show it. */
  canPrompt: boolean;
  /** Browser is iOS/iPadOS — show manual "Add to Home Screen" instructions. */
  isIos: boolean;
  /** App is currently running in standalone/installed mode. */
  isInstalled: boolean;
  /** Browser is iOS Safari specifically (the only iOS browser that can install). */
  isIosSafari: boolean;
}

function getSnapshot(): InstallPromptState {
  // useSyncExternalStore compares snapshots by Object.is — must return a stable
  // reference until emit() invalidates the cache, otherwise React re-renders
  // every tick and trips the max-update-depth guard.
  if (cachedSnapshot) return cachedSnapshot;
  cachedSnapshot = {
    canPrompt: capturedEvent !== null && !installed,
    isIos: isIos(),
    isInstalled: installed || isStandalone(),
    isIosSafari: isIos() && isSafari(),
  };
  return cachedSnapshot;
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export async function promptInstall(): Promise<'accepted' | 'dismissed' | 'unavailable'> {
  if (!capturedEvent) return 'unavailable';
  try {
    await capturedEvent.prompt();
    const { outcome } = await capturedEvent.userChoice;
    // Per spec, the event can only be used once.
    capturedEvent = null;
    emit();
    return outcome;
  } catch {
    return 'unavailable';
  }
}

export function useInstallPrompt(): InstallPromptState & { promptInstall: typeof promptInstall } {
  const state = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
  return { ...state, promptInstall };
}

/**
 * True when ANY install affordance is available — native Chrome prompt OR
 * iOS Safari manual install. Used to decide whether to render install UI.
 */
export function shouldOfferInstall(state: InstallPromptState): boolean {
  if (state.isInstalled) return false;
  if (state.canPrompt) return true;
  if (state.isIosSafari) return true;
  return false;
}
