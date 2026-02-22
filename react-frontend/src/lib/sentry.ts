// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Sentry Error Tracking - React Frontend
 *
 * Provides real-time error tracking and performance monitoring.
 * Reads DSN from VITE_SENTRY_DSN env var — if empty, all exports are safe no-ops.
 */

import * as Sentry from '@sentry/react';
import { createElement } from 'react';
import type { User } from '@/types';
import type { ReactNode, ComponentType } from 'react';

interface TenantInfo {
  id: number;
  name: string;
  slug: string;
}

type SeverityLevel = 'fatal' | 'error' | 'warning' | 'log' | 'info' | 'debug';

const DSN = import.meta.env.VITE_SENTRY_DSN as string | undefined;
const IS_ENABLED = !!DSN;

// ─────────────────────────────────────────────────────────────────────────────
// Initialization
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Initialize Sentry SDK. Must be called before React renders (main.tsx).
 */
export function initSentry(): void {
  if (!IS_ENABLED) {
    return;
  }

  Sentry.init({
    dsn: DSN,
    environment: (import.meta.env.VITE_SENTRY_ENVIRONMENT as string) || 'production',
    release: `nexus-react@${(import.meta.env.VITE_BUILD_COMMIT as string) || 'dev'}`,

    // Error sampling — capture all errors
    sampleRate: 1.0,

    // Performance sampling — configurable via env
    tracesSampleRate: parseFloat(
      (import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE as string) || '0.1'
    ),

    // Session replay (disabled by default — enable if needed)
    replaysSessionSampleRate: 0,
    replaysOnErrorSampleRate: 0,

    // Breadcrumbs
    maxBreadcrumbs: 50,

    // Privacy — don't send PII by default
    sendDefaultPii: false,

    // Integration config
    integrations: [
      Sentry.browserTracingIntegration(),
    ],

    // Filter sensitive data before sending
    beforeSend(event) {
      // Strip sensitive fields from request data
      if (event.request?.data && typeof event.request.data === 'object') {
        const data = event.request.data as Record<string, unknown>;
        const sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key', 'secret', 'csrf_token'];
        for (const field of sensitiveFields) {
          if (field in data) {
            data[field] = '[FILTERED]';
          }
        }
      }
      return event;
    },

    // Filter breadcrumbs
    beforeBreadcrumb(breadcrumb) {
      // Don't send console.debug breadcrumbs
      if (breadcrumb.category === 'console' && breadcrumb.level === 'debug') {
        return null;
      }
      return breadcrumb;
    },
  });

  // Set global tags
  Sentry.setTag('platform', 'react');
  Sentry.setTag('app_component', 'frontend');
}

// ─────────────────────────────────────────────────────────────────────────────
// Context setters (called from AuthContext / TenantContext)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Set user context after login. Pass null on logout to clear.
 */
export function setSentryUser(user: User | null): void {
  if (!IS_ENABLED) return;

  if (user) {
    Sentry.setUser({
      id: String(user.id),
      email: user.email,
      username: user.name || user.first_name,
    });
  } else {
    Sentry.setUser(null);
  }
}

/**
 * Set tenant context after tenant detection.
 */
export function setSentryTenant(tenant: TenantInfo | null): void {
  if (!IS_ENABLED) return;

  if (tenant) {
    Sentry.setTag('tenant_id', String(tenant.id));
    Sentry.setTag('tenant_name', tenant.name);
    Sentry.setTag('tenant_slug', tenant.slug);
    Sentry.setContext('tenant', {
      id: tenant.id,
      name: tenant.name,
      slug: tenant.slug,
    });
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Manual capture functions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Add breadcrumb for debugging
 */
export function addSentryBreadcrumb(
  message: string,
  category: string = 'default',
  data: Record<string, unknown> = {},
  level: SeverityLevel = 'info'
): void {
  if (!IS_ENABLED) return;

  Sentry.addBreadcrumb({
    message,
    category,
    data,
    level,
  });
}

/**
 * Capture an exception manually
 */
export function captureSentryException(error: Error, context?: Record<string, unknown>): void {
  if (!IS_ENABLED) return;

  Sentry.captureException(error, {
    contexts: context ? { additional: context } : undefined,
  });
}

/**
 * Capture a message
 */
export function captureSentryMessage(
  message: string,
  level: SeverityLevel = 'error',
  context?: Record<string, unknown>
): void {
  if (!IS_ENABLED) return;

  Sentry.captureMessage(message, {
    level,
    contexts: context ? { additional: context } : undefined,
  });
}

/**
 * Start a performance span (returns the active span or undefined)
 */
export function startSentrySpan(name: string, op: string = 'function'): ReturnType<typeof Sentry.startInactiveSpan> | undefined {
  if (!IS_ENABLED) return undefined;

  return Sentry.startInactiveSpan({ name, op });
}

// ─────────────────────────────────────────────────────────────────────────────
// Breadcrumb helpers (called from api.ts, AuthContext, etc.)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Capture navigation breadcrumb
 */
export function captureNavigation(from: string, to: string): void {
  addSentryBreadcrumb(`Navigate: ${from} → ${to}`, 'navigation', { from, to });
}

/**
 * Capture API call breadcrumb
 */
export function captureApiCall(
  method: string,
  endpoint: string,
  status: number,
  duration: number
): void {
  addSentryBreadcrumb(
    `${method} ${endpoint} → ${status}`,
    'http',
    { method, url: endpoint, status_code: status, duration_ms: Math.round(duration) },
    status >= 400 ? 'error' : 'info'
  );
}

/**
 * Capture authentication event
 */
export function captureAuthEvent(
  event: string,
  userId?: number,
  data?: Record<string, unknown>
): void {
  addSentryBreadcrumb(
    `Auth: ${event}`,
    'auth',
    { event, user_id: userId, ...data },
    event.includes('fail') ? 'warning' : 'info'
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// React components
// ─────────────────────────────────────────────────────────────────────────────

/**
 * React Error Boundary — wraps the app to catch render errors and report to Sentry.
 * Falls back to passthrough if Sentry is disabled.
 */
export function SentryErrorBoundary({ children, fallback }: {
  children: ReactNode;
  fallback?: ReactNode | ComponentType<{ error: Error }>;
}): ReactNode {
  if (!IS_ENABLED) {
    return children;
  }

  return createElement(Sentry.ErrorBoundary, {
    fallback: fallback as any,
  }, children);
}

/**
 * Sentry profiler wrapper
 */
export function SentryProfiler({ children }: { children: ReactNode }): ReactNode {
  if (!IS_ENABLED) {
    return children;
  }

  return Sentry.withProfiler(() => children as any)({});
}
