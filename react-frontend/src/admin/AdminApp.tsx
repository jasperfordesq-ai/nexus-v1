/**
 * Admin App Entry Point (lazy-loaded)
 *
 * This component bundles the entire admin panel — route guard, layout, and all
 * sub-routes — into a single code-split chunk.  It is loaded via React.lazy()
 * in App.tsx so that none of the admin UI (sidebar, header, 100+ page modules,
 * recharts, jsPDF, etc.) ends up in the main application bundle.
 */

import { Routes, Route } from 'react-router-dom';
import { AdminRoute } from './AdminRoute';
import { AdminLayout } from './AdminLayout';
import { AdminRoutes } from './routes';

export default function AdminApp() {
  return (
    <Routes>
      <Route element={<AdminRoute />}>
        <Route element={<AdminLayout />}>
          {AdminRoutes()}
        </Route>
      </Route>
    </Routes>
  );
}
