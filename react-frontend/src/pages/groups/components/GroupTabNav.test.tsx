// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen, userEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

const disabledTabs = new Set<string>();
let eventsFeatureEnabled = true;

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (path: string) => `/test${path}`,
      hasFeature: vi.fn((key: string) => key !== 'events' || eventsFeatureEnabled),
      hasModule: vi.fn(() => true),
      hasGroupTab: (key: string) => !disabledTabs.has(key),
    }),
  }),
);

import { GroupTabNav } from './GroupTabNav';

const DEFAULT_PROPS = {
  activeTab: 'feed' as const,
  userIsAdmin: false,
  userIsMember: true,
  hasSubGroups: false,
  subGroupCount: 0,
  onTabChange: vi.fn(),
  children: <div>Selected section content</div>,
};

describe('GroupTabNav', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    disabledTabs.clear();
    eventsFeatureEnabled = true;
  });

  it('renders an associated HeroUI tab list, tabs, and selected panel', () => {
    render(<GroupTabNav {...DEFAULT_PROPS} />);

    const tablist = screen.getByRole('tablist', { name: 'Group navigation' });
    expect(tablist).toBeInTheDocument();
    expect(tablist.closest('div.sticky')).toHaveClass(
      'top-[calc(var(--app-header-desktop-offset,5.5rem)+0.75rem)]',
    );
    expect(screen.getByRole('tab', { name: 'Feed' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tabpanel')).toHaveTextContent('Selected section content');
  });

  it('changes section through native tab selection', async () => {
    const onTabChange = vi.fn();
    render(<GroupTabNav {...DEFAULT_PROPS} onTabChange={onTabChange} />);

    await userEvent.click(screen.getByRole('tab', { name: 'Members' }));
    expect(onTabChange).toHaveBeenCalledWith('members');
  });

  it('supports keyboard arrow selection', () => {
    const onTabChange = vi.fn();
    render(<GroupTabNav {...DEFAULT_PROPS} onTabChange={onTabChange} />);

    const feedTab = screen.getByRole('tab', { name: 'Feed' });
    act(() => {
      feedTab.focus();
      fireEvent.keyDown(feedTab, { key: 'ArrowRight' });
    });
    expect(onTabChange).toHaveBeenCalledWith('discussion');
  });

  it('shows the selected section label in the separate mobile dropdown trigger', () => {
    render(<GroupTabNav {...DEFAULT_PROPS} activeTab="discussion" />);

    expect(screen.getByRole('button', { name: 'Group navigation: Discussion' })).toHaveTextContent('Discussion');
  });

  it('shows configured subgroups as a real tab', () => {
    render(<GroupTabNav {...DEFAULT_PROPS} hasSubGroups subGroupCount={3} />);

    expect(screen.getByRole('tab', { name: 'Subgroups (3)' })).toBeInTheDocument();
  });

  it('honours tenant configuration for subgroups and feed', () => {
    disabledTabs.add('tab_subgroups');
    disabledTabs.add('tab_feed');
    render(<GroupTabNav {...DEFAULT_PROPS} hasSubGroups subGroupCount={3} activeTab="discussion" />);

    expect(screen.queryByRole('tab', { name: 'Subgroups (3)' })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Feed' })).not.toBeInTheDocument();
  });

  it('does not expose member-only sections to a nonmember', () => {
    render(<GroupTabNav {...DEFAULT_PROPS} userIsMember={false} />);

    expect(screen.getByRole('tab', { name: 'Feed' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Discussion' })).toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Members' })).not.toBeInTheDocument();
  });

  it('shows analytics only to group admins', () => {
    const { rerender } = render(<GroupTabNav {...DEFAULT_PROPS} userIsAdmin={false} />);
    expect(screen.queryByRole('tab', { name: 'Analytics' })).not.toBeInTheDocument();

    rerender(<GroupTabNav {...DEFAULT_PROPS} userIsAdmin />);
    expect(screen.getByRole('tab', { name: 'Analytics' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Automation' })).toBeInTheDocument();
  });

  it('does not expose Events when the tenant Events feature is disabled', () => {
    eventsFeatureEnabled = false;
    render(<GroupTabNav {...DEFAULT_PROPS} />);

    expect(screen.queryByRole('tab', { name: 'Events' })).not.toBeInTheDocument();
  });
});
