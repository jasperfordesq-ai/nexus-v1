<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use App\Http\Controllers\GovukAlpha\AlphaController;
use App\Http\Controllers\GovukAlpha\Middleware\RequireAccessibleAuthentication;
use Illuminate\Support\Facades\Route;

Route::middleware(RequireAccessibleAuthentication::class)->group(function (): void {
    Route::get('/events/{id}/registration', [AlphaController::class, 'eventsRegistrationProduct'])
        ->whereNumber('id')->name('events.registration.index');
    Route::post('/events/{id}/registration/settings', [AlphaController::class, 'eventsRegistrationSaveSettings'])
        ->whereNumber('id')->middleware('throttle:nexus-route-20-per-1m')->name('events.registration.settings.save');
    Route::post('/events/{id}/registration/settings/publish', [AlphaController::class, 'eventsRegistrationPublishSettings'])
        ->whereNumber('id')->middleware('throttle:nexus-route-10-per-1m')->name('events.registration.settings.publish');

    Route::get('/events/{id}/registration/forms/new', [AlphaController::class, 'eventsRegistrationFormEditor'])
        ->whereNumber('id')->name('events.registration.forms.new');
    Route::post('/events/{id}/registration/forms/new', [AlphaController::class, 'eventsRegistrationSaveForm'])
        ->whereNumber('id')->middleware('throttle:nexus-route-20-per-1m')->name('events.registration.forms.create');
    Route::get('/events/{id}/registration/forms/{formId}', [AlphaController::class, 'eventsRegistrationFormEditor'])
        ->whereNumber('id')->whereNumber('formId')->name('events.registration.forms.edit');
    Route::post('/events/{id}/registration/forms/{formId}', [AlphaController::class, 'eventsRegistrationSaveForm'])
        ->whereNumber('id')->whereNumber('formId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.registration.forms.update');
    Route::post('/events/{id}/registration/forms/{formId}/publish', [AlphaController::class, 'eventsRegistrationPublishForm'])
        ->whereNumber('id')->whereNumber('formId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.registration.forms.publish');
    Route::post('/events/{id}/registration/forms/{formId}/fork', [AlphaController::class, 'eventsRegistrationForkForm'])
        ->whereNumber('id')->whereNumber('formId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.registration.forms.fork');

    Route::post('/events/{id}/registration/registrations/{registrationId}/forms/{formId}/submit', [AlphaController::class, 'eventsRegistrationSubmitAnswers'])
        ->whereNumber('id')->whereNumber('registrationId')->whereNumber('formId')
        ->middleware('throttle:nexus-route-20-per-1m')->name('events.registration.answers.submit');
    Route::post('/events/{id}/registration/submissions/{submissionId}/review', [AlphaController::class, 'eventsRegistrationReviewAnswers'])
        ->whereNumber('id')->whereNumber('submissionId')->middleware('throttle:nexus-route-30-per-1m')
        ->name('events.registration.answers.review');
    Route::post('/events/{id}/registration/submissions/export', [AlphaController::class, 'eventsRegistrationExportAnswers'])
        ->whereNumber('id')->middleware('throttle:nexus-route-10-per-1m')->name('events.registration.answers.export');

    Route::post('/events/{id}/registration/campaigns/preview', [AlphaController::class, 'eventsRegistrationPreviewCampaign'])
        ->whereNumber('id')->middleware('throttle:nexus-route-20-per-1m')->name('events.registration.campaigns.preview');
    Route::post('/events/{id}/registration/campaigns/{campaignId}/issue', [AlphaController::class, 'eventsRegistrationIssueCampaign'])
        ->whereNumber('id')->whereNumber('campaignId')->middleware('throttle:nexus-route-10-per-1m')
        ->name('events.registration.campaigns.issue');
    Route::post('/events/{id}/registration/campaigns/{campaignId}/schedule', [AlphaController::class, 'eventsRegistrationScheduleCampaign'])
        ->whereNumber('id')->whereNumber('campaignId')->middleware('throttle:nexus-route-10-per-1m')
        ->name('events.registration.campaigns.schedule');
    Route::post('/events/{id}/registration/campaigns/{campaignId}/cancel', [AlphaController::class, 'eventsRegistrationCancelCampaign'])
        ->whereNumber('id')->whereNumber('campaignId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.registration.campaigns.cancel');
    Route::post('/events/{id}/registration/invitations/{invitationId}/accept', [AlphaController::class, 'eventsRegistrationAcceptInvitation'])
        ->whereNumber('id')->whereNumber('invitationId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.registration.invitations.accept');

    Route::post('/events/{id}/registration/registrations/{registrationId}/guests', [AlphaController::class, 'eventsRegistrationCaptureGuest'])
        ->whereNumber('id')->whereNumber('registrationId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.registration.guests.capture');
    Route::post('/events/{id}/registration/guests/{guestId}/cancel', [AlphaController::class, 'eventsRegistrationCancelGuest'])
        ->whereNumber('id')->whereNumber('guestId')->middleware('throttle:nexus-route-20-per-1m')
        ->name('events.registration.guests.cancel');
    Route::post('/events/{id}/registration/guests/{guestId}/attendance/{action}', [AlphaController::class, 'eventsRegistrationGuestAttendance'])
        ->whereNumber('id')->whereNumber('guestId')->whereIn('action', ['check_in', 'check_out', 'no_show', 'undo'])
        ->middleware('throttle:nexus-route-30-per-1m')->name('events.registration.guests.attendance');

    Route::post('/events/{id}/registration/retention/preview', [AlphaController::class, 'eventsRegistrationRetentionPreview'])
        ->whereNumber('id')->middleware('throttle:nexus-route-10-per-1m')->name('events.registration.retention.preview');
    Route::post('/events/{id}/registration/retention/{runId}/apply', [AlphaController::class, 'eventsRegistrationRetentionApply'])
        ->whereNumber('id')->whereNumber('runId')->middleware('throttle:nexus-route-5-per-1m')
        ->name('events.registration.retention.apply');
});
