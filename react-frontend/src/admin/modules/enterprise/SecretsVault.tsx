// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Secrets Vault
 * Read-only list of env var names (no values shown).
 */

import { useEffect, useState, useCallback } from 'react';
import { Card, CardBody, Button, Spinner, Chip } from '@heroui/react';
import { KeyRound, CheckCircle, XCircle, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SecretEntry } from '../../api/types';

export function SecretsVault() {
  usePageTitle('Admin - Secrets Vault');

  const [secrets, setSecrets] = useState<SecretEntry[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getSecrets();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setSecrets(Array.isArray(data) ? data : []);
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const setCount = secrets.filter((s) => s.is_set).length;

  return (
    <div>
      <PageHeader
        title="Secrets Vault"
        description={`Environment variables overview (${setCount}/${secrets.length} configured). Values are never exposed.`}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : (
        <Card shadow="sm">
          <CardBody className="p-0">
            <div className="divide-y divide-divider">
              {secrets.map((secret) => (
                <div
                  key={secret.key}
                  className="flex items-center gap-3 px-4 py-3"
                >
                  <KeyRound size={16} className="text-default-400 shrink-0" />
                  <span className="font-mono text-sm font-medium text-foreground flex-1">
                    {secret.key}
                  </span>
                  <span className="text-xs text-default-400 font-mono">
                    {secret.masked_value}
                  </span>
                  {secret.is_set ? (
                    <Chip size="sm" variant="flat" color="success" startContent={<CheckCircle size={12} />}>
                      Set
                    </Chip>
                  ) : (
                    <Chip size="sm" variant="flat" color="danger" startContent={<XCircle size={12} />}>
                      Missing
                    </Chip>
                  )}
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export default SecretsVault;
