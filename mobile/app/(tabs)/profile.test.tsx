// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({}),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'timeBalance': 'Time balance',
        'hubEyebrow': 'Your timebank space',
        'balanceLabel': `${String(opts?.balance ?? '')} hrs · Time balance`,
        'viewWallet': 'View wallet',
        'editProfile': 'Edit profile',
        'browseMembers': 'Browse Members',
        'settings': 'Settings',
        'wallet': 'Wallet',
        'messages': 'Messages',
        'notifications': 'Notifications',
        'listings': 'Listings',
        'marketplace': 'Marketplace',
        'marketplaceSection': 'Marketplace',
        'marketplaceBrowse': 'Browse marketplace',
        'marketplaceSell': 'Sell something',
        'marketplaceMyListings': 'My marketplace listings',
        'marketplaceOrders': 'Marketplace orders',
        'marketplacePickups': 'Marketplace pickups',
        'marketplaceCoupons': 'Marketplace coupons',
        'marketplaceOffers': 'Marketplace offers',
        'marketplaceSaved': 'Saved marketplace',
        'marketplaceSellerCoupons': 'Seller coupons',
        'marketplacePickupTools': 'Pickup tools',
        'marketplaceTools': 'Seller tools',
        'marketplacePayments': 'Seller payments',
        'signOut': 'Sign out',
        'signOutConfirmTitle': 'Sign out',
        'signOutConfirmMessage': 'Are you sure you want to sign out?',
        'hrs': 'hrs',
        'search': 'Search',
        'jobs': 'Jobs',
        'groups': 'Groups',
        'events': 'Events',
        'aiChat': 'AI Assistant',
        'achievements': 'Achievements',
        'myGoals': 'My Goals',
        'volunteering': 'Volunteering',
        'organisations': 'Organisations',
        'blog': 'Blog',
        'skills': 'Skills & Endorsements',
        'federation': 'Federation',
        'myProfile': 'My Profile',
        'mySpace': 'My Space',
        'discover': 'Discover',
        'account': 'Account',
        'quickStats.trust': 'Trust status',
        'quickStats.network': 'Network areas',
        'quickStats.account': 'Account',
        'quickStats.active': 'Active',
        'quickStats.ready': 'Ready',
        'navDescriptions.myProfile': 'View your public profile and federation visibility.',
        'navDescriptions.wallet': 'Review your balance, transactions, and time credit history.',
        'navDescriptions.messages': 'Open conversations and federation messages.',
        'navDescriptions.notifications': 'Review recent alerts and mark updates as read.',
        'navDescriptions.achievements': 'Badges, levels, streaks, and community progress.',
        'navDescriptions.myGoals': 'Track personal goals and timebank milestones.',
        'navDescriptions.groups': 'Your community spaces and group conversations.',
        'navDescriptions.search': 'Search listings, members, events, groups, and posts.',
        'navDescriptions.listings': 'Browse offers, requests, and timebank exchanges.',
        'navDescriptions.marketplace': 'Buy, sell, save, and manage community marketplace listings.',
        'navDescriptions.marketplaceBrowse': 'Browse all marketplace listings, categories, maps, and saved searches.',
        'navDescriptions.marketplaceSell': 'Create a new marketplace listing with photos, price, delivery, and category details.',
        'navDescriptions.marketplaceMyListings': 'Review, edit, and manage the marketplace items you have listed.',
        'navDescriptions.marketplaceOrders': 'Track marketplace purchases, sales, payments, pickup, delivery, ratings, and disputes.',
        'navDescriptions.marketplacePickups': 'Show pickup reservations and QR codes for click-and-collect orders.',
        'navDescriptions.marketplaceCoupons': 'Browse active merchant coupons and show checkout QR codes.',
        'navDescriptions.marketplaceOffers': 'Review offers you have made or received on marketplace listings.',
        'navDescriptions.marketplaceSaved': 'Open your saved marketplace collections and saved search alerts.',
        'navDescriptions.marketplaceSellerCoupons': 'Create, edit, and review redemptions for your seller coupons.',
        'navDescriptions.marketplacePickupTools': 'Manage pickup slots, scan pickup QR codes, and review reservations.',
        'navDescriptions.marketplaceTools': 'Manage seller tools, promotions, pickup slots, collections, searches, and coupons.',
        'navDescriptions.marketplacePayments': 'Set up or review Stripe Connect seller payments.',
        'navDescriptions.jobs': 'Browse job vacancies, applications, and your own postings.',
        'navDescriptions.events': 'Browse workshops, meetups, and community gatherings.',
        'navDescriptions.browseMembers': 'Find neighbours by name, profile, or shared interests.',
        'navDescriptions.volunteering': 'Discover opportunities, hours, and applications.',
        'navDescriptions.organisations': 'Browse local partners and community organisations.',
        'navDescriptions.blog': 'Read community news, stories, and platform updates.',
        'navDescriptions.skills': 'Manage your skills and member endorsements.',
        'navDescriptions.aiChat': 'Ask the assistant for help finding your way around.',
        'navDescriptions.federation': 'Explore partner communities and cross-timebank tools.',
        'navDescriptions.settings': 'Security, notifications, preferences, and account controls.',
        'common:buttons.cancel': 'Cancel',
        'common:attribution': 'Project NEXUS is open-source software licensed under AGPL-3.0-or-later.',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

