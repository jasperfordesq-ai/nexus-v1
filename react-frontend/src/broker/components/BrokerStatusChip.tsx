// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BrokerStatusChip — one status → color/label mapping for the whole broker
 * panel, so "pending" is always warning-amber and "rejected" always danger-red
 * no matter which page renders it. Labels come from the broker.status.* i18n
 * namespace; unknown statuses fall back to a prettified string on a neutral
 * chip rather than crashing or leaking a raw snake_case key.
 */

import { useTranslation } from 'react-i18next';
import { Chip } from '@/components/ui';

type ChipColor = 'success' | 'warning' | 'danger' | 'accent' | 'default';

const STATUS_COLOR: Record<string, ChipColor> = {
  // healthy / complete
  active: 'success',
  approved: 'success',
  verified: 'success',
  completed: 'success',
  accepted: 'success',
  reviewed: 'success',
  // awaiting action
  pending: 'warning',
  submitted: 'warning',
  unreviewed: 'warning',
  pending_broker: 'warning',
  pending_provider: 'warning',
  pending_confirmation: 'warning',
  in_progress: 'accent',
  // problems
  rejected: 'danger',
  expired: 'danger',
  revoked: 'danger',
  suspended: 'danger',
  banned: 'danger',
  disputed: 'danger',
  // severity scale (risk tags, safeguarding)
  low: 'default',
  medium: 'warning',
  high: 'danger',
  critical: 'danger',
  // dormant
  inactive: 'default',
  cancelled: 'default',
};

export function brokerStatusColor(status: string): ChipColor {
  return STATUS_COLOR[status] ?? 'default';
}

interface BrokerStatusChipProps {
  status: string;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export function BrokerStatusChip({ status, size = 'sm', className }: BrokerStatusChipProps) {
  const { t } = useTranslation('broker');
  const normalized = (status || '').toLowerCase();
  const label = t(`status.${normalized}`, {
    defaultValue: normalized.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
  });

  return (
    <Chip size={size} variant="soft" color={brokerStatusColor(normalized)} className={className}>
      {label}
    </Chip>
  );
}

export default BrokerStatusChip;
