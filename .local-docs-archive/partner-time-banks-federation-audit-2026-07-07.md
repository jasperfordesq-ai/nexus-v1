# Partner Time Banks Federation Production-Readiness Audit

Date: 2026-07-07
Repository: Project NEXUS V1.5
Scope: Backend federation module, Partner Time Banks admin shell, user-facing federation React flows, external partner protocols, Partner API, volunteering/organisation integrations, tenant gating, security, resilience, and test coverage.

No application code was changed during this audit.

## Executive Summary

The Partner Time Banks federation module is broad and unusually mature for a feature of this size: it has tenant-scoped services, federation feature controls, external partner SSRF defenses, encrypted partner credentials, HMAC/nonce webhook authentication, scheduled reconciliation alerts for pending money movement, admin and member React screens, and a large existing test surface across Laravel, unit, feature, integration, and React tests.

It is not yet enterprise-production-ready. The highest-risk gaps are in money movement idempotency, external ID/API contract consistency, privacy boundaries across external partner conversations and partner APIs, and uneven enforcement of federation operation gates. These are not cosmetic issues: they can create duplicate external debits, leak unrelated external-message context to translation, expose member/listing data outside the federation consent model, and leave disabled/lockdown federation surfaces partially available.

The frontend generally uses translations and has useful loading/error/empty states, but several federation pages still drift from the HeroUI v3/Tailwind 4 design standard with custom glass/gradient components and improvised controls. The member-facing external partner experience is also inconsistent: external members can appear in search and messaging, but profile/detail and transfer flows do not share a stable contract with the backend.

Production rollout should prioritize financial idempotency, privacy isolation, gate consistency, and external protocol contracts before broader UX polish.

## Guidance And References Checked

- `AGENTS.md`
- `react-frontend/CLAUDE.md`
- `.agents/skills/heroui-react/SKILL.md`
- Official HeroUI v3 docs checked:
  - https://heroui.com/en/docs/react/components/button
  - https://heroui.com/en/docs/react/components/table
  - https://heroui.com/en/docs/react/components/card

HeroUI confirmation relevant to this audit: buttons support explicit variants, loading/pending states, icon-only states, and `onPress`; tables have accessible structured empty/loading/pagination patterns; cards are available as framework primitives. The existing custom `GlassCard`/gradient-heavy federation surfaces should be treated as design-system drift when they replace standard HeroUI primitives without a product-specific need.

## Current Module Map

### Backend Routes And Config

- `routes/api.php`
  - Member-facing `/v2/federation/*` routes: lines 443-471.
  - Public aggregate feed: lines 1193-1198.
  - Admin federation routes: around lines 2264-2336.
  - Super-admin federation controls: around lines 2503-2517.
  - Legacy/external V1 federation routes: lines 3104-3131.
  - Protocol endpoints: around lines 3139-3192.
  - Partner API routes: lines 3376-3409.
  - Admin API partner routes: lines 3435-3444.
  - Global numeric `{id}` route pattern: lines 9-10.
- `config/federation.php`
  - Federation JWT/API secret configuration.
- `routes/govuk-alpha-parity/federation.php`
  - Accessible frontend parity routing for federation.

### Backend Controllers

- `app/Http/Controllers/Api/FederationV2Controller.php`
  - Main user-facing federation controller: status, opt-in/out, partners, activity, events, groups, listings, members, messages, translation, settings, connections, transactions.
- `app/Http/Controllers/Api/FederationController.php`
  - V1 federation API.
- `app/Http/Controllers/Api/FederationExternalWebhookController.php`
  - HMAC/API-key external webhook receiver.
- `app/Http/Controllers/Api/FederationNativeIngestController.php`
  - Native inbound ingest.
- `app/Http/Controllers/Api/FederationKomunitinController.php`
  - Komunitin protocol endpoints.
- `app/Http/Controllers/Api/FederationCreditCommonsController.php`
  - Credit Commons protocol endpoints.
- `app/Http/Controllers/Api/FederationHourTransferController.php`
  - Hour-transfer protocol surface.
- `app/Http/Controllers/Api/FederationAggregateController.php`
  - Public aggregate sharing feed.
- `app/Http/Controllers/Api/AdminFederationController.php`
- `app/Http/Controllers/Api/AdminFederationExternalPartnersController.php`
- `app/Http/Controllers/Api/AdminFederationDataController.php`
- `app/Http/Controllers/Api/AdminFederationNeighborhoodsController.php`
- `app/Http/Controllers/Api/AdminFederationCreditAgreementsController.php`
- `app/Http/Controllers/Api/AdminFederationAggregateController.php`
- `app/Http/Controllers/Api/AdminFederationAnalyticsController.php`
- `app/Http/Controllers/Api/AdminFederationWebhooksController.php`
- `app/Http/Controllers/Api/AdminTimebankingController.php`
- `app/Http/Controllers/Api/AdminFederationPeerController.php`
- `app/Http/Controllers/Api/Admin/ApiPartnerAdminController.php`
- `app/Http/Controllers/Api/PartnerApi/PartnerV1Controller.php`
- `app/Http/Controllers/Api/PartnerApi/PartnerOAuthController.php`
- `app/Http/Controllers/Api/Verein/VereinFederationAdminController.php`
- `app/Http/Controllers/Api/Verein/VereinFederationMemberController.php`

### Services And Middleware

- Federation core:
  - `app/Services/FederationFeatureService.php`
  - `app/Services/FederationUserService.php`
  - `app/Services/FederationPartnershipService.php`
  - `app/Services/FederationSearchService.php`
  - `app/Services/FederationAuditService.php`
  - `app/Services/FederationActivityService.php`
  - `app/Services/FederationDirectoryService.php`
  - `app/Services/FederationCreditService.php`
  - `app/Services/FederationInternalLedgerService.php`
  - `app/Services/FederationRealtimeService.php`
  - `app/Services/FederationEmailService.php`
  - `app/Services/FederatedMessageService.php`
  - `app/Services/FederatedConnectionService.php`
  - `app/Services/FederatedIdentityService.php`
  - `app/Services/FederationJwtService.php`
- External protocols:
  - `app/Services/FederationExternalPartnerService.php`
  - `app/Services/FederationExternalApiClient.php`
  - `app/Contracts/FederationProtocolAdapter.php`
  - `app/Services/Protocols/TimeOverflowAdapter.php`
  - `app/Services/Protocols/KomunitinAdapter.php`
  - `app/Services/Protocols/CreditCommonsAdapter.php`
  - `app/Services/CreditCommonsNodeService.php`
- Partner API:
  - `app/Services/PartnerApi/PartnerApiAuthService.php`
  - `app/Services/PartnerApi/PartnerApiRateLimiter.php`
  - `app/Services/PartnerApi/PartnerWebhookDispatcher.php`
  - `app/Http/Middleware/PartnerApiAuth.php`
  - `app/Http/Middleware/FederationApiAuth.php`
  - `app/Core/FederationApiMiddleware.php`
