/**
 * Federation Tenant Features
 * Manage federation-specific features for an individual tenant:
 * whitelist status, feature toggles, and partnerships.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Card, CardBody, CardHeader, Button, Switch, Chip, Divider, Spinner,
} from '@heroui/react';
import { useParams } from 'react-router-dom';
import {
  Shield, Network, UserCheck, MessageSquare, ArrowLeftRight,
  LayoutList, Calendar, Users, Plus, Minus,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader, StatusBadge, ConfirmModal } from '../../components';
import type { FederationWhitelistEntry, FederationPartnership } from '../../api/types';

interface TenantFeatureSet {
  cross_tenant_profiles_enabled: boolean;
  cross_tenant_messaging_enabled: boolean;
  cross_tenant_transactions_enabled: boolean;
  cross_tenant_listings_enabled: boolean;
  cross_tenant_events_enabled: boolean;
  cross_tenant_groups_enabled: boolean;
}

interface TenantInfo {
  id: number;
  name: string;
  slug: string;
  domain: string;
}

const FEATURE_TOGGLES: Array<{
  key: keyof TenantFeatureSet;
  label: string;
  description: string;
  icon: typeof Shield;
}> = [
  { key: 'cross_tenant_profiles_enabled', label: 'Cross-Tenant Profiles', description: 'Allow member profiles to be visible across tenants', icon: UserCheck },
  { key: 'cross_tenant_messaging_enabled', label: 'Cross-Tenant Messaging', description: 'Allow messaging between members of different tenants', icon: MessageSquare },
  { key: 'cross_tenant_transactions_enabled', label: 'Cross-Tenant Transactions', description: 'Allow time credit transfers between tenants', icon: ArrowLeftRight },
  { key: 'cross_tenant_listings_enabled', label: 'Cross-Tenant Listings', description: 'Allow listings to appear in federated search', icon: LayoutList },
  { key: 'cross_tenant_events_enabled', label: 'Cross-Tenant Events', description: 'Allow events to be shared across tenants', icon: Calendar },
  { key: 'cross_tenant_groups_enabled', label: 'Cross-Tenant Groups', description: 'Allow groups to accept cross-tenant members', icon: Users },
];

export function FederationTenantFeatures() {
  const { tenantId } = useParams<{ tenantId: string }>();
  const numericTenantId = Number(tenantId);
  const toast = useToast();

  const [tenant, setTenant] = useState<TenantInfo | null>(null);
  const [features, setFeatures] = useState<TenantFeatureSet | null>(null);
  const [whitelisted, setWhitelisted] = useState(false);
  const [partnerships, setPartnerships] = useState<FederationPartnership[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState<string | null>(null);
  const [partnerAction, setPartnerAction] = useState<{ type: 'suspend' | 'terminate'; id: number } | null>(null);

  usePageTitle(tenant ? `Federation Features - ${tenant.name}` : 'Federation Tenant Features');

  const loadData = useCallback(async () => {
    if (!numericTenantId || isNaN(numericTenantId)) return;
    setLoading(true);

    const [tenantRes, featuresRes, whitelistRes, partnershipsRes] = await Promise.all([
      adminSuper.getTenant(numericTenantId),
      adminSuper.getTenantFederationFeatures(numericTenantId),
      adminSuper.getWhitelist(),
      adminSuper.getFederationPartnerships(),
    ]);

    if (tenantRes.success && tenantRes.data) {
      const t = tenantRes.data;
      setTenant({ id: t.id, name: t.name, slug: t.slug, domain: t.domain });
    }

    if (featuresRes.success && featuresRes.data) {
      const raw = featuresRes.data as unknown as { features?: Record<string, boolean> };
      if (raw.features) {
        setFeatures({
          cross_tenant_profiles_enabled: raw.features.cross_tenant_profiles_enabled ?? false,
          cross_tenant_messaging_enabled: raw.features.cross_tenant_messaging_enabled ?? false,
          cross_tenant_transactions_enabled: raw.features.cross_tenant_transactions_enabled ?? false,
          cross_tenant_listings_enabled: raw.features.cross_tenant_listings_enabled ?? false,
          cross_tenant_events_enabled: raw.features.cross_tenant_events_enabled ?? false,
          cross_tenant_groups_enabled: raw.features.cross_tenant_groups_enabled ?? false,
        });
      }
    } else {
      // Default all off if endpoint returns nothing
      setFeatures({
        cross_tenant_profiles_enabled: false,
        cross_tenant_messaging_enabled: false,
        cross_tenant_transactions_enabled: false,
        cross_tenant_listings_enabled: false,
        cross_tenant_events_enabled: false,
        cross_tenant_groups_enabled: false,
      });
    }

    if (whitelistRes.success && whitelistRes.data) {
      const list = Array.isArray(whitelistRes.data) ? whitelistRes.data : [];
      setWhitelisted(list.some((e: FederationWhitelistEntry) => e.tenant_id === numericTenantId));
    }

    if (partnershipsRes.success && partnershipsRes.data) {
      const all = Array.isArray(partnershipsRes.data) ? partnershipsRes.data : [];
      setPartnerships(
        all.filter(
          (p: FederationPartnership) =>
            p.tenant_1_id === numericTenantId || p.tenant_2_id === numericTenantId,
        ),
      );
    }

    setLoading(false);
  }, [numericTenantId]);

  useEffect(() => { loadData(); }, [loadData]);

  const toggleFeature = async (featureKey: keyof TenantFeatureSet, enabled: boolean) => {
    setSaving(featureKey);
    const res = await adminSuper.updateTenantFederationFeature(numericTenantId, featureKey, enabled);
    if (res?.success) {
      toast.success(`${enabled ? 'Enabled' : 'Disabled'} ${featureKey.replace(/_/g, ' ')}`);
      setFeatures((prev) => prev ? { ...prev, [featureKey]: enabled } : prev);
    } else {
      toast.error('Failed to update feature');
    }
    setSaving(null);
  };

  const toggleWhitelist = async () => {
    setSaving('whitelist');
    if (whitelisted) {
      const res = await adminSuper.removeFromWhitelist(numericTenantId);
      if (res?.success) {
        toast.success('Removed from whitelist');
        setWhitelisted(false);
      } else {
        toast.error('Failed to remove from whitelist');
      }
    } else {
      const res = await adminSuper.addToWhitelist(numericTenantId);
      if (res?.success) {
        toast.success('Added to whitelist');
        setWhitelisted(true);
      } else {
        toast.error('Failed to add to whitelist');
      }
    }
    setSaving(null);
  };

  const handlePartnerAction = async () => {
    if (!partnerAction) return;
    const res = partnerAction.type === 'suspend'
      ? await adminSuper.suspendPartnership(partnerAction.id, 'Suspended by super admin')
      : await adminSuper.terminatePartnership(partnerAction.id, 'Terminated by super admin');
    if (res?.success) {
      toast.success(`Partnership ${partnerAction.type}d`);
      loadData();
    } else {
      toast.error('Action failed');
    }
    setPartnerAction(null);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center p-16">
        <Spinner size="lg" label="Loading tenant federation features..." />
      </div>
    );
  }

  if (!tenant) {
    return (
      <div className="p-8 text-center text-default-400">
        Tenant not found (ID: {tenantId})
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={`Federation Features: ${tenant.name}`}
        description="Manage federation capabilities for this tenant"
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Tenant Info */}
        <Card>
          <CardHeader className="flex gap-2 items-center">
            <Network size={20} />
            <h3 className="font-semibold">Tenant Information</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <div className="flex justify-between items-center">
              <span className="text-default-500">Name</span>
              <span className="font-medium">{tenant.name}</span>
            </div>
            <Divider />
            <div className="flex justify-between items-center">
              <span className="text-default-500">Slug</span>
              <Chip size="sm" variant="flat">{tenant.slug}</Chip>
            </div>
            <Divider />
            <div className="flex justify-between items-center">
              <span className="text-default-500">Domain</span>
              <span className="text-sm">{tenant.domain || 'N/A'}</span>
            </div>
            <Divider />
            <div className="flex justify-between items-center">
              <span className="text-default-500">Tenant ID</span>
              <Chip size="sm" variant="flat" color="primary">{tenant.id}</Chip>
            </div>
          </CardBody>
        </Card>

        {/* Whitelist Status */}
        <Card>
          <CardHeader className="flex gap-2 items-center">
            <Shield size={20} />
            <h3 className="font-semibold">Whitelist Status</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
              <span>Federation Whitelist</span>
              <Chip
                color={whitelisted ? 'success' : 'default'}
                variant="flat"
                size="lg"
              >
                {whitelisted ? 'Whitelisted' : 'Not Whitelisted'}
              </Chip>
            </div>
            <p className="text-sm text-default-400">
              {whitelisted
                ? 'This tenant is on the federation whitelist and can participate in cross-tenant features when whitelist mode is active.'
                : 'This tenant is not on the federation whitelist. They will be excluded from federation when whitelist mode is active.'}
            </p>
            <Button
              color={whitelisted ? 'danger' : 'success'}
              variant="flat"
              startContent={whitelisted ? <Minus size={16} /> : <Plus size={16} />}
              isLoading={saving === 'whitelist'}
              onPress={toggleWhitelist}
            >
              {whitelisted ? 'Remove from Whitelist' : 'Add to Whitelist'}
            </Button>
          </CardBody>
        </Card>

        {/* Feature Toggles */}
        <Card className="lg:col-span-2">
          <CardHeader className="flex gap-2 items-center">
            <Shield size={20} />
            <h3 className="font-semibold">Federation Feature Toggles</h3>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {features && FEATURE_TOGGLES.map(({ key, label, description, icon: Icon }) => (
                <div
                  key={key}
                  className="flex items-center justify-between p-3 rounded-lg bg-default-50 dark:bg-default-100/50"
                >
                  <div className="flex items-center gap-3 min-w-0 flex-1">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <Icon size={18} />
                    </div>
                    <div className="min-w-0">
                      <p className="font-medium text-sm">{label}</p>
                      <p className="text-xs text-default-400 truncate">{description}</p>
                    </div>
                  </div>
                  <Switch
                    size="sm"
                    isSelected={features[key]}
                    isDisabled={saving === key}
                    onValueChange={(v) => toggleFeature(key, v)}
                  />
                </div>
              ))}
            </div>
          </CardBody>
        </Card>

        {/* Partnerships */}
        <Card className="lg:col-span-2">
          <CardHeader className="flex gap-2 items-center">
            <Network size={20} />
            <h3 className="font-semibold">Partnerships ({partnerships.length})</h3>
          </CardHeader>
          <CardBody>
            {partnerships.length === 0 ? (
              <p className="text-default-400 text-sm text-center py-4">
                No federation partnerships involving this tenant.
              </p>
            ) : (
              <div className="flex flex-col gap-2">
                {partnerships.map((p) => {
                  const partnerName =
                    p.tenant_1_id === numericTenantId ? p.tenant_2_name : p.tenant_1_name;
                  return (
                    <div
                      key={p.id}
                      className="flex items-center justify-between py-3 px-2 border-b last:border-b-0"
                    >
                      <div className="flex items-center gap-3">
                        <span className="font-medium">{partnerName}</span>
                        <StatusBadge status={p.status} />
                        <span className="text-xs text-default-400">
                          Since {new Date(p.created_at).toLocaleDateString()}
                        </span>
                      </div>
                      {p.status === 'active' && (
                        <div className="flex gap-2">
                          <Button
                            size="sm"
                            variant="flat"
                            color="warning"
                            onPress={() => setPartnerAction({ type: 'suspend', id: p.id })}
                          >
                            Suspend
                          </Button>
                          <Button
                            size="sm"
                            variant="flat"
                            color="danger"
                            onPress={() => setPartnerAction({ type: 'terminate', id: p.id })}
                          >
                            Terminate
                          </Button>
                        </div>
                      )}
                      {p.status === 'suspended' && (
                        <Chip size="sm" variant="flat" color="warning">Suspended</Chip>
                      )}
                      {p.status === 'terminated' && (
                        <Chip size="sm" variant="flat" color="danger">Terminated</Chip>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </CardBody>
        </Card>
      </div>

      <ConfirmModal
        isOpen={!!partnerAction}
        onClose={() => setPartnerAction(null)}
        onConfirm={handlePartnerAction}
        title={partnerAction ? `${partnerAction.type === 'suspend' ? 'Suspend' : 'Terminate'} Partnership` : ''}
        message={`Are you sure you want to ${partnerAction?.type} this partnership?`}
        confirmLabel={partnerAction?.type === 'suspend' ? 'Suspend' : 'Terminate'}
        confirmColor="danger"
      />
    </div>
  );
}

export default FederationTenantFeatures;
