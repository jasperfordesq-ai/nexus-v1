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
        Route::get('/blog/{slug}', [AlphaController::class, 'blogPost'])->where('slug', '[a-zA-Z0-9_-]+')->name('blog.show');
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

        Route::get('/dashboard', [AlphaController::class, 'dashboard'])->name('dashboard');
        Route::get('/events', [AlphaController::class, 'events'])->name('events.index');
        Route::get('/events/new', [AlphaController::class, 'createEvent'])->name('events.create');
        Route::post('/events/new', [AlphaController::class, 'storeEvent'])->middleware('throttle:10,1')->name('events.store');
        Route::get('/events/{id}/edit', [AlphaController::class, 'editEvent'])->whereNumber('id')->name('events.edit');
        Route::post('/events/{id}/edit', [AlphaController::class, 'updateEvent'])->whereNumber('id')->middleware('throttle:10,1')->name('events.update');
        Route::post('/events/{id}/cancel', [AlphaController::class, 'cancelEvent'])->whereNumber('id')->middleware('throttle:10,1')->name('events.cancel');
        Route::post('/events/{id}/delete', [AlphaController::class, 'deleteEvent'])->whereNumber('id')->middleware('throttle:10,1')->name('events.delete');
        Route::get('/events/{id}', [AlphaController::class, 'event'])->whereNumber('id')->name('events.show');
        Route::post('/events/{id}/rsvp', [AlphaController::class, 'storeEventRsvp'])->whereNumber('id')->name('events.rsvp.store');
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
        Route::get('/listings', [AlphaController::class, 'listings'])->name('listings.index');
        Route::get('/listings/new', [AlphaController::class, 'createListing'])->name('listings.create');
        Route::post('/listings/new', [AlphaController::class, 'storeListing'])->middleware('throttle:10,1')->name('listings.store');
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
        Route::post('/polls/{pollId}/vote', [AlphaController::class, 'storePollVote'])->whereNumber('pollId')->middleware('throttle:30,1')->name('polls.vote');
        Route::get('/wallet', [AlphaController::class, 'wallet'])->name('wallet.index');
        Route::post('/wallet/transfer', [AlphaController::class, 'transferCredits'])->middleware('throttle:15,1')->name('wallet.transfer');
        Route::get('/wallet/recipients', [AlphaController::class, 'walletRecipients'])->middleware('throttle:60,1')->name('wallet.recipients');
        Route::get('/messages', [AlphaController::class, 'messages'])->name('messages.index');
        Route::get('/messages/new/{userId}', [AlphaController::class, 'conversation'])->whereNumber('userId')->name('messages.new');
        Route::get('/messages/{userId}', [AlphaController::class, 'conversation'])->whereNumber('userId')->name('messages.show');
        Route::post('/messages/{userId}', [AlphaController::class, 'storeMessage'])->middleware('throttle:30,1')->whereNumber('userId')->name('messages.store');
        Route::post('/messages/{userId}/archive', [AlphaController::class, 'archiveConversation'])->middleware('throttle:20,1')->whereNumber('userId')->name('messages.archive');
        Route::post('/messages/{userId}/restore', [AlphaController::class, 'restoreConversation'])->middleware('throttle:20,1')->whereNumber('userId')->name('messages.restore');
        Route::get('/members', [AlphaController::class, 'members'])->name('members.index');
        Route::get('/members/{id}', [AlphaController::class, 'memberProfile'])->whereNumber('id')->name('members.show');
        Route::post('/members/{id}/connection', [AlphaController::class, 'updateMemberConnection'])->middleware('throttle:20,1')->whereNumber('id')->name('members.connection');
        Route::post('/members/{id}/endorse', [AlphaController::class, 'endorseMemberSkill'])->middleware('throttle:30,1')->whereNumber('id')->name('members.endorse');
        Route::get('/connections', [AlphaController::class, 'connections'])->name('connections.index');
        Route::post('/connections/{id}/accept', [AlphaController::class, 'acceptConnection'])->middleware('throttle:30,1')->whereNumber('id')->name('connections.accept');
        Route::post('/connections/{id}/decline', [AlphaController::class, 'declineConnection'])->middleware('throttle:30,1')->whereNumber('id')->name('connections.decline');
        Route::post('/connections/{id}/remove', [AlphaController::class, 'cancelConnection'])->middleware('throttle:30,1')->whereNumber('id')->name('connections.remove');
        Route::get('/account', [AlphaController::class, 'account'])->name('account');
        Route::get('/achievements', [AlphaController::class, 'achievements'])->name('achievements');
        Route::get('/leaderboard', [AlphaController::class, 'leaderboard'])->name('leaderboard');
        Route::get('/nexus-score', [AlphaController::class, 'nexusScore'])->name('nexus-score');
        Route::get('/notifications', [AlphaController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/read-all', [AlphaController::class, 'markAllNotificationsRead'])->middleware('throttle:20,1')->name('notifications.read-all');
        Route::post('/notifications/{id}/delete', [AlphaController::class, 'deleteNotification'])->whereNumber('id')->middleware('throttle:60,1')->name('notifications.delete');
        Route::get('/activity', [AlphaController::class, 'activity'])->name('activity');
        Route::get('/reviews', [AlphaController::class, 'reviews'])->name('reviews.index');
        Route::get('/profile', [AlphaController::class, 'myProfile'])->name('profile.me');
        Route::get('/profile/settings', [AlphaController::class, 'profileSettings'])->name('profile.settings');
        Route::post('/profile/settings', [AlphaController::class, 'updateProfileSettings'])->middleware('throttle:20,1')->name('profile.settings.update');
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
    });
