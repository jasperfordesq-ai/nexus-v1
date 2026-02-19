import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Card, CardBody, CardHeader, Chip } from '@heroui/react';
import { Users, MessageSquare, DollarSign, FileText, Calendar, UsersRound, AlertCircle, Shield } from 'lucide-react';
import PageHeader from '../../../components/PageHeader';
import { useTenant } from '@/contexts/TenantContext';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { tenantPath } from '@/lib/tenant-routing';

interface TenantInfo {
  id: number;
  name: string;
  domain: string;
  is_whitelisted: boolean;
  partnerships_count: number;
}

interface FeatureToggles {
  profiles: boolean;
  messaging: boolean;
  transactions: boolean;
  listings: boolean;
  events: boolean;
  groups: boolean;
  analytics: boolean;
  webhooks: boolean;
}

interface Partnership {
  id: number;
  partner_id: number;
  partner_name: string;
  level: number;
  status: string;
  created_at: string;
}

export default function FederationTenantFeatures() {
  const { tenantId } = useParams<{ tenantId: string }>();
  useTenant();
  const toast = useToast();
  const [tenantInfo] = useState<TenantInfo | null>(null);
  const [features, setFeatures] = useState<FeatureToggles>({
    profiles: false,
    messaging: false,
    transactions: false,
    listings: false,
    events: false,
    groups: false,
    analytics: false,
    webhooks: false,
  });
  const [partnerships] = useState<Partnership[]>([]);
  const [loading, setLoading] = useState(true);

  usePageTitle(tenantInfo ? `${tenantInfo.name} - Federation Features` : 'Tenant Features');

  useEffect(() => {
    // TODO: Replace with adminApi.getTenantFeatures(tenantId)
    setLoading(false);
  }, [tenantId]);

  const handleToggleFeature = async (key: keyof FeatureToggles, value: boolean) => {
    if (!tenantInfo?.is_whitelisted) {
      toast.error('Tenant must be whitelisted to enable features');
      return;
    }

    // TODO: Replace with adminApi.updateTenantFeature(tenantId, key, value)
    setFeatures(prev => ({ ...prev, [key]: value }));
    toast.success(`${key} ${value ? 'enabled' : 'disabled'}`);
  };

  const getLevelColor = (level: number) => {
    switch (level) {
      case 1: return 'primary';
      case 2: return 'success';
      case 3: return 'secondary';
      case 4: return 'warning';
      default: return 'default';
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active': return 'success';
      case 'pending': return 'warning';
      case 'suspended': return 'danger';
      case 'terminated': return 'default';
      default: return 'default';
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={tenantInfo?.name || 'Tenant Features'}
        description="Manage federation features for this tenant"
      />

      {/* Tenant Info */}
      {tenantInfo && (
        <Card>
          <CardBody>
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-lg font-semibold">{tenantInfo.name}</h3>
                <p className="text-sm text-default-600">{tenantInfo.domain}</p>
              </div>
              <div className="flex items-center gap-3">
                {tenantInfo.is_whitelisted && (
                  <Chip color="success" variant="flat" startContent={<Shield className="w-4 h-4" />}>
                    Whitelisted
                  </Chip>
                )}
                <Chip variant="flat">
                  {tenantInfo.partnerships_count} Partnerships
                </Chip>
              </div>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Warning Banner */}
      {!tenantInfo?.is_whitelisted && (
        <Card className="border-2 border-warning">
          <CardBody className="flex flex-row items-center gap-3">
            <AlertCircle className="w-6 h-6 text-warning" />
            <div>
              <p className="font-semibold text-warning">Tenant Not Whitelisted</p>
              <p className="text-sm text-warning-600 dark:text-warning-400">
                This tenant must be added to the whitelist before federation features can be enabled.
              </p>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Feature Toggles */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">Federation Features</h3>
        </CardHeader>
        <CardBody className="space-y-4">
          {[
            {
              key: 'profiles',
              icon: Users,
              label: 'Profile Sharing',
              description: 'Allow this tenant to share and view profiles across communities'
            },
            {
              key: 'messaging',
              icon: MessageSquare,
              label: 'Cross-Community Messaging',
              description: 'Enable messaging with members from partner communities'
            },
            {
              key: 'transactions',
              icon: DollarSign,
              label: 'Cross-Community Transactions',
              description: 'Allow time credit transfers with partner communities'
            },
            {
              key: 'listings',
              icon: FileText,
              label: 'Listing Discovery',
              description: 'Show listings from partner communities to this tenant'
            },
            {
              key: 'events',
              icon: Calendar,
              label: 'Event Sharing',
              description: 'Share events with partner communities'
            },
            {
              key: 'groups',
              icon: UsersRound,
              label: 'Cross-Community Groups',
              description: 'Enable groups that span multiple communities'
            },
            {
              key: 'analytics',
              icon: Users,
              label: 'Federation Analytics',
              description: 'Access to federation analytics and reports'
            },
            {
              key: 'webhooks',
              icon: Users,
              label: 'Federation Webhooks',
              description: 'Receive webhook notifications for federation events'
            },
          ].map(feature => {
            const Icon = feature.icon;
            return (
              <div key={feature.key} className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="p-2 rounded-lg bg-primary-100 dark:bg-primary-900">
                    <Icon className="w-5 h-5 text-primary" />
                  </div>
                  <div>
                    <p className="font-medium">{feature.label}</p>
                    <p className="text-sm text-default-500">{feature.description}</p>
                  </div>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={features[feature.key as keyof FeatureToggles]}
                    onChange={(e) => handleToggleFeature(feature.key as keyof FeatureToggles, e.target.checked)}
                    disabled={!tenantInfo?.is_whitelisted}
                    className="sr-only peer"
                  />
                  <div className={`w-11 h-6 bg-default-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-default-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-default-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-default-600 peer-checked:bg-success-600 ${!tenantInfo?.is_whitelisted ? 'opacity-50 cursor-not-allowed' : ''}`}></div>
                </label>
              </div>
            );
          })}
        </CardBody>
      </Card>

      {/* Partnerships */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">Active Partnerships ({partnerships.length})</h3>
        </CardHeader>
        <CardBody>
          {partnerships.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-default-200">
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Partner</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Level</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Status</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Created</th>
                  </tr>
                </thead>
                <tbody>
                  {partnerships.map(partnership => (
                    <tr key={partnership.id} className="border-b border-default-100">
                      <td className="py-3 px-4">
                        <Link
                          to={tenantPath(`/admin/super/federation/tenant/${partnership.partner_id}/features`, null)}
                          className="text-primary hover:underline"
                        >
                          {partnership.partner_name}
                        </Link>
                      </td>
                      <td className="py-3 px-4">
                        <Chip
                          size="sm"
                          color={getLevelColor(partnership.level)}
                          variant="flat"
                        >
                          L{partnership.level}
                        </Chip>
                      </td>
                      <td className="py-3 px-4">
                        <Chip
                          size="sm"
                          color={getStatusColor(partnership.status)}
                          variant="flat"
                        >
                          {partnership.status}
                        </Chip>
                      </td>
                      <td className="py-3 px-4 text-sm text-default-600">
                        {new Date(partnership.created_at).toLocaleDateString()}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="text-sm text-default-500 text-center py-8">No active partnerships</p>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
