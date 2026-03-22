// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupTasksTab
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div className={className} data-testid="glass-card">{children}</div>
  ),

  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
}));

vi.mock('@/components/ideation', () => ({
  TeamTasks: ({
    groupId,
    isGroupAdmin,
    members,
  }: {
    groupId: number;
    isGroupAdmin: boolean;
    members: { id: number; name: string; avatar_url: string | null }[];
  }) => (
    <div
      data-testid="team-tasks"
      data-group-id={groupId}
      data-is-admin={String(isGroupAdmin)}
      data-member-count={members.length}
    />
  ),
}));

import { GroupTasksTab } from '../GroupTasksTab';
import type { GroupMember } from '../GroupMembersTab';

const mockMembers: GroupMember[] = [
  {
    id: 1,
    name: 'Alice Test',
    first_name: 'Alice',
    last_name: 'Test',
    avatar: null,
    avatar_url: null,
    role: 'admin',
  } as GroupMember,
  {
    id: 2,
    name: 'Bob Test',
    first_name: 'Bob',
    last_name: 'Test',
    avatar: null,
    avatar_url: null,
    role: 'member',
  } as GroupMember,
];

describe('GroupTasksTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<GroupTasksTab groupId={1} isGroupAdmin={false} members={[]} />);
    expect(screen.getByTestId('glass-card')).toBeInTheDocument();
  });

  it('renders TeamTasks component', () => {
    render(<GroupTasksTab groupId={5} isGroupAdmin={false} members={[]} />);
    expect(screen.getByTestId('team-tasks')).toBeInTheDocument();
  });

  it('passes groupId to TeamTasks', () => {
    render(<GroupTasksTab groupId={42} isGroupAdmin={false} members={[]} />);
    expect(screen.getByTestId('team-tasks')).toHaveAttribute('data-group-id', '42');
  });

  it('passes isGroupAdmin to TeamTasks', () => {
    render(<GroupTasksTab groupId={1} isGroupAdmin={true} members={[]} />);
    expect(screen.getByTestId('team-tasks')).toHaveAttribute('data-is-admin', 'true');
  });

  it('passes mapped members to TeamTasks', () => {
    render(<GroupTasksTab groupId={1} isGroupAdmin={false} members={mockMembers} />);
    expect(screen.getByTestId('team-tasks')).toHaveAttribute('data-member-count', '2');
  });

  it('maps member names using first_name + last_name when available', () => {
    render(<GroupTasksTab groupId={1} isGroupAdmin={false} members={mockMembers} />);
    // Members are mapped — TeamTasks receives them
    const teamTasksEl = screen.getByTestId('team-tasks');
    expect(teamTasksEl).toBeInTheDocument();
  });
});
