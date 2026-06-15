# Audit findings — GROUP B (clusters: listings-exchanges-matches, messages-connections-members-profile, events, groups)

Fix POLISH (high+medium) and build PARITY gaps (high+medium, HTML-first feasible only). Skip low unless trivial.


========================================
## CLUSTER: listings-exchanges-matches
========================================

### POLISH findings

- [medium] listings.blade.php
  ISSUE: Line 156–161: Search and Clear Filters buttons rendered inside a bare <div class="nexus-alpha-actions"> instead of a govuk-button-group. Two adjacent action buttons always warrant govuk-button-group for correct spacing and alignment semantics.
  FIX: Replace <div class="nexus-alpha-actions"> with <div class="govuk-button-group"> and move the clear-filters govuk-link inside it as the second child, following the GDS button-group pattern (primary button first, secondary link last).

- [medium] listing-detail.blade.php
  ISSUE: Lines 232–234: The auth-required banner inside the exchange section renders a login button and a register button side-by-side inside a <div class="nexus-alpha-actions">, not a govuk-button-group. Same pattern applies on listing-detail where owner edit action stands alone without govuk-button-group.
  FIX: Wrap every group of two or more adjacent govuk-button or govuk-link action elements in <div class="govuk-button-group">. The nexus-alpha-actions div can remain for layout, but the GOV.UK govuk-button-group should be the immediate parent of the buttons.

- [medium] listing-detail.blade.php
  ISSUE: Lines 113–181: The govuk-summary-list and the author section at lines 113 and 183 sit outside the govuk-grid-column-two-thirds container that wraps the description/gallery/skills (which closes at line 111). Reading content (summary list, author panel, exchange section) therefore spans the full page width, producing very long lines of body text — a GOV.UK polish defect.
  FIX: Wrap the govuk-summary-list (line 114) and author block (line 185) in their own <div class="govuk-grid-row"><div class="govuk-grid-column-two-thirds"> ... </div></div>, or reopen the two-thirds column before those sections. The member-offers/requests lists at the bottom (lines 260–271) can remain full-width as a navigation list.

- [medium] exchange-detail.blade.php
  ISSUE: Lines 162–225: Each workflow action form (accept, start, complete, confirm, decline, cancel) is a separate <form> with an inline margin class. When multiple actions are available they stack as isolated forms with no govuk-button-group grouping. Decline and cancel also have no govuk-warning-text before the warning-variant button, leaving destructive intent implicit.
  FIX: Collect the primary action button and any warning-variant buttons into a govuk-button-group where they appear on the same page together. Add a govuk-warning-text block immediately before the govuk-button--warning for decline and cancel, matching the pattern already used in listing-edit.blade.php's delete section.

- [medium] exchange-request.blade.php
  ISSUE: The form (line 78) has no govuk-error-summary block for server-side field validation errors. The two govuk-error-summary/govuk-notification-banner blocks at the top (lines 17–26) only fire on the 'exchange-failed' and 'compliance-failed' status strings. If the backend returns field-level validation errors the user sees no summary and no field error messages.
  FIX: Add a conditional govuk-error-summary driven by $errors->any() (same pattern as listing-create.blade.php lines 30–43), and wire each input's aria-describedby and govuk-form-group--error class to $errors->has('fieldname'), matching the create/edit blade pattern.

### PARITY gaps

- [high] Save / unsave listing (heart/bookmark toggle) on listing cards and detail page
  BUILD: Add POST/DELETE /alpha/{slug}/listings/{id}/save endpoints in AlphaListingsController; render a govuk-link or govuk-button--secondary 'Save listing' / 'Remove from saved' toggle on the detail page (forms with CSRF, POST/DELETE method-spoof); add govuk-tag or indicator on list cards.
  REACT: react-frontend/src/pages/listings/ListingsPage.tsx (handleToggleSave, Heart button in ListingCard) and ListingDetailPage.tsx (handleSave, Bookmark button)

