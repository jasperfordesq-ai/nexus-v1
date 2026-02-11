/**
 * Reusable Skeleton Components
 * Uses HeroUI Skeleton for consistent loading states
 */

import { Skeleton } from '@heroui/react';

/**
 * Skeleton for listing cards in lists
 */
export function ListingSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated">
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1">
          <Skeleton className="h-5 w-3/4 rounded mb-2" />
          <Skeleton className="h-4 w-full rounded mb-2" />
          <Skeleton className="h-4 w-1/2 rounded" />
        </div>
        <Skeleton className="h-6 w-20 rounded-full" />
      </div>
    </div>
  );
}

/**
 * Skeleton for member cards with avatar
 */
export function MemberCardSkeleton() {
  return (
    <div className="p-4 rounded-lg bg-theme-elevated flex items-center gap-4">
      <Skeleton className="h-12 w-12 rounded-full flex-shrink-0" />
      <div className="flex-1 min-w-0">
        <Skeleton className="h-4 w-24 rounded mb-2" />
        <Skeleton className="h-3 w-32 rounded" />
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
