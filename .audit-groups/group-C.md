# Audit findings — GROUP C (clusters: wallet-gamification-goals, volunteering-organisations, commerce, federation-static-layout)

Fix POLISH (high+medium) and build PARITY gaps (high+medium, HTML-first feasible only). Skip low unless trivial.


========================================
## CLUSTER: wallet-gamification-goals
========================================

### POLISH findings

- [high] achievements.blade.php
  ISSUE: The govuk-inset-text used as an empty-state for earned badges (`<p class="govuk-inset-text">`) is wrong markup. `govuk-inset-text` is a block element (`<div>`), not a `<p>`. Using `<p class="govuk-inset-text">` renders a paragraph with inset styles but the component's vertical border and padding rely on div block behaviour.
  FIX: Change `<p class="govuk-inset-text">` to `<div class="govuk-inset-text"><p class="govuk-body">...</p></div>` for the earned_empty empty state and all equivalent empty-state usages in this file.

- [medium] wallet.blade.php
  ISSUE: govuk-tabs component used with server-side navigation links rather than tabbed panels. The GOV.UK tabs component (`data-module="govuk-tabs"`) expects each tab to reveal a `.govuk-tabs__panel` section; without the panels, govuk-frontend JS activates and immediately hides the tab content because it finds no matching panels. The transaction table below the tabs is outside the tabs container, so JS activation breaks the visual layout.
  FIX: Either (a) wrap the entire transactions section inside a single `.govuk-tabs__panel` div so the JS can show/hide it correctly, or (b) replace the tabs markup with plain links that scroll to `#transactions` and add the active state purely via CSS (a custom `nexus-alpha-filter-links` pattern), removing `data-module` from the container to prevent JS from touching it.

- [medium] wallet.blade.php
  ISSUE: Transfer success uses a govuk-notification-banner, but it should be a govuk-panel (big green confirmation panel). The wallet transfer is a significant transactional action — the GOV.UK design system specifies `govuk-panel--confirmation` for this pattern, not a notification banner. The notification banner is correct for donate-sent and export-failed messages.
  FIX: Replace the `transfer-sent` notification banner with `<div class="govuk-panel govuk-panel--confirmation"><h2 class="govuk-panel__title">...</h2><div class="govuk-panel__body">...</div></div>`.

- [medium] achievements.blade.php
  ISSUE: The XP progress bar and each badge-progress bar use a raw `<progress>` element without any wrapping or label beyond `aria-label`. GOV.UK does not have a native progress-bar component for this use case, but the custom `<progress>` has no visible percentage text adjacent to it for users who cannot see the bar fill.
  FIX: Add a visible numeric percentage adjacent to each `<progress>` element (the value is already computed as `$levelPct` / `$bpPct`). Pattern: `<span class="govuk-body-s govuk-!-margin-right-2">{{ $levelPct }}%</span><progress ...>`.

- [medium] leaderboard.blade.php
  ISSUE: The filter form has two selects in a govuk-grid-row with a submit button outside the grid, but the form lacks a `<fieldset>` + `<legend>` grouping the two related filter controls. GOV.UK forms guidance requires related selects to be wrapped in a fieldset when the combination has a single semantic purpose (filter the leaderboard).
  FIX: Wrap the `govuk-grid-row` div inside `<fieldset class="govuk-fieldset"><legend class="govuk-fieldset__legend govuk-fieldset__legend--m"><h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.leaderboard.filter_heading') }}</h2></legend>...</fieldset>` and remove the standalone heading if present.

- [medium] goals.blade.php
  ISSUE: The two navigation links (Templates, Buddying) at the top of the page are wrapped in a `<nav>` element with `aria-label` but styled with only `govuk-body` and manual margin-right spacing. Multiple adjacent navigation links should use `govuk-button-group` when they are actions, or a proper `govuk-list` inside the nav if they are secondary links. The inline `govuk-!-margin-right-4` spacing is a layout workaround that breaks at narrow viewports.
  FIX: Replace the bare `<nav>` with a `<nav aria-label="..."><ul class="govuk-list govuk-list--inline"><li>...</li><li>...</li></ul></nav>` using GOV.UK inline list, or if these are primary actions use `<div class="govuk-button-group">` with secondary buttons.

- [medium] goals.blade.php
  ISSUE: The create-goal form is inside a `govuk-grid-row` with `govuk-grid-column-two-thirds` applied to form fields, but the goal list above it (the card-list) spans full width without a column wrapper. This creates inconsistent reading width — body text and form fields are constrained but the card list is not, which is correct. However, the `<h2>` create heading at line 53 is outside the grid row and spans full width, making it visually orphaned from the constrained form below.
  FIX: Move the `<h2 class="govuk-heading-l">` create heading inside the `govuk-grid-row > govuk-grid-column-two-thirds` wrapper so it sits above the form fields at the same constrained width.

- [medium] goal-detail.blade.php
  ISSUE: Error state for `goal-failed` / `goal-invalid` / `buddy-failed` uses a `govuk-notification-banner` (without the `--success` modifier) rather than `govuk-error-summary`. The error notification banner used here (lines 27–31) lacks `tabindex="-1"` and `role="alert"`, so it will not receive focus on page load and screen readers may not announce it.
  FIX: Replace the bare notification banner used for error states with a proper `<div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1"><div role="alert">...</div></div>` to match the pattern used in goal-edit.blade.php.

### PARITY gaps

- [high] Daily reward widget — claim XP daily login bonus with streak tracking
  BUILD: achievements.blade.php has no daily-reward section; add a POST form to /alpha/{slug}/achievements/daily-reward with CSRF, showing current streak and next-reward XP from the controller, with a disabled state when already claimed today.
  REACT: react-frontend/src/pages/achievements/AchievementsPage.tsx (DailyRewardWidget component, ~line 134)

