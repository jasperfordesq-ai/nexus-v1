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

interface Props {
  domain?: string | null;
  verifiedAt?: string | null;
  size?: 'sm' | 'md';
  className?: string;
}

export function VerifiedMunicipalityBadge({ domain, verifiedAt, size = 'sm', className }: Props) {
  const tooltipParts: string[] = [
    'This community has been verified as a municipal partner.',
  ];
  if (domain) tooltipParts.push(`Verified domain: ${domain}`);
  if (verifiedAt) {
    try {
      tooltipParts.push(`Verified on ${new Date(verifiedAt).toLocaleDateString()}`);
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
        Verified municipality
      </Chip>
    </Tooltip>
  );
}

export default VerifiedMunicipalityBadge;