- Volunteering/caring/organisations:
  - `app/Services/CaringCommunity/FederationAggregateService.php`
  - `app/Services/CaringCommunity/FederationPeerService.php`
  - `app/Services/ResearchPartnershipService.php`
  - `app/Services/Verein/VereinFederationService.php`

### Models And Data Tables

- `app/Models/FederationExternalPartner.php`
- `app/Models/FederatedIdentity.php`
- Existing shared models/tables touched by federation:
  - `users`
  - `tenants`
  - `transactions`
  - `federation_messages`
  - `federation_transactions`
  - `federation_partnerships`
  - `federation_user_settings`
  - `federation_external_partners`
  - `federation_external_partner_logs`
  - `federation_webhook_nonces`
  - `federation_events`
  - `federation_groups`
  - `federation_listings`
  - `federation_members`
  - `federation_volunteering`
  - `federation_inbound_connections`
  - `federation_aggregate_consents`
  - `federation_aggregate_query_log`
  - `federation_cc_entries`
  - `federation_cc_node_config`
  - `api_partners`
  - `api_partner_credentials`
  - `api_oauth_tokens`
  - `api_webhook_subscriptions`
  - `api_call_log`
  - `api_partner_wallet_credits`
  - `vol_opportunities`
  - `vol_logs`
  - `vol_organizations`
  - `caring_federation_peers`
  - `caring_hour_transfers`
  - `verein_*` federation tables.

### Migrations

Key federation migrations include:

- `database/migrations/2026_03_20_000000_add_federation_rate_limit_tracking.php`
- `database/migrations/2026_04_02_000001_create_federation_topics_tables.php`
- `database/migrations/2026_04_10_170000_drop_messages_sender_fk_for_federation.php`
- `database/migrations/2026_04_10_175000_drop_transactions_sender_receiver_fk_for_federation.php`
- `database/migrations/2026_04_11_200000_add_protocol_type_to_federation_external_partners.php`
- `database/migrations/2026_04_11_200001_create_federation_cc_entries_table.php`
- `database/migrations/2026_04_12_100000_create_federated_identities_table.php`
- `database/migrations/2026_04_12_110000_add_allow_flags_to_federation_external_partners.php`
- `database/migrations/2026_04_12_120000_create_federation_shadow_tables.php`
- `database/migrations/2026_04_13_000002_create_federation_webhook_nonces.php`
- `database/migrations/2026_04_17_083057_add_federation_performance_indexes.php`
- `database/migrations/2026_04_17_100000_add_canonical_pair_to_federation_partnerships.php`
- `database/migrations/2026_04_28_010000_create_federation_aggregate_consents_table.php`
- `database/migrations/2026_04_28_010001_create_federation_aggregate_query_log_table.php`
- `database/migrations/2026_04_29_180000_create_partner_api_tables.php`
- `database/migrations/2026_04_30_180000_create_caring_federation_peers_table.php`
- `database/migrations/2026_05_04_140000_scope_federated_identities_by_tenant.php`
- `database/migrations/2026_05_04_141000_enforce_federation_message_idempotency.php`
- `database/migrations/2026_05_08_000002_add_federation_columns_to_vol_opportunities.php`
- `database/migrations/2026_05_19_000001_scope_vol_opportunity_federation_unique_index_by_tenant.php`
- `database/migrations/2026_05_19_010000_add_external_idempotency_key_to_federation_transactions.php`
- `database/migrations/2026_05_19_091000_add_delivery_markers_to_federation_messages.php`
- `database/migrations/2026_05_19_092000_add_delivery_markers_to_federation_transactions.php`
- `database/migrations/2026_05_19_093000_add_delivery_markers_to_federation_inbound_connections.php`
- `database/migrations/2026_06_04_130000_add_unique_transaction_uuid_to_federation_cc_entries.php`
- `database/migrations/2026_06_12_000000_add_federated_visibility_to_vol_opportunities.php`
- `database/migrations/2026_06_12_120002_create_federation_neighborhood_tenants_table.php`

### Jobs, Listeners, Commands

- Jobs:
  - `app/Jobs/ReconcileFederationPendingTxJob.php`
  - `app/Jobs/FederationInitialSyncJob.php`
- Event registration:
  - `app/Providers/EventServiceProvider.php:200-254`
- Outbound listeners:
  - `PushVolunteerOpportunityToFederatedPartners`
  - `PushMemberProfileUpdateToFederatedPartners`
  - `PushListingToFederatedPartners`
  - `PushMessageToFederatedPartner`
  - `PushTransactionToFederatedPartner`
  - `PushReviewToFederatedPartner`
  - `PushGroupToFederatedPartners`
  - `PushGroupMembershipToFederatedPartners`
  - `PushGroupRetractionToFederatedPartners`
  - `PushConnectionAcceptedToFederatedPartner`
  - `PushCommunityEventToFederatedPartners`
  - `PushFederationDataRetraction`
- Inbound listeners:
  - `IngestFederatedVolunteerOpportunity`
  - `HandleFederatedReviewReceived`
  - `HandleFederatedConnectionReceived`
  - `HandleFederatedListingReceived`
  - `HandleFederatedCommunityEventReceived`
  - `HandleFederatedMemberUpdated`
  - `HandleFederatedGroupReceived`
- Scheduled commands/jobs:
  - `bootstrap/app.php:119-123` schedules pending federation transaction reconciliation.
  - `bootstrap/app.php:197-203` schedules partner sync and external log purge.
  - Federation commands include `SyncFederationPartners`, `RegisterTimeOverflowPartner`, `TestTimeOverflowFederation`, `PurgeFederationExternalLogs`, `PruneFederationAggregateLogs`, `ExpireVereinFederationInvitations`.

### React Frontend

Member-facing federation:

- `react-frontend/src/pages/federation/FederationHubPage.tsx`
- `react-frontend/src/pages/federation/FederationPartnersPage.tsx`
- `react-frontend/src/pages/federation/FederationPartnerDetailPage.tsx`
- `react-frontend/src/pages/federation/FederationMembersPage.tsx`
- `react-frontend/src/pages/federation/FederationMemberProfilePage.tsx`
- `react-frontend/src/pages/federation/FederationMessagesPage.tsx`
- `react-frontend/src/pages/federation/FederationListingsPage.tsx`
- `react-frontend/src/pages/federation/FederationEventsPage.tsx`
- `react-frontend/src/pages/federation/FederationGroupsPage.tsx`
- `react-frontend/src/pages/federation/FederationSettingsPage.tsx`
- `react-frontend/src/pages/federation/FederationOnboardingPage.tsx`
- `react-frontend/src/pages/federation/FederationConnectionsPage.tsx`
- `react-frontend/src/components/federation/FederatedTrustBadge.tsx`
- `react-frontend/src/components/federation/FederationReviewsPanel.tsx`

Partner Time Banks admin shell:

