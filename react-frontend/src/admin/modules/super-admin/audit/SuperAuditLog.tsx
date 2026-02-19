import React, { useEffect, useState } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Input,
  Select,
  SelectItem,
} from '@heroui/react';
import {
  FileText,
  Search,
  X,
  Plus,
  Edit,
  Trash2,
  Settings,
  Users,
  Building2,
  ChevronDown,
  ChevronUp
} from 'lucide-react';
import PageHeader from '../../../components/PageHeader';
import { usePageTitle } from '@/hooks/usePageTitle';

interface AuditEntry {
  id: number;
  actor_name: string;
  actor_email: string;
  action: string;
  target_type: string;
  target_id: number;
  target_name: string;
  description: string;
  old_values?: Record<string, unknown>;
  new_values?: Record<string, unknown>;
  created_at: string;
}

interface Stats {
  total_actions: number;
  tenant_changes: number;
  user_changes: number;
  active_admins: number;
}

export default function SuperAuditLog() {
  usePageTitle('Super Admin Audit Log');
  const [entries] = useState<AuditEntry[]>([]);
  const [stats] = useState<Stats>({
    total_actions: 0,
    tenant_changes: 0,
    user_changes: 0,
    active_admins: 0,
  });
  const [filters, setFilters] = useState({
    action: 'all',
    target_type: 'all',
    from: '',
    to: '',
    search: '',
  });
  const [expandedEntries, setExpandedEntries] = useState<Set<number>>(new Set());
  const [loading, setLoading] = useState(true);

  usePageTitle('Super Admin Audit Log');

  useEffect(() => {
    // TODO: Replace with adminApi.getAuditStats() and adminApi.listAudit(filters)
    setLoading(false);
  }, [filters]);

  const toggleExpanded = (id: number) => {
    setExpandedEntries(prev => {
      const newSet = new Set(prev);
      if (newSet.has(id)) {
        newSet.delete(id);
      } else {
        newSet.add(id);
      }
      return newSet;
    });
  };

  const getActionIcon = (action: string) => {
    const iconMap: Record<string, { icon: React.ComponentType<{ className?: string }>, color: string }> = {
      tenant_created: { icon: Plus, color: 'text-success' },
      tenant_updated: { icon: Edit, color: 'text-primary' },
      tenant_deleted: { icon: Trash2, color: 'text-danger' },
      tenant_settings_updated: { icon: Settings, color: 'text-warning' },
      user_created: { icon: Plus, color: 'text-success' },
      user_updated: { icon: Edit, color: 'text-primary' },
      user_deleted: { icon: Trash2, color: 'text-danger' },
      user_role_changed: { icon: Users, color: 'text-warning' },
      user_status_changed: { icon: Users, color: 'text-warning' },
      feature_enabled: { icon: Plus, color: 'text-success' },
      feature_disabled: { icon: Trash2, color: 'text-danger' },
      federation_enabled: { icon: Plus, color: 'text-success' },
      federation_disabled: { icon: Trash2, color: 'text-danger' },
    };

    const config = iconMap[action] || { icon: FileText, color: 'text-default-500' };
    const Icon = config.icon;
    return <Icon className={`w-4 h-4 ${config.color}`} />;
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
        title="Super Admin Audit Log"
        description="Track all super admin actions and system changes"
      />

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-secondary-100 dark:bg-secondary-900">
                <FileText className="w-5 h-5 text-secondary" />
              </div>
              <div>
                <p className="text-xs text-default-500">Total Actions</p>
                <p className="text-2xl font-bold text-secondary">{stats.total_actions}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-primary-100 dark:bg-primary-900">
                <Building2 className="w-5 h-5 text-primary" />
              </div>
              <div>
                <p className="text-xs text-default-500">Tenant Changes</p>
                <p className="text-2xl font-bold text-primary">{stats.tenant_changes}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-success-100 dark:bg-success-900">
                <Users className="w-5 h-5 text-success" />
              </div>
              <div>
                <p className="text-xs text-default-500">User Changes</p>
                <p className="text-2xl font-bold text-success">{stats.user_changes}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-warning-100 dark:bg-warning-900">
                <Users className="w-5 h-5 text-warning" />
              </div>
              <div>
                <p className="text-xs text-default-500">Active Admins</p>
                <p className="text-2xl font-bold text-warning">{stats.active_admins}</p>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Filter Bar */}
      <Card>
        <CardBody>
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
            <Select
              label="Action Type"
              selectedKeys={[filters.action]}
              onChange={(e) => setFilters(prev => ({ ...prev, action: e.target.value }))}
              variant="bordered"
            >
              <SelectItem key="all">All Actions</SelectItem>
              <optgroup label="Tenant Actions">
                <SelectItem key="tenant_created">Tenant Created</SelectItem>
                <SelectItem key="tenant_updated">Tenant Updated</SelectItem>
                <SelectItem key="tenant_deleted">Tenant Deleted</SelectItem>
                <SelectItem key="tenant_settings_updated">Settings Updated</SelectItem>
              </optgroup>
              <optgroup label="User Actions">
                <SelectItem key="user_created">User Created</SelectItem>
                <SelectItem key="user_updated">User Updated</SelectItem>
                <SelectItem key="user_deleted">User Deleted</SelectItem>
                <SelectItem key="user_role_changed">Role Changed</SelectItem>
                <SelectItem key="user_status_changed">Status Changed</SelectItem>
              </optgroup>
              <optgroup label="Feature Actions">
                <SelectItem key="feature_enabled">Feature Enabled</SelectItem>
                <SelectItem key="feature_disabled">Feature Disabled</SelectItem>
              </optgroup>
              <optgroup label="Federation Actions">
                <SelectItem key="federation_enabled">Federation Enabled</SelectItem>
                <SelectItem key="federation_disabled">Federation Disabled</SelectItem>
              </optgroup>
            </Select>

            <Select
              label="Target Type"
              selectedKeys={[filters.target_type]}
              onChange={(e) => setFilters(prev => ({ ...prev, target_type: e.target.value }))}
              variant="bordered"
            >
              <SelectItem key="all">All Types</SelectItem>
              <SelectItem key="tenant">Tenant</SelectItem>
              <SelectItem key="user">User</SelectItem>
              <SelectItem key="feature">Feature</SelectItem>
              <SelectItem key="federation">Federation</SelectItem>
            </Select>

            <Input
              label="From Date"
              type="date"
              value={filters.from}
              onValueChange={(value) => setFilters(prev => ({ ...prev, from: value }))}
              variant="bordered"
            />

            <Input
              label="To Date"
              type="date"
              value={filters.to}
              onValueChange={(value) => setFilters(prev => ({ ...prev, to: value }))}
              variant="bordered"
            />

            <Input
              label="Search"
              placeholder="Search actions, actors..."
              value={filters.search}
              onValueChange={(value) => setFilters(prev => ({ ...prev, search: value }))}
              variant="bordered"
              startContent={<Search className="w-4 h-4 text-default-400" />}
              endContent={
                filters.search && (
                  <button onClick={() => setFilters(prev => ({ ...prev, search: '' }))}>
                    <X className="w-4 h-4 text-default-400" />
                  </button>
                )
              }
            />
          </div>
        </CardBody>
      </Card>

      {/* Audit Table */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">Audit Entries ({entries.length})</h3>
        </CardHeader>
        <CardBody>
          {entries.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-default-200">
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Time</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Actor</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Action</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Target</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600">Description</th>
                    <th className="text-left py-2 px-4 text-sm font-medium text-default-600"></th>
                  </tr>
                </thead>
                <tbody>
                  {entries.map(entry => {
                    const isExpanded = expandedEntries.has(entry.id);
                    const hasDetails = entry.old_values || entry.new_values;

                    return (
                      <React.Fragment key={entry.id}>
                        <tr className="border-b border-default-100">
                          <td className="py-3 px-4">
                            <div className="text-sm">
                              <p className="font-medium">
                                {new Date(entry.created_at).toLocaleDateString()}
                              </p>
                              <p className="text-xs text-default-500">
                                {new Date(entry.created_at).toLocaleTimeString()}
                              </p>
                            </div>
                          </td>
                          <td className="py-3 px-4">
                            <div>
                              <p className="font-medium text-sm">{entry.actor_name}</p>
                              <p className="text-xs text-default-500">{entry.actor_email}</p>
                            </div>
                          </td>
                          <td className="py-3 px-4">
                            <div className="flex items-center gap-2">
                              {getActionIcon(entry.action)}
                              <span className="text-sm font-medium">{entry.action.replace(/_/g, ' ')}</span>
                            </div>
                          </td>
                          <td className="py-3 px-4">
                            <div>
                              <p className="font-medium text-sm">{entry.target_name}</p>
                              <p className="text-xs text-default-500">
                                {entry.target_type} #{entry.target_id}
                              </p>
                            </div>
                          </td>
                          <td className="py-3 px-4 max-w-md">
                            <p className="text-sm text-default-600">{entry.description}</p>
                          </td>
                          <td className="py-3 px-4">
                            {hasDetails && (
                              <Button
                                size="sm"
                                variant="flat"
                                onPress={() => toggleExpanded(entry.id)}
                                endContent={
                                  isExpanded ? (
                                    <ChevronUp className="w-4 h-4" />
                                  ) : (
                                    <ChevronDown className="w-4 h-4" />
                                  )
                                }
                              >
                                Details
                              </Button>
                            )}
                          </td>
                        </tr>
                        {isExpanded && hasDetails && (
                          <tr>
                            <td colSpan={6} className="px-4 py-3 bg-default-50 dark:bg-default-900/50">
                              <div className="grid grid-cols-2 gap-4">
                                {entry.old_values && Object.keys(entry.old_values).length > 0 && (
                                  <div>
                                    <p className="text-sm font-medium text-default-600 mb-2">Old Values</p>
                                    <pre className="p-3 bg-white dark:bg-default-800 rounded-lg text-xs overflow-auto">
                                      {JSON.stringify(entry.old_values, null, 2)}
                                    </pre>
                                  </div>
                                )}
                                {entry.new_values && Object.keys(entry.new_values).length > 0 && (
                                  <div>
                                    <p className="text-sm font-medium text-default-600 mb-2">New Values</p>
                                    <pre className="p-3 bg-white dark:bg-default-800 rounded-lg text-xs overflow-auto">
                                      {JSON.stringify(entry.new_values, null, 2)}
                                    </pre>
                                  </div>
                                )}
                              </div>
                            </td>
                          </tr>
                        )}
                      </React.Fragment>
                    );
                  })}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="text-sm text-default-500 text-center py-8">No audit entries found</p>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
