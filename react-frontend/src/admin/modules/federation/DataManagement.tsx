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

interface DataConfig {
  export_formats: string[];
  available_exports: Record<string, string>;
  import_supported: boolean;
  last_export_at: string | null;
  last_import_at: string | null;
}

export function DataManagement() {
  usePageTitle('Admin - Federation Data Management');
  const [config, setConfig] = useState<DataConfig | null>(null);
  const [loading, setLoading] = useState(true);

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

  return (
    <div>
      <PageHeader
        title="Data Management"
        description="Import and export federation data"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Download size={20} /> Export Data</h3></CardHeader>
          <CardBody>
            {config?.available_exports ? (
              <div className="space-y-3">
                {Object.entries(config.available_exports).map(([key, label]) => (
                  <div key={key} className="flex items-center justify-between rounded-lg border border-default-200 p-3">
                    <div>
                      <p className="font-medium capitalize">{key}</p>
                      <p className="text-xs text-default-400">{label}</p>
                    </div>
                    <Button size="sm" variant="flat" startContent={<Download size={14} />}>Export</Button>
                  </div>
                ))}
                <p className="text-xs text-default-400">
                  Formats: {config.export_formats?.join(', ') || 'JSON, CSV'}
                </p>
                {config.last_export_at && (
                  <p className="text-xs text-default-400">Last export: {new Date(config.last_export_at).toLocaleDateString()}</p>
                )}
              </div>
            ) : (
              <div className="flex flex-col items-center py-6 text-default-400">
                <Database size={32} className="mb-2" />
                <p className="text-sm">Export configuration loading...</p>
              </div>
            )}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Upload size={20} /> Import Data</h3></CardHeader>
          <CardBody>
            <div className="flex flex-col items-center py-8 text-default-400">
              <Upload size={40} className="mb-3" />
              <p className="text-sm font-medium">Import Users</p>
              <p className="text-xs text-center max-w-xs mt-1">Upload a CSV file to bulk import users from a partner community.</p>
              <Button size="sm" variant="flat" className="mt-4" isDisabled={!config?.import_supported}>
                Upload CSV
              </Button>
              {config?.last_import_at && (
                <p className="text-xs text-default-400 mt-2">Last import: {new Date(config.last_import_at).toLocaleDateString()}</p>
              )}
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default DataManagement;
