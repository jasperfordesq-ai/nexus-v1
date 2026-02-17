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
  Newspaper,
  BookOpen,
  FolderOpen,
  Heart,
  Building2,
  Search,
  Shield,
  Globe,
  Info,
  FileText,
  Handshake,
  Stethoscope,
  TrendingUp,
  BarChart3,
  Compass,
} from 'lucide-react';
import { useAuth, useTenant, useNotifications } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { tokenManager, API_BASE } from '@/lib/api';
import type { TenantFeatures, TenantModules } from '@/types/api';

interface MobileDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  onSearchOpen?: () => void;
}

const mainNavItems = [
  { label: 'Home', href: '/', icon: Home },
  { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, auth: true, module: 'dashboard' as keyof TenantModules },
  { label: 'Feed', href: '/feed', icon: Newspaper, auth: true, module: 'feed' as keyof TenantModules },
  { label: 'Listings', href: '/listings', icon: ListTodo, module: 'listings' as keyof TenantModules },
  { label: 'Messages', href: '/messages', icon: MessageSquare, auth: true, module: 'messages' as keyof TenantModules },
  { label: 'Wallet', href: '/wallet', icon: Wallet, auth: true, module: 'wallet' as keyof TenantModules },
];

const communityNavItems = [
  { label: 'Exchanges', href: '/exchanges', icon: ArrowRightLeft, feature: 'exchange_workflow' as const },
  { label: 'Group Exchanges', href: '/group-exchanges', icon: Users, feature: 'group_exchanges' as keyof TenantFeatures },
  { label: 'Members', href: '/members', icon: Users, feature: 'connections' as const },
  { label: 'Events', href: '/events', icon: Calendar, feature: 'events' as const },
  { label: 'Groups', href: '/groups', icon: Users, feature: 'groups' as const },
  { label: 'Blog', href: '/blog', icon: BookOpen, feature: 'blog' as const },
  { label: 'Volunteering', href: '/volunteering', icon: Heart, feature: 'volunteering' as const },
  { label: 'Organisations', href: '/organisations', icon: Building2, feature: 'volunteering' as const },
  { label: 'Resources', href: '/resources', icon: FolderOpen, feature: 'resources' as const },
];

const exploreNavItems = [
  { label: 'Achievements', href: '/achievements', icon: Trophy, feature: 'gamification' as const },
  { label: 'Leaderboard', href: '/leaderboard', icon: Medal, feature: 'gamification' as const },
  { label: 'Goals', href: '/goals', icon: Target, feature: 'goals' as const },
];

const federationNavItems = [
  { label: 'Federation Hub', href: '/federation', icon: Globe, feature: 'federation' as keyof TenantFeatures },
  { label: 'Partner Communities', href: '/federation/partners', icon: Building2, feature: 'federation' as keyof TenantFeatures },
  { label: 'Federated Members', href: '/federation/members', icon: Users, feature: 'federation' as keyof TenantFeatures },
  { label: 'Federated Messages', href: '/federation/messages', icon: MessageSquare, feature: 'federation' as keyof TenantFeatures },
  { label: 'Federated Listings', href: '/federation/listings', icon: ListTodo, feature: 'federation' as keyof TenantFeatures },
  { label: 'Federated Events', href: '/federation/events', icon: Calendar, feature: 'federation' as keyof TenantFeatures },
];

const aboutNavItems = [
  { label: 'About', href: '/about', icon: Info },
  { label: 'FAQ', href: '/faq', icon: HelpCircle },
  { label: 'Timebanking Guide', href: '/timebanking-guide', icon: BookOpen },
  { label: 'Partner With Us', href: '/partner', icon: Handshake },
  { label: 'Social Prescribing', href: '/social-prescribing', icon: Stethoscope },
  { label: 'Our Impact', href: '/impact-summary', icon: TrendingUp },
  { label: 'Impact Report', href: '/impact-report', icon: BarChart3 },
  { label: 'Strategic Plan', href: '/strategic-plan', icon: Compass },
];

const supportNavItems = [
  { label: 'Help Center', href: '/help', icon: HelpCircle },
  { label: 'Contact', href: '/contact', icon: MessageSquare },
];

