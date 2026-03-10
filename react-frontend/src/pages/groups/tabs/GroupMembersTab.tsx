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
import {
  Users,
  MoreVertical,
  Shield,
  ShieldCheck,
  UserX,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
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
    <GlassCard className="p-6">
      {membersLoading ? (
        <div className="flex justify-center py-8">
          <Spinner size="lg" />
        </div>
      ) : members.length > 0 ? (
        <div className="grid sm:grid-cols-2 gap-4">
          {members.map((member) => {
            const memberIsOwner = member.id === groupOwnerId;
            const memberIsAdmin = member.role === 'admin' || (groupAdminIds?.includes(member.id) ?? false) || memberIsOwner;
            const canManage = userIsAdmin && !memberIsOwner && member.id !== currentUserId;

            return (
              <div key={member.id} className="flex items-center gap-2 sm:gap-4 p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors">
                <Link to={tenantPath(`/profile/${member.id}`)} className="flex items-center gap-4 flex-1 min-w-0">
                  <Avatar
                    src={resolveAvatarUrl(member.avatar_url || member.avatar)}
                    name={member.name}
                    size="md"
                    className="ring-2 ring-white/20 flex-shrink-0"
                  />
                  <div className="min-w-0">
                    <p className="font-medium text-theme-primary truncate">{member.name}</p>
                    {member.tagline && (
                      <p className="text-sm text-theme-subtle truncate">{member.tagline}</p>
                    )}
                    <div className="flex items-center gap-2 mt-1">
                      {memberIsOwner && (
                        <Chip size="sm" variant="flat" className="bg-amber-500/20 text-amber-600 dark:text-amber-400" startContent={<ShieldCheck className="w-3 h-3" />}>
                          {t('detail.member_owner')}
                        </Chip>
                      )}
                      {memberIsAdmin && !memberIsOwner && (
                        <Chip size="sm" variant="flat" className="bg-purple-500/20 text-purple-600 dark:text-purple-400" startContent={<Shield className="w-3 h-3" />}>
                          {t('detail.member_admin')}
                        </Chip>
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
                        <MoreVertical className="w-4 h-4" />
                      </Button>
                    </DropdownTrigger>
                    <DropdownMenu aria-label="Member actions">
                      {memberIsAdmin ? (
                        <DropdownItem
                          key="demote"
                          startContent={<Users className="w-4 h-4" />}
                          onPress={() => onUpdateMemberRole(member.id, 'member')}
                        >
                          {t('detail.demote_to_member')}
                        </DropdownItem>
                      ) : (
                        <DropdownItem
                          key="promote"
                          startContent={<Shield className="w-4 h-4" />}
                          onPress={() => onUpdateMemberRole(member.id, 'admin')}
                        >
                          {t('detail.promote_to_admin')}
                        </DropdownItem>
                      )}
                      <DropdownItem
                        key="remove"
                        className="text-danger"
                        color="danger"
                        startContent={<UserX className="w-4 h-4" />}
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