- [high] Matches: cross-module unified feed (jobs, volunteering, groups alongside listings), source-type tab filter, dismiss action, stats row
  BUILD: Update AlphaMatchesController to call /v2/matches/all (cross-module endpoint) instead of the listings-only matches endpoint; pass source_type in the view; add govuk-tabs or govuk-radios type filter for all/listing/job/volunteering/group; add a dismiss form (POST with CSRF, reason=not_relevant) per card; add a govuk-summary-list or nexus-alpha-stat-grid above the list for total count and avg score.
  REACT: react-frontend/src/pages/matches/MatchesPage.tsx (source_type filter tabs for listing/job/volunteering/group, dismissMatch via POST /v2/matches/{id}/dismiss, stats grid for totalMatches/avgScore/sourceTypeCounts)

- [medium] Social interactions on listing detail: like, comments section, share to feed, report with categorised reason
  BUILD: Out of scope for JS-only realtime comments; the like count and a static 'N people liked this' link can be rendered server-side. Report can be a simple govuk-button linking to a separate /alpha/{slug}/listings/{id}/report GET+POST page with a govuk-radios reason picker and govuk-textarea details, matching the create/edit blade pattern.
  REACT: react-frontend/src/pages/listings/ListingDetailPage.tsx (social.toggleLike, CommentsSection, ShareButton, handleReport / isReportOpen modal)

- [medium] Listing renewal / extend action for expired or expiring listings (owner only)
  BUILD: Add a POST /alpha/{slug}/listings/{id}/renew route in AlphaListingsController; on listing-detail.blade.php inside the $isOwner branch add a secondary govuk-button form that POSTs to renew, visible only when $listing['status'] === 'expired' or expires_at is set.
  REACT: react-frontend/src/pages/listings/ListingDetailPage.tsx (handleRenew, listing.status === 'expired' or listing.expires_at branch)

- [medium] Listing create/edit: skill tags input, experience level, equipment provided, accessibility notes fields
  BUILD: Add govuk-input for experience_level, equipment_provided, accessibility_notes (all optional, with govuk-hint), and a comma-separated govuk-textarea for skill tags (parse server-side into array) to both listing-create.blade.php and listing-edit.blade.php; pass through AlphaListingsController to the API payload.
  REACT: react-frontend/src/pages/listings/CreateListingPage.tsx (SkillTagsInput, experience_level, equipment_provided, accessibility_notes in FormData)

- [medium] Exchanges list: tab-based status filter (Active / Needs Confirmation / Completed / All)
  BUILD: Replace the single govuk-select status filter in exchanges.blade.php with govuk-tabs (or govuk-button-group radio-style tab links as a progressive-enhancement tab pattern); each tab link appends ?tab=active|pending_confirmation|completed|all to the URL; the controller maps tab to the API status_filter parameter.
  REACT: react-frontend/src/pages/exchanges/ExchangesPage.tsx (Tabs with keys active, pending_confirmation, completed, all; handleTabChange)


========================================
## CLUSTER: messages-connections-members-profile
========================================

### POLISH findings

- [high] conversation.blade.php
  ISSUE: The govuk-tabs component (lines 85-95 in messages.blade.php) has an associated data-module but the conversation view's inline edit/delete action uses govuk-details to reveal a form inside an ordered list item (<li class="nexus-alpha-card">). A govuk-details element nested inside a list item is non-standard and breaks list semantics — screen readers announce the list item as containing interactive disclosure content rather than a message.
  FIX: Move the edit/delete govuk-details outside the <li> or restructure as a separate block after the message body, keeping the <li> as a purely semantic message container.

- [medium] messages.blade.php
  ISSUE: The govuk-tabs component is used as navigation tabs (line 85-95) but without the govuk-tabs data-module attribute and correct tab-panel markup. The two list items link to separate pages rather than controlling in-page panels with govuk-tabs__panel, so the GOV.UK JS module will not initialise correctly and the tabs will render as plain links with no panel reveal behaviour.
  FIX: Either remove the govuk-tabs wrapper and use plain govuk-list navigation links (simpler, matches the server-side page reload pattern), or convert to full govuk-tabs markup with govuk-tabs__panel divs for inbox and archived content and add data-module="govuk-tabs" to the outer div.

- [medium] messages.blade.php
  ISSUE: The 'Start new conversation' search block is a plain nexus-alpha-card. This is a separate action zone distinct from the conversation list and would benefit from being a govuk-inset-text or at minimum use a govuk-button-group for the search Submit button and the 'Browse directory' link, which currently sit in an ad-hoc arrangement.
  FIX: Wrap the Submit button and the 'Browse the member directory' link in a govuk-button-group div so they align correctly per GOV.UK button spacing conventions.

