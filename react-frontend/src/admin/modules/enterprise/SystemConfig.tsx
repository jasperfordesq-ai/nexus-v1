// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * System Configuration
 * Configuration form showing key-value pairs from tenant configuration.
 */

import { useEffect, useState, useCallback } from 'react';
import { Card, CardBody, Input, Button, Spinner } from '@heroui/react';
import { Save, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';

export function SystemConfig() {
  usePageTitle('Admin - System Configuration');
  const toast = useToast();

  const [, setConfig] = useState<Record<string, unknown>>({});
  const [editedConfig, setEditedConfig] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getConfig();
      if (res.success && res.data) {
        const data = res.data as unknown as Record<string, unknown>;
        setConfig(data);
        // Flatten for editing
        const flat: Record<string, string> = {};
        for (const [key, value] of Object.entries(data)) {
          if (typeof value === 'object' && value !== null) {
            flat[key] = JSON.stringify(value);
          } else {
            flat[key] = String(value ?? '');
          }
        }
        setEditedConfig(flat);
      }
    } catch {
      toast.error('Failed to load configuration');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleSave = async () => {
    setSaving(true);
    try {
      // Parse JSON values back
      const payload: Record<string, unknown> = {};
      for (const [key, value] of Object.entries(editedConfig)) {
        try {
          payload[key] = JSON.parse(value);
        } catch {
          payload[key] = value;
        }
      }
      await adminEnterprise.updateConfig(payload);
      toast.success('Configuration saved');
      loadData();
    } catch {
      toast.error('Failed to save configuration');
    } finally {
      setSaving(false);
    }
  };

  const handleChange = (key: string, value: string) => {
    setEditedConfig((prev) => ({ ...prev, [key]: value }));
  };

  if (loading) {
    return (
      <div>
        <PageHeader title="System Configuration" description="Tenant configuration settings" />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  const configKeys = Object.keys(editedConfig).sort();

  return (
    <div>
      <PageHeader
        title="System Configuration"
        description="Tenant configuration settings (JSON stored in tenants.configuration)"
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              size="sm"
            >
              Reload
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
              size="sm"
            >
              Save Changes
            </Button>
          </div>
        }
      />

      {configKeys.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="py-16 text-center">
            <p className="text-default-500">No configuration keys found</p>
          </CardBody>
        </Card>
      ) : (
        <Card shadow="sm">
          <CardBody className="p-4 space-y-3">
            {configKeys.map((key) => (
              <div key={key} className="flex items-start gap-3">
                <div className="w-48 shrink-0 pt-2">
                  <span className="text-sm font-mono font-medium text-default-600">{key}</span>
                </div>
                <Input
                  value={editedConfig[key] ?? ''}
                  onValueChange={(v) => handleChange(key, v)}
                  variant="bordered"
                  size="sm"
                  className="flex-1"
                />
              </div>
            ))}
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export default SystemConfig;