- `react-frontend/src/partners/PartnersApp.tsx`
- `react-frontend/src/partners/PartnersRoute.tsx`
- `react-frontend/src/partners/routes.tsx`
- `react-frontend/src/partners/PartnersLayout.tsx`
- `react-frontend/src/partners/components/PartnersSidebar.tsx`
- `react-frontend/src/partners/components/PartnersHeader.tsx`
- `react-frontend/src/partners/components/PartnersBreadcrumbs.tsx`
- `react-frontend/src/partners/pages/*`

Federation admin modules embedded by the shell:

- `react-frontend/src/admin/modules/federation/*`
- `react-frontend/src/admin/modules/super/FederationControls.tsx`
- `react-frontend/src/admin/modules/super/FederationTenantFeatures.tsx`
- `react-frontend/src/admin/modules/super/FederationWhitelist.tsx`
- `react-frontend/src/admin/modules/super/FederationAuditLog.tsx`
- `react-frontend/src/admin/modules/api-partners/ApiPartnersAdminPage.tsx`
- `react-frontend/src/admin/modules/caring-community/FederationPeersAdminPage.tsx`

Translations:

- `react-frontend/public/locales/*/federation.json`
- `react-frontend/public/locales/*/admin_federation.json`
- `react-frontend/public/locales/*/partners.json`
- `react-frontend/public/locales/*/admin_api_partners.json`
- PHP/API translation keys in `lang/*`.

### Existing Tests

Significant coverage already exists:

- Backend integration/feature:
  - `tests/Laravel/Integration/FederationTest.php`
  - `tests/Laravel/Integration/FederationDeliveryReliabilityTest.php`
  - `tests/Laravel/Integration/PartnerApiWalletReliabilityTest.php`
  - `tests/Laravel/Feature/FederationFeatureGateTest.php`
  - `tests/Laravel/Feature/FederationTenantIsolationTest.php`
  - `tests/Laravel/Feature/FederationProtocolEndpointsTest.php`
  - `tests/Laravel/Feature/Federation/FederationV2InternalTransferTest.php`
  - `tests/Laravel/Feature/Federation/FederationAggregateTest.php`
  - `tests/Laravel/Feature/Federation/FederationAggregateDataIntegrityTest.php`
  - `tests/Laravel/Feature/Federation/TimeOverflowProtocolTest.php`
  - `tests/Laravel/Feature/Federation/KomunitinProtocolTest.php`
  - `tests/Laravel/Feature/Federation/CreditCommonsProtocolTest.php`
  - `tests/Laravel/Feature/Federation/CreditCommonsReplaySafetyTest.php`
  - `tests/Laravel/Feature/Federation/NativeNexusProtocolTest.php`
  - `tests/Laravel/Feature/Federation/VolunteerOpportunityFederationOptInTest.php`
  - `tests/Laravel/Feature/PartnerApi/*`
  - `tests/Laravel/Feature/Controllers/AdminFederation*Test.php`
  - `tests/Laravel/Feature/Controllers/Federation*Test.php`
- Backend unit:
  - `tests/Laravel/Unit/Services/Federation*Test.php`
  - `tests/Laravel/Unit/Services/Protocols/*AdapterTest.php`
  - `tests/Laravel/Unit/Middleware/PartnerApiAuthTest.php`
  - `tests/Laravel/Unit/Jobs/ReconcileFederationPendingTxJobTest.php`
  - `tests/Laravel/Unit/Jobs/FederationInitialSyncJobTest.php`
  - `tests/Laravel/Unit/Listeners/*Federated*Test.php`
- React:
  - `react-frontend/src/pages/federation/*.test.tsx`
  - `react-frontend/src/admin/modules/federation/*.test.tsx`
  - `react-frontend/src/partners/**/*.test.tsx`
  - `react-frontend/src/admin/modules/api-partners/ApiPartnersAdminPage.test.tsx`
  - `react-frontend/src/admin/modules/caring-community/FederationPeersAdminPage.test.tsx`

## User-Facing Flows And Admin Flows

### Member/User Flows

- Federation status and onboarding:
  - `/v2/federation/status`
  - `/v2/federation/opt-in`
  - `/v2/federation/setup`
  - `/v2/federation/opt-out`
  - React: `FederationOnboardingPage`, `FederationSettingsPage`, `FederationHubPage`.
- Partner discovery:
  - `/v2/federation/partners`
  - `/v2/federation/partners/{id}`
  - React: `FederationPartnersPage`, `FederationPartnerDetailPage`.
- Cross-community discovery:
  - `/v2/federation/members`
  - `/v2/federation/listings`
  - `/v2/federation/events`
  - `/v2/federation/groups`
  - React: federation members/listings/events/groups pages.
- Member profiles and reviews:
  - `/v2/federation/members/{id}`
  - `/v2/federation/members/{id}/reviews`
  - React: `FederationMemberProfilePage`, `FederationReviewsPanel`.
- Messaging and translation:
  - `/v2/federation/messages`
  - `/v2/federation/messages/{id}/mark-read`
  - `/v2/federation/messages/mark-read-batch`
  - `/v2/federation/messages/{id}/translate`
  - React: `FederationMessagesPage`.
- Connections:
  - `/v2/federation/connections`
  - `/v2/federation/connections/status/{userId}/{tenantId}`
  - React: `FederationConnectionsPage`.
- Time-credit transfers:
  - `/v2/federation/transactions`
  - React: transfer modal in `FederationMemberProfilePage`.

### Admin And Super-Admin Flows

- Partner Time Banks panel:
  - `react-frontend/src/partners/PartnersRoute.tsx` allows any admin into the overview when federation is enabled.
  - `react-frontend/src/partners/routes.tsx` gates protocol, key, webhook, aggregate, data, settings, and caring peer pages to super admins.
- Federation admin modules:
  - Partnerships, directory profile, neighborhoods, credit agreements, activity feed, analytics, data management.
- External partner protocol setup:
  - External partners, Credit Commons config, API documentation, API keys, webhooks.
- Super-admin controls:
  - Global federation controls, tenant feature toggles, whitelist, audit log.
- Partner API administration:
  - API partners, credentials, scopes, call logs.
- Caring/volunteering integration:
  - Caring federation peers.
  - Aggregate sharing consent.
  - Volunteer opportunity federation visibility.

## Cross-Module Dependency Map

- Members/profiles:
  - Federation opt-in, profile visibility, search visibility, member settings, avatar/profile details.
- Wallet/time credits:
  - Internal federation transfer, external partner transfer saga, Credit Commons relay, Partner API wallet credit, transaction audit/reconciliation.
- Listings:
  - Federated visibility, owner opt-in, partner permissions, external listings fetch.
- Groups:
  - Group membership federation and retraction listeners.
- Events:
  - Community event outbound/inbound federation.
- Messages/notifications:
  - Federation messages table, email delivery markers, realtime Pusher events, notification/email locale contexts, message translation.
