// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for VolunteeringPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [], meta: {} }),
    post: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('framer-motion', () => {  const motionProps = new Set(['variants', 'initial', 'animate', 'layout', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);  const filterMotion = (props: Record<string, unknown>) => {    const filtered: Record<string, unknown> = {};    for (const [k, v] of Object.entries(props)) {      if (!motionProps.has(k)) filtered[k] = v;    }    return filtered;  };  return {    motion: {      div: ({ children, ...props }: Record<string, unknown>) => <div {...filterMotion(props)}>{children}</div>,    },    AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,  };});


vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string, fallbackOrOpts?: string | Record<string, unknown>, _opts?: Record<string, unknown>) => {
      // Lookup table for keys used by VolunteeringPage
      const translations: Record<string, string> = {
        "volunteering.heading": "Volunteering",
        "volunteering.subtitle": "Find opportunities and track your impact",
        "volunteering.tab_opportunities": "Opportunities",
        "volunteering.tab_applications": "My Applications",
        "volunteering.tab_hours": "My Hours",
        "volunteering.tab_for_you": "For You",
        "volunteering.tab_certificates": "Certificates",
        "volunteering.tab_alerts": "Alerts",
        "volunteering.tab_wellbeing": "Wellbeing",
        "volunteering.tab_credentials": "Credentials",
        "volunteering.tab_waitlist": "Waitlist",
        "volunteering.tab_swap_requests": "Swap Requests",
        "volunteering.tab_group_signups": "Group Sign-ups",
        "volunteering.browse_organisations": "Browse Organisations",
        "volunteering.post_opportunity": "Post Opportunity",
        "volunteering.search_placeholder": "Search opportunities...",
        "volunteering.no_opportunities_found": "No opportunities found",
        "volunteering.apply": "Apply",
        "volunteering.applied": "Applied",
        "volunteering.unable_to_load_opportunities": "Unable to load opportunities",
        "volunteering.try_again": "Try Again",
        "volunteering.page_title": "Volunteering",
        "volunteering.feature_not_available": "Volunteering Not Available",
        "volunteering.feature_not_available_desc": "The volunteering feature is not enabled for this community.",
        "volunteering.error_load_opportunities": "Failed to load opportunities",
        "volunteering.error_load_opportunities_retry": "Failed to load more opportunities",
        "volunteering.applied_success": "Successfully applied!",
        "volunteering.apply_error": "Failed to apply",
        "volunteering.apply_to_volunteer": "Apply to Volunteer",
        "volunteering.applied_on": "Applied",
      };
      if (typeof fallbackOrOpts === "string") {
        return translations[key] ?? fallbackOrOpts;
      }
      return translations[key] ?? key;
    },
    i18n: { language: "en", changeLanguage: vi.fn() },
  }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
  initReactI18next: { type: "3rdParty", init: vi.fn() },
}));

import { VolunteeringPage } from './VolunteeringPage';
import { api } from '@/lib/api';

describe('VolunteeringPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the page heading and description', () => {
    render(<VolunteeringPage />);
    expect(screen.getByText('Volunteering')).toBeInTheDocument();
    expect(screen.getByText('Find opportunities and track your impact')).toBeInTheDocument();
  });

  it('shows Opportunities tab button', () => {
    render(<VolunteeringPage />);
    expect(screen.getByText('Opportunities')).toBeInTheDocument();
  });

  it('shows My Applications and My Hours tabs when authenticated', () => {
    render(<VolunteeringPage />);
    expect(screen.getByText('My Applications')).toBeInTheDocument();
    expect(screen.getByText('My Hours')).toBeInTheDocument();
  });

  it('shows Browse Organisations button', () => {
    render(<VolunteeringPage />);
    expect(screen.getByText('Browse Organisations')).toBeInTheDocument();
  });

  it('shows search input for opportunities', () => {
    render(<VolunteeringPage />);
    expect(screen.getByPlaceholderText('Search opportunities...')).toBeInTheDocument();
  });

  it('shows empty state when no opportunities exist', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [],
      meta: { cursor: null, has_more: false },
    });
    render(<VolunteeringPage />);
    await waitFor(() => {
      expect(screen.getByText('No opportunities found')).toBeInTheDocument();
    });
  });

  it('renders opportunity cards with Apply button', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          title: 'Community Garden Helper',
          description: 'Help maintain the community garden',
          location: 'Dublin',
          skills_needed: 'Gardening',
          start_date: '2026-03-01',
          end_date: '2026-06-30',
          is_active: true,
          is_remote: false,
          category: 'Environment',
          organization: { id: 1, name: 'Green Org', logo_url: null },
          created_at: '2026-02-01',
          has_applied: false,
        },
      ],
      meta: { cursor: null, has_more: false },
    });
    render(<VolunteeringPage />);
    await waitFor(() => {
      expect(screen.getByText('Community Garden Helper')).toBeInTheDocument();
    });
    expect(screen.getByText('Green Org')).toBeInTheDocument();
    expect(screen.getByText('Apply')).toBeInTheDocument();
  });

  it('shows Applied chip and hides Apply button when already applied', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          title: 'Already Applied Opportunity',
          description: 'Test',
          location: 'Cork',
          skills_needed: '',
          start_date: null,
          end_date: null,
          is_active: true,
          is_remote: false,
          category: null,
          organization: { id: 1, name: 'Test Org', logo_url: null },
          created_at: '2026-02-01',
          has_applied: true,
        },
      ],
      meta: { cursor: null, has_more: false },
    });
    render(<VolunteeringPage />);
    await waitFor(() => {
      expect(screen.getByText('Applied')).toBeInTheDocument();
    });
    expect(screen.queryByText('Apply')).not.toBeInTheDocument();
  });
});
