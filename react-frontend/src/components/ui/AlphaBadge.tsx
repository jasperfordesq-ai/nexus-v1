// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AlphaBadge — small "Alpha" chip for surfacing pre-release modules to end users.
 * Mirrors the admin module-config alpha chip (warning/soft) for visual consistency.
 */

import { useTranslation } from 'react-i18next';
import { Chip } from './Chip';

interface AlphaBadgeProps {
  /** Override the label (defaults to the translated "Alpha"). */
  label?: string;
  className?: string;
  size?: 'sm' | 'md';
}

export function AlphaBadge({ label, className, size = 'sm' }: AlphaBadgeProps) {
  const { t } = useTranslation('common');

  return (
    <Chip
      color="warning"
      variant="soft"
      size={size}
      className={className}
      aria-label={t('alpha_badge')}
    >
      {label ?? t('alpha_badge')}
    </Chip>
  );
}

export default AlphaBadge;
