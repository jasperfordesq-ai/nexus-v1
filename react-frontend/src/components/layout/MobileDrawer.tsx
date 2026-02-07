/**
 * Mobile Navigation Drawer
 * Full-screen slide-in menu for mobile devices
 * Theme-aware styling for light and dark modes
 */

import { useRef, useEffect, useCallback } from 'react';
import { Link, NavLink, useNavigate, useLocation } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Avatar, Divider } from '@heroui/react';
import {
  X,
  Home,
  LayoutDashboard,
  ListTodo,
  MessageSquare,
  Wallet,
  Users,
  Calendar,
  Settings,
  LogOut,
  HelpCircle,
  Trophy,
  Medal,
  Target,
  Hexagon,
} from 'lucide-react';
import { useAuth, useTenant, useNotifications } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { TenantFeatures } from '@/types/api';

interface MobileDrawerProps {
  isOpen: boolean;
  onClose: () => void;
}

const mainNavItems = [
  { label: 'Home', href: '/', icon: Home },
  { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, auth: true },
  { label: 'Listings', href: '/listings', icon: ListTodo },
  { label: 'Messages', href: '/messages', icon: MessageSquare, auth: true },
  { label: 'Wallet', href: '/wallet', icon: Wallet, auth: true },
];

const communityNavItems = [
  { label: 'Members', href: '/members', icon: Users, feature: 'connections' as const },
  { label: 'Events', href: '/events', icon: Calendar, feature: 'events' as const },
  { label: 'Groups', href: '/groups', icon: Users, feature: 'groups' as const },
];

const exploreNavItems = [
  { label: 'Achievements', href: '/achievements', icon: Trophy, feature: 'gamification' as const },
  { label: 'Leaderboard', href: '/leaderboard', icon: Medal, feature: 'gamification' as const },
  { label: 'Goals', href: '/goals', icon: Target, feature: 'goals' as const },
];

const supportNavItems = [
  { label: 'Help Center', href: '/help', icon: HelpCircle },
  { label: 'Contact', href: '/contact', icon: MessageSquare },
];

