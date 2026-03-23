# React Frontend Test Coverage

> Last updated: 2026-03-23
> Total: 259 test files passing (0 failures)

## Coverage Summary

| Area | Source Files | Tested | Coverage |
|------|-------------|--------|----------|
| hooks/ | 14 | 14 | 100% |
| lib/ | 16 | 16 | 100% |
| contexts/ | 8 | 7 | 87% |
| data/ | 2 | 1 | 50% |
| pages/ | 152 | 125 | 82% |
| components/ | 113 | 59 | 52% |
| admin/ | 207 | 181 | 87% |
| **Total** | **512** | **403** | **~79%** |

> Source file counts exclude `index.ts` barrel files and `types.ts` definition-only files.
> Admin coverage is high because batch test files (e.g., `SystemModules.test.tsx`) cover many modules with render-crash tests.

---

## Fully Covered Areas (100%)

### hooks/ (14/14)

| File | Test File | Status |
|------|-----------|--------|
| useApi | useApi.test.ts | PASS |
| useApiErrorHandler | useApiErrorHandler.test.ts | PASS |
| useAppUpdate | useAppUpdate.test.ts | PASS |
| useDraftPersistence | useDraftPersistence.test.ts | PASS |
| useFeedTracking | useFeedTracking.test.ts | PASS |
| useGeolocation | useGeolocation.test.ts | PASS |
| useHeaderScroll | useHeaderScroll.test.ts | PASS |
| useLegalDocument | useLegalDocument.test.ts | PASS |
| useLegalGate | useLegalGate.test.ts | PASS |
| useMediaQuery | useMediaQuery.test.ts | PASS |
| useMenus | useMenus.test.ts | PASS |
| usePageTitle | usePageTitle.test.ts | PASS |
| usePushNotifications | usePushNotifications.test.ts | PASS |
| useSocialInteractions | useSocialInteractions.test.ts | PASS |

### lib/ (16/16)

| File | Test File | Status |
|------|-----------|--------|
| api | api.test.ts | PASS |
| api-schemas | api-schemas.test.ts | PASS |
| api-validation | api-validation.test.ts | PASS |
| chartColors | chartColors.test.ts | PASS |
| compress-image | compress-image.test.ts | PASS |
| exchange-status | exchange-status.test.ts | PASS |
| helpers | helpers.test.ts | PASS |
| logger | logger.test.ts | PASS |
| map-config | map-config.test.ts | PASS |
| map-styles | map-styles.test.ts | PASS |
| nav-helpers | nav-helpers.test.ts | PASS |
| performance | performance.test.ts | PASS |
| sentry | sentry.test.ts | PASS |
| tenant-routing | tenant-routing.test.ts | PASS |
| validation | validation.test.ts | PASS |
| webauthn | webauthn.test.ts | PASS |

---

## Contexts (7/8 -- 87%)

| File | Test File | Status |
|------|-----------|--------|
| AuthContext | AuthContext.test.tsx + __tests__/AuthContext.test.tsx | PASS |
| CookieConsentContext | CookieConsentContext.test.tsx | PASS |
| NotificationsContext | NotificationsContext.test.tsx + __tests__/NotificationsContext.test.tsx | PASS |
| PusherContext | PusherContext.test.tsx | PASS |
| TenantContext | TenantContext.test.tsx + __tests__/TenantContext.test.tsx | PASS |
| ThemeContext | ThemeContext.test.tsx | PASS |
| ToastContext | ToastContext.test.tsx | PASS |
| **MenuContext** | -- | **UNTESTED** |

## Data (1/2 -- 50%)

| File | Test File | Status |
|------|-----------|--------|
| emoji-data | emoji-data.test.ts | PASS |
| **sdg-goals** | -- | **UNTESTED** |

---

## Pages (125/152 -- 82%)

### Tested Pages

