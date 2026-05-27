// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export const MAX_SUPPORT_DIAGNOSTIC_ENTRIES = 50;

type DiagnosticKind = 'api' | 'console';
type ConsoleLevel = 'debug' | 'info' | 'log' | 'warn' | 'error';

export interface SupportDiagnosticEntry {
  kind: DiagnosticKind;
  timestamp: string;
  level?: ConsoleLevel;
  message?: string;
  args?: unknown[];
  method?: string;
  endpoint?: string;
  status?: number;
  duration_ms?: number;
}

export interface SupportDiagnosticsSnapshot {
  captured_at: string;
  page_url: string | null;
  route: string | null;
  user_agent: string | null;
  viewport: {
    width: number | null;
    height: number | null;
  };
  build: {
    commit: string | null;
    time: string | null;
  };
  entries: SupportDiagnosticEntry[];
}

const SENSITIVE_KEY_PATTERN = /(authorization|password|passcode|token|secret|cookie|csrf|session|email|phone|address|credit|card|cvv|iban|sort_code)/i;
const EMAIL_PATTERN = /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi;
const BEARER_PATTERN = /Bearer\s+[A-Za-z0-9._~+/=-]+/gi;
const MAX_STRING_LENGTH = 1000;
const MAX_DEPTH = 5;
const FILTERED = '[filtered]';

const entries: SupportDiagnosticEntry[] = [];
let restoreConsole: (() => void) | null = null;

export function clearSupportDiagnostics(): void {
  entries.length = 0;
}

export function recordConsoleDiagnostic(level: ConsoleLevel, args: unknown[]): void {
  const safeArgs = redactValue(args) as unknown[];
  const message = safeArgs.map((arg) => {
    if (typeof arg === 'string') return arg;
    try {
      return JSON.stringify(arg);
    } catch {
      return String(arg);
    }
  }).join(' ');

  pushEntry({
    kind: 'console',
    timestamp: new Date().toISOString(),
    level,
    message,
    args: safeArgs,
  });
}

export function recordApiDiagnostic(input: {
  method: string;
  endpoint: string;
  status: number;
  durationMs: number;
}): void {
  pushEntry({
    kind: 'api',
    timestamp: new Date().toISOString(),
    method: input.method.toUpperCase(),
    endpoint: redactUrl(input.endpoint),
    status: input.status,
    duration_ms: Math.round(input.durationMs),
  });
}

export function getSupportDiagnosticsSnapshot(): SupportDiagnosticsSnapshot {
  return {
    captured_at: new Date().toISOString(),
    page_url: typeof window === 'undefined' ? null : redactUrl(window.location.href),
    route: typeof window === 'undefined' ? null : `${window.location.pathname}${window.location.search}${window.location.hash}`,
    user_agent: typeof navigator === 'undefined' ? null : navigator.userAgent,
    viewport: {
      width: typeof window === 'undefined' ? null : window.innerWidth,
      height: typeof window === 'undefined' ? null : window.innerHeight,
    },
    build: {
      commit: typeof __BUILD_COMMIT__ === 'undefined' ? null : __BUILD_COMMIT__,
      time: typeof __BUILD_TIME__ === 'undefined' ? null : __BUILD_TIME__,
    },
    entries: entries.map((entry) => ({ ...entry })),
  };
}

export function installSupportDiagnosticsCapture(): () => void {
  if (restoreConsole) {
    return restoreConsole;
  }

  const originalWarn = console.warn;
  const originalError = console.error;

  console.warn = (...args: unknown[]) => {
    recordConsoleDiagnostic('warn', args);
    originalWarn.apply(console, args);
  };

  console.error = (...args: unknown[]) => {
    recordConsoleDiagnostic('error', args);
    originalError.apply(console, args);
  };

  restoreConsole = () => {
    console.warn = originalWarn;
    console.error = originalError;
    restoreConsole = null;
  };

  return restoreConsole;
}

function pushEntry(entry: SupportDiagnosticEntry): void {
  entries.push(entry);
  while (entries.length > MAX_SUPPORT_DIAGNOSTIC_ENTRIES) {
    entries.shift();
  }
}

function redactValue(value: unknown, depth = 0): unknown {
  if (depth > MAX_DEPTH) {
    return '[truncated]';
  }

  if (Array.isArray(value)) {
    return value.slice(0, 80).map((item) => redactValue(item, depth + 1));
  }

  if (value && typeof value === 'object') {
    const output: Record<string, unknown> = {};
    for (const [key, item] of Object.entries(value).slice(0, 80)) {
      if (SENSITIVE_KEY_PATTERN.test(key)) {
        output[key] = FILTERED;
        continue;
      }

      output[key] = redactValue(item, depth + 1);
    }
    return output;
  }

  if (typeof value === 'string') {
    return redactString(value);
  }

  if (typeof value === 'number' || typeof value === 'boolean' || value === null || value === undefined) {
    return value;
  }

  return redactString(String(value));
}

function redactString(value: string): string {
  const redacted = value
    .replace(BEARER_PATTERN, `Bearer ${FILTERED}`)
    .replace(EMAIL_PATTERN, FILTERED);

  return redacted.length > MAX_STRING_LENGTH ? redacted.slice(0, MAX_STRING_LENGTH) : redacted;
}

function redactUrl(value: string): string {
  try {
    const base = typeof window === 'undefined' ? 'https://app.project-nexus.ie' : window.location.origin;
    const url = new URL(value, base);
    for (const key of Array.from(url.searchParams.keys())) {
      if (SENSITIVE_KEY_PATTERN.test(key)) {
        url.searchParams.set(key, FILTERED);
      } else {
        url.searchParams.set(key, redactString(url.searchParams.get(key) ?? ''));
      }
    }

    const redacted = value.startsWith('http://') || value.startsWith('https://')
      ? url.toString()
      : `${url.pathname}${url.search}${url.hash}`;

    return redacted.replace(/%5Bfiltered%5D/gi, FILTERED);
  } catch {
    return redactString(value);
  }
}
