// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Partnerships
 * Lists and manages federation partnerships with other communities.
 * Parity: PHP FederationSettingsController::partnerships() + approve/reject/terminate
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem,
} from '@heroui/react';
import { Handshake, RefreshCw, MoreVertical, CheckCircle, XCircle, Ban } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, StatusBadge, ConfirmModal, type Column } from '../../components';

interface Partnership {
  id: number;
  partner_name: string;
  partner_slug: string;
  status: string;
  created_at: string;
}

export function Partnerships() {
  usePageTitle('Admin - Federation Partnerships');
  const toast = useToast();
  const [items, setItems] = useState<Partnership[]>([]);
  const [loading, setLoading] = useState(true);
  const [terminateTarget, setTerminateTarget] = useState<Partnership | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getPartnerships();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: Partnership[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const handleApprove = async (item: Partnership) => {
    setActionLoading(true);
    try {
      const res = await adminFederation.approvePartnership(item.id);
      if (res.success) {
        toast.success(`Partnership with "${item.partner_name}" approved`);
        loadData();
      } else {
        toast.error('Failed to approve partnership');
      }
    } catch {
      toast.error('Failed to approve partnership');
    } finally {
      setActionLoading(false);
    }
  };

  const handleReject = async (item: Partnership) => {
    setActionLoading(true);
    try {
      const res = await adminFederation.rejectPartnership(item.id);
      if (res.success) {
        toast.success(`Partnership with "${item.partner_name}" rejected`);
        loadData();
      } else {
        toast.error('Failed to reject partnership');
      }
    } catch {
      toast.error('Failed to reject partnership');
    } finally {
      setActionLoading(false);
    }
  };

  const handleTerminate = async () => {
    if (!terminateTarget) return;
    setActionLoading(true);
    try {
      const res = await adminFederation.terminatePartnership(terminateTarget.id);
      if (res.success) {
        toast.success(`Partnership with "${terminateTarget.partner_name}" terminated`);
        setTerminateTarget(null);
        loadData();
      } else {
        toast.error('Failed to terminate partnership');
      }
    } catch {
      toast.error('Failed to terminate partnership');
    } finally {
      setActionLoading(false);
    }
  };

  const columns: Column<Partnership>[] = [
    { key: 'partner_name', label: 'Partner Community', sortable: true },
    { key: 'partner_slug', label: 'Slug' },
    {
      key: 'status', label: 'Status',
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'created_at', label: 'Since', sortable: true,
      render: (item) => <span className="text-sm text-default-500">{item.created_at ? new Date(item.created_at).toLocaleDateString() : '--'}</span>,
    },
    {
      key: 'actions', label: 'Actions',
      render: (item) => (
        <Dropdown>
          <DropdownTrigger>
            <Button isIconOnly size="sm" variant="light" aria-label="Actions" isDisabled={actionLoading}>
              <MoreVertical size={16} />
            </Button>
          </DropdownTrigger>
          <DropdownMenu
            aria-label="Partnership actions"
            onAction={(key) => {
              if (key === 'approve') handleApprove(item);
              else if (key === 'reject') handleReject(item);
              else if (key === 'terminate') setTerminateTarget(item);
            }}
          >
            <DropdownItem
              key="approve"
              startContent={<CheckCircle size={14} />}
              className={item.status === 'pending' ? 'text-success' : 'hidden'}
            >
              Approve
            </DropdownItem>
            <DropdownItem
              key="reject"
              startContent={<XCircle size={14} />}
              className={item.status === 'pending' ? 'text-danger' : 'hidden'}
              color="danger"
            >
              Reject
            </DropdownItem>
            <DropdownItem
              key="terminate"
              startContent={<Ban size={14} />}
              className={item.status === 'active' ? 'text-danger' : 'hidden'}
              color="danger"
            >
              Terminate
            </DropdownItem>
          </DropdownMenu>
        </Dropdown>
      ),
    },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title="Partnerships" description="Manage community partnerships" />
        <EmptyState icon={Handshake} title="No Partnerships" description="No federation partnerships have been established yet. Visit the Partner Directory to find communities." />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Partnerships"
        description="Manage community partnerships"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />
      <DataTable columns={columns} data={items} isLoading={loading} onRefresh={loadData} />

      {terminateTarget && (
        <ConfirmModal
          isOpen={!!terminateTarget}
          onClose={() => setTerminateTarget(null)}
          onConfirm={handleTerminate}
          title="Terminate Partnership"
          message={`Are you sure you want to terminate the partnership with "${terminateTarget.partner_name}"? This cannot be undone.`}
          confirmLabel="Terminate"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default Partnerships;
