/**
 * Main Navigation Bar
 * Responsive header with desktop nav and mobile menu trigger
 * Desktop uses grouped dropdowns for cleaner layout
 * Theme-aware styling for light and dark modes
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { Link, NavLink, useNavigate, useLocation } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
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
  Newspaper,
  BookOpen,
  FolderOpen,
  Heart,
  Building2,
  X,
  Globe,
  Info,
  FileText,
  Shield,
  Handshake,
  Stethoscope,
  TrendingUp,
  BarChart3,
  Compass,
} from 'lucide-react';
import { useAuth, useTenant, useNotifications, useTheme } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { api, tokenManager, API_BASE } from '@/lib/api';

interface SearchSuggestion {
  id: number;
  title?: string;
  name?: string;
  type: 'listing' | 'user' | 'event' | 'group';
}

interface NavbarProps {
  onMobileMenuOpen?: () => void;
  /** External control for search overlay (from MobileDrawer) */
  externalSearchOpen?: boolean;
  onSearchOpenChange?: (open: boolean) => void;
}

export function Navbar({ onMobileMenuOpen, externalSearchOpen, onSearchOpenChange }: NavbarProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, isAuthenticated, logout } = useAuth();
  const { tenant, branding, hasFeature, hasModule, tenantPath } = useTenant();
  const { unreadCount, counts } = useNotifications();
  const { resolvedTheme, toggleTheme } = useTheme();

  // Compute admin status once — used for admin links in dropdown
  const isAdmin = Boolean(user?.role === 'admin' || user?.role === 'tenant_admin' || user?.role === 'super_admin' || user?.is_admin || user?.is_super_admin);

  // Search state — can be controlled externally
  const [internalSearchOpen, setInternalSearchOpen] = useState(false);
  const isSearchOpen = externalSearchOpen ?? internalSearchOpen;
  const setIsSearchOpen = useCallback((open: boolean) => {
    setInternalSearchOpen(open);
    onSearchOpenChange?.(open);
  }, [onSearchOpenChange]);
  const [searchQuery, setSearchQuery] = useState('');
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Dropdown state - controlled to fix close behavior
  const [communityOpen, setCommunityOpen] = useState(false);
  const [moreOpen, setMoreOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [userOpen, setUserOpen] = useState(false);

  // Close ALL dropdowns — used before navigation to prevent stale open state
  const closeAllDropdowns = useCallback(() => {
    setCommunityOpen(false);
    setMoreOpen(false);
    setCreateOpen(false);
    setUserOpen(false);
  }, []);

  // Controlled onOpenChange handlers — close OTHER dropdowns when one opens
  const handleCommunityOpenChange = useCallback((open: boolean) => {
    if (open) { setMoreOpen(false); setCreateOpen(false); setUserOpen(false); }
    setCommunityOpen(open);
  }, []);
  const handleMoreOpenChange = useCallback((open: boolean) => {
    if (open) { setCommunityOpen(false); setCreateOpen(false); setUserOpen(false); }
    setMoreOpen(open);
  }, []);
  const handleCreateOpenChange = useCallback((open: boolean) => {
    if (open) { setCommunityOpen(false); setMoreOpen(false); setUserOpen(false); }
    setCreateOpen(open);
  }, []);
  const handleUserOpenChange = useCallback((open: boolean) => {
    if (open) { setCommunityOpen(false); setMoreOpen(false); setCreateOpen(false); }
    setUserOpen(open);
  }, []);

  // Navigate after closing dropdown — ensures state update flushes before route change
  const dropdownNavigate = useCallback((path: string) => {
    closeAllDropdowns();
    // Use requestAnimationFrame to let React flush the close state update
    // before navigate() triggers a re-render from route change.
    // Without this, React may batch the setState(false) + navigate re-render
    // and the dropdown sees stale isOpen=true during the transition.
    requestAnimationFrame(() => {
      navigate(path);
    });
  }, [closeAllDropdowns, navigate]);

  const handleLogout = async () => {
    closeAllDropdowns();
    await logout();
    navigate(tenantPath('/login'));
  };

  // Close all dropdowns when route changes (safety net)
  useEffect(() => {
    closeAllDropdowns();
  }, [location.pathname, closeAllDropdowns]);

  // Keyboard shortcut: Ctrl/Cmd+K opens search
  useEffect(() => {
    function handleKeyDown(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        setIsSearchOpen(true);
      }
      if (e.key === 'Escape' && isSearchOpen) {
        setIsSearchOpen(false);
        setSearchQuery('');
      }
    }
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isSearchOpen]);

  // Auto-focus search input when opened
  useEffect(() => {
    if (isSearchOpen && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [isSearchOpen]);

  // Live search suggestions
  const [suggestions, setSuggestions] = useState<SearchSuggestion[]>([]);
  const [isLoadingSuggestions, setIsLoadingSuggestions] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const suggestionsTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Debounced suggestions fetch
  useEffect(() => {
    if (suggestionsTimerRef.current) {
      clearTimeout(suggestionsTimerRef.current);
    }

    if (!isSearchOpen || searchQuery.trim().length < 2) {
      setSuggestions([]);
      return;
    }

    suggestionsTimerRef.current = setTimeout(async () => {
      try {
        setIsLoadingSuggestions(true);
        const response = await api.get<Record<string, SearchSuggestion[]>>(
          `/v2/search/suggestions?q=${encodeURIComponent(searchQuery.trim())}&limit=5`
        );
        if (response.success && response.data) {
          // Flatten all suggestion types into one array
          const allSuggestions: SearchSuggestion[] = [];
          const data = response.data;
          if (data.listings) allSuggestions.push(...data.listings.map((s: SearchSuggestion) => ({ ...s, type: 'listing' as const })));
          if (data.users) allSuggestions.push(...data.users.map((s: SearchSuggestion) => ({ ...s, type: 'user' as const })));
          if (data.events) allSuggestions.push(...data.events.map((s: SearchSuggestion) => ({ ...s, type: 'event' as const })));
          if (data.groups) allSuggestions.push(...data.groups.map((s: SearchSuggestion) => ({ ...s, type: 'group' as const })));
          setSuggestions(allSuggestions.slice(0, 8));
        }
      } catch {
        // Silently fail — suggestions are non-critical
      } finally {
        setIsLoadingSuggestions(false);
      }
    }, 250);

    return () => {
      if (suggestionsTimerRef.current) {
        clearTimeout(suggestionsTimerRef.current);
      }
    };
  }, [searchQuery, isSearchOpen]);

  // Reset selection when suggestions change
  useEffect(() => {
    setSelectedIndex(-1);
  }, [suggestions]);

  const handleSearchKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (suggestions.length === 0) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev + 1) % suggestions.length);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex((prev) => (prev <= 0 ? suggestions.length - 1 : prev - 1));
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
      e.preventDefault();
      handleSuggestionClick(suggestions[selectedIndex]);
    }
  }, [suggestions, selectedIndex]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleSearchSubmit = useCallback((e?: React.FormEvent) => {
    e?.preventDefault();
    if (selectedIndex >= 0 && suggestions.length > 0) {
      handleSuggestionClick(suggestions[selectedIndex]);
      return;
    }
    if (searchQuery.trim()) {
      navigate(tenantPath(`/search?q=${encodeURIComponent(searchQuery.trim())}`));
      setIsSearchOpen(false);
      setSearchQuery('');
      setSuggestions([]);
    }
  }, [searchQuery, navigate, selectedIndex, suggestions]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleSuggestionClick = useCallback((suggestion: SearchSuggestion) => {
    const pathMap: Record<string, string> = {
      listing: tenantPath(`/listings/${suggestion.id}`),
      user: tenantPath(`/profile/${suggestion.id}`),
      event: tenantPath(`/events/${suggestion.id}`),
      group: tenantPath(`/groups/${suggestion.id}`),
    };
    navigate(pathMap[suggestion.type] || tenantPath('/search'));
    setIsSearchOpen(false);
    setSearchQuery('');
    setSuggestions([]);
  }, [navigate]);

  // Check if current path matches any in a group
  const isActiveGroup = (paths: string[]) => {
    return paths.some(path => location.pathname.startsWith(path));
  };

  // Community dropdown items
  const communityItems = [
    { label: 'Members', href: tenantPath('/members'), icon: Users, feature: 'connections' as const },
    { label: 'Events', href: tenantPath('/events'), icon: Calendar, feature: 'events' as const },
    { label: 'Groups', href: tenantPath('/groups'), icon: Users, feature: 'groups' as const },
    { label: 'Blog', href: tenantPath('/blog'), icon: BookOpen, feature: 'blog' as const },
    { label: 'Volunteering', href: tenantPath('/volunteering'), icon: Heart, feature: 'volunteering' as const },
    { label: 'Organisations', href: tenantPath('/organisations'), icon: Building2, feature: 'volunteering' as const },
    { label: 'Resources', href: tenantPath('/resources'), icon: FolderOpen, feature: 'resources' as const },
  ].filter(item => hasFeature(item.feature));

  // Activity dropdown items — filtered by feature flags and module flags
  const activityItems = [
    { label: 'Exchanges', href: tenantPath('/exchanges'), icon: ArrowRightLeft, feature: 'exchange_workflow' as const },
    { label: 'Group Exchanges', href: tenantPath('/group-exchanges'), icon: Users, feature: 'group_exchanges' as const },
    { label: 'Wallet', href: tenantPath('/wallet'), icon: Wallet, module: 'wallet' as const },
    { label: 'Achievements', href: tenantPath('/achievements'), icon: Trophy, feature: 'gamification' as const },
    { label: 'Goals', href: tenantPath('/goals'), icon: Target, feature: 'goals' as const },
  ].filter(item => {
    if (item.feature && !hasFeature(item.feature)) return false;
    if (item.module && !hasModule(item.module)) return false;
    return true;
  });

  // Federation dropdown items — only shown when federation feature is enabled
  const federationItems = hasFeature('federation') ? [
    { label: 'Federation Hub', href: tenantPath('/federation'), icon: Globe },
    { label: 'Partner Communities', href: tenantPath('/federation/partners'), icon: Building2 },
    { label: 'Federated Members', href: tenantPath('/federation/members'), icon: Users },
    { label: 'Federated Messages', href: tenantPath('/federation/messages'), icon: MessageSquare },
    { label: 'Federated Listings', href: tenantPath('/federation/listings'), icon: ListTodo },
    { label: 'Federated Events', href: tenantPath('/federation/events'), icon: Calendar },
  ] : [];

  // About dropdown — static items + dynamic CMS pages from bootstrap
  const aboutItems = [
    { label: 'About', href: tenantPath('/about'), icon: Info },
    { label: 'FAQ', href: tenantPath('/faq'), icon: HelpCircle },
    { label: 'Timebanking Guide', href: tenantPath('/timebanking-guide'), icon: BookOpen },
    { label: 'Partner With Us', href: tenantPath('/partner'), icon: Handshake },
    { label: 'Social Prescribing', href: tenantPath('/social-prescribing'), icon: Stethoscope },
    { label: 'Our Impact', href: tenantPath('/impact-summary'), icon: TrendingUp },
    { label: 'Impact Report', href: tenantPath('/impact-report'), icon: BarChart3 },
    { label: 'Strategic Plan', href: tenantPath('/strategic-plan'), icon: Compass },
    ...(tenant?.menu_pages?.about || []).map((p: { title: string; slug: string }) => ({
      label: p.title,
      href: tenantPath(`/${p.slug}`),
      icon: FileText,
    })),
  ];

  const communityPaths = communityItems.map(i => i.href);
  const activityPaths = activityItems.map(i => i.href);
  const federationPaths = federationItems.map(i => i.href);
  const aboutPaths = aboutItems.map(i => i.href);

  // "More" dropdown combines Activity, Federation, and About
  const morePaths = [...activityPaths, ...federationPaths, ...aboutPaths];

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
            <Link to={tenantPath('/')} className="flex items-center gap-2">
              {branding.logo ? (
                <img
                  src={branding.logo}
                  alt={branding.name}
                  className="h-8 sm:h-9 w-auto object-contain"
                />
              ) : (
                <motion.div
                  whileHover={{ rotate: 180 }}
                  transition={{ duration: 0.5 }}
                >
                  <Hexagon className="w-7 h-7 sm:w-8 sm:h-8 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                </motion.div>
              )}
              <span className="font-bold text-lg sm:text-xl text-gradient hidden min-[480px]:inline">
                {branding.name}
              </span>
            </Link>
          </div>

          {/* Desktop Navigation - Reorganized with Dropdowns */}
          <nav className="hidden lg:flex items-center gap-1" aria-label="Main navigation">
            {/* Dashboard - Direct Link (module-gated) */}
            {hasModule('dashboard') && (
              <NavLink
                to={tenantPath('/dashboard')}
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
            )}

            {/* Feed - Direct Link (module-gated) */}
            {hasModule('feed') && (
              <NavLink
                to={tenantPath('/feed')}
                className={({ isActive }) =>
                  `flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                    isActive
                      ? 'bg-theme-active text-theme-primary'
                      : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                  }`
                }
              >
                <Newspaper className="w-4 h-4" aria-hidden="true" />
                <span>Feed</span>
              </NavLink>
            )}

            {/* Listings - Direct Link (module-gated) */}
            {hasModule('listings') && (
              <NavLink
                to={tenantPath('/listings')}
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
            )}

            {/* Messages - Direct Link with Badge (module-gated) */}
            {hasModule('messages') && (
              <NavLink
                to={tenantPath('/messages')}
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
            )}

            {/* Community Dropdown */}
            {communityItems.length > 0 && (
              <Dropdown placement="bottom-start" isOpen={communityOpen} onOpenChange={handleCommunityOpenChange} shouldBlockScroll={false}>
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
                    base: 'bg-[var(--surface-overlay)] border border-[var(--border-default)] shadow-xl',
                  }}
                  onAction={(key) => {
                    dropdownNavigate(String(key));
                  }}
                >
                  {communityItems.map((item) => (
                    <DropdownItem
                      key={item.href}
                      startContent={<item.icon className="w-4 h-4" aria-hidden="true" />}
                      className={location.pathname.startsWith(item.href) ? 'bg-theme-active' : ''}
                    >
                      {item.label}
                    </DropdownItem>
                  ))}
                </DropdownMenu>
              </Dropdown>
            )}

            {/* More Dropdown — combines Activity, Federation, and About */}
            <Dropdown placement="bottom-start" isOpen={moreOpen} onOpenChange={handleMoreOpenChange} shouldBlockScroll={false}>
              <DropdownTrigger>
                <Button
                  variant="light"
                  size="sm"
                  className={`flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all ${
                    isActiveGroup(morePaths)
                      ? 'bg-theme-active text-theme-primary'
                      : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                  }`}
                  endContent={<ChevronDown className="w-3 h-3" aria-hidden="true" />}
                >
                  <Menu className="w-4 h-4" aria-hidden="true" />
                  More
                </Button>
              </DropdownTrigger>
              <DropdownMenu
                aria-label="More navigation"
                className="min-w-[200px]"
                classNames={{
                  base: 'bg-[var(--surface-overlay)] border border-[var(--border-default)] shadow-xl',
                }}
                onAction={(key) => {
                  dropdownNavigate(String(key));
                }}
              >
                {activityItems.length > 0 ? (
                  <DropdownSection title="Activity" showDivider={federationItems.length > 0 || aboutItems.length > 0}>
                    {activityItems.map((item) => (
                      <DropdownItem
                        key={item.href}
                        startContent={<item.icon className="w-4 h-4" aria-hidden="true" />}
                        className={location.pathname.startsWith(item.href) ? 'bg-theme-active' : ''}
                      >
                        {item.label}
                      </DropdownItem>
                    ))}
                  </DropdownSection>
                ) : null}
                {federationItems.length > 0 ? (
                  <DropdownSection title="Partner Communities" showDivider>
                    {federationItems.map((item) => (
                      <DropdownItem
                        key={item.href}
                        startContent={<item.icon className="w-4 h-4" aria-hidden="true" />}
                        className={location.pathname.startsWith(item.href) ? 'bg-theme-active' : ''}
                      >
                        {item.label}
                      </DropdownItem>
                    ))}
                  </DropdownSection>
                ) : null}
                <DropdownSection title="About">
                  {aboutItems.map((item) => (
                    <DropdownItem
                      key={item.href}
                      startContent={<item.icon className="w-4 h-4" aria-hidden="true" />}
                      className={location.pathname.startsWith(item.href) ? 'bg-theme-active' : ''}
                    >
                      {item.label}
                    </DropdownItem>
                  ))}
                </DropdownSection>
              </DropdownMenu>
            </Dropdown>
          </nav>

          {/* User Actions */}
          <div className="flex items-center gap-1 sm:gap-2">
            {isAuthenticated ? (
              <>
                {/* Search Trigger - Desktop: styled button, Mobile: icon */}
                <Button
                  variant="flat"
                  size="sm"
                  className="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-theme-default bg-theme-elevated hover:bg-theme-hover text-theme-subtle text-sm h-auto"
                  onPress={() => setIsSearchOpen(true)}
                  aria-label="Search (Ctrl+K)"
                >
                  <Search className="w-4 h-4" aria-hidden="true" />
                  <span className="text-theme-subtle">Search...</span>
                  <kbd className="ml-2 hidden lg:inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-theme-hover text-[10px] font-medium text-theme-subtle border border-theme-default">
                    <span className="text-xs">⌘</span>K
                  </kbd>
                </Button>
                <Button
                  isIconOnly
                  variant="light"
                  size="sm"
                  className="flex md:hidden text-theme-muted hover:text-theme-primary"
                  onPress={() => setIsSearchOpen(true)}
                  aria-label="Search"
                >
                  <Search className="w-5 h-5" aria-hidden="true" />
                </Button>

                {/* Create Button - Desktop only */}
                <Dropdown placement="bottom-end" isOpen={createOpen} onOpenChange={handleCreateOpenChange} shouldBlockScroll={false}>
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
                      base: 'bg-[var(--surface-overlay)] border border-[var(--border-default)] shadow-xl',
                    }}
                    onAction={(key) => {
                      dropdownNavigate(String(key));
                    }}
                  >
                    <DropdownItem
                      key={tenantPath('/listings/create')}
                      startContent={<ListTodo className="w-4 h-4" aria-hidden="true" />}
                    >
                      New Listing
                    </DropdownItem>
                    {hasFeature('events') ? (
                      <DropdownItem
                        key={tenantPath('/events/create')}
                        startContent={<Calendar className="w-4 h-4" aria-hidden="true" />}
                      >
                        New Event
                      </DropdownItem>
                    ) : null}
                  </DropdownMenu>
                </Dropdown>

                {/* Theme Toggle */}
                <Button
                  isIconOnly
                  variant="light"
                  size="sm"
                  className="text-theme-muted hover:text-theme-primary"
                  onPress={toggleTheme}
                  aria-label={`Switch to ${resolvedTheme === 'dark' ? 'light' : 'dark'} mode`}
                >
                  {resolvedTheme === 'dark' ? (
                    <Sun className="w-4 h-4 sm:w-5 sm:h-5 text-amber-400" aria-hidden="true" />
                  ) : (
                    <Moon className="w-4 h-4 sm:w-5 sm:h-5 text-indigo-500" aria-hidden="true" />
                  )}
                </Button>

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
                    onPress={() => navigate(tenantPath('/notifications'))}
                    aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
                  >
                    <Bell className="w-4 h-4 sm:w-5 sm:h-5" aria-hidden="true" />
                  </Button>
                </Badge>

                {/* User Dropdown - Enhanced */}
                <Dropdown placement="bottom-end" isOpen={userOpen} onOpenChange={handleUserOpenChange} shouldBlockScroll={false}>
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
                      base: 'bg-[var(--surface-overlay)] border border-[var(--border-default)] shadow-xl min-w-[220px]',
                    }}
                    onAction={(key) => {
                      const k = String(key);
                      if (k === 'theme') { toggleTheme(); closeAllDropdowns(); return; }
                      if (k === 'logout') { handleLogout(); return; }
                      if (k === 'profile-header') return;
                      if (k === 'admin-panel') { if (isAdmin) dropdownNavigate('/admin'); return; }
                      if (k === 'legacy-admin') { if (isAdmin) { closeAllDropdowns(); const t = tokenManager.getAccessToken(); const apiBase = API_BASE.startsWith('http') ? API_BASE.replace(/\/+$/, '') : window.location.origin + (API_BASE.startsWith('/') ? API_BASE : '/' + API_BASE).replace(/\/+$/, ''); window.location.href = t ? `${apiBase}/auth/admin-session?token=${encodeURIComponent(t)}&redirect=/admin-legacy` : `${apiBase.replace(/\/api$/, '')}/admin-legacy`; } return; }
                      dropdownNavigate(k);
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
                        key={tenantPath('/profile')}
                        startContent={<UserCircle className="w-4 h-4" aria-hidden="true" />}
                      >
                        My Profile
                      </DropdownItem>
                      {hasModule('wallet') ? (
                        <DropdownItem
                          key={tenantPath('/wallet')}
                          startContent={<Wallet className="w-4 h-4" aria-hidden="true" />}
                          endContent={
                            <span className="text-xs text-theme-subtle">
                              {user?.balance ?? 0}h
                            </span>
                          }
                        >
                          Wallet
                        </DropdownItem>
                      ) : null}
                      <DropdownItem
                        key={tenantPath('/settings')}
                        startContent={<Settings className="w-4 h-4" aria-hidden="true" />}
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
                      >
                        {resolvedTheme === 'dark' ? 'Light Mode' : 'Dark Mode'}
                      </DropdownItem>
                      <DropdownItem
                        key={tenantPath('/help')}
                        startContent={<HelpCircle className="w-4 h-4" aria-hidden="true" />}
                      >
                        Help Center
                      </DropdownItem>
                    </DropdownSection>

                    <DropdownSection
                      showDivider
                      title="Admin"
                      classNames={{
                        base: isAdmin ? '' : 'hidden',
                      }}
                    >
                      <DropdownItem
                        key="admin-panel"
                        startContent={<Shield className="w-4 h-4" aria-hidden="true" />}
                      >
                        Admin Panel
                      </DropdownItem>
                      <DropdownItem
                        key="legacy-admin"
                        startContent={<LayoutDashboard className="w-4 h-4" aria-hidden="true" />}
                      >
                        Legacy Admin
                      </DropdownItem>
                    </DropdownSection>

                    <DropdownSection>
                      <DropdownItem
                        key="logout"
                        color="danger"
                        startContent={<LogOut className="w-4 h-4" aria-hidden="true" />}
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
                <Link to={tenantPath('/login')} className="hidden sm:block">
                  <Button variant="light" size="sm" className="text-theme-secondary hover:text-theme-primary">
                    Log In
                  </Button>
                </Link>
                <Link to={tenantPath('/register')}>
                  <Button size="sm" className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium">
                    Sign Up
                  </Button>
                </Link>
              </>
            )}
          </div>
        </div>
      </div>
      {/* Search Overlay */}
      <AnimatePresence>
        {isSearchOpen && (
          <>
            {/* Backdrop */}
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60]"
              onClick={() => { setIsSearchOpen(false); setSearchQuery(''); setSuggestions([]); }}
            />

            {/* Search Panel */}
            <motion.div
              initial={{ opacity: 0, y: -20, scale: 0.95 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, y: -20, scale: 0.95 }}
              transition={{ duration: 0.15 }}
              className="fixed top-20 left-1/2 -translate-x-1/2 w-[90vw] max-w-xl z-[70]"
            >
              <div className="bg-[var(--surface-overlay)] rounded-xl border border-[var(--border-default)] shadow-2xl overflow-hidden">
                <form onSubmit={handleSearchSubmit} className="flex items-center px-4 py-3 gap-3">
                  <Search className="w-5 h-5 text-theme-subtle flex-shrink-0" aria-hidden="true" />
                  <input
                    ref={searchInputRef}
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    onKeyDown={handleSearchKeyDown}
                    placeholder="Search listings, members, events..."
                    className="flex-1 bg-transparent text-theme-primary placeholder:text-theme-subtle outline-none text-base"
                    aria-label="Search"
                    aria-autocomplete="list"
                    aria-activedescendant={selectedIndex >= 0 ? `suggestion-${selectedIndex}` : undefined}
                  />
                  {searchQuery && (
                    <Button
                      isIconOnly
                      variant="light"
                      size="sm"
                      onPress={() => setSearchQuery('')}
                      className="text-theme-subtle hover:text-theme-primary min-w-6 w-6 h-6"
                      aria-label="Clear search"
                    >
                      <X className="w-4 h-4" aria-hidden="true" />
                    </Button>
                  )}
                  <kbd className="hidden sm:inline-flex items-center px-2 py-1 rounded bg-[var(--surface-elevated)] text-xs text-theme-subtle border border-[var(--border-default)]">
                    ESC
                  </kbd>
                </form>

                {/* Suggestions or Quick Links */}
                <div className="border-t border-[var(--border-default)] px-4 py-3 max-h-64 overflow-y-auto">
                  {suggestions.length > 0 ? (
                    <>
                      <p className="text-xs text-theme-subtle mb-2">Suggestions</p>
                      <div className="space-y-1" role="listbox" aria-label="Search suggestions">
                        {suggestions.map((suggestion, index) => {
                          const typeLabels: Record<string, { label: string; color: string }> = {
                            listing: { label: 'Listing', color: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' },
                            user: { label: 'Member', color: 'bg-indigo-500/20 text-indigo-600 dark:text-indigo-400' },
                            event: { label: 'Event', color: 'bg-amber-500/20 text-amber-600 dark:text-amber-400' },
                            group: { label: 'Group', color: 'bg-purple-500/20 text-purple-600 dark:text-purple-400' },
                          };
                          const typeInfo = typeLabels[suggestion.type] || { label: suggestion.type, color: 'bg-[var(--surface-elevated)] text-theme-subtle' };
                          const isSelected = index === selectedIndex;

                          return (
                            <Button
                              id={`suggestion-${index}`}
                              key={`${suggestion.type}-${suggestion.id}`}
                              variant="light"
                              fullWidth
                              role="option"
                              aria-selected={isSelected}
                              onPress={() => handleSuggestionClick(suggestion)}
                              onMouseEnter={() => setSelectedIndex(index)}
                              className={`flex items-center justify-between px-3 py-2 rounded-lg text-left h-auto min-h-0 ${
                                isSelected
                                  ? 'bg-indigo-50 dark:bg-indigo-500/10'
                                  : 'hover:bg-[var(--surface-hover)]'
                              }`}
                            >
                              <span className="text-sm text-theme-primary truncate">
                                {suggestion.title || suggestion.name}
                              </span>
                              <span className={`text-[10px] px-2 py-0.5 rounded-full ${typeInfo.color} ml-2 flex-shrink-0`}>
                                {typeInfo.label}
                              </span>
                            </Button>
                          );
                        })}
                      </div>
                    </>
                  ) : isLoadingSuggestions ? (
                    <div className="flex items-center gap-2 py-2">
                      <div className="w-4 h-4 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
                      <span className="text-xs text-theme-subtle">Searching...</span>
                    </div>
                  ) : (
                    <>
                      <p className="text-xs text-theme-subtle mb-2">Quick Links</p>
                      <div className="flex flex-wrap gap-2">
                        {[
                          { label: 'Listings', path: tenantPath('/listings') },
                          { label: 'Members', path: tenantPath('/members') },
                          { label: 'Events', path: tenantPath('/events') },
                          { label: 'Help', path: tenantPath('/help') },
                        ].map((link) => (
                          <Button
                            key={link.path}
                            variant="flat"
                            size="sm"
                            onPress={() => { navigate(link.path); setIsSearchOpen(false); setSearchQuery(''); }}
                            className="px-3 py-1.5 rounded-lg bg-[var(--surface-elevated)] text-sm text-theme-muted hover:text-theme-primary hover:bg-[var(--surface-hover)]"
                          >
                            {link.label}
                          </Button>
                        ))}
                      </div>
                    </>
                  )}
                </div>
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </header>
  );
}

export default Navbar;
