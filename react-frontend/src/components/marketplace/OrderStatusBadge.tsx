// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OrderStatusBadge — Renders a HeroUI Chip for marketplace order statuses.
 *
 * Maps each order status to a semantic color:
 *   pending_payment → warning, paid → primary, shipped → secondary,
 *   delivered → success, completed → success, disputed → danger,
 *   refunded → default, cancelled → default
 */

import { Chip } from '@heroui/react';
import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface OrderStatusBadgeProps {
  status: string;
  size?: 'sm' | 'md' | 'lg';
}

// ─────────────────────────────────────────────────────────────────────────────
// Status Maps
// ─────────────────────────────────────────────────────────────────────────────

const STATUS_COLOR_MAP: Record<string, 'warning' | 'primary' | 'secondary' | 'success' | 'danger' | 'default'> = {
  pending_payment: 'warning',
  paid: 'primary',
  shipped: 'secondary',
  delivered: 'success',
  completed: 'success',
  disputed: 'danger',
  refunded: 'default',
  cancelled: 'default',
};

const STATUS_LABEL_MAP: Record<string, string> = {
  pending_payment: 'Pending Payment',
  paid: 'Paid',
  shipped: 'Shipped',
  delivered: 'Delivered',
  completed: 'Completed',
  disputed: 'Disputed',
  refunded: 'Refunded',
  cancelled: 'Cancelled',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function OrderStatusBadge({ status, size = 'sm' }: OrderStatusBadgeProps) {
  const { t } = useTranslation('marketplace');

  const color = STATUS_COLOR_MAP[status] ?? 'default';
  const label = t(
    `orders.status.${status}`,
    STATUS_LABEL_MAP[status] ?? status.replace(/_/g, ' '),
  );

  return (
    <Chip size={size} color={color} variant="flat">
      {label}
    </Chip>
  );
}

export default OrderStatusBadge;