- [high] Active challenges tab — list in-progress challenges with progress bars and XP rewards, claim completed challenge rewards
  BUILD: achievements.blade.php shows only earned badges and badge-progress. Add a `Challenges` section (or second blade) driven by GET /v2/gamification/challenges, listing title, description, progress, end date, and a claim button (POST) for completed-but-unclaimed challenges.
  REACT: react-frontend/src/pages/achievements/AchievementsPage.tsx (ChallengesTab component, ~line 339)

- [high] Leaderboard — Community Impact tab (aggregate stats: total hours exchanged, active members, top services)
  BUILD: leaderboard.blade.php only shows the ranked member table. Add a community-stats section before or alongside the table showing aggregate figures; fetch from the same leaderboard endpoint's meta or a separate /v2/gamification/community-stats endpoint.
  REACT: react-frontend/src/pages/leaderboard/CommunityImpactTab.tsx

- [high] Goals — Discover tab (browse public goals from other members, become buddy from list)
  BUILD: goals.blade.php only shows the current user's own goals. The buddying blade shows goals the user is already buddying. Add a discover section or tab to goal-buddying.blade.php that fetches GET /v2/goals/discover and renders public goals from other members with become-buddy buttons.
  REACT: react-frontend/src/pages/goals/GoalsPage.tsx (tab='discover', ~line 185)

- [medium] Badge journeys / collections tab — grouped badge sets with completion progress and XP reward
  BUILD: Fetch from GET /v2/gamification/collections; render each collection as a govuk-summary-list row or nexus-alpha-card with earned/total count and a progress bar; add to achievements blade below the earned badges section.
  REACT: react-frontend/src/pages/achievements/AchievementsPage.tsx (JourneysTab component, ~line 573)

- [medium] Engagement history — 12-month activity calendar grid showing active/inactive months
  BUILD: Fetch from GET /v2/gamification/engagement-history; render a simple govuk-table or a 3-column table of month/status/activity-count pairs — the visual grid is JS-enhanced but a table is the correct accessible fallback.
  REACT: react-frontend/src/pages/achievements/AchievementsPage.tsx (EngagementTab component, ~line 744)

- [medium] XP shop — browse and purchase XP-cost items (titles, badges, themes)
  BUILD: Fetch from GET /v2/gamification/shop; render each item as a nexus-alpha-card with name, description, cost, and a POST purchase form; show current XP balance at top from the gamification profile. Gate display on user having sufficient XP.
  REACT: react-frontend/src/pages/achievements/AchievementsPage.tsx (XpShopTab component, ~line 852)

- [medium] Leaderboard — Personal Journey tab (XP timeline, season progress, personal stats)
  BUILD: Add a personal-journey section to leaderboard blade or a dedicated blade; data can come from GET /v2/gamification/profile and /v2/gamification/seasons/current; render as a govuk-summary-list of personal stats plus a season progress bar.
  REACT: react-frontend/src/pages/leaderboard/PersonalJourneyTab.tsx

- [medium] Leaderboard — Season card (active season name, days remaining, user rank, ending-soon warning)
  BUILD: Fetch from GET /v2/gamification/seasons/current; render as a govuk-inset-text or nexus-alpha-card above the filter form showing season name, end date, user's current rank, and a govuk-tag--red `Ending soon` badge when days_remaining <= 7.
  REACT: react-frontend/src/pages/leaderboard/LeaderboardPage.tsx (SeasonCard component, ~line 124)

- [medium] Wallet — pending_in / pending_out balances displayed alongside main balance
  BUILD: wallet.blade.php stat grid shows balance/earned/spent but not pending credits. Add a fourth `<div class="nexus-alpha-stat">` for pending amount from wallet['pending_in'] + wallet['pending_out'] passed by the controller.
  REACT: react-frontend/src/pages/wallet/WalletPage.tsx (balance card, ~line 299)

- [medium] Goals — streak count and overdue deadline indicators on goal cards
  BUILD: goals.blade.php cards show progress but not streak_count or overdue state. Add streak display (e.g. `govuk-tag--orange` with count) and a `govuk-tag--red` overdue indicator when deadline is past and goal is not completed.
  REACT: react-frontend/src/pages/goals/GoalsPage.tsx (GoalCard meta info section, ~line 1149)

- [medium] Goals — buddy sends nudge / encouragement action from the buddying view
  BUILD: goal-buddying.blade.php shows goals being buddied but has no nudge action. Add a POST form for each buddied goal linking to POST /alpha/{slug}/goals/{id}/buddy/nudge with a govuk-button--secondary `Send encouragement` button.
  REACT: react-frontend/src/pages/goals/GoalsPage.tsx (handleBuddyAction, ~line 393; GoalCard isBuddyingTab branch, ~line 1282)


========================================
## CLUSTER: volunteering-organisations
========================================

### POLISH findings

- [high] volunteering.blade.php
  ISSUE: The govuk-tabs component (lines 94-113) is used as a pure navigation element with href-linked tabs, but does NOT include data-module="govuk-tabs" on the outer div, so the GOV.UK JS never initialises the panel-switching behaviour. The tabs render as a bare unordered list with no govuk-tabs__panel content wrappers — the content for each tab is conditionally rendered outside the tabs component via @if blocks. This means GOV.UK's tabs progressive-enhancement never activates: keyboard panel-switching, tab[panel] ARIA roles, and the aria-controls linkage are all absent.
  FIX: Either add data-module="govuk-tabs" to the outer div AND wrap each tab's content in <div class="govuk-tabs__panel" id="panel-{tab}">, or drop the govuk-tabs markup entirely and use plain govuk-link navigation (which is what it actually renders as). The current hybrid is broken — it looks like tabs but provides neither the accessibility nor the interaction of the GOV.UK tabs component.

