// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Geocode (stub)
 * Legacy PHP admin screen for triggering geocoding across groups has not been
 * migrated to React. This stub makes the gap explicit instead of silently
 * redirecting to the Groups list.
 */

import { Card, CardBody, CardHeader, Divider, Code } from '@heroui/react';
import { MapPin, Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { PageHeader } from '../../components';

export function GroupGeocode() {
  const { t } = useTranslation('admin');
  usePageTitle(t('groups.geocode_title', 'Geocode Groups'));

  return (
    <div>
      <PageHeader
        title={t('groups.geocode_title', 'Geocode Groups')}
        description={t(
          'groups.geocode_description',
          'Batch-geocode group locations so they appear on the map.',
        )}
      />

      <Card shadow="sm" className="border border-warning/30 bg-warning/5 max-w-2xl">
        <CardHeader className="flex items-center gap-2">
          <MapPin size={20} className="text-warning" />
          <h3 className="text-lg font-semibold">
            {t('groups.geocode_not_migrated_title', 'Admin UI not yet migrated')}
          </h3>
        </CardHeader>
        <Divider />
        <CardBody className="gap-3">
          <div className="flex items-start gap-3">
            <Info size={18} className="text-default-400 shrink-0 mt-0.5" />
            <p className="text-sm text-default-600">
              {t(
                'groups.geocode_not_migrated_desc',
                'The batch geocoding admin screen has not yet been rebuilt in React. For now, run the Artisan command on the server:',
              )}
            </p>
          </div>
          <Code className="text-xs">php artisan groups:geocode</Code>
          <p className="text-xs text-default-400">
            {t(
              'groups.geocode_not_migrated_note',
              'TODO: rebuild this screen with progress reporting, retry controls and per-group status.',
            )}
          </p>
        </CardBody>
      </Card>
    </div>
  );
}

export default GroupGeocode;
