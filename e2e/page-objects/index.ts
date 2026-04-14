// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Page Objects Index
 * Export all page objects for easy importing in tests
 */

export { BasePage } from './BasePage';
export { LoginPage } from './LoginPage';
export { DashboardPage } from './DashboardPage';
export { ListingsPage, CreateListingPage, ListingDetailPage } from './ListingsPage';
export { MessagesPage, MessageThreadPage, NewMessagePage, NewMessageModal } from './MessagesPage';
export { EventsPage, CreateEventPage, EventDetailPage } from './EventsPage';
export { GroupsPage, GroupDetailPage, CreateGroupPage } from './GroupsPage';
export { WalletPage, TransferPage, InsightsPage } from './WalletPage';
export { MembersPage, ProfilePage, SettingsPage } from './MembersPage';
export {
  AdminDashboardPage,
  AdminUsersPage,
  AdminListingsPage,
  AdminSettingsPage,
  AdminTimebankingPage,
} from './AdminPage';
export { SuperAdminPage } from './SuperAdminPage';
export { BrokerControlsPage } from './BrokerControlsPage';
export { FeedPage } from './FeedPage';