- Volunteering:
  - Volunteer opportunity push/ingest, `federated_visibility`, approved volunteer hours in aggregate feed.
- Organisations:
  - `vol_organizations` exact counts in aggregate feed, volunteer organisation profile exposure, organisation wallet/timebanking interactions.
- Caring community:
  - Caring federation peers and hour-transfer protocols.
- Feed/activity:
  - Federation activity logs and volunteer/feed side effects.
- Search:
  - Internal federation member/listing search plus external partner fetch/merge.
- Admin/community management:
  - Partner Time Banks panel, federation settings, data export/import/purge, audit logs.
- Tenant settings/features:
  - Tenant feature gates, global system controls, emergency lockdown, whitelist.
- External protocol partners:
  - TimeOverflow, Komunitin, Credit Commons, Native Nexus ingest, Partner API.

## Findings

### Critical

#### 1. External time-credit transfers can double debit because idempotency is skipped before the external branch

Severity: Critical

Evidence:

- `app/Http/Controllers/Api/FederationV2Controller.php:2647-2651` detects `receiver_tenant_id` starting with `ext-` and immediately calls `sendExternalTransaction(...)`.
- `app/Http/Controllers/Api/FederationV2Controller.php:2685-2715` implements the anti-double-submit idempotency guard only after the internal branch begins.
- `app/Http/Controllers/Api/FederationV2Controller.php:2882-2916` debits the local user, inserts a pending transaction, then sends the external request with an idempotency key derived from the newly created local transaction id. A duplicate client submit creates a second local transaction id and therefore a second external idempotency key.
- `react-frontend/src/pages/federation/FederationMemberProfilePage.tsx:559-573` posts `/v2/federation/transactions` without an `Idempotency-Key` header or body key.
- `database/migrations/2026_05_19_010000_add_external_idempotency_key_to_federation_transactions.php:20-32` adds an external idempotency column, but the member-facing external send path does not claim a client/content idempotency key before debit.

Impact:

A double click, retry, flaky network replay, or impatient user can create two separate local debits and two external credits. This is a money-state integrity bug. The scheduled reconciliation job can detect stale pending rows, but it does not prevent duplicate successful transfers.

Recommended fix:

- Move idempotency claiming before the internal/external branch in `sendTransaction`.
- Require or generate a stable client operation key for both internal and external transfers.
- Store the idempotency claim durably with a unique constraint scoped by tenant, sender, operation type, receiver, amount, and explicit idempotency key. Do not rely only on cache for financial operations.
- Return the original committed result on replay.
- Generate a UUID idempotency key in the React transfer modal and send it as `Idempotency-Key`.
- Keep the submit button pending/disabled until the first request resolves, but treat that as UX only, not the core protection.

Suggested tests:

- Laravel feature test: two external transfer POSTs with the same `Idempotency-Key` create one local transaction and one external API call.
- Laravel feature test: two near-simultaneous external transfer POSTs without an explicit key are either rejected as duplicate or mapped to a single transaction using content fingerprinting.
- Laravel feature test: external API retry after timeout uses the same partner idempotency key.
- Vitest: `FederationMemberProfilePage` sends a stable idempotency key and disables the submit button while pending.

Related modules affected:

- Wallet/time credits
- Partner Time Banks
- External protocols
- Credit Commons
- Audit/reconciliation
- Notifications

#### 2. External message translation context is not partitioned by external partner

Severity: Critical

Evidence:

- `app/Http/Controllers/Api/FederationV2Controller.php:2314-2336` loads both internal and external federation messages and includes `external_partner_id`.
- `app/Http/Controllers/Api/FederationV2Controller.php:2359-2367` fetches prior conversation context only by sender/receiver tenant and user ids. It does not include `external_partner_id` in the context predicate.

Impact:

If two external partners use the same remote user id, or if local storage maps external conversations into overlapping sender/receiver ids, context-aware translation can include unrelated partner messages. That context may be sent to the translation provider, creating a cross-partner privacy leak.

Recommended fix:

- Include `external_partner_id` in the translation context query.
- Treat internal and external conversations as different thread namespaces.
- For external messages, key the context by `(external_partner_id, remote participant id, local participant id)` rather than only tenant/user ids.
- Add defensive tests covering two external partners with the same remote user id.

Suggested tests:

- Laravel feature test: translating a message from external partner A does not include earlier messages from external partner B when remote ids match.
- Laravel feature test: internal federation context remains unchanged for non-external messages.
- Unit test for thread key construction if extracted.

Related modules affected:

- Federation messaging
- Translation
- External partners
- Privacy/compliance

### High

#### 3. `ext-*` external partner/detail contracts are inconsistent with numeric route constraints

Severity: High

Evidence:

- `routes/api.php:9-10` globally constrains `{id}` route parameters to numeric values.
- `routes/api.php:449` registers `/v2/federation/partners/{id}` and `routes/api.php:455` registers `/v2/federation/members/{id}`.
- `app/Http/Controllers/Api/FederationV2Controller.php:410-447` contains an external partner branch for ids like `ext-3`, but the route-level numeric constraint prevents that request from reaching the controller.
- `react-frontend/src/pages/federation/FederationPartnerDetailPage.tsx:107-116` calls `/v2/federation/partners/${id}` with the route param.
- `react-frontend/src/pages/federation/FederationMembersPage.tsx:261-270` explicitly blocks external member profiles with a toast.

Impact:

The module advertises external partners and emits `ext-*` ids in list/search flows, but parts of the API and frontend cannot dereference them consistently. Deep links, detail pages, external partner profiles, and future external-member workflows will fail or require per-page exceptions.

Recommended fix:

- Avoid overloading numeric `{id}` routes for external resources.
- Add explicit routes such as `/v2/federation/external-partners/{id}` and `/v2/federation/external-members/{partnerId}/{externalId}`.
- Alternatively override `where('id', '.*')` only for the affected federation routes, but explicit routes are clearer and safer.
- Update React to use typed identifiers instead of assuming all profile/detail resources are local numeric ids.

Suggested tests:

- Laravel route/controller tests for `/v2/federation/partners/ext-1`.
- Laravel route/controller tests for external member UUID/string ids.
- React tests for external partner deep link and external member actions.
- Contract tests ensuring ids returned by list endpoints can be passed to detail endpoints where the UI offers detail navigation.

Related modules affected:

- Partner directory
- External partner protocols
- Member search
- Messaging
- UX/deep links

#### 4. Federation operation gates are uneven across read and settings endpoints

Severity: High

Evidence:

