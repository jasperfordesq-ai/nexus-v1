// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Sentry Error Tracking - React Frontend
 *
 * Provides real-time error tracking and performance monitoring for the React frontend.
 * Currently provides no-op stubs — install @sentry/react and update this file to enable.
 *
 * Features (when @sentry/react is installed):
 * - Automatic React error boundary
 * - User and tenant context
 * - Performance monitoring
 * - Breadcrumb logging
 * - Network request tracking
 */

import type { User } from '@/types';
import type { ReactNode, ComponentType } from 'react';

interface TenantInfo {
  id: number;
  name: string;
  slug: string;
}

type SeverityLevel = 'fatal' | 'error' | 'warning' | 'log' | 'info' | 'debug';

// ─────────────────────────────────────────────────────────────────────────────
// Sentry SDK detection
// ─────────────────────────────────────────────────────────────────────────────

// @sentry/react is not yet installed — all exports are no-ops.
// To enable Sentry:
// 1. npm install @sentry/react
// 2. Replace this file with the full implementation (see git history)

// ─────────────────────────────────────────────────────────────────────────────
// Public API (no-op stubs)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Initialize Sentry SDK (no-op until @sentry/react is installed)
 */
export function initSentry(): void {
  // No-op
}

/**
 * Set user context after login
 */
export function setSentryUser(_user: User | null): void {
  // No-op
}

/**
 * Set tenant context after tenant detection
 */
export function setSentryTenant(_tenant: TenantInfo | null): void {
  // No-op
}

/**
 * Add breadcrumb for debugging
 */
export function addSentryBreadcrumb(
  _message: string,
  _category: string = 'default',
  _data: Record<string, unknown> = {},
  _level: SeverityLevel = 'info'
): void {
  // No-op
}

/**
 * Capture an exception manually
 */
export function captureSentryException(_error: Error, _context?: Record<string, unknown>): void {
  // No-op
}

/**
 * Capture a message
 */
export function captureSentryMessage(
  _message: string,
  _level: SeverityLevel = 'error',
  _context?: Record<string, unknown>
): void {
  // No-op
}

/**
 * Start a performance span (returns undefined)
 */
export function startSentrySpan(_name: string, _op: string = 'function'): undefined {
  return undefined;
}

/**
 * Capture navigation breadcrumb
 */
export function captureNavigation(_from: string, _to: string): void {
  // No-op
}

/**
 * Capture API call breadcrumb
 */
export function captureApiCall(
  _method: string,
  _endpoint: string,
  _status: number,
  _duration: number
): void {
  // No-op
}

/**
 * Capture authentication event
 */
export function captureAuthEvent(
  _event: string,
  _userId?: number,
  _data?: Record<string, unknown>
): void {
  // No-op
}

/**
 * React Error Boundary component (passthrough — renders children as-is)
 */
export function SentryErrorBoundary({ children, fallback: _fallback }: {
  children: ReactNode;
  fallback?: ReactNode | ComponentType<{ error: Error }>;
}): ReactNode {
  return children;
}

/**
 * Sentry profiler (passthrough)
 */
export function SentryProfiler({ children }: { children: ReactNode }): ReactNode {
  return children;
}
