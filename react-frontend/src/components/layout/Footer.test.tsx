// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for Footer component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

// --- Mocks ---

const mockUseTenant = vi.fn();
const mockUseFeature = vi.fn();

vi.mock('@/contexts', () => ({
  useTenant: (...args: any[]) => mockUseTenant(...args),
  useFeature: (...args: any[]) => mockUseFeature(...args),
}));

import { Footer, FooterLink } from './Footer';

function setupDefaultMocks(overrides: {
  tenant?: Record<string, any>;
  eventsEnabled?: boolean;
  blogEnabled?: boolean;
} = {}) {
  mockUseTenant.mockReturnValue({
    tenant: {
      id: 2,
      name: 'Test Tenant',
      slug: 'test-tenant',
      config: {},
      contact: null,
      ...overrides.tenant,
    },
    branding: {
      name: 'Test Community',
      logo: null,
      tagline: 'Building stronger communities',
    },
    tenantPath: (p: string) => p,
    ...overrides.tenant,
  });
  // useFeature is called twice: once for 'events', once for 'blog'
  mockUseFeature.mockImplementation((feature: string) => {
    if (feature === 'events') return overrides.eventsEnabled ?? false;
    if (feature === 'blog') return overrides.blogEnabled ?? false;
    return false;
  });
}

