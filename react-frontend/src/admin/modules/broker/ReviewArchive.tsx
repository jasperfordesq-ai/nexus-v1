// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Review Archive
 * Compliance archive of reviewed broker message copies.
 * Read-only listing with filtering by decision status.
 * Parity: PHP BrokerControlsController::archives()
 */

import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Tabs, Tab, Button, Chip, Input } from '@heroui/react';
import { ArrowLeft, Search, Flag } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { BrokerArchive } from '../../api/types';

export function ReviewArchive() {
  usePageTitle('Admin - Review Archive');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [items, setItems] = useState<BrokerArchive[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getArchives({
        page,
        decision: filter === 'all' ? undefined : filter,
        search: search.trim() || undefined,
      });
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data as BrokerArchive[]);
        const meta = res.meta as Record<string, unknown> | undefined;
        setTotal(Number(meta?.total ?? meta?.total_items ?? res.data.length));
      }
    } catch {
      toast.error('Failed to load archives');
    } finally {
      setLoading(false);
    }
  }, [page, filter, search]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleFilterChange = (key: string | number) => {
    setFilter(key as string);
    setPage(1);
  };

  const handleSearchChange = (value: string) => {
    setSearch(value);
    setPage(1);
  };

  const columns: Column<BrokerArchive>[] = [
    {
      key: 'sender_name',
      label: 'Sender',
      sortable: true,
      render: (item) => (
        <Link
          to={tenantPath(`/admin/broker-controls/archives/${item.id}`)}
          className="font-medium text-primary hover:underline"
        >
          {item.sender_name}
        </Link>
      ),
    },
    {
      key: 'receiver_name',
      label: 'Receiver',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.receiver_name}</span>
      ),
    },
    {
      key: 'listing_title',
      label: 'Listing',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.listing_title || '\u2014'}
        </span>
      ),
    },
    {
      key: 'copy_reason',
      label: 'Copy Reason',
      render: (item) => (
        <Chip size="sm" variant="flat" color="default" className="capitalize">
          {item.copy_reason.replace(/_/g, ' ')}
        </Chip>
      ),
    },
    {
      key: 'decision',
      label: 'Decision',
      render: (item) => {
        const isApproved = item.decision === 'approved';
        return (
          <Chip
            size="sm"
            variant="flat"
            color={isApproved ? 'success' : 'danger'}
            startContent={!isApproved ? <Flag size={12} /> : undefined}
            className="capitalize"
          >
            {item.decision}
          </Chip>
        );
      },
    },
    {
      key: 'decided_by_name',
      label: 'Decided By',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-foreground">{item.decided_by_name}</span>
      ),
    },
    {
      key: 'decided_at',
      label: 'Date',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.decided_at).toLocaleDateString()}
        </span>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Review Archive"
        description="Compliance archive of reviewed broker message copies"
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/broker-controls')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            Back
          </Button>
        }
      />

      <div className="mb-4 flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <Tabs
          selectedKey={filter}
          onSelectionChange={handleFilterChange}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title="All" />
          <Tab key="approved" title="Approved" />
          <Tab key="flagged" title="Flagged" />
        </Tabs>

        <Input
          className="w-full sm:max-w-xs"
          placeholder="Search sender or receiver..."
          startContent={<Search size={16} className="text-default-400" />}
          value={search}
          onValueChange={handleSearchChange}
          size="sm"
          variant="bordered"
          isClearable
          onClear={() => handleSearchChange('')}
        />
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchable={false}
        onRefresh={loadItems}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        emptyContent="No archive records found"
      />
    </div>
  );
}

export default ReviewArchive;