| Directory | File | Test File | Status |
|-----------|------|-----------|--------|
| about/ | ImpactReportPage | ImpactReportPage.test.tsx | PASS |
| about/ | ImpactSummaryPage | ImpactSummaryPage.test.tsx | PASS |
| about/ | PartnerPage | PartnerPage.test.tsx | PASS |
| about/ | SocialPrescribingPage | SocialPrescribingPage.test.tsx | PASS |
| about/ | StrategicPlanPage | StrategicPlanPage.test.tsx | PASS |
| about/ | TimebankingGuidePage | TimebankingGuidePage.test.tsx | PASS |
| achievements/ | AchievementsPage | AchievementsPage.test.tsx | PASS |
| activity/ | ActivityDashboardPage | ActivityDashboardPage.test.tsx | PASS |
| auth/ | ForgotPasswordPage | ForgotPasswordPage.test.tsx | PASS |
| auth/ | LoginPage | LoginPage.test.tsx | PASS |
| auth/ | RegisterPage | RegisterPage.test.tsx | PASS |
| auth/ | ResetPasswordPage | ResetPasswordPage.test.tsx | PASS |
| auth/ | VerifyEmailPage | VerifyEmailPage.test.tsx | PASS |
| auth/ | VerifyIdentityPage | __tests__/VerifyIdentityPage.test.tsx | PASS |
| blog/ | BlogPage | BlogPage.test.tsx | PASS |
| blog/ | BlogPostPage | BlogPostPage.test.tsx | PASS |
| chat/ | AiChatPage | AiChatPage.test.tsx | PASS |
| connections/ | ConnectionsPage | ConnectionsPage.test.tsx | PASS |
| dashboard/ | DashboardPage | DashboardPage.test.tsx | PASS |
| errors/ | ComingSoonPage | ComingSoonPage.test.tsx | PASS |
| errors/ | NotFoundPage | NotFoundPage.test.tsx | PASS |
| events/ | CreateEventPage | CreateEventPage.test.tsx | PASS |
| events/ | EventDetailPage | EventDetailPage.test.tsx | PASS |
| events/ | EventReminderSettings | EventReminderSettings.test.tsx | PASS |
| events/ | EventsPage | EventsPage.test.tsx | PASS |
| exchanges/ | ExchangeDetailPage | ExchangeDetailPage.test.tsx | PASS |
| exchanges/ | ExchangesPage | ExchangesPage.test.tsx | PASS |
| exchanges/ | RequestExchangePage | RequestExchangePage.test.tsx | PASS |
| federation/ | FederationConnectionsPage | FederationConnectionsPage.test.tsx | PASS |
| federation/ | FederationEventsPage | FederationEventsPage.test.tsx | PASS |
| federation/ | FederationHubPage | FederationHubPage.test.tsx | PASS |
| federation/ | FederationListingsPage | FederationListingsPage.test.tsx | PASS |
| federation/ | FederationMemberProfilePage | FederationMemberProfilePage.test.tsx | PASS |
| federation/ | FederationMembersPage | FederationMembersPage.test.tsx | PASS |
| federation/ | FederationMessagesPage | FederationMessagesPage.test.tsx | PASS |
| federation/ | FederationOnboardingPage | FederationOnboardingPage.test.tsx | PASS |
| federation/ | FederationPartnerDetailPage | FederationPartnerDetailPage.test.tsx | PASS |
| federation/ | FederationPartnersPage | FederationPartnersPage.test.tsx | PASS |
| federation/ | FederationSettingsPage | FederationSettingsPage.test.tsx | PASS |
| feed/ | FeedPage | FeedPage.test.tsx | PASS |
| feed/ | HashtagPage | HashtagPage.test.tsx | PASS |
| feed/ | HashtagsDiscoveryPage | HashtagsDiscoveryPage.test.tsx | PASS |
| goals/ | GoalsPage | GoalsPage.test.tsx | PASS |
| goals/components/ | GoalCheckinModal | __tests__/GoalCheckinModal.test.tsx | PASS |
| goals/components/ | GoalProgressHistory | __tests__/GoalProgressHistory.test.tsx | PASS |
| goals/components/ | GoalReminderToggle | __tests__/GoalReminderToggle.test.tsx | PASS |
| goals/components/ | GoalTemplatePickerModal | __tests__/GoalTemplatePickerModal.test.tsx | PASS |
| group-exchanges/ | CreateGroupExchangePage | CreateGroupExchangePage.test.tsx | PASS |
| group-exchanges/ | GroupExchangeDetailPage | GroupExchangeDetailPage.test.tsx | PASS |
| group-exchanges/ | GroupExchangesPage | GroupExchangesPage.test.tsx | PASS |
| groups/ | CreateGroupPage | CreateGroupPage.test.tsx | PASS |
| groups/ | GroupDetailPage | GroupDetailPage.test.tsx | PASS |
| groups/ | GroupsPage | GroupsPage.test.tsx | PASS |
| groups/components/ | PinnedAnnouncementsBanner | __tests__/PinnedAnnouncementsBanner.test.tsx | PASS |
| groups/tabs/ | GroupAnnouncementsTab | __tests__/GroupAnnouncementsTab.test.tsx | PASS |
| groups/tabs/ | GroupChatroomsTab | __tests__/GroupChatroomsTab.test.tsx | PASS |
| groups/tabs/ | GroupDiscussionTab | __tests__/GroupDiscussionTab.test.tsx | PASS |
| groups/tabs/ | GroupEventsTab | __tests__/GroupEventsTab.test.tsx | PASS |
| groups/tabs/ | GroupFeedTab | __tests__/GroupFeedTab.test.tsx | PASS |
| groups/tabs/ | GroupFilesTab | __tests__/GroupFilesTab.test.tsx | PASS |
| groups/tabs/ | GroupMembersTab | __tests__/GroupMembersTab.test.tsx | PASS |
| groups/tabs/ | GroupSubgroupsTab | __tests__/GroupSubgroupsTab.test.tsx | PASS |
| groups/tabs/ | GroupTasksTab | __tests__/GroupTasksTab.test.tsx | PASS |
| help/ | HelpCenterPage | HelpCenterPage.test.tsx | PASS |
| ideation/ | CampaignDetailPage | CampaignDetailPage.test.tsx | PASS |
| ideation/ | CampaignsPage | CampaignsPage.test.tsx | PASS |
| ideation/ | ChallengeDetailPage | ChallengeDetailPage.test.tsx | PASS |
| ideation/ | CreateChallengePage | CreateChallengePage.test.tsx | PASS |
| ideation/ | IdeaDetailPage | IdeaDetailPage.test.tsx | PASS |
| ideation/ | IdeationPage | IdeationPage.test.tsx | PASS |
| ideation/ | OutcomesDashboardPage | OutcomesDashboardPage.test.tsx | PASS |
| jobs/ | CreateJobPage | CreateJobPage.test.tsx | PASS |
| jobs/ | JobAlertsPage | JobAlertsPage.test.tsx | PASS |
| jobs/ | JobAnalyticsPage | JobAnalyticsPage.test.tsx | PASS |
| jobs/ | JobDetailPage | JobDetailPage.test.tsx | PASS |
| jobs/ | JobsPage | JobsPage.test.tsx | PASS |
| jobs/ | MyApplicationsPage | MyApplicationsPage.test.tsx | PASS |
| leaderboard/ | LeaderboardPage | LeaderboardPage.test.tsx | PASS |
| listings/ | CreateListingPage | CreateListingPage.test.tsx | PASS |
| listings/ | ListingDetailPage | ListingDetailPage.test.tsx | PASS |
| listings/ | ListingsPage | ListingsPage.test.tsx | PASS |
| members/ | MembersPage | MembersPage.test.tsx | PASS |
| messages/ | ConversationPage | ConversationPage.test.tsx | PASS |
| messages/ | MessagesPage | MessagesPage.test.tsx | PASS |
| messages/components/ | MessageBubble | MessageBubble.test.tsx | PASS |
| messages/components/ | MessageInputArea | MessageInputArea.test.tsx | PASS |
| messages/components/ | VoiceMessagePlayer | VoiceMessagePlayer.test.tsx | PASS |
| notifications/ | NotificationsPage | NotificationsPage.test.tsx | PASS |
| onboarding/ | OnboardingPage | OnboardingPage.test.tsx | PASS |
| organisations/ | OrganisationDetailPage | OrganisationDetailPage.test.tsx | PASS |
| organisations/ | OrganisationsPage | OrganisationsPage.test.tsx | PASS |
| organisations/ | RegisterOrganisationPage | RegisterOrganisationPage.test.tsx | PASS |
| profile/ | ProfilePage | ProfilePage.test.tsx | PASS |
| public/ | AboutPage | AboutPage.test.tsx | PASS |
| public/ | AccessibilityPage | AccessibilityPage.test.tsx | PASS |
| public/ | ContactPage | ContactPage.test.tsx | PASS |
| public/ | CookiesPage | CookiesPage.test.tsx | PASS |
| public/ | FaqPage | FaqPage.test.tsx | PASS |
| public/ | HomePage | HomePage.test.tsx | PASS |
| public/ | LegalHubPage | LegalHubPage.test.tsx | PASS |
| public/ | MaintenancePage | MaintenancePage.test.tsx | PASS |
| public/ | PrivacyPage | PrivacyPage.test.tsx | PASS |
| public/ | TermsPage | TermsPage.test.tsx | PASS |
| resources/ | ResourcesPage | ResourcesPage.test.tsx | PASS |
| search/ | SearchPage | SearchPage.test.tsx | PASS |
| settings/ | SettingsPage | SettingsPage.test.tsx | PASS |
| settings/tabs/ | LinkedAccountsTab | LinkedAccountsTab.test.tsx | PASS |
| settings/tabs/ | NotificationsTab | NotificationsTab.test.tsx | PASS |
| settings/tabs/ | PrivacyTab | PrivacyTab.test.tsx | PASS |
| settings/tabs/ | ProfileTab | ProfileTab.test.tsx | PASS |
| settings/tabs/ | SecurityTab | SecurityTab.test.tsx | PASS |
| settings/tabs/ | SkillsTab | SkillsTab.test.tsx | PASS |
| volunteering/ | CertificatesTab | CertificatesTab.test.tsx | PASS |
| volunteering/ | CreateOpportunityPage | CreateOpportunityPage.test.tsx | PASS |
| volunteering/ | CredentialVerificationTab | CredentialVerificationTab.test.tsx | PASS |
| volunteering/ | EmergencyAlertsTab | EmergencyAlertsTab.test.tsx | PASS |
| volunteering/ | GroupSignUpTab | GroupSignUpTab.test.tsx | PASS |
| volunteering/ | HoursReviewTab | HoursReviewTab.test.tsx | PASS |
| volunteering/ | OpportunityDetailPage | OpportunityDetailPage.test.tsx | PASS |
| volunteering/ | RecommendedShiftsTab | RecommendedShiftsTab.test.tsx | PASS |
| volunteering/ | ShiftSwapsTab | ShiftSwapsTab.test.tsx | PASS |
| volunteering/ | VolunteeringPage | VolunteeringPage.test.tsx | PASS |
| volunteering/ | WaitlistTab | WaitlistTab.test.tsx | PASS |
| volunteering/ | WellbeingTab | WellbeingTab.test.tsx | PASS |
| wallet/ | WalletPage | WalletPage.test.tsx | PASS |

