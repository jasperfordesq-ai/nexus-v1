// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeedSkeleton — Enhanced loading placeholder for feed cards.
 *
 * Shared across FeedPage, HashtagPage, and PostDetailPage.
 * Uses HeroUI Skeleton with shimmer animation.
 *
 * Supports per-type variants ("poll", "event", "review", "milestone") so the
 * skeleton shape roughly matches the loaded card — reduces layout shift.
 */

import { Skeleton, Divider } from '@heroui/react';
import { GlassCard } from '@/components/ui';

type SkeletonVariant =
  | 'with-image'
  | 'text-only'
  | 'random'
  | 'poll'
  | 'event'
  | 'review'
  | 'milestone';

// Stable per-index seed so random variants don't shift on re-render
const variantForIndex = (index: number): boolean => index % 3 !== 2; // ~66% show image

interface FeedSkeletonProps {
  variant?: SkeletonVariant;
  /** Index in the skeleton list — used for deterministic "random" variant */
  index?: number;
}

export function FeedSkeleton({ variant = 'random', index = 0 }: FeedSkeletonProps) {
  // Typed variants render a dedicated shape
  if (variant === 'poll') return <PollSkeleton />;
  if (variant === 'event') return <EventSkeleton />;
  if (variant === 'review') return <ReviewSkeleton />;
  if (variant === 'milestone') return <MilestoneSkeleton />;

  const showImage = variant === 'with-image' || (variant === 'random' ? variantForIndex(index) : false);
  const lineCount = variant === 'random' ? (index % 2 === 0 ? 3 : 2) : 3;

  return (
    <GlassCard className="overflow-hidden">
      <div className="p-5">
        {/* Header: Avatar + Name + Timestamp */}
        <div className="flex items-center gap-3 mb-4">
          <Skeleton className="w-10 h-10 rounded-full flex-shrink-0" />
          <div className="flex-1 min-w-0">
            <Skeleton className="h-4 w-28 rounded-lg mb-2" />
            <Skeleton className="h-3 w-20 rounded-lg" />
          </div>
        </div>

        {/* Content lines (varying widths) */}
        <div className="space-y-2 mb-4">
          <Skeleton className="h-4 w-full rounded-lg" />
          <Skeleton className="h-4 w-4/5 rounded-lg" />
          {lineCount >= 3 && <Skeleton className="h-4 w-3/5 rounded-lg" />}
        </div>
      </div>

      {/* Image placeholder (16:9 aspect ratio) — conditional */}
      {showImage && <Skeleton className="w-full aspect-video rounded-none" />}

      <div className="p-5 pt-4">
        {/* Divider */}
        <Divider className="mb-3" />

        {/* Action bar: reactions+comment (primary) + share+bookmark (secondary) */}
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <Skeleton className="h-8 w-20 rounded-lg" />
            <Skeleton className="h-8 w-24 rounded-lg" />
          </div>
          <div className="flex items-center gap-1">
            <Skeleton className="h-8 w-8 rounded-lg" />
            <Skeleton className="h-8 w-8 rounded-lg" />
          </div>
        </div>
      </div>
    </GlassCard>
  );
}

function CardShell({ accentClass, children }: { accentClass: string; children: React.ReactNode }) {
  return (
    <GlassCard className="overflow-hidden">
      <div className={`h-1 bg-gradient-to-r ${accentClass}`} aria-hidden="true" />
      {children}
    </GlassCard>
  );
}

function FooterSkeleton() {
  return (
    <div className="p-5 pt-4">
      <Divider className="mb-3" />
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <Skeleton className="h-8 w-20 rounded-lg" />
          <Skeleton className="h-8 w-24 rounded-lg" />
        </div>
        <div className="flex items-center gap-1">
          <Skeleton className="h-8 w-8 rounded-lg" />
          <Skeleton className="h-8 w-8 rounded-lg" />
        </div>
      </div>
    </div>
  );
}