export function MobileDrawer({ isOpen, onClose, onSearchOpen }: MobileDrawerProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, isAuthenticated, logout } = useAuth();
  const { tenant, branding, hasFeature, hasModule, tenantPath } = useTenant();
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
    navigate(tenantPath('/login'));
  };

  const renderNavLink = (item: {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    auth?: boolean;
    feature?: keyof TenantFeatures;
    module?: keyof TenantModules;
  }) => {
    // Check feature flag
    if (item.feature && !hasFeature(item.feature)) {
      return null;
    }

    // Check module flag
    if (item.module && !hasModule(item.module)) {
      return null;
    }

    // Check auth requirement
    if (item.auth && !isAuthenticated) {
      return null;
    }

    const Icon = item.icon;
    const resolvedHref = tenantPath(item.href);

    return (
      <NavLink
        key={item.href}
        to={resolvedHref}
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
        base: 'bg-[var(--surface-overlay)] border-l border-[var(--border-default)] shadow-2xl',
        header: 'border-b border-[var(--border-default)] p-4',
        body: 'p-0',
      }}
    >
      <DrawerContent>
        {/* Header */}
        <DrawerHeader className="flex items-center justify-between">
          <Link to={tenantPath('/')} className="flex items-center gap-2">
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
          {/* Search Button */}
          {onSearchOpen && (
            <div className="px-4 pt-3 pb-1">
              <Button
                variant="flat"
                fullWidth
                className="flex items-center justify-start gap-3 px-4 py-2.5 rounded-xl bg-theme-elevated hover:bg-theme-hover border border-theme-default text-sm text-theme-subtle h-auto"
                onPress={() => { onClose(); onSearchOpen(); }}
                aria-label="Open search"
              >
                <Search className="w-4 h-4" aria-hidden="true" />
                <span>Search...</span>
              </Button>
            </div>
          )}

          {/* User Section */}
          {isAuthenticated && user && (
            <div className="p-4 border-b border-[var(--border-default)]">
              <Link
                to={tenantPath('/profile')}
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
                  to={tenantPath('/wallet')}
                  className="text-center p-2 rounded-xl bg-theme-elevated hover:bg-theme-hover transition-colors"
                >
                  <p className="text-lg font-bold text-theme-primary">
                    {user.balance ?? 0}
                  </p>
                  <p className="text-xs text-theme-subtle">Credits</p>
                </Link>
                <Link
                  to={tenantPath('/messages')}
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
                  to={tenantPath('/notifications')}
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
            {communityNavItems.filter(item => !item.feature || hasFeature(item.feature)).length > 0 && (
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

            {/* Federation */}
            {hasFeature('federation') && isAuthenticated && (
              <div>
                <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider flex items-center gap-2">
                  <Globe className="w-3 h-3" aria-hidden="true" />
                  Federation
                </p>
                <div className="space-y-1">
                  {federationNavItems.map(renderNavLink)}
                </div>
              </div>
            )}

            {/* About */}
            <div>
              <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                About
              </p>
              <div className="space-y-1">
                {aboutNavItems.map(renderNavLink)}
                {(tenant?.menu_pages?.about || []).map((p: { title: string; slug: string }) => renderNavLink({
                  label: p.title,
                  href: `/${p.slug}`,
                  icon: FileText,
                }))}
              </div>
            </div>

            {/* Support */}
            <div>
              <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                Support
              </p>
              <div className="space-y-1">
                {supportNavItems.map(renderNavLink)}
              </div>
            </div>

            {/* Admin Tools */}
            {isAuthenticated && user && (user.role === 'admin' || user.role === 'tenant_admin' || user.role === 'super_admin' || user.is_admin || user.is_super_admin) && (
              <div>
                <Divider className="bg-theme-elevated mb-4" />
                <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider flex items-center gap-2">
                  <Shield className="w-3 h-3" aria-hidden="true" />
                  Admin Tools
                </p>
                <div className="space-y-1">
                  <a
                    href="/admin"
                    className="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium text-theme-muted hover:text-theme-primary hover:bg-theme-hover transition-all"
                  >
                    <LayoutDashboard className="w-5 h-5" aria-hidden="true" />
                    <span>Admin Panel</span>
                  </a>
                  <a
                    href="/admin-legacy"
                    onClick={(e) => { e.preventDefault(); const t = tokenManager.getAccessToken(); const apiOrigin = API_BASE.startsWith('http') ? API_BASE.replace(/\/api\/?$/, '') : ''; window.location.href = t ? `${apiOrigin}/api/auth/admin-session?token=${encodeURIComponent(t)}&redirect=/admin-legacy` : `${apiOrigin}/admin-legacy`; }}
                    className="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium text-theme-muted hover:text-theme-primary hover:bg-theme-hover transition-all"
                  >
                    <Shield className="w-5 h-5" aria-hidden="true" />
                    <span>Legacy Admin</span>
                  </a>
                </div>
              </div>
            )}

            {/* Account */}
            {isAuthenticated && (
              <div>
                <p className="px-4 mb-2 text-xs font-semibold text-theme-subtle uppercase tracking-wider">
                  Account
                </p>
                <div className="space-y-1">
                  <NavLink
                    to={tenantPath('/settings')}
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
                  <Button
                    variant="light"
                    onPress={handleLogout}
                    className="flex items-center justify-start gap-3 px-4 py-3 rounded-xl text-base font-medium text-red-500 dark:text-red-400 hover:bg-red-500/10 transition-all w-full h-auto"
                  >
                    <LogOut className="w-5 h-5" aria-hidden="true" />
                    <span>Log Out</span>
                  </Button>
                </div>
              </div>
            )}

            {/* Auth buttons for guests */}
            {!isAuthenticated && (
              <div className="space-y-2 pt-4">
                <Divider className="bg-theme-elevated" />
                <Link to={tenantPath('/login')}>
                  <Button
                    variant="flat"
                    className="w-full bg-theme-elevated text-theme-secondary"
                  >
                    Log In
                  </Button>
                </Link>
                <Link to={tenantPath('/register')}>
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
