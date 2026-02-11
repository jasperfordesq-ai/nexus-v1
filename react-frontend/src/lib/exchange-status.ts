/**
 * Shared Exchange Status Configuration
 * Used by ExchangesPage, ExchangeDetailPage, and other exchange-related components
 */

import {
  Clock,
  CheckCircle,
  XCircle,
  AlertTriangle,
  ArrowRightLeft,
  type LucideIcon,
} from 'lucide-react';
import type { ExchangeStatus } from '@/types/api';

export interface ExchangeStatusConfig {
  label: string;
  color: 'warning' | 'primary' | 'success' | 'danger' | 'secondary' | 'default';
  icon: LucideIcon;
  description: string;
}

export const EXCHANGE_STATUS_CONFIG: Record<ExchangeStatus, ExchangeStatusConfig> = {
  pending_provider: {
    label: 'Awaiting Provider',
    color: 'warning',
    icon: Clock,
    description: 'Waiting for the service provider to accept or decline this request.',
  },
  pending_broker: {
    label: 'Awaiting Broker',
    color: 'secondary',
    icon: Clock,
    description: 'This exchange requires broker approval before it can proceed.',
  },
  accepted: {
    label: 'Accepted',
    color: 'primary',
    icon: CheckCircle,
    description: 'The exchange has been accepted. The provider can start when ready.',
  },
  in_progress: {
    label: 'In Progress',
    color: 'primary',
    icon: ArrowRightLeft,
    description: 'The service is currently being provided.',
  },
  pending_confirmation: {
    label: 'Confirm Hours',
    color: 'warning',
    icon: AlertTriangle,
    description: 'Both parties need to confirm the hours worked to complete the exchange.',
  },
  completed: {
    label: 'Completed',
    color: 'success',
    icon: CheckCircle,
    description: 'The exchange has been completed and credits have been transferred.',
  },
  disputed: {
    label: 'Disputed',
    color: 'danger',
    icon: AlertTriangle,
    description: 'There is a disagreement about the hours. A broker will review this.',
  },
  cancelled: {
    label: 'Cancelled',
    color: 'default',
    icon: XCircle,
    description: 'This exchange was cancelled.',
  },
};

/** Maximum hours allowed per exchange (typo protection) */
export const MAX_EXCHANGE_HOURS = 100;

/**
 * Get the color class for a status icon background
 */
export function getStatusIconBgClass(color: ExchangeStatusConfig['color']): string {
  switch (color) {
    case 'success':
      return 'bg-emerald-500/20 text-emerald-400';
    case 'warning':
      return 'bg-amber-500/20 text-amber-400';
    case 'danger':
      return 'bg-red-500/20 text-red-400';
    case 'primary':
      return 'bg-indigo-500/20 text-indigo-400';
    case 'secondary':
      return 'bg-purple-500/20 text-purple-400';
    default:
      return 'bg-theme-elevated text-theme-muted';
  }
}
