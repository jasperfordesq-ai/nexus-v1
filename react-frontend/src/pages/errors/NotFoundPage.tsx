/**
 * 404 Not Found Page
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import { Home, ArrowLeft, Search } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';

export function NotFoundPage() {
  const { tenantPath } = useTenant();
  usePageTitle('Page Not Found');
  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4">
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="w-full max-w-md"
      >
        <GlassCard className="p-8 text-center">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 mb-6">
            <span className="text-5xl font-bold text-gradient">404</span>
          </div>

          <h1 className="text-2xl font-bold text-theme-primary mb-2">Page Not Found</h1>
          <p className="text-theme-muted mb-8">
            The page you&apos;re looking for doesn&apos;t exist or has been moved.
          </p>

          <div className="flex flex-col sm:flex-row gap-3">
            <Link to={tenantPath('/')} className="flex-1">
              <Button
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Home className="w-4 h-4" />}
              >
                Go Home
              </Button>
            </Link>
            <Link to={tenantPath('/search')} className="flex-1">
              <Button
                variant="flat"
                className="w-full bg-theme-elevated text-theme-muted"
                startContent={<Search className="w-4 h-4" />}
              >
                Search
              </Button>
            </Link>
          </div>

          <Button
            variant="light"
            size="sm"
            className="mt-6 text-theme-subtle"
            startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
            onPress={() => window.history.back()}
          >
            Go back
          </Button>
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default NotFoundPage;
