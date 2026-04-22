// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * UserHoverCard — Shows a popup card with quick info and actions
 * when hovering over a username or avatar.
 *
 * On touch devices, hover cards are disabled and the default
 * tap-to-navigate behavior is preserved.
 */

import { useState, useCallback, useRef, useEffect, memo } from 'react';
import { Link } from 'react-router-dom';
import {
  Popover,
  PopoverTrigger,
  PopoverContent,
  Avatar,
  Button,
  Chip,
  Skeleton,
} from '@heroui/react';
import UserPlus from 'lucide-react/icons/user-plus';
import UserCheck from 'lucide-react/icons/user-check';
import MessageCircle from 'lucide-react/icons/message-circle';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';
import { logError } from '@/lib/logger';
import { PresenceIndicator } from './PresenceIndicator';

/* ─── Types ────────────────────────────────────────────────── */

interface UserHoverCardProps {
  userId: number;
  children: React.ReactNode;
}

interface HoverCardData {
  id: number;
  name?: string;
  first_name?: string;
  avatar?: string | null;
  avatar_url?: string | null;
  bio?: string;
  tagline?: string;
  is_verified?: boolean;
  skills?: string[];
  interests?: string[];
  stats?: {
    total_hours_given?: number;
    connections_count?: number;
    listings_count?: number;
  };
  connection_status?: 'none' | 'pending' | 'connected';
}

/* ─── Cache ────────────────────────────────────────────────── */

const userCache = new Map<number, HoverCardData>();

/* ─── Touch device detection ───────────────────────────────── */

function isTouchDevice(): boolean {
  if (typeof window === 'undefined') return true;
  return 'ontouchstart' in window || !window.matchMedia('(hover: hover)').matches;
}

/* ─── Component ────────────────────────────────────────────── */