- [medium] conversation.blade.php
  ISSUE: The archive conversation button (line 196-199) sits as a standalone form after the reply form with only govuk-!-margin-top-4. A destructive secondary action like archiving should be separated from the primary Send reply button more clearly, and should ideally be wrapped with the reply button in a govuk-button-group so the visual grouping and spacing are correct.
  FIX: Wrap the reply submit button and the archive button in a govuk-button-group. The archive button is already govuk-button--secondary which is correct.

- [medium] connections.blade.php
  ISSUE: Lines 70-82 and 100-108: the Accept/Decline and View Profile/Remove rows use a custom nexus-alpha-actions div containing adjacent form buttons and an anchor. Multiple adjacent buttons should be wrapped in a govuk-button-group rather than an ad-hoc flex container to ensure correct spacing and focus behaviour across browsers.
  FIX: Replace nexus-alpha-actions divs that contain only buttons/links with govuk-button-group. The button-group handles margins automatically so the govuk-!-margin-bottom-0 overrides on buttons can be removed.

- [medium] profile.blade.php
  ISSUE: The profile hero (nexus-alpha-profile-hero) places the h1 inside a content div that is beside the avatar media div (lines 67-71). The govuk-caption-l appears inside the content column, which is correct, but the h1 is inside a nested div with class nexus-alpha-profile-hero__content. There is no govuk-grid-row wrapping the hero section so the two-column hero layout uses custom flex positioning rather than the official GOV.UK grid, meaning it may not respect the govuk-width-container correctly.
  FIX: Wrap the profile hero in a govuk-grid-row with govuk-grid-column-one-quarter for the avatar and govuk-grid-column-three-quarters for the content, or retain nexus-alpha-profile-hero but ensure it is built on govuk-grid primitives inside a govuk-width-container.

- [medium] profile.blade.php
  ISSUE: Profile page (line 276): review rating is displayed as plain text 'Rating: N' (govuk-body-s nexus-alpha-meta). This uses numeric-only rating display with no star representation and no accessible text explaining the scale. In the React version a proper 5-star visual with aria-label is rendered.
  FIX: Display the rating as 'N out of 5' using a govuk-tag or accessible text: e.g. '<strong class="govuk-tag govuk-tag--yellow">{{ (int)$review["rating"] }}/5</strong>' with a visually-hidden full label for screen readers.

- [medium] profile-settings.blade.php
  ISSUE: The page has no h2 caption and starts directly with <h1> inside a govuk-grid-column-two-thirds at line 29. The passkey section (line 411) uses h3 without a parent h2 that owns the Security section — the section has an aria-labelledby="security-heading" on the section element (line 345) with h2 id="security-heading", which is correct. However the passkey card (line 429) uses h4 for the passkey device name, meaning the heading hierarchy skips from h3 to h4 without an intervening h3 context boundary.
  FIX: Change passkey card headings from h4 to h3 since they sit inside a section already labelled by an h2, and the passkey sub-heading (line 411) is an h3, making h4 children of h3 a level-skip. Or promote the passkey sub-heading to h2 and the card device names to h3.

- [medium] reviews.blade.php
  ISSUE: Lines 66-97 (Received) and 83-97 (Given) sections use plain h2 headings followed by cards. In the React version the page uses tabs (Received / Given / Pending) — the blade renders them as sequential stacked sections. This is acceptable for server-side rendering, but each section lacks a skip link or govuk-accordion to let users jump between sections on a long page, and the sections are not constrained to govuk-grid-column-two-thirds for comfortable reading width.
  FIX: Wrap the three sections (Received, Given, Pending) in a govuk-accordion so users can expand each section independently and find their section quickly. Each section already has the right heading level structure for govuk-accordion items.

- [medium] reviews.blade.php
  ISSUE: The review form inside each pending review card (lines 118-145) has no error-summary or govuk-form-group--error wiring. If the POST fails validation and the status query param is 'review-invalid', the error-summary at the top of the page (lines 35-51) shows a paragraph-level error but does not anchor to the specific failing form field — the anchor href in the error-summary list links to nothing because the field ids in the loop use $exId suffixes (e.g. comment-{{ $exId }}) but the error summary link uses a plain '#' anchor.
  FIX: Change the error summary list anchor to href="#rating-{{ $exId }}-5" or href="#comment-{{ $exId }}" to point at the first field of the first pending review form, and add govuk-form-group--error to the relevant form-group when the status matches.

