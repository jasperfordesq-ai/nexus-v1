/**
 * 404 Not Found Page
 */

import { Link } from 'react-router-dom';
import { Button } from '@heroui/react';

export function NotFoundPage() {
  return (
    <div className="min-h-[60vh] flex items-center justify-center">
      <div className="text-center">
        <h1 className="text-6xl font-bold text-gray-300 mb-4">404</h1>
        <h2 className="text-2xl font-semibold text-gray-900 mb-2">
          Page Not Found
        </h2>
        <p className="text-gray-600 mb-6">
          The page you're looking for doesn't exist or has been moved.
        </p>
        <Button as={Link} to="/" color="primary">
          Go Home
        </Button>
      </div>
    </div>
  );
}
