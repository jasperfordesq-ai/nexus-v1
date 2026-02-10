/**
 * Main Navigation Bar
 * Responsive header with desktop nav and mobile menu trigger
 * Theme-aware styling for light and dark modes
 */

import { Link, NavLink, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import {
  Hexagon,
  LayoutDashboard,
  ListTodo,
  MessageSquare,
  Wallet,
  Users,
  Calendar,
  Bell,
  Settings,
  LogOut,
  Menu,
  Search,
  Plus,
  Sun,
  Moon,
  ArrowRightLeft,
} from 'lucide-react';
import { useAuth, useTenant, useNotifications, useTheme } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';

interface NavbarProps {
  onMobileMenuOpen?: () => void;
}

const navItems = [
  { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  { label: 'Listings', href: '/listings', icon: ListTodo },
  { label: 'Messages', href: '/messages', icon: MessageSquare, badge: true },
  { label: 'Wallet', href: '/wallet', icon: Wallet },
];

const featureNavItems = [
  { label: 'Exchanges', href: '/exchanges', icon: ArrowRightLeft, feature: 'exchange_workflow' as const },
  { label: 'Members', href: '/members', icon: Users, feature: 'connections' as const },
  { label: 'Events', href: '/events', icon: Calendar, feature: 'events' as const },
  { label: 'Groups', href: '/groups', icon: Users, feature: 'groups' as const },
];

export function Navbar({ onMobileMenuOpen }: NavbarProps) {
  const navigate = useNavigate();
  const { user, isAuthenticated, logout } = useAuth();
  const { branding, hasFeature } = useTenant();
  const { unreadCount } = useNotifications();
  const { resolvedTheme, toggleTheme } = useTheme();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <header className="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl border-b border-theme-default glass-surface">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Mobile Menu Toggle */}
          <div className="flex items-center gap-4 lg:hidden">
            <Button
              isIconOnly
              variant="light"
              className="text-theme-muted hover:text-theme-primary"
              onPress={onMobileMenuOpen}
              aria-label="Open menu"
            >
              <Menu className="w-6 h-6" />
            </Button>
          </div>

          {/* Brand */}
          <Link to="/" className="flex items-center gap-2">
            <motion.div
              whileHover={{ rotate: 180 }}
              transition={{ duration: 0.5 }}
            >
              <Hexagon className="w-8 h-8 text-indigo-500 dark:text-indigo-400" />
            </motion.div>
            <span className="font-bold text-xl text-gradient hidden sm:inline">
              {branding.name}
            </span>
          </Link>

          {/* Desktop Navigation */}
          <nav className="hidden lg:flex items-center gap-1">
            {navItems.map((item) => (
              <NavLink
                key={item.href}
                to={item.href}
                className={({ isActive }) =>
                  `flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all ${
                    isActive
                      ? 'bg-theme-active text-theme-primary'
                      : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                  }`
                }
              >
                <item.icon className="w-4 h-4" />
                <span>{item.label}</span>
                {item.badge && unreadCount > 0 && isAuthenticated && (
                  <span className="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold bg-red-500 text-white rounded-full">
                    {unreadCount > 99 ? '99+' : unreadCount}
                  </span>
                )}
              </NavLink>
            ))}

            {/* Feature-gated nav items */}
            {featureNavItems.map((item) =>
              hasFeature(item.feature) ? (
                <NavLink
                  key={item.href}
                  to={item.href}
                  className={({ isActive }) =>
                    `flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all ${
                      isActive
                        ? 'bg-theme-active text-theme-primary'
                        : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                    }`
                  }
                >
                  <item.icon className="w-4 h-4" />
                  <span>{item.label}</span>
                </NavLink>
              ) : null
            )}
          </nav>

          {/* User Actions */}
          <div className="flex items-center gap-2">
            {isAuthenticated ? (
              <>
                {/* Theme Toggle */}
                <Button
                  isIconOnly
                  variant="light"
                  className="hidden sm:flex text-theme-muted hover:text-theme-primary transition-colors"
                  onPress={toggleTheme}
                  aria-label={`Switch to ${resolvedTheme === 'dark' ? 'light' : 'dark'} mode`}
                >
                  {resolvedTheme === 'dark' ? (
                    <Sun className="w-5 h-5 text-amber-400" />
                  ) : (
                    <Moon className="w-5 h-5 text-indigo-500" />
                  )}
                </Button>

                {/* Search Button */}
                <Button
                  isIconOnly
                  variant="light"
                  className="hidden sm:flex text-theme-muted hover:text-theme-primary"
                  onPress={() => navigate('/search')}
                  aria-label="Search"
                >
                  <Search className="w-5 h-5" />
                </Button>

                {/* Create Button */}
                <Dropdown placement="bottom-end">
                  <DropdownTrigger>
                    <Button
                      isIconOnly
                      className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                      aria-label="Create"
                    >
                      <Plus className="w-5 h-5" />
                    </Button>
                  </DropdownTrigger>
                  <DropdownMenu
                    aria-label="Create actions"
                    className="glass-surface-strong"
                  >
                    <DropdownItem
                      key="listing"
                      startContent={<ListTodo className="w-4 h-4" />}
                      onPress={() => navigate('/listings/create')}
                      className="text-theme-secondary"
                    >
                      New Listing
                    </DropdownItem>
                    {hasFeature('events') ? (
                      <DropdownItem
                        key="event"
                        startContent={<Calendar className="w-4 h-4" />}
                        onPress={() => navigate('/events/create')}
                        className="text-theme-secondary"
                      >
                        New Event
                      </DropdownItem>
                    ) : null}
                  </DropdownMenu>
                </Dropdown>

                {/* Notifications */}
                <div className="relative">
                  <Button
                    isIconOnly
                    variant="light"
                    className={`text-theme-muted hover:text-theme-primary ${unreadCount > 0 ? 'text-indigo-500 dark:text-indigo-400' : ''}`}
                    onPress={() => navigate('/notifications')}
                    aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
                  >
                    <Bell className="w-5 h-5" />
                  </Button>
                  {unreadCount > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold bg-red-500 text-white rounded-full pointer-events-none animate-pulse">
                      {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                  )}
                </div>

                {/* User Dropdown */}
                <Dropdown placement="bottom-end">
                  <DropdownTrigger>
                    <Avatar
                      as="button"
                      name={`${user?.first_name} ${user?.last_name}`}
                      src={resolveAvatarUrl(user?.avatar_url || user?.avatar)}
                      size="sm"
                      className="cursor-pointer ring-2 ring-border-default hover:ring-indigo-500/50 transition-all"
                      showFallback
                    />
                  </DropdownTrigger>
                  <DropdownMenu
                    aria-label="User actions"
                    className="glass-surface-strong"
                  >
                    <DropdownItem
                      key="profile-header"
                      className="h-14 gap-2"
                      textValue="Profile"
                      isReadOnly
                    >
                      <p className="font-semibold text-theme-primary">
                        {user?.first_name} {user?.last_name}
                      </p>
                      <p className="text-sm text-theme-subtle">{user?.email}</p>
                    </DropdownItem>
                    <DropdownItem
                      key="dashboard"
                      startContent={<LayoutDashboard className="w-4 h-4" />}
                      onPress={() => navigate('/dashboard')}
                      className="text-theme-secondary"
                    >
                      Dashboard
                    </DropdownItem>
                    <DropdownItem
                      key="profile"
                      startContent={<Users className="w-4 h-4" />}
                      onPress={() => navigate('/profile')}
                      className="text-theme-secondary"
                    >
                      My Profile
                    </DropdownItem>
                    <DropdownItem
                      key="settings"
                      startContent={<Settings className="w-4 h-4" />}
                      onPress={() => navigate('/settings')}
                      className="text-theme-secondary"
                    >
                      Settings
                    </DropdownItem>
                    <DropdownItem
                      key="logout"
                      color="danger"
                      startContent={<LogOut className="w-4 h-4" />}
                      onPress={handleLogout}
                      className="text-red-500 dark:text-red-400"
                    >
                      Log Out
                    </DropdownItem>
                  </DropdownMenu>
                </Dropdown>
              </>
            ) : (
              <>
                <Link to="/login">
                  <Button variant="light" className="text-theme-secondary hover:text-theme-primary">
                    Log In
                  </Button>
                </Link>
                <Link to="/register">
                  <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium">
                    Sign Up
                  </Button>
                </Link>
              </>
            )}
          </div>
        </div>
      </div>
    </header>
  );
}

export default Navbar;