- `app/Services/FederationFeatureService.php:303-360` defines operation-level gating for emergency lockdown, global enablement, tenant whitelist, system feature toggles, tenant federation enablement, and per-operation tenant feature toggles.
- `app/Http/Controllers/Api/FederationV2Controller.php:98-105` exposes `requireFederationOperation(...)`.
- `app/Http/Controllers/Api/FederationV2Controller.php:292-360` implements `partners()` without calling `requireFederationOperation`.
- `app/Http/Controllers/Api/FederationV2Controller.php:410-447` implements `partnerDetail()` without calling `requireFederationOperation`.
- `app/Http/Controllers/Api/FederationV2Controller.php:524` starts `activity()` and the `rg` scan found no operation gate there.
- `app/Http/Controllers/Api/FederationV2Controller.php:2410-2463` implements settings read/update without an operation gate.
- In contrast, `events`, `groups`, `listings`, `members`, messages, connections, and transactions call `requireFederationOperation` at lines 577, 695, 869, 1102, 1811, 2472, and 2610.

Impact:

Emergency lockdown, global disable, tenant disable, or whitelist removal can leave parts of federation visible or configurable. In enterprise operations, a federation kill switch must be reliable and predictable.

Recommended fix:

- Define a policy matrix for read-only federation status/settings versus data-disclosing federation operations.
- Gate `partners`, `partnerDetail`, `activity`, and any data-disclosing settings with the appropriate operation (`profiles` or a new `directory`/`core` operation).
- Keep `status`, `optOut`, and possibly a minimal settings read available so users can understand and disable sharing.
- Add a shared helper to avoid endpoint drift.

Suggested tests:

- Feature tests for global disabled and emergency lockdown across `partners`, `partnerDetail`, `activity`, `settings`, `members`, `listings`, `messages`, and transactions.
- Tenant whitelist/feature-off tests for the Partner Time Banks panel backend APIs.
- React tests that the UI redirects or shows a translated disabled state when the backend returns 403.

Related modules affected:

- Tenant settings
- Super-admin controls
- Partner directory
- Activity/audit
- Member-facing federation pages

#### 5. Direct external member/listing fetches bypass per-partner allow flags

Severity: High

Evidence:

- `app/Services/FederationExternalPartnerService.php:202-232` stores per-partner flags including `allow_member_search`, `allow_listing_search`, `allow_messaging`, `allow_transactions`, `allow_events`, `allow_groups`, `allow_connections`, `allow_volunteering`, and `allow_member_sync`.
- `app/Http/Controllers/Api/FederationV2Controller.php:993-1001` fetches listings from a requested external partner without checking `allow_listing_search`.
- `app/Http/Controllers/Api/FederationV2Controller.php:1265-1273` fetches members from a requested external partner without checking `allow_member_search`.
- `app/Services/FederationExternalApiClient.php:909-929` scopes partner lookup by tenant and active/failed status, but it does not enforce operation allow flags.

Impact:

If an external partner is active but member or listing search is disabled, a user can still trigger those calls by selecting/directly supplying that external partner id. This violates admin configuration and partner data-sharing agreements.

Recommended fix:

- Add operation-specific partner validation before external fetches.
- Centralize this in `FederationExternalPartnerService`, for example `assertAllowed($partnerId, $tenantId, 'member_search')`.
- Return a translated 403 instead of silently returning empty results when the partner exists but the operation is disabled.
- Ensure bulk merge helpers also respect the same checks.

Suggested tests:

- Feature test: `members?partner_id=ext-N` returns 403 or no request when `allow_member_search=0`.
- Feature test: `listings?partner_id=ext-N` returns 403 or no request when `allow_listing_search=0`.
- Unit test: external API client is not called when the allow flag is false.
- React test: disabled external partner operations are hidden or show a translated unavailable state.

Related modules affected:

- External partners
- Member search
- Listings
- Admin partner settings
- Audit/logging

#### 6. Internal cross-tenant message delivery is not atomic

Severity: High

Evidence:

- `app/Http/Controllers/Api/FederationV2Controller.php:1911-1923` inserts the outbound sender copy.
- `app/Http/Controllers/Api/FederationV2Controller.php:1925-1937` inserts the inbound receiver copy.
- There is no `DB::transaction(...)` wrapping the two durable inserts and related audit setup in the shown block.

Impact:

A DB exception after the outbound insert but before the inbound insert can leave the sender seeing a delivered message that the receiver never got. This undermines trust, supportability, and audit consistency.

Recommended fix:

- Wrap the outbound/inbound durable writes and core audit rows in a single DB transaction.
- Move email/realtime side effects after commit.
- Store a shared message/thread correlation id to make sender/receiver copies auditable.

Suggested tests:

- Feature test that forces the second insert or audit write to fail and verifies no orphan outbound message remains.
- Feature test that successful send creates exactly one outbound and one inbound row with a shared correlation id.
- Delivery tests verifying notifications still happen after commit.

Related modules affected:

- Federation messages
- Notifications/email
- Realtime events
- Audit log

#### 7. Partner API exposes active users/listings outside the federation consent and visibility model

Severity: High

Evidence:

- `app/Http/Middleware/PartnerApiAuth.php:56-70` correctly binds tenant context and checks scopes.
- `app/Http/Controllers/Api/PartnerApi/PartnerV1Controller.php:43-73` lists all active users in the tenant; with `users.pii` scope it includes email.
- `app/Http/Controllers/Api/PartnerApi/PartnerV1Controller.php:76-96` returns a user by id without checking federation opt-in or profile visibility.
- `app/Http/Controllers/Api/PartnerApi/PartnerV1Controller.php:101-124` lists all active listings in the tenant without checking `federated_visibility` or owner opt-in.
- The federation search path is stricter: `app/Http/Controllers/Api/FederationV2Controller.php:310-321` counts listings only when `federated_visibility` and owner federation settings allow it.

Impact:

An API partner with `users.read`, `users.pii`, or `listings.read` can receive data that normal federation users would not see. That may be intentional for selected enterprise integrations, but the current implementation does not make that policy boundary explicit enough in code, consent, UI, or tests.

Recommended fix:

- Decide whether Partner API is a separate contractual export surface or must obey federation consent.
- If separate, add explicit admin UI/legal copy, audit events, and per-partner data scopes such as `users.read_public`, `users.read_consented`, `users.pii`.
- If not separate, join `federation_user_settings` and listing visibility exactly as federation search does.
- Add data minimization defaults: no email/PII unless a partner has a reviewed PII scope and the user/tenant consent permits it.

Suggested tests:

- Partner API tests for opted-out users and hidden profiles.
- Partner API tests for listings with `federated_visibility='none'`.
- Admin UI tests showing PII/data-sharing warning and scope labels.
- Translation tests for admin/API partner warning copy.

Related modules affected:

- Partner API
- Members
- Listings
- Privacy/compliance
- Admin partner management

#### 8. Public aggregate sharing exposes exact low-volume volunteering and organisation metrics

Severity: High

Evidence:

- `app/Http/Controllers/Api/FederationAggregateController.php:47-63` exposes a public aggregate endpoint gated by tenant slug, consent, and signing secret.
- `app/Http/Controllers/Api/FederationAggregateController.php:74-77` declares returned fields including `hours.total_approved`, `hours.by_month`, `hours.by_category`, and `partner_orgs.count`.
- `app/Services/CaringCommunity/FederationAggregateService.php:56-110` returns exact approved volunteer hours by total, month, and category, including category counts.
- `app/Services/CaringCommunity/FederationAggregateService.php:139-183` returns an exact count of approved/active volunteer organisations.