export const UserHoverCard = memo(function UserHoverCard({
  userId,
  children,
}: UserHoverCardProps) {
  const { t } = useTranslation('social');
  const { tenantPath } = useTenant();

  const [isOpen, setIsOpen] = useState(false);
  const [userData, setUserData] = useState<HoverCardData | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isConnecting, setIsConnecting] = useState(false);
  const hoverTimeoutRef = useRef<ReturnType<typeof setTimeout>>();
  const leaveTimeoutRef = useRef<ReturnType<typeof setTimeout>>();

  // Don't render hover card on touch devices
  const isTouch = useRef(isTouchDevice());

  const fetchUserData = useCallback(async () => {
    // Return cached data
    if (userCache.has(userId)) {
      setUserData(userCache.get(userId)!);
      return;
    }

    setIsLoading(true);
    try {
      const response = await api.get<HoverCardData>(`/v2/users/${userId}`);
      if (response.success && response.data) {
        userCache.set(userId, response.data);
        setUserData(response.data);
      }
    } catch (err) {
      logError('Failed to fetch user data for hover card', err);
    } finally {
      setIsLoading(false);
    }
  }, [userId]);

  const handleMouseEnter = useCallback(() => {
    if (leaveTimeoutRef.current) {
      clearTimeout(leaveTimeoutRef.current);
      leaveTimeoutRef.current = undefined;
    }
    hoverTimeoutRef.current = setTimeout(() => {
      setIsOpen(true);
      fetchUserData();
    }, 300);
  }, [fetchUserData]);

  const handleMouseLeave = useCallback(() => {
    if (hoverTimeoutRef.current) {
      clearTimeout(hoverTimeoutRef.current);
      hoverTimeoutRef.current = undefined;
    }
    leaveTimeoutRef.current = setTimeout(() => {
      setIsOpen(false);
    }, 200);
  }, []);

  const handlePopoverMouseEnter = useCallback(() => {
    if (leaveTimeoutRef.current) {
      clearTimeout(leaveTimeoutRef.current);
      leaveTimeoutRef.current = undefined;
    }
  }, []);

  const handlePopoverMouseLeave = useCallback(() => {
    leaveTimeoutRef.current = setTimeout(() => {
      setIsOpen(false);
    }, 200);
  }, []);

  useEffect(() => {
    return () => {
      if (hoverTimeoutRef.current) clearTimeout(hoverTimeoutRef.current);
      if (leaveTimeoutRef.current) clearTimeout(leaveTimeoutRef.current);
    };
  }, []);

  const handleConnect = useCallback(async () => {
    if (!userData || isConnecting) return;
    setIsConnecting(true);
    try {
      await api.post('/v2/connections/request', { user_id: userId });
      // Optimistic update
      const updated = { ...userData, connection_status: 'pending' as const };
      setUserData(updated);
      userCache.set(userId, updated);
    } catch (err) {
      logError('Failed to send connection request from hover card', err);
    } finally {
      setIsConnecting(false);
    }
  }, [userData, userId, isConnecting]);

  // On touch devices, just render children without hover card
  if (isTouch.current) {
    return <>{children}</>;
  }

  const displayName = userData?.name || userData?.first_name || '';
  const avatarSrc = resolveAvatarUrl(userData?.avatar_url || userData?.avatar);
  const skills = userData?.skills?.slice(0, 4) || [];
  const hoursGiven = userData?.stats?.total_hours_given ?? 0;
  const connectionsCount = userData?.stats?.connections_count ?? 0;
  const listingsCount = userData?.stats?.listings_count ?? 0;

  return (
    <Popover
      placement="bottom-start"
      isOpen={isOpen}
      onOpenChange={(open) => {
        if (!open) {
          leaveTimeoutRef.current = setTimeout(() => setIsOpen(false), 200);
        }
      }}
      shouldBlockScroll={false}
      backdrop="transparent"
      offset={8}
      showArrow
    >
      <PopoverTrigger>
        <span
          onMouseEnter={handleMouseEnter}
          onMouseLeave={handleMouseLeave}
          className="inline-flex"
        >
          {children}
        </span>
      </PopoverTrigger>
      <PopoverContent
        className="p-0 bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-2xl rounded-xl w-[280px]"
        onMouseEnter={handlePopoverMouseEnter}
        onMouseLeave={handlePopoverMouseLeave}
      >
        {isLoading && !userData ? (
          <div className="p-4 space-y-3">
            <div className="flex items-center gap-3">
              <Skeleton className="w-12 h-12 rounded-full" />
              <div className="space-y-1.5">
                <Skeleton className="h-3.5 w-24 rounded" />
                <Skeleton className="h-3 w-16 rounded" />
              </div>
            </div>
            <Skeleton className="h-3 w-full rounded" />
            <Skeleton className="h-3 w-3/4 rounded" />
          </div>
        ) : userData ? (
          <div className="p-4 space-y-3">
            {/* User info */}
            <div className="flex items-center gap-3">
              <Link to={tenantPath(`/profile/${userData.id}`)} className="relative flex-shrink-0">
                <Avatar
                  name={displayName}
                  src={avatarSrc}
                  size="lg"
                  className="ring-2 ring-[var(--border-default)]"
                />
                <PresenceIndicator userId={userData.id} size="md" />
              </Link>
              <div className="min-w-0">
                <Link
                  to={tenantPath(`/profile/${userData.id}`)}
                  className="font-semibold text-sm text-[var(--text-primary)] hover:text-[var(--color-primary)] transition-colors truncate block"
                >
                  {displayName}
                  {userData.is_verified && (
                    <span className="ml-1 text-[var(--color-primary)]" title={t('verified', 'Verified')}>
                      &#10003;
                    </span>
                  )}
                </Link>
                {userData.tagline && (
                  <p className="text-xs text-[var(--text-muted)] truncate">{userData.tagline}</p>
                )}
              </div>
            </div>

            {/* Bio */}
            {userData.bio && (
              <p className="text-xs text-[var(--text-secondary)] line-clamp-2">
                {userData.bio}
              </p>
            )}

            {/* Stats */}
            <div className="flex items-center gap-3 text-xs text-[var(--text-muted)]">
              <span>{t('hover_card.hours_given', '{{count}} hrs given', { count: hoursGiven })}</span>
              <span className="text-[var(--border-default)]">|</span>
              <span>{t('hover_card.connections', '{{count}} connections', { count: connectionsCount })}</span>
              <span className="text-[var(--border-default)]">|</span>
              <span>{t('hover_card.listings', '{{count}} listings', { count: listingsCount })}</span>
            </div>

            {/* Skills */}
            {skills.length > 0 && (
              <div className="flex flex-wrap gap-1">
                {skills.map((skill) => (
                  <Chip
                    key={skill}
                    size="sm"
                    variant="flat"
                    className="text-[10px] h-5 bg-[var(--surface-elevated)] text-[var(--text-muted)]"
                  >
                    {skill}
                  </Chip>
                ))}
              </div>
            )}

            {/* Actions */}
            <div className="flex gap-2 pt-1">
              {userData.connection_status === 'connected' ? (
                <Button
                  size="sm"
                  variant="flat"
                  className="flex-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                  startContent={<UserCheck className="w-3.5 h-3.5" />}
                  isDisabled
                >
                  {t('hover_card.connected', 'Connected')}
                </Button>
              ) : userData.connection_status === 'pending' ? (
                <Button
                  size="sm"
                  variant="flat"
                  className="flex-1 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                  isDisabled
                >
                  {t('hover_card.pending', 'Pending')}
                </Button>
              ) : (
                <Button
                  size="sm"
                  variant="flat"
                  className="flex-1 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500/20"
                  startContent={<UserPlus className="w-3.5 h-3.5" />}
                  onPress={handleConnect}
                  isLoading={isConnecting}
                >
                  {t('hover_card.connect', 'Connect')}
                </Button>
              )}
              <Button
                as={Link}
                to={tenantPath(`/messages?to=${userData.id}`)}
                size="sm"
                variant="flat"
                className="flex-1 bg-[var(--surface-elevated)] text-[var(--text-primary)] hover:bg-[var(--surface-hover)]"
                startContent={<MessageCircle className="w-3.5 h-3.5" />}
              >
                {t('hover_card.message', 'Message')}
              </Button>
            </div>
          </div>
        ) : null}
      </PopoverContent>
    </Popover>
  );
});

export default UserHoverCard;
