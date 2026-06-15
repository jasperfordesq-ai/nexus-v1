<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

Route::pattern('tenantSlug', '[a-zA-Z0-9_-]+');

Route::get('/', [AlphaController::class, 'tenantChooser'])->name('govuk-alpha.tenant-chooser');

Route::prefix('alpha')
    ->name('govuk-alpha.host.')
    ->group(function () {
        Route::get('/', [AlphaController::class, 'hostHome'])->name('home');
        Route::get('/login', [AlphaController::class, 'hostLogin'])->name('login');
        Route::get('/register', [AlphaController::class, 'hostRegister'])->name('register');
        Route::get('/contact', [AlphaController::class, 'hostContact'])->name('contact');
    });

Route::prefix('{tenantSlug}/alpha')
    ->name('govuk-alpha.')
    ->group(function () {
        Route::get('/', [AlphaController::class, 'home'])->name('home');
        Route::get('/contact', [AlphaController::class, 'contact'])->name('contact');
        Route::post('/contact', [AlphaController::class, 'storeContact'])->middleware('throttle:5,1')->name('contact.store');

        // Content & legal pages (footer destinations). Legal documents reuse the
        // tenant-scoped LegalDocumentService with a GOV.UK static fallback; the
        // shared legalDocument() method reads the document type from route defaults.
        Route::get('/about', [AlphaController::class, 'about'])->name('about');
        Route::get('/guide', [AlphaController::class, 'guide'])->name('guide');
        Route::get('/features', [AlphaController::class, 'features'])->name('features');
        Route::get('/faq', [AlphaController::class, 'faq'])->name('faq');
        Route::get('/trust-and-safety', [AlphaController::class, 'trustSafety'])->name('trust-safety');
        Route::get('/accessibility', [AlphaController::class, 'accessibility'])->name('accessibility');
        Route::get('/legal', [AlphaController::class, 'legalHub'])->name('legal.hub');
        Route::get('/legal/terms', [AlphaController::class, 'legalDocument'])->defaults('type', 'terms')->name('legal.terms');
        Route::get('/legal/privacy', [AlphaController::class, 'legalDocument'])->defaults('type', 'privacy')->name('legal.privacy');
        Route::get('/legal/cookies', [AlphaController::class, 'legalDocument'])->defaults('type', 'cookies')->name('legal.cookies');
        Route::get('/legal/community-guidelines', [AlphaController::class, 'legalDocument'])->defaults('type', 'community_guidelines')->name('legal.community-guidelines');
        Route::get('/legal/acceptable-use', [AlphaController::class, 'legalDocument'])->defaults('type', 'acceptable_use')->name('legal.acceptable-use');

        // Help centre, knowledge base and blog (native, server-rendered).
        Route::get('/help', [AlphaController::class, 'help'])->name('help');
        Route::get('/kb', [AlphaController::class, 'kb'])->name('kb.index');
        Route::get('/kb/{id}', [AlphaController::class, 'kbArticle'])->whereNumber('id')->name('kb.show');
        Route::get('/blog', [AlphaController::class, 'blog'])->name('blog.index');
        Route::get('/blog/feed.xml', [AlphaController::class, 'blogFeed'])->name('blog.feed');
        Route::get('/blog/{slug}', [AlphaController::class, 'blogPost'])->where('slug', '[a-zA-Z0-9_-]+')->name('blog.show');
        Route::post('/blog/{slug}/comments', [AlphaController::class, 'storeBlogComment'])->where('slug', '[a-zA-Z0-9_-]+')->middleware('throttle:20,1')->name('blog.comments.store');
        Route::get('/login', [AlphaController::class, 'login'])->name('login');
        Route::post('/login', [AlphaController::class, 'storeLogin'])->middleware('throttle:30,1')->name('login.store');
        Route::post('/login/resend-verification', [AlphaController::class, 'resendVerification'])->middleware('throttle:5,5')->name('login.resend');
        Route::post('/logout', [AlphaController::class, 'logout'])->name('logout');
        Route::get('/login/two-factor', [AlphaController::class, 'twoFactor'])->name('login.twofactor');
        Route::post('/login/two-factor', [AlphaController::class, 'storeTwoFactor'])->middleware('throttle:10,1')->name('login.twofactor.store');
        Route::get('/login/forgot-password', [AlphaController::class, 'forgotPassword'])->name('login.forgot');
        Route::post('/login/forgot-password', [AlphaController::class, 'sendPasswordReset'])->middleware('throttle:5,1')->name('login.forgot.store');
        Route::get('/password/reset', [AlphaController::class, 'showResetPassword'])->name('password.reset');
        Route::post('/password/reset', [AlphaController::class, 'storeResetPassword'])->middleware('throttle:5,1')->name('password.reset.store');
        Route::get('/register', [AlphaController::class, 'register'])->name('register');
        Route::post('/register', [AlphaController::class, 'storeRegister'])->middleware('throttle:5,5')->name('register.store');
        // Public landings reached from email links (visitor is signed out).
        Route::get('/verify-email', [AlphaController::class, 'verifyEmail'])->middleware('throttle:20,1')->name('verify-email');
        Route::get('/newsletter/unsubscribe', [AlphaController::class, 'newsletterUnsubscribe'])->middleware('throttle:20,1')->name('newsletter.unsubscribe');

        Route::get('/dashboard', [AlphaController::class, 'dashboard'])->name('dashboard');
        // Onboarding wizard (session-backed, HTML-first multi-step).
        Route::get('/onboarding', [AlphaController::class, 'onboarding'])->name('onboarding');
        Route::get('/onboarding/{step}', [AlphaController::class, 'onboardingStep'])->where('step', '[a-z]+')->name('onboarding.step');
        Route::post('/onboarding/avatar', [AlphaController::class, 'onboardingAvatar'])->middleware('throttle:20,1')->name('onboarding.avatar');
        Route::post('/onboarding/{step}', [AlphaController::class, 'onboardingStepPost'])->where('step', '[a-z]+')->middleware('throttle:30,1')->name('onboarding.step.post');
        Route::get('/events', [AlphaController::class, 'events'])->name('events.index');
        Route::get('/events/new', [AlphaController::class, 'createEvent'])->name('events.create');
        Route::post('/events/new', [AlphaController::class, 'storeEvent'])->middleware('throttle:10,1')->name('events.store');
        Route::get('/events/{id}/edit', [AlphaController::class, 'editEvent'])->whereNumber('id')->name('events.edit');
        Route::post('/events/{id}/edit', [AlphaController::class, 'updateEvent'])->whereNumber('id')->middleware('throttle:10,1')->name('events.update');
        Route::post('/events/{id}/cancel', [AlphaController::class, 'cancelEvent'])->whereNumber('id')->middleware('throttle:10,1')->name('events.cancel');
        Route::post('/events/{id}/delete', [AlphaController::class, 'deleteEvent'])->whereNumber('id')->middleware('throttle:10,1')->name('events.delete');
        Route::get('/events/{id}', [AlphaController::class, 'event'])->whereNumber('id')->name('events.show');
        Route::post('/events/{id}/rsvp', [AlphaController::class, 'storeEventRsvp'])->whereNumber('id')->name('events.rsvp.store');
        Route::post('/events/{id}/waitlist', [AlphaController::class, 'joinEventWaitlist'])->whereNumber('id')->middleware('throttle:30,1')->name('events.waitlist.join');
        Route::post('/events/{id}/waitlist/leave', [AlphaController::class, 'leaveEventWaitlist'])->whereNumber('id')->middleware('throttle:30,1')->name('events.waitlist.leave');
        Route::post('/events/{id}/polls/{pollId}/vote', [AlphaController::class, 'storeEventPollVote'])->whereNumber('id')->whereNumber('pollId')->middleware('throttle:30,1')->name('events.polls.vote');
        Route::get('/volunteering', [AlphaController::class, 'volunteering'])->name('volunteering.index');
        Route::get('/volunteering/hours', [AlphaController::class, 'volunteeringHours'])->name('volunteering.hours');
        Route::get('/volunteering/accessibility', [AlphaController::class, 'volunteerAccessibility'])->name('volunteering.accessibility');
        Route::post('/volunteering/accessibility', [AlphaController::class, 'updateVolunteerAccessibility'])->middleware('throttle:20,1')->name('volunteering.accessibility.update');
        Route::post('/volunteering/hours', [AlphaController::class, 'storeVolunteeringHours'])->middleware('throttle:20,1')->name('volunteering.hours.store');
        Route::get('/volunteering/opportunities/{id}', [AlphaController::class, 'volunteerOpportunity'])->whereNumber('id')->name('volunteering.show');
        Route::post('/volunteering/opportunities/{id}/apply', [AlphaController::class, 'applyVolunteerOpportunity'])->middleware('throttle:20,1')->whereNumber('id')->name('volunteering.apply.store');
        Route::post('/volunteering/applications/{id}/withdraw', [AlphaController::class, 'withdrawVolunteerApplication'])->middleware('throttle:20,1')->whereNumber('id')->name('volunteering.applications.withdraw');
        Route::post('/volunteering/opportunities/{id}/shifts/{shiftId}/signup', [AlphaController::class, 'signUpForVolunteerShift'])->middleware('throttle:20,1')->whereNumber('id')->whereNumber('shiftId')->name('volunteering.shifts.signup');
        Route::post('/volunteering/opportunities/{id}/shifts/{shiftId}/cancel', [AlphaController::class, 'cancelVolunteerShift'])->middleware('throttle:20,1')->whereNumber('id')->whereNumber('shiftId')->name('volunteering.shifts.cancel');
        Route::get('/feed', [AlphaController::class, 'feed'])->name('feed');
        Route::post('/feed/posts', [AlphaController::class, 'storeFeedPost'])->middleware('throttle:20,1')->name('feed.posts.store');
        Route::post('/feed/polls/{pollId}/vote', [AlphaController::class, 'storeFeedPollVote'])->whereNumber('pollId')->middleware('throttle:30,1')->name('feed.polls.vote');
        Route::post('/feed/items/{type}/{id}/like', [AlphaController::class, 'storeFeedLike'])
            ->whereNumber('id')
            ->where('type', '[a-zA-Z0-9_-]+')
            ->middleware('throttle:60,1')
            ->name('feed.items.like');
        Route::post('/feed/items/{type}/{id}/comments', [AlphaController::class, 'storeFeedComment'])
            ->whereNumber('id')
            ->where('type', '[a-zA-Z0-9_-]+')
            ->middleware('throttle:30,1')
            ->name('feed.items.comments.store');
        Route::post('/feed/posts/{id}/update', [AlphaController::class, 'updateFeedPost'])->whereNumber('id')->middleware('throttle:20,1')->name('feed.posts.update');
        Route::post('/feed/posts/{id}/delete', [AlphaController::class, 'deleteFeedPost'])->whereNumber('id')->middleware('throttle:20,1')->name('feed.posts.delete');
        Route::post('/feed/posts/{id}/hide', [AlphaController::class, 'hideFeedItem'])->whereNumber('id')->middleware('throttle:30,1')->name('feed.hide');
        Route::post('/feed/users/{id}/mute', [AlphaController::class, 'muteFeedUser'])->whereNumber('id')->middleware('throttle:30,1')->name('feed.mute');
        Route::post('/feed/posts/{id}/report', [AlphaController::class, 'reportFeedItem'])->whereNumber('id')->middleware('throttle:15,1')->name('feed.report');
        Route::post('/feed/comments/{id}/update', [AlphaController::class, 'updateFeedComment'])->whereNumber('id')->middleware('throttle:30,1')->name('feed.comments.update');
        Route::post('/feed/comments/{id}/delete', [AlphaController::class, 'deleteFeedComment'])->whereNumber('id')->middleware('throttle:30,1')->name('feed.comments.delete');
        // ===== WAVE T1-FEED =====
        Route::get('/feed/posts/{id}', [AlphaController::class, 'feedPost'])->whereNumber('id')->name('feed.posts.show');
        Route::post('/feed/posts/{id}/react', [AlphaController::class, 'storeFeedPostReaction'])->whereNumber('id')->middleware('throttle:60,1')->name('feed.posts.react');
        Route::post('/feed/comments/{id}/react', [AlphaController::class, 'storeFeedCommentReaction'])->whereNumber('id')->middleware('throttle:60,1')->name('feed.comments.react');
        Route::post('/feed/posts/{id}/share', [AlphaController::class, 'storeFeedPostShare'])->whereNumber('id')->middleware('throttle:20,1')->name('feed.posts.share');
        Route::post('/feed/posts/{id}/save', [AlphaController::class, 'storeFeedPostSave'])->whereNumber('id')->middleware('throttle:30,1')->name('feed.posts.save');
        Route::get('/listings', [AlphaController::class, 'listings'])->name('listings.index');
        Route::get('/listings/new', [AlphaController::class, 'createListing'])->name('listings.create');
        Route::post('/listings/new', [AlphaController::class, 'storeListing'])->middleware('throttle:10,1')->name('listings.store');
        Route::get('/listings/{id}/edit', [AlphaController::class, 'editListing'])->whereNumber('id')->name('listings.edit');
        Route::post('/listings/{id}/edit', [AlphaController::class, 'updateListing'])->whereNumber('id')->middleware('throttle:10,1')->name('listings.update');
        Route::post('/listings/{id}/delete', [AlphaController::class, 'deleteListing'])->whereNumber('id')->middleware('throttle:10,1')->name('listings.delete');
        Route::get('/listings/{listingId}/exchange-request', [AlphaController::class, 'requestExchange'])->whereNumber('listingId')->name('exchanges.request');
        Route::post('/listings/{listingId}/exchange-request', [AlphaController::class, 'storeExchangeRequest'])->middleware('throttle:20,1')->whereNumber('listingId')->name('exchanges.request.store');
        Route::get('/listings/{id}', [AlphaController::class, 'listing'])->whereNumber('id')->name('listings.show');
        Route::get('/exchanges', [AlphaController::class, 'exchanges'])->name('exchanges.index');
        Route::get('/exchanges/{id}', [AlphaController::class, 'exchange'])->whereNumber('id')->name('exchanges.show');
        Route::post('/exchanges/{id}', [AlphaController::class, 'storeExchangeAction'])->middleware('throttle:30,1')->whereNumber('id')->name('exchanges.action.store');
        Route::post('/exchanges/{id}/rate', [AlphaController::class, 'storeExchangeRating'])->middleware('throttle:20,1')->whereNumber('id')->name('exchanges.rate.store');
        Route::get('/group-exchanges', [AlphaController::class, 'groupExchanges'])->name('group-exchanges.index');
        Route::get('/group-exchanges/new', [AlphaController::class, 'createGroupExchange'])->name('group-exchanges.create');
        Route::post('/group-exchanges/new', [AlphaController::class, 'storeGroupExchange'])->middleware('throttle:15,1')->name('group-exchanges.store');
        Route::get('/group-exchanges/{id}', [AlphaController::class, 'groupExchange'])->whereNumber('id')->name('group-exchanges.show');
        Route::post('/group-exchanges/{id}/participants', [AlphaController::class, 'addGroupExchangeParticipant'])->middleware('throttle:30,1')->whereNumber('id')->name('group-exchanges.participants.add');
        Route::post('/group-exchanges/{id}/participants/{participantUserId}/remove', [AlphaController::class, 'removeGroupExchangeParticipant'])->middleware('throttle:30,1')->whereNumber('id')->whereNumber('participantUserId')->name('group-exchanges.participants.remove');
        Route::post('/group-exchanges/{id}/confirm', [AlphaController::class, 'confirmGroupExchange'])->middleware('throttle:20,1')->whereNumber('id')->name('group-exchanges.confirm');
        Route::post('/group-exchanges/{id}/complete', [AlphaController::class, 'completeGroupExchange'])->middleware('throttle:15,1')->whereNumber('id')->name('group-exchanges.complete');
        Route::post('/group-exchanges/{id}/cancel', [AlphaController::class, 'cancelGroupExchange'])->middleware('throttle:15,1')->whereNumber('id')->name('group-exchanges.cancel');
        Route::get('/matches', [AlphaController::class, 'matches'])->name('matches.index');
        Route::get('/polls', [AlphaController::class, 'polls'])->name('polls.index');
        Route::post('/polls', [AlphaController::class, 'storePoll'])->middleware('throttle:10,1')->name('polls.store');
        Route::post('/polls/{pollId}/vote', [AlphaController::class, 'storePollVote'])->whereNumber('pollId')->middleware('throttle:30,1')->name('polls.vote');
        Route::get('/wallet', [AlphaController::class, 'wallet'])->name('wallet.index');
        // ===== WAVE T1-WALLET =====
        // CSV export registered before any /wallet/* sibling so a future wildcard
        // can never shadow it. Credit-moving donate is rate-limited tightly.
        Route::get('/wallet/export.csv', [AlphaController::class, 'exportTransactions'])->middleware('throttle:10,1')->name('wallet.export');
        Route::post('/wallet/donate', [AlphaController::class, 'donateCredits'])->middleware('throttle:10,1')->name('wallet.donate');
        Route::post('/wallet/transfer', [AlphaController::class, 'transferCredits'])->middleware('throttle:15,1')->name('wallet.transfer');
        Route::get('/wallet/recipients', [AlphaController::class, 'walletRecipients'])->middleware('throttle:60,1')->name('wallet.recipients');
        Route::get('/messages', [AlphaController::class, 'messages'])->name('messages.index');
        Route::get('/messages/new/{userId}', [AlphaController::class, 'conversation'])->whereNumber('userId')->name('messages.new');
        Route::get('/messages/{userId}', [AlphaController::class, 'conversation'])->whereNumber('userId')->name('messages.show');
        Route::post('/messages/{userId}', [AlphaController::class, 'storeMessage'])->middleware('throttle:30,1')->whereNumber('userId')->name('messages.store');
        Route::post('/messages/{userId}/archive', [AlphaController::class, 'archiveConversation'])->middleware('throttle:20,1')->whereNumber('userId')->name('messages.archive');
        Route::post('/messages/{userId}/restore', [AlphaController::class, 'restoreConversation'])->middleware('throttle:20,1')->whereNumber('userId')->name('messages.restore');
        Route::post('/messages/{userId}/m/{messageId}/edit', [AlphaController::class, 'updateMessage'])->middleware('throttle:30,1')->whereNumber('userId')->whereNumber('messageId')->name('messages.edit');
        Route::post('/messages/{userId}/m/{messageId}/delete', [AlphaController::class, 'deleteMessage'])->middleware('throttle:20,1')->whereNumber('userId')->whereNumber('messageId')->name('messages.delete');
        Route::get('/members', [AlphaController::class, 'members'])->name('members.index');
        Route::get('/members/{id}', [AlphaController::class, 'memberProfile'])->whereNumber('id')->name('members.show');
        Route::post('/members/{id}/connection', [AlphaController::class, 'updateMemberConnection'])->middleware('throttle:20,1')->whereNumber('id')->name('members.connection');
        Route::post('/members/{id}/endorse', [AlphaController::class, 'endorseMemberSkill'])->middleware('throttle:30,1')->whereNumber('id')->name('members.endorse');
        // ===== WAVE T1-SAFETY: block / unblock a member =====
        Route::post('/members/{id}/block', [AlphaController::class, 'blockMember'])->middleware('throttle:20,1')->whereNumber('id')->name('members.block');
        Route::post('/members/{id}/unblock', [AlphaController::class, 'unblockMember'])->middleware('throttle:20,1')->whereNumber('id')->name('members.unblock');
        Route::get('/connections', [AlphaController::class, 'connections'])->name('connections.index');
        Route::post('/connections/{id}/accept', [AlphaController::class, 'acceptConnection'])->middleware('throttle:30,1')->whereNumber('id')->name('connections.accept');
        Route::post('/connections/{id}/decline', [AlphaController::class, 'declineConnection'])->middleware('throttle:30,1')->whereNumber('id')->name('connections.decline');
        Route::post('/connections/{id}/remove', [AlphaController::class, 'cancelConnection'])->middleware('throttle:30,1')->whereNumber('id')->name('connections.remove');
        Route::get('/account', [AlphaController::class, 'account'])->name('account');
        Route::get('/achievements', [AlphaController::class, 'achievements'])->name('achievements');
        // ===== WAVE POLISH-GAMIFY: daily reward + challenge claim (static before any {id}) =====
        Route::post('/achievements/daily-reward', [AlphaController::class, 'dailyReward'])->middleware('throttle:5,1')->name('achievements.daily-reward');
        Route::post('/achievements/challenges/{id}/claim', [AlphaController::class, 'claimChallengeReward'])->whereNumber('id')->middleware('throttle:20,1')->name('achievements.claim-challenge');
        Route::get('/leaderboard', [AlphaController::class, 'leaderboard'])->name('leaderboard');
        Route::get('/nexus-score', [AlphaController::class, 'nexusScore'])->name('nexus-score');
        Route::get('/notifications', [AlphaController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/read-all', [AlphaController::class, 'markAllNotificationsRead'])->middleware('throttle:20,1')->name('notifications.read-all');
        Route::post('/notifications/{id}/delete', [AlphaController::class, 'deleteNotification'])->whereNumber('id')->middleware('throttle:60,1')->name('notifications.delete');
        Route::post('/notifications/{id}/read', [AlphaController::class, 'markNotificationRead'])->whereNumber('id')->middleware('throttle:60,1')->name('notifications.mark-read');
        Route::post('/notifications/delete-all', [AlphaController::class, 'deleteAllNotifications'])->middleware('throttle:5,1')->name('notifications.delete-all');
        Route::get('/activity', [AlphaController::class, 'activity'])->name('activity');
        Route::get('/reviews', [AlphaController::class, 'reviews'])->name('reviews.index');
        Route::post('/reviews', [AlphaController::class, 'storeReview'])->middleware('throttle:10,1')->name('reviews.store');
        Route::get('/explore', [AlphaController::class, 'explore'])->name('explore');
        Route::get('/search', [AlphaController::class, 'search'])->name('search');
        Route::get('/skills', [AlphaController::class, 'skills'])->name('skills.index');
        Route::get('/groups', [AlphaController::class, 'groups'])->name('groups.index');
        // Static segments BEFORE the {id} catch-all so /groups/new is not swallowed.
        Route::get('/groups/new', [AlphaController::class, 'createGroup'])->name('groups.create');
        Route::post('/groups/new', [AlphaController::class, 'storeGroup'])->middleware('throttle:15,1')->name('groups.store');
        Route::get('/groups/{id}', [AlphaController::class, 'group'])->whereNumber('id')->name('groups.show');
        Route::post('/groups/{id}/join', [AlphaController::class, 'joinGroup'])->whereNumber('id')->middleware('throttle:30,1')->name('groups.join');
        Route::post('/groups/{id}/leave', [AlphaController::class, 'leaveGroup'])->whereNumber('id')->middleware('throttle:30,1')->name('groups.leave');
        // Group management (owner/admin actions; ownership re-verified server-side).
        Route::get('/groups/{id}/edit', [AlphaController::class, 'editGroup'])->whereNumber('id')->name('groups.edit');
        Route::post('/groups/{id}/edit', [AlphaController::class, 'updateGroup'])->whereNumber('id')->middleware('throttle:15,1')->name('groups.update');
        Route::post('/groups/{id}/delete', [AlphaController::class, 'deleteGroup'])->whereNumber('id')->middleware('throttle:10,1')->name('groups.delete');
        Route::get('/groups/{id}/manage', [AlphaController::class, 'manageGroup'])->whereNumber('id')->name('groups.manage');
        Route::post('/groups/{id}/members/{memberId}', [AlphaController::class, 'updateGroupMember'])->whereNumber('id')->whereNumber('memberId')->middleware('throttle:30,1')->name('groups.members.update');
        Route::post('/groups/{id}/requests/{requesterId}', [AlphaController::class, 'handleGroupRequest'])->whereNumber('id')->whereNumber('requesterId')->middleware('throttle:30,1')->name('groups.requests.handle');
        // Group discussions (active members only; re-checked in the service layer).
        Route::get('/groups/{id}/discussions', [AlphaController::class, 'groupDiscussions'])->whereNumber('id')->name('groups.discussions.index');
        Route::get('/groups/{id}/discussions/new', [AlphaController::class, 'createGroupDiscussion'])->whereNumber('id')->name('groups.discussions.create');
        Route::post('/groups/{id}/discussions/new', [AlphaController::class, 'storeGroupDiscussion'])->whereNumber('id')->middleware('throttle:15,1')->name('groups.discussions.store');
        Route::get('/groups/{id}/discussions/{discussionId}', [AlphaController::class, 'groupDiscussion'])->whereNumber('id')->whereNumber('discussionId')->name('groups.discussions.show');
        Route::post('/groups/{id}/discussions/{discussionId}/reply', [AlphaController::class, 'replyGroupDiscussion'])->whereNumber('id')->whereNumber('discussionId')->middleware('throttle:30,1')->name('groups.discussions.reply');
        // ===== WAVE T1-GROUPS: group detail depth (events + feed tabs) =====
        // Read tabs (events + feed) render inline on groups.show; this is the
        // members-only compose endpoint for the group feed.
        Route::post('/groups/{id}/feed', [AlphaController::class, 'storeGroupFeedPost'])->whereNumber('id')->middleware('throttle:30,1')->name('groups.feed.store');
        Route::get('/goals', [AlphaController::class, 'goals'])->name('goals.index');
        Route::post('/goals', [AlphaController::class, 'storeGoal'])->middleware('throttle:15,1')->name('goals.store');
        // Static goal sub-routes declared before /goals/{id} so they are never
        // shadowed by the numeric id matcher.
        Route::get('/goals/templates', [AlphaController::class, 'goalTemplates'])->name('goals.templates');
        Route::post('/goals/templates/{id}', [AlphaController::class, 'storeGoalFromTemplate'])->whereNumber('id')->middleware('throttle:15,1')->name('goals.templates.use');
        Route::get('/goals/buddying', [AlphaController::class, 'goalBuddying'])->name('goals.buddying');
        // Static segment /discover registered before {id} so it is never shadowed.
        Route::get('/goals/discover', [AlphaController::class, 'goalDiscover'])->name('goals.discover');
        Route::get('/goals/{id}', [AlphaController::class, 'goal'])->whereNumber('id')->name('goals.show');
        Route::get('/goals/{id}/edit', [AlphaController::class, 'editGoalForm'])->whereNumber('id')->name('goals.edit');
        Route::post('/goals/{id}/edit', [AlphaController::class, 'updateGoal'])->whereNumber('id')->middleware('throttle:15,1')->name('goals.update');
        Route::post('/goals/{id}/delete', [AlphaController::class, 'deleteGoal'])->whereNumber('id')->middleware('throttle:15,1')->name('goals.delete');
        Route::post('/goals/{id}/buddy', [AlphaController::class, 'becomeGoalBuddy'])->whereNumber('id')->middleware('throttle:15,1')->name('goals.buddy');
        Route::post('/goals/{id}/buddy-nudge', [AlphaController::class, 'buddyNudge'])->whereNumber('id')->middleware('throttle:10,1')->name('goals.buddy-nudge');
        Route::post('/goals/{id}/progress', [AlphaController::class, 'incrementGoal'])->whereNumber('id')->middleware('throttle:30,1')->name('goals.progress');
        Route::post('/goals/{id}/complete', [AlphaController::class, 'completeGoal'])->whereNumber('id')->middleware('throttle:15,1')->name('goals.complete');
        Route::get('/organisations', [AlphaController::class, 'organisations'])->name('organisations.index');
        Route::post('/organisations', [AlphaController::class, 'storeOrganisation'])->middleware('throttle:5,5')->name('organisations.store');
        Route::get('/organisations/{id}', [AlphaController::class, 'organisation'])->whereNumber('id')->name('organisations.show');
        Route::get('/saved', [AlphaController::class, 'saved'])->name('saved.index');
        Route::post('/saved/destroy', [AlphaController::class, 'destroySaved'])->middleware('throttle:30,1')->name('saved.destroy');
        Route::get('/resources', [AlphaController::class, 'resources'])->name('resources.index');
        Route::get('/jobs', [AlphaController::class, 'jobs'])->name('jobs.index');
        // Static segments are registered before the {id} wildcard so they always win.
        Route::get('/jobs/saved', [AlphaController::class, 'savedJobs'])->name('jobs.saved');
        Route::get('/jobs/applications', [AlphaController::class, 'myJobApplications'])->name('jobs.applications');
        Route::post('/jobs/applications/{appId}/withdraw', [AlphaController::class, 'withdrawJobApplication'])->whereNumber('appId')->middleware('throttle:15,1')->name('jobs.applications.withdraw');
        Route::get('/jobs/mine', [AlphaController::class, 'myJobPostings'])->name('jobs.mine');
        Route::get('/jobs/create', [AlphaController::class, 'createJobForm'])->name('jobs.create');
        Route::post('/jobs', [AlphaController::class, 'storeJob'])->middleware('throttle:10,1')->name('jobs.store');
        Route::get('/jobs/alerts', [AlphaController::class, 'jobAlerts'])->name('jobs.alerts');
        Route::post('/jobs/alerts', [AlphaController::class, 'subscribeJobAlert'])->middleware('throttle:15,1')->name('jobs.alerts.subscribe');
        Route::post('/jobs/alerts/{alertId}/pause', [AlphaController::class, 'pauseJobAlert'])->whereNumber('alertId')->middleware('throttle:30,1')->name('jobs.alerts.pause');
        Route::post('/jobs/alerts/{alertId}/resume', [AlphaController::class, 'resumeJobAlert'])->whereNumber('alertId')->middleware('throttle:30,1')->name('jobs.alerts.resume');
        Route::post('/jobs/alerts/{alertId}/delete', [AlphaController::class, 'deleteJobAlert'])->whereNumber('alertId')->middleware('throttle:30,1')->name('jobs.alerts.delete');
        Route::get('/jobs/{id}', [AlphaController::class, 'job'])->whereNumber('id')->name('jobs.show');
        Route::get('/jobs/{id}/edit', [AlphaController::class, 'editJobForm'])->whereNumber('id')->name('jobs.edit');
        Route::post('/jobs/{id}/update', [AlphaController::class, 'updateJob'])->whereNumber('id')->middleware('throttle:20,1')->name('jobs.update');
        Route::post('/jobs/{id}/delete', [AlphaController::class, 'deleteJob'])->whereNumber('id')->middleware('throttle:10,1')->name('jobs.delete');
        Route::post('/jobs/{id}/renew', [AlphaController::class, 'renewJobPosting'])->whereNumber('id')->middleware('throttle:10,1')->name('jobs.renew');
        Route::get('/jobs/{id}/applications/export.csv', [AlphaController::class, 'exportJobApplications'])->whereNumber('id')->name('jobs.applicants.export');
        Route::get('/jobs/{id}/applications', [AlphaController::class, 'jobApplicants'])->whereNumber('id')->name('jobs.applicants');
        Route::post('/jobs/{id}/applications/{appId}/status', [AlphaController::class, 'setApplicationStatus'])->whereNumber('id')->whereNumber('appId')->middleware('throttle:30,1')->name('jobs.applicants.status');
        Route::post('/jobs/{id}/apply', [AlphaController::class, 'applyJob'])->whereNumber('id')->middleware('throttle:10,1')->name('jobs.apply');
        Route::post('/jobs/{id}/save', [AlphaController::class, 'saveJobBookmark'])->whereNumber('id')->middleware('throttle:30,1')->name('jobs.save');
        Route::post('/jobs/{id}/unsave', [AlphaController::class, 'unsaveJobBookmark'])->whereNumber('id')->middleware('throttle:30,1')->name('jobs.unsave');
        Route::get('/ideation', [AlphaController::class, 'ideation'])->name('ideation.index');
        Route::get('/ideation/{id}', [AlphaController::class, 'ideationChallenge'])->whereNumber('id')->name('ideation.show');
        Route::post('/ideation/{id}/ideas', [AlphaController::class, 'submitIdea'])->whereNumber('id')->middleware('throttle:10,1')->name('ideation.ideas.store');
        Route::post('/ideation/{id}/ideas/{ideaId}/vote', [AlphaController::class, 'voteIdea'])->whereNumber('id')->whereNumber('ideaId')->middleware('throttle:30,1')->name('ideation.ideas.vote');
        // Wave 4 — commerce & discovery modules (each gated by its feature flag).
        Route::get('/marketplace', [AlphaController::class, 'marketplace'])->name('marketplace.index');
        Route::get('/marketplace/{id}', [AlphaController::class, 'marketplaceItem'])->whereNumber('id')->name('marketplace.show');
        Route::get('/courses', [AlphaController::class, 'courses'])->name('courses.index');
        Route::get('/courses/{id}', [AlphaController::class, 'course'])->whereNumber('id')->name('courses.show');
        Route::post('/courses/{id}/enrol', [AlphaController::class, 'enrolCourse'])->whereNumber('id')->middleware('throttle:10,1')->name('courses.enrol');
        Route::get('/podcasts', [AlphaController::class, 'podcasts'])->name('podcasts.index');
        Route::get('/podcasts/{id}', [AlphaController::class, 'podcast'])->whereNumber('id')->name('podcasts.show');
        Route::get('/coupons', [AlphaController::class, 'coupons'])->name('coupons.index');
        Route::get('/premium', [AlphaController::class, 'premium'])->name('premium.index');
        Route::post('/premium/subscribe', [AlphaController::class, 'subscribePremium'])->middleware('throttle:10,1')->name('premium.subscribe');
        Route::get('/clubs', [AlphaController::class, 'clubs'])->name('clubs.index');
        Route::get('/federation', [AlphaController::class, 'federation'])->name('federation.index');
        // Federation core (hub above is list+stats). Static segments are declared
        // before the partner wildcard so they match first.
        Route::get('/federation/opt-in', [AlphaController::class, 'federationOptIn'])->name('federation.opt-in');
        Route::post('/federation/opt-in', [AlphaController::class, 'storeFederationOptIn'])->middleware('throttle:10,1')->name('federation.opt-in.store');
        Route::get('/federation/opt-out', [AlphaController::class, 'federationOptOut'])->name('federation.opt-out');
        Route::post('/federation/opt-out', [AlphaController::class, 'storeFederationOptOut'])->middleware('throttle:10,1')->name('federation.opt-out.store');
        Route::get('/federation/settings', [AlphaController::class, 'federationSettings'])->name('federation.settings');
        Route::post('/federation/settings', [AlphaController::class, 'updateFederationSettings'])->middleware('throttle:20,1')->name('federation.settings.update');
        Route::get('/federation/members', [AlphaController::class, 'federationMembers'])->name('federation.members.index');
        Route::get('/federation/members/{id}', [AlphaController::class, 'federationMember'])->whereNumber('id')->name('federation.members.show');
        Route::get('/federation/listings', [AlphaController::class, 'federationListings'])->name('federation.listings.index');
        Route::get('/federation/events', [AlphaController::class, 'federationEvents'])->name('federation.events.index');
        Route::get('/federation/partners/{id}', [AlphaController::class, 'federationPartner'])->where('id', '[0-9]+|ext-[0-9]+')->name('federation.partners.show');
        // ===== WAVE FED2 — federated connections, messaging, hour transfer =====
        // Credit-moving POSTs are throttled (the transfer most tightly). Static
        // segments are declared so they never get captured as a {id}.
        Route::get('/federation/connections', [AlphaController::class, 'federationConnections'])->name('federation.connections.index');
        Route::post('/federation/connections', [AlphaController::class, 'storeFederationConnection'])->middleware('throttle:15,1')->name('federation.connections.store');
        Route::post('/federation/connections/{id}/accept', [AlphaController::class, 'acceptFederationConnection'])->whereNumber('id')->middleware('throttle:20,1')->name('federation.connections.accept');
        Route::post('/federation/connections/{id}/reject', [AlphaController::class, 'rejectFederationConnection'])->whereNumber('id')->middleware('throttle:20,1')->name('federation.connections.reject');
        Route::post('/federation/connections/{id}/remove', [AlphaController::class, 'removeFederationConnection'])->whereNumber('id')->middleware('throttle:20,1')->name('federation.connections.remove');
        Route::get('/federation/messages', [AlphaController::class, 'federationMessages'])->name('federation.messages.index');
        Route::post('/federation/messages', [AlphaController::class, 'storeFederationMessage'])->middleware('throttle:15,1')->name('federation.messages.store');
        Route::get('/federation/members/{id}/transfer', [AlphaController::class, 'federationTransfer'])->whereNumber('id')->name('federation.transfer');
        Route::post('/federation/members/{id}/transfer', [AlphaController::class, 'storeFederationTransfer'])->whereNumber('id')->middleware('throttle:10,1')->name('federation.transfer.store');
        Route::get('/profile', [AlphaController::class, 'myProfile'])->name('profile.me');
        Route::get('/profile/settings', [AlphaController::class, 'profileSettings'])->name('profile.settings');
        Route::post('/profile/settings', [AlphaController::class, 'updateProfileSettings'])->middleware('throttle:20,1')->name('profile.settings.update');
        // ===== WAVE T1-SAFETY: authenticator-app 2FA enrolment + blocked-users page =====
        Route::get('/profile/two-factor', [AlphaController::class, 'twoFactorSetup'])->name('profile.2fa');
        Route::post('/profile/two-factor/verify', [AlphaController::class, 'verifyTwoFactorSetup'])->middleware('throttle:10,1')->name('profile.2fa.verify');
        Route::post('/profile/two-factor/disable', [AlphaController::class, 'disableTwoFactor'])->middleware('throttle:5,1')->name('profile.2fa.disable');
        Route::get('/profile/blocked', [AlphaController::class, 'blockedUsers'])->name('profile.blocked');
        Route::post('/profile/email', [AlphaController::class, 'updateProfileEmail'])->middleware('throttle:10,1')->name('profile.email.update');
        Route::post('/profile/password', [AlphaController::class, 'updateProfilePassword'])->middleware('throttle:10,1')->name('profile.password.update');
        Route::post('/profile/language', [AlphaController::class, 'updateProfileLanguage'])->middleware('throttle:20,1')->name('profile.language.update');
        Route::post('/profile/notifications', [AlphaController::class, 'updateProfileNotifications'])->middleware('throttle:20,1')->name('profile.notifications.update');
        Route::post('/profile/passkeys/rename', [AlphaController::class, 'renameProfilePasskey'])->middleware('throttle:20,1')->name('profile.passkeys.rename');
        Route::post('/profile/passkeys/remove', [AlphaController::class, 'removeProfilePasskey'])->middleware('throttle:20,1')->name('profile.passkeys.remove');
        Route::post('/profile/personalisation', [AlphaController::class, 'updateProfilePersonalisation'])->middleware('throttle:20,1')->name('profile.personalisation.update');
        Route::post('/profile/match-preferences', [AlphaController::class, 'updateProfileMatchPreferences'])->middleware('throttle:20,1')->name('profile.match-preferences.update');
        Route::post('/profile/skills/add', [AlphaController::class, 'addProfileSkill'])->middleware('throttle:30,1')->name('profile.skills.add');
        Route::post('/profile/skills/remove', [AlphaController::class, 'removeProfileSkill'])->middleware('throttle:30,1')->name('profile.skills.remove');
        Route::post('/profile/safeguarding/revoke', [AlphaController::class, 'revokeProfileSafeguarding'])->middleware('throttle:20,1')->name('profile.safeguarding.revoke');
        Route::post('/profile/data-export', [AlphaController::class, 'requestDataExport'])->middleware('throttle:3,60')->name('profile.data-export');
        Route::get('/profile/delete-account', [AlphaController::class, 'confirmDeleteAccount'])->name('profile.delete');
        Route::post('/profile/delete-account', [AlphaController::class, 'deleteAccount'])->middleware('throttle:5,60')->name('profile.delete.store');

        // ===== WAVE O: Organisations depth =====
        // The organisation detail page now surfaces volunteering opportunities,
        // member reviews and aggregate stats. These read sections are served by
        // the existing `organisations.show` route (AlphaController::organisation),
        // and the "apply" action reuses the existing volunteering apply path
        // (`volunteering.show` -> `volunteering.apply.store`) so the volunteer
        // application + organiser notification logic is not duplicated. No new
        // routes are required for WAVE O.

        // ===== WAVE V2: Volunteering depth =====
        Route::get('/volunteering/certificates', [AlphaController::class, 'volunteeringCertificates'])->name('volunteering.certificates');
        Route::post('/volunteering/certificates/generate', [AlphaController::class, 'generateVolunteerCertificate'])->middleware('throttle:10,1')->name('volunteering.certificates.generate');
        Route::get('/volunteering/certificates/{code}/download', [AlphaController::class, 'downloadVolunteerCertificate'])->where('code', '[A-Za-z0-9]+')->name('volunteering.certificates.download');
        Route::get('/volunteering/waitlist', [AlphaController::class, 'volunteeringWaitlist'])->name('volunteering.waitlist');
        Route::post('/volunteering/waitlist/{shiftId}/leave', [AlphaController::class, 'leaveVolunteerWaitlist'])->whereNumber('shiftId')->middleware('throttle:20,1')->name('volunteering.waitlist.leave');
        Route::get('/volunteering/swaps', [AlphaController::class, 'volunteeringSwaps'])->name('volunteering.swaps');
        Route::post('/volunteering/swaps', [AlphaController::class, 'requestVolunteerSwap'])->middleware('throttle:10,1')->name('volunteering.swaps.request');
        Route::post('/volunteering/swaps/{id}/respond', [AlphaController::class, 'respondVolunteerSwap'])->whereNumber('id')->middleware('throttle:20,1')->name('volunteering.swaps.respond');
        Route::post('/volunteering/swaps/{id}/cancel', [AlphaController::class, 'cancelVolunteerSwap'])->whereNumber('id')->middleware('throttle:20,1')->name('volunteering.swaps.cancel');

        // ===== WAVE POLISH-COMMERCE =====
        // Static segments are declared before any {id} wildcards so they always win.
        Route::get('/premium/return', [AlphaController::class, 'premiumReturn'])->name('premium.return');
        Route::post('/podcasts/{id}/subscribe', [AlphaController::class, 'podcastSubscribe'])->whereNumber('id')->middleware('throttle:30,1')->name('podcasts.subscribe');
        Route::get('/podcasts/{showId}/episodes/{id}', [AlphaController::class, 'podcastEpisode'])->whereNumber('showId')->whereNumber('id')->name('podcasts.episode');
        Route::get('/coupons/{id}', [AlphaController::class, 'couponShow'])->whereNumber('id')->name('coupons.show');

        // ===== WAVE NIGHT-MEMBERS: profile review + transfer, review delete =====
        // Static `/reviews/delete` segment before member wildcard `/members/{id}` is safe
        // because there is no `reviews` prefix group conflict.
        Route::post('/members/{id}/review', [AlphaController::class, 'storeProfileReview'])->whereNumber('id')->middleware('throttle:10,1')->name('profile.review.store');
        Route::post('/members/{id}/transfer', [AlphaController::class, 'profileTransferCredits'])->whereNumber('id')->middleware('throttle:20,1')->name('profile.transfer');
        Route::post('/reviews/{id}/delete', [AlphaController::class, 'deleteReview'])->whereNumber('id')->middleware('throttle:20,1')->name('reviews.delete');
    });
