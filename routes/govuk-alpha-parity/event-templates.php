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
    Route::get('/events/templates', [AlphaController::class, 'eventsTemplates'])
        ->name('events.templates.index');
    Route::get('/event-templates/{templateId}/history', [AlphaController::class, 'eventsTemplateHistory'])
        ->whereNumber('templateId')->name('events.templates.history');
    Route::get('/events/{id}/template-preview', [AlphaController::class, 'eventsTemplateCapturePreview'])
        ->whereNumber('id')->name('events.templates.capture.preview');
    Route::post('/events/{id}/templates', [AlphaController::class, 'eventsTemplateCapture'])
        ->whereNumber('id')->middleware('throttle:20,1')->name('events.templates.capture');
    Route::get('/event-templates/{templateId}/materialize', [AlphaController::class, 'eventsTemplateMaterializeForm'])
        ->whereNumber('templateId')->name('events.templates.materialize.form');
    Route::post('/event-templates/{templateId}/materialize/preview', [AlphaController::class, 'eventsTemplateMaterializePreview'])
        ->whereNumber('templateId')->middleware('throttle:60,1')->name('events.templates.materialize.preview');
    Route::post('/event-templates/{templateId}/materialize', [AlphaController::class, 'eventsTemplateMaterialize'])
        ->whereNumber('templateId')->middleware('throttle:20,1')->name('events.templates.materialize');
});
