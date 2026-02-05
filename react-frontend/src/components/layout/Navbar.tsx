/**
 * Main Navigation Bar
 * Responsive header with desktop nav and mobile menu trigger
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
} from 'lucide-react';
import { useAuth, useTenant } from '@/contexts';
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
  { label: 'Members', href: '/members', icon: Users, feature: 'connections' as const },
  { label: 'Events', href: '/events', icon: Calendar, feature: 'events' as const },
  { label: 'Groups', href: '/groups', icon: Users, feature: 'groups' as const },
];

export function Navbar({ onMobileMenuOpen }: NavbarProps) {
  const navigate = useNavigate();
  const { user, isAuthenticated, logout } = useAuth();
  const { branding, hasFeature } = useTenant();
  // TODO: Replace with real notification count from NotificationsContext when implemented
  const unreadCount = 0;

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <header className="fixed top-0 left-0 right-0 z-50 bg-white/5 backdrop-blur-xl border-b border-white/10">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Mobile Menu Toggle */}
          <div className="flex items-center gap-4 lg:hidden">
            <Button
              isIconOnly
              variant="light"
              className="text-white/70 hover:text-white"
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
              <Hexagon className="w-8 h-8 text-indigo-400" />
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
                      ? 'bg-white/10 text-white'
                      : 'text-white/70 hover:text-white hover:bg-white/5'
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
                        ? 'bg-white/10 text-white'
                        : 'text-white/70 hover:text-white hover:bg-white/5'
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
                {/* Search Button */}
                <Button
                  isIconOnly
                  variant="light"
                  className="hidden sm:flex text-white/70 hover:text-white"
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
                    className="bg-black/80 backdrop-blur-xl border border-white/10"
                  >
                    <DropdownItem
                      key="listing"
                      startContent={<ListTodo className="w-4 h-4" />}
                      onPress={() => navigate('/listings/create')}
                      className="text-white/80"
                    >
                      New Listing
                    </DropdownItem>
                    {hasFeature('events') ? (
                      <DropdownItem
                        key="event"
                        startContent={<Calendar className="w-4 h-4" />}
                        onPress={() => navigate('/events/create')}
                        className="text-white/80"
                      >
                        New Event
                      </DropdownItem>
                    ) : null}
                  </DropdownMenu>
                </Dropdown>

                {/* Notifications */}
                <Button
                  isIconOnly
                  variant="light"
                  className="relative text-white/70 hover:text-white"
                  onPress={() => navigate('/notifications')}
                  aria-label="Notifications"
                >
                  <Bell className="w-5 h-5" />
                  {unreadCount > 0 && (
                    <span className="absolute -top-1 -right-1 inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold bg-red-500 text-white rounded-full">
                      {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                  )}
                </Button>

                {/* User Dropdown */}
                <Dropdown placement="bottom-end">
                  <DropdownTrigger>
                    <Avatar
                      as="button"
                      name={`${user?.first_name} ${user?.last_name}`}
                      src={resolveAvatarUrl(user?.avatar_url || user?.avatar)}
                      size="sm"
                      className="cursor-pointer ring-2 ring-white/20 hover:ring-indigo-500/50 transition-all"
                      showFallback
                    />
                  </DropdownTrigger>
                  <DropdownMenu
                    aria-label="User actions"
                    className="bg-black/80 backdrop-blur-xl border border-white/10"
                  >
                    <DropdownItem
                      key="profile-header"
                      className="h-14 gap-2"
                      textValue="Profile"
                      isReadOnly
                    >
                      <p className="font-semibold text-white">
                        {user?.first_name} {user?.last_name}
                      </p>
                      <p className="text-sm text-white/50">{user?.email}</p>
                    </DropdownItem>
                    <DropdownItem
                      key="dashboard"
                      startContent={<LayoutDashboard className="w-4 h-4" />}
                      onPress={() => navigate('/dashboard')}
                      className="text-white/80"
                    >
                      Dashboard
                    </DropdownItem>
                    <DropdownItem
                      key="profile"
                      startContent={<Users className="w-4 h-4" />}
                      onPress={() => navigate('/profile')}
                      className="text-white/80"
                    >
                      My Profile
                    </DropdownItem>
                    <DropdownItem
                      key="settings"
                      startContent={<Settings className="w-4 h-4" />}
                      onPress={() => navigate('/settings')}
                      className="text-white/80"
                    >
                      Settings
                    </DropdownItem>
                    <DropdownItem
                      key="logout"
                      color="danger"
                      startContent={<LogOut className="w-4 h-4" />}
                      onPress={handleLogout}
                      className="text-red-400"
                    >
                      Log Out
                    </DropdownItem>
                  </DropdownMenu>
                </Dropdown>
              </>
            ) : (
              <>
                <Link to="/login">
                  <Button variant="light" className="text-white/80 hover:text-white">
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
