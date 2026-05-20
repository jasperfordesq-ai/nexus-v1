// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import {
  Button,
  Avatar,
  Chip,
  Spinner,
} from '@heroui/react';
import Users from 'lucide-react/icons/users';
import MessageSquare from 'lucide-react/icons/message-square';
import Settings from 'lucide-react/icons/settings';
import Lock from 'lucide-react/icons/lock';
import Globe from 'lucide-react/icons/globe';
import UserPlus from 'lucide-react/icons/user-plus';
import UserMinus from 'lucide-react/icons/user-minus';
import Calendar from 'lucide-react/icons/calendar';
import MapPin from 'lucide-react/icons/map-pin';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import Trash2 from 'lucide-react/icons/trash-2';
import Megaphone from 'lucide-react/icons/megaphone';
import { GlassCard } from '@/components/ui';
import { SafeHtml } from '@/components/ui/SafeHtml';
import { LocationMapCard } from '@/components/location';
import { resolveAvatarUrl, formatDateValue, formatRelativeTime } from '@/lib/helpers';
import type { Group } from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GroupDetails extends Group {
  members?: import('@/types/api').User[];
  recent_posts?: import('@/types/api').FeedPost[];
}

export interface JoinRequest {
  user_id: number;
  user: {
    id: number;
    name: string;
    avatar?: string | null;
  };
  created_at: string;
  message?: string;
}

interface GroupTag {
  id: number;
  name: string;
  color?: string;
}