describe('Footer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupDefaultMocks();
  });

  describe('Copyright / Footer text', () => {
    it('renders default copyright text with tenant branding name', () => {
      render(<Footer />);
      const year = new Date().getFullYear();
      expect(screen.getByText(`\u00A9 ${year} Test Community. All rights reserved.`)).toBeInTheDocument();
    });

    it('renders custom footer_text when set in tenant config', () => {
      setupDefaultMocks({
        tenant: {
          tenant: { id: 2, name: 'Test', slug: 'test', config: { footer_text: 'Custom Footer Text' }, contact: null },
        },
      });
      render(<Footer />);
      expect(screen.getByText('Custom Footer Text')).toBeInTheDocument();
    });

    it('renders copyright prop when passed', () => {
      setupDefaultMocks({
        tenant: {
          tenant: { id: 2, name: 'Test', slug: 'test', config: {}, contact: null },
        },
      });
      render(<Footer copyright="My Custom Copyright" />);
      expect(screen.getByText('My Custom Copyright')).toBeInTheDocument();
    });
  });

  describe('AGPL attribution', () => {
    it('renders Built on Project NEXUS by Jasper Ford', () => {
      render(<Footer />);
      const link = screen.getByText('Built on Project NEXUS by Jasper Ford');
      expect(link).toBeInTheDocument();
    });

    it('attribution links to the GitHub repository', () => {
      render(<Footer />);
      const link = screen.getByText('Built on Project NEXUS by Jasper Ford').closest('a');
      expect(link).toHaveAttribute('href', 'https://github.com/jasperfordesq-ai/nexus-v1');
    });

    it('attribution opens in new tab with security attributes', () => {
      render(<Footer />);
      const link = screen.getByText('Built on Project NEXUS by Jasper Ford').closest('a');
      expect(link).toHaveAttribute('target', '_blank');
      expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    });
  });

  describe('Brand section', () => {
    it('renders the tenant brand name', () => {
      render(<Footer />);
      expect(screen.getByText('Test Community')).toBeInTheDocument();
    });

    it('renders brand logo when set', () => {
      setupDefaultMocks({
        tenant: {
          branding: { name: 'Logo Tenant', logo: '/logo.png', tagline: 'Test' },
          tenant: { id: 2, name: 'Test', slug: 'test', config: {}, contact: null },
        },
      });
      render(<Footer />);
      const img = screen.getByAltText('Logo Tenant');
      expect(img).toHaveAttribute('src', '/logo.png');
    });

    it('renders tagline', () => {
      render(<Footer />);
      expect(screen.getByText('Building stronger communities')).toBeInTheDocument();
    });

    it('renders default tagline when branding tagline is empty', () => {
      setupDefaultMocks({
        tenant: {
          branding: { name: 'Test', logo: null, tagline: '' },
          tenant: { id: 2, name: 'Test', slug: 'test', config: {}, contact: null },
        },
      });
      render(<Footer />);
      expect(screen.getByText('Building stronger communities through the exchange of time.')).toBeInTheDocument();
    });
  });

  describe('Legal links', () => {
    it('renders Terms of Service link', () => {
      render(<Footer />);
      expect(screen.getByText('Terms of Service')).toBeInTheDocument();
    });

    it('renders Privacy Policy link', () => {
      render(<Footer />);
      expect(screen.getByText('Privacy Policy')).toBeInTheDocument();
    });

    it('renders Accessibility link', () => {
      render(<Footer />);
      expect(screen.getByText('Accessibility')).toBeInTheDocument();
    });

    it('renders Legal Hub link', () => {
      render(<Footer />);
      expect(screen.getByText('Legal Hub')).toBeInTheDocument();
    });
  });

  describe('Platform links', () => {
    it('renders Listings link', () => {
      render(<Footer />);
      expect(screen.getByText('Listings')).toBeInTheDocument();
    });

    it('renders Members link', () => {
      render(<Footer />);
      expect(screen.getByText('Members')).toBeInTheDocument();
    });

    it('renders Events link when events feature is enabled', () => {
      setupDefaultMocks({ eventsEnabled: true });
      render(<Footer />);
      expect(screen.getByText('Events')).toBeInTheDocument();
    });

    it('does NOT render Events link when events feature is disabled', () => {
      setupDefaultMocks({ eventsEnabled: false });
      render(<Footer />);
      // Only "Events" in Platform section — not present
      expect(screen.queryByText('Events')).not.toBeInTheDocument();
    });

    it('renders Blog link when blog feature is enabled', () => {
      setupDefaultMocks({ blogEnabled: true });
      render(<Footer />);
      expect(screen.getByText('Blog')).toBeInTheDocument();
    });

    it('does NOT render Blog link when blog feature is disabled', () => {
      setupDefaultMocks({ blogEnabled: false });
      render(<Footer />);
      expect(screen.queryByText('Blog')).not.toBeInTheDocument();
    });
  });

  describe('Support links', () => {
    it('renders Help Center link', () => {
      render(<Footer />);
      expect(screen.getByText('Help Center')).toBeInTheDocument();
    });

    it('renders Contact Us link', () => {
      render(<Footer />);
      expect(screen.getByText('Contact Us')).toBeInTheDocument();
    });

    it('renders About link', () => {
      render(<Footer />);
      expect(screen.getByText('About')).toBeInTheDocument();
    });
  });

  describe('Contact info', () => {
    it('renders email when tenant contact has email', () => {
      setupDefaultMocks({
        tenant: {
          tenant: { id: 2, name: 'Test', slug: 'test', config: {}, contact: { email: 'info@test.com' } },
        },
      });
      render(<Footer />);
      expect(screen.getByText('info@test.com')).toBeInTheDocument();
    });

    it('renders phone when tenant contact has phone', () => {
      setupDefaultMocks({
        tenant: {
          tenant: { id: 2, name: 'Test', slug: 'test', config: {}, contact: { phone: '+1234567890' } },
        },
      });
      render(<Footer />);
      expect(screen.getByText('+1234567890')).toBeInTheDocument();
    });

    it('renders location when tenant contact has location', () => {
      setupDefaultMocks({
        tenant: {
          tenant: { id: 2, name: 'Test', slug: 'test', config: {}, contact: { location: 'Dublin, Ireland' } },
        },
      });
      render(<Footer />);
      expect(screen.getByText('Dublin, Ireland')).toBeInTheDocument();
    });
  });

  describe('Custom children', () => {
    it('renders custom children instead of default footer content', () => {
      render(<Footer><div>Custom Footer Content</div></Footer>);
      expect(screen.getByText('Custom Footer Content')).toBeInTheDocument();
      // Default Platform section heading should not appear
      expect(screen.queryByText('Platform')).not.toBeInTheDocument();
    });
  });
});

describe('FooterLink', () => {
  it('renders a link with correct href', () => {
    render(<FooterLink href="/test-link">Test Link</FooterLink>);
    const link = screen.getByText('Test Link');
    expect(link).toBeInTheDocument();
    expect(link.closest('a')).toHaveAttribute('href', '/test-link');
  });
});
