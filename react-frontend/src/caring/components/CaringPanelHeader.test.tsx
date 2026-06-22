// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

const mockNavigate = vi.fn();

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return { ...actual, useNavigate: () => mockNavigate };
});

const mockLogout = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice Member', avatar_url: null, avatar: null },
      isAuthenticated: true,
      login: vi.fn(),
      logout: mockLogout,
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Timebank', slug: 'test' },
      tenantSlug: 'test',
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn(() => null),
  resolveAssetUrl: vi.fn(() => null),
  cn: (...classes: (string | undefined | null | false)[]) => classes.filter(Boolean).join(' '),
}));

import { CaringPanelHeader } from './CaringPanelHeader';

describe('CaringPanelHeader', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a header element', () => {
    render(<CaringPanelHeader sidebarCollapsed={false} />);
    expect(screen.getByRole('banner')).toBeInTheDocument();
  });

  it('renders the tenant name', () => {
    render(<CaringPanelHeader sidebarCollapsed={false} />);
    expect(screen.getByText('Test Timebank')).toBeInTheDocument();
  });

  it('renders back-to-site button', () => {
    render(<CaringPanelHeader sidebarCollapsed={false} />);
    const backBtn = screen.getByRole('button', { name: /back.to.site/i });
    expect(backBtn).toBeInTheDocument();
  });

  it('navigates to dashboard when back button is pressed', () => {
    render(<CaringPanelHeader sidebarCollapsed={false} />);
    const backBtn = screen.getByRole('button', { name: /back.to.site/i });
    fireEvent.click(backBtn);
    expect(mockNavigate).toHaveBeenCalledWith('/test/dashboard');
  });

  it('renders notifications button', () => {
    render(<CaringPanelHeader sidebarCollapsed={false} />);
    const notifBtn = screen.getByRole('button', { name: /notifications/i });
    expect(notifBtn).toBeInTheDocument();
  });

  it('navigates to notifications when notifications button is pressed', () => {
    render(<CaringPanelHeader sidebarCollapsed={false} />);
    const notifBtn = screen.getByRole('button', { name: /notifications/i });
    fireEvent.click(notifBtn);
    expect(mockNavigate).toHaveBeenCalledWith('/test/notifications');
  });

  it('renders user menu trigger with logged-in user name', () => {
    render(<CaringPanelHeader sidebarCollapsed={false} />);
    // User name appears in the button or nearby; look for text
    expect(screen.getByText('Alice Member')).toBeInTheDocument();
  });

  it('renders sidebar toggle button when onSidebarToggle is provided', () => {
    const onSidebarToggle = vi.fn();
    render(
      <CaringPanelHeader sidebarCollapsed={false} onSidebarToggle={onSidebarToggle} />,
    );
    const toggleBtn = screen.getByRole('button', { name: /toggle.sidebar/i });
    expect(toggleBtn).toBeInTheDocument();
  });

  it('calls onSidebarToggle when toggle button is pressed', () => {
    const onSidebarToggle = vi.fn();
    render(
      <CaringPanelHeader sidebarCollapsed={false} onSidebarToggle={onSidebarToggle} />,
    );
    const toggleBtn = screen.getByRole('button', { name: /toggle.sidebar/i });
    fireEvent.click(toggleBtn);
    expect(onSidebarToggle).toHaveBeenCalledTimes(1);
  });

  it('does not render sidebar toggle button when onSidebarToggle is not provided', () => {
    render(<CaringPanelHeader sidebarCollapsed={false} />);
    const toggleBtn = screen.queryByRole('button', { name: /toggle.sidebar/i });
    expect(toggleBtn).not.toBeInTheDocument();
  });

  // NOTE: The user dropdown (My Profile / Sign Out) uses a HeroUI Dropdown that
  // renders into a portal and requires the trigger to be pressed first.
  // Testing dropdown item actions is deferred — those items are inside portals
  // and HeroUI's Dropdown uses floating UI positioning requiring browser layout.
  // The logout flow is tested at the integration level.
});