Impact:

For small tenants, exact monthly/category volunteering hours and exact organisation counts can reveal sensitive operational patterns even without names. This matters especially for caring, volunteering, and local community organisations.

Recommended fix:

- Add k-anonymity/minimum-threshold suppression for categories and months with low event/user counts.
- Bucket or round hours and organisation counts.
- Publish a documented aggregate schema version with privacy guarantees.
- Include the suppression policy in the admin aggregate consent UI.

Suggested tests:

- Unit tests for small-N category/month suppression.
- Feature tests that aggregate output for tenants below threshold omits or buckets sensitive metrics.
- React/admin tests that preview and consent UI explain suppression with translated strings.

Related modules affected:

- Volunteering
- Organisations
- Caring community
- Aggregate sharing
- Research/analytics

#### 9. External protocol recipient ids are coerced to integers

Severity: High

Evidence:

- `app/Http/Controllers/Api/FederationV2Controller.php:2091-2099` parses external message receiver ids from `ext-{partnerId}-{userId}` and casts the real receiver id to integer.
- `app/Http/Controllers/Api/FederationV2Controller.php:2855-2863` performs the same integer coercion for external transactions.
- `app/Http/Controllers/Api/FederationV2Controller.php:1265-1305` builds external member ids by concatenating the partner id with the remote id returned by the adapter.

Impact:

External platforms often use UUIDs, slugs, or opaque ids. Integer coercion makes some protocol adapters incompatible and can route messages/transfers to the wrong remote account if a string id collapses to `0` or a partial integer.

Recommended fix:

- Treat remote external ids as opaque strings.
- Store `external_user_id` as string wherever needed.
- Pass the remote id unchanged to protocol adapters.
- Only cast to integer for native/internal NEXUS users.

Suggested tests:

- Unit tests for TimeOverflow/Komunitin/Credit Commons adapters returning UUID/string remote ids.
- Feature tests for sending messages and transactions to an external UUID id.
- Regression test that `ext-1-abc-123` is parsed as remote id `abc-123`, not `0` or `123`.

Related modules affected:

- External protocols
- Messaging
- Time-credit transfers
- Federated identities

#### 10. External transaction recovery is alert-only, not state-resolving

Severity: High

Evidence:

- `app/Jobs/ReconcileFederationPendingTxJob.php:21-35` documents the job as a safety net that surfaces stuck pending rows and notes that future enhancement should call a partner status endpoint and auto-finalise.
- `app/Jobs/ReconcileFederationPendingTxJob.php:81-96` logs/reports stale pending transactions.
- `bootstrap/app.php:119-123` schedules the job every five minutes.

Impact:

The system can detect stale pending federated money movement, but cannot resolve it automatically. This leaves support teams with manual reconciliation during exactly the incidents where reliable state recovery matters most.

Recommended fix:

- Add a partner transaction-status capability to protocol adapters where supported.
- Store remote transaction ids and partner idempotency keys consistently.
- Auto-finalise, refund, or escalate with a structured admin task based on remote state.
- Add a Partner Time Banks admin queue for stuck transactions with action audit trails.

Suggested tests:

- Job test: stale pending with remote success becomes completed.
- Job test: stale pending with remote rejection is refunded and marked failed.
- Job test: unknown remote state remains pending and raises exactly one alert per configured window.
- Admin UI test for stuck transaction queue.

Related modules affected:

- Wallet/time credits
- External protocols
- Admin operations
- Audit/observability

### Medium

#### 11. External members/listings are merged only on the first page, making totals and pagination misleading

Severity: Medium

Evidence:

- `app/Http/Controllers/Api/FederationV2Controller.php:1246-1255` merges external members only when there is no cursor and no internal partner filter.
- The listings flow has the same pattern around `app/Http/Controllers/Api/FederationV2Controller.php:975-983`.

Impact:

The first page can show a mixed internal/external result set, but subsequent pages are internal-only. Totals, `hasMore`, sorting, and source distribution become confusing for users and hard to test.

Recommended fix:

- Return source-aware pagination metadata.
- Either paginate external sources separately or introduce a unified search index/cursor abstraction.
- Make the UI clear about source boundaries if unified pagination is not feasible.

Suggested tests:

- Feature tests for first/second page behavior with external and internal results.
- React tests that pagination labels and empty states remain accurate when external results are present.

Related modules affected:

- Search
- Members
- Listings
- External partners
- UX

#### 12. Initial sync counts all active listings rather than federated-visible listings

Severity: Medium

Evidence:

- `app/Jobs/FederationInitialSyncJob.php:98-102` computes local and partner listing counts for initial audit/log snapshots.
- `app/Jobs/FederationInitialSyncJob.php:181-186` counts all active listings for a tenant.
- The live partner directory count in `app/Http/Controllers/Api/FederationV2Controller.php:314-321` filters by `federated_visibility` and owner federation settings.

Impact:

Initial sync audit snapshots can overstate the data made visible to partners. This can confuse admins and weaken audit accuracy.

Recommended fix:

- Align `countActiveListings()` with the live federation visibility rules.
- Include both total active listings and federated-visible listings only if the distinction is explicit in the audit payload.

Suggested tests:

- Job unit test with active private listings and active federated-visible listings.
- Audit assertion that snapshot counts match directory/search visibility.

Related modules affected:

- Listings
- Audit
- Partner directory

#### 13. Hardcoded English remains in API/admin federation surfaces

Severity: Medium

Evidence:

- `app/Http/Controllers/Api/PartnerApi/PartnerV1Controller.php:92-94` returns `User not found.` directly.
- `app/Http/Controllers/Api/PartnerApi/PartnerV1Controller.php:379-391` returns hardcoded validation messages for webhook subscription errors.
- `app/Http/Controllers/Api/Admin/ApiPartnerAdminController.php:57-81` returns hardcoded `Partner not found.` and `name is required.`.
- `app/Http/Controllers/Api/Admin/ApiPartnerAdminController.php:183-200` returns hardcoded `Partner not found.`.
- `app/Services/FederationExternalPartnerService.php:160-199` returns hardcoded validation errors such as `Partner name is required`, `Base URL is required`, and invalid protocol text.

Impact:

This violates the project-wide no-hardcoded-user-facing-strings rule. API errors also bubble into admin toasts in several places, so English can appear in localized admin flows.

Recommended fix:

- Move all API/admin partner/federation errors into `lang/*` keys.
- Ensure React toasts prefer translated local fallback copy and avoid showing raw backend messages unless they are translation-key based or safe.
- Run i18n baseline/gaps checks after changes.

Suggested tests:

