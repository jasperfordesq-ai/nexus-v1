// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Sentry Error Tracking - React Frontend
 *
 * This wrapper deliberately avoids a static @sentry/react import. Public routes
 * import these helpers for breadcrumbs and error capture, so loading the SDK at
 * module evaluation time would put Sentry in the startup bundle even before the
 * visitor has granted analytics consent.
 */

import { Component, createElement } from 'react';
import { readStoredConsent } from '@/lib/cookieConsentStorage';
import type { User } from '@/types';
import type { ComponentType, ErrorInfo, ReactElement, ReactNode } from 'react';

interface TenantInfo {
  id: number;
  name: string;
  slug: string;
}

type SeverityLevel = 'fatal' | 'error' | 'warning' | 'log' | 'info' | 'debug';
type SentryModule = typeof import('@sentry/react');
type SentrySpan = ReturnType<SentryModule['startInactiveSpan']>;

const DSN = import.meta.env.VITE_SENTRY_DSN as string | undefined;
const REPLAY_ON_ERROR_SAMPLE_RATE = Number.parseFloat(
  (import.meta.env.VITE_SENTRY_REPLAY_ON_ERROR_SAMPLE_RATE as string | undefined) || '0',
);

function checkEnabled(): boolean {
  if (!DSN) return false;
  const consent = readStoredConsent();
  return consent?.analytics === true;
}

let IS_ENABLED = checkEnabled();
let sentryModule: SentryModule | null = null;
let sentryLoading: Promise<SentryModule | null> | null = null;
let hasInitialized = false;
let idleInitHandle: number | null = null;

type IdleWindow = Window & {
  requestIdleCallback?: (
    callback: IdleRequestCallback,
    options?: IdleRequestOptions,
  ) => number;
  cancelIdleCallback?: (handle: number) => void;
};

function getReplayOnErrorSampleRate(): number {
  return Number.isFinite(REPLAY_ON_ERROR_SAMPLE_RATE)
    ? Math.max(0, Math.min(1, REPLAY_ON_ERROR_SAMPLE_RATE))
    : 0;
}

function sensitiveFields(): string[] {
  return [
    'password',
    'password_confirmation',
    'current_password',
    'token',
    'api_key',
    'secret',
    'csrf_token',
    'email',
    'phone',
    'credit_card',
    'card_number',
    'cvv',
    'refresh_token',
    'access_token',
  ];
}

async function loadSentry(): Promise<SentryModule | null> {
  if (!IS_ENABLED) return null;
  if (sentryModule) return sentryModule;
  if (sentryLoading) return sentryLoading;

  sentryLoading = import('@sentry/react')
    .then((mod) => {
      sentryModule = mod;
      return mod;
    })
    .catch(() => null)
    .finally(() => {
      sentryLoading = null;
    });

  return sentryLoading;
}

async function loadAndInitializeSentry(): Promise<void> {
  const Sentry = await loadSentry();
  if (!Sentry || hasInitialized || !IS_ENABLED) return;

  const replayOnErrorSampleRate = getReplayOnErrorSampleRate();
  const integrations: unknown[] = [
    Sentry.browserTracingIntegration(),
    Sentry.feedbackIntegration({
      colorScheme: 'system',
      autoInject: false,
    }),
  ];

  if (replayOnErrorSampleRate > 0) {
    integrations.push(Sentry.replayIntegration({
      maskAllText: true,
      blockAllMedia: true,
    }));
  }

  Sentry.init({
    dsn: DSN,
    environment: (import.meta.env.VITE_SENTRY_ENVIRONMENT as string) || 'production',
    release: `nexus-react@${__BUILD_COMMIT__}`,
    sampleRate: 1.0,
    tracesSampleRate: parseFloat(
      (import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE as string) || '0.1',
    ),
    replaysSessionSampleRate: 0,
    replaysOnErrorSampleRate: replayOnErrorSampleRate,
    maxBreadcrumbs: 50,
    sendDefaultPii: false,
    integrations: integrations as Parameters<SentryModule['init']>[0]['integrations'],
    beforeSend(event) {
      if (event.request?.data && typeof event.request.data === 'object') {
        const data = event.request.data as Record<string, unknown>;
        for (const field of sensitiveFields()) {
          if (field in data) {
            data[field] = '[FILTERED]';
          }
        }
      }
      return event;
    },
    beforeBreadcrumb(breadcrumb) {
      if (breadcrumb.category === 'console' && breadcrumb.level === 'debug') {
        return null;
      }
      return breadcrumb;
    },
  });

  hasInitialized = true;
  Sentry.setTag('platform', 'react');
  Sentry.setTag('app_component', 'frontend');
  Sentry.setTag('build_commit', __BUILD_COMMIT__);
  Sentry.setTag('build_time', __BUILD_TIME__);
}

export function initSentry(): void {
  IS_ENABLED = checkEnabled();
  if (!IS_ENABLED) return;
  void loadAndInitializeSentry();
}

