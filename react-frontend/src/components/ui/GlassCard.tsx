import { forwardRef, type ReactNode, type CSSProperties, type MouseEventHandler } from 'react';
import { motion, type Variants } from 'framer-motion';

export interface GlassCardProps {
  children: ReactNode;
  /** Enable hover lift effect */
  hoverable?: boolean;
  /** Add glow effect */
  glow?: 'primary' | 'secondary' | 'accent' | 'none';
  /** Use Framer Motion for smooth animations */
  animated?: boolean;
  /** Additional CSS classes */
  className?: string;
  /** Inline styles */
  style?: CSSProperties;
  /** Click handler */
  onClick?: MouseEventHandler<HTMLDivElement>;
}

const cardVariants: Variants = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

/**
 * GlassCard - Glassmorphism card component
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
      style,
      onClick,
    },
    ref
  ) => {
    const baseClass = hoverable ? 'glass-card-hover' : 'glass-card';
    const glowClass = glow !== 'none' ? `glow-${glow}` : '';
    const combinedClassName = [baseClass, glowClass, className].filter(Boolean).join(' ');

    if (animated) {
      return (
        <motion.div
          ref={ref}
          className={combinedClassName}
          style={style}
          onClick={onClick}
          variants={cardVariants}
          initial="hidden"
          animate="visible"
          transition={{ duration: 0.3, ease: 'easeOut' }}
          whileHover={hoverable ? { y: -4, transition: { duration: 0.2 } } : undefined}
        >
          {children}
        </motion.div>
      );
    }

    return (
      <div ref={ref} className={combinedClassName} style={style} onClick={onClick}>
        {children}
      </div>
    );
  }
);

GlassCard.displayName = 'GlassCard';

export default GlassCard;
