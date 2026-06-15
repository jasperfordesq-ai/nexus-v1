# Audit findings — GROUP A (clusters: auth-onboarding, home-feed-dashboard, discovery-content)

Fix POLISH (high+medium) and build PARITY gaps (high+medium, HTML-first feasible only). Skip low unless trivial.


========================================
## CLUSTER: auth-onboarding
========================================

### POLISH findings

- [high] forgot-password.blade.php
  ISSUE: When the page is in its success state (`status === 'forgot-sent'`), no `<h1>` is rendered at all. The notification banner's `<h2>` is not a substitute for a page-level heading. Screen readers and heading-order checks will flag a missing h1.
  FIX: Add `<h1 class="govuk-heading-xl">{{ __('govuk_alpha.auth.forgot_sent_title') }}</h1>` before the success notification banner, matching the pattern used in the error/form branch.

- [medium] forgot-password.blade.php
  ISSUE: The `govuk-error-summary` for `forgot-rate-limited` is rendered before the `<h1>`, which means the error summary appears at the top of the page but the heading follows it. GOV.UK convention requires the error summary to appear immediately after the `<h1>` (or page caption), not before.
  FIX: Move the `@if (in_array($status…))` error-summary block to after the `<h1>` and `<p class="govuk-body-l">` description, mirroring the pattern in `login.blade.php` and `register.blade.php`.

- [medium] two-factor.blade.php
  ISSUE: The "use backup code" checkbox is inside a bare `govuk-form-group` with no `fieldset`/`legend`, but it is a standalone boolean choice that semantically does not belong in a form-group at all — it switches the behaviour of the code input above it. The current markup makes it look like a separate form field rather than an option that modifies the code input.
  FIX: Remove the wrapping `govuk-form-group` from the backup-code checkbox and place it directly in the flow as a `govuk-checkboxes--small` item without the outer form-group, or use a conditional reveal (`data-aria-controls`) on the code input's parent fieldset so the backup-code checkbox conditionally reveals a differently-labelled input — the standard GOV.UK conditional-reveal pattern.

- [medium] two-factor-setup.blade.php
  ISSUE: When `$setup` is truthy (TOTP QR/secret displayed), the numbered `<ol>` steps use `govuk-list govuk-list--number` but the steps call for specific app-install and scanning actions — this is a multi-step task that would benefit from a `govuk-task-list` to make status clearer. Also the `<p class="govuk-body">` manual-key intro is immediately followed by a `<code>` block without a hint-text wrapper, making it easy to miss visually.
  FIX: Wrap the TOTP secret in a `govuk-inset-text` block so it stands out from body text: `<div class="govuk-inset-text"><code class="nexus-alpha-totp-secret">{{ $setup['secret'] }}</code></div>`. For the steps list the `govuk-list--number` is acceptable but consider a brief `govuk-inset-text` above the QR image explaining that a camera app or authenticator can scan it.

- [medium] email-verify.blade.php
  ISSUE: When the state is `missing` or `invalid`, the page uses a plain `govuk-notification-banner` (neutral blue) whose `<h2>` reads from `states.error_title`. A neutral banner is semantically wrong for an error condition. The non-success state should either be a `govuk-error-summary` (for form-flow errors) or a `govuk-notification-banner` without `--success`, but the h2 label should be set to something appropriate rather than re-using the generic error summary title. More importantly, there is no `<h1>` on the success path — the `govuk-panel__title` IS rendered inside the panel, which serves as the heading, but a `govuk-panel` is not treated by browsers as an `<h1>` unless it renders one; checking the govuk-panel component: `govuk-panel__title` uses `<h1>` by default in the Nunjucks macro, but in this Blade it is literally `<h1 class="govuk-panel__title">` on line 12, so heading is present. Issue is the resend-verification CTA: on the error path there is only a `<p>` hint and a `<a>` back-link with no mechanism to actually trigger a resend from this page.
  FIX: Add a resend-verification `<form method="post">` with a govuk-button to the error state of `email-verify.blade.php`, matching the inline resend form already implemented in `login.blade.php` (lines 46-54). Users who land on the error page directly (e.g. from a stale link) should be able to re-trigger the email without going back to login.

