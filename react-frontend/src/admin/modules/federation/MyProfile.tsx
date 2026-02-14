/**
 * Federation Profile (My Listing)
 * View and edit this community's profile in the federation directory.
 * Supports editing name, description, contact email, website, and categories.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Textarea, Button, Spinner } from '@heroui/react';
import { Building, RefreshCw, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';

interface FedProfile {
  id: number;
  name: string;
  slug: string;
  status: string;
  federation_profile: {
    description: string;
    contact_email: string;
    website: string;
    categories: string[];
  };
}

export function MyProfile() {
  usePageTitle('Admin - Federation Profile');
  const toast = useToast();

  const [profile, setProfile] = useState<FedProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);

  // Editable form fields
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [contactEmail, setContactEmail] = useState('');
  const [website, setWebsite] = useState('');
  const [categories, setCategories] = useState('');

  const populateForm = useCallback((p: FedProfile) => {
    setName(p.name || '');
    setDescription(p.federation_profile?.description || '');
    setContactEmail(p.federation_profile?.contact_email || '');
    setWebsite(p.federation_profile?.website || '');
    setCategories((p.federation_profile?.categories || []).join(', '));
    setDirty(false);
  }, []);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getProfile();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let profileData: FedProfile;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          profileData = (payload as { data: FedProfile }).data;
        } else {
          profileData = payload as FedProfile;
        }
        setProfile(profileData);
        populateForm(profileData);
      }
    } catch {
      toast.error('Failed to load federation profile');
      setProfile(null);
    }
    setLoading(false);
  }, [toast, populateForm]);

  useEffect(() => { loadData(); }, [loadData]);

  const markDirty = useCallback(() => {
    if (!dirty) setDirty(true);
  }, [dirty]);

  const handleSave = useCallback(async () => {
    if (!profile) return;
    setSaving(true);
    try {
      const categoriesArray = categories
        .split(',')
        .map((c) => c.trim())
        .filter(Boolean);

      const res = await adminFederation.updateProfile({
        name,
        federation_profile: {
          description,
          contact_email: contactEmail,
          website,
          categories: categoriesArray,
        },
      });
      if (res.success) {
        toast.success('Federation profile updated successfully');
        setDirty(false);
        // Refresh profile from server to get any server-side changes
        await loadData();
      } else {
        toast.error('Failed to update federation profile');
      }
    } catch {
      toast.error('Failed to update federation profile');
    } finally {
      setSaving(false);
    }
  }, [profile, name, description, contactEmail, website, categories, toast, loadData]);

  if (loading) {
    return (
      <div>
        <PageHeader
          title="My Federation Profile"
          description="How your community appears in the federation directory"
        />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  if (!profile) {
    return (
      <div>
        <PageHeader
          title="My Federation Profile"
          description="How your community appears in the federation directory"
          actions={
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData}>
              Refresh
            </Button>
          }
        />
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center py-8 text-default-400">
            <Building size={40} className="mb-2" />
            <p>Federation profile not available</p>
            <p className="text-xs mt-1">Enable federation from Tenant Features to create a profile.</p>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="My Federation Profile"
        description="How your community appears in the federation directory"
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              size="sm"
            >
              Refresh
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
              isDisabled={!dirty}
              size="sm"
            >
              Save Changes
            </Button>
          </div>
        }
      />

      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold">Community Profile</h3></CardHeader>
        <CardBody className="gap-4">
          <Input
            label="Community Name"
            value={name}
            onValueChange={(val) => { setName(val); markDirty(); }}
            variant="bordered"
            description="The public name of your community in the federation directory"
          />
          <Input
            label="Slug"
            value={profile.slug}
            isReadOnly
            variant="bordered"
            description="URL-safe identifier (cannot be changed)"
          />
          <Input
            label="Contact Email"
            type="email"
            value={contactEmail}
            onValueChange={(val) => { setContactEmail(val); markDirty(); }}
            variant="bordered"
            description="Public contact email shown to partner communities"
          />
          <Input
            label="Website"
            type="url"
            value={website}
            onValueChange={(val) => { setWebsite(val); markDirty(); }}
            variant="bordered"
            placeholder="https://"
            description="Your community's website URL"
          />
          <Textarea
            label="Description"
            value={description}
            onValueChange={(val) => { setDescription(val); markDirty(); }}
            variant="bordered"
            minRows={3}
            maxRows={6}
            description="A brief description of your community for the federation directory"
          />
          <Input
            label="Categories"
            value={categories}
            onValueChange={(val) => { setCategories(val); markDirty(); }}
            variant="bordered"
            placeholder="e.g. timebanking, community, volunteering"
            description="Comma-separated list of categories your community participates in"
          />
        </CardBody>
      </Card>
    </div>
  );
}

export default MyProfile;