interface GroupHeaderProps {
  group: GroupDetails;
  groupTags: GroupTag[];
  userIsMember: boolean;
  userIsAdmin: boolean;
  isAuthenticated: boolean;
  isJoining: boolean;
  isPrivateGroup: boolean;
  joinRequests: JoinRequest[];
  requestsLoading: boolean;
  requestsLoaded: boolean;
  processingRequest: number | null;
  getMemberCount: (group: GroupDetails) => number;
  onJoinLeave: () => void;
  onOpenSettings: () => void;
  onOpenDelete: () => void;
  onOpenInvite: () => void;
  onOpenNotifPrefs: () => void;
  onLoadJoinRequests: () => void;
  onJoinRequest: (userId: number, action: 'accept' | 'reject') => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupHeader({
  group,
  groupTags,
  userIsMember,
  userIsAdmin,
  isAuthenticated,
  isJoining,
  isPrivateGroup,
  joinRequests,
  requestsLoading,
  requestsLoaded,
  processingRequest,
  getMemberCount,
  onJoinLeave,
  onOpenSettings,
  onOpenDelete,
  onOpenInvite,
  onOpenNotifPrefs,
  onLoadJoinRequests,
  onJoinRequest,
}: GroupHeaderProps) {
  const { t } = useTranslation('groups');
  const createdDateLabel = formatDateValue(group.created_at);
  const coverImage = group.cover_image_url || group.cover_image || null;
  const avatarImage = resolveAvatarUrl(group.image_url);
  const visibilityLabel = group.visibility === 'private' || group.visibility === 'secret'
    ? t('detail.private_chip')
    : t('detail.public_chip');
  const visibilityAria = group.visibility === 'private' || group.visibility === 'secret'
    ? t('detail.visibility_private_aria')
    : t('detail.visibility_public_aria');

  return (
    <GlassCard className="overflow-hidden">
      <div className="relative min-h-32 bg-gradient-to-br from-[var(--color-primary)]/18 via-[var(--surface-elevated)] to-[var(--color-secondary)]/16">
        {coverImage && (
          <img
            src={coverImage}
            alt={t('detail.cover_image_alt', { name: group.name })}
            className="absolute inset-0 h-full w-full object-cover"
          />
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-[var(--surface-elevated)] via-[var(--surface-elevated)]/55 to-transparent" />
      </div>

      <div className="px-4 pb-5 sm:px-6 sm:pb-6">
        <div className="-mt-12 flex flex-col gap-4 sm:-mt-10 sm:flex-row sm:items-end sm:justify-between">
          <div className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end">
            <Avatar
              src={avatarImage}
              name={group.name}
              className="h-24 w-24 flex-shrink-0 border-4 border-[var(--surface-elevated)] bg-gradient-to-br from-purple-500/20 to-indigo-500/20 text-theme-primary shadow-lg"
              imgProps={{ alt: t('detail.group_image_alt', { name: group.name }) }}
              fallback={<Users className="h-10 w-10 text-purple-400" aria-hidden="true" />}
            />
            <div className="min-w-0 pb-1">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="min-w-0 max-w-full break-words text-2xl font-bold leading-tight text-theme-primary sm:text-3xl">
                  {group.name}
                </h1>
                <Chip
                  size="sm"
                  variant="flat"
                  className={isPrivateGroup
                    ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                    : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                  }
                  startContent={isPrivateGroup
                    ? <Lock className="h-3.5 w-3.5" aria-hidden="true" />
                    : <Globe className="h-3.5 w-3.5" aria-hidden="true" />
                  }
                  aria-label={visibilityAria}
                >
                  {visibilityLabel}
                </Chip>
              </div>
              <p className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-theme-muted">
                <span>{t('detail.members_count', { count: getMemberCount(group) })}</span>
                {group.location && (
                  <span className="inline-flex min-w-0 items-center gap-1">
                    <span aria-hidden="true">&#183;</span>
                    <MapPin className="h-3.5 w-3.5 flex-shrink-0" aria-hidden="true" />
                    <span className="max-w-56 truncate sm:max-w-xs">{group.location}</span>
                  </span>
                )}
                <span aria-hidden="true">&#183;</span>
                <span>
                  {t('detail.created')}{' '}
                  <time dateTime={group.created_at}>{createdDateLabel}</time>
                </span>
              </p>
            </div>
          </div>

        <div className="flex flex-col gap-2 sm:items-end" aria-label={t('detail.header_actions_aria')}>
          <div className="flex w-full flex-wrap gap-2 sm:w-auto sm:justify-end">
          {userIsAdmin && (
            <>
              <Button
                variant="flat"
                className="min-w-0 flex-1 bg-theme-elevated text-theme-primary sm:flex-none"
                startContent={<Settings className="w-4 h-4" aria-hidden="true" />}
                onPress={onOpenSettings}
              >
                {t('detail.settings')}
              </Button>
              <Button
                variant="flat"
                className="min-w-0 flex-1 bg-red-500/10 text-[var(--color-error)] hover:bg-red-500/20 sm:flex-none"
                startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                onPress={onOpenDelete}
              >
                {t('detail.delete')}
              </Button>
            </>
          )}
          {isAuthenticated && (
            <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
              <Button
                className={userIsMember
                  ? 'min-w-0 flex-1 bg-theme-hover text-theme-primary sm:flex-none'
                  : 'min-w-0 flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white sm:flex-none'
                }
                startContent={userIsMember ? <UserMinus className="w-4 h-4" aria-hidden="true" /> : <UserPlus className="w-4 h-4" aria-hidden="true" />}
                onPress={onJoinLeave}
                isLoading={isJoining}
              >
                {userIsMember ? t('detail.leave_group') : t('detail.join_group')}
              </Button>
              {userIsMember && (
                <Button
                  isIconOnly
                  variant="flat"
                  className="h-10 w-10 flex-shrink-0"
                  onPress={onOpenNotifPrefs}
                  aria-label={t('detail.notification_prefs')}
                >
                  <Megaphone className="w-4 h-4" />
                </Button>
              )}
              {userIsAdmin && (
                <Button
                  variant="bordered"
                  className="min-w-0 flex-1 sm:flex-none"
                  startContent={<UserPlus className="w-4 h-4" aria-hidden="true" />}
                  onPress={onOpenInvite}
                  aria-label={t('detail.invite_members')}
                >
                  {t('detail.invite')}
                </Button>
              )}
            </div>
          )}
          </div>
        </div>
      </div>

      {/* Tags */}
      {groupTags.length > 0 && (
        <div className="mt-5 flex flex-wrap gap-2">
          {groupTags.map((tag) => (
            <Chip
              key={tag.id}
              size="sm"
              variant="flat"
              className="bg-primary/10 text-primary"
            >
              {tag.name}
            </Chip>
          ))}
        </div>
      )}

      {/* Description */}
      <SafeHtml
        content={group.description || t('detail.no_description')}
        className="mt-5 text-theme-muted [&_*]:break-words"
        as="div"
      />

      {/* Quick Stats */}
      <div className="mt-6 grid gap-3 text-sm sm:grid-cols-3">
        <div className="flex min-w-0 items-center gap-2 rounded-lg bg-theme-elevated px-3 py-2 text-theme-muted">
          <Users className="w-5 h-5" aria-hidden="true" />
          <span className="truncate">{t('detail.members_count', { count: getMemberCount(group) })}</span>
        </div>
        {group.posts_count !== undefined && (
          <div className="flex min-w-0 items-center gap-2 rounded-lg bg-theme-elevated px-3 py-2 text-theme-muted">
            <MessageSquare className="w-5 h-5" aria-hidden="true" />
            <span className="truncate">{t('detail.posts_count', { count: group.posts_count })}</span>
          </div>
        )}
        <div className="flex min-w-0 items-center gap-2 rounded-lg bg-theme-elevated px-3 py-2 text-theme-muted">
          <Calendar className="w-5 h-5" aria-hidden="true" />
          <span className="truncate">{t('detail.created')} <time dateTime={group.created_at}>{createdDateLabel}</time></span>
        </div>
      </div>

      {/* Location Map */}
      {group.location && group.latitude && group.longitude && (
        <LocationMapCard
          title={t('detail.location_title')}
          locationText={group.location}
          markers={[{
            id: group.id,
            lat: Number(group.latitude),
            lng: Number(group.longitude),
            title: group.name,
          }]}
          center={{ lat: Number(group.latitude), lng: Number(group.longitude) }}
          mapHeight="250px"
          zoom={14}
          className="mt-6"
        />
      )}

      {/* Admin: Pending Requests Banner */}
      {userIsAdmin && isPrivateGroup && !requestsLoaded && (
        <div className="mt-4 pt-4 border-t border-theme-default">
          <Button
            variant="flat"
            size="sm"
            className="bg-amber-500/10 text-amber-600 dark:text-amber-400"
            startContent={<UserPlus className="w-4 h-4" aria-hidden="true" />}
            onPress={onLoadJoinRequests}
          >
            {t('detail.view_pending_requests')}
          </Button>
        </div>
      )}

      {/* Admin: Pending Requests Section */}
      {userIsAdmin && requestsLoaded && (
        <div className="mt-4 pt-4 border-t border-theme-default">
          <h3 className="text-sm font-semibold text-theme-primary mb-3 flex items-center gap-2">
            <UserPlus className="w-4 h-4" aria-hidden="true" />
            {t('detail.pending_requests_title')} ({joinRequests.length})
          </h3>
          {requestsLoading ? (
            <div className="flex justify-center py-4">
              <Spinner size="sm" />
            </div>
          ) : joinRequests.length === 0 ? (
            <p className="text-sm text-theme-subtle">{t('detail.no_pending_requests')}</p>
          ) : (
            <div className="space-y-2">
              {joinRequests.map((request) => (
                <div key={request.user_id} className="flex flex-col gap-3 p-3 rounded-lg bg-theme-elevated sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex min-w-0 items-center gap-3">
                    <Avatar
                      src={resolveAvatarUrl(request.user.avatar)}
                      name={request.user.name}
                      size="sm"
                    />
                    <div className="min-w-0">
                      <p className="truncate text-sm font-medium text-theme-primary">{request.user.name}</p>
                      <time className="text-xs text-theme-subtle" dateTime={request.created_at}>
                        {formatRelativeTime(request.created_at)}
                      </time>
                    </div>
                  </div>
                  <div className="flex gap-2 sm:flex-shrink-0">
                    <Button
                      size="sm"
                      className="bg-emerald-500/20 text-emerald-600 dark:text-emerald-400"
                      startContent={<CheckCircle className="w-3.5 h-3.5" aria-hidden="true" />}
                      isLoading={processingRequest === request.user_id}
                      onPress={() => onJoinRequest(request.user_id, 'accept')}
                    >
                      {t('detail.accept')}
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      className="bg-red-500/10 text-[var(--color-error)]"
                      startContent={<XCircle className="w-3.5 h-3.5" aria-hidden="true" />}
                      isLoading={processingRequest === request.user_id}
                      onPress={() => onJoinRequest(request.user_id, 'reject')}
                    >
                      {t('detail.reject')}
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
      </div>
    </GlassCard>
  );
}