export function initSentryAfterIdle(): void {
  IS_ENABLED = checkEnabled();
  if (!IS_ENABLED || hasInitialized || idleInitHandle !== null) return;

  const scheduleAfterFirstPaint = () => {
    const idleWindow = window as IdleWindow;
    if (typeof idleWindow.requestIdleCallback === 'function') {
      idleInitHandle = idleWindow.requestIdleCallback(() => {
        idleInitHandle = null;
        initSentry();
      }, { timeout: 5000 });
      return;
    }

    idleInitHandle = window.setTimeout(() => {
      idleInitHandle = null;
      initSentry();
    }, 3000);
  };

  window.requestAnimationFrame(() => {
    window.requestAnimationFrame(scheduleAfterFirstPaint);
  });
}

export function setSentryUser(user: User | null): void {
  if (!IS_ENABLED) return;
  void loadSentry().then((Sentry) => {
    if (!Sentry) return;
    Sentry.setUser(user ? { id: String(user.id) } : null);
  });
}

export function setSentryTenant(tenant: TenantInfo | null): void {
  if (!IS_ENABLED || !tenant) return;
  void loadSentry().then((Sentry) => {
    if (!Sentry) return;
    Sentry.setTag('tenant_id', String(tenant.id));
    Sentry.setTag('tenant_name', tenant.name);
    Sentry.setTag('tenant_slug', tenant.slug);
    Sentry.setContext('tenant', {
      id: tenant.id,
      name: tenant.name,
      slug: tenant.slug,
    });
  });
}

export function addSentryBreadcrumb(
  message: string,
  category: string = 'default',
  data: Record<string, unknown> = {},
  level: SeverityLevel = 'info',
): void {
  if (!IS_ENABLED) return;
  void loadSentry().then((Sentry) => {
    Sentry?.addBreadcrumb({ message, category, data, level });
  });
}

export function captureSentryException(error: Error, context?: Record<string, unknown>): void {
  if (!IS_ENABLED) return;
  void loadSentry().then((Sentry) => {
    Sentry?.captureException(error, {
      contexts: context ? { additional: context } : undefined,
    });
  });
}

export function captureSentryMessage(
  message: string,
  level: SeverityLevel = 'error',
  context?: Record<string, unknown>,
): string | null {
  if (!IS_ENABLED || !sentryModule) return null;

  return sentryModule.captureMessage(message, {
    level,
    contexts: context ? { additional: context } : undefined,
  });
}

export function captureSentryFeedback(params: {
  message: string;
  source?: string;
  associatedEventId?: string | null;
  url?: string;
  tags?: Record<string, string | number | boolean | null>;
}, options: { includeReplay?: boolean } = {}): string | null {
  if (!IS_ENABLED || !sentryModule) return null;

  return sentryModule.captureFeedback({
    message: params.message,
    source: params.source,
    associatedEventId: params.associatedEventId ?? undefined,
    url: params.url,
    tags: Object.fromEntries(
      Object.entries(params.tags ?? {}).filter(([, value]) => value !== null),
    ) as Record<string, string | number | boolean>,
  }, {
    includeReplay: options.includeReplay === true,
  });
}

export function startSentrySpan(name: string, op: string = 'function'): SentrySpan | undefined {
  if (!IS_ENABLED || !sentryModule) return undefined;
  return sentryModule.startInactiveSpan({ name, op });
}

export function captureNavigation(from: string, to: string): void {
  addSentryBreadcrumb(`Navigate: ${from} -> ${to}`, 'navigation', { from, to });
}

export function captureApiCall(
  method: string,
  endpoint: string,
  status: number,
  duration: number,
): void {
  addSentryBreadcrumb(
    `${method} ${endpoint} -> ${status}`,
    'http',
    { method, url: endpoint, status_code: status, duration_ms: Math.round(duration) },
    status >= 400 ? 'error' : 'info',
  );
}

export function captureAuthEvent(
  event: string,
  userId?: number,
  data?: Record<string, unknown>,
): void {
  addSentryBreadcrumb(
    `Auth: ${event}`,
    'auth',
    { event, user_id: userId, ...data },
    event.includes('fail') ? 'warning' : 'info',
  );
}

class LocalErrorBoundary extends Component<{
  children?: ReactNode;
  fallback?: ReactNode | ComponentType<{ error: Error }>;
}, { error: Error | null }> {
  state = { error: null };

  static getDerivedStateFromError(error: Error) {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    captureSentryException(error, {
      source: 'root_error_boundary',
      componentStack: info.componentStack,
    });
  }

  render(): ReactNode {
    if (!this.state.error) {
      return this.props.children;
    }

    const { fallback } = this.props;
    if (!fallback) {
      return null;
    }

    if (typeof fallback === 'function') {
      return createElement(fallback, { error: this.state.error });
    }

    return fallback;
  }
}

export function SentryErrorBoundary({ children, fallback }: {
  children: ReactNode;
  fallback?: ReactNode | ComponentType<{ error: Error }>;
}): ReactNode {
  return createElement(LocalErrorBoundary, { fallback, children });
}

export function SentryProfiler({ children }: { children: ReactNode }): ReactNode {
  if (!IS_ENABLED || !sentryModule) {
    return children;
  }

  const Profiled = sentryModule.withProfiler(() => children as ReactElement);
  return createElement(Profiled);
}