### PARITY gaps

- [high] Group messaging / new group conversation
  BUILD: React has a 'New group' button opening a CreateGroupModal for multi-participant conversations. The blade messages view has no group conversation creation — only 1:1 user search. Add a 'Start a group conversation' link/form to messages.blade.php backed by an AlphaController endpoint.
  REACT: react-frontend/src/pages/messages/MessagesPage.tsx (line 486-488, CreateGroupModal import at line 29)

- [high] Send time credits (wallet transfer) from another member's profile
  BUILD: React profile shows a 'Send credits' button that opens a TransferModal when the viewer has the wallet module. The blade profile.blade.php has no equivalent action. Add a form linking to the accessible wallet send route with the recipient pre-filled (can reuse the existing govuk-alpha.wallet.send route with ?recipient_id=X).
  REACT: react-frontend/src/pages/profile/ProfilePage.tsx (line 783-791, TransferModal import at line 41)

- [medium] Write a review directly from another member's profile
  BUILD: React profile's action dropdown includes 'Write a review'. The blade profile.blade.php has no inline review-writing action on the profile view — reviews are only accessible from reviews.blade.php. Add a link to govuk-alpha.reviews.index or a simple form on the profile to submit a review.
  REACT: react-frontend/src/pages/profile/ProfilePage.tsx (line 803-811, ReviewModal at line 171)

- [medium] Availability grid on profile
  BUILD: React profile has an 'Availability' tab rendering an AvailabilityGrid showing day/time slots. The blade profile.blade.php has a basic govuk-summary-list for availability (lines 363-380) but it shows a flat label/time/note list rather than the structured grid. The controller already passes $profileAvailability — enhance the blade to render a day-of-week grid using a govuk-table.
  REACT: react-frontend/src/pages/profile/ProfilePage.tsx (line 905, tabs.availability tab, AvailabilityGrid component at line 46)

- [medium] Profile activity feed (ProfileFeed component)
  BUILD: React profile has a dedicated 'Activity' tab showing a paginated public feed of posts/exchanges from this user via ProfileFeed component. The blade profile.blade.php shows a simple timeline list of activity_type events (nexus-alpha-timeline) with no pagination and no post content — this is a summary view, not a real feed. Enhance with cursor pagination or link through to the public feed.
  REACT: react-frontend/src/pages/profile/ProfilePage.tsx (line 943, tabs.activity tab, ProfileFeed at line 42)

- [medium] Verification badge row on profile
  BUILD: React profile shows per-verification-type badges (ID verified, DBS checked etc.) via VerificationBadgeRow. The blade profile only shows a generic 'Verified' govuk-tag (line 74) with no type breakdown. Add individual verification type govuk-tags if the API returns them.
  REACT: react-frontend/src/pages/profile/ProfilePage.tsx (line 616, VerificationBadgeRow component)

- [medium] Delete own received reviews
  BUILD: React ReviewsPage allows deleting reviews the current user has given (GivenReviewCard with delete button). The blade reviews.blade.php has no delete action on given or received reviews. Add a confirmation + POST form with a _method:DELETE override to govuk-alpha.reviews.destroy for reviews the current user authored.
  REACT: react-frontend/src/pages/reviews/ReviewsPage.tsx (lines 106-123, DELETE /v2/reviews/{id})

- [medium] Search/filter on connections page
  BUILD: React ConnectionsPage has a search field to filter connections by name and uses Tabs for pending/accepted/sent. The blade connections.blade.php shows all three sections stacked with no search or filter. Add a GET search form (govuk-form-group + govuk-input) above the connections list to filter by name, passed as a query param to the controller.
  REACT: react-frontend/src/pages/connections/ConnectionsPage.tsx (SearchField and tab structure)

- [medium] Conversation search / filter on messages list
  BUILD: React MessagesPage has an always-visible search field filtering conversations by participant name client-side. The blade messages.blade.php only has a search to start a new conversation, not to filter existing ones. Add a GET search form to filter the conversation list by the other participant's name.
  REACT: react-frontend/src/pages/messages/MessagesPage.tsx (SearchField lines 529-538)