function HeaderSkeleton() {
  return (
    <div className="flex items-center gap-3 mb-4">
      <Skeleton className="w-10 h-10 rounded-full flex-shrink-0" />
      <div className="flex-1 min-w-0">
        <Skeleton className="h-4 w-28 rounded-lg mb-2" />
        <Skeleton className="h-3 w-20 rounded-lg" />
      </div>
    </div>
  );
}

function PollSkeleton() {
  return (
    <CardShell accentClass="from-amber-500 via-orange-500 to-amber-500">
      <div className="p-5">
        <HeaderSkeleton />
        <div className="rounded-2xl border border-[var(--border-default)] bg-[var(--surface-elevated)] overflow-hidden mb-4">
          <div className="h-1 bg-gradient-to-r from-amber-500/40 via-orange-500/40 to-amber-500/40" />
          <div className="p-5 space-y-3">
            <Skeleton className="h-6 w-3/4 rounded-lg" />
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-12 w-full rounded-xl" />
            ))}
          </div>
        </div>
      </div>
      <FooterSkeleton />
    </CardShell>
  );
}

function EventSkeleton() {
  return (
    <CardShell accentClass="from-emerald-500 via-green-500 to-emerald-500">
      <div className="p-5">
        <HeaderSkeleton />
        <div className="space-y-2 mb-4">
          <Skeleton className="h-4 w-full rounded-lg" />
          <Skeleton className="h-4 w-4/5 rounded-lg" />
        </div>
        {/* Date chip + countdown + location */}
        <div className="flex items-center gap-3 mb-4">
          <Skeleton className="w-11 h-12 rounded-lg flex-shrink-0" />
          <div className="flex-1 space-y-1.5">
            <Skeleton className="h-3 w-40 rounded-lg" />
            <Skeleton className="h-3 w-24 rounded-lg" />
          </div>
          <Skeleton className="h-6 w-24 rounded-full" />
        </div>
      </div>
      <Skeleton className="w-full aspect-video rounded-none" />
      <FooterSkeleton />
    </CardShell>
  );
}

function ReviewSkeleton() {
  return (
    <CardShell accentClass="from-amber-500 via-yellow-500 to-amber-500">
      <div className="p-5">
        <HeaderSkeleton />
        <div className="rounded-xl border border-amber-500/20 bg-gradient-to-br from-amber-500/10 to-orange-500/10 p-5 mb-4">
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2.5">
              <Skeleton className="w-8 h-8 rounded-full" />
              <div className="space-y-1">
                <Skeleton className="h-2.5 w-16 rounded" />
                <Skeleton className="h-3 w-24 rounded" />
              </div>
            </div>
            <div className="flex gap-1">
              {[1, 2, 3, 4, 5].map((i) => (
                <Skeleton key={i} className="w-6 h-6 rounded" />
              ))}
            </div>
          </div>
          <div className="ps-4 border-s-4 border-amber-500/30 space-y-2">
            <Skeleton className="h-4 w-full rounded" />
            <Skeleton className="h-4 w-5/6 rounded" />
          </div>
        </div>
      </div>
      <FooterSkeleton />
    </CardShell>
  );
}

function MilestoneSkeleton() {
  return (
    <CardShell accentClass="from-yellow-500 via-amber-500 to-yellow-500">
      <div className="p-5">
        <HeaderSkeleton />
        <div className="rounded-xl border border-yellow-500/30 bg-gradient-to-br from-yellow-500/20 via-amber-500/15 to-orange-500/10 p-6 text-center mb-4">
          <Skeleton className="mx-auto w-20 h-20 rounded-full mb-3" />
          <Skeleton className="mx-auto h-3 w-24 rounded-lg mb-2" />
          <Skeleton className="mx-auto h-5 w-3/5 rounded-lg" />
        </div>
      </div>
      <FooterSkeleton />
    </CardShell>
  );
}
