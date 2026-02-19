// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Organization Wallets
 * Read-only overview of all organization wallets with balances and member counts.
 * Parity: PHP Admin\TimebankingController::orgWallets()
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Button } from '@heroui/react';
import { Building2, ArrowLeft } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminTimebanking } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { OrgWallet } from '../../api/types';

export function OrgWallets() {
  usePageTitle('Admin - Organization Wallets');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [wallets, setWallets] = useState<OrgWallet[]>([]);
  const [loading, setLoading] = useState(true);

  const loadWallets = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTimebanking.getOrgWallets();
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setWallets(data);
        } else if (data && typeof data === 'object' && 'data' in (data as Record<string, unknown>)) {
          setWallets((data as { data: OrgWallet[] }).data || []);
        }
      }
    } catch {
      toast.error('Failed to load organization wallets');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadWallets();
  }, [loadWallets]);

  const columns: Column<OrgWallet>[] = useMemo(
    () => [
      {
        key: 'org_name',
        label: 'Organization',
        sortable: true,
        render: (wallet) => (
          <span className="text-sm font-medium">{wallet.org_name}</span>
        ),
      },
      {
        key: 'balance',
        label: 'Balance',
        sortable: true,
        render: (wallet) => (
          <span className="text-sm font-semibold">
            {wallet.balance.toLocaleString()}h
          </span>
        ),
      },
      {
        key: 'total_in',
        label: 'Total In',
        sortable: true,
        render: (wallet) => (
          <span className="text-sm text-success">
            +{wallet.total_in.toLocaleString()}h
          </span>
        ),
      },
      {
        key: 'total_out',
        label: 'Total Out',
        sortable: true,
        render: (wallet) => (
          <span className="text-sm text-danger">
            -{wallet.total_out.toLocaleString()}h
          </span>
        ),
      },
      {
        key: 'member_count',
        label: 'Members',
        sortable: true,
        render: (wallet) => (
          <span className="text-sm">{wallet.member_count}</span>
        ),
      },
      {
        key: 'created_at',
        label: 'Created',
        sortable: true,
        render: (wallet) => (
          <span className="text-sm text-default-500">
            {new Date(wallet.created_at).toLocaleDateString()}
          </span>
        ),
      },
    ],
    []
  );

  return (
    <div>
      <PageHeader
        title="Organization Wallets"
        description="View organization wallet balances and activity"
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/timebanking')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            Back to Timebanking
          </Button>
        }
      />

      <DataTable<OrgWallet>
        columns={columns}
        data={wallets}
        isLoading={loading}
        searchable={false}
        onRefresh={loadWallets}
        emptyContent={
          <div className="flex flex-col items-center gap-2 py-8">
            <Building2 size={32} className="text-default-300" />
            <p className="text-sm text-default-400">No organization wallets found</p>
          </div>
        }
      />
    </div>
  );
}

export default OrgWallets;
