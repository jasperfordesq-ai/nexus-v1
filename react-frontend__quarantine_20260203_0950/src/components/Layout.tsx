/**
 * Layout - Main app layout with navbar
 */

import { Outlet } from 'react-router-dom';
import { Navbar } from './Navbar';
import { useTenant } from '../tenant';

export function Layout() {
  const tenant = useTenant();

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Outlet />
      </main>

      <footer className="bg-white border-t mt-auto">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="text-center text-sm text-gray-500">
            {tenant.config?.footer_text && (
              <p className="mb-2">{tenant.config.footer_text}</p>
            )}
            <p>&copy; {new Date().getFullYear()} {tenant.name}</p>
          </div>
        </div>
      </footer>
    </div>
  );
}