========================================
## CLUSTER: events
========================================

### POLISH findings

- [high] event-detail.blade.php
  ISSUE: The capacity tag (govuk-tag--red 'Full' / govuk-tag--green 'X spots left') is rendered inside the govuk-grid-column-one-third sidebar column (lines 127-132) which is outside the two-thirds reading column, while the page's h1 and all descriptive content are in the two-thirds column. This leaves the tag visually floating in a narrow right column with nothing else beside it — on mobile the sidebar collapses below the content making the status invisible until the user scrolls past the description. The tag should appear immediately below the h1, still inside the two-thirds column, before the description section.
  FIX: Move the govuk-tag capacity indicator into the govuk-grid-column-two-thirds block, immediately after the owner action buttons and before the cover image, so it is encountered in reading order.

- [medium] events.blade.php
  ISSUE: The 'Create event' button on line 42 sits alone in a bare <p> tag. No secondary action exists alongside it, so a govuk-button-group is not strictly required today, but the button uses role="button" + draggable="false" without the required data-module="govuk-button" JS attribute that makes the button keyboard-accessible and prevents double-submit. Wait — it does have data-module on line 42. However the search submit button on line 104 also has data-module but lives inside a nexus-alpha-actions div (not a govuk-button-group). When the 'Clear filters' link sits beside the search button they are adjacent actions and should be wrapped in a govuk-button-group to get correct spacing and stacking on mobile.
  FIX: Wrap the search button and 'Clear filters' link (lines 103-108) in <div class="govuk-button-group"> instead of the custom nexus-alpha-actions div. govuk-button-group handles button + link stacking responsively out of the box.

- [medium] event-detail.blade.php
  ISSUE: The event cancellation status (when event.status === 'cancelled') is never surfaced. The blade has no conditional for a cancelled-event banner — it simply shows the RSVP form. The React page shows a prominent red cancellation banner with the cancellation reason before any attendee action.
  FIX: Add a govuk-warning-text or govuk-notification-banner (blue/neutral variant) block at the top of the detail view when $event['status'] === 'cancelled', displaying the cancellation reason if present, and suppress the RSVP form when the event is cancelled.

- [medium] event-detail.blade.php
  ISSUE: The govuk-summary-list (lines 136-181) has no 'actions' column on any row despite the organiser being able to edit date, location, etc from the edit page. This is a presentation issue rather than a functionality gap, but the GOV.UK summary-list pattern expects optional govuk-summary-list__actions cells for change links on review/confirmation pages. On the detail page this is acceptable, however the attendee list below (lines 370-389) renders attendees as <li class="nexus-alpha-card-head"> which is a custom class rather than govuk-list styling — the list should use govuk-list class on the <ul> to get correct list spacing without the need for a custom class.
  FIX: Add class="govuk-list" to the <ul> on line 372 and remove nexus-alpha-card-head custom class from the <li> elements (the existing nexus-alpha-avatar and avatar-placeholder classes are fine to keep).

- [medium] event-create.blade.php
  ISSUE: The 'is_online' checkbox (line 119-125) and the 'online_link' URL input (line 127-130) are not grouped with a fieldset/legend, and there is no conditional disclosure to show the online_link field only when is_online is checked. In the GOV.UK pattern, a checkbox that reveals a related field should use govuk-checkboxes__conditional + data-module="govuk-checkboxes" conditional reveal. Without this the URL field is always visible even when the event is not online, creating a confusing form layout.
  FIX: Use govuk-checkboxes__conditional markup: add data-aria-controls="online-link-conditional" to the is_online input, and wrap the online_link form-group in <div class="govuk-checkboxes__conditional govuk-checkboxes__conditional--hidden" id="online-link-conditional">. This is a native govuk-frontend JS feature requiring no custom code.

- [medium] event-edit.blade.php
  ISSUE: The edit form has no image upload field, whereas event-create.blade.php does (lines 77-81 of event-create). If a user created an event with a cover image and then edits it via the blade, the image will be silently dropped when the form is submitted because there is no file input to re-attach it and no preview of the current image.
  FIX: Add a govuk-form-group with govuk-file-upload (same as event-create lines 77-81) to the edit form's details fieldset, and show the existing cover image as a preview above the input using <img> when $event['cover_image'] is set, with a 'Remove image' checkbox pattern.

