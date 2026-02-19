/**
 * Maintenance Mode Page
 * Shown to non-admin users when the platform is under maintenance.
 */

import { Card, CardBody, Button } from '@heroui/react';
import { Wrench, LogIn } from 'lucide-react';
import { useTenant } from '@/contexts';
import { tenantPath } from '@/lib/tenant-routing';
import { usePageTitle } from '@/hooks/usePageTitle';

export function MaintenancePage() {
  usePageTitle('Maintenance');
  const { tenant } = useTenant();
  const tenantName = tenant?.name || 'Project NEXUS';
  const loginPath = tenantPath('/login', tenant?.slug);

  return (
    <div className="min-h-screen bg-linear-to-br from-primary-500 to-secondary-600 flex items-center justify-center p-4">
      <Card className="max-w-lg w-full">
        <CardBody className="text-center py-12 px-6 gap-6">
          <div className="flex justify-center">
            <div className="w-20 h-20 bg-linear-to-br from-primary-500 to-secondary-600 rounded-full flex items-center justify-center">
              <Wrench size={40} className="text-white" />
            </div>
          </div>

          <div className="space-y-3">
            <h1 className="text-3xl font-bold text-foreground">
              We'll be back soon!
            </h1>

            <p className="text-lg text-default-600">
              <strong>{tenantName}</strong> is currently undergoing scheduled maintenance
              to improve your experience.
            </p>

            <p className="text-default-500">
              We apologize for any inconvenience. Please check back in a little while.
            </p>
          </div>

          <div className="text-sm text-default-400 mt-4">
            Thank you for your patience!
          </div>

          <div className="pt-4 border-t border-divider">
            <Button
              as="a"
              href={loginPath}
              variant="flat"
              color="primary"
              startContent={<LogIn size={18} />}
              size="sm"
            >
              Admin Login
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default MaintenancePage;