const mockLogout = jest.fn();
const mockUseAuth = jest.fn();

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: (...args: unknown[]) => mockUseAuth(...args),
}));

const defaultAuthState = {
  user: {
    id: 1,
    email: 'alice@example.com',
    name: 'Alice Smith',
    avatar_url: null,
    balance: 4.5,
  },
  displayName: 'Alice Smith',
  logout: mockLogout,
  refreshUser: jest.fn(),
};

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006FEE',
  useTenant: () => ({ hasFeature: () => true }),
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    surface: '#f8f9fa',
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
  }),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
  NotificationFeedbackType: { Success: 'success', Warning: 'warning', Error: 'error' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/components/ui/Avatar', () => () => null);
jest.mock('@/components/ui/Skeleton', () => ({
  ProfileSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
}));

// --- Tests ---

import MoreScreen from './profile';

describe('MoreScreen (More tab)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUseAuth.mockReturnValue(defaultAuthState);
  });

  it('renders the user display name and email', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText('Alice Smith')).toBeTruthy();
    expect(getByText('alice@example.com')).toBeTruthy();
  });

  it('renders the time balance chip when balance is present', () => {
    const { getByText } = render(<MoreScreen />);
    // Chip text: "{balance} hrs · Time balance"
    expect(getByText('4.5 hrs · Time balance')).toBeTruthy();
  });

  it('renders Edit Profile and View Wallet action buttons', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText('Edit profile')).toBeTruthy();
    expect(getByText('View wallet')).toBeTruthy();
  });

  it('renders My Space section with profile navigation items', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText('My Space')).toBeTruthy();
    expect(getByText('My Profile')).toBeTruthy();
    expect(getByText('Wallet')).toBeTruthy();
    expect(getByText('Messages')).toBeTruthy();
    expect(getByText('Notifications')).toBeTruthy();
    expect(getByText('Achievements')).toBeTruthy();
    expect(getByText('My Goals')).toBeTruthy();
    expect(getByText('Groups')).toBeTruthy();
  });

  it('renders Discover section with community navigation items', () => {
    const { getAllByText, getByText } = render(<MoreScreen />);
    expect(getByText('Discover')).toBeTruthy();
    expect(getByText('Search')).toBeTruthy();
    expect(getByText('Listings')).toBeTruthy();
    expect(getAllByText('Marketplace').length).toBeGreaterThanOrEqual(1);
    expect(getByText('Jobs')).toBeTruthy();
    expect(getByText('Events')).toBeTruthy();
    expect(getByText('Browse Members')).toBeTruthy();
    expect(getByText('Volunteering')).toBeTruthy();
    expect(getByText('Blog')).toBeTruthy();
    expect(getByText('Skills & Endorsements')).toBeTruthy();
    expect(getByText('AI Assistant')).toBeTruthy();
  });

  it('renders the Marketplace section with seller and buyer shortcuts', () => {
    const { getAllByText, getByText } = render(<MoreScreen />);
    expect(getAllByText('Marketplace').length).toBeGreaterThanOrEqual(2);
    expect(getByText('Browse marketplace')).toBeTruthy();
    expect(getByText('Sell something')).toBeTruthy();
    expect(getByText('My marketplace listings')).toBeTruthy();
    expect(getByText('Marketplace orders')).toBeTruthy();
    expect(getByText('Marketplace pickups')).toBeTruthy();
    expect(getByText('Marketplace coupons')).toBeTruthy();
    expect(getByText('Marketplace offers')).toBeTruthy();
    expect(getByText('Saved marketplace')).toBeTruthy();
    expect(getByText('Seller coupons')).toBeTruthy();
    expect(getByText('Pickup tools')).toBeTruthy();
    expect(getByText('Seller tools')).toBeTruthy();
    expect(getByText('Seller payments')).toBeTruthy();
  });

  it('renders Settings in the Account section', () => {
    const { getAllByText, getByText } = render(<MoreScreen />);
    expect(getAllByText('Account').length).toBeGreaterThanOrEqual(1);
    expect(getByText('Settings')).toBeTruthy();
  });

  it('renders the Sign out button', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText('Sign out')).toBeTruthy();
  });

  it('renders ProfileSkeleton when user is null', () => {
    mockUseAuth.mockReturnValueOnce({
      user: null,
      displayName: '',
      logout: jest.fn(),
      refreshUser: jest.fn(),
    });

    const { queryByText } = render(<MoreScreen />);
    expect(queryByText('Alice Smith')).toBeNull();
    expect(queryByText('alice@example.com')).toBeNull();
  });

  it('renders the AGPL attribution footer', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText(/AGPL-3\.0-or-later/)).toBeTruthy();
  });
});