### Untested Pages (27 files)

| Directory | File | Priority | Notes |
|-----------|------|----------|-------|
| about/ | RelatedPages | Low | Helper component, not a full page |
| jobs/ | BiasAuditPage | Medium | New feature (2026-03) |
| jobs/ | EmployerBrandPage | Medium | New feature (2026-03) |
| jobs/ | EmployerOnboardingPage | Medium | New feature (2026-03) |
| jobs/ | JobKanbanPage | Medium | New feature (2026-03) |
| jobs/ | TalentSearchPage | Medium | New feature (2026-03) |
| kb/ | KBArticlePage | Medium | Knowledge base article viewer |
| kb/ | KnowledgeBasePage | Medium | Knowledge base landing page |
| matches/ | MatchesPage | Medium | Core feature |
| matches/ | MatchesRedirectPage | Low | Simple redirect component |
| newsletter/ | NewsletterUnsubscribePage | Low | Simple unsubscribe form |
| nexus-score/ | NexusScorePage | Medium | Gamification feature |
| platform/ | PlatformDisclaimerPage | Low | Static legal page |
| platform/ | PlatformPrivacyPage | Low | Static legal page |
| platform/ | PlatformTermsPage | Low | Static legal page |
| polls/ | PollsPage | Medium | Community feature |
| public/ | AcceptableUsePage | Low | Static legal page |
| public/ | CommunityGuidelinesPage | Low | Static legal page |
| public/ | CustomPage | Medium | Dynamic CMS page renderer |
| public/ | DevelopmentStatusPage | Low | Dev-only status page |
| public/ | LegalVersionHistoryPage | Low | Legal version history |
| skills/ | SkillsBrowsePage | Medium | Skills discovery |
| volunteering/ | AccessibilityTab | Medium | Volunteering tab |
| volunteering/ | CommunityProjectsTab | Medium | Volunteering tab |
| volunteering/ | DonationsTab | Medium | Volunteering tab |
| volunteering/ | ExpensesTab | Medium | Volunteering tab |
| volunteering/ | SafeguardingTab | Medium | Volunteering tab |