- [high] volunteering-swaps.blade.php
  ISSUE: The 'to_shift_id' field (line 96) and 'to_user_id' field (line 101) ask the user to type a numeric shift ID and a numeric user ID directly into free-text inputs. These are internal database IDs that no user can realistically know. The React ShiftSwapsTab selects from the user's own shifts and a member search — the accessible version exposes raw IDs. This is a broken user experience, not just a polish issue.
  FIX: Replace the to_shift_id text input with a govuk-select populated with available open shifts from the requested-from shift's opportunity (or a dropdown of all open shifts the user is eligible to request). Replace to_user_id with a govuk-input for a member name/username lookup, or at minimum add a hint text explaining where to find the other user's ID and the shift ID. The current form is unusable without this context.

- [medium] volunteering.blade.php
  ISSUE: The 'organisations' sub-tab (lines 265-315) shows a card list of the user's joined organisations with a govuk-summary-list per card, but the organisation name (line 280) is not linked to the organisation-detail page. The user cannot navigate from the Organisations tab to an organisation's detail without going via a separate route.
  FIX: Wrap the organisation name h3 in <a class="govuk-link" href="{{ route('govuk-alpha.organisations.show', ...) }}"> if a detail route exists, or add a govuk-link 'View organisation' action below the summary-list, consistent with the opportunity cards' 'View details' pattern.

- [medium] volunteering.blade.php
  ISSUE: The hours summary stat-grid (lines 68-81) on the index page uses nexus-alpha-stat-grid with bare <dl>/<dt>/<dd> — this is the correct accepted pattern. However the links to hours/accessibility/certificates/waitlist/swaps below it (lines 83-92) are rendered as a single <p> with middot separators and no visual hierarchy. On narrow viewports these wrap awkwardly and there is no govuk-button-group or govuk-list to contain them. They are secondary navigation actions, not body prose.
  FIX: Replace with a <ul class="govuk-list"> or a <div class="govuk-button-group"> of govuk-button--secondary links to give consistent spacing and make keyboard focus order obvious.

- [medium] volunteering.blade.php
  ISSUE: The filter form fieldset (line 318-359) wraps all three filter controls (search, category select, remote checkbox) in a single fieldset+legend. The remote checkbox at line 344 uses govuk-checkboxes--small and is inside the same fieldset as the text/select inputs. GOV.UK pattern requires checkboxes and radios to have their own govuk-form-group wrapper enclosing the fieldset — but here the outer govuk-form-group (line 343) contains the govuk-checkboxes div without its own inner fieldset+legend because it is already inside the parent fieldset. The remote checkbox has no legend of its own; its context depends on the parent legend which spans all three fields. This is acceptable but the govuk-!-margin-top-6 on the form-group (line 343) pushing the checkbox down to align visually with the select is a fragile spacing hack.
  FIX: Remove govuk-!-margin-top-6 from the remote checkbox form-group and use standard govuk form-group stacking inside the grid column. Consider giving the checkbox its own short legend if it might be unclear without the parent context.

- [medium] volunteer-opportunity.blade.php
  ISSUE: The h2 on line 79 reads 'Volunteer opportunity' (the detail_title translation key) — this is a repetition of the caption on line 61 which also says 'Volunteer opportunity'. The h2 should be a meaningful section heading such as 'About this opportunity' or 'Details', not a repeat of the page-level caption.
  FIX: Change the govuk_alpha.volunteering.detail_title key used for the h2 to a distinct value such as 'govuk_alpha.volunteering.about_heading' ("About this opportunity") and update the translation file.

- [medium] organisations.blade.php
  ISSUE: The organisation card list (lines 35-45) renders each card with an h2 (govuk-heading-s). Within a single page that already has an h1, using h2 for every card item is correct. However, when many organisations are listed the heading level makes sense. The empty state at line 33 uses <p class="govuk-inset-text"> — but govuk-inset-text is a div, not a p element. Wrapping govuk-inset-text class on a <p> is invalid HTML (block inside inline).
  FIX: Change line 33 from <p class="govuk-inset-text"> to <div class="govuk-inset-text"><p class="govuk-body">...</p></div>.

- [medium] organisations.blade.php
  ISSUE: The register-organisation form (lines 50-71) is embedded on the same page as the organisation listing, placed below the list. The form wraps a govuk-grid-column-two-thirds inner div but the outer <form> element starts with class="govuk-grid-row" (line 50). This means the form element itself becomes the grid row container, which is valid HTML but unconventional — child fieldsets and govuk-form-groups sit inside a grid column without an explicit fieldset+legend group wrapping all the fields.
  FIX: Move the register organisation form fields into a fieldset with a govuk-fieldset__legend grouping the fields under 'Register your organisation'. This gives context for AT users who navigate by form landmarks. Alternatively separate the register form onto its own page (RegisterOrganisationPage.tsx pattern).

- [medium] organisation-detail.blade.php
  ISSUE: The stats section (lines 54-83) uses nexus-alpha-inline-list — the accepted custom pattern for horizontally-flowing key-value stats. However the rating stat embeds a raw <progress> element (line 78) inside a <dd>. A <progress> inside a <dd> inside a <dl> inside an inline-list creates a visually inconsistent widget. The progress bar is styled by the browser, producing a platform-specific appearance.
  FIX: Display the average rating as numeric text (e.g. '4.2 / 5') with a visually-hidden description of what it means, using the same pattern as the other stats in the grid. Reserve <progress> elements for goal/completion contexts (as in volunteering-hours.blade.php), not for average ratings.

### PARITY gaps

- [high] Volunteering swaps — partner shift selector using real shift data rather than raw numeric ID input
  BUILD: The blade's to_shift_id field is a bare text input requiring the user to know an internal shift ID. Pass available open shifts from the API to the blade and render them as a govuk-select. The to_user_id field should similarly allow a username rather than a user ID.
  REACT: react-frontend/src/pages/volunteering/ShiftSwapsTab.tsx — loads available shifts from API and renders a dropdown

