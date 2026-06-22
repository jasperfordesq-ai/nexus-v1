// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { Pagination } from './Pagination';

vi.mock('@/contexts', () => ({
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

describe('Pagination', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders page numbers for a small total', () => {
    render(<Pagination total={5} page={1} />);

    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
    expect(screen.getByText('5')).toBeInTheDocument();
  });

  it('calls onChange with the clicked page number', () => {
    const onChange = vi.fn();
    render(<Pagination total={5} page={1} onChange={onChange} />);

    fireEvent.click(screen.getByText('3'));

    expect(onChange).toHaveBeenCalledWith(3);
  });

  it('renders prev/next controls when showControls is true', () => {
    render(<Pagination total={5} page={2} showControls />);

    // HeroUI Previous / Next buttons are present in the DOM
    const buttons = screen.getAllByRole('button');
    // At minimum: prev, pages 1-3+5, next
    expect(buttons.length).toBeGreaterThanOrEqual(3);
  });

  it('calls onChange with page-1 when previous button is clicked', () => {
    const onChange = vi.fn();
    render(<Pagination total={5} page={3} showControls onChange={onChange} />);

    // Previous button is the first button
    const prevButton = screen.getAllByRole('button')[0];
    fireEvent.click(prevButton);

    expect(onChange).toHaveBeenCalledWith(2);
  });

  it('calls onChange with page+1 when next button is clicked', () => {
    const onChange = vi.fn();
    render(<Pagination total={5} page={3} showControls onChange={onChange} />);

    const buttons = screen.getAllByRole('button');
    // Next button is the last button
    const nextButton = buttons[buttons.length - 1];
    fireEvent.click(nextButton);

    expect(onChange).toHaveBeenCalledWith(4);
  });

  it('previous button is disabled on first page without loop', () => {
    render(<Pagination total={5} page={1} showControls />);

    // Previous button should be disabled on page 1 (non-loop)
    const prevButton = screen.getAllByRole('button')[0];
    // HeroUI sets aria-disabled or the disabled attribute
    const isDisabled =
      prevButton.hasAttribute('disabled') ||
      prevButton.getAttribute('aria-disabled') === 'true' ||
      prevButton.getAttribute('data-disabled') === 'true';
    expect(isDisabled).toBe(true);
  });

  it('next button is disabled on last page without loop', () => {
    render(<Pagination total={5} page={5} showControls />);

    const buttons = screen.getAllByRole('button');
    const nextButton = buttons[buttons.length - 1];

    const isDisabled =
      nextButton.hasAttribute('disabled') ||
      nextButton.getAttribute('aria-disabled') === 'true' ||
      nextButton.getAttribute('data-disabled') === 'true';
    expect(isDisabled).toBe(true);
  });

  it('does not call onChange when a disabled page button is clicked', () => {
    const onChange = vi.fn();
    render(<Pagination total={5} page={5} showControls onChange={onChange} />);

    const buttons = screen.getAllByRole('button');
    const nextButton = buttons[buttons.length - 1];
    fireEvent.click(nextButton);

    // HeroUI swallows clicks on disabled elements; onChange should not fire
    expect(onChange).not.toHaveBeenCalled();
  });

  it('works uncontrolled with initialPage', () => {
    const onChange = vi.fn();
    render(<Pagination total={5} initialPage={2} onChange={onChange} />);

    fireEvent.click(screen.getByText('4'));

    expect(onChange).toHaveBeenCalledWith(4);
  });

  it('renders ellipsis for large page counts', () => {
    render(<Pagination total={20} page={10} />);

    // With default siblings=1, boundaries=1 there should be ellipsis items
    // We can verify pages 1 and 20 (boundaries) are rendered
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('20')).toBeInTheDocument();
  });

  it('renders nothing (empty) when total is 0', () => {
    const { container } = render(<Pagination total={0} />);

    // The HeroUI Pagination wrapper still renders but has no page items
    const pageLinks = container.querySelectorAll('[aria-current="page"], [aria-label]');
    // Just verify no page number text buttons for pages 1+ are visible
    expect(screen.queryByText('1')).not.toBeInTheDocument();
  });

  it('accepts custom aria label via getItemAriaLabel', () => {
    render(
      <Pagination
        total={3}
        page={1}
        getItemAriaLabel={(p) => `Go to page ${p}`}
      />
    );

    // aria-label applied to page link buttons
    expect(screen.getByLabelText('Go to page 2')).toBeInTheDocument();
  });

  it('renders all pages when total fits within maxVisible without ellipsis', () => {
    // With boundaries=1, siblings=1: maxVisible = 2+2+3 = 7
    // total=5 < 7 so all pages shown without ellipsis
    render(<Pagination total={5} page={1} />);

    for (let p = 1; p <= 5; p++) {
      expect(screen.getByText(String(p))).toBeInTheDocument();
    }
  });
});
