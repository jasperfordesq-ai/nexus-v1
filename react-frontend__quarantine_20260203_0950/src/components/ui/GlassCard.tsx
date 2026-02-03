/**
 * GlassCard - NEXUS Glassmorphism Card Component
 *
 * A reusable card component with glassmorphism styling that maintains
 * the NEXUS visual identity across the application.
 *
 * Variants:
 * - default: Standard glass card with subtle blur
 * - elevated: Floating card with hover lift effect
 * - primary: Glass card with tenant primary color tint
 * - solid: Less transparent, more readable on busy backgrounds
 *
 * Features:
 * - Backdrop blur effect
 * - Translucent background
 * - Subtle border highlight
 * - Optional hover animation
 * - Consistent depth/shadow system
 */

import { ReactNode } from 'react';

export type GlassCardVariant = 'default' | 'elevated' | 'primary' | 'solid';

interface GlassCardProps {
  children: ReactNode;
  variant?: GlassCardVariant;
  className?: string;
  as?: 'div' | 'article' | 'section';
  padding?: 'none' | 'sm' | 'md' | 'lg';
  hoverable?: boolean;
  onClick?: () => void;
}

const variantClasses: Record<GlassCardVariant, string> = {
  default: 'glass',
  elevated: 'glass-elevated',
  primary: 'glass-primary',
  solid: 'glass-strong',
};

const paddingClasses: Record<string, string> = {
  none: '',
  sm: 'p-4',
  md: 'p-6',
  lg: 'p-8',
};

export function GlassCard({
  children,
  variant = 'default',
  className = '',
  as: Component = 'div',
  padding = 'md',
  hoverable = false,
  onClick,
}: GlassCardProps) {
  const baseClasses = 'rounded-2xl overflow-hidden';
  const variantClass = variantClasses[variant];
  const paddingClass = paddingClasses[padding];
  const hoverClass = hoverable ? 'cursor-pointer transition-all duration-200 hover:shadow-elevated hover:-translate-y-0.5' : '';

  return (
    <Component
      className={`${baseClasses} ${variantClass} ${paddingClass} ${hoverClass} ${className}`}
      onClick={onClick}
    >
      {children}
    </Component>
  );
}

/**
 * GlassCardHeader - Header section for GlassCard
 */
interface GlassCardHeaderProps {
  children: ReactNode;
  className?: string;
  divider?: boolean;
}

export function GlassCardHeader({
  children,
  className = '',
  divider = false,
}: GlassCardHeaderProps) {
  return (
    <div className={`${divider ? 'pb-4 mb-4 border-b border-white/20' : ''} ${className}`}>
      {children}
    </div>
  );
}

/**
 * GlassCardBody - Main content area for GlassCard
 */
interface GlassCardBodyProps {
  children: ReactNode;
  className?: string;
}

export function GlassCardBody({ children, className = '' }: GlassCardBodyProps) {
  return <div className={className}>{children}</div>;
}

/**
 * GlassCardFooter - Footer section for GlassCard
 */
interface GlassCardFooterProps {
  children: ReactNode;
  className?: string;
  divider?: boolean;
}

export function GlassCardFooter({
  children,
  className = '',
  divider = false,
}: GlassCardFooterProps) {
  return (
    <div className={`${divider ? 'pt-4 mt-4 border-t border-white/20' : ''} ${className}`}>
      {children}
    </div>
  );
}
