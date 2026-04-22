// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation API Keys
 * Manage API keys for federation integrations.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button, Chip } from '@heroui/react';
import { Key, Plus, RefreshCw, Ban } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { adminFederation } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, ConfirmModal, type Column } from '../../components';

import { useTranslation } from 'react-i18next';
interface ApiKey {
  id: number;
  name: string;
  key_prefix: string;
  status: string;
  scopes: string[];
  last_used_at: string | null;
  expires_at: string | null;
  created_at: string;
}

export function ApiKeys() {
  const { t } = useTranslation('admin');
  usePageTitle("Federation");
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [items, setItems] = useState<ApiKey[]>([]);
  const [loading, setLoading] = useState(true);
  const [revokingId, setRevokingId] = useState<number | null>(null);
  const [revokeTarget, setRevokeTarget] = useState<ApiKey | null>(null);

  const confirmRevoke = useCallback(async () => {
    if (!revokeTarget) return;
    const id = revokeTarget.id;
    setRevokingId(id);
    try {
      const res = await adminFederation.revokeApiKey(id);
      if (res.success) {
        toast.success(t('federation.key_revoked', 'API key revoked successfully'));
        setItems(prev => prev.map(k => k.id === id ? { ...k, status: 'revoked' } : k));
      }
    } catch (err) {
      logError('ApiKeys: failed to revoke key', err);
      toast.error(t('federation.revoke_failed', 'Failed to revoke API key'));
    }
    setRevokingId(null);
    setRevokeTarget(null);
  }, [revokeTarget, t, toast]);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getApiKeys();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: ApiKey[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const getStatusColor = (item: ApiKey): 'success' | 'danger' | 'warning' => {
    if (item.status === 'revoked') return 'danger';
    if (item.expires_at && new Date(item.expires_at) < new Date()) return 'warning';
    return 'success';
  };

  const getStatusLabel = (item: ApiKey): string => {
    if (item.status === 'revoked') return t('federation.status_revoked', 'Revoked');
    if (item.expires_at && new Date(item.expires_at) < new Date()) return t('federation.status_expired', 'Expired');
    return t('federation.status_active', 'Active');
  };

  const columns: Column<ApiKey>[] = [
    { key: 'name', label: "Key Name", sortable: true },
    {
      key: 'key_prefix', label: "Prefix",
      render: (item) => <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">{item.key_prefix}...</code>,
    },
    {
      key: 'status', label: "Status",
      render: (item) => (
        <Chip size="sm" variant="flat" color={getStatusColor(item)} className="capitalize">{getStatusLabel(item)}</Chip>
      ),
    },
    {
      key: 'scopes', label: "Scopes",
      render: (item) => <span className="text-sm text-default-500">{Array.isArray(item.scopes) ? item.scopes.join(', ') : '--'}</span>,
    },
    {
      key: 'expires_at', label: t('federation.col_expires', 'Expires'),
      render: (item) => {
        if (!item.expires_at) return <span className="text-sm text-default-400">{t('federation.never_expires', 'Never')}</span>;
        const isExpired = new Date(item.expires_at) < new Date();
        return <span className={`text-sm ${isExpired ? 'text-warning' : 'text-default-500'}`}>{new Date(item.expires_at).toLocaleDateString()}</span>;
      },
    },
    {
      key: 'last_used_at', label: "Last Used",
      render: (item) => <span className="text-sm text-default-500">{item.last_used_at ? new Date(item.last_used_at).toLocaleDateString() : "Never"}</span>,
    },
    {
      key: 'created_at', label: "Created", sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
    {
      key: 'actions' as keyof ApiKey, label: '',
      render: (item) => item.status === 'active' ? (
        <Button
          size="sm"
          variant="flat"
          color="danger"
          startContent={<Ban size={14} />}
          isLoading={revokingId === item.id}
          onPress={() => setRevokeTarget(item)}
        >
          {t('federation.revoke', 'Revoke')}
        </Button>
      ) : null,
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader
          title={"API Keys"}
          description={"Manage API keys for federation integration with external systems"}
          actions={<Button color="primary" startContent={<Plus size={16} />} onPress={() => navigate(tenantPath('/admin/federation/api-keys/create'))}>{"Create Key"}</Button>}
        />
        <EmptyState icon={Key} title={"No API keys"} description={"Create an API key to enable federation integration"} actionLabel={"Create API Key"} onAction={() => navigate(tenantPath('/admin/federation/api-keys/create'))} />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={"API Keys"}
        description={"Manage API keys for federation integration with external systems"}
        actions={
          <div className="flex gap-2">
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{"Refresh"}</Button>
            <Button color="primary" startContent={<Plus size={16} />} onPress={() => navigate(tenantPath('/admin/federation/api-keys/create'))}>{"Create Key"}</Button>
          </div>
        }
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />

      {revokeTarget && (
        <ConfirmModal
          isOpen={!!revokeTarget}
          onClose={() => setRevokeTarget(null)}
          onConfirm={confirmRevoke}
          title={t('federation.revoke_key_title', 'Revoke API Key')}
          message={t('federation.confirm_revoke', 'Are you sure you want to revoke this API key? This cannot be undone.')}
          confirmLabel={t('federation.revoke', 'Revoke')}
          confirmColor="danger"
          isLoading={revokingId === revokeTarget.id}
        />
      )}
    </div>
  );
}

export default ApiKeys;