- [medium] tenant-chooser.blade.php
  ISSUE: The empty-state `<h2>` and `<p>` (lines 16-17) are rendered outside the `govuk-grid-column-two-thirds` column, breaking the page-width constraint. The card list (`nexus-alpha-card-list`) is also outside any column wrapper, which is acceptable for a grid listing but the empty-state prose should stay within the two-thirds column for reading comfort.
  FIX: Move the empty-state block (`@if (empty($tenants))`) inside the `govuk-grid-column-two-thirds` div. The card list can remain full-width but should be wrapped in `<div class="govuk-grid-row"><div class="govuk-grid-column-full">` for structural correctness.

### PARITY gaps

- [high] Post-registration pending screen with distinct states for email-verification-required, admin-approval-required, and waitlist-joined (including waitlist position)
  BUILD: The blade `register.blade.php` redirects to login after POST (PRG), so after registration the user sees only a `register-created` notification banner on the login page with no distinction between verification/approval/waitlist states. Add a dedicated `register-pending.blade.php` view (or expand the `register-created` status on login) that shows a `govuk-panel` for success plus `govuk-inset-text` blocks for each pending state, including waitlist position when available. The controller's `register.store` action already knows which path applied.
  REACT: react-frontend/src/pages/auth/RegisterPage.tsx (lines 1127-1232, `pendingResult` branch with `requiresWaitlist`, `waitlistPosition`, `requiresVerification`, `requiresApproval`)

- [medium] "Trust this device" checkbox during 2FA login — suppresses future 2FA prompts on trusted devices
  BUILD: Add `<input class="govuk-checkboxes__input" name="trust_device" type="checkbox" value="1">` with label to `two-factor.blade.php` and pass it through the POST handler.
  REACT: react-frontend/src/pages/auth/LoginPage.tsx (line 709-718, `trustDevice` state + `trust_device` param passed to `verify2FA`)

- [medium] OAuth / SSO social login buttons (Google, GitHub, etc.) shown on the login form
  BUILD: Requires OAuth redirect flow; not a form POST. Add a `govuk-button-group` below the login form containing per-provider links to `/{tenantSlug}/alpha/oauth/{provider}` redirect routes. Blade template can render these from a `$oauthProviders` view variable passed by the controller.
  REACT: react-frontend/src/pages/auth/LoginPage.tsx (lines 448-453, `<OAuthButtons>` and `<SsoButtons>`)

- [medium] OAuth / SSO social registration buttons shown on the register form
  BUILD: Same pattern as login OAuth gap — add provider links in a `govuk-button-group` at the top of `register.blade.php`, rendered from controller-provided `$oauthProviders`.
  REACT: react-frontend/src/pages/auth/RegisterPage.tsx (lines 972-976, `<SsoButtons>` + `<OAuthButtons intent="register">`)

- [medium] Admin-approval-pending notice on the email-verify success page (shown when tenant requires admin approval AND user is not yet authenticated)
  BUILD: In `email-verify.blade.php` the success path shows only a `govuk-panel` with a sign-in button. Add a conditional `govuk-warning-text` or `govuk-inset-text` below the panel when `$requiresApproval` is truthy (pass from `EmailVerifyController`), explaining the account still awaits admin approval before login will succeed.
  REACT: react-frontend/src/pages/auth/VerifyEmailPage.tsx (lines 147-159, `requiresApproval && !isAuthenticated` block with amber warning panel)

- [medium] Verify email: auto-verification on page load with a loading spinner state (token POSTed on mount)
  BUILD: The blade `email-verify.blade.php` requires the server to have already consumed the token (via GET route) before rendering — it is controller-driven, not JS-driven. This is the correct HTML-first pattern. No gap: the controller handles verification synchronously. The loading spinner is a JS-only progressive enhancement. No action required.
  REACT: react-frontend/src/pages/auth/VerifyEmailPage.tsx (lines 49-82, `useEffect` with `api.post('/auth/verify-email', { token })`)

- [medium] Verify email: resend verification email button on the error state when the user is authenticated
  BUILD: The `email-verify.blade.php` error state shows a hint paragraph and a back-to-sign-in link but no resend mechanism. Add a `<form method="post">` with a secondary govuk-button to POST to a resend route (which already exists — it is used in `login.blade.php`). Conditionally show it when `$isAuthenticated` is passed from the controller.
  REACT: react-frontend/src/pages/auth/VerifyEmailPage.tsx (lines 203-222, `handleResendVerification` with `api.post('/auth/resend-verification')`)


