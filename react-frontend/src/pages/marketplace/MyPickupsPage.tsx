// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MyPickupsPage — AG45 click-and-collect: buyer's reservations with QR display.
 */

import { useEffect, useState } from 'react';
import { Spinner, Chip } from '@heroui/react';
import ShoppingBag from 'lucide-react/icons/shopping-bag';
import QrCode from 'lucide-react/icons/qr-code';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';

interface Reservation {
  id: number;
  slot_id: number;
  order_id: number;
  listing_id: number;
  listing_title: string | null;
  qr_code: string;
  status: string;
  reserved_at: string | null;
  picked_up_at: string | null;
  slot: { slot_start: string | null; slot_end: string | null } | null;
}

export function MyPickupsPage() {
  const { t } = useTranslation('common');
  usePageTitle(t('marketplace.pickup.my_pickups_title', 'My Pickups'));
  const { isAuthenticated } = useAuth();

  const [reservations, setReservations] = useState<Reservation[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!isAuthenticated) return;
    let cancelled = false;
    (async () => {
      try {
        const res = await api.get<Reservation[]>('/v2/marketplace/me/pickups');
        if (!cancelled && res.success && res.data) setReservations(res.data);
      } catch (err) {
        logError('MyPickupsPage: load failed', err);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [isAuthenticated]);

  const formatTime = (s: string | null) => (s ? new Date(s).toLocaleString() : '—');

  const statusColor = (s: string): 'primary' | 'success' | 'warning' | 'danger' => {
    if (s === 'picked_up') return 'success';
    if (s === 'cancelled') return 'danger';
    if (s === 'no_show') return 'warning';
    return 'primary';
  };

  return (
    <div className="max-w-3xl mx-auto px-4 py-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground flex items-center gap-2">
          <ShoppingBag className="w-7 h-7 text-primary" />
          {t('marketplace.pickup.my_pickups_title', 'My Pickups')}
        </h1>
        <p className="text-default-500 text-sm mt-1">
          {t('marketplace.pickup.my_pickups_subtitle', 'Show this code at the seller\'s location to collect your order.')}
        </p>
      </div>

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" color="primary" />
        </div>
      ) : reservations.length === 0 ? (
        <GlassCard className="p-8 text-center text-default-500">
          {t('marketplace.pickup.no_pickups', 'No upcoming pickups.')}
        </GlassCard>
      ) : (
        <div className="grid gap-4">
          {reservations.map((r) => (
            <GlassCard key={r.id} className="p-5 space-y-3">
              <div className="flex justify-between items-start gap-4 flex-wrap">
                <div>
                  <p className="font-semibold text-foreground">
                    {r.listing_title || t('marketplace.pickup.order_n', 'Order #{{id}}', { id: r.order_id })}
                  </p>
                  <p className="text-sm text-default-500">
                    {t('marketplace.pickup.window', 'Pickup window')}: {formatTime(r.slot?.slot_start ?? null)}
                  </p>
                </div>
                <Chip color={statusColor(r.status)} variant="flat" size="sm">
                  {t(`marketplace.pickup.status_${r.status}`, r.status)}
                </Chip>
              </div>

              {r.status === 'reserved' && (
                <div className="flex items-center gap-3 p-3 rounded-md bg-default-100">
                  <QrCode className="w-10 h-10 text-primary shrink-0" />
                  <div className="flex-1 min-w-0">
                    <p className="text-xs text-default-500">
                      {t('marketplace.pickup.show_this_code', 'Show this code to the seller')}
                    </p>
                    <p className="font-mono text-sm font-semibold break-all text-foreground">
                      {r.qr_code}
                    </p>
                  </div>
                </div>
              )}
            </GlassCard>
          ))}
        </div>
      )}
    </div>
  );
}

export default MyPickupsPage;
