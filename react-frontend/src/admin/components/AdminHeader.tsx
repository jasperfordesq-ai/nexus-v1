/**
 * Admin Header Bar
 * Top bar with breadcrumbs, search, and user menu
 */

import { useNavigate } from 'react-router-dom';
import { useAuth, useTenant } from '@/contexts';
import { Button, Avatar, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem } from '@heroui/react';
import { ArrowLeft, Bell, LogOut, User } from 'lucide-react';

interface AdminHeaderProps {
  sidebarCollapsed: boolean;
}

export function AdminHeader({ sidebarCollapsed }: AdminHeaderProps) {
  const { user, logout } = useAuth();
  const { tenantPath, tenant } = useTenant();
  const navigate = useNavigate();

  return (
    <header
      className={`fixed top-0 right-0 z-30 flex h-16 items-center justify-between border-b border-divider bg-content1/95 backdrop-blur px-6 transition-all duration-300 ${
        sidebarCollapsed ? 'left-16' : 'left-64'
      }`}
    >
      {/* Left: Back to frontend + tenant name */}
      <div className="flex items-center gap-4">
        <button
          onClick={() => navigate(tenantPath('/dashboard'))}
          className="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-default-500 hover:bg-default-100 hover:text-foreground transition-colors"
        >
          <ArrowLeft size={16} />
          <span>Back to site</span>
        </button>
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
            <button className="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-default-100 transition-colors">
              <Avatar
                src={user?.avatar_url || user?.avatar || undefined}
                name={user?.name || 'Admin'}
                size="sm"
                className="h-8 w-8"
              />
              <span className="hidden text-sm font-medium text-foreground sm:block">
                {user?.name || 'Admin'}
              </span>
            </button>
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
