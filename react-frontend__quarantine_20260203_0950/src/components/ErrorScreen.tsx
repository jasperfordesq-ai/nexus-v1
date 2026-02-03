/**
 * Error Screen - Shown when tenant bootstrap fails
 */

import { Button } from '@heroui/react';

interface ErrorScreenProps {
  message: string;
  statusCode?: number | null;
  onRetry?: () => void;
}

export function ErrorScreen({ message, statusCode, onRetry }: ErrorScreenProps) {
  return (
    <div className="min-h-screen flex items-center justify-center nexus-bg">
      <div className="text-center max-w-md px-4 glass p-8 rounded-2xl">
        <div className="mb-6">
          <svg
            className="mx-auto h-16 w-16 text-red-500"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
        </div>

        <h1 className="text-xl font-semibold text-gray-900 mb-2">
          Failed to Load Application
        </h1>

        <p className="text-gray-600 mb-2">{message}</p>

        {statusCode && (
          <p className="text-sm text-gray-500 mb-6">
            Status code: {statusCode}
          </p>
        )}

        {onRetry && (
          <Button color="primary" onPress={onRetry}>
            Try Again
          </Button>
        )}

        <p className="mt-6 text-sm text-gray-500">
          If this problem persists, please contact support.
        </p>
      </div>
    </div>
  );
}
