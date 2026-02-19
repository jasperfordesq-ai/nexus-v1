// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin 404 Page
 * Shown when a user navigates to an unknown admin path.
 * Keeps the admin layout (sidebar + header) intact.
 */

import { Link } from 'react-router-dom';
import { Card, CardBody, Button } from '@heroui/react';
import { FileQuestion, ArrowLeft } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { PageHeader } from '../components';

export function AdminNotFound() {
  usePageTitle('Admin - Page Not Found');
  const { tenantPath } = useTenant();

  return (
    <div>
      <PageHeader title="Page Not Found" />
      <Card shadow="sm">
        <CardBody className="flex flex-col items-center justify-center py-16 text-center">
          <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-danger/10">
            <FileQuestion size={32} className="text-danger" />
          </div>
          <h3 className="text-lg font-semibold text-foreground">
            Admin Page Not Found
          </h3>
          <p className="mt-2 max-w-md text-sm text-default-500">
            The admin page you're looking for doesn't exist or has been moved.
          </p>
          <Button
            as={Link}
            to={tenantPath('/admin')}
            variant="flat"
            color="primary"
            className="mt-6"
            startContent={<ArrowLeft size={16} />}
          >
            Back to Admin Dashboard
          </Button>
        </CardBody>
      </Card>
    </div>
  );
}

export default AdminNotFound;
