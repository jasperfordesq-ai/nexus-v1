// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';
import {
  Button,
  Avatar,
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

  return (
    <GlassCard className="p-6 sm:p-8">
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div className="flex items-center gap-4">
          <div className="p-4 rounded-2xl bg-gradient-to-br from-purple-500/20 to-indigo-500/20">
            <Users className="w-8 h-8 text-purple-400" aria-hidden="true" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold text-theme-primary">{group.name}</h1>
              {group.visibility === 'private' ? (
                <Lock className="w-5 h-5 text-amber-400" aria-hidden="true" />
              ) : (
                <Globe className="w-5 h-5 text-emerald-400" aria-hidden="true" />
              )}
            </div>
            <p className="text-theme-muted text-sm mt-1">
              {t('detail.members_count', { count: getMemberCount(group) })}
              {group.location && (
                <>
                  {' '}<span aria-hidden="true">&#183;</span>{' '}
                  <MapPin className="w-3.5 h-3.5 inline" aria-hidden="true" /> {group.location}
                </>
              )}
              {' '}<span aria-hidden="true">&#183;</span>{' '}{t('detail.created')}{' '}
              <time dateTime={group.created_at}>{createdDateLabel}</time>
            </p>
          </div>
        </div>

        <div className="flex gap-2 flex-wrap">
          {userIsAdmin && (
            <>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<Settings className="w-4 h-4" aria-hidden="true" />}
                onPress={onOpenSettings}
              >
                {t('detail.settings')}
              </Button>
              <Button
                variant="flat"
                className="bg-red-500/10 text-[var(--color-error)] hover:bg-red-500/20"
                startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
                onPress={onOpenDelete}
              >
                {t('detail.delete')}
              </Button>
            </>
          )}
          {isAuthenticated && (
            <div className="flex items-center gap-2">
              <Button
                className={userIsMember
                  ? 'bg-theme-hover text-theme-primary'
                  : 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
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
                  size="sm"
                  onPress={onOpenNotifPrefs}
                  aria-label={t('detail.notification_prefs', 'Notification preferences')}
                >
                  <Megaphone className="w-4 h-4" />
                </Button>
              )}
              {userIsAdmin && (
                <Button
                  variant="bordered"
                  size="sm"
                  startContent={<UserPlus className="w-4 h-4" aria-hidden="true" />}
                  onPress={onOpenInvite}
                  aria-label={t('detail.invite_members', 'Invite Members')}
                >
                  {t('detail.invite', 'Invite')}
                </Button>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Tags */}
      {groupTags.length > 0 && (
        <div className="flex flex-wrap gap-2 mb-4">
          {groupTags.map((tag) => (
            <span
              key={tag.id}
              className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary"
            >
              {tag.name}
            </span>
          ))}
        </div>
      )}

      {/* Description */}
      <SafeHtml content={group.description || t('detail.no_description')} className="text-theme-muted mb-6" as="div" />

      {/* Quick Stats */}
      <div className="flex flex-wrap gap-3 sm:gap-6">
        <div className="flex items-center gap-2 text-theme-muted">
          <Users className="w-5 h-5" aria-hidden="true" />
          <span>{t('detail.members_count', { count: getMemberCount(group) })}</span>
        </div>
        {group.posts_count !== undefined && (
          <div className="flex items-center gap-2 text-theme-muted">
            <MessageSquare className="w-5 h-5" aria-hidden="true" />
            <span>{t('detail.posts_count', { count: group.posts_count })}</span>
          </div>
        )}
        <div className="flex items-center gap-2 text-theme-muted">
          <Calendar className="w-5 h-5" aria-hidden="true" />
          <span>{t('detail.created')} <time dateTime={group.created_at}>{createdDateLabel}</time></span>
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
                <div key={request.user_id} className="flex items-center justify-between p-3 rounded-lg bg-theme-elevated">
                  <div className="flex items-center gap-3">
                    <Avatar
                      src={resolveAvatarUrl(request.user.avatar)}
                      name={request.user.name}
                      size="sm"
                    />
                    <div>
                      <p className="text-sm font-medium text-theme-primary">{request.user.name}</p>
                      <time className="text-xs text-theme-subtle" dateTime={request.created_at}>
                        {formatRelativeTime(request.created_at)}
                      </time>
                    </div>
                  </div>
                  <div className="flex gap-2">
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
    </GlassCard>
  );
}