### PARITY gaps

- [high] Recurrence / recurring event creation
  BUILD: Add a govuk-checkboxes 'Repeat this event' toggle to event-create and event-edit with conditional-reveal fieldsets for frequency (weekly/biweekly/monthly/daily govuk-radios), day-of-week checkboxes (for weekly), and end condition (after N occurrences vs on a date), then POST the RRULE string as a hidden input.
  REACT: react-frontend/src/pages/events/CreateEventPage.tsx (lines 48-143, FormData.isRecurring, recurrenceFrequency, recurrenceDays, recurrenceCount, recurrenceEndDate, buildRecurrenceRule())

- [high] Remote attendance / video meeting link on create and edit forms
  BUILD: Add a govuk-checkboxes__conditional 'Allow remote attendance' checkbox with a conditional URL input for the meeting/video link to both event-create.blade.php and event-edit.blade.php capacity/place fieldsets.
  REACT: react-frontend/src/pages/events/CreateEventPage.tsx (lines 63-66, FormData.allowRemoteAttendance, FormData.videoUrl)

- [high] Cancelled event status indicator and RSVP suppression
  BUILD: In event-detail.blade.php, check $event['status'] === 'cancelled' and render a govuk-warning-text block with the cancellation reason; wrap the RSVP form and waitlist section in @unless($event['status'] === 'cancelled') to match React behaviour.
  REACT: react-frontend/src/pages/events/EventDetailPage.tsx (lines 731-743, isCancelled banner, RSVP conditional on !isCancelled line 1222)

- [medium] Share / copy event link action on detail page
  BUILD: Add a govuk-button--secondary 'Copy link' form-action or JS-free 'Share via email' govuk-link on event-detail.blade.php (clipboard copy is JS; email mailto: link is a zero-JS fallback that satisfies the intent for this accessible track).
  REACT: react-frontend/src/pages/events/EventDetailPage.tsx (lines 339-348, handleShare)

- [medium] Organiser attendee check-in list
  BUILD: Add a POST /events/{id}/attendees/{attendeeId}/check-in action to each attendee row in the blade's attendee section, shown only when $isOwner; mark checked-in attendees with a govuk-tag--green 'Checked in' tag.
  REACT: react-frontend/src/pages/events/EventDetailPage.tsx (lines 371-413, handleCheckIn, tab_checkin, checkedInCount, checkInPercent)

- [medium] Social interaction panel — likes and comments on event
  BUILD: Add a 'Useful / Like' POST form below the RSVP section on event-detail.blade.php using the existing /v2/events/{id}/like endpoint pattern, displayed as a govuk-button--secondary with a count badge (govuk-tag); comments thread is lower priority (JS-heavy) but a simple flat govuk-textarea POST form for leaving a comment is feasible.
  REACT: react-frontend/src/pages/events/EventDetailPage.tsx (lines 1353-1363, SocialInteractionPanel)


========================================
## CLUSTER: groups
========================================

### POLISH findings

- [high] group-detail.blade.php
  ISSUE: The notification banner for success states (group-joined, group-created, etc.) is rendered ABOVE the govuk-caption-xl / <h1> block (lines 23–43 fire before line 45 caption). GOV.UK pattern requires the h1 to appear before banners on success pages, or the banner placed between h1 and the content — not before the heading. Additionally the status banners display regardless of whether the user just arrived from a redirect (no expiry/flash guard visible in the Blade), so a hard page refresh will re-show old success banners.
  FIX: Move the <h1> block (caption + heading) above all notification banners so the page always opens with a visible h1. Use the controller to pass banners only on redirect (flash session), not on direct GET.

- [medium] groups.blade.php
  ISSUE: The 'Create a group' call-to-action is wrapped in a bare <p> tag rendering an anchor styled as a button. GOV.UK buttons must be <button> elements or, for link-buttons, an <a> with role="button" and data-module="govuk-button". The current markup already has data-module on the <a>, but no role="button" or draggable="false", so the govuk-button JS module cannot initialise it correctly (spacebar activation fails).
  FIX: Change line 20 to: <a class="govuk-button" data-module="govuk-button" role="button" draggable="false" href="...">. Remove the wrapping <p> and place the anchor directly before the search <form>.