========================================
## CLUSTER: home-feed-dashboard
========================================

### POLISH findings

- [high] dashboard.blade.php
  ISSUE: The 'New listing' primary button (line 59) sits as a standalone govuk-button immediately before the two-column grid. The quick-links column also contains a 'Create listing' link. Having a lone primary CTA button outside govuk-button-group or any contextual grouping, with no secondary action beside it, is fine per se — but the button has no explicit govuk-!-margin-bottom-* utility and the default govuk-button bottom margin (20px) is the only gap before the heading. On narrow screens this is visually tight. More critically, dashboard.blade.php has no govuk-task-list component despite the onboarding incomplete banner — this is a missed opportunity for the most apposite GOV.UK component.
  FIX: Wrap the 'New listing' button in a govuk-button-group div. For the incomplete-onboarding state, replace (or complement) the notification-banner with a govuk-task-list showing profile/onboarding steps — this is precisely the GOV.UK task-list use case.

- [high] feed.blade.php
  ISSUE: The error-summary anchors for 'post-empty' and 'post-failed' both point to `href="#content"` (lines 89, 100). The actual textarea id is `content`, so this is technically correct, but the `content` id is also the id of the govuk-hint div (`id="content-hint"`) via aria-describedby. The textarea is `id="content"` (line 127). This is fine as written, but the error-summary link text is the full error sentence rather than a short field label — GOV.UK error-summary convention is to use the field label ('Post content') as the link text, with the error as the list item description.
  FIX: Change the error-summary list items to: `<li><a href="#content">{{ __('govuk_alpha.feed.post_label') }}</a> — {{ $postErrorMessage }}</li>` to match the GOV.UK error-summary pattern (link = field label, error details follow).

- [medium] home.blade.php
  ISSUE: Two primary buttons in the authenticated state ('Go to your dashboard' and 'View your profile') are wrapped in a bare div.nexus-alpha-actions rather than govuk-button-group. Multiple adjacent primary/secondary buttons must use govuk-button-group so the GOV.UK JS correctly handles wrapping and spacing on small viewports.
  FIX: Replace `<div class="nexus-alpha-actions govuk-!-margin-bottom-8">` with `<div class="govuk-button-group govuk-!-margin-bottom-8">` for the button pair at lines 123-131.

- [medium] dashboard.blade.php
  ISSUE: The 'Recent feed' and 'Recent listings' sections sit inside a govuk-grid-column-one-half each (line 187-248). Feed items are rendered as nexus-alpha-cards with no link on the card title — the title is just plain text (line 201: `<h3 class="govuk-heading-m ...">{{ $itemTitle }}</h3>` with no anchor). Users cannot navigate to the full feed post from the dashboard. The listings column correctly links titles; the feed column does not.
  FIX: Wrap the feed item title in an `<a class="govuk-link" href="{{ route('govuk-alpha.feed') }}">` or, if a feed-post detail route exists, link to it. At minimum add a 'View feed' link below the card list consistent with the listings column pattern.

- [medium] feed.blade.php
  ISSUE: The moderation 'Delete post' action is hidden inside a govuk-details disclosure (lines 433-444). The button inside is correctly classed govuk-button--warning, but GOV.UK guidance recommends pairing a destructive action with a govuk-warning-text component above the button to make the consequence explicit before the user commits. The current govuk-body confirmation sentence is adequate but lacks the warning icon.
  FIX: Above the delete form's `<p class="govuk-body">{{ __('govuk_alpha.feed.delete_post_confirm') }}</p>` add: `<div class="govuk-warning-text"><span class="govuk-warning-text__icon" aria-hidden="true">!</span><strong class="govuk-warning-text__text"><span class="govuk-warning-text__assistive">Warning</span>{{ __('govuk_alpha.feed.delete_post_confirm') }}</strong></div>` and remove the bare paragraph.

