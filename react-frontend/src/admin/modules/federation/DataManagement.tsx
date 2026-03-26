// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Data Management
 * Import and export federation data (users, partnerships, transactions, audit).
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, Button } from '@heroui/react';
import { Database, Download, Upload, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';
import { API_BASE } from '@/lib/api';

import { useTranslation } from 'react-i18next';
interface DataConfig {
  export_formats: string[];
  available_exports: Record<string, string>;
  import_supported: boolean;
  last_export_at: string | null;
  last_import_at: string | null;
}

export function DataManagement() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation.page_title'));
  const [config, setConfig] = useState<DataConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [exportingType, setExportingType] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getDataManagement();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          setConfig((payload as { data: DataConfig }).data);
        } else {
          setConfig(payload as DataConfig);
        }
      }
    } catch {
      setConfig(null);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const handleExport = useCallback(async (type: string) => {
    setExportingType(type);
    try {
      const token = localStorage.getItem('nexus_access_token');
      const tenantId = localStorage.getItem('nexus_tenant_id');
      const headers: Record<string, string> = {};
      if (token) headers['Authorization'] = `Bearer ${token}`;
      if (tenantId) headers['X-Tenant-ID'] = tenantId;
      const response = await fetch(`${API_BASE}/v2/admin/federation/export/${type}`, {
        headers,
      });
      if (!response.ok) throw new Error('Export failed');
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `federation_${type}_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
    } catch {
      // Silently handle — could add toast here
    }
    setExportingType(null);
  }, []);

  return (
    <div>
      <PageHeader
        title={t('federation.data_management_title')}
        description={t('federation.data_management_desc')}
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{t('federation.refresh')}</Button>}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Download size={20} /> {t('federation.export_data')}</h3></CardHeader>
          <CardBody>
            {config?.available_exports ? (
              <div className="space-y-3">
                {Object.entries(config.available_exports).map(([key, label]) => (
                  <div key={key} className="flex items-center justify-between rounded-lg border border-default-200 p-3">
                    <div>
                      <p className="font-medium capitalize">{key}</p>
                      <p className="text-xs text-default-400">{label}</p>
                    </div>
                    <Button size="sm" variant="flat" startContent={<Download size={14} />} isLoading={exportingType === key} onPress={() => handleExport(key)}>{t('federation.export')}</Button>
                  </div>
                ))}
                <p className="text-xs text-default-400">
                  {t('federation.formats')}: {config.export_formats?.join(', ') || 'JSON, CSV'}
                </p>
                {config.last_export_at && (
                  <p className="text-xs text-default-400">{t('federation.last_export')}: {new Date(config.last_export_at).toLocaleDateString()}</p>
                )}
              </div>
            ) : (
              <div className="flex flex-col items-center py-6 text-default-400">
                <Database size={32} className="mb-2" />
                <p className="text-sm">{t('federation.export_config_loading')}</p>
              </div>
            )}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Upload size={20} /> {t('federation.import_data')}</h3></CardHeader>
          <CardBody>
            <div className="flex flex-col items-center py-8 text-default-400">
              <Upload size={40} className="mb-3" />
              <p className="text-sm font-medium">{t('federation.import_users')}</p>
              <p className="text-xs text-center max-w-xs mt-1">{t('federation.import_users_desc')}</p>
              <Button size="sm" variant="flat" className="mt-4" isDisabled={!config?.import_supported}>
                {t('federation.upload_csv')}
              </Button>
              {config?.last_import_at && (
                <p className="text-xs text-default-400 mt-2">{t('federation.last_import')}: {new Date(config.last_import_at).toLocaleDateString()}</p>
              )}
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default DataManagement;
