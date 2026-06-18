<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use App\Http\Controllers\GovukAlpha\AlphaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accessible-frontend parity — commerce module
|--------------------------------------------------------------------------
| Marketplace seller/buyer flows, courses learning, premium subscription
| management. Required INSIDE the {tenantSlug}/alpha group (prefix
| '{tenantSlug}/alpha', name prefix 'govuk-alpha.'). Static segments are
| declared BEFORE any wildcard {id} so they always match first. Numeric ids
| are constrained with whereNumber so non-numeric static paths never collide
| with the existing /marketplace/{id}, /courses/{id} and /premium routes
| declared in routes/govuk-alpha.php.
*/

// ===== Marketplace — seller listing management (static before wildcards) =====
Route::get('/marketplace/create', [AlphaController::class, 'commerceCreateListingForm'])->name('marketplace.create');
Route::post('/marketplace/create', [AlphaController::class, 'commerceStoreListing'])->middleware('throttle:10,1')->name('marketplace.store');
Route::get('/marketplace/mine', [AlphaController::class, 'commerceMyListings'])->name('marketplace.mine');
Route::get('/marketplace/saved', [AlphaController::class, 'commerceSavedListings'])->name('marketplace.saved');
Route::get('/marketplace/free', [AlphaController::class, 'commerceFreeItems'])->name('marketplace.free');

// ===== Marketplace — offers dashboard =====
Route::get('/marketplace/offers', [AlphaController::class, 'commerceMyOffers'])->name('marketplace.offers');
Route::post('/marketplace/offers/{id}/accept', [AlphaController::class, 'commerceAcceptOffer'])->whereNumber('id')->middleware('throttle:20,1')->name('marketplace.offers.accept');
Route::post('/marketplace/offers/{id}/decline', [AlphaController::class, 'commerceDeclineOffer'])->whereNumber('id')->middleware('throttle:20,1')->name('marketplace.offers.decline');
Route::post('/marketplace/offers/{id}/withdraw', [AlphaController::class, 'commerceWithdrawOffer'])->whereNumber('id')->middleware('throttle:20,1')->name('marketplace.offers.withdraw');

// ===== Marketplace — orders dashboards =====
Route::get('/marketplace/orders', [AlphaController::class, 'commerceBuyerOrders'])->name('marketplace.orders.buyer');
Route::get('/marketplace/sales', [AlphaController::class, 'commerceSellerOrders'])->name('marketplace.orders.seller');
Route::post('/marketplace/orders/{id}/ship', [AlphaController::class, 'commerceShipOrder'])->whereNumber('id')->middleware('throttle:20,1')->name('marketplace.orders.ship');
Route::post('/marketplace/orders/{id}/confirm', [AlphaController::class, 'commerceConfirmOrder'])->whereNumber('id')->middleware('throttle:20,1')->name('marketplace.orders.confirm');
Route::post('/marketplace/orders/{id}/cancel', [AlphaController::class, 'commerceCancelOrder'])->whereNumber('id')->middleware('throttle:20,1')->name('marketplace.orders.cancel');
Route::post('/marketplace/orders/{id}/rate', [AlphaController::class, 'commerceRateOrder'])->whereNumber('id')->middleware('throttle:10,1')->name('marketplace.orders.rate');

// ===== Marketplace — seller public profile =====
Route::get('/marketplace/seller/{sellerId}', [AlphaController::class, 'commerceSellerProfile'])->whereNumber('sellerId')->name('marketplace.seller');

