// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { forwardRef, type ReactNode, type HTMLAttributes } from 'react';
import { Card } from '@heroui/react';
import { motion, type Variants } from 'framer-motion';

/** Exclude HTML event props that conflict with Framer Motion's signatures */
type SafeHtmlProps = Omit<HTMLAttributes<HTMLDivElement>, 'children' | 'onDrag' | 'onDragStart' | 'onDragEnd' | 'onAnimationStart'>;

export interface GlassCardProps extends SafeHtmlProps {
  children: ReactNode;
  /** Enable hover lift effect */
  hoverable?: boolean;
  /** Add glow effect */
  glow?: 'primary' | 'secondary' | 'accent' | 'none';
  /** Use Framer Motion for smooth animations */
  animated?: boolean;
}

const cardVariants: Variants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

/**
 * GlassCard - Glassmorphism card component built on HeroUI Card
 *
 * Uses centralized CSS utilities from styles/glass.css
 */
export const GlassCard = forwardRef<HTMLDivElement, GlassCardProps>(
  (
    {
      children,
      hoverable = false,
      glow = 'none',
      animated = false,
      className = '',
      ...rest
    },
    ref
  ) => {
    const baseClass = hoverable ? 'glass-card-hover' : 'glass-card';
    const glowClass = glow !== 'none' ? `glow-${glow}` : '';
    const combinedClassName = ['backdrop-blur-lg', baseClass, glowClass, className].filter(Boolean).join(' ');

    if (animated) {
      return (
        <motion.div
          ref={ref}
          variants={cardVariants}
          initial="hidden"
          animate="visible"
          transition={{ duration: 0.3, ease: 'easeOut' }}
          whileHover={hoverable ? { y: -4, transition: { duration: 0.2 } } : undefined}
          {...(rest as object)}
        >
          <Card
            classNames={{ base: combinedClassName }}
            shadow="none"
            radius="none"
          >
            {children}
          </Card>
        </motion.div>
      );
    }

    return (
      <Card
        ref={ref}
        classNames={{ base: combinedClassName }}
        shadow="none"
        radius="none"
        {...(rest as object)}
      >
        {children}
      </Card>
    );
  }
);

GlassCard.displayName = 'GlassCard';

export default GlassCard;
