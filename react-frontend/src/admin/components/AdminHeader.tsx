// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Header Bar
 * Top bar with breadcrumbs, search, and user menu
 */

import { useNavigate } from 'react-router-dom';
import { useAuth, useTenant } from '@/contexts';
import { Button, Avatar, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem } from '@heroui/react';
import { ArrowLeft, Bell, LogOut, Menu, User } from 'lucide-react';

interface AdminHeaderProps {
  sidebarCollapsed: boolean;
  onSidebarToggle?: () => void;
}

export function AdminHeader({ sidebarCollapsed, onSidebarToggle }: AdminHeaderProps) {
  const { user, logout } = useAuth();
  const { tenantPath, tenant } = useTenant();
  const navigate = useNavigate();

  return (
    <header
      className={`fixed top-0 right-0 z-30 flex h-16 items-center justify-between border-b border-divider bg-content1/95 backdrop-blur px-3 sm:px-6 transition-all duration-300 left-0 ${
        sidebarCollapsed ? 'md:left-16' : 'md:left-64'
      }`}
    >
      {/* Left: Mobile menu + Back to frontend + tenant name */}
      <div className="flex items-center gap-2 sm:gap-4">
        {/* Mobile hamburger */}
        {onSidebarToggle && (
          <Button
            isIconOnly
            variant="light"
            size="sm"
            onPress={onSidebarToggle}
            className="text-default-500 md:hidden"
            aria-label="Toggle sidebar"
          >
            <Menu size={20} />
          </Button>
        )}
        <Button
          variant="light"
          size="sm"
          onPress={() => navigate(tenantPath('/dashboard'))}
          startContent={<ArrowLeft size={16} />}
          className="text-default-500"
        >
          <span className="hidden sm:inline">Back to site</span>
        </Button>
        {tenant?.name && (
          <span className="text-sm font-medium text-default-400">
            {tenant.name}
          </span>
        )}
      </div>

      {/* Right: User menu */}
      <div className="flex items-center gap-3">
        <Button
          isIconOnly
          variant="light"
          size="sm"
          onPress={() => navigate(tenantPath('/notifications'))}
          aria-label="Notifications"
        >
          <Bell size={18} />
        </Button>

        <Dropdown placement="bottom-end">
          <DropdownTrigger>
            <Button variant="light" className="flex items-center gap-2 px-2 py-1 h-auto min-w-0">
              <Avatar
                src={user?.avatar_url || user?.avatar || undefined}
                name={user?.name || 'Admin'}
                size="sm"
                className="h-8 w-8"
              />
              <span className="hidden text-sm font-medium text-foreground sm:block">
                {user?.name || 'Admin'}
              </span>
            </Button>
          </DropdownTrigger>
          <DropdownMenu
            aria-label="Admin menu"
            onAction={(key) => {
              if (key === 'profile') navigate(tenantPath('/profile'));
              if (key === 'logout') logout();
            }}
          >
            <DropdownItem
              key="profile"
              startContent={<User size={16} />}
            >
              My Profile
            </DropdownItem>
            <DropdownItem
              key="logout"
              startContent={<LogOut size={16} />}
              className="text-danger"
              color="danger"
            >
              Sign Out
            </DropdownItem>
          </DropdownMenu>
        </Dropdown>
      </div>
    </header>
  );
}

export default AdminHeader;
