/**
 * Federation Profile (My Listing)
 * View and edit this community's profile in the federation directory.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Textarea, Button } from '@heroui/react';
import { Building, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
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
  const [profile, setProfile] = useState<FedProfile | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getProfile();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          setProfile((payload as { data: FedProfile }).data);
        } else {
          setProfile(payload as FedProfile);
        }
      }
    } catch {
      setProfile(null);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  return (
    <div>
      <PageHeader
        title="My Federation Profile"
        description="How your community appears in the federation directory"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />

      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold">Community Profile</h3></CardHeader>
        <CardBody className="gap-4">
          {loading ? (
            <div className="space-y-3">
              <div className="h-10 w-full animate-pulse rounded bg-default-200" />
              <div className="h-10 w-full animate-pulse rounded bg-default-200" />
              <div className="h-24 w-full animate-pulse rounded bg-default-200" />
            </div>
          ) : profile ? (
            <>
              <Input label="Community Name" value={profile.name} isReadOnly variant="bordered" />
              <Input label="Slug" value={profile.slug} isReadOnly variant="bordered" />
              <Input label="Contact Email" value={profile.federation_profile?.contact_email || ''} isReadOnly variant="bordered" />
              <Input label="Website" value={profile.federation_profile?.website || ''} isReadOnly variant="bordered" />
              <Textarea label="Description" value={profile.federation_profile?.description || ''} isReadOnly variant="bordered" minRows={3} />
              <p className="text-xs text-default-400">Profile editing will be available in a future update. Contact a super admin to make changes.</p>
            </>
          ) : (
            <div className="flex flex-col items-center py-8 text-default-400">
              <Building size={40} className="mb-2" />
              <p>Federation profile not available</p>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default MyProfile;
