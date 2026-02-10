/**
 * Empty State Component
 * Displays when there's no data to show
 */

import type { ReactNode } from 'react';
import { motion } from 'framer-motion';
import { Inbox } from 'lucide-react';
import { Button } from '@heroui/react';

export interface EmptyStateProps {
  /**
   * Icon to display (defaults to Inbox)
   */
  icon?: ReactNode;

  /**
   * Main title
   */
  title: string;

  /**
   * Description text (alias: message)
   */
  description?: string;
  message?: string;

  /**
   * Call-to-action element (flexible)
   */
  action?: ReactNode;

  /**
   * Call-to-action button text (legacy)
   */
  actionLabel?: string;

  /**
   * Call-to-action handler (legacy)
   */
  onAction?: () => void;

  /**
   * Additional class names
   */
  className?: string;
}

export function EmptyState({
  icon,
  title,
  description,
  message,
  action,
  actionLabel,
  onAction,
  className = '',
}: EmptyStateProps) {
  const displayMessage = description || message;

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className={`flex flex-col items-center justify-center py-16 text-center ${className}`}
      role="status"
      aria-label={title}
    >
      <div className="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-theme-elevated border border-theme-default mb-6" aria-hidden="true">
        {icon || <Inbox className="w-10 h-10 text-theme-subtle" />}
      </div>

      <h3 className="text-xl font-semibold text-theme-primary mb-2">{title}</h3>

      {displayMessage && (
        <p className="text-theme-subtle max-w-sm mb-6">{displayMessage}</p>
      )}

      {action}

      {actionLabel && onAction && (
        <Button
          onPress={onAction}
          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium"
        >
          {actionLabel}
        </Button>
      )}
    </motion.div>
  );
}

export default EmptyState;