- [medium] Volunteering tabs: Emergency Alerts, Wellbeing, Credential Verification, Group Sign-ups, Hours Review (for org admins), Expenses, Safeguarding, Community Projects, Donations
  BUILD: Accessible blade has only 5 tabs (opportunities/applications/recommended/hours/organisations). React has 17 config-driven tabs. Build each missing tab as a separate blade partial rendered on ?tab= param, starting with alerts (EmergencyAlertsTab) and expenses (ExpensesTab) as highest-value for verified volunteers.
  REACT: react-frontend/src/pages/volunteering/VolunteeringPage.tsx (lines 304-322) — tabs 'alerts', 'wellbeing', 'credentials', 'group-signups', 'hours-review', 'expenses', 'safeguarding', 'community-projects', 'donations'

- [medium] Volunteering index — 'Post opportunity' and 'My organisations' action buttons for org admins/owners
  BUILD: The accessible blade shows no org-admin actions. The controller already fetches user organisations for the 'organisations' tab; check if any have status approved + role owner/admin and expose 'Post a new opportunity' link to the create route and 'Manage my organisation' link to the org-admin dashboard if one exists.
  REACT: react-frontend/src/pages/volunteering/VolunteeringPage.tsx lines 265-296 — shown when hasApprovedOrg is true

- [medium] Organisation detail — Job openings section (vacancies from the org)
  BUILD: organisation-detail.blade.php has no job openings section. The AlphaController's showOrganisation action should fetch open jobs for the org (if job_vacancies feature is enabled) and pass them to the blade. Render as a nexus-alpha-card-list with title, type chip, location, and a govuk-link to the job detail page.
  REACT: react-frontend/src/pages/organisations/OrganisationDetailPage.tsx lines 471-511 — fetches /v2/jobs?organization_id={id}&status=open and renders a 'Job openings' section

- [medium] Organisation detail — inline 'Apply to volunteer' action on each opportunity card (authenticated users)
  BUILD: The accessible organisation-detail blade links opportunities through to the volunteer-opportunity detail page but has no direct apply action on the org-detail page itself. Acceptable as-is since the opportunity detail page has the full apply form — note as low-priority omission.
  REACT: react-frontend/src/pages/organisations/OrganisationDetailPage.tsx lines 450-463 — 'Apply' button opens a modal with message textarea; applies to /v2/volunteering/opportunities/{id}/apply

- [medium] Organisations index — rich card metadata (opportunity count, volunteer count, average rating, location) on listing cards
  BUILD: accessible organisations.blade.php cards show only name and truncated description (lines 38-45). The API already returns opportunity_count, volunteer_count, average_rating, and location on the list endpoint. Add a govuk-summary-list or nexus-alpha-inline-list below the description on each card showing these stats.
  REACT: react-frontend/src/pages/organisations/OrganisationsPage.tsx — OrganisationCard component renders opportunity_count, volunteer_count, average_rating, location from the Organisation interface (lines 39-52)


========================================
## CLUSTER: commerce
========================================

### POLISH findings

- [high] marketplace-detail.blade.php
  ISSUE: No contact/action buttons are present. The detail page shows metadata in a govuk-summary-list but offers no way for the viewer to contact the seller or make an offer — there is no CTA form, no message link, nothing. The page is read-only and dead-ends.
  FIX: Add a `govuk-button-group` after the summary-list with a primary 'Message Seller' link-button (linking to messages route with seller context) and, where authenticated, a secondary 'Make Offer' form, wrapped in `@auth`.

- [high] course-detail.blade.php
  ISSUE: The `<h2>` at line 68 reads `{{ __('govuk_alpha.courses.enrol_button') }}` — the translation key for the button label is re-used as a section heading. This means the heading text is whatever the button label is (e.g. 'Enrol'), not a meaningful section title like 'Enrolment'.
  FIX: Add a separate translation key `govuk_alpha.courses.enrol_section_heading` (e.g. 'Enrol in this course') and use it for the `<h2>`, keeping `enrol_button` only for the `<button>` text.

- [high] coupons.blade.php
  ISSUE: Coupon codes are displayed as `<strong>{{ $code }}</strong>` inline in a paragraph (line 45). React's CouponDetailPage uses a prominent `font-mono` code block with copy-to-clipboard and QR code generation. The blade has no way to copy the code and no link to a detail page (coupon title/heading is not linked).
  FIX: Wrap the coupon title in an `<a class="govuk-link" href="{{ route('govuk-alpha.coupons.show', ...) }}">` link. The coupons blade should either add a detail route or render the copy-code interaction inline as a `<details>`/`govuk-details` component showing the code prominently.

- [high] premium.blade.php
  ISSUE: The billing interval toggle (monthly vs yearly) is rendered as govuk-radios--inline on each tier card form. This means every tier card has its own independent radio group for interval selection — so a user could select monthly for tier A and yearly for tier B simultaneously. React's PricingPage has a single global toggle (Switch) above all tiers that sets the interval for all. Having per-card radios is confusing and inconsistent.
  FIX: Move the interval radio group (`govuk-radios`) outside the per-tier forms, as a single global fieldset above the tier list. Pass the selected `interval` value via a hidden input in each tier's form, updated on radio change via a minimal script or by submitting only the form matching the selected interval.

- [medium] marketplace.blade.php
  ISSUE: Empty state uses a bare `<p class="govuk-inset-text">` (line 37), which is not how govuk-inset-text works — inset-text is a block wrapper, not a paragraph class. The correct markup is `<div class="govuk-inset-text"><p class="govuk-body">…</p></div>`.
  FIX: Replace `<p class="govuk-inset-text">` with `<div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.marketplace.empty') }}</p></div>`.

