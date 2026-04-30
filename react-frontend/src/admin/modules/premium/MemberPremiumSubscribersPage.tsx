// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG58 — Admin: Member Premium subscriber list.
 * English-only per project convention for /admin/.
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Card,
  CardBody,
  Button,
  Chip,
  Spinner,
  Pagination,
  Select,
  SelectItem,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@heroui/react';
import Users from 'lucide-react/icons/users';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import {
  memberPremiumAdminApi,
  type MemberSubscriberRow,
} from '../../api/memberPremiumApi';
import { PageHeader } from '../../components';

const STATUS_OPTIONS = ['', 'active', 'past_due', 'canceled', 'trialing', 'incomplete'];

function statusColor(s: string): 'success' | 'warning' | 'danger' | 'default' {
  switch (s) {
    case 'active':
    case 'trialing':
      return 'success';
    case 'past_due':
    case 'incomplete':
      return 'warning';
    case 'canceled':
      return 'danger';
    default:
      return 'default';
  }
}

export function MemberPremiumSubscribersPage() {
  usePageTitle('Premium Subscribers');
  const toast = useToast();

  const [rows, setRows] = useState<MemberSubscriberRow[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [perPage] = useState(25);
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await memberPremiumAdminApi.listSubscribers({
        page,
        per_page: perPage,
        status: statusFilter || undefined,
      });
      setRows(res.data?.rows ?? []);
      setTotal(res.data?.total ?? 0);
    } catch {
      toast.error('Failed to load subscribers');
    } finally {
      setLoading(false);
    }
  }, [page, perPage, statusFilter, toast]);

  useEffect(() => {
    load();
  }, [load]);

  const totalPages = Math.max(1, Math.ceil(total / perPage));

  return (
    <div className="space-y-6">
      <PageHeader
        title="Premium Subscribers"
        description="Members currently subscribed to a premium tier."
        icon={<Users size={24} />}
      />

      <Card>
        <CardBody className="flex flex-row items-center gap-3 flex-wrap">
          <Select
            label="Status filter"
            size="sm"
            className="max-w-xs"
            selectedKeys={statusFilter ? [statusFilter] : []}
            onSelectionChange={(keys) => {
              const next = Array.from(keys as Set<string>)[0] ?? '';
              setStatusFilter(next);
              setPage(1);
            }}
          >
            {STATUS_OPTIONS.map((s) => (
              <SelectItem key={s} textValue={s || 'All'}>{s || 'All'}</SelectItem>
            ))}
          </Select>
          <span className="text-sm text-default-500">{total} total</span>
          <div className="flex-1" />
          <Button size="sm" variant="flat" onPress={load} isLoading={loading}>Refresh</Button>
        </CardBody>
      </Card>

      <Card>
        <CardBody className="p-0">
          {loading ? (
            <div className="flex justify-center py-10"><Spinner /></div>
          ) : rows.length === 0 ? (
            <div className="text-center py-10 text-default-500">No subscribers match the filter.</div>
          ) : (
            <Table removeWrapper aria-label="Subscribers">
              <TableHeader>
                <TableColumn>MEMBER</TableColumn>
                <TableColumn>EMAIL</TableColumn>
                <TableColumn>TIER</TableColumn>
                <TableColumn>INTERVAL</TableColumn>
                <TableColumn>STATUS</TableColumn>
                <TableColumn>NEXT BILLING</TableColumn>
                <TableColumn>STARTED</TableColumn>
              </TableHeader>
              <TableBody>
                {rows.map((r) => (
                  <TableRow key={r.id}>
                    <TableCell>{r.user_name || r.first_name || `User #${r.user_id}`}</TableCell>
                    <TableCell><span className="text-xs">{r.email ?? '—'}</span></TableCell>
                    <TableCell>{r.tier_name}</TableCell>
                    <TableCell>{r.billing_interval}</TableCell>
                    <TableCell>
                      <Chip size="sm" color={statusColor(r.status)} variant="flat">{r.status}</Chip>
                    </TableCell>
                    <TableCell>
                      {r.current_period_end ? new Date(r.current_period_end).toLocaleDateString() : '—'}
                    </TableCell>
                    <TableCell>
                      {new Date(r.created_at).toLocaleDateString()}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {totalPages > 1 && (
        <div className="flex justify-center">
          <Pagination total={totalPages} page={page} onChange={setPage} />
        </div>
      )}
    </div>
  );
}

export default MemberPremiumSubscribersPage;
