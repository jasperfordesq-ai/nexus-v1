// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Frontend Performance Monitoring
 *
 * Tracks:
 * - Page load times (Performance API)
 * - Slow component renders (>100ms via React Profiler)
 * - API call latency
 * - Client-side errors
 *
 * Logs to localStorage in dev, sends to /api/v2/metrics in production
 */

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface PerformanceMetric {
  type: 'page_load' | 'component_render' | 'api_call' | 'error';
  timestamp: string;
  name: string;
  duration?: number;
  data?: Record<string, unknown>;
}

interface PageLoadMetrics {
  dns: number;
  tcp: number;
  request: number;
  response: number;
  dom: number;
  load: number;
  total: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Configuration
// ─────────────────────────────────────────────────────────────────────────────

const SLOW_COMPONENT_THRESHOLD_MS = 100;
const STORAGE_KEY = 'nexus_performance_metrics';
const MAX_STORED_METRICS = 100;
const BATCH_SEND_INTERVAL_MS = 30000; // Send every 30 seconds

// ─────────────────────────────────────────────────────────────────────────────
// Core Functions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Track a performance metric
 */
export function trackMetric(metric: PerformanceMetric): void {
  const isDev = import.meta.env.DEV;

  if (isDev) {
    // In dev, log to console and localStorage
    console.log('[Performance]', metric);
    storeMetric(metric);
  } else {
    // In production, queue for sending to backend
    queueMetric(metric);
  }
}

/**
 * Track page load performance using Performance API
 */
export function trackPageLoad(pageName: string): void {
  if (!window.performance || !window.performance.timing) {
    return;
  }

  // Wait for load event to complete
  window.addEventListener('load', () => {
    setTimeout(() => {
      const timing = window.performance.timing;
      const navigation = timing.navigationStart;

      const metrics: PageLoadMetrics = {
        dns: timing.domainLookupEnd - timing.domainLookupStart,
        tcp: timing.connectEnd - timing.connectStart,
        request: timing.responseStart - timing.requestStart,
        response: timing.responseEnd - timing.responseStart,
        dom: timing.domContentLoadedEventEnd - timing.domContentLoadedEventStart,
        load: timing.loadEventEnd - timing.loadEventStart,
        total: timing.loadEventEnd - navigation,
      };

      trackMetric({
        type: 'page_load',
        timestamp: new Date().toISOString(),
        name: pageName,
        duration: metrics.total,
        data: metrics as unknown as Record<string, unknown>,
      });
    }, 0);
  });
}

/**
 * Track component render performance (use with React Profiler)
 */
export function trackComponentRender(
  componentName: string,
  phase: 'mount' | 'update',
  actualDuration: number,
  baseDuration: number,
  startTime: number,
  commitTime: number
): void {
  // Only track slow renders
  if (actualDuration < SLOW_COMPONENT_THRESHOLD_MS) {
    return;
  }

  trackMetric({
    type: 'component_render',
    timestamp: new Date().toISOString(),
    name: componentName,
    duration: actualDuration,
    data: {
      phase,
      baseDuration,
      startTime,
      commitTime,
    },
  });
}

/**
 * Track API call latency
 */
export function trackApiCall(endpoint: string, duration: number, success: boolean): void {
  trackMetric({
    type: 'api_call',
    timestamp: new Date().toISOString(),
    name: endpoint,
    duration,
    data: {
      success,
    },
  });
}

/**
 * Track client-side errors
 */
export function trackError(error: Error, context?: string): void {
  trackMetric({
    type: 'error',
    timestamp: new Date().toISOString(),
    name: error.name,
    data: {
      message: error.message,
      stack: error.stack,
      context,
    },
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Storage (Dev Mode)
// ─────────────────────────────────────────────────────────────────────────────

function storeMetric(metric: PerformanceMetric): void {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    const metrics: PerformanceMetric[] = stored ? JSON.parse(stored) : [];

    metrics.push(metric);

    // Keep only the latest MAX_STORED_METRICS
    if (metrics.length > MAX_STORED_METRICS) {
      metrics.splice(0, metrics.length - MAX_STORED_METRICS);
    }

    localStorage.setItem(STORAGE_KEY, JSON.stringify(metrics));
  } catch (e) {
    console.warn('Failed to store performance metric:', e);
  }
}

/**
 * Get stored metrics (dev mode)
 */
export function getStoredMetrics(): PerformanceMetric[] {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored ? JSON.parse(stored) : [];
  } catch {
    return [];
  }
}

/**
 * Clear stored metrics (dev mode)
 */
export function clearStoredMetrics(): void {
  localStorage.removeItem(STORAGE_KEY);
}

// ─────────────────────────────────────────────────────────────────────────────
// Production Queuing & Sending
// ─────────────────────────────────────────────────────────────────────────────

let metricQueue: PerformanceMetric[] = [];
let sendTimer: number | null = null;

function queueMetric(metric: PerformanceMetric): void {
  metricQueue.push(metric);

  // Start batch send timer if not already running
  if (!sendTimer) {
    sendTimer = window.setTimeout(sendQueuedMetrics, BATCH_SEND_INTERVAL_MS);
  }
}

async function sendQueuedMetrics(): Promise<void> {
  if (metricQueue.length === 0) {
    return;
  }

  const metricsToSend = [...metricQueue];
  metricQueue = [];

  try {
    const response = await fetch('/api/v2/metrics', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        metrics: metricsToSend,
        user_agent: navigator.userAgent,
        page_url: window.location.href,
      }),
    });

    if (!response.ok) {
      console.warn('Failed to send performance metrics:', response.statusText);
    }
  } catch (error) {
    console.warn('Failed to send performance metrics:', error);
  } finally {
    sendTimer = null;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Initialization
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Initialize performance monitoring
 */
export function initPerformanceMonitoring(): void {
  // Track page load for initial page
  if (window.performance && window.performance.timing) {
    trackPageLoad(window.location.pathname);
  }

  // Setup global error handler
  window.addEventListener('error', (event) => {
    trackError(event.error || new Error(event.message), 'window.error');
  });

  // Setup unhandled rejection handler
  window.addEventListener('unhandledrejection', (event) => {
    trackError(
      new Error(event.reason?.message || String(event.reason)),
      'unhandledrejection'
    );
  });

  // Send any queued metrics before page unload
  window.addEventListener('beforeunload', () => {
    if (metricQueue.length > 0 && !import.meta.env.DEV) {
      // Use sendBeacon for reliable sending during unload
      navigator.sendBeacon(
        '/api/v2/metrics',
        JSON.stringify({
          metrics: metricQueue,
          user_agent: navigator.userAgent,
          page_url: window.location.href,
        })
      );
    }
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// React Profiler Wrapper
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Callback for React Profiler component
 *
 * Usage:
 * <Profiler id="MyComponent" onRender={onProfilerRender}>
 *   <MyComponent />
 * </Profiler>
 */
export function onProfilerRender(
  id: string,
  phase: 'mount' | 'update',
  actualDuration: number,
  baseDuration: number,
  startTime: number,
  commitTime: number
): void {
  trackComponentRender(id, phase, actualDuration, baseDuration, startTime, commitTime);
}