- [medium] marketplace.blade.php
  ISSUE: Price tag uses `govuk-tag--green` for all listings regardless of whether the item is free or paid. GOV.UK Tag colour should convey meaningful status; using green for a paid item (e.g., '2.50 credits') is misleading — green conventionally signals free/success.
  FIX: Use `govuk-tag--green` only when price is free; use `govuk-tag--blue` for credit prices and a neutral/default tag for money prices.

- [medium] marketplace-detail.blade.php
  ISSUE: When there are multiple images only the first is shown (`$images[0]`). No image gallery, thumbnail strip, or indication that more images exist. Users cannot see all listing images.
  FIX: Render a `<ul class="govuk-list nexus-alpha-image-strip">` with all images as `<li>` items (small thumbnails with links to full size), matching the govuk-list pattern.

- [medium] marketplace-detail.blade.php
  ISSUE: The govuk-caption-xl (line 38) wraps correctly but the h1 is inside a `nexus-alpha-module-row` flex div alongside the price govuk-tag (line 39–42). This causes the h1 to sit in a flexbox row rather than as a full-width block element above the content, which breaks the standard GOV.UK caption+heading pattern and can cause heading text to truncate.
  FIX: Move the govuk-tag price badge below the h1 as a separate line element, so the heading is a full-width block: `<h1 class="govuk-heading-xl">{{ $iTitle }}</h1><p><strong class="govuk-tag govuk-tag--green">{{ $priceLabel }}</strong></p>`.

- [medium] courses.blade.php
  ISSUE: Empty state uses `<p class="govuk-inset-text">` (line 32) — same misuse as marketplace. govuk-inset-text is a `<div>` wrapper, not a paragraph class.
  FIX: Replace with `<div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.courses.empty') }}</p></div>`.

- [medium] course-detail.blade.php
  ISSUE: The success confirmation on enrolment uses a govuk-notification-banner (line 22). GOV.UK recommends a `govuk-panel` (the large green confirmation panel) for post-submission confirmation screens, while notification banners are for non-critical in-page updates. Enrolment is a transaction completion.
  FIX: Use `<div class="govuk-panel govuk-panel--confirmation"><h1 class="govuk-panel__title">…</h1><div class="govuk-panel__body">…</div></div>` instead of the notification banner for the enrolled success state.

- [medium] podcasts.blade.php
  ISSUE: Empty state uses bare `<p class="govuk-inset-text">` (line 13). Same misuse as courses and marketplace.
  FIX: Replace with `<div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.podcasts.empty') }}</p></div>`.

- [medium] podcasts.blade.php
  ISSUE: No search or sort controls, unlike React's PodcastsPage which has a search field, category Select, sort Select (newest/title/episodes/followers), and a clear-filters button. The blade has no filtering at all.
  FIX: Add a search `<input class="govuk-input">` and a `<select class="govuk-select">` for sort order inside a `<form method="get">` matching the marketplace/courses pattern.

- [medium] podcast-detail.blade.php
  ISSUE: Episodes are rendered as bare `<article class="nexus-alpha-card">` blocks with the episode title as a non-linked `<h3>` (line 29). There is no per-episode detail page route referenced, so titles are not clickable. React's PodcastShowPage links each episode to `/podcasts/{show.slug}/{episode.slug}`.
  FIX: Wrap episode titles in `<a class="govuk-link" href="{{ route('govuk-alpha.podcasts.episode', [...]) }}">` once a blade episode-detail view/route is available, or at minimum add a 'Listen' govuk-button link per episode.

- [medium] coupons.blade.php
  ISSUE: Empty state uses `<p class="govuk-inset-text">` (line 26). Same misuse pattern.
  FIX: Replace with `<div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.coupons.empty') }}</p></div>`.

- [medium] premium.blade.php
  ISSUE: The success/cancel states after Stripe checkout return are not handled. React's PricingPage has a `SubscriptionReturnPage` for the return URL. The blade's `subscribe-failed` error summary is shown (line 13) but there is no success state after a successful Stripe redirect back.
  FIX: Add a `@if ($status === 'subscribed')` branch that renders a `govuk-panel govuk-panel--confirmation` with a confirmation heading and body, matching the GOV.UK post-payment confirmation pattern.

- [medium] clubs.blade.php
  ISSUE: Club logos (avatar images) from the React ClubsPage (`logo_url` field) are not rendered in the blade. The blade shows only name, member count, description, schedule, email, and website. The React card prominently shows an Avatar component with club logo.
  FIX: Add `@if (trim((string)($club['logo_url'] ?? '')) !== '') <img class="nexus-alpha-avatar" src="{{ $club['logo_url'] }}" alt="" aria-hidden="true"> @endif` at the top of each club card article.

- [medium] clubs.blade.php
  ISSUE: Empty state uses `<p class="govuk-inset-text">` (line 26). Same misuse pattern.
  FIX: Replace with `<div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.clubs.empty') }}</p></div>`.

### PARITY gaps

- [high] Marketplace: category filter (CategoryChips / category_id param)
  BUILD: Blade has no category filter; add a `<select class="govuk-select" name="category_id">` populated from a controller-injected `$categories` array, matching the existing `?q=` GET param pattern.
  REACT: react-frontend/src/pages/marketplace/MarketplacePage.tsx (lines 101–103, 322–330)

- [high] Marketplace listing detail: contact seller / message seller button
  BUILD: The blade detail page has no action CTA at all. Add a `govuk-button-group` with a 'Message seller' govuk-link-button (linking to `/{tenantSlug}/alpha/messages?to={seller_id}&...`) wrapped in `@auth`.
  REACT: react-frontend/src/pages/marketplace/MarketplaceListingPage.tsx (lines 682–692)

