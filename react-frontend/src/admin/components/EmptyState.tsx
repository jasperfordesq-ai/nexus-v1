/**
 * Empty State Component
 * Shown when a list or page has no data
 */

import { Card, CardBody, Button } from '@heroui/react';
import { Inbox, type LucideIcon } from 'lucide-react';

interface EmptyStateProps {
  icon?: LucideIcon;
  title: string;
  description?: string;
  actionLabel?: string;
  onAction?: () => void;
}

export function EmptyState({
  icon: Icon = Inbox,
  title,
  description,
  actionLabel,
  onAction,
}: EmptyStateProps) {
  return (
    <Card shadow="sm">
      <CardBody className="flex flex-col items-center justify-center py-16 text-center">
        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-default-100">
          <Icon size={32} className="text-default-400" />
        </div>
        <h3 className="text-lg font-semibold text-foreground">{title}</h3>
        {description && (
          <p className="mt-1 max-w-md text-sm text-default-500">{description}</p>
        )}
        {actionLabel && onAction && (
          <Button
            color="primary"
            className="mt-4"
            onPress={onAction}
          >
            {actionLabel}
          </Button>
        )}
      </CardBody>
    </Card>
  );
}

export default EmptyState;
