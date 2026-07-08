// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readStoredConsent } from '@/lib/cookieConsentStorage';
import type { User } from '@/types';

type SentryFacade = typeof import('@/lib/sentry');
type SeverityLevel = 'fatal' | 'error' | 'warning' | 'log' | 'info' | 'debug';
type SentryTask = (Sentry: SentryFacade) => void;

type IdleWindow = Window & {
  requestIdleCallback?: (
    callback: IdleRequestCallback,
    options?: IdleRequestOptions,
  ) => number;
};

const MAX_PENDING_TASKS = 100;
const SENTRY_QUEUE_FLUSH_DELAY_MS = 45000;

const pendingTasks: SentryTask[] = [];
let isScheduled = false;
let isFlushing = false;

const AUTH_ENTRY_PATHS = new Set([
  '/login',
  '/register',
  '/forgot-password',
  '/reset-password',
  '/password/forgot',
  '/password/reset',
  '/verify-email',
  '/verify-identity',
  '/auth/oauth/callback',
  '/oauth/callback',
]);

function hasAnalyticsConsent(): boolean {
  return readStoredConsent()?.analytics === true;
}

function isAuthEntryPath(): boolean {
  if (typeof window === 'undefined') return false;

  const normalizedPath = window.location.pathname.toLowerCase().replace(/\/+$/, '') || '/';
  const segments = normalizedPath.split('/').filter(Boolean);
  const candidatePaths = segments.map((_, index) => `/${segments.slice(index).join('/')}`);
  return candidatePaths.some((candidate) => AUTH_ENTRY_PATHS.has(candidate));
}

function runAfterFirstPaintIdle(callback: () => void): void {
  if (typeof window === 'undefined') {
    callback();
    return;
  }

  const scheduleIdle = () => {
    const idleWindow = window as IdleWindow;
    if (typeof idleWindow.requestIdleCallback === 'function') {
      idleWindow.requestIdleCallback(callback, { timeout: 5000 });
      return;
    }

    window.setTimeout(callback, 3000);
  };

  window.requestAnimationFrame(() => {
    window.requestAnimationFrame(scheduleIdle);
  });
}

function runAfterDelayedIdle(callback: () => void, delayMs: number): void {
  if (typeof window === 'undefined') {
    callback();
    return;
  }

  window.setTimeout(() => runAfterFirstPaintIdle(callback), delayMs);
}

function scheduleFlush(): void {
  if (isScheduled || isFlushing) return;
  if (isAuthEntryPath()) return;

  isScheduled = true;
  runAfterDelayedIdle(() => {
    isScheduled = false;
    flushPendingTasks();
  }, SENTRY_QUEUE_FLUSH_DELAY_MS);
}

function flushPendingTasks(): void {
  if (isFlushing || pendingTasks.length === 0) return;
  if (isAuthEntryPath()) return;

  if (!hasAnalyticsConsent()) {
    pendingTasks.length = 0;
    return;
  }

  isFlushing = true;
  void import('@/lib/sentry')
    .then((Sentry) => {
      const tasks = pendingTasks.splice(0);
      for (const task of tasks) {
        task(Sentry);
      }
    })
    .finally(() => {
      isFlushing = false;
      if (pendingTasks.length > 0) {
        scheduleFlush();
      }
    });
}

function enqueueSentryTask(task: SentryTask): void {
  if (!hasAnalyticsConsent()) return;

  pendingTasks.push(task);
  if (pendingTasks.length > MAX_PENDING_TASKS) {
    pendingTasks.shift();
  }

  scheduleFlush();
}

export function queueSentryUser(user: User | null): void {
  enqueueSentryTask(({ setSentryUser }) => setSentryUser(user));
}

export function queueSentryTenant(tenant: { id: number; name: string; slug: string } | null): void {
  enqueueSentryTask(({ setSentryTenant }) => setSentryTenant(tenant));
}

export function queueSentryBreadcrumb(
  message: string,
  category: string,
  data: Record<string, unknown>,
  level: SeverityLevel = 'info',
): void {
  enqueueSentryTask(({ addSentryBreadcrumb }) => {
    addSentryBreadcrumb(message, category, data, level);
  });
}

export function queueSentryApiCall(
  method: string,
  endpoint: string,
  status: number,
  duration: number,
): void {
  enqueueSentryTask(({ captureApiCall }) => {
    captureApiCall(method, endpoint, status, duration);
  });
}

export function queueSentryAuthEvent(
  event: string,
  userId?: number,
  context?: Record<string, unknown>,
): void {
  enqueueSentryTask(({ captureAuthEvent }) => {
    captureAuthEvent(event, userId, context);
  });
}

export function queueSentryMessage(
  message: string,
  level: SeverityLevel,
  context?: Record<string, unknown>,
): void {
  enqueueSentryTask(({ captureSentryMessage }) => {
    captureSentryMessage(message, level, context);
  });
}

export function queueSentryException(
  error: Error,
  context?: Record<string, unknown>,
): void {
  enqueueSentryTask(({ captureSentryException }) => {
    captureSentryException(error, context);
  });
}
