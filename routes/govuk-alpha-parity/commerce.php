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

// ===== Courses — instructor section + lesson builder (no-JS CRUD) =====
// All sub-paths of /courses/instructor/{id} so they never collide with the
// public /courses/{id} route. The course id is numeric; section/lesson ids too.
Route::post('/courses/instructor/{id}/sections', [AlphaController::class, 'commerceStoreCourseSection'])->whereNumber('id')->middleware('throttle:30,1')->name('courses.instructor.sections.store');
Route::post('/courses/instructor/{id}/sections/{sectionId}/update', [AlphaController::class, 'commerceUpdateCourseSection'])->whereNumber('id')->whereNumber('sectionId')->middleware('throttle:30,1')->name('courses.instructor.sections.update');
Route::post('/courses/instructor/{id}/sections/{sectionId}/delete', [AlphaController::class, 'commerceDeleteCourseSection'])->whereNumber('id')->whereNumber('sectionId')->middleware('throttle:30,1')->name('courses.instructor.sections.delete');
Route::post('/courses/instructor/{id}/lessons', [AlphaController::class, 'commerceStoreCourseLesson'])->whereNumber('id')->middleware('throttle:30,1')->name('courses.instructor.lessons.store');
Route::post('/courses/instructor/{id}/lessons/{lessonId}/update', [AlphaController::class, 'commerceUpdateCourseLesson'])->whereNumber('id')->whereNumber('lessonId')->middleware('throttle:30,1')->name('courses.instructor.lessons.update');
Route::post('/courses/instructor/{id}/lessons/{lessonId}/delete', [AlphaController::class, 'commerceDeleteCourseLesson'])->whereNumber('id')->whereNumber('lessonId')->middleware('throttle:30,1')->name('courses.instructor.lessons.delete');

// ===== Marketplace — category browse + buyer pickups + merchant onboarding =====
// Static segments — declared here so they win over the numeric {id} wildcard
// (which is whereNumber-constrained, so 'category'/'onboarding'/'pickups' could
// never match it anyway, but we keep them explicit for clarity).
Route::get('/marketplace/pickups', [AlphaController::class, 'commerceMyPickups'])->name('marketplace.pickups');
Route::get('/marketplace/onboarding', [AlphaController::class, 'commerceMerchantOnboarding'])->name('marketplace.onboarding');
Route::post('/marketplace/onboarding', [AlphaController::class, 'commerceStoreMerchantOnboarding'])->middleware('throttle:10,1')->name('marketplace.onboarding.store');
Route::get('/marketplace/category/{slug}', [AlphaController::class, 'commerceCategoryListings'])->where('slug', '[A-Za-z0-9_-]+')->name('marketplace.category');

// ===== Seller — merchant coupon management (create / edit / delete) =====
Route::get('/marketplace/coupons', [AlphaController::class, 'commerceSellerCoupons'])->name('marketplace.coupons');
Route::get('/marketplace/coupons/new', [AlphaController::class, 'commerceCreateCouponForm'])->name('marketplace.coupons.create');
Route::post('/marketplace/coupons/new', [AlphaController::class, 'commerceStoreCoupon'])->middleware('throttle:20,1')->name('marketplace.coupons.store');
Route::get('/marketplace/coupons/{id}/edit', [AlphaController::class, 'commerceEditCouponForm'])->whereNumber('id')->name('marketplace.coupons.edit');
Route::post('/marketplace/coupons/{id}/update', [AlphaController::class, 'commerceUpdateCoupon'])->whereNumber('id')->middleware('throttle:20,1')->name('marketplace.coupons.update');
Route::post('/marketplace/coupons/{id}/delete', [AlphaController::class, 'commerceDeleteCoupon'])->whereNumber('id')->middleware('throttle:10,1')->name('marketplace.coupons.delete');

// ===== Podcasts — studio: show create/edit + episode management =====
// `studio` is non-numeric so it never collides with the public /podcasts/{id}
// (whereNumber) route in routes/govuk-alpha.php. Static before numeric sub-paths.
Route::get('/podcasts/studio', [AlphaController::class, 'commercePodcastStudio'])->name('podcasts.studio');
Route::get('/podcasts/studio/new', [AlphaController::class, 'commerceCreatePodcastForm'])->name('podcasts.studio.create');
Route::post('/podcasts/studio/new', [AlphaController::class, 'commerceStorePodcast'])->middleware('throttle:10,1')->name('podcasts.studio.store');
Route::get('/podcasts/studio/{id}', [AlphaController::class, 'commercePodcastManage'])->whereNumber('id')->name('podcasts.studio.manage');
Route::post('/podcasts/studio/{id}/update', [AlphaController::class, 'commerceUpdatePodcast'])->whereNumber('id')->middleware('throttle:20,1')->name('podcasts.studio.update');
Route::post('/podcasts/studio/{id}/publish', [AlphaController::class, 'commercePublishPodcast'])->whereNumber('id')->middleware('throttle:10,1')->name('podcasts.studio.publish');
Route::post('/podcasts/studio/{id}/delete', [AlphaController::class, 'commerceDeletePodcast'])->whereNumber('id')->middleware('throttle:10,1')->name('podcasts.studio.delete');
Route::post('/podcasts/studio/{id}/episodes', [AlphaController::class, 'commerceStorePodcastEpisode'])->whereNumber('id')->middleware('throttle:20,1')->name('podcasts.studio.episodes.store');
Route::post('/podcasts/studio/{id}/episodes/{episodeId}/publish', [AlphaController::class, 'commercePublishPodcastEpisode'])->whereNumber('id')->whereNumber('episodeId')->middleware('throttle:20,1')->name('podcasts.studio.episodes.publish');
Route::post('/podcasts/studio/{id}/episodes/{episodeId}/delete', [AlphaController::class, 'commerceDeletePodcastEpisode'])->whereNumber('id')->whereNumber('episodeId')->middleware('throttle:10,1')->name('podcasts.studio.episodes.delete');