---

## Components (59/113 -- 52%)

### Tested Components

| Directory | File | Test File | Status |
|-----------|------|-----------|--------|
| compose/ | ComposeHub | ComposeHub.test.tsx | PASS |
| compose/shared/ | AiAssistButton | __tests__/AiAssistButton.test.tsx | PASS |
| compose/shared/ | CharacterCount | CharacterCount.test.tsx | PASS |
| compose/shared/ | EmojiPicker | EmojiPicker.test.tsx | PASS |
| compose/shared/ | GroupSelector | __tests__/GroupSelector.test.tsx | PASS |
| compose/shared/ | LinkPreview | LinkPreview.test.tsx | PASS |
| compose/shared/ | SdgGoalsPicker | __tests__/SdgGoalsPicker.test.tsx | PASS |
| compose/shared/ | TemplatePicker | __tests__/TemplatePicker.test.tsx | PASS |
| compose/shared/ | VoiceInput | VoiceInput.test.tsx | PASS |
| compose/tabs/ | PollTab | __tests__/PollTab.test.tsx | PASS |
| endorsements/ | EndorseButton | __tests__/EndorseButton.test.tsx | PASS |
| endorsements/ | TopEndorsedWidget | __tests__/TopEndorsedWidget.test.tsx | PASS |
| feed/ | FeedCard | FeedCard.test.tsx | PASS |
| feed/sidebar/ | ProfileCardWidget | __tests__/ProfileCardWidget.test.tsx | PASS |
| feedback/ | AppUpdateModal | AppUpdateModal.test.tsx | PASS |
| feedback/ | CookieConsentBanner | CookieConsentBanner.test.tsx | PASS |
| feedback/ | EmptyState | EmptyState.test.tsx | PASS |
| feedback/ | ErrorBoundary | ErrorBoundary.test.tsx | PASS |
| feedback/ | FeatureErrorBoundary | FeatureErrorBoundary.test.tsx | PASS |
| feedback/ | LoadingScreen | LoadingScreen.test.tsx | PASS |
| feedback/ | OfflineIndicator | OfflineIndicator.test.tsx | PASS |
| feedback/ | SessionExpiredModal | SessionExpiredModal.test.tsx | PASS |
| feedback/ | UpdateAvailableBanner | __tests__/UpdateAvailableBanner.test.tsx | PASS |
| hashtags/ | HashtagRenderer | __tests__/HashtagRenderer.test.tsx | PASS |
| hashtags/ | TrendingHashtags | __tests__/TrendingHashtags.test.tsx | PASS |
| layout/ | DevelopmentStatusBanner | __tests__/DevelopmentStatusBanner.test.tsx | PASS |
| layout/ | Footer | Footer.test.tsx | PASS |
| layout/ | Layout | Layout.test.tsx | PASS |
| layout/ | MegaMenu | __tests__/MegaMenu.test.tsx | PASS |
| layout/ | MobileDrawer | MobileDrawer.test.tsx | PASS |
| layout/ | MobileTabBar | MobileTabBar.test.tsx | PASS |
| layout/ | Navbar | Navbar.test.tsx | PASS |
| layout/ | QuickCreateMenu | __tests__/QuickCreateMenu.test.tsx | PASS |
| layout/ | SearchOverlay | __tests__/SearchOverlay.test.tsx | PASS |
| legal/ | CustomLegalDocument | CustomLegalDocument.test.tsx | PASS |
| listings/ | FeaturedBadge | __tests__/FeaturedBadge.test.tsx | PASS |
| location/ | DistanceBadge | DistanceBadge.test.tsx | PASS |
| navigation/ | Breadcrumbs | Breadcrumbs.test.tsx | PASS |
| routing/ | FeatureGate | FeatureGate.test.tsx | PASS |
| routing/ | ProtectedRoute | ProtectedRoute.test.tsx | PASS |
| routing/ | ScrollToTop | ScrollToTop.test.tsx | PASS |
| routing/ | TenantShell | TenantShell.test.tsx | PASS |
| security/ | BiometricSettings | BiometricSettings.test.tsx | PASS |
| seo/ | PageMeta | PageMeta.test.tsx | PASS |
| ui/ | AlgorithmLabel | __tests__/AlgorithmLabel.test.tsx | PASS |
| ui/ | BackToTop | BackToTop.test.tsx | PASS |
| ui/ | DynamicIcon | __tests__/DynamicIcon.test.tsx | PASS |
| ui/ | GlassButton | GlassButton.test.tsx | PASS |
| ui/ | GlassCard | GlassCard.test.tsx | PASS |
| ui/ | GlassInput | GlassInput.test.tsx | PASS |
| ui/ | ImagePlaceholder | __tests__/ImagePlaceholder.test.tsx | PASS |
| ui/ | LevelProgress | LevelProgress.test.tsx | PASS |
| ui/ | Skeletons | Skeletons.test.tsx | PASS |
| verification/ | VerificationBadge | __tests__/VerificationBadge.test.tsx | PASS |
| wallet/ | CategorySelect | __tests__/CategorySelect.test.tsx | PASS |
| wallet/ | CommunityFundCard | __tests__/CommunityFundCard.test.tsx | PASS |
| wallet/ | DonateModal | __tests__/DonateModal.test.tsx | PASS |
| wallet/ | RatingModal | __tests__/RatingModal.test.tsx | PASS |
| wallet/ | TransferModal | __tests__/TransferModal.test.tsx | PASS |

