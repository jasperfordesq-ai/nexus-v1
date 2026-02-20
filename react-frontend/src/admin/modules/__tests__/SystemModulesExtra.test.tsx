// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch render tests for System admin modules (extra, separate from SystemModules.test.tsx):
 * - ActivityLog, AdminSettings, BlogRestore, CronJobLogs, CronJobs,
 *   CronJobSettings, CronJobSetup, ImageSettings, NativeApp,
 *   SeedGenerator, TestRunner, WebpConverter
 *
 * Smoke tests only — verify each component renders without crashing.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Common mocks ────────────────────────────────────────────────────────────

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn(), getAccessToken: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Admin', last_name: 'User', name: 'Admin User', role: 'admin', is_super_admin: true, tenant_id: 2 },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), showToast: vi.fn() })),
  useNotifications: vi.fn(() => ({ counts: { messages: 0, notifications: 0 } })),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string) => url || '/default.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
  resolveAssetUrl: vi.fn((url: string) => url || ''),
}));

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

// Mock admin API modules used by system components
vi.mock('../../api/adminApi', () => ({
  adminSystem: {
    getActivityLog: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0, has_more: false } },
    }),
    getCronJobs: vi.fn().mockResolvedValue({ success: true, data: [] }),
    runCronJob: vi.fn().mockResolvedValue({ success: true }),
  },
  adminCron: {
    getLogs: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0 } },
    }),
    clearLogs: vi.fn().mockResolvedValue({ success: true }),
    exportLogs: vi.fn().mockResolvedValue({ success: true }),
    getJobSettings: vi.fn().mockResolvedValue({ success: true, data: {} }),
    getGlobalSettings: vi.fn().mockResolvedValue({ success: true, data: {} }),
    updateJobSettings: vi.fn().mockResolvedValue({ success: true }),
    updateGlobalSettings: vi.fn().mockResolvedValue({ success: true }),
    getHealthMetrics: vi.fn().mockResolvedValue({ success: true, data: {} }),
  },
  adminSettings: {
    get: vi.fn().mockResolvedValue({ success: true, data: {} }),
    update: vi.fn().mockResolvedValue({ success: true }),
    getImageSettings: vi.fn().mockResolvedValue({ success: true, data: {} }),
    updateImageSettings: vi.fn().mockResolvedValue({ success: true }),
    getNativeAppSettings: vi.fn().mockResolvedValue({ success: true, data: {} }),
    updateNativeAppSettings: vi.fn().mockResolvedValue({ success: true }),
  },
  adminTools: {
    getBlogBackups: vi.fn().mockResolvedValue({ success: true, data: [] }),
    restoreBlogBackup: vi.fn().mockResolvedValue({ success: true }),
    getWebpStats: vi.fn().mockResolvedValue({ success: true, data: { total: 0, converted: 0 } }),
    runWebpConversion: vi.fn().mockResolvedValue({ success: true }),
    runSeedGenerator: vi.fn().mockResolvedValue({ success: true }),
    runHealthCheck: vi.fn().mockResolvedValue({ success: true, data: { results: [] } }),
  },
}));

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── ActivityLog ────────────────────────────────────────────────────────────

import { ActivityLog } from '../system/ActivityLog';

describe('ActivityLog', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><ActivityLog /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── AdminSettings ──────────────────────────────────────────────────────────

import { AdminSettings } from '../system/AdminSettings';

describe('AdminSettings', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><AdminSettings /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── BlogRestore ────────────────────────────────────────────────────────────

import { BlogRestore } from '../system/BlogRestore';

describe('BlogRestore', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><BlogRestore /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── CronJobLogs ────────────────────────────────────────────────────────────

import { CronJobLogs } from '../system/CronJobLogs';

describe('CronJobLogs', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><CronJobLogs /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── CronJobs ───────────────────────────────────────────────────────────────

import { CronJobs } from '../system/CronJobs';

describe('CronJobs', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><CronJobs /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── CronJobSettingsPage ────────────────────────────────────────────────────

import { CronJobSettingsPage } from '../system/CronJobSettings';

describe('CronJobSettingsPage', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><CronJobSettingsPage /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── CronJobSetup ───────────────────────────────────────────────────────────

import { CronJobSetup } from '../system/CronJobSetup';

describe('CronJobSetup', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><CronJobSetup /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── ImageSettings ──────────────────────────────────────────────────────────

import { ImageSettings } from '../system/ImageSettings';

describe('ImageSettings', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><ImageSettings /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── NativeApp ──────────────────────────────────────────────────────────────

import { NativeApp } from '../system/NativeApp';

describe('NativeApp', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><NativeApp /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── SeedGenerator ──────────────────────────────────────────────────────────

import { SeedGenerator } from '../system/SeedGenerator';

describe('SeedGenerator', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><SeedGenerator /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── TestRunner ─────────────────────────────────────────────────────────────

import { TestRunner } from '../system/TestRunner';

describe('TestRunner', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><TestRunner /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── WebpConverter ──────────────────────────────────────────────────────────

import { WebpConverter } from '../system/WebpConverter';

describe('WebpConverter', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><WebpConverter /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});
