// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState, useCallback } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Card, CardBody, CardHeader, Chip, Switch, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell } from '@heroui/react';
import { Users, MessageSquare, DollarSign, FileText, Calendar, UsersRound, AlertCircle, Shield } from 'lucide-react';
import PageHeader from '../../components/PageHeader';
import { useTenant } from '@/contexts/TenantContext';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminSuper } from '../../api/adminApi';
import type { TenantFederationFeatures as TenantFederationFeaturesType, FederationPartnership } from '../../api/types';

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

function mapPartnership(p: FederationPartnership, currentTenantId: number): Partnership {
  const isFirst = p.tenant_1_id === currentTenantId;
  return {
    id: p.id,
    partner_id: isFirst ? p.tenant_2_id : p.tenant_1_id,
    partner_name: isFirst ? p.tenant_2_name : p.tenant_1_name,
    level: 1,
    status: p.status,
    created_at: p.created_at,
  };
}

export default function FederationTenantFeatures() {
  const { tenantId } = useParams<{ tenantId: string }>();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [tenantInfo, setTenantInfo] = useState<TenantInfo | null>(null);
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
  const [partnerships, setPartnerships] = useState<Partnership[]>([]);
  const [loading, setLoading] = useState(true);

  usePageTitle(tenantInfo ? `${tenantInfo.name} - Federation Features` : 'Tenant Features');

  const loadData = useCallback(async () => {
    if (!tenantId) return;
    setLoading(true);
    const res = await adminSuper.getTenantFederationFeatures(Number(tenantId));
    if (res.success && res.data) {
      const data = res.data as TenantFederationFeaturesType;
      setTenantInfo({
        id: data.tenant_id,
        name: data.tenant_name,
        domain: '',
        is_whitelisted: data.is_whitelisted,
        partnerships_count: data.partnerships?.length || 0,
      });
      setFeatures({
        profiles: data.features?.profiles ?? false,
        messaging: data.features?.messaging ?? false,
        transactions: data.features?.transactions ?? false,
        listings: data.features?.listings ?? false,
        events: data.features?.events ?? false,
        groups: data.features?.groups ?? false,
        analytics: data.features?.analytics ?? false,
        webhooks: data.features?.webhooks ?? false,
      });
      if (data.partnerships) {
        setPartnerships(data.partnerships.map(p => mapPartnership(p, data.tenant_id)));
      }
    }
    setLoading(false);
  }, [tenantId]);

  useEffect(() => { loadData(); }, [loadData]);

  const handleToggleFeature = async (key: keyof FeatureToggles, value: boolean) => {
    if (!tenantInfo?.is_whitelisted) {
      toast.error('Tenant must be whitelisted to enable features');
      return;
    }
    if (!tenantId) return;

    const res = await adminSuper.updateTenantFederationFeature(Number(tenantId), key, value);
    if (res.success) {
      setFeatures(prev => ({ ...prev, [key]: value }));
      toast.success(`${key} ${value ? 'enabled' : 'disabled'}`);
    } else {
      toast.error(res.error || 'Failed to update feature');
    }
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
                <Switch
                  isSelected={features[feature.key as keyof FeatureToggles]}
                  onValueChange={(value) => handleToggleFeature(feature.key as keyof FeatureToggles, value)}
                  isDisabled={!tenantInfo?.is_whitelisted}
                  size="sm"
                />
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
          <Table aria-label="Active partnerships" shadow="sm" isStriped>
            <TableHeader>
              <TableColumn>Partner</TableColumn>
              <TableColumn>Level</TableColumn>
              <TableColumn>Status</TableColumn>
              <TableColumn>Created</TableColumn>
            </TableHeader>
            <TableBody emptyContent="No active partnerships">
              {partnerships.map(partnership => (
                <TableRow key={partnership.id}>
                  <TableCell>
                    <Link
                      to={tenantPath(`/admin/super/federation/tenant/${partnership.partner_id}/features`)}
                      className="text-primary hover:underline"
                    >
                      {partnership.partner_name}
                    </Link>
                  </TableCell>
                  <TableCell>
                    <Chip size="sm" color={getLevelColor(partnership.level)} variant="flat">
                      L{partnership.level}
                    </Chip>
                  </TableCell>
                  <TableCell>
                    <Chip size="sm" color={getStatusColor(partnership.status)} variant="flat">
                      {partnership.status}
                    </Chip>
                  </TableCell>
                  <TableCell className="text-sm text-default-600">
                    {new Date(partnership.created_at).toLocaleDateString()}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>
    </div>
  );
}
