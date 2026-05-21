// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AdminCouponsPage — AG63 admin oversight: list / suspend / delete merchant coupons.
 */

import { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
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
  const { t } = useTranslation('admin');
  usePageTitle(t('marketplace.coupons.page_title'));
  const toast = useToast();
  const [items, setItems] = useState<AdminCoupon[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ items: AdminCoupon[] }>('/v2/admin/marketplace/coupons');
      setItems(res.data?.items ?? []);
    } catch {
      toast.error(t('marketplace.coupons.failed_load'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);

  useEffect(() => {
    load();
  }, [load]);

  const handleSuspend = async (id: number) => {
    if (!window.confirm(t('marketplace.coupons.confirm_suspend'))) return;
    try {
      await api.post(`/v2/admin/marketplace/coupons/${id}/suspend`, {});
      toast.success(t('marketplace.coupons.suspended'));
      load();
    } catch {
      toast.error(t('marketplace.coupons.failed_suspend'));
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm(t('marketplace.coupons.confirm_delete'))) return;
    try {
      await api.delete(`/v2/admin/marketplace/coupons/${id}`);
      toast.success(t('marketplace.coupons.deleted'));
      load();
    } catch {
      toast.error(t('marketplace.coupons.failed_delete'));
    }
  };

  const formatDiscount = (c: AdminCoupon): string => {
    if (c.discount_type === 'percent') return t('marketplace.coupons.discount_percent', { value: c.discount_value });
    if (c.discount_type === 'fixed') return t('marketplace.coupons.discount_fixed', { value: (c.discount_value / 100).toFixed(2) });
    return t('marketplace.coupons.discount_bogo');
  };

  return (
    <div>
      <PageHeader
        title={t('marketplace.coupons.page_title')}
        description={t('marketplace.coupons.description')}
      />
      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner />
        </div>
      ) : (
        <Card>
          <CardBody>
            <Table aria-label={t('marketplace.coupons.table_aria')}>
              <TableHeader>
                <TableColumn>{t('marketplace.coupons.columns.code')}</TableColumn>
                <TableColumn>{t('marketplace.coupons.columns.title')}</TableColumn>
                <TableColumn>{t('marketplace.coupons.columns.seller_id')}</TableColumn>
                <TableColumn>{t('marketplace.coupons.columns.discount')}</TableColumn>
                <TableColumn>{t('marketplace.coupons.columns.status')}</TableColumn>
                <TableColumn>{t('marketplace.coupons.columns.uses')}</TableColumn>
                <TableColumn>{t('marketplace.coupons.columns.valid_until')}</TableColumn>
                <TableColumn>{t('marketplace.coupons.columns.actions')}</TableColumn>
              </TableHeader>
              <TableBody emptyContent={t('marketplace.coupons.empty')}>
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
                        {t(`marketplace.coupons.status.${c.status}`)}
                      </Chip>
                    </TableCell>
                    <TableCell>{c.usage_count}</TableCell>
                    <TableCell>
                      {c.valid_until ? new Date(c.valid_until).toLocaleDateString() : t('marketplace.coupons.no_expiry')}
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
                          {t('marketplace.coupons.suspend')}
                        </Button>
                        <Button
                          size="sm"
                          variant="light"
                          color="danger"
                          startContent={<Trash2 className="w-4 h-4" />}
                          onPress={() => handleDelete(c.id)}
                        >
                          {t('marketplace.coupons.delete')}
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
