// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VerifiedMunicipalityBadge — AG29
 *
 * Small chip with a shield icon indicating that a tenant's municipal
 * impact reports have been verified (DNS-attested or admin-attested).
 */

import { Chip, Tooltip } from '@heroui/react';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';

interface Props {
  domain?: string | null;
  verifiedAt?: string | null;
  size?: 'sm' | 'md';
  className?: string;
}

export function VerifiedMunicipalityBadge({ domain, verifiedAt, size = 'sm', className }: Props) {
  const { t } = useTranslation('common');
  const tooltipParts: string[] = [
    t('verified_municipality.tooltip'),
  ];
  if (domain) tooltipParts.push(t('verified_municipality.domain', { domain }));
  if (verifiedAt) {
    try {
      tooltipParts.push(t('verified_municipality.verified_on', { date: new Date(verifiedAt).toLocaleDateString() }));
    } catch {
      /* ignore */
    }
  }

  return (
    <Tooltip content={tooltipParts.join(' · ')} placement="bottom">
      <Chip
        size={size}
        color="success"
        variant="flat"
        startContent={<ShieldCheck className="w-3.5 h-3.5" />}
        className={className}
      >
        {t('verified_municipality.label')}
      </Chip>
    </Tooltip>
  );
}

export default VerifiedMunicipalityBadge;
