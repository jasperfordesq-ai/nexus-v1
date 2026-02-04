/**
 * Header - NEXUS Glass Navigation Bar
 *
 * Visual Identity:
 * - Glassmorphism with strong backdrop blur
 * - Tenant-branded active states and accents
 * - Floating effect with subtle shadow
 * - Gradient accent on brand hover
 *
 * Structure:
 * - Brand logo/name on left
 * - Primary navigation in center (desktop)
 * - User menu or login button on right
 * - Hamburger menu for mobile
 */

import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import {
  Navbar,
  NavbarBrand,
  NavbarContent,
  NavbarItem,
  NavbarMenuToggle,
  NavbarMenu,
  NavbarMenuItem,
  Button,
  Avatar,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  DropdownSection,
} from '@heroui/react';
import { useTenant, useFeature } from '../../tenant';
import { useAuth } from '../../auth';

interface NavLink {
  label: string;
  href: string;
  requiresAuth?: boolean;
}

export function Header() {
  const tenant = useTenant();
  const { user, isAuthenticated, logout, isLoading } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  // Feature flags
  const hasListings = useFeature('listings');
  const hasMessages = useFeature('messages');
  const hasWallet = useFeature('wallet');

  // Simplified nav for visual shell - placeholder items per task spec
  const navLinks: NavLink[] = [
    { label: 'Home', href: '/' },
    ...(hasListings ? [{ label: 'Listings', href: '/listings' }] : []),
    ...(hasMessages ? [{ label: 'Messages', href: '/messages', requiresAuth: true }] : []),
    ...(hasWallet ? [{ label: 'Wallet', href: '/wallet', requiresAuth: true }] : []),
  ];

  // Filter nav links based on auth
  const visibleNavLinks = navLinks.filter(
    link => !link.requiresAuth || isAuthenticated
  );

  const handleLogout = async () => {
    await logout();
    navigate('/login');
    setIsMenuOpen(false);
  };

  const isActive = (href: string) => {
    if (href === '/') {
      return location.pathname === '/';
    }
    return location.pathname.startsWith(href);
  };

  const handleMenuItemClick = () => {
    setIsMenuOpen(false);
  };

  return (
    <Navbar
      isMenuOpen={isMenuOpen}
      onMenuOpenChange={setIsMenuOpen}
      maxWidth="xl"
      height="4rem"
      classNames={{
        base: 'glass-strong border-b border-white/20',
        wrapper: 'px-4 sm:px-6',
      }}
    >
      {/* Mobile menu toggle */}
      <NavbarContent className="sm:hidden" justify="start">
        <NavbarMenuToggle
          aria-label={isMenuOpen ? 'Close menu' : 'Open menu'}
          className="text-foreground"
        />
      </NavbarContent>

      {/* Brand */}
      <NavbarBrand>
        <Link
          to="/"
          className="flex items-center gap-3 group"
          onClick={handleMenuItemClick}
        >
          {tenant.branding.logo_url ? (
            <img
              src={tenant.branding.logo_url}
              alt={tenant.name}
              className="h-9 w-auto transition-transform group-hover:scale-105"
            />
          ) : (
            <span className="font-bold text-xl bg-gradient-to-r from-[var(--tenant-primary)] to-[var(--tenant-secondary)] bg-clip-text text-transparent">
              {tenant.name}
            </span>
          )}
        </Link>
      </NavbarBrand>

      {/* Desktop Primary Navigation */}
      <NavbarContent className="hidden sm:flex gap-1" justify="center">
        {visibleNavLinks.map((link) => (
          <NavbarItem key={link.href}>
            <Link
              to={link.href}
              className={`
                relative px-4 py-2 text-sm font-medium rounded-full transition-all duration-200
                ${isActive(link.href)
                  ? 'text-white glass-primary'
                  : 'text-foreground/80 hover:text-foreground hover:bg-white/30'
                }
              `}
            >
              {link.label}
              {isActive(link.href) && (
                <span className="absolute bottom-0 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-[var(--tenant-primary)]" />
              )}
            </Link>
          </NavbarItem>
        ))}
      </NavbarContent>

      {/* User Menu / Login */}
      <NavbarContent justify="end">
        {isAuthenticated && user ? (
          <Dropdown placement="bottom-end">
            <DropdownTrigger>
              <Avatar
                as="button"
                className="transition-transform ring-2 ring-white/30 hover:ring-[var(--tenant-primary)]/50"
                name={`${user.first_name} ${user.last_name}`}
                size="sm"
                src={user.avatar_url || undefined}
                isBordered
                color="primary"
              />
            </DropdownTrigger>
            <DropdownMenu
              aria-label="User menu"
              variant="flat"
              className="glass-strong"
            >
              <DropdownSection showDivider>
                <DropdownItem key="profile-info" className="h-14 gap-2" isReadOnly>
                  <p className="font-semibold">{user.first_name} {user.last_name}</p>
                  <p className="text-sm text-default-500">{user.email}</p>
                </DropdownItem>
              </DropdownSection>

              <DropdownSection showDivider items={[]}>
                <DropdownItem key="dashboard" onPress={() => navigate('/dashboard')}>
                  Dashboard
                </DropdownItem>
                <DropdownItem key="messages" onPress={() => navigate('/messages')} className={hasMessages ? '' : 'hidden'}>
                  Messages
                </DropdownItem>
                <DropdownItem key="wallet" onPress={() => navigate('/wallet')} className={hasWallet ? '' : 'hidden'}>
                  Wallet
                </DropdownItem>
                <DropdownItem key="profile" onPress={() => navigate('/profile')}>
                  Profile
                </DropdownItem>
                <DropdownItem key="settings" onPress={() => navigate('/settings')}>
                  Settings
                </DropdownItem>
              </DropdownSection>

              <DropdownSection>
                <DropdownItem
                  key="logout"
                  color="danger"
                  onPress={handleLogout}
                >
                  Log Out
                </DropdownItem>
              </DropdownSection>
            </DropdownMenu>
          </Dropdown>
        ) : (
          <NavbarItem>
            <Button
              as={Link}
              to="/login"
              size="sm"
              isLoading={isLoading}
              className="gradient-primary text-white font-medium shadow-medium hover:shadow-elevated transition-shadow"
            >
              Sign In
            </Button>
          </NavbarItem>
        )}
      </NavbarContent>

      {/* Mobile Menu */}
      <NavbarMenu className="glass-strong pt-6">
        {visibleNavLinks.map((link) => (
          <NavbarMenuItem key={link.href}>
            <Link
              to={link.href}
              className={`
                block w-full py-3 px-4 text-lg rounded-xl transition-all
                ${isActive(link.href)
                  ? 'glass-primary text-[var(--tenant-primary)] font-semibold'
                  : 'text-foreground hover:bg-white/30'
                }
              `}
              onClick={handleMenuItemClick}
            >
              {link.label}
            </Link>
          </NavbarMenuItem>
        ))}

        {/* Auth actions in mobile menu */}
        {isAuthenticated ? (
          <>
            <NavbarMenuItem className="mt-4 pt-4 border-t border-white/20">
              <span className="text-sm text-default-500 font-semibold px-4">Account</span>
            </NavbarMenuItem>
            <NavbarMenuItem>
              <Link
                to="/profile"
                className="block w-full py-3 px-4 text-lg text-foreground hover:bg-white/30 rounded-xl"
                onClick={handleMenuItemClick}
              >
                Profile
              </Link>
            </NavbarMenuItem>
            <NavbarMenuItem>
              <Link
                to="/settings"
                className="block w-full py-3 px-4 text-lg text-foreground hover:bg-white/30 rounded-xl"
                onClick={handleMenuItemClick}
              >
                Settings
              </Link>
            </NavbarMenuItem>
            <NavbarMenuItem>
              <button
                onClick={handleLogout}
                className="w-full py-3 px-4 text-lg text-danger text-left hover:bg-danger/10 rounded-xl transition-colors"
              >
                Log Out
              </button>
            </NavbarMenuItem>
          </>
        ) : (
          <NavbarMenuItem className="mt-4 pt-4 border-t border-white/20">
            <Link
              to="/login"
              className="block w-full py-3 px-4 text-lg gradient-primary text-white text-center font-semibold rounded-xl"
              onClick={handleMenuItemClick}
            >
              Sign In
            </Link>
          </NavbarMenuItem>
        )}
      </NavbarMenu>
    </Navbar>
  );
}
