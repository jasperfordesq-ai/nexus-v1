/**
 * Loading Screen - Shown while tenant config loads
 */

import { Spinner } from '@heroui/react';

export function LoadingScreen() {
  return (
    <div className="min-h-screen flex items-center justify-center nexus-bg">
      <div className="text-center glass p-8 rounded-2xl">
        <Spinner size="lg" color="primary" />
        <p className="mt-4 text-gray-600">Loading...</p>
      </div>
    </div>
  );
}