> Note: `SidebarWidgets.test.tsx` and `LocationComponents.test.tsx` exist as aggregate test files but test widget/component rendering patterns rather than covering individual source files listed below.

### Untested Components (54 files)

#### Feed Sidebar (10 files)
| File | Path | Priority |
|------|------|----------|
| CommunityPulseWidget | feed/sidebar/CommunityPulseWidget.tsx | Medium |
| FeedSidebar | feed/sidebar/FeedSidebar.tsx | Medium |
| FriendsWidget | feed/sidebar/FriendsWidget.tsx | Medium |
| PeopleYouMayKnowWidget | feed/sidebar/PeopleYouMayKnowWidget.tsx | Medium |
| PopularGroupsWidget | feed/sidebar/PopularGroupsWidget.tsx | Medium |
| QuickActionsWidget | feed/sidebar/QuickActionsWidget.tsx | Medium |
| SuggestedListingsWidget | feed/sidebar/SuggestedListingsWidget.tsx | Medium |
| TopCategoriesWidget | feed/sidebar/TopCategoriesWidget.tsx | Medium |
| UpcomingEventsWidget | feed/sidebar/UpcomingEventsWidget.tsx | Medium |
| WidgetSkeleton | feed/sidebar/WidgetSkeleton.tsx | Low |

#### Feed (7 files)
| File | Path | Priority |
|------|------|----------|
| FeedContentRenderer | feed/FeedContentRenderer.tsx | High |
| FeedModeToggle | feed/FeedModeToggle.tsx | Medium |
| LocationRadiusFilter | feed/LocationRadiusFilter.tsx | Medium |
| MobileFAB | feed/MobileFAB.tsx | Low |
| ShareButton | feed/ShareButton.tsx | Medium |
| StoriesBar | feed/StoriesBar.tsx | Medium |
| SubFilterChips | feed/SubFilterChips.tsx | Medium |

