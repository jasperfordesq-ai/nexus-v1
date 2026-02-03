/**
 * Components - Public exports
 */

export { LoadingScreen } from './LoadingScreen';
export { ErrorScreen } from './ErrorScreen';

// Layout components (from layout/ folder)
export { AppShell } from './layout/AppShell';
export { Header } from './layout/Header';
export { Footer } from './layout/Footer';
export { MobileNav } from './layout/MobileNav';
export { ProtectedRoute } from './layout/ProtectedRoute';

// UI components (NEXUS visual identity)
export {
  GlassCard,
  GlassCardHeader,
  GlassCardBody,
  GlassCardFooter,
  type GlassCardVariant,
} from './ui';

// Legacy exports (deprecated - use AppShell instead)
export { Navbar } from './Navbar';
export { Layout as LegacyLayout } from './Layout';
