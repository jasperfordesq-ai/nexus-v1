// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';

import ArrowLeft from 'lucide-react/icons/arrow-left';
import MapPin from 'lucide-react/icons/map-pin';
import TrendingUp from 'lucide-react/icons/trending-up';
import Users from 'lucide-react/icons/users';
import FileText from 'lucide-react/icons/file-text';
import Calendar from 'lucide-react/icons/calendar';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { useTenant } from '@/contexts';
import { adminGroups } from '@/admin/api/adminApi';
import type { AdminGroup, GroupMember as GroupMemberType } from '@/admin/api/types';
interface AdminGroupDetail extends AdminGroup {  stats?: { total_exchanges: number; total_hours: number; active_members: number; posts_count: number; events_count: number; activity_score: number };  latitude?: number;  longitude?: number;}
import type { GroupMember } from '@/admin/api/types';
import { ConfirmModal } from '../../components/ConfirmModal';
import { Button, Chip, Card, Tabs, Tab, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell } from '@/components/ui';
import { resolveAvatarUrl, getFormattingLocale } from '@/lib/helpers';
import { GroupAuditLog } from './GroupAuditLog';

const DETAIL_TABS = ['overview', 'members', 'location', 'audit'] as const;
type DetailTab = typeof DETAIL_TABS[number];

