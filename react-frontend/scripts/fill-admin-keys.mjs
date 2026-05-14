// One-shot: inject missing admin translation keys into en/admin.json.
// Safe to re-run — only adds keys that aren't already present.

import fs from 'node:fs';

const PATH = 'public/locales/en/admin.json';
const a = JSON.parse(fs.readFileSync(PATH, 'utf8'));

const VALUES = {
  // ---- billing ----
  'billing.over_limit_warning': "You've reached your plan limits. Upgrade to continue.",
  'billing.upgrade_modal_desc': 'Upgrade your plan to unlock additional features and capacity.',

  // ---- admin.providers (Care Providers) ----
  'admin.providers.title': 'Care Providers',
  'admin.providers.meta_title': 'Care Providers',
  'admin.providers.subtitle': 'Manage local care providers, their services, and verification status.',
  'admin.providers.empty': 'No care providers found.',
  'admin.providers.verified': 'Verified',
  'admin.providers.yes': 'Yes',
  'admin.providers.no': 'No',
  'admin.providers.confirm_deactivate': 'Deactivate this provider? It will no longer appear in member-facing lists.',
  'admin.providers.confirm_delete': 'Delete this provider permanently? This cannot be undone.',
  'admin.providers.about.title': 'About care providers',
  'admin.providers.about.body': 'Care providers are local organisations or individuals offering services to community members.',
  'admin.providers.about.types_label': 'Types',
  'admin.providers.about.types_body': 'Providers can be home care, transport, meal delivery, companionship, or other categories.',
  'admin.providers.about.verification_label': 'Verification',
  'admin.providers.about.verification_body': 'Verified providers have been reviewed by an administrator and have passed identity and credential checks.',
  'admin.providers.actions.add_provider': 'Add provider',
  'admin.providers.actions.cancel': 'Cancel',
  'admin.providers.actions.close': 'Close',
  'admin.providers.actions.create_provider': 'Create provider',
  'admin.providers.actions.deactivate_named': 'Deactivate {{name}}',
  'admin.providers.actions.delete_provider': 'Delete provider',
  'admin.providers.actions.edit_provider': 'Edit provider',
  'admin.providers.actions.find_duplicates': 'Find duplicates',
  'admin.providers.actions.save_changes': 'Save changes',
  'admin.providers.actions.verify_provider': 'Verify provider',
  'admin.providers.duplicates.title': 'Potential duplicates',
  'admin.providers.duplicates.empty': 'No duplicates found.',
  'admin.providers.duplicates.match_percent': '{{percent}}% match',
  'admin.providers.duplicates.scanning': 'Scanning for duplicates…',
  'admin.providers.duplicates.summary': 'Found {{count}} possible duplicate(s).',
  'admin.providers.duplicates.vs': 'vs',
  'admin.providers.errors.deactivate': 'Failed to deactivate provider.',
  'admin.providers.errors.delete': 'Failed to delete provider.',
  'admin.providers.errors.duplicates': 'Failed to scan for duplicates.',
  'admin.providers.errors.load': 'Failed to load providers.',
  'admin.providers.errors.name_required': 'Name is required.',
  'admin.providers.errors.save': 'Failed to save provider.',
  'admin.providers.errors.type_required': 'Type is required.',
  'admin.providers.errors.verify': 'Failed to verify provider.',
  'admin.providers.fields.address': 'Address',
  'admin.providers.fields.description': 'Description',
  'admin.providers.fields.email': 'Email',
  'admin.providers.fields.name': 'Name',
  'admin.providers.fields.phone': 'Phone',
  'admin.providers.fields.status': 'Status',
  'admin.providers.fields.type': 'Type',
  'admin.providers.fields.website': 'Website',
  'admin.providers.messages.created': 'Provider created.',
  'admin.providers.messages.deactivated': 'Provider deactivated.',
  'admin.providers.messages.removed': 'Provider removed.',
  'admin.providers.messages.updated': 'Provider updated.',
  'admin.providers.messages.verified': 'Provider verified.',
  'admin.providers.modal.create_title': 'Add care provider',
  'admin.providers.modal.edit_title': 'Edit care provider',
  'admin.providers.pagination.next': 'Next',
  'admin.providers.pagination.previous': 'Previous',
  'admin.providers.pagination.summary': 'Showing {{from}}–{{to}} of {{total}}',
  'admin.providers.placeholders.address': 'Street, city, postcode…',
  'admin.providers.placeholders.description': 'A short description of the provider and their services…',
  'admin.providers.placeholders.name': 'e.g. Acme Home Care',
  'admin.providers.status.active': 'Active',
  'admin.providers.status.inactive': 'Inactive',
  'admin.providers.table.actions': 'Actions',
  'admin.providers.table.aria': 'Care providers',
  'admin.providers.table.name': 'Name',
  'admin.providers.table.status': 'Status',
  'admin.providers.table.type': 'Type',
  'admin.providers.table.verified': 'Verified',

  // ---- admin.common ----
  'admin.common.cancel': 'Cancel',
  'admin.common.empty_dash': '—',
  'admin.common.refresh': 'Refresh',

  // ---- admin.hour_transfers ----
  'admin.hour_transfers.title': 'Hour Transfers',
  'admin.hour_transfers.subtitle': 'Review and process incoming and pending hour transfers between members.',
  'admin.hour_transfers.actions.approve': 'Approve',
  'admin.hour_transfers.actions.reject': 'Reject',
  'admin.hour_transfers.errors.approve': 'Failed to approve transfer.',
  'admin.hour_transfers.errors.load_inbound': 'Failed to load inbound transfers.',
  'admin.hour_transfers.errors.load_pending': 'Failed to load pending transfers.',
  'admin.hour_transfers.errors.reject': 'Failed to reject transfer.',
  'admin.hour_transfers.inbound.empty': 'No inbound transfers.',
  'admin.hour_transfers.inbound.title': 'Inbound',
  'admin.hour_transfers.messages.approved': 'Transfer approved.',
  'admin.hour_transfers.messages.rejected': 'Transfer rejected.',
  'admin.hour_transfers.pending.empty': 'No pending transfers.',
  'admin.hour_transfers.pending.title': 'Pending',
  'admin.hour_transfers.reject_modal.reason_label': 'Reason',
  'admin.hour_transfers.reject_modal.reason_placeholder': 'Explain why this transfer is being rejected…',
  'admin.hour_transfers.reject_modal.title': 'Reject transfer',
  'admin.hour_transfers.tabs.aria': 'Hour transfer tabs',
  'admin.hour_transfers.tabs.inbound': 'Inbound',
  'admin.hour_transfers.tabs.pending': 'Pending',

  // ---- admin.loyalty ----
  'admin.loyalty.title': 'Loyalty Program',
  'admin.loyalty.description': 'Configure how members redeem time credits for discounts at participating merchants.',
  'admin.loyalty.refresh': 'Refresh',
  'admin.loyalty.about.title': 'About the loyalty program',
  'admin.loyalty.about.body': 'Members can redeem time credits at participating merchants in exchange for a discount on goods or services.',
  'admin.loyalty.about.rate_label': 'Exchange rate',
  'admin.loyalty.about.rate_body': 'Defines how much currency one time credit hour is worth at the merchant point of sale.',
  'admin.loyalty.about.discount_label': 'Maximum discount',
  'admin.loyalty.about.discount_body': 'The maximum percentage of a purchase that can be paid for with time credits.',
  'admin.loyalty.errors.discount_range': 'Discount must be between 0 and 100.',
  'admin.loyalty.errors.load_redemptions': 'Failed to load redemptions.',
  'admin.loyalty.errors.load_settings': 'Failed to load loyalty settings.',
  'admin.loyalty.errors.rate_positive': 'Exchange rate must be a positive number.',
  'admin.loyalty.errors.reverse': 'Failed to reverse redemption.',
  'admin.loyalty.errors.save_settings': 'Failed to save loyalty settings.',
  'admin.loyalty.ledger.actions': 'Actions',
  'admin.loyalty.ledger.date': 'Date',
  'admin.loyalty.ledger.discount': 'Discount',
  'admin.loyalty.ledger.empty': 'No redemptions yet.',
  'admin.loyalty.ledger.hours': 'Hours',
  'admin.loyalty.ledger.item': 'Item',
  'admin.loyalty.ledger.member': 'Member',
  'admin.loyalty.ledger.merchant': 'Merchant',
  'admin.loyalty.ledger.rate': 'Rate',
  'admin.loyalty.ledger.reverse': 'Reverse',
  'admin.loyalty.ledger.status': 'Status',
  'admin.loyalty.ledger.title': 'Redemption ledger',
  'admin.loyalty.messages.reversed': 'Redemption reversed.',
  'admin.loyalty.messages.settings_saved': 'Loyalty settings saved.',
  'admin.loyalty.reverse_modal.body': 'Reversing a redemption will refund the time credits to the member.',
  'admin.loyalty.reverse_modal.cancel': 'Cancel',
  'admin.loyalty.reverse_modal.confirm': 'Reverse',
  'admin.loyalty.reverse_modal.discount': 'Discount',
  'admin.loyalty.reverse_modal.member_fallback': 'Member',
  'admin.loyalty.reverse_modal.merchant': 'Merchant',
  'admin.loyalty.reverse_modal.reason': 'Reason',
  'admin.loyalty.reverse_modal.reason_placeholder': 'Why is this redemption being reversed?',
  'admin.loyalty.reverse_modal.redeemed': 'Redeemed',
  'admin.loyalty.reverse_modal.title': 'Reverse redemption',
  'admin.loyalty.settings.accept_time_credits': 'Accept time credits',
  'admin.loyalty.settings.accept_time_credits_hint': 'Allow this merchant to accept time credits as payment for a portion of each purchase.',
  'admin.loyalty.settings.clear': 'Clear',
  'admin.loyalty.settings.description': 'Configure the merchant-side rate and maximum-discount policy for the loyalty program.',
  'admin.loyalty.settings.exchange_rate': 'Exchange rate',
  'admin.loyalty.settings.exchange_rate_description': 'How much currency a single time credit hour is worth at the merchant point of sale.',
  'admin.loyalty.settings.maximum_discount': 'Maximum discount (%)',
  'admin.loyalty.settings.maximum_discount_description': 'The maximum percentage of a purchase that can be paid using time credits.',
  'admin.loyalty.settings.no_matching_members': 'No matching members.',
  'admin.loyalty.settings.save': 'Save settings',
  'admin.loyalty.settings.seller_label': 'Merchant',
  'admin.loyalty.settings.seller_placeholder': 'Search merchants…',
  'admin.loyalty.settings.title': 'Loyalty settings',
  'admin.loyalty.settings.view_only': 'You have read-only access to this setting.',
  'admin.loyalty.stats.hours_redeemed': 'Hours redeemed',
  'admin.loyalty.stats.total_chf_discount': 'Total discount given',
  'admin.loyalty.stats.total_redemptions': 'Total redemptions',
  'admin.loyalty.table.empty_value': '—',

  // ---- admin.surveys ----
  'admin.surveys.errors.export_failed': 'Failed to export survey data.',

  // ---- admin.feedback (municipality feedback) ----
  'admin.feedback.title': 'Municipality Feedback',
  'admin.feedback.subtitle': 'Review feedback submitted by community members.',
  'admin.feedback.errors.close': 'Failed to close feedback item.',
  'admin.feedback.errors.export': 'Failed to export feedback.',
  'admin.feedback.errors.load': 'Failed to load feedback.',
  'admin.feedback.errors.resolution_required': 'A resolution note is required.',
  'admin.feedback.errors.resolve': 'Failed to resolve feedback.',
  'admin.feedback.errors.save_triage': 'Failed to save triage notes.',
  'admin.feedback.messages.closed': 'Feedback closed.',
  'admin.feedback.messages.resolved': 'Feedback resolved.',
  'admin.feedback.messages.triage_saved': 'Triage notes saved.',

  // ---- admin.trust_tier ----
  'admin.trust_tier.errors.recompute_failed': 'Failed to recompute trust tiers.',
  'admin.trust_tier.errors.save_failed': 'Failed to save trust tier settings.',
  'admin.trust_tier.messages.recomputed': 'Trust tiers recomputed.',
  'admin.trust_tier.messages.saved': 'Trust tier settings saved.',
  'admin.trust_tier.recompute_confirm': 'Recompute trust tiers for all members? This may take a few minutes.',

  // ---- admin.warmth_pass ----
  'admin.warmth_pass.title': 'Warmth Pass',
  'admin.warmth_pass.subtitle': 'Look up a member to check Warmth Pass eligibility and recognised contribution history.',
  'admin.warmth_pass.eligible': 'Eligible',
  'admin.warmth_pass.not_eligible': 'Not eligible',
  'admin.warmth_pass.verified': 'Verified',
  'admin.warmth_pass.not_verified': 'Not verified',
  'admin.warmth_pass.empty_date': '—',
  'admin.warmth_pass.loading': 'Loading…',
  'admin.warmth_pass.no_categories': 'No help categories recorded.',
  'admin.warmth_pass.tier_chip': 'Tier {{tier}}',
  'admin.warmth_pass.about.title': 'About Warmth Pass',
  'admin.warmth_pass.about.body': 'Warmth Pass recognises members who have made sustained, verified contributions to their community.',
  'admin.warmth_pass.errors.lookup_failed': 'Member lookup failed.',
  'admin.warmth_pass.errors.no_data': 'No data available for this member.',
  'admin.warmth_pass.fields.help_categories': 'Help categories',
  'admin.warmth_pass.fields.hours_logged': 'Hours logged',
  'admin.warmth_pass.fields.identity_verified': 'Identity verified',
  'admin.warmth_pass.fields.member_since': 'Member since',
  'admin.warmth_pass.fields.pass_active_since': 'Pass active since',
  'admin.warmth_pass.fields.reviews_received': 'Reviews received',
  'admin.warmth_pass.fields.trust_tier': 'Trust tier',
  'admin.warmth_pass.lookup.button': 'Look up',
  'admin.warmth_pass.lookup.member_id': 'Member ID',
  'admin.warmth_pass.lookup.placeholder': 'Enter a member ID…',
  'admin.warmth_pass.lookup.title': 'Member lookup',
  'admin.warmth_pass.not_eligible_notice.title': 'Not eligible yet',
  'admin.warmth_pass.not_eligible_notice.body': 'This member does not yet meet the requirements for a Warmth Pass.',

  // ---- panel.sidebar.items ----
  'panel.sidebar.items.surveys': 'Surveys',
  'panel.sidebar.items.projects': 'Projects',
  'panel.sidebar.items.trust_tier': 'Trust Tier',

  // ---- tenant_features ----
  'tenant_features.cost_warning': 'This setting affects costs — review carefully before changing.',
  'tenant_features.currently_serving': 'Currently serving',
  'tenant_features.status_autocomplete': 'Autocomplete',
  'tenant_features.status_google_paid': 'Google (paid)',
  'tenant_features.status_google_places_paid': 'Google Places (paid)',
  'tenant_features.status_maps': 'Maps',
  'tenant_features.status_nominatim_free': 'Nominatim (free)',
  'tenant_features.status_off': 'Off',
  'tenant_features.status_osm_free': 'OpenStreetMap (free)',

  // ---- federation.analytics ----
  'federation.analytics.title': 'Federation Analytics',

  // ---- groups ----
  'groups.geocode_description': 'Geocode group locations to enable map display and distance-based discovery.',
  'groups.geocode_not_migrated_desc': 'Groups have not been migrated to the geocoding system yet.',

  // ---- moderation ----
  'moderation.flags_tooltip': 'Number of moderation flags on this item.',
  'moderation.load_error': 'Failed to load the moderation queue.',
  'moderation.pagination_label': 'Moderation queue pagination',
  'moderation.user_fallback': 'Unknown user',

  // ---- resources ----
  'resources.excerpt_desc': 'A short summary shown in article lists and search results.',
  'resources.video_url_desc': 'Optional. A YouTube or Vimeo URL embedded at the top of the article.',

  // ---- super ----
  'super.impersonate_user_warning': "Impersonation logs every action under this user's identity. Use only when necessary for support.",

  // ---- volunteering ----
  'volunteering.move_down': 'Move down',
  'volunteering.move_up': 'Move up',
  'volunteering.preview': 'Preview',
  'volunteering.consent_renewal_body': 'This consent has expired or is about to expire. Please request renewal from the volunteer.',
  'volunteering.deactivate': 'Deactivate',
  'volunteering.contact_email_required': 'A contact email is required.',
  'volunteering.description_min_length': 'Description must be at least 20 characters.',
  'volunteering.meeting_schedule_label': 'Meeting schedule',
  'volunteering.meeting_schedule_placeholder': 'e.g. Tuesdays 7pm at the community hall',
  'volunteering.org_type_club': 'Club',
  'volunteering.org_type_label': 'Organisation type',
  'volunteering.org_type_organisation': 'Organisation',
  'volunteering.reject_training_reason_prompt': 'Please provide a reason for rejecting this training submission.',
  'volunteering.time_days_ago': '{{count}} day ago',
  'volunteering.time_days_ago_plural': '{{count}} days ago',
  'volunteering.time_hours_ago': '{{count}} hour ago',
  'volunteering.time_hours_ago_plural': '{{count}} hours ago',
  'volunteering.time_just_now': 'Just now',
  'volunteering.time_minutes_ago': '{{count}} minute ago',
  'volunteering.time_minutes_ago_plural': '{{count}} minutes ago',
};

function set(o, key, val) {
  const parts = key.split('.');
  let c = o;
  for (let i = 0; i < parts.length - 1; i++) {
    if (typeof c[parts[i]] !== 'object' || c[parts[i]] === null || Array.isArray(c[parts[i]])) {
      c[parts[i]] = {};
    }
    c = c[parts[i]];
  }
  if (!(parts[parts.length - 1] in c)) {
    c[parts[parts.length - 1]] = val;
  }
}

let added = 0, skipped = 0;
for (const [k, v] of Object.entries(VALUES)) {
  const parts = k.split('.');
  let cur = a, exists = true;
  for (const p of parts) {
    if (cur == null || typeof cur !== 'object' || !(p in cur)) { exists = false; break; }
    cur = cur[p];
  }
  if (exists && typeof cur === 'string') { skipped++; continue; }
  set(a, k, v);
  added++;
}

fs.writeFileSync(PATH, JSON.stringify(a, null, 2) + '\n');
console.log('Added:', added, 'Skipped (already present):', skipped);