- Static i18n check additions for these controllers/services.
- Feature tests asserting translation-key-backed errors for common failure paths.
- React tests for localized admin API partner errors.

Related modules affected:

- Partner API
- Admin Partner Time Banks
- External partners
- i18n

#### 14. Frontend federation pages drift from HeroUI v3 component conventions

Severity: Medium

Evidence:

- `react-frontend/src/pages/federation/FederationMembersPage.tsx:395-426` uses `GlassCard` and a gradient custom retry button for an error state.
- `react-frontend/src/pages/federation/FederationEventsPage.tsx:268-279` implements an interactive filter as a clickable `Chip` with manual keyboard handling.
- `react-frontend/src/pages/federation/FederationEventsPage.tsx:303-309` uses a gradient custom retry button.
- `react-frontend/src/pages/federation/FederationPartnersPage.tsx:260-265` uses gradient avatar styling.
- Official HeroUI Button docs document standard variants, loading/pending states, icon-only states, and `onPress`; Table/Card docs provide framework primitives for structured data and cards.

Impact:

The UI is visually inconsistent with the current project guidance and increases accessibility/maintenance risk. The clickable `Chip` pattern is especially fragile compared with a `Switch`, `ToggleButton`, or `Button`.

Recommended fix:

- Replace improvised interactive chips with HeroUI controls (`Switch`, `ToggleButton`, segmented control, or `Button` as appropriate).
- Replace one-off gradient buttons with HeroUI `Button` variants and token-based styling.
- Use existing project tokens and HeroUI/Card primitives where card framing is necessary.
- Retain translations and existing loading/error/empty-state coverage.

Suggested tests:

- Vitest/Testing Library tests for keyboard interaction and accessible names on filters.
- Visual/Playwright pass for desktop/mobile federation pages.
- Run `cd react-frontend && npx tsc --noEmit` and `cd react-frontend && npm test` for touched frontend areas.

Related modules affected:

- React federation pages
- Partner Time Banks admin
- Accessibility
- Design system

#### 15. Batch message read marking does not emit the same realtime/read-receipt side effects as single-message read marking

Severity: Medium

Evidence:

- `app/Http/Controllers/Api/FederationV2Controller.php:2205-2238` single `markMessageRead` includes realtime/read receipt behavior.
- `app/Http/Controllers/Api/FederationV2Controller.php:2251-2281` batch mark-read updates rows but does not mirror the same side effects.

Impact:

Users can see inconsistent read state depending on whether the UI marks one message or a batch. This matters for support/debugging and realtime trust.

Recommended fix:

- Decide whether batch read should emit per-message read receipts, a compact batch event, or intentionally no realtime event.
- Implement the chosen behavior consistently and document it in tests.

Suggested tests:

- Feature test for batch read side effects.
- React test for read state after batch mark-read.

Related modules affected:

- Messaging
- Realtime
- Notifications

#### 16. External partner creation defaults several data-sharing flags to enabled

Severity: Medium

Evidence:

- `app/Services/FederationExternalPartnerService.php:224-231` defaults `allow_member_search`, `allow_listing_search`, `allow_messaging`, and `allow_transactions` to enabled when data is omitted; events/groups/connections/volunteering/member sync default disabled.

Impact:

An admin/API call that omits flags can accidentally create an external partner with member search, listing search, messaging, and transactions enabled. For enterprise privacy posture, least privilege should be the default.

Recommended fix:

- Default all external partner allow flags to false unless explicitly enabled.
- Add an admin setup wizard or review step for enabling high-risk permissions.
- Log permission changes in the federation audit log.

Suggested tests:

- Service unit test for omitted flags defaulting to false.
- Admin UI test that new external partner creation requires explicit permission selection.

Related modules affected:

- External partners
- Admin setup
- Privacy/compliance

### Low

#### 17. Route comments conflict with the implemented Partner Time Banks access rule

Severity: Low

Evidence:

- `react-frontend/src/App.tsx:123` and `react-frontend/src/App.tsx:1806-1807` describe the Partner Timebanks panel as super-admin-only.
- `react-frontend/src/lib/access.ts:80-90` documents the current owner decision that any admin may access the panel while sensitive setup pages are super-admin-only.
- `react-frontend/src/partners/PartnersRoute.tsx:34-45` implements the broader admin access rule.

Impact:

The implementation appears intentional, but contradictory comments make future access-control reviews easier to misread.

Recommended fix:

- Update comments in `App.tsx` to match the implemented rule.

Suggested tests:

- Existing PartnersRoute tests are likely enough. Add a comment-only check is not necessary.

Related modules affected:

- Partner Time Banks shell
- Admin access-control maintainability

#### 18. External message receiver names are HTML-escaped before storage

Severity: Low

Evidence:

- `app/Http/Controllers/Api/FederationV2Controller.php:1852-1854` states internal message subject/body are stored as plain text and escaped at render time.
- `app/Http/Controllers/Api/FederationV2Controller.php:2140-2144` HTML-escapes the external receiver name before storing/using it.

Impact:

This can cause double-escaping or inconsistent display across API, email, and React rendering. It is a smaller issue than the transaction and privacy findings, but it violates the local plain-text storage convention.

Recommended fix:

- Store external receiver names as plain text.
- Escape at output boundaries.
- Add a regression test for names containing quotes and angle brackets.

Suggested tests:

- Feature test for external message recipient name storage and rendered API response.
- React rendering test for escaped display.

Related modules affected:

- External messaging
- React message display
- Email rendering

## Missing Test Coverage

Priority missing coverage:

- External transaction idempotency for repeated client submissions and network retries.
- Frontend transfer modal idempotency key generation and pending-state behavior.
- `ext-*` route/detail contract coverage for partner and member ids.
- External UUID/string remote id support for messaging and transactions.
- Translation context isolation by `external_partner_id`.
- Operation gate coverage for `partners`, `partnerDetail`, `activity`, and federation settings under global disabled, emergency lockdown, tenant disabled, and whitelist removal states.
- External partner allow-flag enforcement for direct member/listing partner filters.
- Partner API consent/visibility tests for opted-out users and non-federated listings.
- Aggregate privacy tests for small-N volunteering/organisation metrics.
- Cross-tenant message atomicity tests.
- Initial sync listing count parity with live federation visibility.
- Batch mark-read realtime/read-receipt behavior.
- Admin UI tests for least-privilege external partner permission defaults.

Existing coverage is strong enough to build on rather than rewrite. The missing pieces are largely edge/contract tests around privacy, finance, and cross-source consistency.

## i18n And Accessibility Issues

i18n:

- React federation pages generally use `t(...)` namespaces.
- Several backend/admin/Partner API errors remain hardcoded in English; see Finding 13.
- Backend errors can surface in React toasts, so backend translation discipline matters for the admin UX.
- Future fixes must update `lang/*` and `react-frontend/public/locales/*` as appropriate, then run the i18n checks.

Accessibility:

- Loading states often use `Spinner`, `role="status"`, and translated labels, which is good.
- Error states generally use `role="alert"`.
- The clickable `Chip` in `FederationEventsPage.tsx:268-279` is keyboard-patched manually; use a standard interactive control.
- External member profile blocking is communicated by toast only (`FederationMembersPage.tsx:261-270`). Consider an inline accessible explanation or disabled action with tooltip/description if external profile support remains intentionally unavailable.

## Security, Privacy, And Multi-Tenant Risks

Strengths:

- `PartnerApiAuth` binds `TenantContext` to the partner tenant and enforces scopes/rate limits (`app/Http/Middleware/PartnerApiAuth.php:56-102`).
- External webhooks authenticate before payload parsing and require nonce replay protection (`app/Http/Controllers/Api/FederationExternalWebhookController.php:92-132`).
- External partner service blocks private/internal URLs for SSRF prevention (`app/Services/FederationExternalPartnerService.php:31-63`).
- External API logs redact sensitive payload fields (`app/Services/FederationExternalApiClient.php:941-994`).
- Federated identities were later scoped by tenant with unique indexes (`database/migrations/2026_05_04_140000_scope_federated_identities_by_tenant.php:77-88`).

Risks:

- Duplicate external transfers can debit/credit twice.
- Translation context can cross external partner boundaries.
- Partner API user/listing exports bypass federation consent/visibility unless this is explicitly a separate contractual surface.
- Public aggregate feed needs small-N suppression/bucketing for volunteering and organisation metrics.
- Operation gates do not consistently cover data-disclosing read endpoints.
- External partner allow flags are not uniformly enforced.
- External ids are not treated as opaque identifiers.

## API Contract Issues

- `ext-*` ids are returned by list/search flows but blocked by global numeric route constraints on detail routes.
- External partner/member ids are strings in frontend state but are sometimes coerced to integers before protocol calls.
- External member detail/profile is intentionally unavailable in the React members page, but messaging and transfers still rely on external ids. The product contract should state which external actions are supported.
- Partner API exposes broad `users.read`, `users.pii`, and `listings.read` behavior without mirroring federation visibility.
- External search pagination merges first-page external results into an internal cursor model.
- API error localization is inconsistent.

## Frontend UX Gaps

- External members appear in search but profile pages are blocked by toast. This is understandable as a temporary product decision, but it is not enterprise-grade.
- Transfer modal lacks durable idempotency key handling and should show stronger pending/replay protection.
- Some error/retry controls use custom gradient buttons instead of HeroUI variants.
- Interactive filters should use standard controls rather than clickable chips.
- Partner Time Banks panel access is implemented coherently in `access.ts` and `PartnersRoute`, but comments in `App.tsx` are stale.
- Admin aggregate consent UI should explain small-N suppression once implemented.

## Backend Resilience Gaps

- External money movement has saga staging and scheduled alerting, but no durable pre-branch idempotency and no automatic remote-state reconciliation.
- Internal federation message delivery is not atomic.
- External partner fetch helpers do not enforce allow flags at call boundaries.
- Initial sync audit snapshots can over-count listings.
- Webhook authentication and nonce handling are strong, but partner secret uniqueness/collision policy should be documented and tested if bearer secret auth continues to scan partners globally.

## Recommended Implementation Order

1. Fix external transaction idempotency end to end:
   - Backend durable idempotency before internal/external branch.
   - Frontend idempotency key and pending state.
   - Tests for duplicate external submissions.
2. Fix translation context isolation by `external_partner_id`.
3. Normalize external id/API contracts:
   - Explicit external partner/member routes.
   - Opaque remote ids.
   - Contract tests for list-to-detail/action ids.
4. Apply operation gates consistently across federation read/settings/activity endpoints.
5. Enforce external partner allow flags for direct and bulk external fetches.
6. Make internal message delivery atomic.
7. Decide and implement Partner API consent/visibility policy.
8. Add small-N suppression/bucketing for aggregate volunteering/organisation metrics.
9. Improve external transaction reconciliation from alert-only to remote-state resolution.
10. Align initial sync counts with federation visibility.
11. Replace hardcoded backend/admin strings with translations.
12. Clean up frontend HeroUI/accessibility drift in member-facing federation pages.
13. Improve external pagination/source UX.
14. Update stale Partner Time Banks access comments.

## Validation Commands For Follow-Up Implementation

Run the narrowest relevant tests while implementing each fix, then broaden before final handoff:

- `vendor/bin/phpunit tests/Laravel/Feature/Federation`
- `vendor/bin/phpunit tests/Laravel/Feature/Controllers/FederationV2ControllerTest.php`
- `vendor/bin/phpunit tests/Laravel/Feature/PartnerApi`
- `vendor/bin/phpunit tests/Laravel/Unit/Jobs/ReconcileFederationPendingTxJobTest.php`
- `vendor/bin/phpunit tests/Laravel/Unit/Services/FederationExternalApiClientTest.php`
- `vendor/bin/phpunit tests/Laravel/Unit/Listeners/IngestFederatedVolunteerOpportunityTest.php`
- `cd react-frontend && npx tsc --noEmit`
- `cd react-frontend && npm test -- federation`
- `npm run check:i18n:baseline`
- `npm run check:i18n:gaps`
- `npm run check:docs` only if public docs are touched.
- `npm run check:version` only if release/version metadata is touched.

If running PHP tests through Docker and tests touch encryption, remember the project instruction to pass the fixed test `APP_KEY` explicitly to `docker exec`.

## Autonomous Implementation Prompt

You are working in Project NEXUS V1.5. Read `AGENTS.md` first and follow it strictly. Use `.local-docs-archive/partner-time-banks-federation-audit-2026-07-07.md` as the source of truth for the Partner Time Banks federation production-readiness work.

Implement fixes in priority order from the audit. Bring the Partner Time Banks federation module into production-ready, enterprise-grade condition, with special attention to volunteering and organisations integration points. Preserve existing platform conventions: React is the primary UI, HeroUI v3 and Tailwind CSS 4 are required for frontend changes, all user-facing strings must use translations, all new source files need the AGPL SPDX header, and legacy PHP views under `views/` must not be touched except to note live dependencies if directly relevant.

Start with the critical issues: external transfer idempotency before any debit or external call, frontend idempotency key support, and external message translation context isolation. Then fix external id contracts, operation gates, external partner allow-flag enforcement, internal message atomicity, Partner API consent/visibility policy, aggregate privacy suppression, external transaction reconciliation, and the listed frontend/admin UX hardening.

Add or update Laravel, service, listener/job, and React tests for each changed behavior. Update PHP and React translations for every user-facing string. Update `CHANGELOG.md` under `[Unreleased]` for release-relevant changes and refresh the bundled changelog with `npm --prefix react-frontend run copy-changelog`. Run the relevant validation commands listed in the audit and report any commands that could not be run.

Do not deploy. Do not push to the backup remote. Commit directly to `main` only if Jasper explicitly asks you to commit.
