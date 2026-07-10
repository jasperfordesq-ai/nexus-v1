// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation API Keys
 * Manage API keys for federation integrations.
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useState, useCallback, useEffect } from 'react';import Key from 'lucide-react/icons/key';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Ban from 'lucide-react/icons/ban';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { adminFederation } from '../../api/adminApi';
import { DataTable, type Column } from '../../components/DataTable';
import { PageHeader } from '../../components/PageHeader';
import { EmptyState } from '../../components/EmptyState';
import { ConfirmModal } from '../../components/ConfirmModal';

import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui';
import { BrokerStatusChip } from '@/broker/components';
import { PartnerTimebankGuidance } from './PartnerTimebankGuidance';
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

interface ApiKeysProps {
  /**
   * Embedded mode (e.g. the Partner Timebanks panel): open the create
   * flow in the host's drawer instead of navigating to the create route.
   */
  onCreateClick?: () => void;
  /** Bumped by the host after a drawer-based create to refresh the list. */
  refreshToken?: number;
}

export function ApiKeys({ onCreateClick, refreshToken }: ApiKeysProps = {}) {
  const { t } = useTranslation('admin_federation');
  usePageTitle(t('federation.page_title'));
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const openCreate = onCreateClick ?? (() => navigate(tenantPath('/partner-timebanks/api-keys/create')));
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
        toast.success(t('federation.key_revoked'));
        setItems(prev => prev.map(k => k.id === id ? { ...k, status: 'revoked' } : k));
      }
    } catch (err) {
      logError('ApiKeys: failed to revoke key', err);
      toast.error(t('federation.revoke_failed'));
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

  useEffect(() => { loadData(); }, [loadData, refreshToken]);

  const columns: Column<ApiKey>[] = [
    { key: 'name', label: t('federation.col_key_name'), sortable: true },
    {
      key: 'key_prefix', label: t('federation.col_prefix'),
      render: (item) => <code className="text-xs bg-surface-secondary px-1.5 py-0.5 rounded">{item.key_prefix}...</code>,
    },
    {
      key: 'status', label: t('federation.col_status'),
      render: (item) => {
        const s = item.status === 'revoked' ? 'revoked' : (item.expires_at && new Date(item.expires_at) < new Date()) ? 'expired' : 'active';
        return <BrokerStatusChip status={s} />;
      },
    },
    {
      key: 'scopes', label: t('federation.col_scopes'),
      render: (item) => <span className="text-sm text-muted">{Array.isArray(item.scopes) ? item.scopes.join(', ') : '--'}</span>,
    },
    {
      key: 'expires_at', label: t('federation.col_expires'),
      render: (item) => {
        if (!item.expires_at) return <span className="text-sm text-muted">{t('federation.never_expires')}</span>;
        const isExpired = new Date(item.expires_at) < new Date();
        return <span className={`text-sm ${isExpired ? 'text-warning' : 'text-muted'}`}>{new Date(item.expires_at).toLocaleDateString(getFormattingLocale())}</span>;
      },
    },
    {
      key: 'last_used_at', label: t('federation.col_last_used'),
      render: (item) => <span className="text-sm text-muted">{item.last_used_at ? new Date(item.last_used_at).toLocaleDateString(getFormattingLocale()) : t('federation.never')}</span>,
    },
    {
      key: 'created_at', label: t('federation.col_created'), sortable: true,
      render: (item) => <span className="text-sm text-muted">{item.created_at ? new Date(item.created_at).toLocaleDateString(getFormattingLocale()) : '--'}</span>,
    },
    {
      key: 'actions' as keyof ApiKey, label: '',
      render: (item) => item.status === 'active' ? (
        <Button
          size="sm"
          variant="danger"
          startContent={<Ban size={14} />}
          isLoading={revokingId === item.id}
          onPress={() => setRevokeTarget(item)}
        >
          {t('federation.revoke')}
        </Button>
      ) : null,
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader
          title={t('federation.api_keys_title')}
          description={t('federation.api_keys_desc')}
          actions={<Button startContent={<Plus size={16} />} onPress={openCreate}>{t('federation.create_key')}</Button>}
        />
        <PartnerTimebankGuidance page="apiKeys" />
        <EmptyState icon={Key} title={t('federation.no_api_keys')} description={t('federation.no_api_keys_desc')} actionLabel={t('federation.create_api_key_action')} onAction={openCreate} />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('federation.api_keys_title')}
        description={t('federation.api_keys_desc')}
        actions={
          <div className="flex gap-2">
            <Button variant="tertiary" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{t('common.refresh')}</Button>
            <Button startContent={<Plus size={16} />} onPress={openCreate}>{t('federation.create_key')}</Button>
          </div>
        }
      />
      <PartnerTimebankGuidance page="apiKeys" />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />

      {revokeTarget && (
        <ConfirmModal
          isOpen={!!revokeTarget}
          onClose={() => setRevokeTarget(null)}
          onConfirm={confirmRevoke}
          title={t('federation.revoke_key_title')}
          message={t('federation.confirm_revoke')}
          confirmLabel={t('federation.revoke')}
          confirmColor="danger"
          isLoading={revokingId === revokeTarget.id}
        />
      )}
    </div>
  );
}

export default ApiKeys;
