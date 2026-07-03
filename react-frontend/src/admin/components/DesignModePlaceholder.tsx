// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * DesignModePlaceholder — the single mount point for the Phase 2 GrapesJS +
 * MJML drag-and-drop builder. Phase 1 ships this stub so the mode switcher,
 * guard matrix and DB enum already account for 'builder'/'design'; Phase 2
 * swaps this body for a lazy <NewsletterBuilder> with zero rework elsewhere.
 */

import { Card, CardBody } from '@/components/ui';
import Paintbrush from 'lucide-react/icons/paintbrush';
import { useTranslation } from 'react-i18next';

export function DesignModePlaceholder() {
  const { t } = useTranslation('admin');
  return (
    <Card>
      <CardBody className="flex flex-col items-center gap-3 py-12 text-center">
        <Paintbrush size={32} className="text-muted" />
        <p className="text-sm font-medium text-foreground">
          {t('newsletter_content_editor.builder_coming_soon_title')}
        </p>
        <p className="max-w-md text-xs text-muted">
          {t('newsletter_content_editor.builder_coming_soon_desc')}
        </p>
      </CardBody>
    </Card>
  );
}

export default DesignModePlaceholder;
