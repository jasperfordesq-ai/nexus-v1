// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';

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
        'matches': 'Matches',
        'reviews': 'Reviews',
        'listings': 'Listings',
        'marketplace': 'Marketplace',
        'marketplaceSection': 'Marketplace',
        'marketplaceBrowse': 'Browse marketplace',
        'marketplaceSearch': 'Marketplace search',
        'marketplaceNearby': 'Nearby marketplace',
        'marketplaceFree': 'Free items',
        'marketplaceSell': 'Sell something',
        'marketplaceMyListings': 'My marketplace listings',
        'marketplaceSellerSetup': 'Seller setup',
        'marketplaceOrders': 'Marketplace orders',
        'marketplaceSales': 'Sales orders',
        'marketplacePickups': 'Marketplace pickups',
        'marketplaceShipping': 'Shipping options',
        'marketplaceCoupons': 'Marketplace coupons',
        'marketplaceOffers': 'Marketplace offers',
        'marketplaceSaved': 'Saved marketplace',
        'marketplaceSellerCoupons': 'Seller coupons',
        'marketplacePickupTools': 'Pickup tools',
        'marketplacePromotions': 'Promotions',
        'marketplaceSavedSearches': 'Saved searches',
        'marketplaceTools': 'Seller tools',
        'marketplacePayments': 'Seller payments',
        'signOut': 'Sign out',
        'signOutConfirmTitle': 'Sign out',
        'signOutConfirmMessage': 'Are you sure you want to sign out?',
        'hrs': 'hrs',
        'search': 'Search',
        'jobs': 'Jobs',
        'groups': 'Groups',
        'groupExchanges': 'Group exchanges',
        'events': 'Events',
        'aiChat': 'AI Assistant',
        'achievements': 'Achievements',
        'myGoals': 'My Goals',
        'volunteering': 'Volunteering',
        'organisations': 'Organisations',
        'blog': 'Blog',
        'skills': 'Skills & Endorsements',
        'federation': 'Federation',
        'federationSection': 'Partner communities',
        'federationHub': 'Federation hub',
        'federationPartners': 'Partner communities',
        'federationMembers': 'Federated members',
        'federationConnections': 'Federation connections',
        'federationMessages': 'Federated messages',
        'federationListings': 'Federated listings',
        'federationGroups': 'Federated groups',
        'federationEvents': 'Federated events',
        'federationSettings': 'Federation settings',
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
        'navDescriptions.matches': 'Review recommended listings, jobs, volunteering, and groups.',
        'navDescriptions.reviews': 'Review feedback you have received and exchanges waiting for a review.',
        'navDescriptions.achievements': 'Badges, levels, streaks, and community progress.',
        'navDescriptions.myGoals': 'Track personal goals and timebank milestones.',
        'navDescriptions.groups': 'Your community spaces and group conversations.',
        'navDescriptions.groupExchanges': 'Review multi-person time exchanges, splits, confirmations, and completion status.',
        'navDescriptions.search': 'Search listings, members, events, groups, and posts.',
        'navDescriptions.listings': 'Browse offers, requests, and timebank exchanges.',
        'navDescriptions.marketplace': 'Buy, sell, save, and manage community marketplace listings.',
        'navDescriptions.marketplaceBrowse': 'Browse all marketplace listings, categories, maps, and saved searches.',
        'navDescriptions.marketplaceSearch': 'Search marketplace listings by keyword, category, delivery method, and price.',
        'navDescriptions.marketplaceNearby': 'Find marketplace listings around you on the map.',
        'navDescriptions.marketplaceFree': 'Browse free community items and giveaway listings.',
        'navDescriptions.marketplaceSell': 'Create a new marketplace listing with photos, price, delivery, and category details.',
        'navDescriptions.marketplaceMyListings': 'Review, edit, and manage the marketplace items you have listed.',
        'navDescriptions.marketplaceSellerSetup': 'Create or update the seller profile used by marketplace buyers.',
        'navDescriptions.marketplaceOrders': 'Track marketplace purchases, sales, payments, pickup, delivery, ratings, and disputes.',
        'navDescriptions.marketplaceSales': 'Manage orders placed with you, including fulfillment, pickup, delivery, and buyer updates.',
        'navDescriptions.marketplacePickups': 'Show pickup reservations and QR codes for click-and-collect orders.',
        'navDescriptions.marketplaceShipping': 'Review postage, collection, local delivery, and community delivery options.',
        'navDescriptions.marketplaceCoupons': 'Browse active merchant coupons and show checkout QR codes.',
        'navDescriptions.marketplaceOffers': 'Review offers you have made or received on marketplace listings.',
        'navDescriptions.marketplaceSaved': 'Open your saved marketplace collections and saved search alerts.',
        'navDescriptions.marketplaceSellerCoupons': 'Create, edit, and review redemptions for your seller coupons.',
        'navDescriptions.marketplacePickupTools': 'Manage pickup slots, scan pickup QR codes, and review reservations.',
        'navDescriptions.marketplacePromotions': 'Promote active listings and review marketplace campaign metrics.',
        'navDescriptions.marketplaceSavedSearches': 'Create saved marketplace searches and manage alert preferences.',
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
        'navDescriptions.federationHub': 'Review federation status, partners, activity, and quick links.',
        'navDescriptions.federationPartners': 'Browse partner communities connected to your timebank.',
        'navDescriptions.federationMembers': 'Find members across connected communities.',
        'navDescriptions.federationConnections': 'Manage cross-community connection requests.',
        'navDescriptions.federationMessages': 'Open conversations with members in partner communities.',
        'navDescriptions.federationListings': 'Browse federated offers and requests.',
        'navDescriptions.federationGroups': 'Explore groups shared by partner communities.',
        'navDescriptions.federationEvents': 'Discover events from connected communities.',
        'navDescriptions.federationSettings': 'Review federation visibility and sharing preferences.',
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
const mockHasFeature = jest.fn<boolean, [string]>(() => true);
const mockHasModule = jest.fn<boolean, [string]>(() => true);

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
  useTenant: () => ({
    hasFeature: (feature: string) => mockHasFeature(feature),
    hasModule: (module: string) => mockHasModule(module),
  }),
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
import { router } from 'expo-router';

