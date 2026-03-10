// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState, useCallback } from 'react';
import { Card, CardBody, CardHeader, Button, Chip, Tabs, Tab, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell } from '@heroui/react';
import { TrendingUp, Users, MessageSquare, DollarSign, FileText, Calendar, UsersRound, Pause, XCircle } from 'lucide-react';
import PageHeader from '../../components/PageHeader';
import ConfirmModal from '../../components/ConfirmModal';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminSuper } from '../../api/adminApi';
import type { FederationPartnership } from '../../api/types';

interface Partnership {
  id: number;
  tenant_a_id: number;
  tenant_a_name: string;
  tenant_b_id: number;
  tenant_b_name: string;
  level: number;
  status: 'active' | 'pending' | 'suspended' | 'terminated';
  features: {
    profiles: boolean;
    messaging: boolean;
    transactions: boolean;
    listings: boolean;
    events: boolean;
    groups: boolean;
  };
  created_at: string;
}

interface Stats {
  active: number;
  pending: number;
  suspended: number;
  terminated: number;
}

type PartnershipStatus = 'all' | 'active' | 'pending' | 'suspended' | 'terminated';

function mapApiPartnership(p: FederationPartnership): Partnership {
  return {
    id: p.id,
    tenant_a_id: p.tenant_1_id,
    tenant_a_name: p.tenant_1_name,
    tenant_b_id: p.tenant_2_id,
    tenant_b_name: p.tenant_2_name,
    level: 1,
    status: p.status,
    features: { profiles: false, messaging: false, transactions: false, listings: false, events: false, groups: false },
    created_at: p.created_at,
  };
}

function computeStats(partnerships: Partnership[]): Stats {
  return partnerships.reduce(
    (acc, p) => {
      if (p.status in acc) acc[p.status as keyof Stats]++;
      return acc;
    },
    { active: 0, pending: 0, suspended: 0, terminated: 0 },
  );
}

