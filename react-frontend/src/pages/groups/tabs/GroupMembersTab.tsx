// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Members Tab
 * Lists group members with admin role management (promote/demote/remove).
 */

import { Link } from 'react-router-dom';
import {
  Button,
  Avatar,
  Chip,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Spinner,
} from '@heroui/react';
import Users from 'lucide-react/icons/users';
import MoreVertical from 'lucide-react/icons/ellipsis-vertical';
import Shield from 'lucide-react/icons/shield';
import ShieldCheck from 'lucide-react/icons/shield-check';
import UserX from 'lucide-react/icons/user-x';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { resolveAvatarUrl, formatDateValue } from '@/lib/helpers';
import type { User } from '@/types/api';

export interface GroupMember extends User {
  role?: 'member' | 'admin' | 'moderator';
  joined_at?: string;
}

interface GroupMembersTabProps {
  members: GroupMember[];
  membersLoading: boolean;
  userIsAdmin: boolean;
  currentUserId?: number;
  groupOwnerId?: number;
  groupAdminIds?: number[];
  updatingMember: number | null;
  onUpdateMemberRole: (userId: number, role: 'member' | 'admin') => void;
  onRemoveMember: (userId: number) => void;
}

export function GroupMembersTab({
  members,
  membersLoading,
  userIsAdmin,
  currentUserId,
  groupOwnerId,
  groupAdminIds,
  updatingMember,
  onUpdateMemberRole,
  onRemoveMember,
}: GroupMembersTabProps) {
  const { t } = useTranslation('groups');
  const { tenantPath } = useTenant();

  return (
    <GlassCard className="p-4 sm:p-6">
      {membersLoading ? (
        <div className="flex justify-center py-10" aria-label={t('detail.members_loading_aria')}>
          <Spinner size="lg" />
        </div>
      ) : members.length > 0 ? (
        <div className="grid gap-3 sm:grid-cols-2 sm:gap-4">
          {members.map((member) => {
            const memberIsOwner = member.id === groupOwnerId;
            const memberIsAdmin = member.role === 'admin' || (groupAdminIds?.includes(member.id) ?? false) || memberIsOwner;
            const canManage = userIsAdmin && !memberIsOwner && member.id !== currentUserId;
            const joinedLabel = member.joined_at ? formatDateValue(member.joined_at) : null;

            return (
              <div key={member.id} className="flex min-w-0 items-center gap-2 rounded-lg bg-theme-elevated p-3 transition-colors hover:bg-theme-hover sm:gap-4 sm:p-4">
                <Link to={tenantPath(`/profile/${member.id}`)} className="flex min-w-0 flex-1 items-center gap-3 sm:gap-4">
                  <Avatar
                    src={resolveAvatarUrl(member.avatar_url || member.avatar)}
                    name={member.name}
                    size="md"
                    className="ring-2 ring-white/20 flex-shrink-0"
                  />
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-theme-primary truncate">{member.name}</p>
                    {member.tagline && (
                      <p className="text-sm text-theme-subtle truncate">{member.tagline}</p>
                    )}
                    <div className="mt-1 flex min-w-0 flex-wrap items-center gap-1.5">
                      {memberIsOwner && (
                        <Chip size="sm" variant="flat" className="bg-amber-500/20 text-amber-600 dark:text-amber-400" startContent={<ShieldCheck className="w-3 h-3" aria-hidden="true" />}>
                          {t('detail.member_owner')}
                        </Chip>
                      )}
                      {memberIsAdmin && !memberIsOwner && (
                        <Chip size="sm" variant="flat" className="bg-purple-500/20 text-purple-600 dark:text-purple-400" startContent={<Shield className="w-3 h-3" aria-hidden="true" />}>
                          {t('detail.member_admin')}
                        </Chip>
                      )}
                      {joinedLabel && (
                        <span className="truncate text-xs text-theme-subtle">
                          {t('detail.member_joined', { date: joinedLabel })}
                        </span>
                      )}
                    </div>
                  </div>
                </Link>

                {/* Admin: Role Management */}
                {canManage && (
                  <Dropdown>
                    <DropdownTrigger>
                      <Button
                        isIconOnly
                        variant="light"
                        size="sm"
                        aria-label={t('detail.manage_member_aria', { name: member.name })}
                        isLoading={updatingMember === member.id}
                      >
                        <MoreVertical className="w-4 h-4" aria-hidden="true" />
                      </Button>
                    </DropdownTrigger>
                    <DropdownMenu aria-label={t('detail.member_actions_aria')}>
                      {memberIsAdmin ? (
                        <DropdownItem
                          key="demote"
                          startContent={<Users className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => onUpdateMemberRole(member.id, 'member')}
                        >
                          {t('detail.demote_to_member')}
                        </DropdownItem>
                      ) : (
                        <DropdownItem
                          key="promote"
                          startContent={<Shield className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => onUpdateMemberRole(member.id, 'admin')}
                        >
                          {t('detail.promote_to_admin')}
                        </DropdownItem>
                      )}
                      <DropdownItem
                        key="remove"
                        className="text-danger"
                        color="danger"
                        startContent={<UserX className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => onRemoveMember(member.id)}
                      >
                        {t('detail.remove_from_group')}
                      </DropdownItem>
                    </DropdownMenu>
                  </Dropdown>
                )}
              </div>
            );
          })}
        </div>
      ) : (
        <EmptyState
          icon={<Users className="w-12 h-12" aria-hidden="true" />}
          title={t('detail.no_members_title')}
          description={t('detail.no_members_desc')}
        />
      )}
    </GlassCard>
  );
}
