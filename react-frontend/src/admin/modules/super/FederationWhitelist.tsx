// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Select,
  SelectItem,
  Textarea,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@heroui/react';
import { Plus, Trash2 } from 'lucide-react';
import PageHeader from '../../components/PageHeader';
import ConfirmModal from '../../components/ConfirmModal';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast, useTenant } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import type { FederationWhitelistEntry, SuperAdminTenant } from '../../api/types';

interface WhitelistEntry {
  id: number;
  tenant_id: number;
  tenant_name: string;
  domain: string;
  approved_by_name: string;
  approved_at: string;
  notes?: string;
}

interface Tenant {
  id: number;
  name: string;
  domain: string;
}

function mapWhitelistEntry(e: FederationWhitelistEntry): WhitelistEntry {
  return {
    id: e.tenant_id,
    tenant_id: e.tenant_id,
    tenant_name: e.tenant_name,
    domain: e.tenant_domain || '',
    approved_by_name: String(e.added_by),
    approved_at: e.added_at,
    notes: e.notes,
  };
}

export default function FederationWhitelist() {
  usePageTitle('Federation Whitelist');
  const toast = useToast();
  const { tenantPath } = useTenant();
  const [entries, setEntries] = useState<WhitelistEntry[]>([]);
  const [availableTenants, setAvailableTenants] = useState<Tenant[]>([]);
  const [selectedTenantId, setSelectedTenantId] = useState<string>('');
  const [notes, setNotes] = useState('');
  const [loading, setLoading] = useState(true);
  const [removing, setRemoving] = useState<number | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    const [whitelistRes, tenantsRes] = await Promise.all([
      adminSuper.getWhitelist(),
      adminSuper.listTenants(),
    ]);
    if (whitelistRes.success && whitelistRes.data) {
      const mapped = (Array.isArray(whitelistRes.data) ? whitelistRes.data : []).map(mapWhitelistEntry);
      setEntries(mapped);
    }
    if (tenantsRes.success && tenantsRes.data) {
      const tenants = (Array.isArray(tenantsRes.data) ? tenantsRes.data : []) as SuperAdminTenant[];
      setAvailableTenants(tenants.map(t => ({ id: t.id, name: t.name, domain: t.domain || '' })));
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const handleAdd = async () => {
    if (!selectedTenantId) {
      toast.error('Please select a tenant');
      return;
    }

    const res = await adminSuper.addToWhitelist(parseInt(selectedTenantId), notes || undefined);
    if (res.success) {
      toast.success('Tenant added to whitelist');
      setSelectedTenantId('');
      setNotes('');
      loadData();
    } else {
      toast.error(res.error || 'Failed to add tenant to whitelist');
    }
  };

  const handleRemove = async (tenantId: number) => {
    const res = await adminSuper.removeFromWhitelist(tenantId);
    if (res.success) {
      setEntries(prev => prev.filter(e => e.tenant_id !== tenantId));
      setRemoving(null);
      toast.success('Tenant removed from whitelist');
    } else {
      toast.error(res.error || 'Failed to remove tenant');
      setRemoving(null);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Federation Whitelist"
        description="Manage which tenants can use federation features"
      />

      {/* Add Form */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <Plus className="w-5 h-5" />
            Add Tenant to Whitelist
          </h3>
        </CardHeader>
        <CardBody className="space-y-4">
          <Select
            label="Tenant"
            placeholder="Select a tenant"
            selectedKeys={selectedTenantId ? [selectedTenantId] : []}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0];
              setSelectedTenantId(selected ? String(selected) : '');
            }}
            variant="bordered"
          >
            {availableTenants.map(tenant => (
              <SelectItem key={tenant.id.toString()}>
                {tenant.name} ({tenant.domain})
              </SelectItem>
            ))}
          </Select>

          <Textarea
            label="Notes (Optional)"
            placeholder="Add notes about why this tenant is being whitelisted"
            value={notes}
            onValueChange={setNotes}
            variant="bordered"
            minRows={2}
          />

          <Button
            color="primary"
            onPress={handleAdd}
            startContent={<Plus className="w-4 h-4" />}
          >
            Add to Whitelist
          </Button>
        </CardBody>
      </Card>

      {/* Whitelist Table */}
      <Card>
        <CardHeader>
          <h3 className="text-lg font-semibold">Whitelisted Tenants ({entries.length})</h3>
        </CardHeader>
        <CardBody>
          <Table aria-label="Whitelisted tenants" shadow="sm" isStriped>
            <TableHeader>
              <TableColumn>Tenant</TableColumn>
              <TableColumn>Domain</TableColumn>
              <TableColumn>Approved By</TableColumn>
              <TableColumn>Date</TableColumn>
              <TableColumn>Notes</TableColumn>
              <TableColumn>Actions</TableColumn>
            </TableHeader>
            <TableBody emptyContent="No tenants whitelisted yet">
              {entries.map(entry => (
                <TableRow key={entry.id}>
                  <TableCell>
                    <Link
                      to={tenantPath(`/admin/super/federation/tenant/${entry.tenant_id}/features`)}
                      className="text-primary hover:underline"
                    >
                      {entry.tenant_name}
                    </Link>
                  </TableCell>
                  <TableCell className="text-sm text-default-600">{entry.domain}</TableCell>
                  <TableCell className="text-sm text-default-600">{entry.approved_by_name}</TableCell>
                  <TableCell className="text-sm text-default-600">
                    {new Date(entry.approved_at).toLocaleDateString()}
                  </TableCell>
                  <TableCell className="text-sm text-default-600 max-w-xs truncate">
                    {entry.notes || '-'}
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <Button
                        as={Link}
                        to={tenantPath(`/admin/super/federation/tenant/${entry.tenant_id}/features`)}
                        size="sm"
                        variant="flat"
                        color="primary"
                      >
                        View
                      </Button>
                      <Button
                        size="sm"
                        variant="flat"
                        color="danger"
                        onPress={() => setRemoving(entry.tenant_id)}
                        startContent={<Trash2 className="w-4 h-4" />}
                      >
                        Remove
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      {/* Remove Confirmation Modal */}
      {removing !== null && (
        <ConfirmModal
          isOpen={true}
          onClose={() => setRemoving(null)}
          onConfirm={() => handleRemove(removing)}
          title="Remove from Whitelist"
          message="Are you sure you want to remove this tenant from the whitelist? They will lose access to all federation features."
          confirmLabel="Remove"
          confirmColor="danger"
        />
      )}
    </div>
  );
}
