// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AdminCouponsPage — AG63 admin oversight: list / suspend / delete merchant coupons.
 * Admin panel is English-only — no t() calls.
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Card,
  CardBody,
  Button,
  Chip,
  Spinner,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@heroui/react';
import Pause from 'lucide-react/icons/pause';
import Trash2 from 'lucide-react/icons/trash-2';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

interface AdminCoupon {
  id: number;
  seller_id: number;
  code: string;
  title: string;
  discount_type: 'percent' | 'fixed' | 'bogo';
  discount_value: number;
  status: string;
  usage_count: number;
  valid_until: string | null;
  created_at: string;
}

export default function AdminCouponsPage() {
  usePageTitle('Merchant Coupons');
  const toast = useToast();
  const [items, setItems] = useState<AdminCoupon[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ items: AdminCoupon[] }>('/v2/admin/marketplace/coupons');
      setItems(res.data?.items ?? []);
    } catch (err) {
      toast.error('Failed to load coupons');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    load();
  }, [load]);

  const handleSuspend = async (id: number) => {
    if (!window.confirm('Suspend this coupon?')) return;
    try {
      await api.post(`/v2/admin/marketplace/coupons/${id}/suspend`, {});
      toast.success('Coupon suspended');
      load();
    } catch {
      toast.error('Failed to suspend');
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm('Permanently delete this coupon? This cannot be undone.')) return;
    try {
      await api.delete(`/v2/admin/marketplace/coupons/${id}`);
      toast.success('Coupon deleted');
      load();
    } catch {
      toast.error('Failed to delete');
    }
  };

  const formatDiscount = (c: AdminCoupon): string => {
    if (c.discount_type === 'percent') return `${c.discount_value}%`;
    if (c.discount_type === 'fixed') return `€${(c.discount_value / 100).toFixed(2)}`;
    return 'BOGO';
  };

  return (
    <div>
      <PageHeader
        title="Merchant Coupons"
        description="Oversight of merchant-issued discount coupons across all sellers."
      />
      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner />
        </div>
      ) : (
        <Card>
          <CardBody>
            <Table aria-label="Merchant coupons">
              <TableHeader>
                <TableColumn>Code</TableColumn>
                <TableColumn>Title</TableColumn>
                <TableColumn>Seller ID</TableColumn>
                <TableColumn>Discount</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Uses</TableColumn>
                <TableColumn>Valid until</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody emptyContent="No coupons.">
                {items.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-mono">{c.code}</TableCell>
                    <TableCell>{c.title}</TableCell>
                    <TableCell>{c.seller_id}</TableCell>
                    <TableCell>{formatDiscount(c)}</TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        color={c.status === 'active' ? 'success' : c.status === 'paused' ? 'warning' : 'default'}
                        variant="flat"
                      >
                        {c.status}
                      </Chip>
                    </TableCell>
                    <TableCell>{c.usage_count}</TableCell>
                    <TableCell>
                      {c.valid_until ? new Date(c.valid_until).toLocaleDateString() : '—'}
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button
                          size="sm"
                          variant="light"
                          color="warning"
                          startContent={<Pause className="w-4 h-4" />}
                          onPress={() => handleSuspend(c.id)}
                          isDisabled={c.status === 'paused'}
                        >
                          Suspend
                        </Button>
                        <Button
                          size="sm"
                          variant="light"
                          color="danger"
                          startContent={<Trash2 className="w-4 h-4" />}
                          onPress={() => handleDelete(c.id)}
                        >
                          Delete
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
