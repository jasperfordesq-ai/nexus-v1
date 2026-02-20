// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for QuickCreateMenu component
 * Verifies modal rendering, feature/module gating, and navigation
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import { QuickCreateMenu } from '../QuickCreateMenu';

// ─── Mocks ───────────────────────────────────────────────────────────────────

const mockNavigate = vi.fn();
const mockHasFeature = vi.fn();
const mockHasModule = vi.fn();
const mockTenantPath = vi.fn((p: string) => `/test${p}`);

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

vi.mock('@/contexts', () => ({
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: mockHasFeature,
    hasModule: mockHasModule,
    tenantPath: mockTenantPath,
  }),
}));

vi.mock('lucide-react', () => ({
  ListTodo: () => <span data-testid="icon-list" />,
  Calendar: () => <span data-testid="icon-calendar" />,
  Users: () => <span data-testid="icon-users" />,
  Target: () => <span data-testid="icon-target" />,
  X: () => <span data-testid="icon-close" />,
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: any) => <div {...props}>{children}</div>,
  },
  AnimatePresence: ({ children }: any) => <>{children}</>,
}));

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('QuickCreateMenu', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default: all features/modules enabled
    mockHasFeature.mockReturnValue(true);
    mockHasModule.mockReturnValue(true);
  });

  it('renders when open', () => {
    render(
      <W>
        <QuickCreateMenu isOpen={true} onClose={vi.fn()} />
      </W>
    );
    expect(screen.getByText('Create New')).toBeTruthy();
  });

  it('does not render when closed', () => {
    const { container } = render(
      <W>
        <QuickCreateMenu isOpen={false} onClose={vi.fn()} />
      </W>
    );
    expect(container.textContent).not.toContain('Create New');
  });

  it('shows all options when features enabled', () => {
    render(
      <W>
        <QuickCreateMenu isOpen={true} onClose={vi.fn()} />
      </W>
    );
    expect(screen.getByText('New Listing')).toBeTruthy();
    expect(screen.getByText('New Event')).toBeTruthy();
    expect(screen.getByText('New Group')).toBeTruthy();
    expect(screen.getByText('New Goal')).toBeTruthy();
  });

  it('hides event option when events feature disabled', () => {
    mockHasFeature.mockImplementation((feature: string) => feature !== 'events');
    render(
      <W>
        <QuickCreateMenu isOpen={true} onClose={vi.fn()} />
      </W>
    );
    expect(screen.getByText('New Listing')).toBeTruthy();
    expect(screen.queryByText('New Event')).toBeNull();
  });

  it('hides group option when groups feature disabled', () => {
    mockHasFeature.mockImplementation((feature: string) => feature !== 'groups');
    render(
      <W>
        <QuickCreateMenu isOpen={true} onClose={vi.fn()} />
      </W>
    );
    expect(screen.getByText('New Listing')).toBeTruthy();
    expect(screen.queryByText('New Group')).toBeNull();
  });

  it('hides listing option when listings module disabled', () => {
    mockHasModule.mockImplementation((module: string) => module !== 'listings');
    render(
      <W>
        <QuickCreateMenu isOpen={true} onClose={vi.fn()} />
      </W>
    );
    expect(screen.queryByText('New Listing')).toBeNull();
    expect(screen.getByText('New Event')).toBeTruthy();
  });

  it('displays option descriptions', () => {
    render(
      <W>
        <QuickCreateMenu isOpen={true} onClose={vi.fn()} />
      </W>
    );
    expect(screen.getByText('Offer or request a service')).toBeTruthy();
    expect(screen.getByText('Organise a community event')).toBeTruthy();
  });
});
