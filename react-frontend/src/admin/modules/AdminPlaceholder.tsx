// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Module Placeholder
 * Shown for admin modules not yet migrated to React.
 * Displays the module name and provides a link to the legacy PHP admin.
 */

import { Card, CardBody, Button } from '@heroui/react';
import { Construction, ExternalLink } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { PageHeader } from '../components';

interface AdminPlaceholderProps {
  title: string;
  description?: string;
  legacyPath?: string;
}

export function AdminPlaceholder({ title, description, legacyPath }: AdminPlaceholderProps) {
  usePageTitle(`Admin - ${title}`);

  const apiBase = import.meta.env.VITE_API_BASE?.replace('/api', '') || '';

  return (
    <div>
      <PageHeader title={title} description={description} />
      <Card shadow="sm">
        <CardBody className="flex flex-col items-center justify-center py-16 text-center">
          <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-warning/10">
            <Construction size={32} className="text-warning" />
          </div>
          <h3 className="text-lg font-semibold text-foreground">
            Migration In Progress
          </h3>
          <p className="mt-2 max-w-md text-sm text-default-500">
            This module is being migrated from the legacy PHP admin panel to React.
            Full functionality will be available here soon.
          </p>
          {legacyPath && apiBase && (
            <Button
              as="a"
              href={`${apiBase}${legacyPath}`}
              target="_blank"
              rel="noopener"
              variant="flat"
              className="mt-6"
              endContent={<ExternalLink size={14} />}
            >
              Open in Legacy Admin
            </Button>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default AdminPlaceholder;
