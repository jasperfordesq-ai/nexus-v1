// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card,
  Button,
  Tabs,
  Tab,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Chip,
  Input,
  Textarea,
} from '@heroui/react';
import { ArrowLeft, MapPin, TrendingUp, Users, FileText, Calendar, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminGroups } from '@/admin/api/adminApi';
import type { AdminGroup, GroupMember as GroupMemberType } from '@/admin/api/types';
interface AdminGroupDetail extends AdminGroup {  stats?: { total_exchanges: number; total_hours: number; active_members: number; posts_count: number; events_count: number; activity_score: number };  latitude?: number;  longitude?: number;}
import type { GroupMember } from '@/admin/api/types';

import { useTranslation } from 'react-i18next';
export default function GroupDetail() {
  const { t } = useTranslation('admin');
  usePageTitle(t('groups.page_title'));
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { success, error } = useToast();
  const [group, setGroup] = useState<AdminGroupDetail | null>(null);
  const [members, setMembers] = useState<GroupMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [editMode, setEditMode] = useState(false);
  const [formData, setFormData] = useState({ name: '', description: '', location: '' });

  const loadGroup = useCallback(async () => {
    try {
      setLoading(true);
      const response = await adminGroups.getGroup(Number(id));
      if (response.success && response.data) {
        const groupData = response.data as AdminGroupDetail;
        setGroup(groupData);
        setFormData({
          name: groupData.name || '',
          description: groupData.description || '',
          location: groupData.location || '',
        });
      }
    } catch {
      error(t('groups.failed_to_load_groups'));
    } finally {
      setLoading(false);
    }
  }, [id, error]);

  const loadMembers = useCallback(async () => {
    try {
      const response = await adminGroups.getMembers(Number(id), { limit: 50 });
      if (response.success && response.data) {
        setMembers(response.data as GroupMemberType[]);
      }
    } catch {
      error(t('groups.failed_to_load_members'));
    }
  }, [id, error]);

  useEffect(() => {
    if (id) {
      loadGroup();
      loadMembers();
    }
  }, [id, loadGroup, loadMembers]);

  const handleSave = async () => {
    try {
      await adminGroups.updateGroup(Number(id), formData);
      success(t('groups.group_updated'));
      setEditMode(false);
      loadGroup();
    } catch {
      error(t('groups.failed_to_update_group'));
    }
  };

  const handleGeocode = async () => {
    try {
      await adminGroups.geocodeGroup(Number(id));
      success(t('groups.location_geocoded'));
      loadGroup();
    } catch {
      error(t('groups.failed_to_geocode_location'));
    }
  };

  const handlePromote = async (userId: number) => {
    try {
      await adminGroups.promoteMember(Number(id), userId);
      success(t('groups.member_promoted'));
      loadMembers();
    } catch {
      error(t('groups.failed_to_promote_member'));
    }
  };

  const handleDemote = async (userId: number) => {
    try {
      await adminGroups.demoteMember(Number(id), userId);
      success(t('groups.member_demoted'));
      loadMembers();
    } catch {
      error(t('groups.failed_to_demote_member'));
    }
  };

  const handleKick = async (userId: number) => {
    if (!confirm(t('groups.confirm_remove_member'))) return;
    try {
      await adminGroups.kickMember(Number(id), userId);
      success(t('groups.member_removed'));
      loadMembers();
    } catch {
      error(t('groups.failed_to_remove_member'));
    }
  };

  if (loading || !group) {
    return <div className="p-6 text-center">{t('groups.loading')}</div>;
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center gap-4">
        <Button isIconOnly variant="light" aria-label={t('groups.label_go_back')} onPress={() => navigate(-1)}>
          <ArrowLeft className="w-5 h-5" />
        </Button>
        <div className="flex-1">
          <h1 className="text-2xl font-bold">{group.name}</h1>
          <p className="text-sm text-gray-500">{t('groups.group_id', { id: group.id })}</p>
        </div>
        {editMode ? (
          <Button color="primary" startContent={<Save className="w-4 h-4" />} onPress={handleSave}>
            {t('groups.save')}
          </Button>
        ) : (
          <Button variant="flat" onPress={() => setEditMode(true)}>
            {t('groups.edit')}
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <Users className="w-8 h-8 text-primary" />
            <div>
              <div className="text-2xl font-bold">{group.member_count || 0}</div>
              <div className="text-xs text-gray-500">{t('groups.members')}</div>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <FileText className="w-8 h-8 text-success" />
            <div>
              <div className="text-2xl font-bold">{group.stats?.posts_count || 0}</div>
              <div className="text-xs text-gray-500">{t('groups.posts')}</div>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <Calendar className="w-8 h-8 text-warning" />
            <div>
              <div className="text-2xl font-bold">{group.stats?.events_count || 0}</div>
              <div className="text-xs text-gray-500">{t('groups.events')}</div>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <TrendingUp className="w-8 h-8 text-secondary" />
            <div>
              <div className="text-2xl font-bold">{group.stats?.activity_score || 0}</div>
              <div className="text-xs text-gray-500">{t('groups.activity_score')}</div>
            </div>
          </div>
        </Card>
      </div>

      <Tabs>
        <Tab key="overview" title={t('groups.overview')}>
          <Card className="p-6 mt-4 space-y-4">
            {editMode ? (
              <>
                <Input label={t('groups.label_name')} value={formData.name} onValueChange={(v) => setFormData({ ...formData, name: v })} />
                <Textarea label={t('groups.label_description')} value={formData.description} onValueChange={(v) => setFormData({ ...formData, description: v })} />
                <Input label={t('groups.label_location')} value={formData.location} onValueChange={(v) => setFormData({ ...formData, location: v })} />
              </>
            ) : (
              <>
                <div>
                  <div className="text-sm text-gray-500">{t('groups.label_description')}</div>
                  <div className="mt-1">{group.description || t('groups.no_description')}</div>
                </div>
                <div>
                  <div className="text-sm text-gray-500">{t('groups.visibility')}</div>
                  <Chip className="mt-1" size="sm">{group.visibility}</Chip>
                </div>
                <div>
                  <div className="text-sm text-gray-500">{t('groups.created')}</div>
                  <div className="mt-1">{new Date(group.created_at).toLocaleString()}</div>
                </div>
              </>
            )}
          </Card>
        </Tab>

        <Tab key="members" title={t('groups.members')}>
          <Card className="p-4 mt-4">
            <Table aria-label={t('groups.label_members_table')}>
              <TableHeader>
                <TableColumn>{t('groups.col_user')}</TableColumn>
                <TableColumn>{t('groups.col_role')}</TableColumn>
                <TableColumn>{t('groups.col_joined')}</TableColumn>
                <TableColumn>{t('groups.col_actions')}</TableColumn>
              </TableHeader>
              <TableBody emptyContent={t('groups.no_members_found')}>
                {members.map((member) => (
                  <TableRow key={member.user_id}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        {member.user_avatar && <img src={member.user_avatar} className="w-8 h-8 rounded-full" alt={member.user_name || 'Member avatar'} loading="lazy" />}
                        <div>{member.user_name}</div>
                      </div>
                    </TableCell>
                    <TableCell><Chip size="sm" color={member.role === 'owner' ? 'primary' : member.role === 'admin' ? 'secondary' : 'default'}>{member.role}</Chip></TableCell>
                    <TableCell>{new Date(member.joined_at).toLocaleDateString()}</TableCell>
                    <TableCell>
                      <div className="flex gap-2">
                        {member.role === 'member' && <Button size="sm" variant="flat" onPress={() => handlePromote(member.user_id)}>{t('groups.promote')}</Button>}
                        {member.role === 'admin' && (
                          <>
                            <Button size="sm" variant="flat" onPress={() => handlePromote(member.user_id)}>{t('groups.make_owner')}</Button>
                            <Button size="sm" variant="flat" onPress={() => handleDemote(member.user_id)}>{t('groups.demote')}</Button>
                          </>
                        )}
                        {member.role !== 'owner' && <Button size="sm" variant="flat" color="danger" onPress={() => handleKick(member.user_id)}>{t('groups.kick')}</Button>}
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
              color="primary"
              startContent={<MapPin className="w-4 h-4" />}
              onPress={handleGeocode}
              isDisabled={!group.location}
            >
              {t('groups.geocode_location')}
            </Button>
          </Card>
        </Tab>
      </Tabs>
    </div>
  );
}
