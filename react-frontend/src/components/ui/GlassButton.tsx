import { forwardRef, type ReactNode, type MouseEventHandler } from 'react';
import { motion } from 'framer-motion';

export interface GlassButtonProps {
  children: ReactNode;
  /** Button style variant */
  variant?: 'default' | 'primary' | 'accent' | 'ghost';
  /** Button size */
  size?: 'sm' | 'md' | 'lg';
  /** Use Framer Motion for hover/tap animations */
  animated?: boolean;
  /** Additional CSS classes */
  className?: string;
  /** Disabled state */
  disabled?: boolean;
  /** Button type */
  type?: 'button' | 'submit' | 'reset';
  /** Click handler */
  onClick?: MouseEventHandler<HTMLButtonElement>;
  /** Full width */
  fullWidth?: boolean;
}

const sizeClasses: Record<string, string> = {
  sm: 'glass-button-sm',
  md: 'glass-button-md',
  lg: 'glass-button-lg',
};

const variantClasses: Record<string, string> = {
  default: '',
  primary: 'glass-button-primary',
  accent: 'glass-button-accent',
  ghost: 'glass-button-ghost',
};

/**
 * GlassButton - Glassmorphism button component
 *
 * Uses centralized CSS utilities from styles/glass.css
 */
export const GlassButton = forwardRef<HTMLButtonElement, GlassButtonProps>(
  (
    {
      children,
      variant = 'default',
      size = 'md',
      animated = true,
      className = '',
      disabled = false,
      type = 'button',
      onClick,
      fullWidth = false,
    },
    ref
  ) => {
    const combinedClassName = [
      'glass-button',
      sizeClasses[size],
      variantClasses[variant],
      fullWidth ? 'w-full' : '',
      className,
    ]
      .filter(Boolean)
      .join(' ');

    if (animated && !disabled) {
      return (
        <motion.button
          ref={ref}
          type={type}
          className={combinedClassName}
          disabled={disabled}
          onClick={onClick}
          whileHover={{ scale: 1.02 }}
          whileTap={{ scale: 0.98 }}
          transition={{ duration: 0.15 }}
        >
          {children}
        </motion.button>
      );
    }

    return (
      <button
        ref={ref}
        type={type}
        className={combinedClassName}
        disabled={disabled}
        onClick={onClick}
      >
        {children}
      </button>
    );
  }
);

GlassButton.displayName = 'GlassButton';

export default GlassButton;
