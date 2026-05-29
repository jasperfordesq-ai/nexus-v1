import { useTranslation } from 'react-i18next';
import { Chip } from '@/components/ui';

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

const STATUS_COLOR_MAP: Record<string, 'warning' | 'accent' | 'success' | 'danger' | 'default'> = {
  pending_payment: 'warning',
  paid: 'accent',
  shipped: 'default',
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
    <Chip size={size} color={color} variant="tertiary">
      {label}
    </Chip>
  );
}

export default OrderStatusBadge;
