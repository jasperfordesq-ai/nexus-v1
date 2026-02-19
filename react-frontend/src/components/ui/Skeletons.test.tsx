// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { render } from '@/test/test-utils';
import {
  ListingSkeleton,
  MemberCardSkeleton,
  StatCardSkeleton,
  EventCardSkeleton,
  GroupCardSkeleton,
  ConversationSkeleton,
  ExchangeCardSkeleton,
  NotificationSkeleton,
  ProfileHeaderSkeleton,
  SkeletonList,
} from './Skeletons';

describe('Skeleton Components', () => {
  it('renders ListingSkeleton without crashing', () => {
    const { container } = render(<ListingSkeleton />);
    expect(container.firstChild).toBeInTheDocument();
  });

  it('renders MemberCardSkeleton without crashing', () => {
    const { container } = render(<MemberCardSkeleton />);
    expect(container.firstChild).toBeInTheDocument();
  });

  it('renders StatCardSkeleton without crashing', () => {
    const { container } = render(<StatCardSkeleton />);
    expect(container.firstChild).toBeInTheDocument();
  });

  it('renders EventCardSkeleton without crashing', () => {
    const { container } = render(<EventCardSkeleton />);
    expect(container.firstChild).toBeInTheDocument();
  });

  it('renders GroupCardSkeleton without crashing', () => {
    const { container } = render(<GroupCardSkeleton />);
    expect(container.firstChild).toBeInTheDocument();
  });

  it('renders ConversationSkeleton without crashing', () => {
    const { container } = render(<ConversationSkeleton />);
    expect(container.firstChild).toBeInTheDocument();
  });

  it('renders ExchangeCardSkeleton without crashing', () => {
    const { container } = render(<ExchangeCardSkeleton />);
    expect(container.firstChild).toBeInTheDocument();
  });

  it('renders NotificationSkeleton without crashing', () => {
    const { container } = render(<NotificationSkeleton />);
    expect(container.firstChild).toBeInTheDocument();
  });

  it('renders ProfileHeaderSkeleton without crashing', () => {
    const { container } = render(<ProfileHeaderSkeleton />);
    expect(container.firstChild).toBeInTheDocument();
  });

  it('renders SkeletonList with correct count', () => {
    const { container } = render(
      <SkeletonList count={4}>
        <div data-testid="child">Skeleton</div>
      </SkeletonList>
    );
    const children = container.querySelectorAll('[data-testid="child"]');
    expect(children).toHaveLength(4);
  });

  it('renders SkeletonList with default count of 3', () => {
    const { container } = render(
      <SkeletonList>
        <div data-testid="child">Skeleton</div>
      </SkeletonList>
    );
    const children = container.querySelectorAll('[data-testid="child"]');
    expect(children).toHaveLength(3);
  });
});