export default function Partnerships() {
  usePageTitle('Partnerships');
  const toast = useToast();
  const [partnerships, setPartnerships] = useState<Partnership[]>([]);
  const [stats, setStats] = useState<Stats>({ active: 0, pending: 0, suspended: 0, terminated: 0 });
  const [filter, setFilter] = useState<PartnershipStatus>('all');
  const [loading, setLoading] = useState(true);
  const [actionPartnership, setActionPartnership] = useState<{ id: number; action: 'suspend' | 'terminate' } | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    const res = await adminSuper.getFederationPartnerships();
    if (res.success && res.data) {
      const mapped = (Array.isArray(res.data) ? res.data : []).map(mapApiPartnership);
      setPartnerships(mapped);
      setStats(computeStats(mapped));
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const handleSuspend = async (id: number) => {
    const res = await adminSuper.suspendPartnership(id, 'Suspended by admin');
    if (res.success) {
      setPartnerships(prev => {
        const updated = prev.map(p => p.id === id ? { ...p, status: 'suspended' as const } : p);
        setStats(computeStats(updated));
        return updated;
      });
      setActionPartnership(null);
      toast.success('Partnership suspended');
    } else {
      toast.error(res.error || 'Failed to suspend partnership');
    }
  };

  const handleTerminate = async (id: number) => {
    const res = await adminSuper.terminatePartnership(id, 'Terminated by admin');
    if (res.success) {
      setPartnerships(prev => {
        const updated = prev.map(p => p.id === id ? { ...p, status: 'terminated' as const } : p);
        setStats(computeStats(updated));
        return updated;
      });
      setActionPartnership(null);
      toast.success('Partnership terminated');
    } else {
      toast.error(res.error || 'Failed to terminate partnership');
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

  const getFeatureIcon = (feature: string) => {
    switch (feature) {
      case 'profiles': return <Users className="w-4 h-4 text-primary" />;
      case 'messaging': return <MessageSquare className="w-4 h-4 text-success" />;
      case 'transactions': return <DollarSign className="w-4 h-4 text-secondary" />;
      case 'listings': return <FileText className="w-4 h-4 text-warning" />;
      case 'events': return <Calendar className="w-4 h-4 text-pink-500" />;
      case 'groups': return <UsersRound className="w-4 h-4 text-cyan-500" />;
      default: return null;
    }
  };

  const filteredPartnerships = filter === 'all'
    ? partnerships
    : partnerships.filter(p => p.status === filter);

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
        title="Federation Partnerships"
        description="Manage cross-community partnerships"
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-success-100 dark:bg-success-900">
                <TrendingUp className="w-5 h-5 text-success" />
              </div>
              <div>
                <p className="text-xs text-default-500">Active</p>
                <p className="text-2xl font-bold text-success">{stats.active}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-warning-100 dark:bg-warning-900">
                <TrendingUp className="w-5 h-5 text-warning" />
              </div>
              <div>
                <p className="text-xs text-default-500">Pending</p>
                <p className="text-2xl font-bold text-warning">{stats.pending}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-danger-100 dark:bg-danger-900">
                <Pause className="w-5 h-5 text-danger" />
              </div>
              <div>
                <p className="text-xs text-default-500">Suspended</p>
                <p className="text-2xl font-bold text-danger">{stats.suspended}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-default-100">
                <XCircle className="w-5 h-5 text-default-500" />
              </div>
              <div>
                <p className="text-xs text-default-500">Terminated</p>
                <p className="text-2xl font-bold">{stats.terminated}</p>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Partnerships Table */}
      <Card>
        <CardHeader>
          <Tabs
            selectedKey={filter}
            onSelectionChange={(key) => setFilter(key as PartnershipStatus)}
          >
            <Tab key="all" title={`All (${partnerships.length})`} />
            <Tab key="active" title={`Active (${stats.active})`} />
            <Tab key="pending" title={`Pending (${stats.pending})`} />
            <Tab key="suspended" title={`Suspended (${stats.suspended})`} />
            <Tab key="terminated" title={`Terminated (${stats.terminated})`} />
          </Tabs>
        </CardHeader>
        <CardBody>
          <Table aria-label="Federation partnerships" shadow="sm" isStriped>
            <TableHeader>
              <TableColumn>Partnership</TableColumn>
              <TableColumn>Level</TableColumn>
              <TableColumn>Features</TableColumn>
              <TableColumn>Status</TableColumn>
              <TableColumn>Created</TableColumn>
              <TableColumn>Actions</TableColumn>
            </TableHeader>
            <TableBody emptyContent={`No ${filter !== 'all' ? filter : ''} partnerships found`}>
              {filteredPartnerships.map(partnership => (
                <TableRow key={partnership.id}>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <span className="font-medium">{partnership.tenant_a_name}</span>
                      <span className="text-default-500">↔</span>
                      <span className="font-medium">{partnership.tenant_b_name}</span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Chip size="sm" color={getLevelColor(partnership.level)} variant="flat">
                      L{partnership.level}
                    </Chip>
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      {Object.entries(partnership.features).map(([key, enabled]) =>
                        enabled ? (
                          <span key={key} title={key}>
                            {getFeatureIcon(key)}
                          </span>
                        ) : null
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Chip size="sm" color={getStatusColor(partnership.status)} variant="flat">
                      {partnership.status}
                    </Chip>
                  </TableCell>
                  <TableCell className="text-sm text-default-600">
                    {new Date(partnership.created_at).toLocaleDateString()}
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      {partnership.status === 'active' && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="warning"
                          onPress={() => setActionPartnership({ id: partnership.id, action: 'suspend' })}
                          startContent={<Pause className="w-4 h-4" />}
                        >
                          Suspend
                        </Button>
                      )}
                      {(partnership.status === 'active' || partnership.status === 'suspended') && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="danger"
                          onPress={() => setActionPartnership({ id: partnership.id, action: 'terminate' })}
                          startContent={<XCircle className="w-4 h-4" />}
                        >
                          Terminate
                        </Button>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      {/* Action Confirmation Modal */}
      {actionPartnership && (
        <ConfirmModal
          isOpen={true}
          onClose={() => setActionPartnership(null)}
          onConfirm={() => {
            if (actionPartnership.action === 'suspend') {
              handleSuspend(actionPartnership.id);
            } else {
              handleTerminate(actionPartnership.id);
            }
          }}
          title={actionPartnership.action === 'suspend' ? 'Suspend Partnership' : 'Terminate Partnership'}
          message={
            actionPartnership.action === 'suspend'
              ? 'Are you sure you want to suspend this partnership? All federation features will be temporarily disabled.'
              : 'Are you sure you want to terminate this partnership? This action cannot be undone.'
          }
          confirmLabel={actionPartnership.action === 'suspend' ? 'Suspend' : 'Terminate'}
          confirmColor="danger"
        />
      )}
    </div>
  );
}
