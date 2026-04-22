// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for PlatformLegalPage component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import FileText from 'lucide-react/icons/file-text';

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() })),
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, name: 'Test User', role: 'user' },
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle',
    error: null,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Timebank', slug: 'test' },
    branding: { name: 'Test Timebank', logo_url: null },
    tenantSlug: 'test',
    tenantPath: (p: string) => '/test' + p,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

import { PlatformLegalPage } from '../PlatformLegalPage';
import type { PlatformLegalSection } from '../PlatformLegalPage';

const mockSections: PlatformLegalSection[] = [
  {
    id: 'introduction',
    title: 'Introduction',
    content: <p>Welcome to our platform.</p>,
  },
  {
    id: 'definitions',
    title: 'Definitions',
    content: <p>Key terms used in this document.</p>,
  },
  {
    id: 'user-obligations',
    title: 'User Obligations',
    content: <p>What users must do.</p>,
  },
  {
    id: 'privacy',
    title: 'Privacy',
    content: <p>How we handle data.</p>,
  },
];

const mockCrossLinks = [
  { label: 'Platform Privacy', to: '/platform-privacy' },
  { label: 'Platform Disclaimer', to: '/platform-disclaimer' },
];

describe('PlatformLegalPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms of Service"
        subtitle="These terms govern your use of the platform."
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    expect(screen.getByText('Platform Terms of Service')).toBeInTheDocument();
  });

  it('renders the title and subtitle', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms of Service"
        subtitle="These terms govern your use of the platform."
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    expect(screen.getByText('Platform Terms of Service')).toBeInTheDocument();
    expect(screen.getByText('These terms govern your use of the platform.')).toBeInTheDocument();
  });

  it('renders the effective date', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    expect(screen.getByText('Effective 1 March 2026')).toBeInTheDocument();
  });

  it('renders the Project NEXUS Platform chip', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    expect(screen.getByText('Project NEXUS Platform')).toBeInTheDocument();
  });

  it('renders Platform Provider Notice', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    expect(screen.getByText('Platform Provider Notice')).toBeInTheDocument();
  });

  it('renders all sections', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    // Each section appears in both TOC and section heading
    expect(screen.getAllByText('Introduction').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Definitions').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('User Obligations').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Privacy').length).toBeGreaterThanOrEqual(1);
  });

  it('renders section content', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    expect(screen.getByText('Welcome to our platform.')).toBeInTheDocument();
    expect(screen.getByText('Key terms used in this document.')).toBeInTheDocument();
  });

  it('renders Table of Contents when 4+ sections', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    expect(screen.getByText('Contents')).toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: /contents/i })).toBeInTheDocument();
  });

  it('does not render Table of Contents when less than 4 sections', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections.slice(0, 3)}
      />
    );
    expect(screen.queryByText('Contents')).not.toBeInTheDocument();
  });

  it('renders cross-links when provided', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
        crossLinks={mockCrossLinks}
      />
    );
    expect(screen.getByText('Related Platform Documents')).toBeInTheDocument();
    expect(screen.getByText('Platform Privacy')).toBeInTheDocument();
    expect(screen.getByText('Platform Disclaimer')).toBeInTheDocument();
  });

  it('does not render cross-links section when none provided', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    expect(screen.queryByText('Related Platform Documents')).not.toBeInTheDocument();
  });

  it('renders the footer CTA section', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    expect(screen.getByText('Questions About This Document?')).toBeInTheDocument();
    expect(screen.getByText('Project NEXUS Website')).toBeInTheDocument();
  });

  it('renders tenant name in contact section', () => {
    render(
      <PlatformLegalPage
        title="Platform Terms"
        subtitle="Terms"
        icon={FileText}
        effectiveDate="1 March 2026"
        sections={mockSections}
      />
    );
    expect(screen.getByText('Contact Test Timebank')).toBeInTheDocument();
  });
});