- [high] Podcasts: search field
  BUILD: Add `<input class="govuk-input" type="search" name="q">` in a `<form method="get">` to the podcasts index view, mirroring the marketplace/courses search pattern.
  REACT: react-frontend/src/pages/podcasts/PodcastsPage.tsx (lines 131–139)

- [high] Podcast show detail: subscribe/unsubscribe button
  BUILD: Add an `@auth` form posting to `govuk-alpha.podcasts.subscribe` with a toggle based on `$isSubscribed`, rendered as a govuk-button (secondary variant when subscribed).
  REACT: react-frontend/src/pages/podcasts/PodcastShowPage.tsx (lines 123–135)

- [high] Podcast show detail: clickable per-episode links (episode detail route)
  BUILD: React links each episode to `/podcasts/{show.slug}/{episode.slug}`. The blade renders episode title as a plain `<h3>` with no link. Add `<a class="govuk-link" href="{{ route('govuk-alpha.podcasts.episode', ...) }}">` — requires a new blade episode-detail route and view.
  REACT: react-frontend/src/pages/podcasts/PodcastShowPage.tsx (lines 150–158)

- [high] Coupons: coupon detail page (copy code, QR code redemption)
  BUILD: No blade detail view exists. React has a full CouponDetailPage with copy-to-clipboard and QR generation. Build `coupons-detail.blade.php` with the coupon code displayed in a `govuk-panel`-style block, a copy form (JS-progressive-enhanced), and a govuk-details element showing QR code image URL.
  REACT: react-frontend/src/pages/coupons/CouponDetailPage.tsx

- [high] Coupons: clickable coupon cards (link to detail page)
  BUILD: Coupon card titles in blade are plain text `<h2>` (not linked). Wrap in `<a class="govuk-link" href="{{ route('govuk-alpha.coupons.show', ...) }}">` to link to the missing detail view.
  REACT: react-frontend/src/pages/coupons/CouponsPage.tsx (line 112 — Link to /coupons/:id)

- [high] Premium: global billing interval toggle (monthly/yearly) above all tiers
  BUILD: React has a single Switch above the tier grid that sets the same interval for all. The blade has a per-card radio group per tier. Replace per-card radios with a single `govuk-radios--inline` fieldset above the list; use JS (or hidden form field propagation) to sync value to all tier forms.
  REACT: react-frontend/src/pages/premium/PricingPage.tsx (lines 126–137)

- [high] Premium: post-Stripe checkout success page (SubscriptionReturnPage)
  BUILD: React redirects to `/premium/return` after Stripe checkout. No blade return/success view exists. Build a `premium-return.blade.php` that uses `govuk-panel govuk-panel--confirmation` for successful subscription confirmation and an error-summary for failures.
  REACT: react-frontend/src/pages/premium/SubscriptionReturnPage.tsx

- [medium] Marketplace: featured listings section
  BUILD: React shows a 'Featured listings' section before the main grid. Pass `$featuredListings` from the controller and render a separate `nexus-alpha-card-list` block with a `govuk-heading-m` heading above the main results.
  REACT: react-frontend/src/pages/marketplace/MarketplacePage.tsx (lines 337–349)

- [medium] Marketplace listing detail: make offer form
  BUILD: React allows authenticated users to make an offer (amount + message). Add an `@auth` form posting to a `govuk-alpha.marketplace.offer` route with a credit/money input and optional message textarea.
  REACT: react-frontend/src/pages/marketplace/MarketplaceListingPage.tsx (lines 670–692)

- [medium] Marketplace listing detail: save/unsave listing
  BUILD: No save action in blade. Add a secondary govuk-button form posting to a `marketplace.save` route (`@auth`).
  REACT: react-frontend/src/pages/marketplace/MarketplaceListingPage.tsx (lines 402–421)

- [medium] Courses: category filter dropdown
  BUILD: Pass `$categories` from AlphaController and add `<select class="govuk-select" name="category_id">` to the search form.
  REACT: react-frontend/src/pages/courses/CoursesPage.tsx (lines 118–128)

- [medium] Courses: 'Load more' pagination
  BUILD: Blade fetches all courses with no pagination. Add page-based pagination using govuk-pagination component or a 'Load more' link to `?page=N`.
  REACT: react-frontend/src/pages/courses/CoursesPage.tsx (lines 155–163)

- [medium] Courses: authenticated instructor actions (Create Course, My Learning, Instructor Dashboard links)
  BUILD: Wrap govuk-button links in `@auth` pointing to `/{tenantSlug}/alpha/courses/instructor` and `/{tenantSlug}/alpha/courses/my-learning` (requires those blade views, note as route-stub until built).
  REACT: react-frontend/src/pages/courses/CoursesPage.tsx (lines 91–103)

- [medium] Course detail: prerequisites list with completion status
  BUILD: Pass `$prerequisites` (array with `completed` bool) from the controller and render a `<ul class="govuk-list">` using govuk-tag `govuk-tag--green` / `govuk-tag--grey` to show completed vs pending, above the enrol form.
  REACT: react-frontend/src/pages/courses/CourseDetailPage.tsx (lines 145–158)

- [medium] Course detail: course reviews / ratings
  BUILD: Pass `$reviews` (array) and `$ratingAvg`, `$ratingCount` from the controller. Render a `<dl class="govuk-summary-list">` with rating average, then `nexus-alpha-card-list` for individual reviews.
  REACT: react-frontend/src/pages/courses/CourseDetailPage.tsx (line 161 — CourseReviews component)

- [medium] Course detail: 'Continue learning' button for enrolled users (navigates to course player)
  BUILD: When `$isEnrolled` is true and a player route exists, show a primary govuk-button 'Continue learning' linking to `/{tenantSlug}/alpha/courses/{id}/learn`.
  REACT: react-frontend/src/pages/courses/CourseDetailPage.tsx (lines 186–188)

