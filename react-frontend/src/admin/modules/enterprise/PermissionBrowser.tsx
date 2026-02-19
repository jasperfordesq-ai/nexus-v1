// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Permission Browser
 * Read-only list of all available permissions grouped by category.
 */

import { useEffect, useState, useCallback } from 'react';
import { Card, CardBody, Chip, Spinner } from '@heroui/react';
import { Key, Lock } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';

export function PermissionBrowser() {
  usePageTitle('Admin - Permission Browser');

  const [permissions, setPermissions] = useState<Record<string, string[]>>({});
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getPermissions();
      if (res.success && res.data) {
        setPermissions(res.data as unknown as Record<string, string[]>);
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

  const totalCount = Object.values(permissions).reduce((acc, perms) => acc + perms.length, 0);

  return (
    <div>
      <PageHeader
        title="Permission Browser"
        description={`${totalCount} permissions available across ${Object.keys(permissions).length} categories`}
      />

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : (
        <div className="space-y-4">
          {Object.entries(permissions).map(([category, perms]) => (
            <Card key={category} shadow="sm">
              <CardBody className="p-4">
                <div className="flex items-center gap-2 mb-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                    <Key size={16} className="text-primary" />
                  </div>
                  <h3 className="text-base font-semibold capitalize">{category}</h3>
                  <Chip size="sm" variant="flat" color="default">
                    {perms.length}
                  </Chip>
                </div>
                <div className="flex flex-wrap gap-2">
                  {perms.map((perm) => (
                    <Chip
                      key={perm}
                      size="sm"
                      variant="bordered"
                      startContent={<Lock size={12} />}
                    >
                      {perm}
                    </Chip>
                  ))}
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

export default PermissionBrowser;
