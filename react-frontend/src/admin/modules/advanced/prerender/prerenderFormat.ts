// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { formatUnit, getFormattingLocale } from '@/lib/helpers';
import type { PrerenderJob } from '../../../api/adminApi';

export function formatBytes(n: number): string {
  if (n < 1024) return formatUnit(n, 'byte');
  if (n < 1024 * 1024) return formatUnit(n / 1024, 'kilobyte', { maximumFractionDigits: 1 });
  return formatUnit(n / 1024 / 1024, 'megabyte', { maximumFractionDigits: 2 });
}

export function formatAge(seconds: number | null | undefined): string {
  if (seconds == null) return '—';
  if (seconds < 60) return formatUnit(seconds, 'second');
  if (seconds < 3600) return formatUnit(Math.floor(seconds / 60), 'minute');
  if (seconds < 86400) return formatUnit(Math.floor(seconds / 3600), 'hour');
  return formatUnit(Math.floor(seconds / 86400), 'day');
}

export function formatTs(ts: number | string | null | undefined): string {
  if (!ts) return '—';
  const d = typeof ts === 'number' ? new Date(ts * 1000) : new Date(ts);
  if (Number.isNaN(d.getTime())) return String(ts);
  return d.toLocaleString(getFormattingLocale());
}

export function stalenessColor(staleness: 'fresh' | 'warn' | 'stale'): 'success' | 'warning' | 'danger' {
  return staleness === 'fresh' ? 'success' : staleness === 'warn' ? 'warning' : 'danger';
}

export function seoGradeColor(grade: 'A' | 'B' | 'C' | 'D' | 'F'): 'success' | 'primary' | 'warning' | 'danger' | 'default' {
  switch (grade) {
    case 'A': return 'success';
    case 'B': return 'primary';
    case 'C': return 'warning';
    case 'D': return 'warning';
    case 'F': return 'danger';
  }
}

export const SEO_GRADE_TEXT_CLASSES: Record<ReturnType<typeof seoGradeColor>, string> = {
  success: 'text-success',
  primary: 'text-accent',
  warning: 'text-warning',
  danger: 'text-danger',
  default: 'text-muted',
};

export function httpStatusColor(n: number): 'default' | 'success' | 'warning' | 'danger' {
  if (n === 200) return 'success';
  if (n >= 300 && n < 400) return 'default';
  if (n >= 400 && n < 500) return 'warning';
  if (n >= 500) return 'danger';
  return 'default';
}

export function jobStatusColor(status: PrerenderJob['status']): 'default' | 'primary' | 'success' | 'warning' | 'danger' {
  switch (status) {
    case 'pending_fence': return 'warning';
    case 'queued': return 'default';
    case 'claimed':
    case 'running': return 'primary';
    case 'succeeded': return 'success';
    case 'partial': return 'warning';
    case 'failed': return 'danger';
    case 'cancelled': return 'default';
  }
}
