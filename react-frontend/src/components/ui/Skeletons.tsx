// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Reusable Skeleton Components
 * Uses HeroUI Skeleton for consistent loading states
 */

import { Skeleton } from '@heroui/react';

/**
 * Skeleton for listing grid cards — matches ListingCard grid layout
 */
export function ListingSkeleton() {
  return (
    <div className="rounded-xl bg-theme-elevated overflow-hidden">
      {/* Image area with shimmer sweep */}
      <div className="h-36 bg-theme-hover relative overflow-hidden">
        <div className="absolute inset-0 -translate-x-full animate-[shimmer_1.8s_ease-in-out_infinite] bg-gradient-to-r from-transparent via-white/10 to-transparent" />
      </div>
      <div className="p-5">
        <div className="flex gap-2 mb-3">
          <Skeleton className="h-5 w-16 rounded-full" />
          <Skeleton className="h-5 w-12 rounded-full" />
        </div>
        <Skeleton className="h-5 w-3/4 rounded-lg mb-2" />
        <Skeleton className="h-4 w-full rounded-lg mb-1.5" />
        <Skeleton className="h-4 w-2/3 rounded-lg mb-4" />
        <div className="flex items-center justify-between pt-3 border-t border-theme-default">
          <div className="flex items-center gap-2">
            <Skeleton className="h-6 w-6 rounded-full" />
            <Skeleton className="h-4 w-20 rounded-lg" />
          </div>
          <Skeleton className="h-4 w-12 rounded-lg" />
        </div>
      </div>
    </div>
  );
}

/**
 * Skeleton for member grid cards — matches MemberCard grid layout
 */
export function MemberCardSkeleton() {
  return (
    <div className="p-5 rounded-xl bg-theme-elevated text-center">
      <Skeleton className="h-20 w-20 rounded-full mx-auto mb-3" />
      <Skeleton className="h-4 w-28 rounded-lg mx-auto mb-2" />
      <Skeleton className="h-3 w-36 rounded-lg mx-auto mb-4" />
      <div className="flex justify-center gap-3">
        <Skeleton className="h-6 w-14 rounded-full" />
        <Skeleton className="h-6 w-12 rounded-full" />
      </div>
    </div>
  );
}

/**
 * Skeleton for stat cards on dashboard
 */
export function StatCardSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated">
      <Skeleton className="h-10 w-10 rounded-lg mb-3" />
      <Skeleton className="h-3 w-16 rounded mb-2" />
      <Skeleton className="h-8 w-12 rounded" />
    </div>
  );
}

/**
 * Skeleton for event cards
 */
export function EventCardSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated flex gap-4">
      <div className="flex-shrink-0 w-14 text-center">
        <Skeleton className="h-5 w-10 mx-auto rounded mb-1" />
        <Skeleton className="h-7 w-8 mx-auto rounded" />
      </div>
      <div className="flex-1 min-w-0">
        <Skeleton className="h-5 w-3/4 rounded mb-2" />
        <Skeleton className="h-4 w-1/2 rounded mb-2" />
        <Skeleton className="h-4 w-1/3 rounded" />
      </div>
    </div>
  );
}

/**
 * Skeleton for group cards
 */
export function GroupCardSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated">
      <div className="flex items-start gap-4 mb-3">
        <Skeleton className="h-12 w-12 rounded-lg flex-shrink-0" />
        <div className="flex-1 min-w-0">
          <Skeleton className="h-5 w-2/3 rounded mb-2" />
          <Skeleton className="h-4 w-1/3 rounded" />
        </div>
      </div>
      <Skeleton className="h-4 w-full rounded mb-2" />
      <Skeleton className="h-4 w-3/4 rounded" />
    </div>
  );
}

/**
 * Skeleton for conversation list items in messages
 */
export function ConversationSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated flex items-center gap-4">
      <Skeleton className="h-12 w-12 rounded-full flex-shrink-0" />
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between mb-2">
          <Skeleton className="h-4 w-24 rounded" />
          <Skeleton className="h-3 w-12 rounded" />
        </div>
        <Skeleton className="h-3 w-3/4 rounded" />
      </div>
    </div>
  );
}

/**
 * Skeleton for exchange cards
 */
export function ExchangeCardSkeleton() {
  return (
    <div className="p-4 sm:p-6 rounded-lg bg-theme-elevated">
      <div className="flex flex-col sm:flex-row sm:items-center gap-4">
        <Skeleton className="h-12 w-12 rounded-full flex-shrink-0" />
        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-center gap-2 mb-2">
            <Skeleton className="h-5 w-40 rounded" />
            <Skeleton className="h-5 w-24 rounded-full" />
          </div>
          <div className="flex flex-wrap items-center gap-4">
            <Skeleton className="h-4 w-20 rounded" />
            <Skeleton className="h-4 w-16 rounded" />
            <Skeleton className="h-4 w-24 rounded" />
          </div>
        </div>
      </div>
    </div>
  );
}

/**
 * Skeleton for notification items
 */
export function NotificationSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated flex items-start gap-3">
      <Skeleton className="h-10 w-10 rounded-full flex-shrink-0" />
      <div className="flex-1 min-w-0">
        <Skeleton className="h-4 w-full rounded mb-2" />
        <Skeleton className="h-3 w-2/3 rounded mb-2" />
        <Skeleton className="h-3 w-20 rounded" />
      </div>
    </div>
  );
}

/**
 * Skeleton for profile page header
 */
export function ProfileHeaderSkeleton() {
  return (
    <div className="p-6 rounded-lg bg-theme-elevated">
      <div className="flex flex-col sm:flex-row items-center gap-6">
        <Skeleton className="h-24 w-24 rounded-full" />
        <div className="flex-1 text-center sm:text-left">
          <Skeleton className="h-7 w-48 rounded mb-2 mx-auto sm:mx-0" />
          <Skeleton className="h-4 w-32 rounded mb-2 mx-auto sm:mx-0" />
          <Skeleton className="h-4 w-64 rounded mx-auto sm:mx-0" />
        </div>
      </div>
    </div>
  );
}

/**
 * Skeleton for message list items
 */
export function MessageListSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated flex items-center gap-3">
      <Skeleton className="h-11 w-11 rounded-full flex-shrink-0" />
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between mb-2">
          <Skeleton className="h-4 w-28 rounded" />
          <Skeleton className="h-3 w-10 rounded" />
        </div>
        <Skeleton className="h-3 w-3/4 rounded" />
      </div>
    </div>
  );
}

/**
 * Skeleton for profile cards
 */
export function ProfileCardSkeleton() {
  return (
    <div className="p-6 rounded-lg bg-theme-elevated flex flex-col items-center gap-4">
      <Skeleton className="h-20 w-20 rounded-full" />
      <Skeleton className="h-5 w-32 rounded" />
      <div className="flex items-center gap-4 w-full justify-center">
        <Skeleton className="h-10 w-16 rounded-lg" />
        <Skeleton className="h-10 w-16 rounded-lg" />
        <Skeleton className="h-10 w-16 rounded-lg" />
      </div>
      <div className="w-full space-y-2">
        <Skeleton className="h-3 w-full rounded" />
        <Skeleton className="h-3 w-4/5 rounded" />
      </div>
    </div>
  );
}

/**
 * Generic list of skeletons helper
 */
export function SkeletonList({
  count = 3,
  children,
}: {
  count?: number;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-3">
      {Array.from({ length: count }).map((_, i) => (
        <div key={i}>{children}</div>
      ))}
    </div>
  );
}
