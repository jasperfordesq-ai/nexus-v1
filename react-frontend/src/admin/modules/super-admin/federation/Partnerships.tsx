// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { Card, CardBody, CardHeader, Button, Chip, Tabs, Tab } from '@heroui/react';
import { TrendingUp, Users, MessageSquare, DollarSign, FileText, Calendar, UsersRound, Pause, XCircle } from 'lucide-react';
import PageHeader from '../../../components/PageHeader';
import ConfirmModal from '../../../components/ConfirmModal';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';

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

export default function Partnerships() {
  usePageTitle('Partnerships');
  const toast = useToast();
  const [partnerships, setPartnerships] = useState<Partnership[]>([]);
  const [stats] = useState<Stats>({ active: 0, pending: 0, suspended: 0, terminated: 0 });
  const [filter, setFilter] = useState<PartnershipStatus>('all');
  const [loading, setLoading] = useState(true);
  const [actionPartnership, setActionPartnership] = useState<{ id: number; action: 'suspend' | 'terminate' } | null>(null);

  useEffect(() => {
    // TODO: Replace with adminApi.listPartnerships()
    setLoading(false);
  }, []);

  const handleSuspend = async (id: number) => {
    // TODO: Replace with adminApi.suspendPartnership(id)
    setPartnerships(prev => prev.map(p => p.id === id ? { ...p, status: 'suspended' as const } : p));
    setActionPartnership(null);
    toast.success('Partnership suspended');
  };

  const handleTerminate = async (id: number) => {
    // TODO: Replace with adminApi.terminatePartnership(id)
    setPartnerships(prev => prev.map(p => p.id === id ? { ...p, status: 'terminated' as const } : p));
    setActionPartnership(null);
    toast.success('Partnership terminated');
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
          {filteredPartnerships.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-default-200">
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Partnership</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Level</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Features</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Status</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Created</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredPartnerships.map(partnership => (
                    <tr key={partnership.id} className="border-b border-default-100">
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-2">
                          <span className="font-medium">{partnership.tenant_a_name}</span>
                          <span className="text-default-500">↔</span>
                          <span className="font-medium">{partnership.tenant_b_name}</span>
                        </div>
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
                        <div className="flex items-center gap-2">
                          {Object.entries(partnership.features).map(([key, enabled]) =>
                            enabled ? (
                              <span key={key} title={key}>
                                {getFeatureIcon(key)}
                              </span>
                            ) : null
                          )}
                        </div>
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
                      <td className="py-3 px-4">
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
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="text-sm text-default-500 text-center py-8">
              No {filter !== 'all' ? filter : ''} partnerships found
            </p>
          )}
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