describe('MoreScreen (More tab)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUseAuth.mockReturnValue(defaultAuthState);
    mockHasFeature.mockReturnValue(true);
    mockHasModule.mockReturnValue(true);
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
    expect(getByText('Reviews')).toBeTruthy();
    expect(getByText('Achievements')).toBeTruthy();
    expect(getByText('My Goals')).toBeTruthy();
    expect(getByText('Groups')).toBeTruthy();
    expect(getByText('Group exchanges')).toBeTruthy();
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
    expect(getByText('Marketplace search')).toBeTruthy();
    expect(getByText('Nearby marketplace')).toBeTruthy();
    expect(getByText('Free items')).toBeTruthy();
    expect(getByText('Sell something')).toBeTruthy();
    expect(getByText('My marketplace listings')).toBeTruthy();
    expect(getByText('Seller setup')).toBeTruthy();
    expect(getByText('Marketplace orders')).toBeTruthy();
    expect(getByText('Sales orders')).toBeTruthy();
    expect(getByText('Marketplace pickups')).toBeTruthy();
    expect(getByText('Shipping options')).toBeTruthy();
    expect(getByText('Marketplace coupons')).toBeTruthy();
    expect(getByText('Marketplace offers')).toBeTruthy();
    expect(getByText('Saved marketplace')).toBeTruthy();
    expect(getByText('Seller coupons')).toBeTruthy();
    expect(getByText('Pickup tools')).toBeTruthy();
    expect(getByText('Promotions')).toBeTruthy();
    expect(getByText('Saved searches')).toBeTruthy();
    expect(getByText('Seller tools')).toBeTruthy();
    expect(getByText('Seller payments')).toBeTruthy();
  });

  it('renders direct federation shortcuts in the partner communities section', () => {
    const { getAllByText, getByText } = render(<MoreScreen />);

    expect(getAllByText('Partner communities').length).toBeGreaterThanOrEqual(2);
    expect(getByText('Federation hub')).toBeTruthy();
    expect(getByText('Federated members')).toBeTruthy();
    expect(getByText('Federation connections')).toBeTruthy();
    expect(getByText('Federated messages')).toBeTruthy();
    expect(getByText('Federated listings')).toBeTruthy();
    expect(getByText('Federated groups')).toBeTruthy();
    expect(getByText('Federated events')).toBeTruthy();
    expect(getByText('Federation settings')).toBeTruthy();
  });

  it('opens federated members directly from the partner communities shortcuts', () => {
    const { getByLabelText } = render(<MoreScreen />);

    fireEvent.press(getByLabelText('Federated members'));

    expect(router.push).toHaveBeenCalledWith('/(modals)/federation-members');
  });

  it('opens the seller sales orders view from Marketplace shortcuts', () => {
    const { getByLabelText } = render(<MoreScreen />);

    fireEvent.press(getByLabelText('Sales orders'));

    expect(router.push).toHaveBeenCalledWith({
      pathname: '/(modals)/marketplace-orders',
      params: { mode: 'sales' },
    });
  });

  it('opens seller promotion tools from Marketplace shortcuts', () => {
    const { getByLabelText } = render(<MoreScreen />);

    fireEvent.press(getByLabelText('Promotions'));

    expect(router.push).toHaveBeenCalledWith({
      pathname: '/(modals)/marketplace-tools',
      params: { tab: 'promotions' },
    });
  });

  it('opens saved search tools from Marketplace shortcuts', () => {
    const { getByLabelText } = render(<MoreScreen />);

    fireEvent.press(getByLabelText('Saved searches'));

    expect(router.push).toHaveBeenCalledWith({
      pathname: '/(modals)/marketplace-tools',
      params: { tab: 'savedSearches' },
    });
  });

  it('renders Settings in the Account section', () => {
    const { getAllByText, getByText } = render(<MoreScreen />);
    expect(getAllByText('Account').length).toBeGreaterThanOrEqual(1);
    expect(getByText('Settings')).toBeTruthy();
  });

  it('hides More menu buttons when their backend module is disabled', () => {
    mockHasModule.mockImplementation((module: string) => !['wallet', 'messages', 'notifications', 'listings', 'settings'].includes(module));

    const { getAllByText, queryByText } = render(<MoreScreen />);

    expect(queryByText('Wallet')).toBeNull();
    expect(queryByText('Messages')).toBeNull();
    expect(queryByText('Notifications')).toBeNull();
    expect(queryByText('Listings')).toBeNull();
    expect(queryByText('Settings')).toBeNull();
    expect(getAllByText('Marketplace').length).toBeGreaterThanOrEqual(1);
  });

  it('hides More menu buttons when their backend feature is disabled', () => {
    mockHasFeature.mockImplementation((feature: string) => !['marketplace', 'events', 'groups', 'volunteering', 'blog', 'ai_chat', 'federation'].includes(feature));

    const { queryByText } = render(<MoreScreen />);

    expect(queryByText('Marketplace')).toBeNull();
    expect(queryByText('Events')).toBeNull();
    expect(queryByText('Groups')).toBeNull();
    expect(queryByText('Volunteering')).toBeNull();
    expect(queryByText('Blog')).toBeNull();
    expect(queryByText('AI Assistant')).toBeNull();
    expect(queryByText('Federation')).toBeNull();
    expect(queryByText('Wallet')).toBeTruthy();
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
