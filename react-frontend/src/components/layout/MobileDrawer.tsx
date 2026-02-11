/**
 * Mobile Navigation Drawer
 * Uses HeroUI Drawer component for accessibility and animations
 * Theme-aware styling for light and dark modes
 */

import { useEffect, useRef } from 'react';
import { Link, NavLink, useNavigate, useLocation } from 'react-router-dom';
import {
  Button,
  Avatar,
  Divider,
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerBody,
} from '@heroui/react';
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
  ArrowRightLeft,
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
  { label: 'Exchanges', href: '/exchanges', icon: ArrowRightLeft, feature: 'exchange_workflow' as const },
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
        <Icon className="w-5 h-5" aria-hidden="true" />
        <span>{item.label}</span>
      </NavLink>
    );
  };

  return (
    <Drawer
      isOpen={isOpen}
      onClose={onClose}
      placement="right"
      size="sm"
      hideCloseButton
      classNames={{
        base: 'bg-theme-card border-l border-theme-default',
        header: 'border-b border-theme-default p-4',
        body: 'p-0',
      }}
    >
      <DrawerContent>
        {/* Header */}
        <DrawerHeader className="flex items-center justify-between">
          <Link to="/" className="flex items-center gap-2">
            <Hexagon className="w-8 h-8 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
            <span className="font-bold text-xl text-gradient">{branding.name}</span>
          </Link>
          <Button
            isIconOnly
            variant="light"
            className="text-theme-muted hover:text-theme-primary"
            onPress={onClose}
            aria-label="Close menu"
          >
            <X className="w-6 h-6" aria-hidden="true" />
          </Button>
        </DrawerHeader>

        <DrawerBody>
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
                    <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" aria-hidden="true" />
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
                    <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" aria-hidden="true" />
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
            {(hasFeature('connections') || hasFeature('events') || hasFeature('groups') || hasFeature('exchange_workflow')) && (
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
                    <Settings className="w-5 h-5" aria-hidden="true" />
                    <span>Settings</span>
                  </NavLink>
                  <button
                    onClick={handleLogout}
                    className="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium text-red-500 dark:text-red-400 hover:bg-red-500/10 transition-all w-full"
                  >
                    <LogOut className="w-5 h-5" aria-hidden="true" />
                    <span>Log Out</span>
                  </button>
                </div>
              </div>
            )}

            {/* Auth buttons for guests */}
            {!isAuthenticated && (
              <div className="space-y-2 pt-4">
                <Divider className="bg-theme-default" />
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
        </DrawerBody>
      </DrawerContent>
    </Drawer>
  );
}

export default MobileDrawer;