- [medium] Podcasts: sort order selector (newest / title / episodes / followers)
  BUILD: Add `<select class="govuk-select" name="sort">` with the same four options to the search form and pass `$sort` to the controller query.
  REACT: react-frontend/src/pages/podcasts/PodcastsPage.tsx (lines 153–162)

- [medium] Podcasts: category filter
  BUILD: Pass `$categories` from controller (derived from show category field) and add `<select class="govuk-select" name="category">` to the form.
  REACT: react-frontend/src/pages/podcasts/PodcastsPage.tsx (lines 143–152)

- [medium] Podcasts: show artwork image (Avatar/artwork_url)
  BUILD: Add `@if (!empty($s['artwork_url'])) <img class="nexus-alpha-avatar" src="{{ $s['artwork_url'] }}" alt="" aria-hidden="true"> @endif` inside each show card in the index.
  REACT: react-frontend/src/pages/podcasts/PodcastsPage.tsx (line 193–199 — Avatar with artwork_url)

- [medium] Podcast show detail: RSS feed link
  BUILD: If `$show['rss_enabled']` is true, add `<a class="govuk-link" href="{rss_url}">RSS Feed</a>` as a govuk-button--secondary or govuk-link below the show description.
  REACT: react-frontend/src/pages/podcasts/PodcastShowPage.tsx (lines 119–123)

- [medium] Podcast show detail: show artwork (large) and visibility/moderation-status chips
  BUILD: Render `$show['artwork_url']` as a large `<img>` in the detail header. Add govuk-tag for visibility and moderation status (e.g., `govuk-tag--yellow` for pending, `govuk-tag--red` for rejected).
  REACT: react-frontend/src/pages/podcasts/PodcastShowPage.tsx (lines 98–111)

- [medium] Premium: current subscription management (MySubscriptionPage — cancel, change tier)
  BUILD: React has a MySubscriptionPage for managing the active subscription. Add a blade view `premium-subscription.blade.php` linked from the premium page for authenticated users, showing current tier via govuk-summary-list and a 'Cancel subscription' govuk-button--warning form.
  REACT: react-frontend/src/pages/premium/MySubscriptionPage.tsx

- [medium] Clubs: club logo/avatar image
  BUILD: The `logo_url` field is available in the API response but not rendered in the blade. Add an `<img>` with the `nexus-alpha-avatar` class inside each club card article when `$club['logo_url']` is non-empty.
  REACT: react-frontend/src/pages/clubs/ClubsPage.tsx (lines 244–248 — Avatar with logo_url)

- [medium] Clubs: load more / pagination
  BUILD: React loads clubs with ITEMS_PER_PAGE=20 and shows a 'Load more' button. The blade fetches all clubs with no pagination. Add `?page=N` server-side pagination via govuk-pagination or a 'Show more' link.
  REACT: react-frontend/src/pages/clubs/ClubsPage.tsx (lines 213–226)


========================================
## CLUSTER: federation-static-layout
========================================

### POLISH findings

- [high] federation-connections.blade.php
  ISSUE: The three-tab navigation (Accepted / Received / Sent) is implemented as a plain <ul class="govuk-list"> with bold-link active states and aria-current="page". This is the govuk-tabs pattern rendered as bare links — govuk-tabs provides the correct semantics (tablist/tab/tabpanel roles, keyboard left/right navigation, controlled panel visibility) and progressive-enhancement JS. Bare links with page-reload work but lose the keyboard UX and ARIA tab semantics.
  FIX: Replace the <nav><ul> tab switcher with a govuk-tabs component: <div class="govuk-tabs" data-module="govuk-tabs"> with govuk-tabs__list / govuk-tabs__tab / govuk-tabs__panel markup. Each tab panel contains the card list for that state. Server-side pre-selection of the active panel still works via govuk-tabs__panel--hidden.

- [medium] federation.blade.php
  ISSUE: The page lacks a govuk-grid-row / govuk-grid-column-two-thirds wrapper. The h1 caption, description, stats summary-list, partner cards, and quick-links list all run full-width. Long body text (the govuk-body-l description) and the quick-links list span the full container width, producing uncomfortably long lines.
  FIX: Wrap the caption+h1+description paragraph and the quick-links section in <div class="govuk-grid-row"><div class="govuk-grid-column-two-thirds">. The stats summary-list and partner card grid can remain outside the two-thirds column.

- [medium] federation.blade.php
  ISSUE: The opt-in notification banner CTA uses a standalone govuk-button anchor inside govuk-notification-banner__content without a govuk-button-group wrapper. Multiple adjacent calls-to-action (button + potential cancel link) must be wrapped in govuk-button-group per the GOV.UK button group pattern.
  FIX: Wrap the <a class="govuk-button"> inside <div class="govuk-button-group"> within the notification banner content.

- [medium] federation-connections.blade.php
  ISSUE: The Accept and Reject buttons in the pending-incoming row are inside govuk-button-group but are each wrapped in separate <form> elements with govuk-!-display-inline. Inline forms sitting inside a govuk-button-group is non-standard; the govuk-button-group flex model expects direct children of type button/a/p. The inline form wrappers break the expected flex child structure.
  FIX: Move the CSRF hidden input and action URL to a single outer form or use a hidden action field pattern; keep both buttons as siblings inside one govuk-button-group without an outer <form> wrapping each individual button.

- [medium] federation-member.blade.php
  ISSUE: The messaging form label for the subject field reads __('govuk_alpha.fed2.messages.no_subject') — that is a fallback display string ('No subject'), not a descriptive field label. The actual label text should instruct what to enter (e.g. 'Subject (optional)').
  FIX: Change the label for #message-subject to a proper govuk-label string such as 'govuk_alpha.fed2.member_actions.subject_label' (add the key). The no_subject string is for display of messages that have no subject, not a form label.

