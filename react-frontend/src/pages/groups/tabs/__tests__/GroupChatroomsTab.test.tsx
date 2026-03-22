// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupChatroomsTab
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
  TeamChatrooms: ({ groupId, isGroupAdmin }: { groupId: number; isGroupAdmin: boolean }) => (
    <div data-testid="team-chatrooms" data-group-id={groupId} data-is-admin={String(isGroupAdmin)} />
  ),
}));

import { GroupChatroomsTab } from '../GroupChatroomsTab';

describe('GroupChatroomsTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<GroupChatroomsTab groupId={1} isGroupAdmin={false} />);
    expect(screen.getByTestId('glass-card')).toBeInTheDocument();
  });

  it('renders TeamChatrooms component inside GlassCard', () => {
    render(<GroupChatroomsTab groupId={5} isGroupAdmin={false} />);
    expect(screen.getByTestId('team-chatrooms')).toBeInTheDocument();
  });

  it('passes groupId to TeamChatrooms', () => {
    render(<GroupChatroomsTab groupId={42} isGroupAdmin={false} />);
    const chatrooms = screen.getByTestId('team-chatrooms');
    expect(chatrooms).toHaveAttribute('data-group-id', '42');
  });

  it('passes isGroupAdmin=true to TeamChatrooms when admin', () => {
    render(<GroupChatroomsTab groupId={1} isGroupAdmin={true} />);
    const chatrooms = screen.getByTestId('team-chatrooms');
    expect(chatrooms).toHaveAttribute('data-is-admin', 'true');
  });

  it('passes isGroupAdmin=false to TeamChatrooms when not admin', () => {
    render(<GroupChatroomsTab groupId={1} isGroupAdmin={false} />);
    const chatrooms = screen.getByTestId('team-chatrooms');
    expect(chatrooms).toHaveAttribute('data-is-admin', 'false');
  });
});
