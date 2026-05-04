// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SellerCouponEditPage — AG63 create/edit coupon form.
 */

import { useEffect, useState, FormEvent } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  Input,
  Textarea,
  Button,
  Select,
  SelectItem,
  Spinner,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { logError } from '@/lib/logger';

type DiscountType = 'percent' | 'fixed' | 'bogo';
type Status = 'draft' | 'active' | 'paused' | 'expired';
type AppliesTo = 'all_listings' | 'listing_ids' | 'category_ids';

interface FormState {
  code: string;
  title: string;
  description: string;
  discount_type: DiscountType;
  discount_value: string;
  min_order_cents: string;
  max_uses: string;
  max_uses_per_member: string;
  valid_from: string;
  valid_until: string;
  status: Status;
  applies_to: AppliesTo;
}

const initialState: FormState = {
  code: '',
  title: '',
  description: '',
  discount_type: 'percent',
  discount_value: '10',
  min_order_cents: '',
  max_uses: '',
  max_uses_per_member: '1',
  valid_from: '',
  valid_until: '',
  status: 'draft',
  applies_to: 'all_listings',
};

export default function SellerCouponEditPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const toast = useToast();
  const { tenantPath } = useTenant();
  const isEdit = Boolean(id);
  usePageTitle(isEdit ? t('coupon.seller.edit_title') : t('coupon.seller.create_title'));

  const [form, setForm] = useState<FormState>(initialState);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!isEdit || !id) return;
    let cancelled = false;
    (async () => {
      try {
        // Seller endpoint returns list; we filter. Or fetch via member detail endpoint.
        const res = await api.get<{ items: Array<{ id: number } & Record<string, unknown>> }>(
          '/v2/marketplace/seller/coupons'
        );
        const c = (res.data?.items ?? []).find((x) => x.id === Number(id));
        if (cancelled || !c) return;
        setForm({
          code: String(c.code ?? ''),
          title: String(c.title ?? ''),
          description: String(c.description ?? ''),
          discount_type: (c.discount_type as DiscountType) ?? 'percent',
          discount_value: String(c.discount_value ?? ''),
          min_order_cents: c.min_order_cents != null ? String(c.min_order_cents) : '',
          max_uses: c.max_uses != null ? String(c.max_uses) : '',
          max_uses_per_member: String(c.max_uses_per_member ?? '1'),
          valid_from: c.valid_from ? String(c.valid_from).slice(0, 16) : '',
          valid_until: c.valid_until ? String(c.valid_until).slice(0, 16) : '',
          status: (c.status as Status) ?? 'draft',
          applies_to: (c.applies_to as AppliesTo) ?? 'all_listings',
        });
      } catch (err) {
        logError('SellerCouponEditPage.load', err);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [id, isEdit]);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        code: form.code || undefined,
        title: form.title,
        description: form.description || null,
        discount_type: form.discount_type,
        discount_value: parseFloat(form.discount_value || '0'),
        min_order_cents: form.min_order_cents ? parseInt(form.min_order_cents, 10) : null,
        max_uses: form.max_uses ? parseInt(form.max_uses, 10) : null,
        max_uses_per_member: parseInt(form.max_uses_per_member || '1', 10),
        valid_from: form.valid_from || null,
        valid_until: form.valid_until || null,
        status: form.status,
        applies_to: form.applies_to,
      };
      if (isEdit) {
        await api.put(`/v2/marketplace/seller/coupons/${id}`, payload);
      } else {
        await api.post('/v2/marketplace/seller/coupons', payload);
      }
      toast.success(t('coupon.seller.saved'));
      navigate(tenantPath('/marketplace/seller/coupons'));
    } catch (err) {
      logError('SellerCouponEditPage.save', err);
      toast.error(t('errors.unexpected', 'Something went wrong'));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner />
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8 max-w-2xl">
      <Button
        as={Link}
        to={tenantPath('/marketplace/seller/coupons')}
        variant="light"
        startContent={<ArrowLeft className="w-4 h-4" />}
        className="mb-4"
      >
        {t('coupon.back_to_coupons')}
      </Button>

      <Card>
        <CardBody className="p-6">
          <h1 className="text-2xl font-bold mb-6">
            {isEdit ? t('coupon.seller.edit_title') : t('coupon.seller.create_title')}
          </h1>
          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <Input
              label={t('coupon.code')}
              description={t('coupon.seller.code_help')}
              value={form.code}
              onValueChange={(v) => setForm((f) => ({ ...f, code: v.toUpperCase() }))}
            />
            <Input
              label={t('coupon.title')}
              isRequired
              value={form.title}
              onValueChange={(v) => setForm((f) => ({ ...f, title: v }))}
            />
            <Textarea
              label={t('coupon.description')}
              value={form.description}
              onValueChange={(v) => setForm((f) => ({ ...f, description: v }))}
              minRows={2}
            />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Select
                label={t('coupon.seller.discount_type')}
                selectedKeys={[form.discount_type]}
                onChange={(e) =>
                  setForm((f) => ({ ...f, discount_type: e.target.value as DiscountType }))
                }
              >
                <SelectItem key="percent">% {t('coupon.type_percent')}</SelectItem>
                <SelectItem key="fixed">{t('coupon.type_fixed')}</SelectItem>
                <SelectItem key="bogo">{t('coupon.type_bogo')}</SelectItem>
              </Select>
              <Input
                type="number"
                label={t('coupon.seller.discount_value')}
                description={
                  form.discount_type === 'percent'
                    ? t('coupon.seller.discount_value_help_percent')
                    : form.discount_type === 'fixed'
                      ? t('coupon.seller.discount_value_help_fixed')
                      : ''
                }
                value={form.discount_value}
                onValueChange={(v) => setForm((f) => ({ ...f, discount_value: v }))}
              />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                type="number"
                label={t('coupon.seller.max_uses')}
                value={form.max_uses}
                onValueChange={(v) => setForm((f) => ({ ...f, max_uses: v }))}
              />
              <Input
                type="number"
                label={t('coupon.seller.max_uses_per_member')}
                value={form.max_uses_per_member}
                onValueChange={(v) => setForm((f) => ({ ...f, max_uses_per_member: v }))}
              />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                type="datetime-local"
                label={t('coupon.seller.valid_from')}
                value={form.valid_from}
                onValueChange={(v) => setForm((f) => ({ ...f, valid_from: v }))}
              />
              <Input
                type="datetime-local"
                label={t('coupon.seller.valid_until')}
                value={form.valid_until}
                onValueChange={(v) => setForm((f) => ({ ...f, valid_until: v }))}
              />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Select
                label={t('coupon.seller.status')}
                selectedKeys={[form.status]}
                onChange={(e) => setForm((f) => ({ ...f, status: e.target.value as Status }))}
              >
                <SelectItem key="draft">draft</SelectItem>
                <SelectItem key="active">active</SelectItem>
                <SelectItem key="paused">paused</SelectItem>
                <SelectItem key="expired">expired</SelectItem>
              </Select>
              <Select
                label={t('coupon.seller.applies_to')}
                selectedKeys={[form.applies_to]}
                onChange={(e) =>
                  setForm((f) => ({ ...f, applies_to: e.target.value as AppliesTo }))
                }
              >
                <SelectItem key="all_listings">{t('coupon.seller.all_listings')}</SelectItem>
                <SelectItem key="listing_ids">{t('coupon.seller.specific_listings')}</SelectItem>
                <SelectItem key="category_ids">{t('coupon.seller.specific_categories')}</SelectItem>
              </Select>
            </div>
            <Input
              type="number"
              label={t('coupon.min_order')}
              description="cents"
              value={form.min_order_cents}
              onValueChange={(v) => setForm((f) => ({ ...f, min_order_cents: v }))}
            />
            <div className="flex justify-end gap-2 mt-2">
              <Button as={Link} to={tenantPath('/marketplace/seller/coupons')} variant="light">
                {t('common.cancel', 'Cancel')}
              </Button>
              <Button color="primary" type="submit" isLoading={saving}>
                {t('coupon.seller.save')}
              </Button>
            </div>
          </form>
        </CardBody>
      </Card>
    </div>
  );
}