- [medium] federation-member.blade.php
  ISSUE: The hour-transfer CTA is rendered as an anchor styled govuk-button--warning directly in a <p> tag rather than inside a govuk-button-group, and it sits immediately after the messaging form with no visual separator. When multiple action blocks (connect form / message form / transfer CTA) are present on the same page, grouping the final destructive action in govuk-button-group with a cancel link is the GOV.UK pattern.
  FIX: Wrap the transfer govuk-button--warning anchor and a govuk-link 'Cancel' back-link inside <div class="govuk-button-group"> and remove the containing <p>.

- [medium] federation-events.blade.php
  ISSUE: Event cards have no link to a detail view — the event title is plain text (h2, no anchor). Members can browse titles and dates but cannot navigate to see full event details or RSVP. This breaks the expected content pattern where every card title should be a govuk-link to the item.
  FIX: Either link the title to a federation event detail route or, if no detail route exists, add a 'View on [CommunityName]' govuk-link below the description. Also confirm whether a govuk-button 'RSVP / Express interest' is in scope.

- [medium] federation-listings.blade.php
  ISSUE: Listing cards have no link to a listing detail or contact action. The listing title is plain text, so members can see the listing exists but cannot act on it. This is an important usability gap in a browse view.
  FIX: Link the listing title to a federation listing detail route (or the owning community's listing if cross-community deep-link is available), or add a 'Contact member' CTA as a govuk-link.

- [medium] about.blade.php
  ISSUE: The CTA block at line 143 uses <div class="nexus-alpha-actions"> to contain a govuk-button and a govuk-link side by side. This is the govuk-button-group pattern and should use that class so layout, spacing, and responsive wrapping are handled by the GOV.UK stylesheet rather than a custom div.
  FIX: Replace <div class="nexus-alpha-actions"> with <div class="govuk-button-group"> in the CTA section.

- [medium] trust-safety.blade.php
  ISSUE: The CTA block at line 54 uses <div class="nexus-alpha-actions"> for a govuk-button and a govuk-link side by side. Same govuk-button-group pattern gap as in about.blade.php.
  FIX: Replace <div class="nexus-alpha-actions"> with <div class="govuk-button-group">.

- [medium] guide.blade.php
  ISSUE: The CTA section at line 27 uses <div class="nexus-alpha-actions govuk-!-margin-top-4"> for multiple adjacent buttons. Same govuk-button-group gap.
  FIX: Replace nexus-alpha-actions div with govuk-button-group. The govuk-!-margin-top-4 utility can remain on the outer container.

- [medium] legal-hub.blade.php
  ISSUE: The document list uses <li class="nexus-alpha-card"> inside <ul class="govuk-list">. A <li> being directly classed as a custom card element is structurally unusual and may produce unexpected list-item marker rendering (the govuk-list reset removes bullets but the child element takes over visual styling). More idiomatic is to use govuk-summary-list rows or a standard nexus-alpha-card-list outside the govuk-list.
  FIX: Change the container to <div class="nexus-alpha-card-list"> with <article class="nexus-alpha-card"> children, removing the outer <ul class="govuk-list"> wrapper. The h2 + p pattern inside each card already carries the semantic structure.

- [medium] federation-settings.blade.php
  ISSUE: The federation settings form is missing the 'messaging_enabled_federated' and 'transactions_enabled_federated' checkboxes that exist in the React FederationSettingsPage Communication section. The blade form only exposes visibility settings, notifications and service_reach — the two communication permission toggles are absent.
  FIX: Add govuk-checkboxes__item entries for messaging_enabled_federated and transactions_enabled_federated inside a new fieldset with legend matching govuk_alpha.federation.settings.communications_legend. These map to the React 'Allow messaging' and 'Allow time-credit transactions' toggles.

### PARITY gaps

- [high] Federation settings: messaging_enabled_federated and transactions_enabled_federated toggles
  BUILD: Two federation permission settings present in React — 'Allow messaging from partner communities' and 'Allow time-credit transactions with partner members' — are completely absent from federation-settings.blade.php. Without them, users cannot control whether they can be messaged or sent hour-transfers via federation on the accessible frontend. Add two checkboxes in a new 'Communications' fieldset matching the blade's existing checkbox pattern.
  REACT: react-frontend/src/pages/federation/FederationSettingsPage.tsx (SettingToggle for messaging_enabled_federated, transactions_enabled_federated, lines 424–437)

- [medium] Federation hub: recent activity feed
  BUILD: React hub shows up to 5 recent activity items (message received/sent, transaction, partnership, member joined) with actor avatar, relative timestamp and description. The blade hub shows only stats + partner cards + quick links — no activity section. Add a section after partner cards iterating $activity (pass from controller, same /v2/federation/activity endpoint) using nexus-alpha-card-list cards with direction tags and formatted timestamps.
  REACT: react-frontend/src/pages/federation/FederationHubPage.tsx (RecentActivitySection, lines 502–573)

- [medium] Federation hub: 'Partners' page (full list with pagination)
  BUILD: React has a dedicated /federation/partners index page listing all partner communities. The blade surface only shows a preview on the hub and routes to federation-partner.blade.php for a single partner. A federation-partners.blade.php index view and matching AlphaController method need to be added, linked from the hub quick-links list.
  REACT: react-frontend/src/pages/federation/FederationPartnersPage.tsx

- [medium] Federation groups browsing
  BUILD: React has a /federation/groups page for browsing federated groups (hub quick-links includes a Groups entry). The accessible frontend has no federation-groups.blade.php equivalent and no link to it from the hub quick-links. Add the view and controller method, or add a note to the hub that groups browsing is not yet available on this service.
  REACT: react-frontend/src/pages/federation/FederationGroupsPage.tsx
