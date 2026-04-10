// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Main Navigation Bar
 * Responsive header with utility bar, desktop nav, and mobile menu trigger.
 * Desktop uses grouped dropdowns and a mega menu for cleaner layout.
 * Theme-aware styling for light and dark modes.
 */

import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { DevelopmentStatusBanner } from './DevelopmentStatusBanner';
import { Link, NavLink, useNavigate, useLocation } from 'react-router-dom';
import {
  Button,
  Avatar,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  DropdownSection,
} from '@heroui/react';
import {
  LayoutDashboard,
  ListTodo,
  MessageSquare,
  Wallet,
  Users,
  Users2,
  Calendar,
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
  Medal,
  Target,
  HelpCircle,
  UserCircle,
  Newspaper,
  BookOpen,
  FolderOpen,
  Heart,
  Building2,
  Globe,
  Info,
  FileText,
  Shield,
  Handshake,
  Stethoscope,
  TrendingUp,
  BarChart3,
  Compass,
  Bot,
  Briefcase,
  Lightbulb,
  GraduationCap,
  Activity,
  ShoppingBag,
  Fingerprint,
  ShieldCheck,
  Bookmark,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useNotifications, useTheme, useMenuContext } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { LanguageSwitcher } from '@/components/LanguageSwitcher';
import { DesktopMenuItems } from '@/components/navigation';
import { SearchOverlay } from '@/components/layout/SearchOverlay';
import { MegaMenu } from '@/components/layout/MegaMenu';
import { NotificationFlyout } from '@/components/layout/NotificationFlyout';
import { TenantLogo } from '@/components/branding';
import { PresenceIndicator, StatusSelector } from '@/components/social';
import { useHeaderScroll } from '@/hooks/useHeaderScroll';

interface NavbarProps {
  /** Opens mobile drawer — only used for unauthenticated users (authenticated users use MobileTabBar) */
  onMobileMenuOpen?: () => void;
  /** External control for search overlay (from MobileDrawer) */
  externalSearchOpen?: boolean;
  onSearchOpenChange?: (open: boolean) => void;
  /** Whether the mobile drawer is currently open — hides navbar on mobile when true */
  isMobileMenuOpen?: boolean;
}

