// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Safeguarding Options Page — reuses the admin SafeguardingOptionsAdmin
 * module untouched inside the broker shell. See adminEmbed for the pattern.
 */

import { useTranslation } from 'react-i18next';
import Shield from 'lucide-react/icons/shield';
import SafeguardingOptionsAdmin from '@/admin/modules/safeguarding/SafeguardingOptionsAdmin';
import { BrokerPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function SafeguardingOptionsPage() {
  const { t } = useTranslation('broker');

  return (
    <BrokerPageShell
      title={t('safeguarding_options.title')}
      description={t('safeguarding_options.description')}
      icon={Shield}
      color="danger"
    >
      <div className={EMBED_RESTYLE}>
        <SafeguardingOptionsAdmin />
      </div>
    </BrokerPageShell>
  );
}
