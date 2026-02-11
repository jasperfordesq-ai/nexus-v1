/**
 * Main Navigation Bar
 * Responsive header with desktop nav and mobile menu trigger
 * Desktop uses grouped dropdowns for cleaner layout
 * Theme-aware styling for light and dark modes
 */

import { Link, NavLink, useNavigate, useLocation } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  Badge,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  DropdownSection,
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
  ChevronDown,
  Trophy,
  Target,
  HelpCircle,
  UserCircle,
} from 'lucide-react';
import { useAuth, useTenant, useNotifications, useTheme } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';

interface NavbarProps {
  onMobileMenuOpen?: () => void;
}

export function Navbar({ onMobileMenuOpen }: NavbarProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, isAuthenticated, logout } = useAuth();
  const { branding, hasFeature } = useTenant();
  const { unreadCount, counts } = useNotifications();
  const { resolvedTheme, toggleTheme } = useTheme();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  // Check if current path matches any in a group
  const isActiveGroup = (paths: string[]) => {
    return paths.some(path => location.pathname.startsWith(path));
  };

  // Community dropdown items
  const communityItems = [
    { label: 'Members', href: '/members', icon: Users, feature: 'connections' as const },
    { label: 'Events', href: '/events', icon: Calendar, feature: 'events' as const },
    { label: 'Groups', href: '/groups', icon: Users, feature: 'groups' as const },
  ].filter(item => hasFeature(item.feature));

  // Activity dropdown items
  const activityItems = [
    { label: 'Exchanges', href: '/exchanges', icon: ArrowRightLeft, feature: 'exchange_workflow' as const },
    { label: 'Wallet', href: '/wallet', icon: Wallet },
    { label: 'Achievements', href: '/achievements', icon: Trophy, feature: 'gamification' as const },
    { label: 'Goals', href: '/goals', icon: Target, feature: 'goals' as const },
  ].filter(item => !item.feature || hasFeature(item.feature));

  const communityPaths = communityItems.map(i => i.href);
  const activityPaths = activityItems.map(i => i.href);

  return (
    <header className="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl border-b border-theme-default glass-surface">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-14 sm:h-16">
          {/* Left Section: Mobile Menu + Brand */}
          <div className="flex items-center gap-2 sm:gap-3">
            {/* Mobile Menu Toggle */}
            <Button
              isIconOnly
              variant="light"
              size="sm"
              className="lg:hidden text-theme-muted hover:text-theme-primary"
              onPress={onMobileMenuOpen}
              aria-label="Open menu"
            >
              <Menu className="w-5 h-5" aria-hidden="true" />
            </Button>

            {/* Brand */}
            <Link to="/" className="flex items-center gap-2">
              <motion.div
                whileHover={{ rotate: 180 }}
                transition={{ duration: 0.5 }}
              >
                <Hexagon className="w-7 h-7 sm:w-8 sm:h-8 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
              </motion.div>
              <span className="font-bold text-lg sm:text-xl text-gradient hidden min-[480px]:inline">
                {branding.name}
              </span>
            </Link>
          </div>

          {/* Desktop Navigation - Reorganized with Dropdowns */}
          <nav className="hidden lg:flex items-center gap-1">
            {/* Dashboard - Direct Link */}
            <NavLink
              to="/dashboard"
              className={({ isActive }) =>
                `flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                  isActive
                    ? 'bg-theme-active text-theme-primary'
                    : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                }`
              }
            >
              <LayoutDashboard className="w-4 h-4" aria-hidden="true" />
              <span>Dashboard</span>
            </NavLink>

            {/* Listings - Direct Link */}
            <NavLink
              to="/listings"
              className={({ isActive }) =>
                `flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                  isActive
                    ? 'bg-theme-active text-theme-primary'
                    : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                }`
              }
            >
              <ListTodo className="w-4 h-4" aria-hidden="true" />
              <span>Listings</span>
            </NavLink>

            {/* Messages - Direct Link with Badge */}
            <NavLink
              to="/messages"
              className={({ isActive }) =>
                `flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                  isActive
                    ? 'bg-theme-active text-theme-primary'
                    : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                }`
              }
            >
              <MessageSquare className="w-4 h-4" aria-hidden="true" />
              <span>Messages</span>
              {counts.messages > 0 && isAuthenticated && (
                <span className="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold bg-red-500 text-white rounded-full">
                  {counts.messages > 99 ? '99+' : counts.messages}
                </span>
              )}
            </NavLink>

            {/* Community Dropdown */}
            {communityItems.length > 0 && (
              <Dropdown placement="bottom-start">
                <DropdownTrigger>
                  <Button
                    variant="light"
                    size="sm"
                    className={`flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all ${
                      isActiveGroup(communityPaths)
                        ? 'bg-theme-active text-theme-primary'
                        : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                    }`}
                    endContent={<ChevronDown className="w-3 h-3" aria-hidden="true" />}
                  >
                    <Users className="w-4 h-4" aria-hidden="true" />
                    Community
                  </Button>
                </DropdownTrigger>
                <DropdownMenu
                  aria-label="Community navigation"
                  className="min-w-[180px]"
                  classNames={{
                    base: 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-xl',
                  }}
                >
                  {communityItems.map((item) => (
                    <DropdownItem
                      key={item.href}
                      startContent={<item.icon className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => navigate(item.href)}
                      className={location.pathname.startsWith(item.href) ? 'bg-theme-active' : ''}
                    >
                      {item.label}
                    </DropdownItem>
                  ))}
                </DropdownMenu>
              </Dropdown>
            )}

            {/* Activity Dropdown */}
            {activityItems.length > 0 && (
              <Dropdown placement="bottom-start">
                <DropdownTrigger>
                  <Button
                    variant="light"
                    size="sm"
                    className={`flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all ${
                      isActiveGroup(activityPaths)
                        ? 'bg-theme-active text-theme-primary'
                        : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                    }`}
                    endContent={<ChevronDown className="w-3 h-3" aria-hidden="true" />}
                  >
                    <ArrowRightLeft className="w-4 h-4" aria-hidden="true" />
                    Activity
                  </Button>
                </DropdownTrigger>
                <DropdownMenu
                  aria-label="Activity navigation"
                  className="min-w-[180px]"
                  classNames={{
                    base: 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-xl',
                  }}
                >
                  {activityItems.map((item) => (
                    <DropdownItem
                      key={item.href}
                      startContent={<item.icon className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => navigate(item.href)}
                      className={location.pathname.startsWith(item.href) ? 'bg-theme-active' : ''}
                    >
                      {item.label}
                    </DropdownItem>
                  ))}
                </DropdownMenu>
              </Dropdown>
            )}
          </nav>

          {/* User Actions */}
          <div className="flex items-center gap-1 sm:gap-2">
            {isAuthenticated ? (
              <>
                {/* Search Button - Desktop only */}
                <Button
                  isIconOnly
                  variant="light"
                  size="sm"
                  className="hidden md:flex text-theme-muted hover:text-theme-primary"
                  onPress={() => navigate('/search')}
                  aria-label="Search"
                >
                  <Search className="w-5 h-5" aria-hidden="true" />
                </Button>

                {/* Create Button - Desktop only */}
                <Dropdown placement="bottom-end">
                  <DropdownTrigger>
                    <Button
                      isIconOnly
                      size="sm"
                      className="hidden sm:flex bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                      aria-label="Create new"
                    >
                      <Plus className="w-4 h-4" aria-hidden="true" />
                    </Button>
                  </DropdownTrigger>
                  <DropdownMenu
                    aria-label="Create actions"
                    classNames={{
                      base: 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-xl',
                    }}
                  >
                    <DropdownItem
                      key="listing"
                      startContent={<ListTodo className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => navigate('/listings/create')}
                    >
                      New Listing
                    </DropdownItem>
                    {hasFeature('events') ? (
                      <DropdownItem
                        key="event"
                        startContent={<Calendar className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => navigate('/events/create')}
                      >
                        New Event
                      </DropdownItem>
                    ) : null}
                  </DropdownMenu>
                </Dropdown>

                {/* Notifications */}
                <Badge
                  content={unreadCount > 99 ? '99+' : unreadCount}
                  color="danger"
                  size="sm"
                  isInvisible={unreadCount === 0}
                  placement="top-right"
                >
                  <Button
                    isIconOnly
                    variant="light"
                    size="sm"
                    className={`text-theme-muted hover:text-theme-primary ${unreadCount > 0 ? 'text-indigo-500 dark:text-indigo-400' : ''}`}
                    onPress={() => navigate('/notifications')}
                    aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
                  >
                    <Bell className="w-4 h-4 sm:w-5 sm:h-5" aria-hidden="true" />
                  </Button>
                </Badge>

                {/* User Dropdown - Enhanced */}
                <Dropdown placement="bottom-end">
                  <DropdownTrigger>
                    <Avatar
                      as="button"
                      name={`${user?.first_name} ${user?.last_name}`}
                      src={resolveAvatarUrl(user?.avatar_url || user?.avatar)}
                      size="sm"
                      className="cursor-pointer ring-2 ring-transparent hover:ring-indigo-500/50 transition-all w-8 h-8 sm:w-9 sm:h-9"
                      showFallback
                    />
                  </DropdownTrigger>
                  <DropdownMenu
                    aria-label="User actions"
                    classNames={{
                      base: 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-xl min-w-[220px]',
                    }}
                  >
                    <DropdownSection showDivider>
                      <DropdownItem
                        key="profile-header"
                        className="h-14 gap-2 cursor-default"
                        textValue="Profile"
                        isReadOnly
                      >
                        <p className="font-semibold text-theme-primary">
                          {user?.first_name} {user?.last_name}
                        </p>
                        <p className="text-sm text-theme-subtle">{user?.email}</p>
                      </DropdownItem>
                    </DropdownSection>

                    <DropdownSection showDivider>
                      <DropdownItem
                        key="profile"
                        startContent={<UserCircle className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => navigate('/profile')}
                      >
                        My Profile
                      </DropdownItem>
                      <DropdownItem
                        key="wallet"
                        startContent={<Wallet className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => navigate('/wallet')}
                        endContent={
                          <span className="text-xs text-theme-subtle">
                            {user?.balance ?? 0}h
                          </span>
                        }
                      >
                        Wallet
                      </DropdownItem>
                      <DropdownItem
                        key="settings"
                        startContent={<Settings className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => navigate('/settings')}
                      >
                        Settings
                      </DropdownItem>
                    </DropdownSection>

                    <DropdownSection showDivider>
                      <DropdownItem
                        key="theme"
                        startContent={
                          resolvedTheme === 'dark' ? (
                            <Sun className="w-4 h-4 text-amber-400" aria-hidden="true" />
                          ) : (
                            <Moon className="w-4 h-4 text-indigo-500" aria-hidden="true" />
                          )
                        }
                        onPress={toggleTheme}
                      >
                        {resolvedTheme === 'dark' ? 'Light Mode' : 'Dark Mode'}
                      </DropdownItem>
                      <DropdownItem
                        key="help"
                        startContent={<HelpCircle className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => navigate('/help')}
                      >
                        Help Center
                      </DropdownItem>
                    </DropdownSection>

                    <DropdownSection>
                      <DropdownItem
                        key="logout"
                        color="danger"
                        startContent={<LogOut className="w-4 h-4" aria-hidden="true" />}
                        onPress={handleLogout}
                        className="text-red-500 dark:text-red-400"
                      >
                        Log Out
                      </DropdownItem>
                    </DropdownSection>
                  </DropdownMenu>
                </Dropdown>
              </>
            ) : (
              <>
                <Link to="/login" className="hidden sm:block">
                  <Button variant="light" size="sm" className="text-theme-secondary hover:text-theme-primary">
                    Log In
                  </Button>
                </Link>
                <Link to="/register">
                  <Button size="sm" className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium">
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