export default function GroupDetail() {
  const { t } = useTranslation('admin_groups');
  usePageTitle(t('groups.page_title'));
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const [searchParams, setSearchParams] = useSearchParams();
  const { success, error } = useToast();
  const [group, setGroup] = useState<AdminGroupDetail | null>(null);
  const [members, setMembers] = useState<GroupMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [kickTarget, setKickTarget] = useState<number | null>(null);
  const [kickLoading, setKickLoading] = useState(false);
  const requestedTab = searchParams.get('tab');
  const selectedTab: DetailTab = DETAIL_TABS.includes(requestedTab as DetailTab)
    ? requestedTab as DetailTab
    : 'overview';

  useEffect(() => {
    if (requestedTab !== null && requestedTab !== selectedTab) {
      const next = new URLSearchParams(searchParams);
      next.delete('tab');
      setSearchParams(next, { replace: true });
    }
  }, [requestedTab, searchParams, selectedTab, setSearchParams]);

  const loadGroup = useCallback(async () => {
    try {
      setLoading(true);
      const response = await adminGroups.getGroup(Number(id));
      if (response.success && response.data) {
        const groupData = response.data as AdminGroupDetail;
        setGroup(groupData);
      }
    } catch {
      error(t('groups.failed_to_load_groups'));
    } finally {
      setLoading(false);
    }
  }, [id, error, t])


  const loadMembers = useCallback(async () => {
    try {
      const response = await adminGroups.getMembers(Number(id), { limit: 50 });
      if (response.success && response.data) {
        setMembers(response.data as GroupMemberType[]);
      }
    } catch {
      error(t('groups.failed_to_load_members'));
    }
  }, [id, error, t])


  useEffect(() => {
    if (id) {
      loadGroup();
      loadMembers();
    }
  }, [id, loadGroup, loadMembers]);

  const handleGeocode = async () => {
    try {
      const res = await adminGroups.geocodeGroup(Number(id));
      if (res.success) {
        success(t('groups.location_geocoded'));
        loadGroup();
      } else {
        error(res.error || t('groups.failed_to_geocode_location'));
      }
    } catch {
      error(t('groups.failed_to_geocode_location'));
    }
  };

  const handlePromote = async (userId: number) => {
    try {
      const res = await adminGroups.promoteMember(Number(id), userId);
      if (res.success) {
        success(t('groups.member_promoted'));
        loadMembers();
      } else {
        error(res.error || t('groups.failed_to_promote_member'));
      }
    } catch {
      error(t('groups.failed_to_promote_member'));
    }
  };

  const handleDemote = async (userId: number) => {
    try {
      const res = await adminGroups.demoteMember(Number(id), userId);
      if (res.success) {
        success(t('groups.member_demoted'));
        loadMembers();
      } else {
        error(res.error || t('groups.failed_to_demote_member'));
      }
    } catch {
      error(t('groups.failed_to_demote_member'));
    }
  };

  const handleKick = async () => {
    if (kickTarget === null) return;
    setKickLoading(true);
    try {
      const res = await adminGroups.kickMember(Number(id), kickTarget);
      if (res.success) {
        success(t('groups.member_removed'));
        loadMembers();
      } else {
        error(res.error || t('groups.failed_to_remove_member'));
      }
    } catch {
      error(t('groups.failed_to_remove_member'));
    } finally {
      setKickLoading(false);
      setKickTarget(null);
    }
  };

  if (loading || !group) {
    return <div className="p-6 text-center">{t('groups.loading')}</div>;
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center gap-4">
        <Button isIconOnly variant="tertiary" aria-label={t('groups.go_back')} onPress={() => navigate(-1)}>
          <ArrowLeft className="w-5 h-5" aria-hidden="true" />
        </Button>
        <div className="flex-1">
          <h1 className="text-2xl font-bold">{group.name}</h1>
          <p className="text-sm text-gray-500">{t('groups.group_id')}</p>
        </div>
        <Button variant="tertiary" onPress={() => navigate(tenantPath(`/groups/edit/${group.id}`))}>
          {t('groups.edit')}
        </Button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <Users className="w-8 h-8 text-accent" aria-hidden="true" />
            <div>
              <div className="text-2xl font-bold">{group.member_count || 0}</div>
              <div className="text-xs text-gray-500">{t('groups.members')}</div>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <FileText className="w-8 h-8 text-success" aria-hidden="true" />
            <div>
              <div className="text-2xl font-bold">{group.stats?.posts_count || 0}</div>
              <div className="text-xs text-gray-500">{t('groups.posts')}</div>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <Calendar className="w-8 h-8 text-warning" aria-hidden="true" />
            <div>
              <div className="text-2xl font-bold">{group.stats?.events_count || 0}</div>
              <div className="text-xs text-gray-500">{t('groups.events')}</div>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <TrendingUp className="w-8 h-8 text-accent" aria-hidden="true" />
            <div>
              <div className="text-2xl font-bold">{group.stats?.activity_score || 0}</div>
              <div className="text-xs text-gray-500">{t('groups.activity_score')}</div>
            </div>
          </div>
        </Card>
      </div>

      <Tabs
        aria-label={t('groups.detail_tabs_aria')}
        selectedKey={selectedTab}
        onSelectionChange={(key) => {
          const nextTab = DETAIL_TABS.includes(String(key) as DetailTab)
            ? String(key) as DetailTab
            : 'overview';
          const next = new URLSearchParams(searchParams);
          if (nextTab === 'overview') next.delete('tab');
          else next.set('tab', nextTab);
          setSearchParams(next, { replace: true });
        }}
      >
        <Tab key="overview" title={t('groups.overview')}>
          <Card className="p-6 mt-4 space-y-4">
            <div>
              <div className="text-sm text-gray-500">{t('groups.description')}</div>
              <div className="mt-1">{group.description || t('groups.no_description')}</div>
            </div>
            <div>
              <div className="text-sm text-gray-500">{t('groups.visibility')}</div>
              <Chip className="mt-1" size="sm">
                {(['public', 'private', 'hidden'] as string[]).includes(group.visibility)
                  ? t(`groups.visibility_${group.visibility}`)
                  : t('groups.visibility_unknown')}
              </Chip>
            </div>
            <div>
              <div className="text-sm text-gray-500">{t('groups.created')}</div>
              <div className="mt-1">{new Date(group.created_at).toLocaleString(getFormattingLocale())}</div>
            </div>
          </Card>
        </Tab>

        <Tab key="members" title={t('groups.members')}>
          <Card className="p-4 mt-4">
            <Table aria-label={t('groups.members_table')}>
              <TableHeader>
                <TableColumn>{t('groups.user')}</TableColumn>
                <TableColumn>{t('groups.role')}</TableColumn>
                <TableColumn>{t('groups.joined')}</TableColumn>
                <TableColumn>{t('groups.actions')}</TableColumn>
              </TableHeader>
              <TableBody emptyContent={t('groups.no_members_found')}>
                {members.map((member) => (
                  <TableRow key={member.user_id}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        {member.user_avatar && <img src={resolveAvatarUrl(member.user_avatar)} className="w-8 h-8 rounded-full" alt={member.user_name || t('groups.member_avatar')} loading="lazy" />}
                        <div>{member.user_name}</div>
                      </div>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={member.role === 'owner' ? 'primary' : member.role === 'admin' ? 'secondary' : 'default'}>
                        {t(`groups.role_${member.role}`)}
                      </Chip>
                    </TableCell>
                    <TableCell>{new Date(member.joined_at).toLocaleDateString(getFormattingLocale())}</TableCell>
                    <TableCell>
                      <div className="flex gap-2">
                        {member.role === 'member' && <Button size="sm" variant="tertiary" onPress={() => handlePromote(member.user_id)}>{t('groups.promote')}</Button>}
                        {member.role === 'admin' && (
                          <Button size="sm" variant="tertiary" onPress={() => handleDemote(member.user_id)}>{t('groups.demote')}</Button>
                        )}
                        {member.role !== 'owner' && <Button size="sm" variant="danger" onPress={() => setKickTarget(member.user_id)}>{t('groups.kick')}</Button>}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </Card>
        </Tab>

        <Tab key="location" title={t('groups.location')}>
          <Card className="p-6 mt-4 space-y-4">
            <div>
              <div className="text-sm text-gray-500">{t('groups.address')}</div>
              <div className="mt-1">{group.location || t('groups.no_location')}</div>
            </div>
            {group.latitude && group.longitude && (
              <div>
                <div className="text-sm text-gray-500">{t('groups.coordinates')}</div>
                <div className="mt-1">{group.latitude}, {group.longitude}</div>
              </div>
            )}
            <Button
              startContent={<MapPin className="w-4 h-4" aria-hidden="true" />}
              onPress={handleGeocode}
              isDisabled={!group.location}
            >
              {t('groups.geocode_location')}
            </Button>
          </Card>
        </Tab>

        <Tab key="audit" title={t('groups.audit_log')}>
          <div className="mt-4">
            <GroupAuditLog groupId={Number(id)} />
          </div>
        </Tab>
      </Tabs>

      <ConfirmModal
        isOpen={kickTarget !== null}
        onClose={() => setKickTarget(null)}
        onConfirm={handleKick}
        title={t('common.confirm')}
        message={t('groups.confirm_remove_member')}
        confirmLabel={t('groups.kick')}
        confirmColor="danger"
        isLoading={kickLoading}
      />
    </div>
  );
}
