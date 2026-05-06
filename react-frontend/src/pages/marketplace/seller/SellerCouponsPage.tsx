// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SellerCouponsPage — AG63 seller-side list of own coupons.
 */

import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
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
import Plus from 'lucide-react/icons/plus';
import Tag from 'lucide-react/icons/tag';
import Pencil from 'lucide-react/icons/pencil';
import Trash2 from 'lucide-react/icons/trash-2';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { logError } from '@/lib/logger';

interface Coupon {
  id: number;
  code: string;
  title: string;
  discount_type: 'percent' | 'fixed' | 'bogo';
  discount_value: number;
  status: string;
  usage_count: number;
  valid_until: string | null;
}

export default function SellerCouponsPage() {
  const { t } = useTranslation(['common', 'marketplace']);
  const toast = useToast();
  const { tenantPath } = useTenant();
  usePageTitle(t('coupon.seller.page_title'));

  const [items, setItems] = useState<Coupon[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ items: Coupon[] }>('/v2/marketplace/seller/coupons');
      setItems(res.data?.items ?? []);
    } catch (err) {
      logError('SellerCouponsPage.load', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleDelete = async (id: number) => {
    if (!window.confirm(t('coupon.seller.delete_confirm'))) return;
    try {
      await api.delete(`/v2/marketplace/seller/coupons/${id}`);
      toast.success(t('coupon.seller.deleted'));
      load();
    } catch (err) {
      logError('SellerCouponsPage.delete', err);
    }
  };

  const formatDiscount = (c: Coupon): string => {
    if (c.discount_type === 'percent') return `${c.discount_value}%`;
    if (c.discount_type === 'fixed') return `€${(c.discount_value / 100).toFixed(2)}`;
    return 'BOGO';
  };

  return (
    <div className="container mx-auto px-4 py-8 max-w-6xl">
      <header className="flex items-center justify-between mb-6">
        <h1 className="text-3xl font-bold">{t('coupon.seller.page_title')}</h1>
        <Button
          as={Link}
          to={tenantPath('/marketplace/seller/coupons/new')}
          color="primary"
          startContent={<Plus className="w-4 h-4" />}
        >
          {t('coupon.seller.create_button')}
        </Button>
      </header>

      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner />
        </div>
      ) : items.length === 0 ? (
        <Card>
          <CardBody className="text-center py-12">
            <Tag className="w-12 h-12 mx-auto mb-3 text-[var(--color-text-secondary)]" />
            <p>{t('coupon.seller.no_coupons')}</p>
          </CardBody>
        </Card>
      ) : (
        <Card>
          <CardBody>
            <Table aria-label="Coupons">
              <TableHeader>
                <TableColumn>{t('coupon.code')}</TableColumn>
                <TableColumn>{t('coupon.title')}</TableColumn>
                <TableColumn>{t('coupon.discount')}</TableColumn>
                <TableColumn>{t('coupon.seller.status')}</TableColumn>
                <TableColumn>{t('coupon.seller.usage_count', { count: 0 }).split(' ')[0]}</TableColumn>
                <TableColumn>{t('coupon.valid_until')}</TableColumn>
                <TableColumn>{' '}</TableColumn>
              </TableHeader>
              <TableBody>
                {items.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-mono">{c.code}</TableCell>
                    <TableCell>{c.title}</TableCell>
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
                      <div className="flex gap-1 justify-end">
                        <Button
                          as={Link}
                          to={tenantPath(`/marketplace/seller/coupons/${c.id}/edit`)}
                          isIconOnly
                          size="sm"
                          variant="light"
                          aria-label={t('marketplace:edit.action_edit')}
                        >
                          <Pencil className="w-4 h-4" />
                        </Button>
                        <Button
                          isIconOnly
                          size="sm"
                          variant="light"
                          color="danger"
                          aria-label={t('common.delete')}
                          onPress={() => handleDelete(c.id)}
                        >
                          <Trash2 className="w-4 h-4" />
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
