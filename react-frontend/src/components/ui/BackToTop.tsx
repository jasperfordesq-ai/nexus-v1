// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BackToTop - Floating scroll-to-top button
 * Appears after scrolling 400px, smooth scrolls to top on click
 */

import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { motion, AnimatePresence } from '@/lib/motion';import ArrowUp from 'lucide-react/icons/arrow-up';
import { Button } from '@/components/ui';

const SCROLL_THRESHOLD = 400;

export function BackToTop() {
  const { t } = useTranslation('common');
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    function handleScroll() {
      setIsVisible(window.scrollY > SCROLL_THRESHOLD);
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <AnimatePresence>
      {isVisible && (
        <motion.div
          initial={{ opacity: 0, scale: 0.8 }}
          animate={{ opacity: 1, scale: 1 }}
          exit={{ opacity: 0, scale: 0.8 }}
          transition={{ duration: 0.2 }}
          className="fixed bottom-[calc(var(--safe-area-bottom)+8.75rem)] right-3 z-40 md:bottom-24 md:right-6"
        >
          <Button
            isIconOnly
            size="sm"
            onPress={scrollToTop}
            className="bg-[var(--surface-elevated)] border border-[var(--border-default)] text-theme-muted hover:text-theme-primary shadow-lg backdrop-blur-sm"
            aria-label={t('accessibility.scroll_to_top')}
          >
            <ArrowUp className="w-4 h-4" aria-hidden="true" />
          </Button>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

export default BackToTop;