- [medium] group-detail.blade.php
  ISSUE: Admin actions (Edit / Manage links, lines 77–81) are inside a bare div with class nexus-alpha-actions. Multiple adjacent action links should use govuk-button-group for correct horizontal spacing and keyboard focus order, and the links should carry appropriate button roles or be govuk-link styled consistently. Currently they sit in a custom div with govuk-!-margin-right-4 utilities applied inline — not a govuk-button-group.
  FIX: Wrap the edit and manage links in <div class="govuk-button-group"> and render each as a govuk-button--secondary <a> with role="button" draggable="false" data-module="govuk-button".

- [medium] group-detail.blade.php
  ISSUE: The member list (lines 84–96) uses a flat govuk-list <ul> containing plain text or links without any role context or visual separation. For a group detail page with potentially 50+ members this should be paginated or limited with a 'View all' link. More importantly, the member list duplicates the same data already shown in the Manage tab — on the detail page, a govuk-summary-list showing key group metadata (visibility, member count, organiser) would be more appropriate than the full member list.
  FIX: Replace the flat member list on group-detail with a govuk-summary-list showing: Visibility, Members (count with link to manage), Founded/created date. Move the full member list exclusively to group-manage.blade.php.

- [medium] group-create.blade.php
  ISSUE: The error notification banner on line 19 uses govuk-notification-banner (the information variant, blue header) to signal a creation failure. GOV.UK pattern mandates govuk-error-summary for validation/submission failures — not a notification banner. The notification banner is for success confirmations or important information, not errors. An error-summary also requires tabindex="-1" and autofocus on page load to move screen reader focus.
  FIX: Replace the govuk-notification-banner block (lines 19–28) with a govuk-error-summary block (matching the pattern already used on lines 31–43 for field errors). Remove the now-duplicate pattern.

- [medium] group-edit.blade.php
  ISSUE: The group-edit form is missing a tags field that exists in group-create (line 83–86 of group-create.blade.php). The edit form (group-edit.blade.php) has no tags input, so admins who created a group with tags cannot update them via the accessible frontend.
  FIX: Add the govuk-form-group containing the tags input (mirroring group-create lines 82–86) between the cover image field and the submit button in group-edit.blade.php. Wire it to the controller's update action.

- [medium] group-edit.blade.php
  ISSUE: The two error notification banners (lines 22–39) for group-update-failed and group-delete-failed use the information-variant govuk-notification-banner (blue) not the error pattern. Error conditions must use govuk-error-summary (role alert, tabindex=-1) so screen readers announce the failure immediately on page load.
  FIX: Replace both govuk-notification-banner blocks that signal failures with govuk-error-summary blocks, each with role="alert" and tabindex="-1", matching the pattern used for field validation errors on lines 43–55.

- [medium] group-manage.blade.php
  ISSUE: Multiple promote/demote/remove buttons for each member (lines 92–109) are rendered as independent <form> tags with govuk-!-display-inline but without a govuk-button-group wrapper. This means the buttons lose correct spacing and the submit button for the remove (warning) action sits directly adjacent to the promote/demote action without the separation GOV.UK warning button patterns require.
  FIX: Wrap the per-member action forms in <div class="govuk-button-group">. The govuk-button-group correctly spaces inline forms/buttons and ensures the warning button is visually separated from secondary actions.

- [medium] group-discussion-detail.blade.php
  ISSUE: Reply messages (lines 60–74) are rendered as <li class="nexus-alpha-card"> inside a <ul class="govuk-list">. Using nexus-alpha-card (a block with box-shadow/border) as a list item creates inconsistent visual treatment — the govuk-list styles apply padding/bullet suppression to <li> but the card styles add their own box. Additionally, each reply shows author name as a bare <p> with no timestamp, which is a significant accessibility omission for conversation threading.
  FIX: Replace the <ul>/<li> pattern with a series of <article class="nexus-alpha-card"> elements (matching the original post style). Add <time> elements for each reply's created_at. Use govuk-heading-s or govuk-body-s with a <strong> for the author name.