- [medium] feed.blade.php
  ISSUE: Like/reaction/share/save action buttons within each feed card are wrapped in a bare `<div class="nexus-alpha-actions govuk-!-margin-bottom-3">` rather than govuk-button-group. When multiple forms/buttons appear adjacent (like + reaction row + share + save), GOV.UK button-group handles responsive wrapping and correct margin collapse automatically.
  FIX: Replace `<div class="nexus-alpha-actions govuk-!-margin-bottom-3">` around the like button (line 371) with `<div class="govuk-button-group govuk-!-margin-bottom-3">`. Apply consistently across all multi-button rows in the card.

- [medium] notifications.blade.php
  ISSUE: The filter links ('All' / 'Unread') at lines 69-70 are styled as govuk-links with govuk-!-font-weight-bold for the active state. These are navigation-style state switches; GOV.UK Design System has no native filter-link pattern, but the current implementation has no `aria-current="page"` or `aria-pressed` attribute on the active link, meaning screen readers have no indication of the current filter state.
  FIX: Add `aria-current="true"` to the active filter link. Alternatively, replace the two links with a two-option govuk-radios inline group inside a `<form method="get">` (consistent with the feed filter pattern) — this gives a native form state that screen readers and keyboard users handle correctly.

- [medium] notifications.blade.php
  ISSUE: The 'Delete all notifications' button (line 80) is govuk-button--warning but sits in the same bare div.nexus-alpha-actions row as 'Mark all read' (govuk-button--secondary). There is no govuk-warning-text before it and no confirmation step — clicking it immediately POSTs the delete-all action. GOV.UK guidance requires a confirmation step for irreversible destructive actions.
  FIX: Wrap 'Delete all notifications' in a govuk-details disclosure with a govuk-warning-text block and a confirmation button inside, matching the feed post-delete pattern. Or navigate to a separate confirmation page.

- [medium] search.blade.php
  ISSUE: The search form places the type-filter select (line 28-34) as a separate govuk-form-group after the search input. These two fields together constitute the search form — they should be grouped inside a `<fieldset class="govuk-fieldset">` with a `<legend>` so their relationship is announced to screen readers, consistent with the feed filter pattern in feed.blade.php.
  FIX: Wrap both the `<div class="govuk-form-group">` (query) and `<div class="govuk-form-group">` (type select) inside `<fieldset class="govuk-fieldset"><legend class="govuk-fieldset__legend govuk-fieldset__legend--m"><h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.search.title') }}</h2></legend>...</fieldset>`. Remove the standalone `<h1>` caption from inside the fieldset — it remains outside.

- [medium] feed-post.blade.php
  ISSUE: The success notification banner on the permalink page (lines 31-40) uses `{{ __('govuk_alpha.states.success_title') }}` for both the banner title and the banner heading (lines 34 and 37). This means the visible confirmation text is the generic 'Success' string rather than a specific message describing what just happened (e.g. 'Your reaction was added'). The feed.blade.php version correctly uses `$statusMessage($status)` for the heading — this is missing here.
  FIX: At line 37, replace `{{ __('govuk_alpha.states.success_title') }}` with a lookup matching the feed.blade.php pattern: `{{ in_array($status ?? '', $t1StatusKeys ?? [], true) ? __('govuk_alpha.feed_t1.status_' . str_replace('-', '_', $status ?? '')) : __('govuk_alpha.states.success_title') }}`.

### PARITY gaps

- [high] Hashtag browsing: Feed has HashtagPage and HashtagsDiscoveryPage — users can click a hashtag to see all posts tagged with it. No equivalent accessible-frontend route or blade exists.
  BUILD: Add govuk-alpha.feed.hashtag route + blade listing posts by hashtag tag; hashtag tokens in post content on feed.blade.php can link to it.
  REACT: react-frontend/src/pages/feed/HashtagPage.tsx, react-frontend/src/pages/feed/HashtagsDiscoveryPage.tsx

- [high] Explore page — Trending posts, popular listings, top contributors, trending hashtags, upcoming events, new members, featured challenges, recommended listings, near-you listings, blog posts, volunteering opportunities: React ExplorePage has rich sectioned discovery with all these data-driven slices. explore.blade.php is only a static list of module links.
  BUILD: The accessible Explore page is currently a pure navigation hub; add at minimum a 'Recent listings' and 'Upcoming events' govuk-list section beneath the module links to surface live content. Full parity with the React page's 10+ data sections is a larger build — prioritise listings + events as they are the core timebanking discovery flow.
  REACT: react-frontend/src/pages/explore/ExplorePage.tsx lines 54-198 (interface definitions for all data types)

