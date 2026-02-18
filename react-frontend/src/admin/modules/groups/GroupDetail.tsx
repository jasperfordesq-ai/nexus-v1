import { useState, useEffect } from 'react';
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
import type { GroupMember } from '@/admin/api/types';

export default function GroupDetail() {
  usePageTitle('Group Detail');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { success, error } = useToast();
  const [group, setGroup] = useState<any>(null);
  const [members, setMembers] = useState<GroupMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [editMode, setEditMode] = useState(false);
  const [formData, setFormData] = useState({ name: '', description: '', location: '' });

  useEffect(() => {
    if (id) {
      loadGroup();
      loadMembers();
    }
  }, [id]);

  const loadGroup = async () => {
    try {
      setLoading(true);
      const response = await adminGroups.getGroup(Number(id));
      if (response.success && response.data) {
        const groupData = response.data as AdminGroup;
        setGroup(groupData);
        setFormData({
          name: groupData.name || '',
          description: groupData.description || '',
          location: groupData.location || '',
        });
      }
    } catch (err) {
      error('Failed to load group');
    } finally {
      setLoading(false);
    }
  };

  const loadMembers = async () => {
    try {
      const response = await adminGroups.getMembers(Number(id), { limit: 50 });
      if (response.success && response.data) {
        setMembers(response.data as GroupMemberType[]);
      }
    } catch (err) {
      error('Failed to load members');
    }
  };

  const handleSave = async () => {
    try {
      await adminGroups.updateGroup(Number(id), formData);
      success('Group updated');
      setEditMode(false);
      loadGroup();
    } catch (err) {
      error('Failed to update group');
    }
  };

  const handleGeocode = async () => {
    try {
      await adminGroups.geocodeGroup(Number(id));
      success('Location geocoded');
      loadGroup();
    } catch (err) {
      error('Failed to geocode location');
    }
  };

  const handlePromote = async (userId: number) => {
    try {
      await adminGroups.promoteMember(Number(id), userId);
      success('Member promoted');
      loadMembers();
    } catch (err) {
      error('Failed to promote member');
    }
  };

  const handleDemote = async (userId: number) => {
    try {
      await adminGroups.demoteMember(Number(id), userId);
      success('Member demoted');
      loadMembers();
    } catch (err) {
      error('Failed to demote member');
    }
  };

  const handleKick = async (userId: number) => {
    if (!confirm('Are you sure you want to remove this member?')) return;
    try {
      await adminGroups.kickMember(Number(id), userId);
      success('Member removed');
      loadMembers();
    } catch (err) {
      error('Failed to remove member');
    }
  };

  if (loading || !group) {
    return <div className="p-6 text-center">Loading...</div>;
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center gap-4">
        <Button isIconOnly variant="light" onPress={() => navigate(-1)}>
          <ArrowLeft className="w-5 h-5" />
        </Button>
        <div className="flex-1">
          <h1 className="text-2xl font-bold">{group.name}</h1>
          <p className="text-sm text-gray-500">Group #{group.id}</p>
        </div>
        {editMode ? (
          <Button color="primary" startContent={<Save className="w-4 h-4" />} onPress={handleSave}>
            Save
          </Button>
        ) : (
          <Button variant="flat" onPress={() => setEditMode(true)}>
            Edit
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <Users className="w-8 h-8 text-primary" />
            <div>
              <div className="text-2xl font-bold">{group.member_count || 0}</div>
              <div className="text-xs text-gray-500">Members</div>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <FileText className="w-8 h-8 text-success" />
            <div>
              <div className="text-2xl font-bold">{group.stats?.posts_count || 0}</div>
              <div className="text-xs text-gray-500">Posts</div>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <Calendar className="w-8 h-8 text-warning" />
            <div>
              <div className="text-2xl font-bold">{group.stats?.events_count || 0}</div>
              <div className="text-xs text-gray-500">Events</div>
            </div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <TrendingUp className="w-8 h-8 text-secondary" />
            <div>
              <div className="text-2xl font-bold">{group.stats?.activity_score || 0}</div>
              <div className="text-xs text-gray-500">Activity Score</div>
            </div>
          </div>
        </Card>
      </div>

      <Tabs>
        <Tab key="overview" title="Overview">
          <Card className="p-6 mt-4 space-y-4">
            {editMode ? (
              <>
                <Input label="Name" value={formData.name} onValueChange={(v) => setFormData({ ...formData, name: v })} />
                <Textarea label="Description" value={formData.description} onValueChange={(v) => setFormData({ ...formData, description: v })} />
                <Input label="Location" value={formData.location} onValueChange={(v) => setFormData({ ...formData, location: v })} />
              </>
            ) : (
              <>
                <div>
                  <div className="text-sm text-gray-500">Description</div>
                  <div className="mt-1">{group.description || 'No description'}</div>
                </div>
                <div>
                  <div className="text-sm text-gray-500">Visibility</div>
                  <Chip className="mt-1" size="sm">{group.visibility}</Chip>
                </div>
                <div>
                  <div className="text-sm text-gray-500">Created</div>
                  <div className="mt-1">{new Date(group.created_at).toLocaleString()}</div>
                </div>
              </>
            )}
          </Card>
        </Tab>

        <Tab key="members" title="Members">
          <Card className="p-4 mt-4">
            <Table aria-label="Members table">
              <TableHeader>
                <TableColumn>USER</TableColumn>
                <TableColumn>ROLE</TableColumn>
                <TableColumn>JOINED</TableColumn>
                <TableColumn>ACTIONS</TableColumn>
              </TableHeader>
              <TableBody items={members}>
                {(member) => (
                  <TableRow key={member.user_id}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        {member.user_avatar && <img src={member.user_avatar} className="w-8 h-8 rounded-full" />}
                        <div>{member.user_name}</div>
                      </div>
                    </TableCell>
                    <TableCell><Chip size="sm" color={member.role === 'owner' ? 'primary' : member.role === 'admin' ? 'secondary' : 'default'}>{member.role}</Chip></TableCell>
                    <TableCell>{new Date(member.joined_at).toLocaleDateString()}</TableCell>
                    <TableCell>
                      <div className="flex gap-2">
                        {member.role === 'member' && <Button size="sm" variant="flat" onPress={() => handlePromote(member.user_id)}>Promote</Button>}
                        {member.role === 'admin' && (
                          <>
                            <Button size="sm" variant="flat" onPress={() => handlePromote(member.user_id)}>Make Owner</Button>
                            <Button size="sm" variant="flat" onPress={() => handleDemote(member.user_id)}>Demote</Button>
                          </>
                        )}
                        {member.role !== 'owner' && <Button size="sm" variant="flat" color="danger" onPress={() => handleKick(member.user_id)}>Kick</Button>}
                      </div>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </Card>
        </Tab>

        <Tab key="location" title="Location">
          <Card className="p-6 mt-4 space-y-4">
            <div>
              <div className="text-sm text-gray-500">Address</div>
              <div className="mt-1">{group.location || 'No location'}</div>
            </div>
            {group.latitude && group.longitude && (
              <div>
                <div className="text-sm text-gray-500">Coordinates</div>
                <div className="mt-1">{group.latitude}, {group.longitude}</div>
              </div>
            )}
            <Button
              color="primary"
              startContent={<MapPin className="w-4 h-4" />}
              onPress={handleGeocode}
              isDisabled={!group.location}
            >
              Geocode Location
            </Button>
          </Card>
        </Tab>
      </Tabs>
    </div>
  );
}
