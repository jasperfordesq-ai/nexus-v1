// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MobileFAB — floating action button for mobile quick post creation.
 */

import { Button } from '@heroui/react';
import { PenSquare } from 'lucide-react';
import { motion } from 'framer-motion';
import { useTranslation } from 'react-i18next';

interface MobileFABProps {
  onPress: () => void;
}

export function MobileFAB({ onPress }: MobileFABProps) {
  const { t } = useTranslation('feed');

  return (
    <motion.div
      className="fixed bottom-6 right-6 z-40 md:hidden"
      initial={{ scale: 0, opacity: 0 }}
      animate={{ scale: 1, opacity: 1 }}
      transition={{ type: 'spring', stiffness: 260, damping: 20, delay: 0.3 }}
    >
      <Button
        isIconOnly
        className="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30"
        onPress={onPress}
        aria-label={t('compose.create_post', 'Create post')}
      >
        <PenSquare className="w-5 h-5" />
      </Button>
    </motion.div>
  );
}

export default MobileFAB;
