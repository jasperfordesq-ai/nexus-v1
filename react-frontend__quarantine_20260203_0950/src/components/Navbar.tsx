/**
 * Navbar - Top navigation with tenant branding
 */

import { Link, useNavigate } from 'react-router-dom';
import {
  Navbar as HeroNavbar,
  NavbarBrand,
  NavbarContent,
  NavbarItem,
  Button,
  Avatar,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import { useTenant, useFeature } from '../tenant';
import { useAuth } from '../auth';

export function Navbar() {
  const tenant = useTenant();
  const { user, isAuthenticated, logout, isLoading } = useAuth();
  const navigate = useNavigate();

  // Feature flags
  const hasListings = useFeature('listings');
  const hasEvents = useFeature('events');
  const hasGroups = useFeature('groups');

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };

  return (
    <HeroNavbar isBordered maxWidth="xl">
      <NavbarBrand>
        <Link to="/" className="flex items-center gap-2">
          {tenant.branding.logo_url ? (
            <img
              src={tenant.branding.logo_url}
              alt={tenant.name}
              className="h-8 w-auto"
            />
          ) : (
            <span className="font-bold text-inherit">{tenant.name}</span>
          )}
        </Link>
      </NavbarBrand>

      <NavbarContent className="hidden sm:flex gap-4" justify="center">
        {hasListings && (
          <NavbarItem>
            <Link
              to="/listings"
              className="text-foreground hover:text-primary transition-colors"
            >
              Listings
            </Link>
          </NavbarItem>
        )}
        {hasEvents && (
          <NavbarItem>
            <Link
              to="/events"
              className="text-foreground hover:text-primary transition-colors"
            >
              Events
            </Link>
          </NavbarItem>
        )}
        {hasGroups && (
          <NavbarItem>
            <Link
              to="/groups"
              className="text-foreground hover:text-primary transition-colors"
            >
              Groups
            </Link>
          </NavbarItem>
        )}
      </NavbarContent>

      <NavbarContent justify="end">
        {isAuthenticated && user ? (
          <Dropdown placement="bottom-end">
            <DropdownTrigger>
              <Avatar
                as="button"
                className="transition-transform"
                color="primary"
                name={`${user.first_name} ${user.last_name}`}
                size="sm"
                src={user.avatar_url || undefined}
              />
            </DropdownTrigger>
            <DropdownMenu aria-label="User menu">
              <DropdownItem key="profile" className="h-14 gap-2">
                <p className="font-semibold">Signed in as</p>
                <p className="font-semibold">{user.email}</p>
              </DropdownItem>
              <DropdownItem key="logout" color="danger" onPress={handleLogout}>
                Log Out
              </DropdownItem>
            </DropdownMenu>
          </Dropdown>
        ) : (
          <NavbarItem>
            <Button
              as={Link}
              color="primary"
              to="/login"
              variant="flat"
              isLoading={isLoading}
            >
              Login
            </Button>
          </NavbarItem>
        )}
      </NavbarContent>
    </HeroNavbar>
  );
}
