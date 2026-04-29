// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SellerPickupScanPage — AG45 click-and-collect: seller scans/enters a buyer's QR code.
 *
 * Manual entry by default. Camera scanning is optional via html5-qrcode (not bundled).
 */

import { useState } from 'react';
import { Button, Input } from '@heroui/react';
import QrCode from 'lucide-react/icons/qr-code';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';

interface ScanResult {
  id: number;
  order_id: number;
  listing_id: number;
  status: string;
  picked_up_at: string | null;
}

export function SellerPickupScanPage() {
  const { t } = useTranslation('common');
  usePageTitle(t('marketplace.pickup.scan_title', 'Pickup Scan'));
  const toast = useToast();

  const [code, setCode] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [last, setLast] = useState<ScanResult | null>(null);

  const handleSubmit = async () => {
    if (!code.trim()) return;
    setSubmitting(true);
    try {
      const res = await api.post<ScanResult>('/v2/marketplace/seller/pickup-scan', {
        qr_code: code.trim(),
      });
      if (res.success && res.data) {
        setLast(res.data);
        setCode('');
        toast.success(t('marketplace.pickup.scan_success', 'Pickup confirmed'));
      } else {
        toast.error(res.error || t('marketplace.pickup.scan_failed', 'Scan failed'));
      }
    } catch (err) {
      logError('SellerPickupScanPage: scan failed', err);
      toast.error(t('marketplace.pickup.scan_failed', 'Scan failed'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="max-w-xl mx-auto px-4 py-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
          <QrCode className="w-7 h-7 text-primary" />
          {t('marketplace.pickup.scan_title', 'Pickup Scan')}
        </h1>
        <p className="text-default-500 text-sm mt-1">
          {t('marketplace.pickup.scan_subtitle', 'Enter the buyer\'s pickup code to confirm collection.')}
        </p>
      </div>

      <GlassCard className="p-6 space-y-4">
        <Input
          label={t('marketplace.pickup.qr_code', 'Pickup Code')}
          value={code}
          onValueChange={setCode}
          placeholder="01HXXXXXXXXXXXXXXXXXXXXXXX"
          autoFocus
        />
        <Button
          color="primary"
          fullWidth
          onPress={handleSubmit}
          isLoading={submitting}
          isDisabled={!code.trim()}
          startContent={<CheckCircle2 className="w-4 h-4" />}
        >
          {t('marketplace.pickup.confirm_pickup', 'Confirm Pickup')}
        </Button>
      </GlassCard>

      {last && (
        <GlassCard className="p-4 border-l-4 border-success">
          <p className="font-semibold text-success">
            {t('marketplace.pickup.last_scan', 'Last scan')}
          </p>
          <p className="text-sm text-default-700 mt-1">
            {t('marketplace.pickup.order_n', 'Order #{{id}}', { id: last.order_id })} —{' '}
            {t('marketplace.pickup.status_picked_up', 'Picked up')}
          </p>
        </GlassCard>
      )}
    </div>
  );
}

export default SellerPickupScanPage;
