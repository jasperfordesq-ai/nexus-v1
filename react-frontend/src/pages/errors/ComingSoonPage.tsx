// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Coming Soon Page - For features not yet implemented
 */

import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@heroui/react';
import { Home, ArrowLeft, Construction } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';

interface ComingSoonPageProps {
  feature?: string;
}

export function ComingSoonPage({ feature = 'This feature' }: ComingSoonPageProps) {
  const { tenantPath } = useTenant();
  usePageTitle('Coming Soon');
  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4">
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="w-full max-w-md"
      >
        <GlassCard className="p-8 text-center">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 mb-6">
            <Construction className="w-10 h-10 text-amber-400" />
          </div>

          <h1 className="text-2xl font-bold text-theme-primary mb-2">Coming Soon</h1>
          <p className="text-theme-muted mb-8">
            {feature} is currently under development. Check back soon!
          </p>

          <div className="flex flex-col sm:flex-row gap-3">
            <Link to={tenantPath('/dashboard')} className="flex-1">
              <Button
                className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Home className="w-4 h-4" />}
              >
                Dashboard
              </Button>
            </Link>
            <Button
              variant="flat"
              className="w-full bg-theme-elevated text-theme-muted flex-1"
              startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              onPress={() => window.history.back()}
            >
              Go Back
            </Button>
          </div>
        </GlassCard>
      </motion.div>
    </div>
  );
}

export default ComingSoonPage;