#### Compose (8 files)
| File | Path | Priority |
|------|------|----------|
| ComposeSubmitContext | compose/ComposeSubmitContext.tsx | Medium |
| MobileComposeOverlay | compose/MobileComposeOverlay.tsx | Medium |
| ComposeEditor | compose/shared/ComposeEditor.tsx | High |
| ImageUploader | compose/shared/ImageUploader.tsx | High |
| MultiImageUploader | compose/shared/MultiImageUploader.tsx | Medium |
| VideoUploader | compose/shared/VideoUploader.tsx | Medium |
| EventTab | compose/tabs/EventTab.tsx | Medium |
| GoalTab | compose/tabs/GoalTab.tsx | Medium |
| ListingTab | compose/tabs/ListingTab.tsx | Medium |
| PostTab | compose/tabs/PostTab.tsx | Medium |

#### Location (5 files)
| File | Path | Priority |
|------|------|----------|
| EntityMapView | location/EntityMapView.tsx | Medium |
| GoogleMapsProvider | location/GoogleMapsProvider.tsx | Low |
| LocationMap | location/LocationMap.tsx | Medium |
| LocationMapCard | location/LocationMapCard.tsx | Medium |
| PlaceAutocompleteInput | location/PlaceAutocompleteInput.tsx | High |

> Note: `LocationComponents.test.tsx` exists but tests the DistanceBadge component, not these files.

#### Layout / Navigation (2 files)
| File | Path | Priority |
|------|------|----------|
| NotificationFlyout | layout/NotificationFlyout.tsx | High |
| MenuNavItems | navigation/MenuNavItems.tsx | Medium |

#### Legal (2 files)
| File | Path | Priority |
|------|------|----------|
| LegalAcceptanceGate | legal/LegalAcceptanceGate.tsx | High |
| PlatformLegalPage | legal/PlatformLegalPage.tsx | Medium |

#### Ideation Team Components (3 files)
| File | Path | Priority |
|------|------|----------|
| TeamChatrooms | ideation/TeamChatrooms.tsx | Medium |
| TeamDocuments | ideation/TeamDocuments.tsx | Medium |
| TeamTasks | ideation/TeamTasks.tsx | Medium |

#### Listings (2 files)
| File | Path | Priority |
|------|------|----------|
| ListingAnalyticsPanel | listings/ListingAnalyticsPanel.tsx | Medium |
| SkillTagsInput | listings/SkillTagsInput.tsx | Medium |

#### Search (2 files)
| File | Path | Priority |
|------|------|----------|
| AdvancedSearchFilters | search/AdvancedSearchFilters.tsx | Medium |
| SavedSearches | search/SavedSearches.tsx | Medium |

#### Social (3 files)
| File | Path | Priority |
|------|------|----------|
| CommentsSection | social/CommentsSection.tsx | High |
| LikersModal | social/LikersModal.tsx | Medium |
| ShareButton | social/ShareButton.tsx | Medium |

#### Other (7 files)
| File | Path | Priority |
|------|------|----------|
| LanguageSwitcher | LanguageSwitcher.tsx | Medium |
| AvailabilityGrid | availability/AvailabilityGrid.tsx | Medium |
| TenantLogo | branding/TenantLogo.tsx | Low |
| MessageContextCard | messages/MessageContextCard.tsx | Medium |
| ProfileFeed | profile/ProfileFeed.tsx | Medium |
| ReviewModal | reviews/ReviewModal.tsx | High |
| SkillSelector | skills/SkillSelector.tsx | Medium |
| SubAccountsManager | subaccounts/SubAccountsManager.tsx | Medium |

---

## Admin (181/207 -- 87%)

### Admin Core -- Tested

| File | Test File | Status |
|------|-----------|--------|
| AdminApp | __tests__/AdminApp.test.tsx | PASS |
| AdminLayout | __tests__/AdminLayout.test.tsx | PASS |
| AdminRoute | __tests__/AdminRoute.test.tsx | PASS |
| SuperAdminRoute | __tests__/SuperAdminRoute.test.tsx | PASS |
| adminApi | __tests__/adminApi.test.ts + api/__tests__/adminApi.test.ts | PASS |

### Admin Components -- Tested via Batch Files

Tested in `__tests__/components.test.tsx`:
AdminSidebar, AdminHeader, AdminBreadcrumbs, StatCard, EmptyState, PageHeader, ConfirmModal, DataTable

### Admin Components -- Untested (3 files)

| File | Path | Priority |
|------|------|----------|
| IconPicker | components/IconPicker.tsx | Low |
| RichTextEditor | components/RichTextEditor.tsx | Medium |
| VisibilityRulesEditor | components/VisibilityRulesEditor.tsx | Medium |

### Admin Modules -- Tested via Batch Files

