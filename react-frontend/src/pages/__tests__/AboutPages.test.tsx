// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for About pages (7 pages)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

// Mock dependencies
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Community', logo_url: null },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: vi.fn((url: string) => url || ''),
  resolveAvatarUrl: vi.fn((url: string) => url || '/default-avatar.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

vi.mock('framer-motion', () => {
  const motionProps = new Set(['variants', 'initial', 'animate', 'whileInView', 'viewport', 'layout', 'transition', 'exit', 'whileHover', 'whileTap']);
  const filterMotion = (props: Record<string, unknown>) => {
    const filtered: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(props)) {
      if (!motionProps.has(k)) filtered[k] = v;
    }
    return filtered;
  };
  return {
    motion: {
      div: ({ children, ...props }: Record<string, unknown>) => <div {...filterMotion(props)}>{children}</div>,
      h1: ({ children, ...props }: Record<string, unknown>) => <h1 {...filterMotion(props)}>{children}</h1>,
      p: ({ children, ...props }: Record<string, unknown>) => <p {...filterMotion(props)}>{children}</p>,
    },
    AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  };
});

vi.mock('lucide-react', () => {
  const MockIcon = ({ className, 'aria-hidden': ariaHidden }: { className?: string; 'aria-hidden'?: boolean | string }) => (
    <span className={className} aria-hidden={ariaHidden}>icon</span>
  );
  // Return a Proxy that returns MockIcon for any icon name
  return new Proxy({}, {
    get: () => MockIcon,
  });
});

import { ImpactReportPage } from '../about/ImpactReportPage';
import { ImpactSummaryPage } from '../about/ImpactSummaryPage';
import { PartnerPage } from '../about/PartnerPage';
import { SocialPrescribingPage } from '../about/SocialPrescribingPage';
import { StrategicPlanPage } from '../about/StrategicPlanPage';
import { TimebankingGuidePage } from '../about/TimebankingGuidePage';

describe('About Pages', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('ImpactReportPage', () => {
    it('renders without crashing', () => {
      render(<ImpactReportPage />);
      expect(screen.getByText(/Social Impact Study/i)).toBeInTheDocument();
    });

    it('shows SROI ratio', () => {
      render(<ImpactReportPage />);
      expect(screen.getByText(/16.*1/i)).toBeInTheDocument();
    });

    it('renders table of contents', () => {
      render(<ImpactReportPage />);
      expect(screen.getByText(/Contents/i)).toBeInTheDocument();
    });

    it('shows download buttons', () => {
      render(<ImpactReportPage />);
      expect(screen.getAllByText(/Download Full Report/i)[0]).toBeInTheDocument();
    });
  });

  describe('ImpactSummaryPage', () => {
    it('renders without crashing', () => {
      render(<ImpactSummaryPage />);
      expect(screen.getByText(/Impact at a Glance/i)).toBeInTheDocument();
    });

    it('shows key metrics', () => {
      render(<ImpactSummaryPage />);
      expect(screen.getByText(/Key Outcomes/i)).toBeInTheDocument();
    });

    it('renders hero section', () => {
      render(<ImpactSummaryPage />);
      expect(screen.getByText(/Creating social value/i)).toBeInTheDocument();
    });
  });

  describe('PartnerPage', () => {
    it('renders without crashing', () => {
      render(<PartnerPage />);
      expect(screen.getByText(/Our Partners/i)).toBeInTheDocument();
    });

    it('shows partner categories', () => {
      render(<PartnerPage />);
      expect(screen.getByText(/Funding Partners/i)).toBeInTheDocument();
    });

    it('renders partner logos section', () => {
      render(<PartnerPage />);
      expect(screen.getByText(/Strategic Partners/i)).toBeInTheDocument();
    });
  });

  describe('SocialPrescribingPage', () => {
    it('renders without crashing', () => {
      render(<SocialPrescribingPage />);
      expect(screen.getByText(/Social Prescribing/i)).toBeInTheDocument();
    });

    it('shows what is social prescribing section', () => {
      render(<SocialPrescribingPage />);
      expect(screen.getByText(/What is Social Prescribing/i)).toBeInTheDocument();
    });

    it('renders benefits section', () => {
      render(<SocialPrescribingPage />);
      expect(screen.getByText(/Benefits/i)).toBeInTheDocument();
    });
  });

  describe('StrategicPlanPage', () => {
    it('renders without crashing', () => {
      render(<StrategicPlanPage />);
      expect(screen.getByText(/Strategic Plan/i)).toBeInTheDocument();
    });

    it('shows vision and mission', () => {
      render(<StrategicPlanPage />);
      expect(screen.getByText(/Vision/i)).toBeInTheDocument();
    });

    it('renders strategic pillars', () => {
      render(<StrategicPlanPage />);
      expect(screen.getByText(/Strategic Pillars/i)).toBeInTheDocument();
    });
  });

  describe('TimebankingGuidePage', () => {
    it('renders without crashing', () => {
      render(<TimebankingGuidePage />);
      expect(screen.getByText(/Timebanking Guide/i)).toBeInTheDocument();
    });

    it('shows how it works section', () => {
      render(<TimebankingGuidePage />);
      expect(screen.getByText(/How.*Works/i)).toBeInTheDocument();
    });

    it('renders getting started section', () => {
      render(<TimebankingGuidePage />);
      expect(screen.getByText(/Getting Started/i)).toBeInTheDocument();
    });

    it('shows benefits section', () => {
      render(<TimebankingGuidePage />);
      expect(screen.getByText(/Benefits/i)).toBeInTheDocument();
    });
  });
});