// ===== Marketplace — per-listing buyer actions (numeric id sub-paths) =====
Route::get('/marketplace/{id}/edit', [AlphaController::class, 'commerceEditListingForm'])->whereNumber('id')->name('marketplace.edit');
Route::post('/marketplace/{id}/update', [AlphaController::class, 'commerceUpdateListing'])->whereNumber('id')->middleware('throttle:20,1')->name('marketplace.update');
Route::post('/marketplace/{id}/delete', [AlphaController::class, 'commerceDeleteListing'])->whereNumber('id')->middleware('throttle:10,1')->name('marketplace.delete');
Route::post('/marketplace/{id}/renew', [AlphaController::class, 'commerceRenewListing'])->whereNumber('id')->middleware('throttle:10,1')->name('marketplace.renew');
Route::post('/marketplace/{id}/save', [AlphaController::class, 'commerceSaveListing'])->whereNumber('id')->middleware('throttle:30,1')->name('marketplace.save');
Route::post('/marketplace/{id}/unsave', [AlphaController::class, 'commerceUnsaveListing'])->whereNumber('id')->middleware('throttle:30,1')->name('marketplace.unsave');
Route::get('/marketplace/{id}/buy', [AlphaController::class, 'commerceBuyForm'])->whereNumber('id')->name('marketplace.buy');
Route::post('/marketplace/{id}/buy', [AlphaController::class, 'commerceStoreBuy'])->whereNumber('id')->middleware('throttle:10,1')->name('marketplace.buy.store');
Route::get('/marketplace/{id}/offer', [AlphaController::class, 'commerceOfferForm'])->whereNumber('id')->name('marketplace.offer');
Route::post('/marketplace/{id}/offer', [AlphaController::class, 'commerceStoreOffer'])->whereNumber('id')->middleware('throttle:10,1')->name('marketplace.offer.store');
Route::get('/marketplace/{id}/report', [AlphaController::class, 'commerceReportForm'])->whereNumber('id')->name('marketplace.report');
Route::post('/marketplace/{id}/report', [AlphaController::class, 'commerceStoreReport'])->whereNumber('id')->middleware('throttle:5,60')->name('marketplace.report.store');

// ===== Courses — learner dashboard + lesson player =====
Route::get('/courses/mine', [AlphaController::class, 'commerceMyLearning'])->name('courses.mine');
Route::get('/courses/{id}/learn', [AlphaController::class, 'commerceCourseLearn'])->whereNumber('id')->name('courses.learn');
Route::post('/courses/{id}/lessons/{lessonId}/complete', [AlphaController::class, 'commerceCompleteLesson'])->whereNumber('id')->whereNumber('lessonId')->middleware('throttle:30,1')->name('courses.lessons.complete');

// ===== Premium — subscription management =====
Route::get('/premium/manage', [AlphaController::class, 'commercePremiumManage'])->name('premium.manage');
Route::post('/premium/cancel', [AlphaController::class, 'commercePremiumCancel'])->middleware('throttle:5,1')->name('premium.cancel');
Route::post('/premium/portal', [AlphaController::class, 'commercePremiumPortal'])->middleware('throttle:10,1')->name('premium.portal');

// ===== Courses — instructor / creator suite =====
// `instructor` and `new` are non-numeric, so they never collide with the
// existing /courses/{id} (whereNumber) route in routes/govuk-alpha.php. Static
// segments are declared before the numeric {id} sub-paths within this block.
Route::get('/courses/instructor', [AlphaController::class, 'commerceInstructorCourses'])->name('courses.instructor');
Route::get('/courses/instructor/new', [AlphaController::class, 'commerceCreateCourseForm'])->name('courses.instructor.create');
Route::post('/courses/instructor/new', [AlphaController::class, 'commerceStoreCourse'])->middleware('throttle:10,1')->name('courses.instructor.store');
Route::get('/courses/instructor/{id}/edit', [AlphaController::class, 'commerceEditCourseForm'])->whereNumber('id')->name('courses.instructor.edit');
Route::post('/courses/instructor/{id}/update', [AlphaController::class, 'commerceUpdateCourse'])->whereNumber('id')->middleware('throttle:20,1')->name('courses.instructor.update');
Route::post('/courses/instructor/{id}/publish', [AlphaController::class, 'commercePublishCourse'])->whereNumber('id')->middleware('throttle:10,1')->name('courses.instructor.publish');
Route::post('/courses/instructor/{id}/unpublish', [AlphaController::class, 'commerceUnpublishCourse'])->whereNumber('id')->middleware('throttle:10,1')->name('courses.instructor.unpublish');
Route::post('/courses/instructor/{id}/delete', [AlphaController::class, 'commerceDeleteCourse'])->whereNumber('id')->middleware('throttle:10,1')->name('courses.instructor.delete');
Route::get('/courses/instructor/{id}/analytics', [AlphaController::class, 'commerceCourseAnalytics'])->whereNumber('id')->name('courses.instructor.analytics');