The following admin modules are tested across these batch test files:
- `AdvancedModules.test.tsx` -- AiSettings, AlgorithmSettings, Error404Tracking, FeedAlgorithm, Redirects, SeoAudit, SeoOverview
- `BrokerModules.test.tsx` -- BrokerDashboard, ExchangeManagement, RiskTags, MessageReview, UserMonitoring, VettingRecords, BrokerConfiguration, ExchangeDetail
- `ContentModules.test.tsx` -- AttributesAdmin, MenuBuilder, MenusAdmin, PageBuilder, PagesAdmin, PlanForm, PlansAdmin, Subscriptions
- `DeliverabilityModules.test.tsx` -- CreateDeliverable, DeliverabilityAnalytics, DeliverabilityDashboard, DeliverablesList
- `EnterpriseModules.test.tsx` -- EnterpriseDashboard, ErrorLogs, GdprDashboard, GdprRequests, GdprConsents, GdprAuditLog, GdprBreaches, HealthCheck, PermissionBrowser, RoleList, RoleForm, SecretsVault, SystemConfig, SystemMonitoring, LegalDocList, LegalDocForm, LegalDocComplianceDashboard, LegalDocVersionComparison
- `FederationModules.test.tsx` -- FederationSettings, ApiKeys, CreateApiKey, DataManagement, FederationAnalytics, MyProfile, PartnerDirectory, Partnerships
- `GroupModules.test.tsx` -- GroupList, GroupDetail, GroupAnalytics, GroupApprovals, GroupModeration, GroupPolicies, GroupRanking, GroupRecommendations, GroupTypes
- `ModerationModules.test.tsx` -- CommentsModeration, FeedModeration, ReportsManagement, ReviewsModeration
- `NewsletterModules.test.tsx` -- NewsletterList, NewsletterForm, NewsletterAnalytics, NewsletterBounces, NewsletterDiagnostics, NewsletterResend, NewsletterSendTimeOptimizer, Segments, Subscribers, Templates
- `RegistrationPolicyModules.test.tsx` -- RegistrationPolicySettings
- `RemainingModules.test.tsx` -- BlogPostForm, SmartMatchMonitoring, SmartMatchUsers, MatchingDiagnostic, NexusScoreAnalytics, LegalDocVersionForm, LegalDocVersionList, CampaignForm, CreateBadge, GamificationHub, MatchDetail, VolunteerApprovals, VolunteeringOverview, VolunteerOrganizations
- `ReportModules.test.tsx` -- HoursReportsPage, InactiveMembersPage, MemberReportsPage, ModerationQueuePage, SocialValuePage, ResourcesAdmin, SafeguardingDashboard
- `SimpleModules.test.tsx` -- EventsAdmin, GoalsAdmin, IdeationAdmin, JobsAdmin, PerformanceDashboard, PollsAdmin
- `SuperAdminModules.test.tsx` -- SuperAuditLog, FederationAuditLog, FederationControls, FederationSystemControls, FederationTenantFeatures, FederationWhitelist, Partnerships, TenantForm, TenantHierarchy, TenantListAdmin, TenantShow (all super-admin/)
- `SuperModules.test.tsx` -- BulkOperations, FederationAuditLog, FederationControls, FederationTenantFeatures, SuperDashboard, SuperUserForm, SuperUserList, TenantForm, TenantHierarchy, TenantList, TenantShow, UserShow (all super/)
- `SystemModules.test.tsx` -- AdminDashboard, CommunityAnalytics, ImpactReport, CategoriesAdmin, ListingsAdmin, TenantFeatures, AdminPlaceholder, MatchingConfig, SmartMatchingOverview, MatchApprovals
- `SystemModulesExtra.test.tsx` -- ActivityLog, AdminSettings, BlogRestore, CronJobLogs, CronJobs, CronJobSettings, CronJobSetup, ImageSettings, NativeApp, SeedGenerator, TestRunner, WebpConverter
- `TimebankingUserModules.test.tsx` -- FraudAlerts, OrgWallets, StartingBalances, TimebankingDashboard, UserReport, UserCreate, UserEdit, UserList
- `modules-batch1.test.tsx` -- AdminDashboard, AdminPlaceholder, AdminNotFound, ListingsAdmin, BlogAdmin, CategoriesAdmin
- `modules-batch2.test.tsx` -- BrokerDashboard, ExchangeManagement, RiskTags, MessageReview, UserMonitoring, VettingRecords, MatchingConfig, MatchApprovals, MatchingAnalytics, TimebankingDashboard, FraudAlerts, OrgWallets, GamificationAnalytics, CustomBadges, CampaignList
- `JobsAdmin.test.tsx` -- JobsAdmin (standalone)
- `VerificationAuditLog.test.tsx` -- VerificationAuditLog (standalone)

### Admin Modules -- Untested (23 files)

| Directory | File | Priority |
|-----------|------|----------|
| advanced/ | EmailSettings | Medium |
| advanced/ | MatchDebugPanel | Low |
| broker/ | ArchiveDetail | Low |
| broker/ | InsuranceCertificates | Low |
| broker/ | MessageDetail | Low |
| broker/ | ReviewArchive | Low |
| crm/ | ActivityTimeline | Medium |
| crm/ | CoordinatorTasks | Medium |
| crm/ | CrmDashboard | Medium |
| crm/ | MemberNotes | Medium |
| crm/ | MemberTags | Medium |
| crm/ | OnboardingFunnel | Medium |
| federation/ | CreditAgreements | Medium |
| federation/ | Neighborhoods | Medium |
| jobs/ | JobModerationQueue | Medium |
| newsletters/ | NewsletterActivity | Low |
| newsletters/ | NewsletterStats | Low |
| newsletters/ | SegmentForm | Low |
| newsletters/ | TemplateForm | Low |
| newsletters/ | TemplatePreview | Low |
| super/ | SuperPartnerships | Low |
| system/ | ProviderHealthDashboard | Medium |
| system/ | VerificationReviewQueue | Medium |