export function MobileDrawer({ isOpen, onClose }: MobileDrawerProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const drawerRef = useRef<HTMLDivElement>(null);
  const { user, isAuthenticated, logout } = useAuth();
  const { branding, hasFeature } = useTenant();
  const { unreadCount, counts } = useNotifications();

  // Track previous pathname to only close on actual navigation
  const prevPathRef = useRef(location.pathname);

  // Close on route change (but not on initial mount)
  useEffect(() => {
    if (prevPathRef.current !== location.pathname) {
      onClose();
      prevPathRef.current = location.pathname;
    }
  }, [location.pathname, onClose]);

  // Focus trap and escape key handling
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const lastFocusedElement = useRef<HTMLElement | null>(null);

  // Handle keyboard events (Escape, Tab trap)
  const handleKeyDown = useCallback((e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      onClose();
      return;
    }

    // Focus trap: keep focus within drawer
    if (e.key === 'Tab' && drawerRef.current) {
      const focusableElements = drawerRef.current.querySelectorAll<HTMLElement>(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      if (e.shiftKey && document.activeElement === firstElement) {
        e.preventDefault();
        lastElement?.focus();
      } else if (!e.shiftKey && document.activeElement === lastElement) {
        e.preventDefault();
        firstElement?.focus();
      }
    }
  }, [onClose]);

  // Manage focus and body scroll lock
  useEffect(() => {
    if (isOpen) {
      // Store the currently focused element to restore later
      lastFocusedElement.current = document.activeElement as HTMLElement;

      // Lock body scroll
      document.body.style.overflow = 'hidden';

      // Add keyboard listener
      document.addEventListener('keydown', handleKeyDown);

      // Focus the close button after animation
      requestAnimationFrame(() => {
        closeButtonRef.current?.focus();
      });
    } else {
      // Restore focus to previously focused element
      lastFocusedElement.current?.focus();
    }

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [isOpen, handleKeyDown]);

  const handleLogout = async () => {
    await logout();
    onClose();
    navigate('/login');
  };

  const renderNavLink = (item: {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    auth?: boolean;
    feature?: keyof TenantFeatures;
  }) => {
    // Check feature flag
    if (item.feature && !hasFeature(item.feature)) {
      return null;
    }

    // Check auth requirement
    if (item.auth && !isAuthenticated) {
      return null;
    }

    const Icon = item.icon;

    return (
      <NavLink
        key={item.href}
        to={item.href}
        className={({ isActive }) =>
          `flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium transition-all ${
            isActive
              ? 'bg-theme-active text-theme-primary'
              : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
          }`
        }
      >
        <Icon className="w-5 h-5" />
        <span>{item.label}</span>
      </NavLink>
    );
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/60 dark:bg-black/60 backdrop-blur-sm z-50"
            onClick={onClose}
            aria-hidden="true"
          />

          {/* Drawer */}
          <motion.div
            ref={drawerRef}
            initial={{ x: '100%' }}
            animate={{ x: 0 }}
            exit={{ x: '100%' }}
            transition={{ type: 'spring', damping: 25, stiffness: 200 }}
            className="fixed top-0 right-0 bottom-0 w-full max-w-sm bg-theme-overlay backdrop-blur-xl border-l border-theme-default z-50 overflow-y-auto"
            style={{ backgroundColor: 'var(--surface-overlay)' }}
            role="dialog"
            aria-modal="true"
            aria-label="Navigation menu"
          >
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-theme-default">
              <Link to="/" className="flex items-center gap-2">
                <Hexagon className="w-8 h-8 text-indigo-500 dark:text-indigo-400" />
                <span className="font-bold text-xl text-gradient">{branding.name}</span>
              </Link>
              <Button
                ref={closeButtonRef}
                isIconOnly
                variant="light"
                className="text-theme-muted hover:text-theme-primary"
                onPress={onClose}
                aria-label="Close menu"
              >
                <X className="w-6 h-6" />
              </Button>
            </div>

            {/* User Section */}
            {isAuthenticated && user && (
              <div className="p-4 border-b border-theme-default">
                <Link
                  to="/profile"
                  className="flex items-center gap-3"
                >
                  <Avatar
                    name={`${user.first_name} ${user.last_name}`}
                    src={resolveAvatarUrl(user.avatar_url || user.avatar)}
                    size="lg"
                    showFallback
                  />
                  <div>
                    <p className="font-semibold text-theme-primary">
                      {user.first_name} {user.last_name}
                    </p>
                    <p className="text-sm text-theme-subtle">{user.email}</p>
                  </div>
                </Link>

                {/* Quick Stats */}
                <div className="grid grid-cols-3 gap-4 mt-4">
                  <Link
                    to="/wallet"
                    className="text-center p-2 rounded-xl bg-theme-elevated hover:bg-theme-hover transition-colors"
                  >
                    <p className="text-lg font-bold text-theme-primary">
                      {user.balance ?? 0}
                    </p>
                    <p className="text-xs text-theme-subtle">Credits</p>
                  </Link>
                  <Link
                    to="/messages"
                    className="text-center p-2 rounded-xl bg-theme-elevated hover:bg-theme-hover transition-colors relative"
                  >
                    <p className="text-lg font-bold text-theme-primary">
                      {counts.messages > 0 ? counts.messages : 0}
                    </p>
                    <p className="text-xs text-theme-subtle">Messages</p>
                    {counts.messages > 0 && (
                      <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
                    )}
                  </Link>
                  <Link
                    to="/notifications"
                    className="text-center p-2 rounded-xl bg-theme-elevated hover:bg-theme-hover transition-colors relative"
                  >
                    <p className="text-lg font-bold text-theme-primary">
                      {unreadCount > 0 ? unreadCount : 0}
                    </p>
                    <p className="text-xs text-theme-subtle">Alerts</p>
                    {unreadCount > 0 && (
                      <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
                    )}
                  </Link>
                </div>
              </div>
            )}

            {/* Navigation */}
            <nav className="p-4 space-y-6" aria-label="Mobile navigation">
              {/* Main */}
              <div className="space-y-1">
                {mainNavItems.map(renderNavLink)}
              </div>

              {/* Community */}
              {(hasFeature('connections') || hasFeature('events') || hasFeature('groups')) && (
                <div>
                  <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                    Community
                  </p>
                  <div className="space-y-1">
                    {communityNavItems.map(renderNavLink)}
                  </div>
                </div>
              )}

              {/* Explore */}
              {(hasFeature('gamification') || hasFeature('goals')) && (
                <div>
                  <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                    Explore
                  </p>
                  <div className="space-y-1">
                    {exploreNavItems.map(renderNavLink)}
                  </div>
                </div>
              )}

              {/* Support */}
              <div>
                <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                  Support
                </p>
                <div className="space-y-1">
                  {supportNavItems.map(renderNavLink)}
                </div>
              </div>

              {/* Account */}
              {isAuthenticated && (
                <div>
                  <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                    Account
                  </p>
                  <div className="space-y-1">
                    <NavLink
                      to="/settings"
                      className={({ isActive }) =>
                        `flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium transition-all ${
                          isActive
                            ? 'bg-theme-active text-theme-primary'
                            : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                        }`
                      }
                    >
                      <Settings className="w-5 h-5" />
                      <span>Settings</span>
                    </NavLink>
                    <button
                      onClick={handleLogout}
                      className="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium text-red-500 dark:text-red-400 hover:bg-red-500/10 transition-all w-full"
                    >
                      <LogOut className="w-5 h-5" />
                      <span>Log Out</span>
                    </button>
                  </div>
                </div>
              )}

              {/* Auth buttons for guests */}
              {!isAuthenticated && (
                <div className="space-y-2 pt-4">
                  <Divider className="bg-theme-default" style={{ backgroundColor: 'var(--border-default)' }} />
                  <Link to="/login">
                    <Button
                      variant="flat"
                      className="w-full bg-theme-elevated text-theme-secondary"
                    >
                      Log In
                    </Button>
                  </Link>
                  <Link to="/register">
                    <Button className="w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium">
                      Sign Up
                    </Button>
                  </Link>
                </div>
              )}
            </nav>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}

export default MobileDrawer;