export function Navbar({ onMobileMenuOpen, externalSearchOpen, onSearchOpenChange, isMobileMenuOpen }: NavbarProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const { t } = useTranslation('common');
  const { user, isAuthenticated, logout } = useAuth();
  const { tenant, hasFeature, hasModule, tenantPath } = useTenant();
  const { counts } = useNotifications();
  const { resolvedTheme, toggleTheme } = useTheme();
  const { headerMenus, hasCustomMenus } = useMenuContext();

  // Scroll behavior for utility bar auto-hide + logo shrink
  const { isScrolled, isUtilityBarVisible } = useHeaderScroll(48);

  // Smart nav: track overflow to collapse items into MegaMenu
  const navRef = useRef<HTMLElement>(null);
  const [maxVisibleNav, setMaxVisibleNav] = useState(6);

  useEffect(() => {
    const el = navRef.current;
    if (!el) return;
    const observer = new ResizeObserver(() => {
      const availableWidth = el.offsetWidth;
      // Each nav item is ~110px avg, Community dropdown ~130px, More ~90px
      // Reserve 220px for Community + More
      const itemWidth = 110;
      const reserved = 220;
      const count = Math.max(1, Math.floor((availableWidth - reserved) / itemWidth));
      setMaxVisibleNav(count);
    });
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  // Compute admin status once
  const isAdmin = Boolean(user?.role === 'admin' || user?.role === 'tenant_admin' || user?.role === 'super_admin' || user?.is_admin || user?.is_super_admin || user?.is_tenant_super_admin);

  // Search state — can be controlled externally
  const [internalSearchOpen, setInternalSearchOpen] = useState(false);
  const isSearchOpen = externalSearchOpen ?? internalSearchOpen;
  const setIsSearchOpen = useCallback((open: boolean) => {
    setInternalSearchOpen(open);
    onSearchOpenChange?.(open);
  }, [onSearchOpenChange]);

  // Dropdown state - controlled to fix close behavior
  const [timebankingOpen, setTimebankingOpen] = useState(false);
  const [communityOpen, setCommunityOpen] = useState(false);
  const [moreOpen, setMoreOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [userOpen, setUserOpen] = useState(false);

  const closeAllDropdowns = useCallback(() => {
    setTimebankingOpen(false);
    setCommunityOpen(false);
    setMoreOpen(false);
    setCreateOpen(false);
    setUserOpen(false);
  }, []);

  const handleTimebankingOpenChange = useCallback((open: boolean) => {
    if (open) { setCommunityOpen(false); setMoreOpen(false); setCreateOpen(false); setUserOpen(false); }
    setTimebankingOpen(open);
  }, []);
  const handleCommunityOpenChange = useCallback((open: boolean) => {
    if (open) { setTimebankingOpen(false); setMoreOpen(false); setCreateOpen(false); setUserOpen(false); }
    setCommunityOpen(open);
  }, []);
  const handleMoreOpenChange = useCallback((open: boolean) => {
    if (open) { setTimebankingOpen(false); setCommunityOpen(false); setCreateOpen(false); setUserOpen(false); }
    setMoreOpen(open);
  }, []);
  const handleCreateOpenChange = useCallback((open: boolean) => {
    if (open) { setTimebankingOpen(false); setCommunityOpen(false); setMoreOpen(false); setUserOpen(false); }
    setCreateOpen(open);
  }, []);
  const handleUserOpenChange = useCallback((open: boolean) => {
    if (open) { setTimebankingOpen(false); setCommunityOpen(false); setMoreOpen(false); setCreateOpen(false); }
    setUserOpen(open);
  }, []);

  // Identity verification status — shows "Verify Identity" or "Identity Verified" in utility bar
  const [isIdVerified, setIsIdVerified] = useState<boolean>(false);
  const [idVerifiedLoaded, setIdVerifiedLoaded] = useState(false);
  useEffect(() => {
    if (!isAuthenticated || !user?.id) return;
    let cancelled = false;
    import('@/lib/api').then(({ api }) => {
      api.get('/v2/identity/status').then((res: any) => {
        if (cancelled) return;
        const data = res?.data;
        setIsIdVerified(data?.has_id_verified_badge === true);
        setIdVerifiedLoaded(true);
      }).catch(() => {
        if (!cancelled) { setIsIdVerified(false); setIdVerifiedLoaded(true); }
      });
    }).catch(() => {
      if (!cancelled) { setIsIdVerified(false); setIdVerifiedLoaded(true); }
    });
    return () => { cancelled = true; };
  }, [isAuthenticated, user?.id]);

  const dropdownNavigate = useCallback((path: string) => {
    closeAllDropdowns();
    requestAnimationFrame(() => {
      navigate(path);
    });
  }, [closeAllDropdowns, navigate]);

  const handleLogout = async () => {
    closeAllDropdowns();
    await logout();
    navigate(tenantPath('/login'));
  };

  // Close all dropdowns when route changes
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
    }
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [setIsSearchOpen]);

  // Check if current path matches any in a group
  const isActiveGroup = (paths: string[]) => {
    return paths.some(path => location.pathname.startsWith(path));
  };

  // ─── Memoized nav item arrays ──────────────────────────────────────────────
  const isHourTimebank = tenant?.slug === 'hour-timebank';

  // Timebanking dropdown — replaces top-level Listings link
  const timebankingItems = useMemo(() => [
    { label: t('nav.listings'), desc: t('nav_desc.timebanking_listings', 'Offers & requests'), href: tenantPath('/listings'), icon: ListTodo, module: 'listings' as const },
    { label: t('nav.exchanges'), desc: t('nav_desc.exchanges'), href: tenantPath('/exchanges'), icon: ArrowRightLeft, feature: 'exchange_workflow' as const },
    { label: t('nav.group_exchanges'), desc: t('nav_desc.group_exchanges'), href: tenantPath('/group-exchanges'), icon: Users, feature: 'group_exchanges' as const },
    { label: t('nav.wallet'), desc: t('nav_desc.wallet'), href: tenantPath('/wallet'), icon: Wallet, module: 'wallet' as const },
  ].filter(item => {
    if ('feature' in item && item.feature) return hasFeature(item.feature as Parameters<typeof hasFeature>[0]);
    if ('module' in item && item.module) return hasModule(item.module as Parameters<typeof hasModule>[0]);
    return true;
  }), [t, tenantPath, hasFeature, hasModule]);

  const communityItems = useMemo(() => [
    { label: t('nav.members'), desc: t('nav_desc.members'), path: '/members', href: tenantPath('/members'), icon: Users, feature: 'connections' as const },
    { label: t('nav.connections'), desc: t('nav_desc.connections'), path: '/connections', href: tenantPath('/connections'), icon: Users2, feature: 'connections' as const },
    { label: t('nav.events'), desc: t('nav_desc.events'), path: '/events', href: tenantPath('/events'), icon: Calendar, feature: 'events' as const },
    { label: t('nav.groups'), desc: t('nav_desc.groups'), path: '/groups', href: tenantPath('/groups'), icon: Users, feature: 'groups' as const },
    { label: t('nav.volunteering'), desc: t('nav_desc.volunteering'), path: '/volunteering', href: tenantPath('/volunteering'), icon: Heart, feature: 'volunteering' as const },
    { label: t('nav.resources'), desc: t('nav_desc.resources'), path: '/resources', href: tenantPath('/resources'), icon: FolderOpen, feature: 'resources' as const },
    { label: t('nav.jobs'), desc: t('nav_desc.jobs'), path: '/jobs', href: tenantPath('/jobs'), icon: Briefcase, feature: 'job_vacancies' as const },
    { label: t('nav.marketplace', 'Marketplace'), desc: t('nav_desc.marketplace', 'Buy & sell in your community'), path: '/marketplace', href: tenantPath('/marketplace'), icon: ShoppingBag, feature: 'marketplace' as const },
  ].filter(item => hasFeature(item.feature)), [t, tenantPath, hasFeature]);

  // Helper to filter items by feature/module gates
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const gateFilter = useCallback((item: any) => {
    if ('feature' in item && item.feature && !hasFeature(item.feature as Parameters<typeof hasFeature>[0])) return false;
    if ('module' in item && item.module && !hasModule(item.module as Parameters<typeof hasModule>[0])) return false;
    return true;
  }, [hasFeature, hasModule]);

  // ─── Collapsed primary nav items → overflow into MegaMenu ────────────────
  const overflowNavItems = useMemo(() => {
    const items: { label: string; desc: string; href: string; icon: typeof LayoutDashboard; module?: string }[] = [];
    if (hasModule('feed') && maxVisibleNav < 1)
      items.push({ label: t('nav.feed'), desc: t('nav_desc.feed', 'Community feed'), href: tenantPath('/feed'), icon: Newspaper, module: 'feed' });
    if (maxVisibleNav < 2)
      items.push({ label: t('nav.explore', 'Explore'), desc: t('nav_desc.explore', 'Discover content'), href: tenantPath('/explore'), icon: Compass });
    if (hasModule('messages') && maxVisibleNav < 4)
      items.push({ label: t('nav.messages'), desc: t('nav_desc.messages', 'Your messages'), href: tenantPath('/messages'), icon: MessageSquare, module: 'messages' });
    return items;
  }, [maxVisibleNav, hasModule, t, tenantPath]);

  // ─── Left column sections ────────────────────────────────────────────────
  const leftSections = useMemo(() => [
    // Overflow section — only visible when primary nav items are collapsed
    ...(overflowNavItems.length > 0 ? [{
      key: 'main',
      title: t('sections.main', 'Main'),
      items: overflowNavItems,
    }] : []),
    {
      key: 'engage',
      title: t('sections.engage', 'Engage'),
      items: [
        { label: t('nav.goals'), desc: t('nav_desc.goals'), href: tenantPath('/goals'), icon: Target, feature: 'goals' },
        { label: t('nav.polls'), desc: t('nav_desc.polls'), href: tenantPath('/polls'), icon: BarChart3, feature: 'polls' },
        { label: t('nav.ideation'), desc: t('nav_desc.ideation'), href: tenantPath('/ideation'), icon: Lightbulb, feature: 'ideation_challenges' },
      ].filter(gateFilter),
    },
    {
      key: 'progress',
      title: t('sections.progress', 'Progress'),
      collapsible: true,
      defaultExpanded: false,
      items: [
        { label: t('nav.achievements'), desc: t('nav_desc.achievements'), href: tenantPath('/achievements'), icon: Trophy, feature: 'gamification' },
        { label: t('nav.leaderboard'), desc: t('nav_desc.leaderboard'), href: tenantPath('/leaderboard'), icon: Medal, feature: 'gamification' },
        { label: t('nav.nexus_score', 'NexusScore'), desc: t('nav_desc.nexus_score'), href: tenantPath('/nexus-score'), icon: BarChart3, feature: 'gamification' },
      ].filter(gateFilter),
    },
    {
      key: 'tools',
      title: t('sections.tools', 'Tools'),
      collapsible: true,
      defaultExpanded: false,
      items: [
        { label: t('nav.matches', 'Matches'), desc: t('nav_desc.matches'), href: tenantPath('/matches'), icon: Handshake },
        { label: t('nav.skills', 'Skills'), desc: t('nav_desc.skills'), href: tenantPath('/skills'), icon: GraduationCap },
        { label: t('nav.saved', 'Saved'), desc: t('nav_desc.saved', 'Your bookmarked items'), href: tenantPath('/saved'), icon: Bookmark },
        { label: t('nav.activity', 'My Activity'), desc: t('nav_desc.activity'), href: tenantPath('/activity'), icon: Activity },
        { label: t('nav.ai_chat', 'AI Assistant'), desc: t('nav_desc.ai_chat'), href: tenantPath('/chat'), icon: Bot, feature: 'ai_chat' },
      ].filter(gateFilter),
    },
  ], [t, tenantPath, overflowNavItems, gateFilter]);

  // ─── Right column sections ───────────────────────────────────────────────
  const rightSections = useMemo(() => [
    {
      key: 'about',
      title: t('sections.about'),
      items: [
        { label: t('nav.about'), desc: t('nav_desc.about'), href: tenantPath('/about'), icon: Info },
        { label: t('nav.blog'), desc: t('nav_desc.blog'), href: tenantPath('/blog'), icon: BookOpen, feature: 'blog' },
        { label: t('nav.faq'), desc: t('nav_desc.faq'), href: tenantPath('/faq'), icon: HelpCircle },
        { label: t('nav.timebanking_guide'), desc: t('nav_desc.timebanking_guide'), href: tenantPath('/timebanking-guide'), icon: BookOpen },
        ...(tenant?.menu_pages?.about || []).map((pg: { title: string; slug: string }) => ({
          label: pg.title,
          desc: undefined as string | undefined,
          href: tenantPath(`/page/${pg.slug}`),
          icon: FileText,
        })),
      ].filter(gateFilter),
    },
    ...(isHourTimebank ? [{
      key: 'impact',
      title: t('sections.impact', 'Impact'),
      collapsible: true,
      defaultExpanded: false,
      items: [
        { label: t('nav.partner_with_us'), desc: t('nav_desc.partner_with_us'), href: tenantPath('/partner'), icon: Handshake },
        { label: t('nav.social_prescribing'), desc: t('nav_desc.social_prescribing'), href: tenantPath('/social-prescribing'), icon: Stethoscope },
        { label: t('nav.our_impact'), desc: t('nav_desc.our_impact'), href: tenantPath('/impact-summary'), icon: TrendingUp },
        { label: t('nav.impact_report'), desc: t('nav_desc.impact_report'), href: tenantPath('/impact-report'), icon: BarChart3 },
        { label: t('nav.strategic_plan'), desc: t('nav_desc.strategic_plan'), href: tenantPath('/strategic-plan'), icon: Compass },
      ],
    }] : []),
    ...(hasFeature('federation') ? [{
      key: 'federation',
      title: t('sections.partner_communities'),
      collapsible: true,
      defaultExpanded: false,
      items: [
        { label: t('nav.federation_hub'), desc: t('nav_desc.federation_hub'), href: tenantPath('/federation'), icon: Globe },
        { label: t('nav.partner_communities'), desc: t('nav_desc.partner_communities'), href: tenantPath('/federation/partners'), icon: Building2 },
        { label: t('nav.federated_members'), desc: t('nav_desc.federated_members'), href: tenantPath('/federation/members'), icon: Users },
        { label: t('nav.federated_messages'), desc: t('nav_desc.federated_messages'), href: tenantPath('/federation/messages'), icon: MessageSquare },
        { label: t('nav.federated_listings'), desc: t('nav_desc.federated_listings'), href: tenantPath('/federation/listings'), icon: ListTodo },
        { label: t('nav.federated_events'), desc: t('nav_desc.federated_events'), href: tenantPath('/federation/events'), icon: Calendar },
        { label: t('nav.federation_settings'), desc: t('nav_desc.federation_settings'), href: tenantPath('/federation/settings'), icon: Settings },
      ],
    }] : []),
  ], [t, tenantPath, isHourTimebank, hasFeature, tenant?.menu_pages?.about, gateFilter]);

  const timebankingPaths = useMemo(() => timebankingItems.map(i => i.href), [timebankingItems]);
  const communityPaths = useMemo(() => communityItems.map(i => i.href), [communityItems]);
  const morePaths = useMemo(() => [
    ...leftSections.flatMap(s => s.items.map(i => i.href)),
    ...rightSections.flatMap(s => s.items.map(i => i.href)),
  ], [leftSections, rightSections]);

  return (
    <>
      {/* Skip to content link — accessible to keyboard/screen reader users */}
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[500] focus:px-4 focus:py-2 focus:bg-indigo-600 focus:text-white focus:rounded-lg focus:text-sm focus:font-medium"
      >
        {t('accessibility.skip_to_content', 'Skip to main content')}
      </a>

      <header className={`fixed top-0 left-0 right-0 z-300 backdrop-blur-xl border-b border-theme-default glass-surface overflow-x-clip transition-transform duration-200 ${isMobileMenuOpen ? '-translate-y-full md:translate-y-0' : ''}`} style={{ paddingTop: 'env(safe-area-inset-top, 0px)' }}>
        {/* Development status banner — inside fixed header so it's always visible */}
        <DevelopmentStatusBanner />

        {/* Utility Bar — slim top strip, auto-hides on scroll down */}
        <div
          className={`hidden sm:block border-b border-[var(--border-default)] bg-[var(--surface-elevated)] transition-all duration-200 overflow-hidden ${
            isUtilityBarVisible ? 'max-h-8 opacity-100' : 'max-h-0 opacity-0 border-b-0'
          }`}
        >
          <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex items-center justify-end gap-1 h-8 flex-nowrap overflow-x-auto">
              {/* Dashboard — authenticated users */}
              {isAuthenticated && hasModule('dashboard') && (
                <Button
                  variant="light"
                  size="sm"
                  className="text-theme-muted hover:text-theme-primary h-7 min-w-0 px-2 gap-1 text-xs shrink-0"
                  onPress={() => navigate(tenantPath('/dashboard'))}
                >
                  <LayoutDashboard className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                  <span className="hidden md:inline">{t('nav.dashboard')}</span>
                </Button>
              )}
              {/* Help Center — authenticated users */}
              {isAuthenticated && (
                <Button
                  variant="light"
                  size="sm"
                  className="text-theme-muted hover:text-theme-primary h-7 min-w-0 px-2 gap-1 text-xs shrink-0"
                  onPress={() => navigate(tenantPath('/help'))}
                >
                  <HelpCircle className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                  <span className="hidden md:inline">{t('user_menu.help_center')}</span>
                </Button>
              )}
              {/* Federation Hub — authenticated users with federation feature */}
              {isAuthenticated && hasFeature('federation') && (
                <Button
                  variant="light"
                  size="sm"
                  className="text-theme-muted hover:text-theme-primary h-7 min-w-0 px-2 gap-1 text-xs shrink-0"
                  onPress={() => navigate(tenantPath('/federation'))}
                >
                  <Globe className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                  <span className="hidden md:inline">{t('nav.partner_communities')}</span>
                </Button>
              )}
              {/* Identity verification status */}
              {isAuthenticated && idVerifiedLoaded && (
                <>
                  <span className="text-[var(--border-default)] text-xs select-none shrink-0">|</span>
                  {isIdVerified ? (
                    <div className="flex items-center gap-1 px-2 h-7 text-xs text-emerald-600 dark:text-emerald-400 shrink-0">
                      <ShieldCheck className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                      <span className="hidden md:inline">Verified</span>
                    </div>
                  ) : (
                    <Button
                      variant="light"
                      size="sm"
                      className="text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300 h-7 min-w-0 px-2 gap-1 text-xs shrink-0 font-semibold"
                      onPress={() => navigate(tenantPath('/verify-identity-optional'))}
                    >
                      <Fingerprint className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                      <span className="hidden md:inline">Verify Identity</span>
                    </Button>
                  )}
                </>
              )}
              {/* Admin links — admin users only */}
              {isAuthenticated && isAdmin && (
                <>
                  <span className="text-[var(--border-default)] text-xs select-none shrink-0">|</span>
                  <Button
                    variant="light"
                    size="sm"
                    className="text-theme-muted hover:text-theme-primary h-7 min-w-0 px-2 gap-1 text-xs shrink-0"
                    onPress={() => navigate(tenantPath('/admin'))}
                    aria-label={t('user_menu.admin_panel')}
                  >
                    <Shield className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                    <span className="hidden md:inline">{t('user_menu.admin_panel')}</span>
                  </Button>
                </>
              )}
              {isAuthenticated && <span className="text-[var(--border-default)] text-xs select-none shrink-0">|</span>}
              <LanguageSwitcher />
              <Button
                isIconOnly
                variant="light"
                size="sm"
                className="text-theme-muted hover:text-theme-primary w-7 h-7 min-w-7 shrink-0"
                onPress={toggleTheme}
                aria-label={`Switch to ${resolvedTheme === 'dark' ? 'light' : 'dark'} mode`}
              >
                {resolvedTheme === 'dark' ? (
                  <Sun className="w-3.5 h-3.5 text-amber-400" aria-hidden="true" />
                ) : (
                  <Moon className="w-3.5 h-3.5 text-indigo-500" aria-hidden="true" />
                )}
              </Button>
              <span className="text-[var(--border-default)] text-xs select-none shrink-0">|</span>
              {/* Search — in utility bar on desktop */}
              <Button
                variant="light"
                size="sm"
                onPress={() => setIsSearchOpen(true)}
                aria-label={t('accessibility.search_ctrl_k')}
                className="flex items-center gap-1 px-2 h-7 min-w-0 text-theme-muted hover:text-theme-primary text-xs shrink-0"
              >
                <Search className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                <span className="hidden md:inline">Search</span>
                <kbd className="hidden lg:inline-flex items-center gap-0.5 ml-0.5 px-1 py-0 rounded bg-theme-hover/60 text-[10px] font-medium text-theme-subtle">
                  <span className="text-xs">⌘</span>K
                </kbd>
              </Button>
            </div>
          </div>
        </div>

        {/* Main Navigation Bar */}
        <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-14 sm:h-16">
            {/* Left Section: Mobile Menu + Brand */}
            <div className="flex items-center gap-2 sm:gap-3">
              {/* Mobile Menu Toggle — guests only (authenticated users use MobileTabBar) */}
              {!isAuthenticated && (
                <Button
                  isIconOnly
                  variant="light"
                  size="sm"
                  className="lg:hidden text-theme-muted hover:text-theme-primary min-w-[44px] min-h-[44px]"
                  onPress={onMobileMenuOpen}
                  aria-label={t('accessibility.open_menu')}
                >
                  <Menu className="w-5 h-5" aria-hidden="true" />
                </Button>
              )}

              {/* Brand — shrinks when scrolled */}
              <TenantLogo size="md" showName compact={isScrolled} />
            </div>

            {/* Desktop Navigation — uses ResizeObserver for smart collapsing */}
            <nav ref={navRef} className="hidden lg:flex items-center gap-1 flex-1 justify-center min-w-0" aria-label={t('aria.main_navigation')}>
              {hasCustomMenus ? (
                <DesktopMenuItems menus={headerMenus} />
              ) : (
              <>
              {/* Primary nav items — collapse into More when space is tight */}
              {hasModule('feed') && maxVisibleNav >= 1 && (
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
                  <span>{t('nav.feed')}</span>
                </NavLink>
              )}

              {/* Explore / Discover */}
              {maxVisibleNav >= 2 && (
                <NavLink
                  to={tenantPath('/explore')}
                  className={({ isActive }) =>
                    `flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                      isActive
                        ? 'bg-theme-active text-theme-primary'
                        : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                    }`
                  }
                >
                  <Compass className="w-4 h-4" aria-hidden="true" />
                  <span>{t('nav.explore', 'Explore')}</span>
                </NavLink>
              )}

              {/* Timebanking Dropdown — replaces top-level Listings link */}
              {timebankingItems.length > 0 && maxVisibleNav >= 3 && (
                <Dropdown placement="bottom-start" isOpen={timebankingOpen} onOpenChange={handleTimebankingOpenChange} shouldBlockScroll={false}>
                  <DropdownTrigger>
                    <Button
                      variant="light"
                      size="sm"
                      className={`flex items-center gap-1 px-3 py-2 text-sm font-medium transition-all ${
                        isActiveGroup(timebankingPaths)
                          ? 'bg-theme-active text-theme-primary'
                          : 'text-theme-muted hover:text-theme-primary hover:bg-theme-hover'
                      }`}
                      endContent={<ChevronDown className="w-3 h-3" aria-hidden="true" />}
                    >
                      <ArrowRightLeft className="w-4 h-4" aria-hidden="true" />
                      {t('nav.timebanking', 'Timebanking')}
                    </Button>
                  </DropdownTrigger>
                  <DropdownMenu
                    aria-label={t('aria.timebanking_navigation')}
                    className="min-w-[220px]"
                    classNames={{
                      base: 'bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-xl max-h-[70vh] overflow-y-auto',
                    }}
                    onAction={(key) => {
                      dropdownNavigate(String(key));
                    }}
                  >
                    {timebankingItems.map((item) => (
                      <DropdownItem
                        key={item.href}
                        description={item.desc}
                        startContent={<item.icon className="w-4 h-4" aria-hidden="true" />}
                        className={location.pathname.startsWith(item.href) ? 'bg-theme-active' : ''}
                      >
                        {item.label}
                      </DropdownItem>
                    ))}
                  </DropdownMenu>
                </Dropdown>
              )}

              {hasModule('messages') && maxVisibleNav >= 4 && (
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
                  <span>{t('nav.messages')}</span>
                  {counts.messages > 0 && isAuthenticated && (
                    <span className="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold bg-red-500 text-white rounded-full">
                      {counts.messages > 99 ? '99+' : counts.messages}
                    </span>
                  )}
                </NavLink>
              )}

              {/* Community Dropdown — always visible on desktop */}
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
                      {t('nav.community')}
                    </Button>
                  </DropdownTrigger>
                  <DropdownMenu
                    aria-label={t('aria.community_navigation')}
                    className="min-w-[220px]"
                    classNames={{
                      base: 'bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-xl max-h-[70vh] overflow-y-auto',
                    }}
                    onAction={(key) => {
                      dropdownNavigate(String(key));
                    }}
                  >
                    {communityItems.map((item) => (
                      <DropdownItem
                        key={item.href}
                        description={item.desc}
                        startContent={<item.icon className="w-4 h-4" aria-hidden="true" />}
                        className={location.pathname.startsWith(item.href) ? 'bg-theme-active' : ''}
                      >
                        {item.label}
                      </DropdownItem>
                    ))}
                  </DropdownMenu>
                </Dropdown>
              )}

              {/* More — Multi-column mega menu */}
              <MegaMenu
                isOpen={moreOpen}
                onOpenChange={handleMoreOpenChange}
                isActive={isActiveGroup(morePaths)}
                leftSections={leftSections}
                rightSections={rightSections}
                onNavigate={dropdownNavigate}
              />
              </>
              )}
            </nav>

            {/* User Actions */}
            <div className="flex items-center gap-1 sm:gap-2">
              {/* Search — visible on mobile/tablet where utility bar is hidden */}
              <Button
                isIconOnly
                variant="light"
                size="sm"
                onPress={() => setIsSearchOpen(true)}
                aria-label={t('accessibility.search_ctrl_k')}
                className="sm:hidden text-theme-muted hover:text-theme-primary min-w-[44px] min-h-[44px]"
              >
                <Search className="w-5 h-5" aria-hidden="true" />
              </Button>

              {isAuthenticated ? (
                <>
                  {/* Create Button */}
                  <Dropdown placement="bottom-end" isOpen={createOpen} onOpenChange={handleCreateOpenChange} shouldBlockScroll={false}>
                    <DropdownTrigger>
                      <Button
                        isIconOnly
                        size="sm"
                        className="hidden sm:flex bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        aria-label={t('accessibility.create_new')}
                      >
                        <Plus className="w-4 h-4" aria-hidden="true" />
                      </Button>
                    </DropdownTrigger>
                    <DropdownMenu
                      aria-label={t('aria.create_actions')}
                      classNames={{
                        base: 'bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-xl max-h-[70vh] overflow-y-auto',
                      }}
                      onAction={(key) => {
                        dropdownNavigate(String(key));
                      }}
                    >
                      <DropdownItem
                        key={tenantPath('/listings/create')}
                        startContent={<ListTodo className="w-4 h-4" aria-hidden="true" />}
                      >
                        {t('create.new_listing')}
                      </DropdownItem>
                      {hasFeature('events') ? (
                        <DropdownItem
                          key={tenantPath('/events/create')}
                          startContent={<Calendar className="w-4 h-4" aria-hidden="true" />}
                        >
                          {t('create.new_event')}
                        </DropdownItem>
                      ) : null}
                    </DropdownMenu>
                  </Dropdown>

                  {/* Language Switcher — mobile only (desktop uses utility bar) */}
                  <div className="sm:hidden">
                    <LanguageSwitcher />
                  </div>

                  {/* Notification Flyout — rich popover instead of simple navigate */}
                  <NotificationFlyout />

                  {/* Status Selector (small dot button) */}
                  <StatusSelector />

                  {/* User Dropdown */}
                  <Dropdown placement="bottom-end" isOpen={userOpen} onOpenChange={handleUserOpenChange} shouldBlockScroll={false}>
                    <DropdownTrigger>
                      <div className="relative cursor-pointer">
                        <Avatar
                          as="button"
                          name={`${user?.first_name} ${user?.last_name}`}
                          src={resolveAvatarUrl(user?.avatar_url || user?.avatar)}
                          size="sm"
                          className="cursor-pointer ring-2 ring-transparent hover:ring-indigo-500/50 transition-all w-8 h-8 sm:w-9 sm:h-9"
                          showFallback
                        />
                        {user?.id && <PresenceIndicator userId={user.id} size="lg" showOffline />}
                      </div>
                    </DropdownTrigger>
                    <DropdownMenu
                      aria-label={t('aria.user_actions')}
                      classNames={{
                        base: 'bg-[var(--surface-dropdown)] border border-[var(--border-default)] shadow-xl min-w-[220px] max-h-[70vh] overflow-y-auto',
                      }}
                      onAction={(key) => {
                        const k = String(key);
                        if (k === 'theme') { toggleTheme(); closeAllDropdowns(); return; }
                        if (k === 'logout') { handleLogout(); return; }
                        if (k === 'profile-header') return;
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
                          {t('user_menu.my_profile')}
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
                            {t('user_menu.wallet')}
                          </DropdownItem>
                        ) : null}
                        <DropdownItem
                          key={tenantPath('/settings')}
                          startContent={<Settings className="w-4 h-4" aria-hidden="true" />}
                        >
                          {t('user_menu.settings')}
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
                          {resolvedTheme === 'dark' ? t('user_menu.light_mode') : t('user_menu.dark_mode')}
                        </DropdownItem>
                      </DropdownSection>

                      <DropdownSection>
                        <DropdownItem
                          key="logout"
                          color="danger"
                          startContent={<LogOut className="w-4 h-4" aria-hidden="true" />}
                          className="text-red-500 dark:text-red-400"
                        >
                          {t('user_menu.log_out')}
                        </DropdownItem>
                      </DropdownSection>
                    </DropdownMenu>
                  </Dropdown>
                </>
              ) : (
                <>
                  {/* Theme Toggle + Language Switcher — mobile only (desktop uses utility bar) */}
                  <div className="flex items-center gap-1 sm:hidden">
                    <Button
                      isIconOnly
                      variant="light"
                      size="sm"
                      className="text-theme-muted hover:text-theme-primary"
                      onPress={toggleTheme}
                      aria-label={`Switch to ${resolvedTheme === 'dark' ? 'light' : 'dark'} mode`}
                    >
                      {resolvedTheme === 'dark' ? (
                        <Sun className="w-4 h-4 text-amber-400" aria-hidden="true" />
                      ) : (
                        <Moon className="w-4 h-4 text-indigo-500" aria-hidden="true" />
                      )}
                    </Button>
                    <LanguageSwitcher />
                  </div>

                  <Link to={tenantPath('/login')}>
                    <Button variant="light" size="sm" className="text-theme-secondary hover:text-theme-primary">
                      {t('auth.log_in')}
                    </Button>
                  </Link>
                  <Link to={tenantPath('/register')}>
                    <Button size="sm" className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-medium">
                      {t('auth.sign_up')}
                    </Button>
                  </Link>
                </>
              )}
            </div>
          </div>
        </div>

        {/* Search Overlay */}
        <SearchOverlay
          isOpen={isSearchOpen}
          onClose={() => setIsSearchOpen(false)}
        />
      </header>
    </>
  );
}

export default Navbar;
