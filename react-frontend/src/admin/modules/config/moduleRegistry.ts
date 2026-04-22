// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Module Registry
 * Central definition of all platform modules, their metadata, icons,
 * config sources, and granular configuration options.
 */

import type { LucideIcon } from 'lucide-react';
import ListChecks from 'lucide-react/icons/list-checks';
import Wallet from 'lucide-react/icons/wallet';
import MessageSquare from 'lucide-react/icons/message-square';
import LayoutDashboard from 'lucide-react/icons/layout-dashboard';
import Rss from 'lucide-react/icons/rss';
import Bell from 'lucide-react/icons/bell';
import UserCircle from 'lucide-react/icons/circle-user';
import Settings from 'lucide-react/icons/settings';
import Calendar from 'lucide-react/icons/calendar';
import Users from 'lucide-react/icons/users';
import Gamepad2 from 'lucide-react/icons/gamepad-2';
import Target from 'lucide-react/icons/target';
import FileText from 'lucide-react/icons/file-text';
import BookOpen from 'lucide-react/icons/book-open';
import Heart from 'lucide-react/icons/heart';
import ArrowLeftRight from 'lucide-react/icons/arrow-left-right';
import Building2 from 'lucide-react/icons/building-2';
import Globe from 'lucide-react/icons/globe';
import Link2 from 'lucide-react/icons/link-2';
import Star from 'lucide-react/icons/star';
import BarChart3 from 'lucide-react/icons/chart-column';
import Briefcase from 'lucide-react/icons/briefcase';
import Lightbulb from 'lucide-react/icons/lightbulb';
import MessageCircle from 'lucide-react/icons/message-circle';
import Repeat2 from 'lucide-react/icons/repeat-2';
import Search from 'lucide-react/icons/search';
import Brain from 'lucide-react/icons/brain';
import ShoppingBag from 'lucide-react/icons/shopping-bag';
import ShieldCheck from 'lucide-react/icons/shield-check';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export type ModuleType = 'core' | 'feature';

export type ConfigSource =
  | 'tenant_features'
  | 'tenant_modules'
  | 'broker_config'
  | 'group_config'
  | 'listing_config'
  | 'volunteering_config'
  | 'job_config'
  | 'identity_config'
  | 'group_policies'
  | 'onboarding_config'
  | 'tenant_settings'
  | 'none';

export type ConfigOptionType = 'boolean' | 'number' | 'string' | 'select';

export interface SelectChoice {
  value: string;
  label: string;
}

export interface ConfigOption {
  key: string;
  label: string;
  description: string;
  type: ConfigOptionType;
  defaultValue: boolean | number | string;
  category: string;
  comingSoon?: boolean;
  min?: number;
  max?: number;
  choices?: SelectChoice[];
}