> Note: `admin/routes.tsx` is a routing configuration file, not a component -- excluded from coverage tracking.

---

## Other Test Files

| Test File | Covers |
|-----------|--------|
| `test/accessibility.test.tsx` | Cross-cutting accessibility checks |

---

## Gaps Being Filled

Files currently having tests written (this session):

### Components -- Feed Sidebar (10 files)
- [ ] CommunityPulseWidget
- [ ] FeedSidebar
- [ ] FriendsWidget
- [ ] PeopleYouMayKnowWidget
- [ ] PopularGroupsWidget
- [ ] QuickActionsWidget
- [ ] SuggestedListingsWidget
- [ ] TopCategoriesWidget
- [ ] UpcomingEventsWidget
- [ ] WidgetSkeleton

### Components -- Feed (7 files)
- [ ] FeedContentRenderer
- [ ] FeedModeToggle
- [ ] LocationRadiusFilter
- [ ] MobileFAB
- [ ] ShareButton
- [ ] StoriesBar
- [ ] SubFilterChips

### Components -- Compose (10 files)
- [ ] ComposeSubmitContext
- [ ] MobileComposeOverlay
- [ ] ComposeEditor
- [ ] ImageUploader
- [ ] MultiImageUploader
- [ ] VideoUploader
- [ ] EventTab
- [ ] GoalTab
- [ ] ListingTab
- [ ] PostTab

### Components -- Location (5 files)
- [ ] EntityMapView
- [ ] GoogleMapsProvider
- [ ] LocationMap
- [ ] LocationMapCard
- [ ] PlaceAutocompleteInput

### Components -- Other (20 files)
- [ ] NotificationFlyout
- [ ] MenuNavItems
- [ ] TeamChatrooms
- [ ] TeamDocuments
- [ ] TeamTasks
- [ ] LegalAcceptanceGate
- [ ] PlatformLegalPage
- [ ] ListingAnalyticsPanel
- [ ] SkillTagsInput
- [ ] MessageContextCard
- [ ] ReviewModal
- [ ] AdvancedSearchFilters
- [ ] SavedSearches
- [ ] LanguageSwitcher
- [ ] AvailabilityGrid
- [ ] TenantLogo
- [ ] SkillSelector
- [ ] CommentsSection
- [ ] LikersModal
- [ ] SubAccountsManager
- [ ] ProfileFeed

### Pages -- Jobs (5 files)
- [ ] BiasAuditPage
- [ ] EmployerBrandPage
- [ ] EmployerOnboardingPage
- [ ] JobKanbanPage
- [ ] TalentSearchPage

### Pages -- Knowledge Base (2 files)
- [ ] KBArticlePage
- [ ] KnowledgeBasePage

### Pages -- Volunteering Tabs (5 files)
- [ ] AccessibilityTab
- [ ] CommunityProjectsTab
- [ ] DonationsTab
- [ ] ExpensesTab
- [ ] SafeguardingTab

### Pages -- Public/Legal (7 files)
- [ ] AcceptableUsePage
- [ ] CommunityGuidelinesPage
- [ ] CustomPage
- [ ] DevelopmentStatusPage
- [ ] LegalVersionHistoryPage
- [ ] PlatformDisclaimerPage
- [ ] PlatformPrivacyPage
- [ ] PlatformTermsPage

### Pages -- Misc (6 files)
- [ ] MatchesPage
- [ ] MatchesRedirectPage
- [ ] NewsletterUnsubscribePage
- [ ] NexusScorePage
- [ ] PollsPage
- [ ] RelatedPages
- [ ] SkillsBrowsePage

### Contexts (1 file)
- [ ] MenuContext

### Data (1 file)
- [ ] sdg-goals

### Admin -- Components (3 files)
- [ ] IconPicker
- [ ] RichTextEditor
- [ ] VisibilityRulesEditor

### Admin -- CRM Module (6 files)
- [ ] ActivityTimeline
- [ ] CoordinatorTasks
- [ ] CrmDashboard
- [ ] MemberNotes
- [ ] MemberTags
- [ ] OnboardingFunnel

### Admin -- Other Untested Modules (17 files)
- [ ] EmailSettings
- [ ] MatchDebugPanel
- [ ] ArchiveDetail
- [ ] InsuranceCertificates
- [ ] MessageDetail
- [ ] ReviewArchive
- [ ] CreditAgreements
- [ ] Neighborhoods
- [ ] JobModerationQueue
- [ ] NewsletterActivity
- [ ] NewsletterStats
- [ ] SegmentForm
- [ ] TemplateForm
- [ ] TemplatePreview
- [ ] SuperPartnerships
- [ ] ProviderHealthDashboard
- [ ] VerificationReviewQueue
