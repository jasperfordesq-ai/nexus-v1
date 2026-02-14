/**
 * Federation Settings
 * Configure federation features and partnership preferences for the tenant.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, Switch, Button } from '@heroui/react';
import { Network, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';

interface FedSettings {
  federation_enabled: boolean;
  tenant_id: number;
  settings: {
    allow_inbound_partnerships: boolean;
    auto_approve_partners: boolean;
    shared_categories: string[];
    max_partnerships: number;
  };
}

export function FederationSettings() {
  usePageTitle('Admin - Federation Settings');
  const [data, setData] = useState<FedSettings | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getSettings();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          setData((payload as { data: FedSettings }).data);
        } else {
          setData(payload as FedSettings);
        }
      }
    } catch {
      setData(null);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  return (
    <div>
      <PageHeader
        title="Federation Settings"
        description="Configure cross-community federation features"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Federation Status</h3></CardHeader>
          <CardBody>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Federation Enabled</p>
                <p className="text-sm text-default-500">Allow this community to participate in the federation network</p>
              </div>
              <Switch isSelected={data?.federation_enabled ?? false} isDisabled aria-label="Federation enabled" />
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Partnership Preferences</h3></CardHeader>
          <CardBody className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Allow Inbound Partnerships</p>
                <p className="text-sm text-default-500">Other communities can request to partner with you</p>
              </div>
              <Switch isSelected={data?.settings?.allow_inbound_partnerships ?? true} isDisabled aria-label="Allow inbound" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Auto-Approve Partners</p>
                <p className="text-sm text-default-500">Automatically approve incoming partnership requests</p>
              </div>
              <Switch isSelected={data?.settings?.auto_approve_partners ?? false} isDisabled aria-label="Auto approve" />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Max Partnerships</p>
                <p className="text-sm text-default-500">Maximum number of active partnerships allowed</p>
              </div>
              <span className="text-lg font-semibold">{data?.settings?.max_partnerships ?? 10}</span>
            </div>
          </CardBody>
        </Card>

        {!data && !loading && (
          <Card shadow="sm">
            <CardBody className="flex flex-col items-center py-8 text-default-400">
              <Network size={40} className="mb-2" />
              <p>Federation feature is not enabled for this tenant.</p>
              <p className="text-xs">Enable it from Tenant Features to configure settings.</p>
            </CardBody>
          </Card>
        )}
      </div>
    </div>
  );
}

export default FederationSettings;
