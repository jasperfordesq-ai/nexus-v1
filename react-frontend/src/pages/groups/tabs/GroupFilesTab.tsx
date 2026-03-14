// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Files Tab (GR1)
 * File sharing within a group — currently a "Coming Soon" placeholder
 * while the backend API endpoints are being developed.
 */

import { Card, CardBody } from '@heroui/react';
import { FolderOpen, Construction } from 'lucide-react';
import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GroupFilesTabProps {
  groupId: number;
  isAdmin: boolean;
  isMember?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GroupFilesTab({ groupId: _groupId, isAdmin: _isAdmin }: GroupFilesTabProps) {
  const { t } = useTranslation('groups');

  return (
    <Card className="bg-content1 border border-theme-default">
      <CardBody className="flex flex-col items-center justify-center py-16 px-6 text-center">
        <div className="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-4">
          <FolderOpen className="w-8 h-8 text-primary" aria-hidden="true" />
        </div>

        <h2 className="text-xl font-semibold text-theme-primary mb-2">
          {t('files.heading', 'Files')}
        </h2>

        <div className="flex items-center gap-2 text-theme-subtle mb-3">
          <Construction className="w-4 h-4" aria-hidden="true" />
          <span className="text-sm font-medium">
            {t('files.coming_soon', 'Coming Soon')}
          </span>
        </div>

        <p className="text-sm text-theme-subtle max-w-md">
          {t(
            'files.coming_soon_description',
            'Group file sharing is currently in development. Soon you will be able to upload, download, and manage files within your group.'
          )}
        </p>
      </CardBody>
    </Card>
  );
}

export default GroupFilesTab;