- [medium] Dashboard — My Groups section: React DashboardPage fetches and renders the user's group memberships (myGroups) with links to each group. dashboard.blade.php has no groups section.
  BUILD: Add a 'Your groups' govuk-list section to dashboard.blade.php, gated on hasFeature('groups'), fetching up to 3 groups via the dashboard controller.
  REACT: react-frontend/src/pages/dashboard/DashboardPage.tsx lines 221, 248

- [medium] Dashboard — Suggested listings: React DashboardPage shows 4 suggested listings from /v2/listings (not the user's own listings). dashboard.blade.php shows the user's own recent listings but not community suggestions.
  BUILD: Add a 'Listings in your community' govuk-list or card section alongside the existing recent-listings section in dashboard.blade.php.
  REACT: react-frontend/src/pages/dashboard/DashboardPage.tsx lines 220, 246

- [medium] Dashboard — Pending transactions count: React DashboardPage fetches /v2/wallet/pending-count and surfaces it as a stat card. dashboard.blade.php has no pending-transaction indicator.
  BUILD: Add a nexus-alpha-stat to the stat-grid for pending transactions, gated on hasModule('wallet'), fetching count in AlphaDashboardController.
  REACT: react-frontend/src/pages/dashboard/DashboardPage.tsx line 214

- [medium] Notifications — Grouped notifications: React NotificationsPage uses /v2/notifications/grouped and renders grouped notification items with markGroupAsRead. The accessible notifications view uses a flat list with no grouping.
  BUILD: The API already returns grouped data — in AlphaNotificationsController, expose group_key and use a govuk-accordion or govuk-details component to group notifications by type/group_key.
  REACT: react-frontend/src/pages/notifications/NotificationsPage.tsx lines 81, 194-216

- [medium] Search — Blog posts and organisations in search results: React search supports 'blog' and 'organisation' result types. search.blade.php $typeMeta only handles listing, user, event, group.
  BUILD: Extend $typeMeta in search.blade.php to include 'blog' => ['govuk-alpha.blog.show', 'govuk-tag--grey', 'tag_blog'] and 'organisation' => ['govuk-alpha.organisations.show', 'govuk-tag--turquoise', 'tag_organisation'] once those routes exist.
  REACT: react-frontend/src/pages/explore/ExplorePage.tsx (search integration) and AlphaSearchController


========================================
## CLUSTER: discovery-content
========================================

### POLISH findings

- [high] saved.blade.php
  ISSUE: Saved items render as a plain <ul class="govuk-list govuk-list--spaced"> of bare text (title + type label) with no link to the saved item (lines 29-33). Users cannot navigate to bookmarked content — the list is read-only display only.
  FIX: For each saved item, resolve a URL using the bookmarkable_type (post→feed, listing→listings, event→events, job→jobs, blog→blog, discussion→feed). Render each list item as <a class="govuk-link" href="...">{{ $sTitle }}</a> with the type govuk-tag alongside.

- [medium] ideation.blade.php
  ISSUE: Empty state uses a bare <p class="govuk-inset-text"> (line 25). govuk-inset-text is for asides/callouts, not empty states. An empty state that is a standalone paragraph inside govuk-inset-text renders without an enclosing <div>, which is the correct element per the Design System.
  FIX: Wrap in <div class="govuk-inset-text"> instead of <p class="govuk-inset-text">. The <p> tag is invalid inside govuk-inset-text markup — the component expects a <div> wrapper per the govuk-frontend template.

- [medium] ideation-detail.blade.php
  ISSUE: Vote form buttons (one per idea card, line 86) are each a lone <button> inside a <form> with no govuk-button-group wrapper. When multiple vote buttons appear vertically in the list they are structurally identical — wrapping each in its own <form> is correct — but the button lacks aria context about which idea it targets beyond the surrounding card. The button text is the generic 'Vote' label; screen readers reaching the button have no heading association.
  FIX: Add aria-describedby on each vote button referencing the idea <h3> id so screen readers announce 'Vote [described by] Idea title'. Pattern: give the <h3> id="idea-{{ $idea['id'] }}-title" and add aria-describedby="idea-{{ $idea['id'] }}-title" to the button.

- [medium] saved.blade.php
  ISSUE: No govuk-breadcrumbs on this personal page. The page has no 'when was this saved' timestamp and no type-tab filtering. The list shows raw class_basename() output for type (e.g. 'Post' instead of a translated label) when the lang key is missing (line 26-27).
  FIX: No breadcrumbs needed on a top-level personal page. For timestamps, pass 'saved_at' / 'created_at' from the controller and display with govuk-body-s nexus-alpha-meta. For type, the Str::headline() fallback is acceptable but ensure govuk_alpha.saved.types keys cover all API slugs.

- [medium] activity.blade.php
  ISSUE: Monthly hours chart uses HTML <progress> elements (line 53). The <progress> has aria-label but the per-month rows have no visual label distinguishing given vs received — only a single combined bar with the month label. The govuk Design System uses govuk-summary-list or well-structured tables for comparative data; a series of unlabelled progress bars is not a recognised GOV.UK pattern.
  FIX: Replace the <progress>-per-month pattern with a govuk-summary-list where each row has the month as the key and 'Given X · Received Y' as the value. This matches the tabular nature of the data and is screen-reader-friendly without needing custom aria. Retain the <progress> bars only if they genuinely add value; if kept, add two progress bars per month with distinct aria-labels ('January given' / 'January received').

- [medium] polls.blade.php
  ISSUE: Closed poll result bars use <progress max="100" value="{{ $pct }}" aria-label="{{ $pct }}%"> (line 161). The aria-label only announces the percentage, not the option name. A screen reader user hears '63%' with no context about which option that refers to.
  FIX: Change aria-label to include the option text: aria-label="{{ $opt['text'] ?? $opt['label'] ?? '' }}: {{ $pct }}%". This matches the govuk-accessible-autocomplete guidance on providing descriptive labels for form controls that represent named data.

- [medium] blog-post.blade.php
  ISSUE: Comment success notification (line 79) uses <h3 class="govuk-notification-banner__title"> inside the notification banner. The Design System spec requires the notification-banner title to be an <h2>. Using <h3> creates an incorrect heading hierarchy inside the notification banner component.
  FIX: Change <h3 class="govuk-notification-banner__title" id="comment-status"> to <h2 class="govuk-notification-banner__title" id="comment-status"> at line 79.

- [medium] resources.blade.php
  ISSUE: Resource download links (line 40) use rel="noopener" but no visual or accessible indication that they open in a new tab (there is no target="_blank" either). More importantly, the link text is the generic translated 'Download' for every resource — screen readers list multiple 'Download' links with no distinguishing context.
  FIX: Append the resource title to the accessible name: <a class="govuk-link" href="..." aria-label="{{ __('govuk_alpha.resources.download') }} {{ $rTitle }}">{{ __('govuk_alpha.resources.download') }}</a>. Or use govuk-visually-hidden: <span class="govuk-visually-hidden"> {{ $rTitle }}</span> inside the anchor after the visible text.

### PARITY gaps

- [high] Ideation index: status/category filter tabs and search bar
  BUILD: Blade shows all challenges unsorted with no filter. Add govuk-tabs (or link-based ?status= query param filter) for All/Open/Voting/Closed and a govuk-form-group search input at top; the AlphaController already accepts query params — wire them to the view.
  REACT: react-frontend/src/pages/ideation/IdeationPage.tsx (Tabs: all/open/voting/evaluating/closed/archived/favorites; SearchField; category Select dropdown)

- [high] Saved items: clickable links to bookmarked content
  BUILD: Critical gap: blade renders titles as plain text with no links. Resolve URLs in the AlphaController (or in the blade via a type-to-route map) and render each item as a govuk-link. Types: post→feed/posts/{id}, listing→listings/{id}, event→events/{id}, job→jobs/{id}, blog→blog/{slug}.
  REACT: react-frontend/src/pages/bookmarks/BookmarksPage.tsx line 201-211 (getDetailPath resolves type→URL; line 386 renders as Link)

- [high] Polls: create poll form (authenticated users)
  BUILD: Blade has no create-poll UI at all. Add a govuk-details ('Create a poll') wrapping a POST form with: govuk-input for question, govuk-textarea for description, dynamic option inputs (min 2), govuk-date-input for expires_at. POST to govuk-alpha.polls.create route.
  REACT: react-frontend/src/pages/polls/PollsPage.tsx lines 527-577 (handleCreate, create form with question/description/options/expiry/poll_type/anonymous)

- [medium] Ideation detail: sort ideas by Top Voted / Newest toggle
  BUILD: Blade shows ideas in API-default order only. Add a ?sort=votes|newest GET param, two govuk-button--secondary links acting as sort toggles, and pass the param through to the API call in AlphaController.
  REACT: react-frontend/src/pages/ideation/ChallengeDetailPage.tsx (sort toggle referenced in JSDoc header)

- [medium] Ideation detail: outcomes section on closed/evaluated challenges
  BUILD: No outcomes section in the blade. Add a conditional section below the ideas list for status==='closed'|'archived': show $challenge['outcomes'] if present, wrapped in govuk-inset-text or a govuk-summary-list.
  REACT: react-frontend/src/pages/ideation/ChallengeDetailPage.tsx (I10: Outcomes section on closed challenges)

- [medium] Saved items: content-type tab filtering (All / Post / Listing / Event / Job / Blog)
  BUILD: Add ?type= query param filter; render as govuk-tabs or a set of govuk-link pills at top of page. AlphaController already accepts type param for the bookmarks API.
  REACT: react-frontend/src/pages/bookmarks/BookmarksPage.tsx lines 330-352 (Tabs by ContentTab)

- [medium] Saved items: bookmark collections (create/rename/delete named collections; filter list by collection)
  BUILD: Blade has no collections UI. A minimal HTML-first implementation: a <select> to filter by collection (GET ?collection_id=), and a simple form to create a new collection. Delete/rename can be separate small forms per collection row.
  REACT: react-frontend/src/pages/bookmarks/BookmarksPage.tsx lines 44-52, 80-92 (BookmarkCollectionData, collection CRUD modals)

- [medium] Saved items: remove (unsave) a bookmark from the list
  BUILD: Blade has no remove action per item. Add a small POST form with a govuk-button--warning 'Remove' button per list item, posting to a govuk-alpha.bookmarks.destroy route in AlphaController.
  REACT: react-frontend/src/pages/bookmarks/BookmarksPage.tsx lines 130-145 (handleRemoveBookmark, DELETE via POST /v2/bookmarks)

- [medium] Activity: engagement metrics panel (posts count, comments, likes given/received)
  BUILD: Blade stat grid shows hours_given, hours_received, connections, groups_joined. Missing: posts_30d, likes_received_30d, net_balance. The API response includes engagement and hours_summary.net_balance — add a second govuk-summary-list below the stat grid.
  REACT: react-frontend/src/pages/activity/ActivityDashboardPage.tsx lines 393-426 (Quick Stats sidebar: groups_joined, posts_count, likes_received, net_balance)

- [medium] Blog post: SocialInteractionPanel (likes, comments count, share) on post
  BUILD: Blade has comment form and comment list but no like/share interaction. Add a like toggle form (POST /v2/likes with type=blog) as a govuk-button--secondary below the article content, showing the current count. Share is JS-only — omit from blade track.
  REACT: react-frontend/src/pages/blog/BlogPostPage.tsx (SocialInteractionPanel with targetType='blog', likes and comment count)

- [medium] Resources: category tree filter (hierarchical category sidebar)
  BUILD: Blade has only a freetext search input. Add a ?category_id= filter via a flat govuk-select populated from GET /v2/resources/categories. Hierarchical tree is JS-only; flat select is the accessible equivalent.
  REACT: react-frontend/src/pages/resources/ResourcesPage.tsx lines 83-90, 122-140 (CategoryTreeNode, CategoryTreeItem component with expand/collapse)

- [medium] Resources: upload new resource form (authenticated users)
  BUILD: Blade has no upload UI. Add a govuk-details ('Upload a resource') wrapping a multipart POST form with govuk-input for title, govuk-textarea for description, and govuk-input type=file (accept='.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.jpg,.png'). POST to govuk-alpha.resources.store.
  REACT: react-frontend/src/pages/resources/ResourcesPage.tsx lines 93-95 (ALLOWED_EXTENSIONS, MAX_FILE_SIZE) and upload modal (POST /v2/resources multipart)