- [medium] group-exchange-create.blade.php
  ISSUE: The fieldset for split_type (lines 45–57) is missing govuk-form-group as a wrapper — it is directly a <fieldset> without the standard govuk-form-group container div. GOV.UK form pattern requires the govuk-form-group wrapper outside the fieldset for consistent spacing and error state application.
  FIX: Wrap the <fieldset> in <div class="govuk-form-group"> matching the pattern for name/description/total_hours fields above it.

- [medium] group-exchange-detail.blade.php
  ISSUE: The complete/cancel action buttons (lines 185–192) are wrapped in <div class="nexus-alpha-actions"> not govuk-button-group. The complete button uses a conditional govuk-button--disabled class but also has disabled and aria-disabled="true" attributes applied inline — GOV.UK warns against using the disabled attribute on buttons (it removes them from tab order). Use aria-disabled only and handle the no-op in the form submission or server side.
  FIX: Wrap both forms in <div class="govuk-button-group">. Remove the HTML disabled attribute from the complete button; keep only aria-disabled="true" and the visual govuk-button--disabled class. Add a server-side guard or show a govuk-warning-text below instead of disabling the button.

### PARITY gaps

- [high] Group exchange status model mismatch — React has statuses pending_participants, pending_broker, pending_confirmation, disputed; the blade's $knownStatuses only covers draft/pending/approved/active/completed/cancelled
  BUILD: Extend $knownStatuses and $statusTag in group-exchanges.blade.php and group-exchange-detail.blade.php to include pending_participants, pending_broker, pending_confirmation, and disputed with appropriate govuk-tag colour modifiers.
  REACT: react-frontend/src/pages/group-exchanges/GroupExchangeDetailPage.tsx lines 54–62

- [medium] Cursor-based 'Load more' pagination for the groups list — the blade shows all groups in a single pass with no pagination or load-more mechanism
  BUILD: Add ?page= query param support in the controller and render govuk-pagination at the bottom of groups.blade.php.
  REACT: react-frontend/src/pages/groups/GroupsPage.tsx lines 402–434

- [medium] Pinned announcements banner on group detail — a sticky banner showing pinned group announcements above the tab area
  BUILD: Fetch pinned announcements in the controller for members and render them as govuk-inset-text or govuk-notification-banner blocks in group-detail.blade.php above the events section.
  REACT: react-frontend/src/pages/groups/GroupDetailPage.tsx line 1124 (PinnedAnnouncementsBanner component)

- [medium] Group location field — React create/edit supports a location text field with geocoding; accessible create/edit has no location field
  BUILD: Add a govuk-form-group with a govuk-input for 'location' text to both group-create.blade.php and group-edit.blade.php, and include location in the controller's store/update payload.
  REACT: react-frontend/src/pages/groups/CreateGroupPage.tsx lines 29, 45 (PlaceAutocompleteInput, location in FormData)

- [medium] Group invite — members (or admins) can invite others by email or via a shareable invite link
  BUILD: Add an invite section to group-manage.blade.php with a govuk-form-group email input and POST route for sending invitations.
  REACT: react-frontend/src/pages/groups/GroupDetailPage.tsx lines 245–271 (handleGenerateInviteLink, handleSendInvites) and GroupModals.tsx (GroupInviteModal)

- [medium] Group announcements tab — admins can create pinned announcements; members can read them in a dedicated tab
  BUILD: Add a group-announcements.blade.php page and controller action served at a sub-route of group-detail; add link from group-detail.blade.php for members.
  REACT: react-frontend/src/pages/groups/tabs/GroupAnnouncementsTab.tsx

- [medium] Group exchanges list — React shows participant count, organiser name, and organiser avatar per exchange card; blade table shows only title, status, total hours
  BUILD: Add participant_count and organizer_name columns to the govuk-table in group-exchanges.blade.php; pass these from the controller.
  REACT: react-frontend/src/pages/group-exchanges/GroupExchangesPage.tsx lines 44–58 (GroupExchange interface: participant_count, organizer_name)

- [medium] Group exchanges list filter tabs — React shows All / Active / Completed / Cancelled filter tabs; blade has no filtering
  BUILD: Add ?status= GET param support in the controller and render govuk-tabs or filter links above the table in group-exchanges.blade.php.
  REACT: react-frontend/src/pages/group-exchanges/GroupExchangesPage.tsx lines 31 (Tabs, Tab imports) and status filter logic