export interface ModuleDefinition {
  id: string;
  name: string;
  description: string;
  icon: LucideIcon;
  type: ModuleType;
  configSource: ConfigSource;
  configOptions: ConfigOption[];
  /** Link to an existing dedicated admin page for this module's config */
  detailPageUrl?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Core Modules (8)
// ─────────────────────────────────────────────────────────────────────────────

const CORE_MODULES: ModuleDefinition[] = [
  {
    id: 'listings',
    name: 'Listings',
    description: 'Service offers and requests marketplace',
    icon: ListChecks,
    type: 'core',
    configSource: 'listing_config',
    configOptions: [
      // Moderation & Approval
      { key: 'listing.moderation_enabled', label: 'Require Moderation', description: 'New listings must be approved by an admin before going live', type: 'boolean', defaultValue: false, category: 'Moderation & Approval' },
      { key: 'listing.auto_approve_trusted', label: 'Auto-Approve Trusted Members', description: 'Automatically approve listings from verified/trusted members', type: 'boolean', defaultValue: false, category: 'Moderation & Approval' },
      // Listing Limits
      { key: 'listing.max_per_user', label: 'Max Listings Per User', description: 'Maximum number of active listings a member can have', type: 'number', defaultValue: 50, category: 'Listing Limits', min: 1, max: 500 },
      { key: 'listing.max_images', label: 'Max Images Per Listing', description: 'Maximum number of images that can be uploaded per listing', type: 'number', defaultValue: 5, category: 'Listing Limits', min: 1, max: 20 },
      { key: 'listing.max_image_size_mb', label: 'Max Image Size (MB)', description: 'Maximum file size for listing images in megabytes', type: 'number', defaultValue: 8, category: 'Listing Limits', min: 1, max: 50 },
      { key: 'listing.require_image', label: 'Require Image', description: 'Listings must include at least one image', type: 'boolean', defaultValue: false, category: 'Listing Limits' },
      { key: 'listing.min_title_length', label: 'Min Title Length', description: 'Minimum characters required for listing title', type: 'number', defaultValue: 5, category: 'Listing Limits', min: 1, max: 100 },
      { key: 'listing.min_description_length', label: 'Min Description Length', description: 'Minimum characters required for listing description', type: 'number', defaultValue: 20, category: 'Listing Limits', min: 1, max: 500 },
      // Types & Form Options
      { key: 'listing.allow_offers', label: 'Allow Offers', description: 'Members can create service offer listings', type: 'boolean', defaultValue: true, category: 'Types & Form Options' },
      { key: 'listing.allow_requests', label: 'Allow Requests', description: 'Members can create service request listings', type: 'boolean', defaultValue: true, category: 'Types & Form Options' },
      { key: 'listing.require_category', label: 'Require Category', description: 'Listings must be assigned to a category', type: 'boolean', defaultValue: true, category: 'Types & Form Options' },
      { key: 'listing.require_location', label: 'Require Location', description: 'Listings must include a location', type: 'boolean', defaultValue: false, category: 'Types & Form Options' },
      { key: 'listing.require_hours_estimate', label: 'Require Hours Estimate', description: 'Listings must include an estimated time', type: 'boolean', defaultValue: false, category: 'Types & Form Options' },
      { key: 'listing.enable_skill_tags', label: 'Enable Skill Tags', description: 'Allow members to add skill tags to listings', type: 'boolean', defaultValue: true, category: 'Types & Form Options' },
      { key: 'listing.enable_service_type', label: 'Enable Service Type', description: 'Show service type options (remote, in-person, hybrid)', type: 'boolean', defaultValue: true, category: 'Types & Form Options' },
      // Expiry & Renewal
      { key: 'listing.auto_expire_days', label: 'Auto-Expire (Days)', description: 'Automatically expire listings after this many days (0 = never)', type: 'number', defaultValue: 0, category: 'Expiry & Renewal', min: 0, max: 365 },
      { key: 'listing.max_renewals', label: 'Max Renewals', description: 'Maximum number of times a listing can be renewed', type: 'number', defaultValue: 12, category: 'Expiry & Renewal', min: 0, max: 50 },
      { key: 'listing.renewal_days', label: 'Renewal Duration (Days)', description: 'How many days each renewal extends the listing', type: 'number', defaultValue: 30, category: 'Expiry & Renewal', min: 7, max: 365 },
      { key: 'listing.expiry_reminders', label: 'Expiry Reminders', description: 'Send email reminders before listings expire', type: 'boolean', defaultValue: true, category: 'Expiry & Renewal' },
      // Features
      { key: 'listing.enable_featured', label: 'Featured Listings', description: 'Allow admins to feature/boost listings', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'listing.featured_duration_days', label: 'Featured Duration (Days)', description: 'Default number of days a listing stays featured', type: 'number', defaultValue: 7, category: 'Features', min: 1, max: 90 },
      { key: 'listing.enable_ai_descriptions', label: 'AI Description Generation', description: 'Allow members to generate descriptions using AI', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'listing.enable_reporting', label: 'Listing Reporting', description: 'Allow members to report inappropriate listings', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'listing.enable_favourites', label: 'Favourites/Saved Listings', description: 'Allow members to save/favourite listings', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'listing.enable_map_view', label: 'Map View', description: 'Show map view option on listings page', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'listing.enable_reciprocity', label: 'Reciprocity Matching', description: 'Show reciprocity section on listing detail (member offers/requests)', type: 'boolean', defaultValue: true, category: 'Features' },
    ],
  },
  {
    id: 'wallet',
    name: 'Wallet',
    description: 'Time credit transactions and balance',
    icon: Wallet,
    type: 'core',
    configSource: 'tenant_modules',
    configOptions: [
      { key: 'wallet.min_transfer', label: 'Minimum Transfer', description: 'Minimum time credits per transfer', type: 'number', defaultValue: 0.25, category: 'Limits', comingSoon: true, min: 0.25, max: 10 },
      { key: 'wallet.max_transfer', label: 'Maximum Transfer', description: 'Maximum time credits per single transfer', type: 'number', defaultValue: 100, category: 'Limits', comingSoon: true, min: 1, max: 1000 },
      { key: 'wallet.allow_negative_balance', label: 'Allow Negative Balance', description: 'Allow members to go into negative time credit balance', type: 'boolean', defaultValue: false, category: 'Rules', comingSoon: true },
    ],
  },
  {
    id: 'messages',
    name: 'Messages',
    description: 'Messaging system',
    icon: MessageSquare,
    type: 'core',
    configSource: 'tenant_modules',
    configOptions: [
      { key: 'messages.retention_days', label: 'Message Retention (Days)', description: 'How long to keep messages (0 = forever)', type: 'number', defaultValue: 0, category: 'Storage', comingSoon: true, min: 0, max: 3650 },
      { key: 'messages.rate_limit', label: 'Rate Limit (per hour)', description: 'Maximum messages a user can send per hour', type: 'number', defaultValue: 60, category: 'Limits', comingSoon: true, min: 5, max: 500 },
    ],
  },
  {
    id: 'dashboard',
    name: 'Dashboard',
    description: 'Member dashboard',
    icon: LayoutDashboard,
    type: 'core',
    configSource: 'tenant_modules',
    configOptions: [
      { key: 'dashboard.show_recent_activity', label: 'Show Recent Activity', description: 'Display recent activity feed on the dashboard', type: 'boolean', defaultValue: true, category: 'Layout', comingSoon: true },
      { key: 'dashboard.show_quick_actions', label: 'Show Quick Actions', description: 'Display quick action buttons on the dashboard', type: 'boolean', defaultValue: true, category: 'Layout', comingSoon: true },
    ],
  },
  {
    id: 'feed',
    name: 'Feed',
    description: 'Social activity feed',
    icon: Rss,
    type: 'core',
    configSource: 'tenant_modules',
    configOptions: [
      { key: 'feed.allow_images', label: 'Allow Images', description: 'Allow members to post images in the feed', type: 'boolean', defaultValue: true, category: 'Content', comingSoon: true },
      { key: 'feed.allow_polls', label: 'Allow Feed Polls', description: 'Allow members to create polls in feed posts', type: 'boolean', defaultValue: true, category: 'Content', comingSoon: true },
      { key: 'feed.require_moderation', label: 'Require Moderation', description: 'All feed posts must be approved before publishing', type: 'boolean', defaultValue: false, category: 'Moderation', comingSoon: true },
    ],
  },
  {
    id: 'notifications',
    name: 'Notifications',
    description: 'In-app notifications',
    icon: Bell,
    type: 'core',
    configSource: 'tenant_modules',
    configOptions: [
      { key: 'notifications.email_digest', label: 'Email Digest', description: 'Send daily digest of unread notifications via email', type: 'boolean', defaultValue: false, category: 'Delivery', comingSoon: true },
      { key: 'notifications.push_enabled', label: 'Push Notifications', description: 'Enable browser/mobile push notifications', type: 'boolean', defaultValue: true, category: 'Delivery', comingSoon: true },
    ],
  },
  {
    id: 'profile',
    name: 'Profile',
    description: 'User profiles',
    icon: UserCircle,
    type: 'core',
    configSource: 'tenant_modules',
    configOptions: [
      { key: 'profile.require_avatar', label: 'Require Avatar', description: 'Members must upload a profile photo', type: 'boolean', defaultValue: false, category: 'Requirements', comingSoon: true },
      { key: 'profile.require_bio', label: 'Require Bio', description: 'Members must write a bio before accessing the platform', type: 'boolean', defaultValue: false, category: 'Requirements', comingSoon: true },
    ],
  },
  {
    id: 'settings',
    name: 'Settings',
    description: 'User settings',
    icon: Settings,
    type: 'core',
    configSource: 'tenant_modules',
    configOptions: [],
  },
];

// ─────────────────────────────────────────────────────────────────────────────
// Optional Features (19)
// ─────────────────────────────────────────────────────────────────────────────

const FEATURE_MODULES: ModuleDefinition[] = [
  {
    id: 'events',
    name: 'Events',
    description: 'Community events with RSVPs',
    icon: Calendar,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'events.require_rsvp', label: 'Require RSVP', description: 'Members must RSVP to attend events', type: 'boolean', defaultValue: false, category: 'Attendance', comingSoon: true },
      { key: 'events.max_attendees', label: 'Default Max Attendees', description: 'Default maximum attendees per event (0 = unlimited)', type: 'number', defaultValue: 0, category: 'Limits', comingSoon: true, min: 0, max: 10000 },
      { key: 'events.allow_recurring', label: 'Allow Recurring Events', description: 'Allow organizers to create recurring event series', type: 'boolean', defaultValue: true, category: 'Features', comingSoon: true },
    ],
  },
  {
    id: 'groups',
    name: 'Groups',
    description: 'Community groups and discussions',
    icon: Users,
    type: 'feature',
    configSource: 'group_config',
    configOptions: [
      // Creation & Membership
      { key: 'allow_user_group_creation', label: 'Allow User Group Creation', description: 'Members can create their own groups', type: 'boolean', defaultValue: true, category: 'Creation & Membership' },
      { key: 'require_group_approval', label: 'Require Group Approval', description: 'New groups must be approved by an admin', type: 'boolean', defaultValue: false, category: 'Creation & Membership' },
      { key: 'max_groups_per_user', label: 'Max Groups Per User', description: 'Maximum groups a member can join', type: 'number', defaultValue: 10, category: 'Creation & Membership', min: 1, max: 100 },
      { key: 'max_members_per_group', label: 'Max Members Per Group', description: 'Maximum members per group', type: 'number', defaultValue: 500, category: 'Creation & Membership', min: 2, max: 10000 },
      { key: 'allow_private_groups', label: 'Allow Private Groups', description: 'Allow creation of private/invite-only groups', type: 'boolean', defaultValue: true, category: 'Creation & Membership' },
      { key: 'default_visibility', label: 'Default Visibility', description: 'Default visibility for new groups', type: 'select', defaultValue: 'public', category: 'Creation & Membership', choices: [{ value: 'public', label: 'Public' }, { value: 'private', label: 'Private' }] },
      // Features
      { key: 'enable_discussions', label: 'Enable Discussions', description: 'Allow group discussion threads', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'enable_feedback', label: 'Enable Feedback', description: 'Allow group feedback features', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'enable_achievements', label: 'Enable Achievements', description: 'Enable achievement tracking within groups', type: 'boolean', defaultValue: true, category: 'Features' },
      // Moderation
      { key: 'moderation_enabled', label: 'Moderation Enabled', description: 'Enable content moderation in groups', type: 'boolean', defaultValue: true, category: 'Moderation' },
      { key: 'content_filter_enabled', label: 'Content Filter', description: 'Automatically filter inappropriate content', type: 'boolean', defaultValue: false, category: 'Moderation' },
      { key: 'profanity_filter_enabled', label: 'Profanity Filter', description: 'Enable profanity filtering in group posts', type: 'boolean', defaultValue: false, category: 'Moderation' },
      { key: 'min_description_length', label: 'Min Description Length', description: 'Minimum characters for group descriptions', type: 'number', defaultValue: 10, category: 'Content', min: 0, max: 1000 },
      // Tab Visibility — control which tabs appear in group detail view
      { key: 'tab_feed', label: 'Feed Tab', description: 'Show the activity feed tab in groups', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_discussion', label: 'Discussion Tab', description: 'Show the threaded discussion tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_members', label: 'Members Tab', description: 'Show the members list tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_events', label: 'Events Tab', description: 'Show the events/calendar tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_files', label: 'Files Tab', description: 'Show the file storage tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_announcements', label: 'Announcements Tab', description: 'Show the announcements tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_qa', label: 'Q&A Tab', description: 'Show the questions & answers tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_wiki', label: 'Wiki Tab', description: 'Show the wiki pages tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_media', label: 'Gallery Tab', description: 'Show the image gallery tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_chatrooms', label: 'Channels Tab', description: 'Show the real-time chat channels tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_tasks', label: 'Tasks Tab', description: 'Show the task/project management tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_challenges', label: 'Challenges Tab', description: 'Show the challenges/competitions tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_analytics', label: 'Analytics Tab', description: 'Show the usage analytics tab (admin only)', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'tab_subgroups', label: 'Subgroups Tab', description: 'Show the sub-group hierarchy tab', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
    ],
  },
  {
    id: 'gamification',
    name: 'Gamification',
    description: 'Badges, achievements, XP, leaderboards',
    icon: Gamepad2,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'gamification.show_leaderboard', label: 'Show Leaderboard', description: 'Display community leaderboard to members', type: 'boolean', defaultValue: true, category: 'Visibility', comingSoon: true },
      { key: 'gamification.xp_multiplier', label: 'XP Multiplier', description: 'Global XP multiplier for all activities', type: 'number', defaultValue: 1, category: 'Rewards', comingSoon: true, min: 0.1, max: 10 },
      { key: 'gamification.daily_rewards', label: 'Daily Login Rewards', description: 'Award XP for daily logins', type: 'boolean', defaultValue: true, category: 'Rewards', comingSoon: true },
    ],
  },
  {
    id: 'goals',
    name: 'Goals',
    description: 'Personal and community goals',
    icon: Target,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'goals.allow_community_goals', label: 'Community Goals', description: 'Allow creation of community-wide goals', type: 'boolean', defaultValue: true, category: 'Features', comingSoon: true },
      { key: 'goals.max_active_goals', label: 'Max Active Goals', description: 'Maximum active goals per member', type: 'number', defaultValue: 10, category: 'Limits', comingSoon: true, min: 1, max: 50 },
    ],
  },
  {
    id: 'blog',
    name: 'Blog',
    description: 'Community blog/news posts',
    icon: FileText,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'blog.allow_comments', label: 'Allow Comments', description: 'Allow members to comment on blog posts', type: 'boolean', defaultValue: true, category: 'Features', comingSoon: true },
      { key: 'blog.require_approval', label: 'Require Approval', description: 'Blog posts require admin approval', type: 'boolean', defaultValue: true, category: 'Moderation', comingSoon: true },
    ],
  },
  {
    id: 'resources',
    name: 'Resources',
    description: 'Shared resource library',
    icon: BookOpen,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'resources.allow_uploads', label: 'Allow Uploads', description: 'Allow members to upload resource files', type: 'boolean', defaultValue: true, category: 'Features', comingSoon: true },
      { key: 'resources.max_file_size_mb', label: 'Max File Size (MB)', description: 'Maximum upload file size in megabytes', type: 'number', defaultValue: 10, category: 'Limits', comingSoon: true, min: 1, max: 100 },
    ],
  },
  {
    id: 'volunteering',
    name: 'Volunteering',
    description: 'Volunteer opportunities and hours',
    icon: Heart,
    type: 'feature',
    configSource: 'volunteering_config',
    configOptions: [
      // Tab Visibility (17 tabs)
      { key: 'volunteering.tab_opportunities', label: 'Opportunities Tab', description: 'Browse and search volunteer opportunities', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_applications', label: 'Applications Tab', description: 'Track submitted applications', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_hours', label: 'Hours Tab', description: 'View logged volunteer hours and stats', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_recommended', label: 'Recommended Tab', description: 'AI-powered shift recommendations', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_certificates', label: 'Certificates Tab', description: 'Generate and view impact certificates', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_alerts', label: 'Emergency Alerts Tab', description: 'Receive and respond to emergency shift requests', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_wellbeing', label: 'Wellbeing Tab', description: 'Burnout risk detection and wellness tracking', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_credentials', label: 'Credentials Tab', description: 'Credential verification and safeguarding badges', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_waitlist', label: 'Waitlist Tab', description: 'View shift waitlist status and auto-promotion', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_swaps', label: 'Shift Swaps Tab', description: 'Manage shift swap requests between volunteers', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_group_signups', label: 'Group Sign-ups Tab', description: 'Group shift reservations with team members', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_hours_review', label: 'Hours Review Tab', description: 'Org owners approve/decline logged hours', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_expenses', label: 'Expenses Tab', description: 'Submit and track expense claims', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_safeguarding', label: 'Safeguarding Tab', description: 'Safeguarding incident reporting', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_community_projects', label: 'Community Projects Tab', description: 'Community-proposed projects with voting', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_donations', label: 'Donations Tab', description: 'Make and view donations', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      { key: 'volunteering.tab_accessibility', label: 'Accessibility Tab', description: 'Accessibility needs declaration', type: 'boolean', defaultValue: true, category: 'Tab Visibility' },
      // Shifts & Applications
      { key: 'volunteering.swap_requires_admin', label: 'Swap Requires Admin', description: 'Shift swaps require admin approval before taking effect', type: 'boolean', defaultValue: false, category: 'Shifts & Applications' },
      { key: 'volunteering.auto_approve_applications', label: 'Auto-Approve Applications', description: 'Automatically approve volunteer applications', type: 'boolean', defaultValue: false, category: 'Shifts & Applications' },
      { key: 'volunteering.require_org_note_on_decline', label: 'Require Note on Decline', description: 'Organisations must provide a reason when declining applications', type: 'boolean', defaultValue: false, category: 'Shifts & Applications' },
      { key: 'volunteering.cancellation_deadline_hours', label: 'Cancellation Deadline (Hours)', description: 'How many hours before a shift volunteers can cancel', type: 'number', defaultValue: 24, category: 'Shifts & Applications', min: 0, max: 168 },
      { key: 'volunteering.max_hours_per_shift', label: 'Max Hours Per Shift', description: 'Maximum hours allowed per volunteer shift', type: 'number', defaultValue: 8, category: 'Shifts & Applications', min: 1, max: 24 },
      // Hours & Verification
      { key: 'volunteering.hours_require_verification', label: 'Hours Require Verification', description: 'Logged hours must be verified/approved before counting', type: 'boolean', defaultValue: true, category: 'Hours & Verification' },
      { key: 'volunteering.min_hours_for_certificate', label: 'Min Hours for Certificate', description: 'Minimum verified hours needed to generate a certificate', type: 'number', defaultValue: 1, category: 'Hours & Verification', min: 1, max: 100 },
      // Emergency Alerts
      { key: 'volunteering.alert_default_expiry_hours', label: 'Alert Expiry (Hours)', description: 'Default expiry time for emergency alerts', type: 'number', defaultValue: 24, category: 'Emergency Alerts', min: 1, max: 168 },
      { key: 'volunteering.alert_skill_matching', label: 'Skill-Based Alert Matching', description: 'Match emergency alerts to volunteers based on skills', type: 'boolean', defaultValue: true, category: 'Emergency Alerts' },
      // Expenses
      { key: 'volunteering.expenses_enabled', label: 'Expenses Enabled', description: 'Allow volunteers to submit expense claims', type: 'boolean', defaultValue: true, category: 'Expenses' },
      { key: 'volunteering.expense_require_receipt', label: 'Require Receipt', description: 'All expense claims must include a receipt', type: 'boolean', defaultValue: false, category: 'Expenses' },
      { key: 'volunteering.expense_max_amount', label: 'Max Expense Amount', description: 'Maximum amount per expense claim', type: 'number', defaultValue: 500, category: 'Expenses', min: 1, max: 10000 },
      // Wellbeing & Safety
      { key: 'volunteering.burnout_detection', label: 'Burnout Detection', description: 'Enable burnout risk scoring and recommendations', type: 'boolean', defaultValue: true, category: 'Wellbeing & Safety' },
      { key: 'volunteering.guardian_consent_required', label: 'Guardian Consent Required', description: 'Require guardian consent for minor volunteers', type: 'boolean', defaultValue: false, category: 'Wellbeing & Safety' },
      // Features
      { key: 'volunteering.enable_qr_checkin', label: 'QR Code Check-in', description: 'Enable QR code check-in/out for shifts', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'volunteering.enable_recurring_shifts', label: 'Recurring Shifts', description: 'Allow creation of recurring shift patterns', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'volunteering.enable_reviews', label: 'Reviews', description: 'Allow volunteers to review organisations and vice versa', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'volunteering.enable_matching', label: 'Smart Matching', description: 'AI-powered volunteer-to-shift recommendations', type: 'boolean', defaultValue: true, category: 'Features' },
    ],
  },
  {
    id: 'exchange_workflow',
    name: 'Exchange Workflow',
    description: 'Structured exchange requests with broker approval',
    icon: ArrowLeftRight,
    type: 'feature',
    configSource: 'broker_config',
    detailPageUrl: '/admin/broker/configuration',
    configOptions: [
      // Messaging
      { key: 'broker_messaging_enabled', label: 'Broker Messaging', description: 'Enable broker messaging oversight', type: 'boolean', defaultValue: true, category: 'Messaging' },
      { key: 'broker_copy_all_messages', label: 'Copy All Messages', description: 'Broker receives a copy of all messages', type: 'boolean', defaultValue: false, category: 'Messaging' },
      { key: 'broker_copy_threshold_hours', label: 'Copy Threshold (Hours)', description: 'Hours threshold for broker message copies', type: 'number', defaultValue: 5, category: 'Messaging', min: 1, max: 100 },
      { key: 'new_member_monitoring_days', label: 'New Member Monitoring (Days)', description: 'Days to monitor new member messages', type: 'number', defaultValue: 30, category: 'Messaging', min: 0, max: 365 },
      { key: 'require_exchange_for_listings', label: 'Require Exchange for Listings', description: 'Require exchange workflow for all listings', type: 'boolean', defaultValue: false, category: 'Messaging' },
      // Risk Tagging
      { key: 'risk_tagging_enabled', label: 'Risk Tagging', description: 'Enable risk tagging system', type: 'boolean', defaultValue: true, category: 'Risk Tagging' },
      { key: 'auto_flag_high_risk', label: 'Auto-Flag High Risk', description: 'Automatically flag high-risk exchanges', type: 'boolean', defaultValue: true, category: 'Risk Tagging' },
      { key: 'require_approval_high_risk', label: 'Require Approval (High Risk)', description: 'High-risk exchanges require broker approval', type: 'boolean', defaultValue: false, category: 'Risk Tagging' },
      { key: 'notify_on_high_risk_match', label: 'Notify on High Risk Match', description: 'Send notification when high-risk match occurs', type: 'boolean', defaultValue: true, category: 'Risk Tagging' },
      // Exchange Workflow
      { key: 'broker_approval_required', label: 'Broker Approval Required', description: 'All exchanges require broker approval', type: 'boolean', defaultValue: true, category: 'Exchange Workflow' },
      { key: 'auto_approve_low_risk', label: 'Auto-Approve Low Risk', description: 'Automatically approve low-risk exchanges', type: 'boolean', defaultValue: false, category: 'Exchange Workflow' },
      { key: 'exchange_timeout_days', label: 'Exchange Timeout (Days)', description: 'Days before an exchange request times out', type: 'number', defaultValue: 7, category: 'Exchange Workflow', min: 1, max: 90 },
      { key: 'max_hours_without_approval', label: 'Max Hours Without Approval', description: 'Maximum hours that can be exchanged without broker approval', type: 'number', defaultValue: 5, category: 'Exchange Workflow', min: 1, max: 100 },
      { key: 'confirmation_deadline_hours', label: 'Confirmation Deadline (Hours)', description: 'Hours for exchange confirmation deadline', type: 'number', defaultValue: 48, category: 'Exchange Workflow', min: 1, max: 720 },
      { key: 'allow_hour_adjustment', label: 'Allow Hour Adjustment', description: 'Allow adjusting hours after exchange agreement', type: 'boolean', defaultValue: false, category: 'Exchange Workflow' },
      { key: 'max_hour_variance_percent', label: 'Max Hour Variance (%)', description: 'Maximum percentage variance allowed for hour adjustments', type: 'number', defaultValue: 20, category: 'Exchange Workflow', min: 0, max: 100 },
      { key: 'expiry_hours', label: 'Exchange Expiry (Hours)', description: 'Hours before pending exchange expires', type: 'number', defaultValue: 168, category: 'Exchange Workflow', min: 1, max: 720 },
      // Broker Visibility
      { key: 'broker_visible_to_members', label: 'Broker Visible to Members', description: 'Show broker identity to community members', type: 'boolean', defaultValue: false, category: 'Broker Visibility' },
      { key: 'show_broker_name', label: 'Show Broker Name', description: 'Display broker name in exchange communications', type: 'boolean', defaultValue: false, category: 'Broker Visibility' },
      { key: 'broker_contact_email', label: 'Broker Contact Email', description: 'Public contact email for the broker', type: 'string', defaultValue: '', category: 'Broker Visibility' },
      { key: 'copy_first_contact', label: 'Copy First Contact', description: 'Broker receives copy of first contact messages', type: 'boolean', defaultValue: true, category: 'Broker Visibility' },
      { key: 'copy_new_member_messages', label: 'Copy New Member Messages', description: 'Broker receives copy of new member messages', type: 'boolean', defaultValue: true, category: 'Broker Visibility' },
      { key: 'copy_high_risk_listing_messages', label: 'Copy High Risk Messages', description: 'Broker receives copy of high-risk listing messages', type: 'boolean', defaultValue: true, category: 'Broker Visibility' },
      { key: 'random_sample_percentage', label: 'Random Sample (%)', description: 'Percentage of messages randomly sampled for review', type: 'number', defaultValue: 0, category: 'Broker Visibility', min: 0, max: 100 },
      { key: 'retention_days', label: 'Retention (Days)', description: 'Days to retain broker message copies', type: 'number', defaultValue: 90, category: 'Broker Visibility', min: 1, max: 3650 },
      // Compliance
      { key: 'vetting_enabled', label: 'Vetting Enabled', description: 'Enable member vetting requirements', type: 'boolean', defaultValue: false, category: 'Compliance' },
      { key: 'insurance_enabled', label: 'Insurance Enabled', description: 'Enable insurance requirements', type: 'boolean', defaultValue: false, category: 'Compliance' },
      { key: 'enforce_vetting_on_exchanges', label: 'Enforce Vetting on Exchanges', description: 'Require vetting before participating in exchanges', type: 'boolean', defaultValue: false, category: 'Compliance' },
      { key: 'enforce_insurance_on_exchanges', label: 'Enforce Insurance on Exchanges', description: 'Require insurance before participating in exchanges', type: 'boolean', defaultValue: false, category: 'Compliance' },
      { key: 'vetting_expiry_warning_days', label: 'Vetting Expiry Warning (Days)', description: 'Days before vetting expiry to warn member', type: 'number', defaultValue: 30, category: 'Compliance', min: 1, max: 90 },
      { key: 'insurance_expiry_warning_days', label: 'Insurance Expiry Warning (Days)', description: 'Days before insurance expiry to warn member', type: 'number', defaultValue: 30, category: 'Compliance', min: 1, max: 90 },
    ],
  },
  {
    id: 'organisations',
    name: 'Organisations',
    description: 'Organization profiles and management',
    icon: Building2,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'organisations.require_approval', label: 'Require Approval', description: 'New organisations require admin approval', type: 'boolean', defaultValue: false, category: 'Moderation', comingSoon: true },
      { key: 'organisations.allow_org_wallets', label: 'Organisation Wallets', description: 'Allow organisations to have their own time credit wallets', type: 'boolean', defaultValue: true, category: 'Features', comingSoon: true },
    ],
  },
  {
    id: 'federation',
    name: 'Federation',
    description: 'Multi-community network and partnerships',
    icon: Globe,
    type: 'feature',
    configSource: 'tenant_features',
    detailPageUrl: '/admin/federation/settings',
    configOptions: [
      { key: 'federation.auto_accept_partners', label: 'Auto-Accept Partners', description: 'Automatically accept federation partnership requests', type: 'boolean', defaultValue: false, category: 'Partnerships', comingSoon: true },
      { key: 'federation.share_listings', label: 'Share Listings', description: 'Share listings across federated communities', type: 'boolean', defaultValue: true, category: 'Sharing', comingSoon: true },
      { key: 'federation.share_events', label: 'Share Events', description: 'Share events across federated communities', type: 'boolean', defaultValue: true, category: 'Sharing', comingSoon: true },
    ],
  },
  {
    id: 'connections',
    name: 'Connections',
    description: 'User connections and friend requests',
    icon: Link2,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'connections.max_connections', label: 'Max Connections', description: 'Maximum connections per member (0 = unlimited)', type: 'number', defaultValue: 0, category: 'Limits', comingSoon: true, min: 0, max: 10000 },
      { key: 'connections.require_mutual', label: 'Require Mutual Accept', description: 'Both parties must accept connection requests', type: 'boolean', defaultValue: true, category: 'Rules', comingSoon: true },
    ],
  },
  {
    id: 'reviews',
    name: 'Reviews',
    description: 'Member reviews and ratings',
    icon: Star,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'reviews.require_exchange', label: 'Require Exchange', description: 'Only allow reviews after a completed exchange', type: 'boolean', defaultValue: true, category: 'Rules', comingSoon: true },
      { key: 'reviews.allow_anonymous', label: 'Allow Anonymous', description: 'Allow anonymous reviews', type: 'boolean', defaultValue: false, category: 'Features', comingSoon: true },
    ],
  },
  {
    id: 'polls',
    name: 'Polls',
    description: 'Community polls and voting',
    icon: BarChart3,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'polls.allow_member_creation', label: 'Member Poll Creation', description: 'Allow regular members to create polls', type: 'boolean', defaultValue: true, category: 'Permissions', comingSoon: true },
      { key: 'polls.show_results_before_close', label: 'Show Results Before Close', description: 'Show poll results before the poll closes', type: 'boolean', defaultValue: false, category: 'Display', comingSoon: true },
    ],
  },
  {
    id: 'job_vacancies',
    name: 'Job Vacancies',
    description: 'Job postings and application management',
    icon: Briefcase,
    type: 'feature',
    configSource: 'job_config',
    configOptions: [
      // Tab / Page Visibility
      { key: 'jobs.tab_browse', label: 'Browse Tab', description: 'Browse all open job listings', type: 'boolean', defaultValue: true, category: 'Tab & Page Visibility' },
      { key: 'jobs.tab_saved', label: 'Saved Jobs Tab', description: 'View bookmarked/saved jobs', type: 'boolean', defaultValue: true, category: 'Tab & Page Visibility' },
      { key: 'jobs.tab_my_postings', label: 'My Postings Tab', description: 'View own job postings', type: 'boolean', defaultValue: true, category: 'Tab & Page Visibility' },
      { key: 'jobs.page_kanban', label: 'Kanban Board', description: 'Application pipeline kanban view', type: 'boolean', defaultValue: true, category: 'Tab & Page Visibility' },
      { key: 'jobs.page_analytics', label: 'Analytics Page', description: 'Job posting performance analytics', type: 'boolean', defaultValue: true, category: 'Tab & Page Visibility' },
      { key: 'jobs.page_bias_audit', label: 'Bias Audit Page', description: 'Hiring bias audit reports', type: 'boolean', defaultValue: true, category: 'Tab & Page Visibility' },
      { key: 'jobs.page_talent_search', label: 'Talent Search Page', description: 'Search for candidates', type: 'boolean', defaultValue: true, category: 'Tab & Page Visibility' },
      { key: 'jobs.page_alerts', label: 'Job Alerts Page', description: 'Job alert subscriptions', type: 'boolean', defaultValue: true, category: 'Tab & Page Visibility' },
      // Job Types & Posting Rules
      { key: 'jobs.allow_paid', label: 'Allow Paid Jobs', description: 'Allow posting paid employment positions', type: 'boolean', defaultValue: true, category: 'Job Types & Posting Rules' },
      { key: 'jobs.allow_volunteer', label: 'Allow Volunteer Jobs', description: 'Allow posting volunteer positions', type: 'boolean', defaultValue: true, category: 'Job Types & Posting Rules' },
      { key: 'jobs.allow_timebank', label: 'Allow Timebank Jobs', description: 'Allow posting timebank-compensated positions', type: 'boolean', defaultValue: true, category: 'Job Types & Posting Rules' },
      { key: 'jobs.require_salary', label: 'Require Salary', description: 'Job postings must include salary information', type: 'boolean', defaultValue: false, category: 'Job Types & Posting Rules' },
      { key: 'jobs.default_currency', label: 'Default Currency', description: 'Default currency for salary fields', type: 'select', defaultValue: 'EUR', category: 'Job Types & Posting Rules', choices: [{ value: 'EUR', label: 'EUR' }, { value: 'GBP', label: 'GBP' }, { value: 'USD', label: 'USD' }] },
      { key: 'jobs.max_postings_per_user', label: 'Max Postings Per User', description: 'Maximum active job postings per user', type: 'number', defaultValue: 20, category: 'Job Types & Posting Rules', min: 1, max: 100 },
      { key: 'jobs.default_deadline_days', label: 'Default Deadline (Days)', description: 'Default application deadline in days from posting', type: 'number', defaultValue: 30, category: 'Job Types & Posting Rules', min: 7, max: 365 },
      // Moderation
      { key: 'jobs.moderation_enabled', label: 'Require Moderation', description: 'Job postings require admin approval before going live', type: 'boolean', defaultValue: false, category: 'Moderation' },
      { key: 'jobs.spam_detection', label: 'Spam Detection', description: 'Automatically detect and flag spam job postings', type: 'boolean', defaultValue: true, category: 'Moderation' },
      { key: 'jobs.auto_approve_trusted', label: 'Auto-Approve Trusted', description: 'Automatically approve postings from trusted employers', type: 'boolean', defaultValue: false, category: 'Moderation' },
      // Applications & Pipeline
      { key: 'jobs.enable_cv_upload', label: 'CV Upload', description: 'Allow applicants to upload CVs with applications', type: 'boolean', defaultValue: true, category: 'Applications & Pipeline' },
      { key: 'jobs.require_cover_message', label: 'Require Cover Message', description: 'Applications must include a cover message', type: 'boolean', defaultValue: false, category: 'Applications & Pipeline' },
      { key: 'jobs.enable_interview_scheduling', label: 'Interview Scheduling', description: 'Enable interview slot booking system', type: 'boolean', defaultValue: true, category: 'Applications & Pipeline' },
      { key: 'jobs.enable_offers', label: 'Job Offers', description: 'Enable formal job offer management', type: 'boolean', defaultValue: true, category: 'Applications & Pipeline' },
      { key: 'jobs.enable_scorecards', label: 'Interview Scorecards', description: 'Enable interviewer evaluation scorecards', type: 'boolean', defaultValue: true, category: 'Applications & Pipeline' },
      { key: 'jobs.enable_pipeline_rules', label: 'Pipeline Automation', description: 'Enable automated application pipeline rules', type: 'boolean', defaultValue: true, category: 'Applications & Pipeline' },
      { key: 'jobs.enable_blind_hiring', label: 'Blind Hiring', description: 'Enable blind hiring mode (hide applicant identity)', type: 'boolean', defaultValue: false, category: 'Applications & Pipeline' },
      // Features
      { key: 'jobs.enable_featured', label: 'Featured Jobs', description: 'Allow admins to feature/boost job postings', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'jobs.featured_duration_days', label: 'Featured Duration (Days)', description: 'Default number of days a job stays featured', type: 'number', defaultValue: 7, category: 'Features', min: 1, max: 90 },
      { key: 'jobs.enable_ai_descriptions', label: 'AI Description Generation', description: 'Allow employers to generate descriptions using AI', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'jobs.enable_skills_matching', label: 'Skills Matching', description: 'Show skills match percentage on job listings', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'jobs.enable_referrals', label: 'Referrals', description: 'Enable job referral tracking', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'jobs.enable_templates', label: 'Job Templates', description: 'Allow employers to save and reuse job templates', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'jobs.enable_rss_feed', label: 'RSS Feed', description: 'Generate public RSS/JSON feeds for job listings', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'jobs.enable_saved_profiles', label: 'Saved Candidate Profiles', description: 'Allow applicants to save profile for quick applications', type: 'boolean', defaultValue: true, category: 'Features' },
      { key: 'jobs.enable_employer_branding', label: 'Employer Branding', description: 'Enable employer brand profile pages', type: 'boolean', defaultValue: true, category: 'Features' },
    ],
  },
  {
    id: 'ideation_challenges',
    name: 'Ideation Challenges',
    description: 'Community challenges with idea voting',
    icon: Lightbulb,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'ideation.max_votes_per_user', label: 'Max Votes Per User', description: 'Maximum votes a member can cast per challenge', type: 'number', defaultValue: 3, category: 'Voting', comingSoon: true, min: 1, max: 20 },
      { key: 'ideation.allow_comments', label: 'Allow Comments', description: 'Allow comments on submitted ideas', type: 'boolean', defaultValue: true, category: 'Features', comingSoon: true },
    ],
  },
  {
    id: 'direct_messaging',
    name: 'Direct Messaging',
    description: 'Private messaging between members',
    icon: MessageCircle,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'dm.require_connection', label: 'Require Connection', description: 'Only allow DMs between connected members', type: 'boolean', defaultValue: false, category: 'Rules', comingSoon: true },
      { key: 'dm.rate_limit_per_hour', label: 'Rate Limit (per hour)', description: 'Maximum direct messages per hour', type: 'number', defaultValue: 30, category: 'Limits', comingSoon: true, min: 1, max: 200 },
    ],
  },
  {
    id: 'group_exchanges',
    name: 'Group Exchanges',
    description: 'Multi-party group exchange sessions',
    icon: Repeat2,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'group_exchanges.min_participants', label: 'Min Participants', description: 'Minimum participants for a group exchange', type: 'number', defaultValue: 3, category: 'Rules', comingSoon: true, min: 2, max: 50 },
      { key: 'group_exchanges.max_participants', label: 'Max Participants', description: 'Maximum participants per group exchange', type: 'number', defaultValue: 20, category: 'Rules', comingSoon: true, min: 2, max: 100 },
    ],
  },
  {
    id: 'search',
    name: 'Search',
    description: 'Full-text search across listings, members, and content',
    icon: Search,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'search.include_members', label: 'Include Members', description: 'Include member profiles in search results', type: 'boolean', defaultValue: true, category: 'Scope', comingSoon: true },
      { key: 'search.include_events', label: 'Include Events', description: 'Include events in search results', type: 'boolean', defaultValue: true, category: 'Scope', comingSoon: true },
    ],
  },
  {
    id: 'ai_chat',
    name: 'AI Assistant',
    description: 'AI-powered chat assistant for members',
    icon: Brain,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'ai_chat.daily_message_limit', label: 'Daily Message Limit', description: 'Maximum AI chat messages per user per day (0 = unlimited)', type: 'number', defaultValue: 50, category: 'Limits', comingSoon: true, min: 0, max: 1000 },
      { key: 'ai_chat.show_context_sources', label: 'Show Context Sources', description: 'Show which community data the AI referenced', type: 'boolean', defaultValue: true, category: 'Features', comingSoon: true },
    ],
  },
  {
    id: 'marketplace',
    name: 'Marketplace',
    description: 'Commercial buy/sell marketplace for physical goods and paid services',
    icon: ShoppingBag,
    type: 'feature',
    configSource: 'tenant_features',
    configOptions: [
      { key: 'marketplace.enabled', label: 'Marketplace Enabled', description: 'Master switch to enable the marketplace module', type: 'boolean', defaultValue: false, category: 'General' },
      { key: 'marketplace.allow_shipping', label: 'Allow Shipping', description: 'Enable shipping options for marketplace listings', type: 'boolean', defaultValue: false, category: 'General' },
      { key: 'marketplace.allow_free_items', label: 'Allow Free Items', description: 'Enable a free items section in the marketplace', type: 'boolean', defaultValue: true, category: 'General' },
      { key: 'marketplace.allow_business_sellers', label: 'Allow Business Sellers', description: 'Allow business accounts to sell in the marketplace', type: 'boolean', defaultValue: true, category: 'General' },
      { key: 'marketplace.allow_hybrid_pricing', label: 'Allow Hybrid Pricing', description: 'Allow items priced with both time credits and cash', type: 'boolean', defaultValue: false, category: 'Pricing' },
      { key: 'marketplace.stripe_enabled', label: 'Stripe Payments', description: 'Enable in-app payments via Stripe', type: 'boolean', defaultValue: false, category: 'Pricing' },
      { key: 'marketplace.escrow_enabled', label: 'Escrow Protection', description: 'Enable escrow buyer protection for transactions', type: 'boolean', defaultValue: false, category: 'Pricing' },
      { key: 'marketplace.platform_fee_percent', label: 'Platform Fee (%)', description: 'Platform take rate percentage on marketplace sales', type: 'number', defaultValue: 5, category: 'Pricing', min: 0, max: 50 },
      { key: 'marketplace.moderation_enabled', label: 'Require Moderation', description: 'Marketplace listings require admin approval before going live', type: 'boolean', defaultValue: true, category: 'Moderation' },
      { key: 'marketplace.max_images', label: 'Max Images Per Listing', description: 'Maximum number of images per marketplace listing', type: 'number', defaultValue: 20, category: 'Limits', min: 1, max: 50 },
      { key: 'marketplace.max_active_listings', label: 'Max Active Listings', description: 'Maximum active marketplace listings per user', type: 'number', defaultValue: 50, category: 'Limits', min: 1, max: 500 },
      { key: 'marketplace.listing_duration_days', label: 'Listing Duration (Days)', description: 'Number of days before marketplace listings auto-expire', type: 'number', defaultValue: 30, category: 'Limits', min: 1, max: 365 },
    ],
  },
  {
    id: 'identity_verification',
    name: 'Identity Verification',
    description: 'Stripe Identity verification with ID Verified badge, document + selfie matching, and configurable fee',
    icon: ShieldCheck,
    type: 'feature',
    configSource: 'identity_config',
    configOptions: [
      { key: 'identity_verification_fee_cents', label: 'Verification Fee (cents)', description: 'One-time fee charged for identity verification. Set to 0 for free verification. Value in cents (e.g. 500 = €5.00)', type: 'number', defaultValue: 500, category: 'Pricing', min: 0, max: 10000 },
    ],
  },
];

// ─────────────────────────────────────────────────────────────────────────────
// Exports
// ─────────────────────────────────────────────────────────────────────────────

export const MODULE_REGISTRY: ModuleDefinition[] = [...CORE_MODULES, ...FEATURE_MODULES];

export function getCoreModules(): ModuleDefinition[] {
  return CORE_MODULES;
}

export function getFeatureModules(): ModuleDefinition[] {
  return FEATURE_MODULES;
}

/** Get unique categories from a module's config options */
export function getOptionCategories(mod: ModuleDefinition): string[] {
  const cats = new Set(mod.configOptions.map(o => o.category));
  return Array.from(cats);
}

/** Count non-comingSoon (live) options */
export function getLiveOptionCount(mod: ModuleDefinition): number {
  return mod.configOptions.filter(o => !o.comingSoon).length;
}
