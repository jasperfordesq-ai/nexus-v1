<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\MarketplaceOrder;
use App\Services\CourseEnrollmentService;
use App\Services\CourseInstructorService;
use App\Services\CourseLessonService;
use App\Services\CourseProgressService;
use App\Services\CourseSectionService;
use App\Services\CourseService;
use App\Services\MarketplaceListingService;
use App\Services\MarketplaceOfferService;
use App\Services\MarketplaceOrderService;
use App\Services\MarketplacePickupSlotService;
use App\Services\MarketplaceRatingService;
use App\Services\MarketplaceReportService;
use App\Services\MarketplaceSellerService;
use App\Services\MemberPremiumService;
use App\Services\MerchantCouponService;
use App\Services\MerchantOnboardingService;
use App\Services\PodcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Marketplace, courses, podcasts, coupons, premium & clubs — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * Calls the SAME services the React API controllers call — never reimplements
 * money/offer/order/rating/notification logic. Notifications are mirrored by
 * the underlying services (e.g. MarketplaceOfferService::create sends the
 * seller email + bell), wrapped there in LocaleContext.
 */
trait CommerceParity
{
    // =================================================================
    //  Marketplace — Create / Edit / My Listings
    // =================================================================

    /** Show the "create a listing" form (seller flow). */
    public function commerceCreateListingForm(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $this->view('accessible-frontend::commerce-listing-form', $this->commerceListingFormData(
            $tenantSlug,
            'create',
            null,
            route('govuk-alpha.marketplace.store', ['tenantSlug' => $tenantSlug]),
            self::asStr($request->query('status')) ?: null,
        ));
    }

    /** Persist a new marketplace listing. */
    public function commerceStoreListing(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        [$data, $errors] = $this->commerceListingInput($request);
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.marketplace.create', ['tenantSlug' => $tenantSlug])
                ->withInput()->with('commerceListingErrors', $errors);
        }

        $newId = 0;
        $failure = null;
        try {
            MarketplaceSellerService::getOrCreateProfile($userId);
            $listing = MarketplaceListingService::create($userId, $data);
            $newId = (int) $listing->id;
        } catch (\DomainException $e) {
            $failure = $e->getMessage() === 'SELLER_SUSPENDED'
                ? __('govuk_alpha_commerce.listing_form.error_suspended')
                : __('govuk_alpha_commerce.listing_form.error_create');
        } catch (\InvalidArgumentException $e) {
            $failure = e($e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $failure = __('govuk_alpha_commerce.listing_form.error_create');
        }

        if ($newId > 0) {
            return redirect()->route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $newId]);
        }

        return redirect()->route('govuk-alpha.marketplace.create', ['tenantSlug' => $tenantSlug])
            ->withInput()->with('commerceListingErrors', [$failure ?? __('govuk_alpha_commerce.listing_form.error_create')]);
    }

    /** Show the edit form for one of the member's own listings. */
    public function commerceEditListingForm(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $listing = $this->commerceOwnedListingOr404($id, $userId);

        return $this->view('accessible-frontend::commerce-listing-form', $this->commerceListingFormData(
            $tenantSlug,
            'edit',
            $listing,
            route('govuk-alpha.marketplace.update', ['tenantSlug' => $tenantSlug, 'id' => $id]),
            self::asStr($request->query('status')) ?: null,
        ));
    }

    /** Persist edits to one of the member's own listings. */
    public function commerceUpdateListing(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $listing = $this->commerceOwnedListingOr404($id, $userId);

        [$data, $errors] = $this->commerceListingInput($request);
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.marketplace.edit', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withInput()->with('commerceListingErrors', $errors);
        }

        $ok = false;
        try {
            MarketplaceListingService::update($listing, $data);
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        if ($ok) {
            return redirect()->route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $id]);
        }

        return redirect()->route('govuk-alpha.marketplace.edit', ['tenantSlug' => $tenantSlug, 'id' => $id])
            ->withInput()->with('commerceListingErrors', [__('govuk_alpha_commerce.listing_form.error_update')]);
    }

    /** Soft-remove one of the member's own listings. */
    public function commerceDeleteListing(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $listing = $this->commerceOwnedListingOr404($id, $userId);

        $ok = false;
        try {
            MarketplaceListingService::remove($listing);
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.mine', ['tenantSlug' => $tenantSlug, 'status' => $ok ? 'deleted' : 'delete-failed']);
    }

    /** Renew (re-activate + extend) one of the member's own listings. */
    public function commerceRenewListing(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $listing = $this->commerceOwnedListingOr404($id, $userId);

        $ok = false;
        try {
            MarketplaceListingService::renew($listing);
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.mine', ['tenantSlug' => $tenantSlug, 'status' => $ok ? 'renewed' : 'renew-failed']);
    }

    /** Seller dashboard: the member's own listings grouped by status. */
    public function commerceMyListings(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tab = $this->allowed(self::asStr($request->query('tab')), ['active', 'draft', 'sold', 'expired'], 'active');

        $items = [];
        $counts = ['active' => 0, 'draft' => 0, 'sold' => 0, 'expired' => 0];
        try {
            // Fetch all the member's listings (own listings show every status).
            $all = MarketplaceListingService::getAll([
                'user_id' => $userId,
                'limit' => 100,
                'current_user_id' => $userId,
            ])['items'] ?? [];
            foreach ($all as $row) {
                $row = is_array($row) ? $row : (array) $row;
                $status = (string) ($row['status'] ?? '');
                if (array_key_exists($status, $counts)) {
                    $counts[$status]++;
                }
                if ($status === $tab) {
                    $items[] = $row;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-my-listings', [
            'title' => __('govuk_alpha_commerce.my_listings.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'commerceActiveTab' => 'mine',
            'listings' => $items,
            'tab' => $tab,
            'counts' => $counts,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    // =================================================================
    //  Marketplace — Save / Collections / Free items / Seller profile / Category
    // =================================================================

    /** Save (bookmark) a listing. */
    public function commerceSaveListing(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        try {
            // 404 a cross-tenant / missing id (HasTenantScope on the model).
            MarketplaceListing::findOrFail($id);
            MarketplaceListingService::saveListing($userId, $id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $id])
            ->with('commerce_status', 'saved');
    }

    /** Unsave (remove bookmark) a listing. */
    public function commerceUnsaveListing(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        try {
            MarketplaceListingService::unsaveListing($userId, $id);
        } catch (\Throwable $e) {
            report($e);
        }

        $back = self::asStr($request->input('redirect_to'));
        if ($back === 'saved') {
            return redirect()->route('govuk-alpha.marketplace.saved', ['tenantSlug' => $tenantSlug])
                ->with('commerce_status', 'unsaved');
        }

        return redirect()->route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $id])
            ->with('commerce_status', 'unsaved');
    }

    /** Saved / favourite listings collection. */
    public function commerceSavedListings(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $items = [];
        try {
            $items = MarketplaceListingService::getSavedListings($userId, 50)['items'] ?? [];
            $items = array_map(static fn ($i) => is_array($i) ? $i : (array) $i, $items);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-saved', [
            'title' => __('govuk_alpha_commerce.saved.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'commerceActiveTab' => 'saved',
            'listings' => $items,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Free items: marketplace filtered to free (price_type=free) listings. */
    public function commerceFreeItems(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $items = [];
        try {
            $items = MarketplaceListingService::getAll([
                'limit' => 50,
                'price_type' => 'free',
                'current_user_id' => $userId,
            ])['items'] ?? [];
            $items = array_map(static fn ($i) => is_array($i) ? $i : (array) $i, $items);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-free-items', [
            'title' => __('govuk_alpha_commerce.free_items.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'commerceActiveTab' => 'free',
            'listings' => $items,
        ]);
    }

    /** A seller's public profile: their info + active listings. */
    public function commerceSellerProfile(Request $request, string $tenantSlug, int $sellerId): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Seller must belong to this tenant.
        $seller = \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $sellerId)
            ->where('tenant_id', TenantContext::getId())
            ->select(['id', 'first_name', 'last_name', 'name', 'avatar_url', 'is_verified', 'created_at'])
            ->first();
        abort_if($seller === null, 404);

        $items = [];
        $rating = null;
        try {
            $items = MarketplaceListingService::getAll([
                'user_id' => $sellerId,
                'status' => 'active',
                'limit' => 50,
                'current_user_id' => $userId,
            ])['items'] ?? [];
            $items = array_map(static fn ($i) => is_array($i) ? $i : (array) $i, $items);
            // Aggregate rating columns live on the seller profile (keyed by user_id).
            $profile = MarketplaceSellerService::getByUserId($sellerId);
            if ($profile !== null) {
                $rating = [
                    'avg_rating' => (float) ($profile->avg_rating ?? 0),
                    'total_ratings' => (int) ($profile->total_ratings ?? 0),
                    'total_sales' => (int) ($profile->total_sales ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $name = trim((string) ($seller->first_name ?? '') . ' ' . (string) ($seller->last_name ?? ''));
        if ($name === '') {
            $name = (string) ($seller->name ?? '');
        }

        return $this->view('accessible-frontend::commerce-seller', [
            'title' => $name !== '' ? $name : __('govuk_alpha_commerce.seller.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'sellerName' => $name,
            'sellerAvatar' => self::asStr($seller->avatar_url ?? ''),
            // Identity-verified trust tag keys on the id_verified badge, NOT the
            // email-verified `is_verified` column (mirrors React VerificationBadgeRow).
            'sellerVerified' => $this->alphaIdentityVerified((int) $seller->id),
            'sellerSince' => isset($seller->created_at) ? (string) $seller->created_at : '',
            'sellerRating' => is_array($rating) ? $rating : null,
            'listings' => $items,
        ]);
    }

    // =================================================================
    //  Marketplace — Buy now / Make offer / Report
    // =================================================================

    /** Buy-now confirmation page (for fixed-price money listings). */
    public function commerceBuyForm(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $item = $this->commercePurchasableOr404($id, $userId);

        return $this->view('accessible-frontend::commerce-buy', [
            'title' => __('govuk_alpha_commerce.buy.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'item' => $item,
        ]);
    }

    /** Place a direct purchase order (creates a pending-payment order). */
    public function commerceStoreBuy(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $this->commercePurchasableOr404($id, $userId);

        $quantity = max(1, (int) self::asStr($request->input('quantity', '1')));
        $deliveryNotes = trim(self::asStr($request->input('delivery_notes')));
        $data = ['quantity' => $quantity];
        if ($deliveryNotes !== '') {
            $data['delivery_notes'] = $deliveryNotes;
        }

        $error = null;
        try {
            MarketplaceOrderService::createDirectPurchase($userId, $id, $data);
        } catch (\InvalidArgumentException $e) {
            $error = e($e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $error = __('govuk_alpha_commerce.buy.error_generic');
        }

        if ($error !== null) {
            return redirect()->route('govuk-alpha.marketplace.buy', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->with('commerceBuyError', $error);
        }

        return redirect()->route('govuk-alpha.marketplace.orders.buyer', ['tenantSlug' => $tenantSlug, 'status' => 'ordered']);
    }

    /**
     * Pay for a pending money order by card via Stripe's hosted Checkout page
     * (the accessible no-JS path — no Stripe.js). Creates a Connect Checkout
     * Session and 303-redirects the buyer to Stripe; the checkout.session
     * .completed webhook marks the order paid. Only the order's buyer, only a
     * pending_payment money order.
     */
    public function commerceCheckoutCardPay(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Buyer-only; cross-tenant / non-owner orders 404 inside the helper.
        $order = $this->commerceOrderForRoleOr404($id, $userId, 'buyer');

        if (($order->status ?? '') !== 'pending_payment') {
            return redirect()->route('govuk-alpha.marketplace.orders.buyer', ['tenantSlug' => $tenantSlug, 'status' => 'pay-not-pending']);
        }
        // Card checkout is only for money orders (time-credit orders settle elsewhere).
        if ((float) ($order->total_price ?? 0) <= 0) {
            return redirect()->route('govuk-alpha.marketplace.orders.buyer', ['tenantSlug' => $tenantSlug, 'status' => 'pay-not-required']);
        }

        // Absolute URLs on the accessible host that Stripe returns the buyer to.
        $successUrl = route('govuk-alpha.marketplace.orders.buyer', ['tenantSlug' => $tenantSlug, 'status' => 'payment-submitted']);
        $cancelUrl = route('govuk-alpha.marketplace.orders.buyer', ['tenantSlug' => $tenantSlug, 'status' => 'payment-cancelled']);

        try {
            $checkoutUrl = \App\Services\MarketplacePaymentService::createCheckoutSession($order, $successUrl, $cancelUrl);
        } catch (\RuntimeException $e) {
            // Seller not onboarded / Stripe API failure — surface a friendly state.
            return redirect()->route('govuk-alpha.marketplace.orders.buyer', ['tenantSlug' => $tenantSlug, 'status' => 'pay-unavailable']);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.marketplace.orders.buyer', ['tenantSlug' => $tenantSlug, 'status' => 'pay-failed']);
        }

        // External redirect to Stripe's hosted Checkout (303 so the POST becomes a GET).
        return redirect()->away($checkoutUrl, 303);
    }

    /** Make-an-offer form (for negotiable listings). */
    public function commerceOfferForm(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $item = null;
        try {
            $item = MarketplaceListingService::getById($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($item === null, 404);
        // Cannot offer on your own listing.
        abort_if((bool) ($item['is_own'] ?? false), 403);

        return $this->view('accessible-frontend::commerce-offer', [
            'title' => __('govuk_alpha_commerce.offer.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'item' => $item,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Submit an offer on a listing. */
    public function commerceStoreOffer(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $amount = (float) self::asStr($request->input('amount'));
        $message = trim(self::asStr($request->input('message')));

        if ($amount <= 0) {
            return redirect()->route('govuk-alpha.marketplace.offer', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withInput()->with('commerceOfferErrors', [__('govuk_alpha_commerce.offer.error_amount')]);
        }

        $error = null;
        try {
            $data = ['amount' => $amount];
            if ($message !== '') {
                $data['message'] = $message;
            }
            // MarketplaceOfferService::create resolves the listing tenant + notifies the seller.
            MarketplaceOfferService::create($userId, $id, $data);
        } catch (\InvalidArgumentException $e) {
            $error = e($e->getMessage());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404);
        } catch (\Throwable $e) {
            report($e);
            $error = __('govuk_alpha_commerce.offer.error_generic');
        }

        if ($error !== null) {
            return redirect()->route('govuk-alpha.marketplace.offer', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withInput()->with('commerceOfferErrors', [$error]);
        }

        return redirect()->route('govuk-alpha.marketplace.offers', ['tenantSlug' => $tenantSlug, 'status' => 'offer-sent']);
    }

    /** Report a listing (content moderation). */
    public function commerceReportForm(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $item = null;
        try {
            $item = MarketplaceListingService::getById($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($item === null, 404);

        return $this->view('accessible-frontend::commerce-report', [
            'title' => __('govuk_alpha_commerce.report.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'item' => $item,
            'reasons' => self::COMMERCE_REPORT_REASONS,
        ]);
    }

    /** Submit a listing report. */
    public function commerceStoreReport(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $reason = $this->allowed(self::asStr($request->input('reason')), self::COMMERCE_REPORT_REASONS, '');
        $description = trim(self::asStr($request->input('description')));

        $errors = [];
        if ($reason === '') {
            $errors['reason'] = __('govuk_alpha_commerce.report.error_reason');
        }
        if ($description === '') {
            $errors['description'] = __('govuk_alpha_commerce.report.error_description');
        }
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.marketplace.report', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withInput()->withErrors($errors);
        }

        $ok = false;
        try {
            MarketplaceReportService::createReport($userId, $id, [
                'reason' => $reason,
                'description' => $description,
            ]);
            $ok = true;
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('govuk-alpha.marketplace.report', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withInput()->withErrors(['description' => e($e->getMessage())]);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $id])
            ->with('commerce_status', $ok ? 'reported' : 'report-failed');
    }

    // =================================================================
    //  Marketplace — Offers dashboard
    // =================================================================

    /** My offers dashboard: sent (as buyer) and received (as seller). */
    public function commerceMyOffers(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tab = $this->allowed(self::asStr($request->query('tab')), ['received', 'sent'], 'received');

        $offers = [];
        try {
            if ($tab === 'sent') {
                $offers = MarketplaceOfferService::getSentOffers($userId, 50)['items'] ?? [];
            } else {
                $offers = MarketplaceOfferService::getReceivedOffers($userId, 50)['items'] ?? [];
            }
            $offers = array_map(static fn ($o) => is_array($o) ? $o : (array) $o, $offers);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-offers', [
            'title' => __('govuk_alpha_commerce.offers.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'commerceActiveTab' => 'offers',
            'tab' => $tab,
            'offers' => $offers,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Seller accepts a received offer. */
    public function commerceAcceptOffer(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        return $this->commerceOfferAction($tenantSlug, $id, 'accept');
    }

    /** Seller declines a received offer. */
    public function commerceDeclineOffer(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        return $this->commerceOfferAction($tenantSlug, $id, 'decline');
    }

    /** Buyer withdraws a sent offer. */
    public function commerceWithdrawOffer(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        return $this->commerceOfferAction($tenantSlug, $id, 'withdraw');
    }

    // =================================================================
    //  Marketplace — Orders dashboards
    // =================================================================

    /** Buyer's purchase orders. */
    public function commerceBuyerOrders(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tab = $this->allowed(self::asStr($request->query('tab')), ['all', 'active', 'completed', 'cancelled'], 'all');
        $statusFilter = $tab === 'all' ? null : $tab;

        $orders = [];
        try {
            $orders = MarketplaceOrderService::getBuyerOrders($userId, $statusFilter, 50)['items'] ?? [];
            $orders = array_map(static fn ($o) => is_array($o) ? $o : (array) $o, $orders);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-orders', [
            'title' => __('govuk_alpha_commerce.orders_buyer.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'commerceActiveTab' => 'orders',
            'orderRole' => 'buyer',
            'tab' => $tab,
            'orders' => $orders,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Seller's incoming sales orders. */
    public function commerceSellerOrders(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tab = $this->allowed(self::asStr($request->query('tab')), ['all', 'paid', 'shipped', 'completed'], 'all');
        $statusFilter = $tab === 'all' ? null : $tab;

        $orders = [];
        try {
            $orders = MarketplaceOrderService::getSellerOrders($userId, $statusFilter, 50)['items'] ?? [];
            $orders = array_map(static fn ($o) => is_array($o) ? $o : (array) $o, $orders);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-orders', [
            'title' => __('govuk_alpha_commerce.orders_seller.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'commerceActiveTab' => 'sales',
            'orderRole' => 'seller',
            'tab' => $tab,
            'orders' => $orders,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Seller marks an order as shipped. */
    public function commerceShipOrder(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $order = $this->commerceOrderForRoleOr404($id, $userId, 'seller');

        $status = 'ship-failed';
        try {
            $tracking = trim(self::asStr($request->input('tracking_number')));
            $shipData = [];
            if ($tracking !== '') {
                $shipData['tracking_number'] = $tracking;
            }
            MarketplaceOrderService::markShipped($order, $shipData);
            $status = 'shipped';
        } catch (\InvalidArgumentException $e) {
            $status = 'ship-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.orders.seller', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    /** Buyer confirms delivery / receipt. */
    public function commerceConfirmOrder(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $order = $this->commerceOrderForRoleOr404($id, $userId, 'buyer');

        $status = 'confirm-failed';
        try {
            MarketplaceOrderService::confirmDelivery($order);
            $status = 'confirmed';
        } catch (\InvalidArgumentException $e) {
            $status = 'confirm-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.orders.buyer', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    /** Buyer or seller cancels an order. */
    public function commerceCancelOrder(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $order = $this->commerceOrderForRoleOr404($id, $userId, 'either');
        $isBuyer = (int) $order->buyer_id === $userId;

        $reason = trim(self::asStr($request->input('reason')));
        if ($reason === '') {
            $reason = __('govuk_alpha_commerce.orders.cancel_default_reason');
        }

        $status = 'cancel-failed';
        try {
            MarketplaceOrderService::cancel($order, $reason);
            $status = 'cancelled';
        } catch (\InvalidArgumentException $e) {
            $status = 'cancel-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        $route = $isBuyer ? 'govuk-alpha.marketplace.orders.buyer' : 'govuk-alpha.marketplace.orders.seller';
        return redirect()->route($route, ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    /** Buyer or seller rates a completed order. */
    public function commerceRateOrder(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $order = $this->commerceOrderForRoleOr404($id, $userId, 'either');
        $role = (int) $order->buyer_id === $userId ? 'buyer' : 'seller';
        $isBuyer = $role === 'buyer';

        $rating = (int) self::asStr($request->input('rating'));
        $comment = trim(self::asStr($request->input('comment')));

        $route = $isBuyer ? 'govuk-alpha.marketplace.orders.buyer' : 'govuk-alpha.marketplace.orders.seller';

        if ($rating < 1 || $rating > 5) {
            return redirect()->route($route, ['tenantSlug' => $tenantSlug, 'status' => 'rate-invalid']);
        }

        $status = 'rate-failed';
        try {
            $data = ['rating' => $rating];
            if ($comment !== '') {
                $data['comment'] = $comment;
            }
            MarketplaceRatingService::rateOrder($id, $userId, $role, $data, TenantContext::getId());
            $status = 'rated';
        } catch (\InvalidArgumentException $e) {
            $status = 'rate-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route($route, ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    // =================================================================
    //  Courses — My Learning + Lesson player
    // =================================================================

    /** Learner dashboard: courses the member is enrolled in. */
    public function commerceMyLearning(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $enrollments = [];
        try {
            $enrollments = CourseEnrollmentService::forUser($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-my-learning', [
            'title' => __('govuk_alpha_commerce.my_learning.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'enrollments' => is_array($enrollments) ? $enrollments : [],
        ]);
    }

    /** Course lesson player / learn view. Enrolment required. */
    public function commerceCourseLearn(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = null;
        try {
            $course = CourseService::findById($id);
            if ($course !== null && (int) $course->tenant_id !== TenantContext::getId()) {
                $course = null;
            }
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($course === null, 404);

        // Must be enrolled to access the player.
        $enrollment = CourseEnrollmentService::find($id, $userId);
        if ($enrollment === null) {
            return redirect()->route('govuk-alpha.courses.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'enrol-required']);
        }

        // Build the section/lesson outline + per-lesson completion + availability.
        $completedLessonIds = \Illuminate\Support\Facades\DB::table('course_lesson_progress')
            ->where('enrollment_id', $enrollment->id)
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->flip()
            ->all();

        $sections = [];
        $currentLesson = null;
        $requestedLessonId = (int) self::asStr($request->query('lesson'));

        foreach ($course->sections->sortBy('position') as $section) {
            $sectionLessons = [];
            foreach ($section->lessons->sortBy('position') as $lesson) {
                $availability = \App\Services\CourseLessonService::availability($lesson, $enrollment->enrolled_at);
                $available = (bool) ($availability['available'] ?? true);
                $entry = [
                    'id' => (int) $lesson->id,
                    'title' => (string) $lesson->title,
                    'content_type' => (string) $lesson->content_type,
                    'is_completed' => isset($completedLessonIds[$lesson->id]),
                    'available' => $available,
                ];
                $sectionLessons[] = $entry;

                if ($available) {
                    if ($requestedLessonId > 0 && (int) $lesson->id === $requestedLessonId) {
                        $currentLesson = $this->commerceLessonPayload($lesson, $entry);
                    } elseif ($currentLesson === null && $requestedLessonId === 0 && !$entry['is_completed']) {
                        $currentLesson = $this->commerceLessonPayload($lesson, $entry);
                    }
                }
            }
            $sections[] = [
                'id' => (int) $section->id,
                'title' => (string) $section->title,
                'lessons' => $sectionLessons,
            ];
        }

        // Fall back to the first available lesson if nothing matched.
        if ($currentLesson === null) {
            foreach ($course->sections->sortBy('position') as $section) {
                foreach ($section->lessons->sortBy('position') as $lesson) {
                    $availability = \App\Services\CourseLessonService::availability($lesson, $enrollment->enrolled_at);
                    if ((bool) ($availability['available'] ?? true)) {
                        $currentLesson = $this->commerceLessonPayload($lesson, [
                            'id' => (int) $lesson->id,
                            'is_completed' => isset($completedLessonIds[$lesson->id]),
                        ]);
                        break 2;
                    }
                }
            }
        }

        // Quiz lessons carry an interactive quiz: load the learner-safe payload
        // + the viewer's attempt state so the player can render the questions and
        // their last result (mirrors React's quiz lesson type).
        if (is_array($currentLesson) && ($currentLesson['content_type'] ?? '') === 'quiz') {
            $lessonModel = \App\Models\CourseLesson::find((int) $currentLesson['id']);
            if ($lessonModel !== null) {
                $currentLesson['quiz'] = $this->commerceQuizPayload($lessonModel, $userId);
            }
        }

        return $this->view('accessible-frontend::commerce-course-learn', [
            'title' => (string) ($course->title ?? '') ?: __('govuk_alpha_commerce.learn.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'course' => ['id' => (int) $course->id, 'title' => (string) $course->title],
            'sections' => $sections,
            'currentLesson' => $currentLesson,
            'progressPercent' => (float) ($enrollment->progress_percent ?? 0),
            'isCompleted' => ($enrollment->status ?? '') === 'completed',
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Mark a lesson complete (advances course progress). */
    public function commerceCompleteLesson(Request $request, string $tenantSlug, int $id, int $lessonId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = null;
        try {
            $course = CourseService::findById($id);
            if ($course !== null && (int) $course->tenant_id !== TenantContext::getId()) {
                $course = null;
            }
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($course === null, 404);

        $enrollment = CourseEnrollmentService::find($id, $userId);
        if ($enrollment === null) {
            return redirect()->route('govuk-alpha.courses.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'enrol-required']);
        }

        // The lesson must belong to this course (defence-in-depth).
        $lessonBelongs = \Illuminate\Support\Facades\DB::table('course_lessons')
            ->where('id', $lessonId)
            ->where('course_id', $id)
            ->exists();
        abort_unless($lessonBelongs, 404);

        $completed = false;
        try {
            $result = CourseProgressService::completeLesson($enrollment, $lessonId, $userId);
            $completed = (bool) ($result['course_completed'] ?? false);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.courses.learn', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $completed ? 'course-completed' : 'lesson-completed',
        ]);
    }

    /**
     * Submit a quiz attempt for a quiz lesson. Mirrors the React quiz submit:
     * answers are posted as answers[questionId] (a scalar, or an array for
     * multi-select), graded by CourseQuizService::submitAttempt (which enforces
     * the max-attempts ceiling under a row lock). Tenant + enrollment + lesson↔
     * quiz ownership are all verified so a cross-course/cross-tenant quiz id
     * cannot be graded.
     */
    public function commerceCourseQuizSubmit(Request $request, string $tenantSlug, int $id, int $lessonId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = null;
        try {
            $course = CourseService::findById($id);
            if ($course !== null && (int) $course->tenant_id !== TenantContext::getId()) {
                $course = null;
            }
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($course === null, 404);

        $enrollment = CourseEnrollmentService::find($id, $userId);
        if ($enrollment === null) {
            return redirect()->route('govuk-alpha.courses.show', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'enrol-required']);
        }

        // The lesson must belong to this course AND be a quiz lesson.
        $lesson = \App\Models\CourseLesson::where('id', $lessonId)
            ->where('course_id', $id)
            ->first();
        abort_if($lesson === null, 404);

        $quiz = $lesson->quiz; // HasOne(CourseQuiz, lesson_id)
        abort_if($quiz === null || (int) $quiz->course_id !== $id, 404);

        $answers = $request->input('answers');
        $answers = is_array($answers) ? $answers : [];

        $status = 'quiz-failed';
        try {
            $result = \App\Services\CourseQuizService::submitAttempt((int) $quiz->id, $userId, $answers, (int) $enrollment->id);
            if (! empty($result['needs_review'])) {
                $status = 'quiz-pending-review';
            } elseif (! empty($result['passed'])) {
                $status = 'quiz-passed';
            } else {
                $status = 'quiz-failed';
            }
        } catch (\App\Exceptions\MaxAttemptsExceededException $e) {
            $status = 'quiz-no-attempts';
        } catch (\Throwable $e) {
            report($e);
            $status = 'quiz-error';
        }

        return redirect()->route('govuk-alpha.courses.learn', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'lesson' => $lessonId,
            'status' => $status,
        ]);
    }

    // =================================================================
    //  Premium — Manage subscription / cancel / billing portal
    // =================================================================

    /** Manage subscription page: current tier, status, billing actions. */
    public function commercePremiumManage(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('member_premium'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $subscription = null;
        try {
            $subscription = MemberPremiumService::getMemberSubscription($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        // No subscription → send them to the pricing page.
        if ($subscription === null) {
            return redirect()->route('govuk-alpha.premium.index', ['tenantSlug' => $tenantSlug, 'status' => 'no-subscription']);
        }

        return $this->view('accessible-frontend::commerce-premium-manage', [
            'title' => __('govuk_alpha_commerce.premium_manage.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'subscription' => $subscription,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Cancel the member's subscription at period end. */
    public function commercePremiumCancel(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('member_premium'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $ok = false;
        try {
            $ok = MemberPremiumService::cancel($userId, true);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.premium.manage', ['tenantSlug' => $tenantSlug, 'status' => $ok ? 'cancel-scheduled' : 'cancel-failed']);
    }

    /** Redirect to the Stripe billing portal to manage payment methods. */
    public function commercePremiumPortal(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('member_premium'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $returnUrl = route('govuk-alpha.premium.manage', ['tenantSlug' => $tenantSlug]);
        try {
            $session = MemberPremiumService::createBillingPortalSession($userId, $returnUrl);
            $url = self::asStr($session['portal_url'] ?? '');
            if ($url !== '') {
                return redirect()->away($url);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.premium.manage', ['tenantSlug' => $tenantSlug, 'status' => 'portal-failed']);
    }

    // =================================================================
    //  Courses — Instructor / creator suite
    // =================================================================

    /** Instructor dashboard: the courses the member teaches (authored), any status. */
    public function commerceInstructorCourses(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $courses = [];
        try {
            $courses = CourseService::authoredBy($userId);
            $courses = is_array($courses) ? array_map(static fn ($c) => is_array($c) ? $c : (array) $c, $courses) : [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-instructor-courses', [
            'title' => __('govuk_alpha_commerce.instructor.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'courses' => $courses,
            'canAuthor' => $this->commerceCanAuthorCourses($userId),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Show the "create a course" form. */
    public function commerceCreateCourseForm(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Respect the tenant's authoring policy (open by default).
        abort_unless($this->commerceCanAuthorCourses($userId), 403);

        return $this->view('accessible-frontend::commerce-course-form', $this->commerceCourseFormData(
            $tenantSlug,
            'create',
            null,
            route('govuk-alpha.courses.instructor.store', ['tenantSlug' => $tenantSlug]),
        ));
    }

    /** Persist a new (draft) course, then redirect to the edit form. */
    public function commerceStoreCourse(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        abort_unless($this->commerceCanAuthorCourses($userId), 403);

        [$data, $errors] = $this->commerceCourseInput($request);
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.courses.instructor.create', ['tenantSlug' => $tenantSlug])
                ->withInput()->with('commerceCourseErrors', $errors);
        }

        $newId = 0;
        try {
            $course = CourseService::create($userId, $data);
            $newId = (int) $course->id;
        } catch (\Throwable $e) {
            report($e);
        }

        if ($newId > 0) {
            return redirect()->route('govuk-alpha.courses.instructor.edit', ['tenantSlug' => $tenantSlug, 'id' => $newId, 'status' => 'created']);
        }

        return redirect()->route('govuk-alpha.courses.instructor.create', ['tenantSlug' => $tenantSlug])
            ->withInput()->with('commerceCourseErrors', [__('govuk_alpha_commerce.instructor.error_create')]);
    }

    /** Show the edit form for one of the member's own courses. */
    public function commerceEditCourseForm(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);

        return $this->view('accessible-frontend::commerce-course-form', $this->commerceCourseFormData(
            $tenantSlug,
            'edit',
            $course,
            route('govuk-alpha.courses.instructor.update', ['tenantSlug' => $tenantSlug, 'id' => $id]),
            self::asStr($request->query('status')) ?: null,
        ));
    }

    /** Persist edits to one of the member's own courses. */
    public function commerceUpdateCourse(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);

        [$data, $errors] = $this->commerceCourseInput($request);
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.courses.instructor.edit', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withInput()->with('commerceCourseErrors', $errors);
        }

        $ok = false;
        try {
            CourseService::update($course, $data);
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.courses.instructor.edit', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'saved' : 'save-failed',
        ]);
    }

    /** Publish one of the member's own courses (subject to tenant moderation). */
    public function commercePublishCourse(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);

        $status = 'publish-failed';
        try {
            $updated = CourseService::publish($course, true);
            $status = ($updated->moderation_status ?? '') === 'approved' ? 'published' : 'pending-review';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.courses.instructor.edit', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]);
    }

    /** Revert one of the member's own courses to draft. */
    public function commerceUnpublishCourse(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);

        $status = 'unpublish-failed';
        try {
            CourseService::unpublish($course);
            $status = 'unpublished';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.courses.instructor.edit', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]);
    }

    /** Delete one of the member's own courses. */
    public function commerceDeleteCourse(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);

        $ok = false;
        try {
            CourseService::delete($course);
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.courses.instructor', ['tenantSlug' => $tenantSlug, 'status' => $ok ? 'deleted' : 'delete-failed']);
    }

    /** Per-course analytics for the owner: enrolment funnel, completion, per-lesson drop-off. */
    public function commerceCourseAnalytics(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);

        // Mirror CourseController::analytics — the SAME aggregation queries.
        $enrollments = \App\Models\CourseEnrollment::where('course_id', $id);
        $total = (clone $enrollments)->count();
        $completed = (clone $enrollments)->where('status', 'completed')->count();
        $active = (clone $enrollments)->where('status', 'active')->count();
        $dropped = (clone $enrollments)->where('status', 'dropped')->count();
        $avgProgress = (float) (clone $enrollments)->avg('progress_percent');

        $lessons = \App\Models\CourseLesson::where('course_id', $id)
            ->orderBy('position')
            ->get(['id', 'title']);
        $perLesson = $lessons->map(static function ($lesson) {
            return [
                'lesson_id' => (int) $lesson->id,
                'title' => (string) $lesson->title,
                'completed' => \App\Models\CourseLessonProgress::where('lesson_id', $lesson->id)
                    ->where('status', 'completed')
                    ->count(),
            ];
        })->all();

        $quizIds = \App\Models\CourseQuiz::where('course_id', $id)->pluck('id')->all();
        $avgQuizScore = $quizIds
            ? (float) \App\Models\CourseQuizAttempt::whereIn('quiz_id', $quizIds)->avg('score_percent')
            : 0.0;
        $quizAttempts = $quizIds
            ? \App\Models\CourseQuizAttempt::whereIn('quiz_id', $quizIds)->count()
            : 0;

        $maxLessonCompleted = 0;
        foreach ($perLesson as $row) {
            $maxLessonCompleted = max($maxLessonCompleted, (int) ($row['completed'] ?? 0));
        }

        return $this->view('accessible-frontend::commerce-course-analytics', [
            'title' => __('govuk_alpha_commerce.analytics.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'courseTitle' => (string) $course->title,
            'analytics' => [
                'total' => $total,
                'active' => $active,
                'completed' => $completed,
                'dropped' => $dropped,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
                'avg_progress' => round($avgProgress, 1),
                'avg_quiz_score' => round($avgQuizScore, 1),
                'quiz_attempts' => $quizAttempts,
            ],
            'perLesson' => $perLesson,
            'maxLessonCompleted' => $maxLessonCompleted,
        ]);
    }

    // =================================================================
    //  Courses — section + lesson BUILDER (no-JS CRUD on the edit page)
    //  Mirrors CourseContentController: feature gate + course ownership,
    //  then CourseSectionService / CourseLessonService (the SAME services).
    // =================================================================

    /** Add a section to one of the member's own courses. */
    public function commerceStoreCourseSection(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);

        $title = trim(self::asStr($request->input('section_title')));
        if ($title === '') {
            return $this->commerceBuilderRedirect($tenantSlug, $id, 'section-title-missing');
        }

        $status = 'section-failed';
        try {
            CourseSectionService::create((int) $course->id, ['title' => mb_substr($title, 0, 200)]);
            $status = 'section-added';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->commerceBuilderRedirect($tenantSlug, $id, $status);
    }

    /** Rename a section that belongs to one of the member's own courses. */
    public function commerceUpdateCourseSection(Request $request, string $tenantSlug, int $id, int $sectionId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);
        $this->commerceSectionInCourseOr404($sectionId, (int) $course->id);

        $title = trim(self::asStr($request->input('section_title')));
        if ($title === '') {
            return $this->commerceBuilderRedirect($tenantSlug, $id, 'section-title-missing');
        }

        $status = 'section-failed';
        try {
            CourseSectionService::update($sectionId, ['title' => mb_substr($title, 0, 200)]);
            $status = 'section-saved';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->commerceBuilderRedirect($tenantSlug, $id, $status);
    }

    /** Delete a section from one of the member's own courses (lessons are orphaned, not deleted). */
    public function commerceDeleteCourseSection(Request $request, string $tenantSlug, int $id, int $sectionId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);
        $this->commerceSectionInCourseOr404($sectionId, (int) $course->id);

        $status = 'section-failed';
        try {
            CourseSectionService::delete($sectionId);
            $status = 'section-deleted';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->commerceBuilderRedirect($tenantSlug, $id, $status);
    }

    /** Add a lesson to one of the member's own courses. */
    public function commerceStoreCourseLesson(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);

        [$data, $errors] = $this->commerceLessonInput($request, (int) $course->id);
        if (!empty($errors)) {
            return $this->commerceBuilderRedirect($tenantSlug, $id, 'lesson-title-missing');
        }

        $status = 'lesson-failed';
        try {
            CourseLessonService::create((int) $course->id, $data);
            $status = 'lesson-added';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->commerceBuilderRedirect($tenantSlug, $id, $status);
    }

    /** Edit a lesson that belongs to one of the member's own courses. */
    public function commerceUpdateCourseLesson(Request $request, string $tenantSlug, int $id, int $lessonId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);
        $this->commerceLessonInCourseOr404($lessonId, (int) $course->id);

        [$data, $errors] = $this->commerceLessonInput($request, (int) $course->id);
        if (!empty($errors)) {
            return $this->commerceBuilderRedirect($tenantSlug, $id, 'lesson-title-missing');
        }

        $status = 'lesson-failed';
        try {
            CourseLessonService::update($lessonId, $data);
            $status = 'lesson-saved';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->commerceBuilderRedirect($tenantSlug, $id, $status);
    }

    /** Delete a lesson from one of the member's own courses. */
    public function commerceDeleteCourseLesson(Request $request, string $tenantSlug, int $id, int $lessonId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);
        $this->commerceLessonInCourseOr404($lessonId, (int) $course->id);

        $status = 'lesson-failed';
        try {
            CourseLessonService::delete($lessonId);
            $status = 'lesson-deleted';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->commerceBuilderRedirect($tenantSlug, $id, $status);
    }

    // =================================================================
    //  Marketplace — category browse page (/marketplace/category/{slug})
    // =================================================================

    /** Browse the marketplace filtered to a single category slug. */
    public function commerceCategoryListings(Request $request, string $tenantSlug, string $slug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $slug = mb_substr(trim($slug), 0, 120);
        $q = trim(self::asStr($request->query('q')));

        $items = [];
        $categories = [];
        $category = null;
        try {
            $rawCats = MarketplaceListingService::getCategories();
            $categories = is_array($rawCats) ? array_map(static fn ($c) => is_array($c) ? $c : (array) $c, $rawCats) : [];
            foreach ($categories as $cat) {
                if ((string) ($cat['slug'] ?? '') === $slug) {
                    $category = $cat;
                    break;
                }
            }

            $filters = ['limit' => 30, 'current_user_id' => $userId, 'category_slug' => $slug];
            if ($q !== '') {
                $filters['search'] = $q;
            }
            $items = MarketplaceListingService::getAll($filters)['items'] ?? [];
            $items = array_map(static fn ($i) => is_array($i) ? $i : (array) $i, $items);
        } catch (\Throwable $e) {
            report($e);
        }

        abort_if($category === null, 404);

        return $this->view('accessible-frontend::commerce-category', [
            'title' => (string) ($category['name'] ?? __('govuk_alpha_commerce.category.title')),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'listings' => is_array($items) ? $items : [],
            'category' => $category,
            'categorySlug' => $slug,
            'categoryQuery' => $q,
        ]);
    }

    // =================================================================
    //  Marketplace — buyer "my pickups" (click-and-collect reservations)
    // =================================================================

    /** List the member's pickup reservations with the collection code. */
    public function commerceMyPickups(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $reservations = [];
        try {
            $reservations = MarketplacePickupSlotService::listForBuyer($userId);
            $reservations = is_array($reservations) ? array_map(static fn ($r) => is_array($r) ? $r : (array) $r, $reservations) : [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-my-pickups', [
            'title' => __('govuk_alpha_commerce.pickups.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'reservations' => $reservations,
        ]);
    }

    // =================================================================
    //  Marketplace — seller pickup-slot management (click-and-collect)
    //  Mirrors MarketplacePickupSlotController slotsIndex/slotsStore/
    //  slotsUpdate/slotsDestroy: marketplace feature + auth, then the SAME
    //  MarketplacePickupSlotService the React seller page calls. Slots are
    //  scoped to the member's own seller profile (cross-seller id → 404).
    // =================================================================

    /** List the seller's own pickup time slots, with a "new slot" form. */
    public function commerceSellerPickupSlots(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $slots = [];
        try {
            $profile = MarketplaceSellerService::getOrCreateProfile($userId);
            $slots = MarketplacePickupSlotService::listForSeller($profile->id);
            $slots = is_array($slots) ? array_map(static fn ($s) => is_array($s) ? $s : (array) $s, $slots) : [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-pickup-slots', [
            'title' => __('govuk_alpha_commerce.slots.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'slots' => $slots,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Confirm a click-and-collect collection by entering the buyer's collection
     * code. This is the no-JS equivalent of the React seller "scan QR" page
     * (SellerPickupScanPage) — both call MarketplacePickupSlotService::scanQr,
     * which a camera scanner would ultimately hit too. The seller simply types
     * the short code the buyer shows them on their device.
     */
    public function commerceScanPickup(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $code = trim(self::asStr($request->input('qr_code')));
        if ($code === '') {
            return redirect()->route('govuk-alpha.marketplace.slots', ['tenantSlug' => $tenantSlug, 'status' => 'pickup-scan-failed']);
        }

        try {
            $reservation = MarketplacePickupSlotService::scanQr($code, $userId);
        } catch (\Throwable $e) {
            // DomainException carries a buyer-facing reason (bad/used code, not
            // this seller's order); only unexpected failures are worth a report.
            if (!$e instanceof \DomainException) {
                report($e);
            }
            return redirect()->route('govuk-alpha.marketplace.slots', ['tenantSlug' => $tenantSlug, 'status' => 'pickup-scan-failed']);
        }

        return redirect()
            ->route('govuk-alpha.marketplace.slots', ['tenantSlug' => $tenantSlug, 'status' => 'pickup-confirmed'])
            ->with('commercePickupScanOrderId', (int) ($reservation->order_id ?? 0));
    }

    /** Create a new pickup slot for the member's seller profile. */
    public function commerceStorePickupSlot(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        [$data, $errors] = $this->commercePickupSlotInput($request);
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.marketplace.slots', ['tenantSlug' => $tenantSlug])
                ->withInput()->with('commercePickupSlotErrors', $errors);
        }

        $ok = false;
        try {
            $profile = MarketplaceSellerService::getOrCreateProfile($userId);
            MarketplacePickupSlotService::create($profile->id, $data);
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.slots', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'slot-created' : 'slot-create-failed',
        ]);
    }

    /** Show the edit form for one of the seller's own pickup slots. */
    public function commerceEditPickupSlot(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $slot = $this->commerceOwnedPickupSlotOr404($id, $userId);

        return $this->view('accessible-frontend::commerce-pickup-slot-form', [
            'title' => __('govuk_alpha_commerce.slots.title_edit'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'slot' => [
                'id' => (int) $slot->id,
                'slot_start' => $slot->slot_start?->format('Y-m-d\TH:i'),
                'slot_end' => $slot->slot_end?->format('Y-m-d\TH:i'),
                'capacity' => (int) $slot->capacity,
                'booked_count' => (int) $slot->booked_count,
                'is_recurring' => (bool) $slot->is_recurring,
                'is_active' => (bool) $slot->is_active,
            ],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Persist edits to one of the seller's own pickup slots. */
    public function commerceUpdatePickupSlot(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $slot = $this->commerceOwnedPickupSlotOr404($id, $userId);

        [$data, $errors] = $this->commercePickupSlotInput($request);
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.marketplace.slots.edit', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withInput()->with('commercePickupSlotErrors', $errors);
        }

        $ok = false;
        try {
            MarketplacePickupSlotService::update($slot, $data);
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.slots.edit', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'slot-saved' : 'slot-save-failed',
        ]);
    }

    /** Delete one of the seller's own pickup slots. */
    public function commerceDeletePickupSlot(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $slot = $this->commerceOwnedPickupSlotOr404($id, $userId);

        $ok = false;
        try {
            MarketplacePickupSlotService::delete($slot);
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.slots', [
            'tenantSlug' => $tenantSlug,
            'status' => $ok ? 'slot-deleted' : 'slot-delete-failed',
        ]);
    }

    // =================================================================
    //  Courses — instructor grading queue (manual review of quiz attempts)
    //  Mirrors CourseQuizController gradingQueue/gradeAttempt: courses
    //  feature + course ownership, then the SAME CourseQuizService
    //  (pendingReviewForCourse / gradeAttempt). No-JS grading form.
    // =================================================================

    /** List the attempts awaiting manual review for one of the member's own courses. */
    public function commerceCourseGrading(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $course = $this->commerceOwnedCourseOr404($id, $userId);

        $attempts = [];
        try {
            $attempts = \App\Services\CourseQuizService::pendingReviewForCourse($id);
            $attempts = is_array($attempts) ? array_map(static fn ($a) => is_array($a) ? $a : (array) $a, $attempts) : [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-course-grading', [
            'title' => __('govuk_alpha_commerce.grading.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'courseId' => $id,
            'courseTitle' => (string) $course->title,
            'attempts' => $attempts,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Apply a manual grade to a pending attempt belonging to the member's own course. */
    public function commerceGradeAttempt(Request $request, string $tenantSlug, int $id, int $attemptId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('courses'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Owner-gate the course (cross-tenant → 404, non-owner → 403).
        $this->commerceOwnedCourseOr404($id, $userId);

        // The attempt MUST belong to a quiz of THIS course, else 404.
        $attemptCourseId = null;
        try {
            $attemptCourseId = \App\Services\CourseQuizService::courseIdForAttempt($attemptId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($attemptCourseId === null || (int) $attemptCourseId !== $id, 404);

        $scoreRaw = self::asStr($request->input('score_percent'));
        $score = is_numeric($scoreRaw) ? max(0.0, min(100.0, (float) $scoreRaw)) : 0.0;
        $passed = filter_var($request->input('passed'), FILTER_VALIDATE_BOOLEAN);
        $feedback = trim(self::asStr($request->input('feedback')));
        $feedback = $feedback === '' ? null : mb_substr($feedback, 0, 5000);

        $ok = false;
        try {
            $graded = \App\Services\CourseQuizService::gradeAttempt($attemptId, $score, $passed, $feedback, $userId);
            $ok = $graded !== null;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.courses.instructor.grading', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'graded' : 'grade-failed',
        ]);
    }

    // =================================================================
    //  Marketplace — merchant onboarding KYC wizard (single accessible page)
    //  Mirrors MerchantOnboardingController: marketplace feature + auth, then
    //  MerchantOnboardingService saveStep1/saveStep2/completeOnboarding.
    // =================================================================

    /** Show the merchant (business seller) onboarding form. */
    public function commerceMerchantOnboarding(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $statusData = ['has_profile' => false, 'onboarding_completed' => false, 'profile' => null];
        try {
            $statusData = MerchantOnboardingService::getOnboardingStatus($tenantId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        $profile = is_array($statusData['profile'] ?? null) ? $statusData['profile'] : [];

        return $this->view('accessible-frontend::commerce-merchant-onboarding', [
            'title' => __('govuk_alpha_commerce.onboarding.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'completed' => (bool) ($statusData['onboarding_completed'] ?? false),
            'sellerTypes' => self::COMMERCE_SELLER_TYPES,
            'profile' => [
                'business_name' => (string) ($profile['business_name'] ?? ''),
                'display_name' => (string) ($profile['display_name'] ?? ''),
                'bio' => (string) ($profile['bio'] ?? ''),
                'seller_type' => (string) ($profile['seller_type'] ?? 'business'),
                'business_registration' => (string) ($profile['business_registration'] ?? ''),
            ],
            'address' => $this->commerceDecodeAddress($profile['business_address'] ?? null),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Persist the merchant onboarding form (identity + location), then finalise. */
    public function commerceStoreMerchantOnboarding(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $sellerType = $this->allowed(self::asStr($request->input('seller_type')), self::COMMERCE_SELLER_TYPES, 'business');
        $businessName = trim(self::asStr($request->input('business_name')));
        $displayName = trim(self::asStr($request->input('display_name')));

        $errors = [];
        if ($sellerType === 'business' && $businessName === '') {
            $errors[] = __('govuk_alpha_commerce.onboarding.error_business_name');
        }
        if ($displayName === '') {
            $errors[] = __('govuk_alpha_commerce.onboarding.error_display_name');
        }
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.marketplace.onboarding', ['tenantSlug' => $tenantSlug])
                ->withInput()->with('commerceOnboardingErrors', $errors);
        }

        $step1 = array_filter([
            'business_name' => mb_substr($businessName, 0, 200),
            'display_name' => mb_substr($displayName, 0, 200),
            'bio' => mb_substr(trim(self::asStr($request->input('bio'))), 0, 2000),
            'seller_type' => $sellerType,
            'business_registration' => mb_substr(trim(self::asStr($request->input('business_registration'))), 0, 120),
        ], static fn ($v) => $v !== '');

        $address = [
            'street' => mb_substr(trim(self::asStr($request->input('address_street'))), 0, 200),
            'city' => mb_substr(trim(self::asStr($request->input('address_city'))), 0, 120),
            'postal_code' => mb_substr(trim(self::asStr($request->input('address_postal_code'))), 0, 40),
            'country' => mb_substr(trim(self::asStr($request->input('address_country'))), 0, 120),
        ];
        $address = array_filter($address, static fn ($v) => $v !== '');

        $status = 'onboarding-failed';
        try {
            MerchantOnboardingService::saveStep1($tenantId, $userId, $step1);
            if (!empty($address)) {
                MerchantOnboardingService::saveStep2($tenantId, $userId, ['business_address' => $address]);
            }
            MerchantOnboardingService::completeOnboarding($tenantId, $userId);
            $status = 'onboarding-complete';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.onboarding', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    // =================================================================
    //  Seller — merchant coupon management (create / edit / delete)
    //  Mirrors MerchantCouponSellerController: marketplace + merchant_coupons
    //  features, seller profile required, then MerchantCouponService.
    // =================================================================

    /** List the seller's own coupons. */
    public function commerceSellerCoupons(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $this->commerceCouponFeatureGate();
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $profile = $this->commerceSellerProfileOr403($userId);

        $coupons = [];
        try {
            $rows = MerchantCouponService::listForMerchant((int) $profile->id);
            $coupons = array_map([MerchantCouponService::class, 'format'], $rows);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-seller-coupons', [
            'title' => __('govuk_alpha_commerce.coupons.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'coupons' => $coupons,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Show the "create a coupon" form. */
    public function commerceCreateCouponForm(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $this->commerceCouponFeatureGate();
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        $this->commerceSellerProfileOr403($userId);

        return $this->view('accessible-frontend::commerce-coupon-form', $this->commerceCouponFormData(
            $tenantSlug,
            'create',
            null,
            route('govuk-alpha.marketplace.coupons.store', ['tenantSlug' => $tenantSlug]),
        ));
    }

    /** Persist a new coupon. */
    public function commerceStoreCoupon(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $this->commerceCouponFeatureGate();
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        $profile = $this->commerceSellerProfileOr403($userId);

        [$data, $errors] = $this->commerceCouponInput($request);
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.marketplace.coupons.create', ['tenantSlug' => $tenantSlug])
                ->withInput()->with('commerceCouponErrors', $errors);
        }

        try {
            MerchantCouponService::issueCoupon((int) $profile->id, $data);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('govuk-alpha.marketplace.coupons.create', ['tenantSlug' => $tenantSlug])
                ->withInput()->with('commerceCouponErrors', [$e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.marketplace.coupons.create', ['tenantSlug' => $tenantSlug])
                ->withInput()->with('commerceCouponErrors', [__('govuk_alpha_commerce.coupons.error_create')]);
        }

        return redirect()->route('govuk-alpha.marketplace.coupons', ['tenantSlug' => $tenantSlug, 'status' => 'coupon-created']);
    }

    /** Show the edit form for one of the seller's own coupons. */
    public function commerceEditCouponForm(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $this->commerceCouponFeatureGate();
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        $profile = $this->commerceSellerProfileOr403($userId);
        $coupon = $this->commerceOwnedCouponOr404($id, (int) $profile->id);

        return $this->view('accessible-frontend::commerce-coupon-form', $this->commerceCouponFormData(
            $tenantSlug,
            'edit',
            $coupon,
            route('govuk-alpha.marketplace.coupons.update', ['tenantSlug' => $tenantSlug, 'id' => $id]),
            self::asStr($request->query('status')) ?: null,
        ));
    }

    /** Persist edits to one of the seller's own coupons. */
    public function commerceUpdateCoupon(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $this->commerceCouponFeatureGate();
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        $profile = $this->commerceSellerProfileOr403($userId);
        $coupon = $this->commerceOwnedCouponOr404($id, (int) $profile->id);

        [$data, $errors] = $this->commerceCouponInput($request);
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.marketplace.coupons.edit', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withInput()->with('commerceCouponErrors', $errors);
        }

        try {
            MerchantCouponService::updateCoupon($coupon, $data);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('govuk-alpha.marketplace.coupons.edit', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withInput()->with('commerceCouponErrors', [$e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.marketplace.coupons.edit', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'coupon-save-failed']);
        }

        return redirect()->route('govuk-alpha.marketplace.coupons.edit', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'coupon-saved']);
    }

    /** Delete one of the seller's own coupons. */
    public function commerceDeleteCoupon(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $this->commerceCouponFeatureGate();
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        $profile = $this->commerceSellerProfileOr403($userId);
        $coupon = $this->commerceOwnedCouponOr404($id, (int) $profile->id);

        $status = 'coupon-delete-failed';
        try {
            $coupon->delete();
            $status = 'coupon-deleted';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.marketplace.coupons', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    // =================================================================
    //  Podcasts — studio: show create/edit + episode management
    //  Mirrors PodcastController: podcasts feature, member-show-creation
    //  policy, then PodcastService. Owner-or-404/403 on every mutation.
    // =================================================================

    /** Studio dashboard: the member's own shows. */
    public function commercePodcastStudio(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('podcasts'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->commerceCanAuthorPodcasts(), 403);

        $shows = [];
        try {
            $rows = PodcastService::authoredBy($userId);
            $shows = is_array($rows) ? array_map(static fn ($s) => is_array($s) ? $s : (array) $s, $rows) : [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::commerce-podcast-studio', [
            'title' => __('govuk_alpha_commerce.podcast_studio.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'shows' => $shows,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Show the "create a podcast" form. */
    public function commerceCreatePodcastForm(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('podcasts'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->commerceCanAuthorPodcasts(), 403);

        return $this->view('accessible-frontend::commerce-podcast-form', $this->commercePodcastFormData(
            $tenantSlug,
            'create',
            null,
            route('govuk-alpha.podcasts.studio.store', ['tenantSlug' => $tenantSlug]),
        ));
    }

    /** Persist a new (draft) show, then redirect to the manage page. */
    public function commerceStorePodcast(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('podcasts'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->commerceCanAuthorPodcasts(), 403);

        [$data, $errors] = $this->commercePodcastShowInput($request);
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.podcasts.studio.create', ['tenantSlug' => $tenantSlug])
                ->withInput()->with('commercePodcastErrors', $errors);
        }

        $newId = 0;
        try {
            $show = PodcastService::createShow($userId, $data);
            $newId = (int) $show->id;
        } catch (\Throwable $e) {
            report($e);
        }

        if ($newId > 0) {
            return redirect()->route('govuk-alpha.podcasts.studio.manage', ['tenantSlug' => $tenantSlug, 'id' => $newId, 'status' => 'show-created']);
        }

        return redirect()->route('govuk-alpha.podcasts.studio.create', ['tenantSlug' => $tenantSlug])
            ->withInput()->with('commercePodcastErrors', [__('govuk_alpha_commerce.podcast_studio.error_create')]);
    }

    /** Manage a show: edit details, manage episodes, publish. */
    public function commercePodcastManage(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('podcasts'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->commerceCanAuthorPodcasts(), 403);

        $show = $this->commerceOwnedShowOr404($id, $userId);

        $episodes = [];
        try {
            foreach ($show->episodes()->orderByDesc('id')->get() as $ep) {
                $episodes[] = [
                    'id' => (int) $ep->id,
                    'title' => (string) ($ep->title ?? ''),
                    'status' => (string) ($ep->status ?? 'draft'),
                    'episode_number' => $ep->episode_number,
                    'season_number' => $ep->season_number,
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $data = $this->commercePodcastFormData(
            $tenantSlug,
            'edit',
            $show,
            route('govuk-alpha.podcasts.studio.update', ['tenantSlug' => $tenantSlug, 'id' => $id]),
            self::asStr($request->query('status')) ?: null,
        );
        $data['episodes'] = $episodes;
        $data['episodeStoreAction'] = route('govuk-alpha.podcasts.studio.episodes.store', ['tenantSlug' => $tenantSlug, 'id' => $id]);

        return $this->view('accessible-frontend::commerce-podcast-manage', $data);
    }

    /** Persist edits to one of the member's own shows. */
    public function commerceUpdatePodcast(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('podcasts'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->commerceCanAuthorPodcasts(), 403);

        $show = $this->commerceOwnedShowOr404($id, $userId);

        [$data, $errors] = $this->commercePodcastShowInput($request);
        if (!empty($errors)) {
            return redirect()->route('govuk-alpha.podcasts.studio.manage', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withInput()->with('commercePodcastErrors', $errors);
        }

        $status = 'show-save-failed';
        try {
            PodcastService::updateShow($show, $data);
            $status = 'show-saved';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.podcasts.studio.manage', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]);
    }

    /** Publish one of the member's own shows. */
    public function commercePublishPodcast(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('podcasts'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->commerceCanAuthorPodcasts(), 403);

        $show = $this->commerceOwnedShowOr404($id, $userId);

        $status = 'show-publish-failed';
        try {
            $updated = PodcastService::publishShow($show);
            $status = ($updated->moderation_status ?? '') === 'approved' ? 'show-published' : 'show-pending-review';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.podcasts.studio.manage', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]);
    }

    /** Delete one of the member's own shows (with all episodes). */
    public function commerceDeletePodcast(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('podcasts'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->commerceCanAuthorPodcasts(), 403);

        $show = $this->commerceOwnedShowOr404($id, $userId);

        $status = 'show-delete-failed';
        try {
            PodcastService::deleteShow($show);
            $status = 'show-deleted';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.podcasts.studio', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }

    /** Add an episode (audio file or audio URL) to one of the member's own shows. */
    public function commerceStorePodcastEpisode(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('podcasts'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->commerceCanAuthorPodcasts(), 403);

        $show = $this->commerceOwnedShowOr404($id, $userId);

        $title = trim(self::asStr($request->input('episode_title')));
        $audioUrl = trim(self::asStr($request->input('audio_url')));
        $audioFile = $request->file('audio');
        if ($title === '') {
            return redirect()->route('govuk-alpha.podcasts.studio.manage', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'episode-title-missing']);
        }
        if ($audioUrl === '' && $audioFile === null) {
            return redirect()->route('govuk-alpha.podcasts.studio.manage', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => 'episode-audio-missing']);
        }

        $data = [
            'title' => mb_substr($title, 0, 200),
            'summary' => mb_substr(trim(self::asStr($request->input('episode_summary'))), 0, 600),
            'description' => mb_substr(trim(self::asStr($request->input('episode_description'))), 0, 20000),
        ];
        if ($audioUrl !== '') {
            $data['audio_url'] = $audioUrl;
        }
        $episodeNumber = self::asStr($request->input('episode_number'));
        if ($episodeNumber !== '' && is_numeric($episodeNumber)) {
            $data['episode_number'] = max(0, (int) $episodeNumber);
        }

        $status = 'episode-failed';
        try {
            PodcastService::createEpisode($show, $userId, $data, $audioFile);
            $status = 'episode-added';
        } catch (\InvalidArgumentException $e) {
            $status = 'episode-invalid-audio';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.podcasts.studio.manage', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]);
    }

    /** Publish an episode that belongs to one of the member's own shows. */
    public function commercePublishPodcastEpisode(Request $request, string $tenantSlug, int $id, int $episodeId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('podcasts'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->commerceCanAuthorPodcasts(), 403);

        $show = $this->commerceOwnedShowOr404($id, $userId);
        $episode = $this->commerceEpisodeInShowOr404($episodeId, (int) $show->id);

        $status = 'episode-publish-failed';
        try {
            PodcastService::publishEpisode($episode);
            $status = 'episode-published';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.podcasts.studio.manage', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]);
    }

    /** Delete an episode that belongs to one of the member's own shows. */
    public function commerceDeletePodcastEpisode(Request $request, string $tenantSlug, int $id, int $episodeId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('podcasts'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        abort_unless($this->commerceCanAuthorPodcasts(), 403);

        $show = $this->commerceOwnedShowOr404($id, $userId);
        $episode = $this->commerceEpisodeInShowOr404($episodeId, (int) $show->id);

        $status = 'episode-delete-failed';
        try {
            PodcastService::deleteEpisode($episode);
            $status = 'episode-deleted';
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.podcasts.studio.manage', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]);
    }

    // =================================================================
    //  Commerce private helpers (prefixed; do not collide with siblings)
    // =================================================================

    /** Marketplace seller types — mirrors the onboarding enum. */
    private const COMMERCE_SELLER_TYPES = ['private', 'business'];

    /** Coupon discount types — mirrors the enum. */
    private const COMMERCE_COUPON_DISCOUNT_TYPES = ['percent', 'fixed', 'bogo'];

    /** Coupon lifecycle statuses — mirrors the enum. */
    private const COMMERCE_COUPON_STATUSES = ['draft', 'active', 'paused', 'expired'];

    /** Lesson content types — mirrors the course_lessons enum. */
    private const COMMERCE_LESSON_CONTENT_TYPES = ['text', 'video', 'pdf', 'embed'];

    /** Redirect helper back to the course edit/builder page with a status flag. */
    private function commerceBuilderRedirect(string $tenantSlug, int $courseId, string $status): RedirectResponse
    {
        return redirect()->route('govuk-alpha.courses.instructor.edit', [
            'tenantSlug' => $tenantSlug,
            'id' => $courseId,
            'status' => $status,
        ]);
    }

    /** Ensure a section belongs to the given course, else 404. */
    private function commerceSectionInCourseOr404(int $sectionId, int $courseId): void
    {
        $exists = \App\Models\CourseSection::where('id', $sectionId)
            ->where('course_id', $courseId)
            ->exists();
        abort_unless($exists, 404);
    }

    /** Ensure a lesson belongs to the given course, else 404. */
    private function commerceLessonInCourseOr404(int $lessonId, int $courseId): void
    {
        $exists = \App\Models\CourseLesson::where('id', $lessonId)
            ->where('course_id', $courseId)
            ->exists();
        abort_unless($exists, 404);
    }

    /**
     * Parse + lightly validate the lesson form input.
     *
     * @return array{0: array<string,mixed>, 1: array<int,string>} [data, errors]
     */
    private function commerceLessonInput(Request $request, int $courseId): array
    {
        $errors = [];
        $title = trim(self::asStr($request->input('lesson_title')));
        if ($title === '') {
            $errors[] = __('govuk_alpha_commerce.builder.error_lesson_title');
        }

        $contentType = $this->allowed(self::asStr($request->input('content_type')), self::COMMERCE_LESSON_CONTENT_TYPES, 'text');
        $data = [
            'title' => mb_substr($title, 0, 200),
            'content_type' => $contentType,
            'body' => mb_substr(trim(self::asStr($request->input('body'))), 0, 50000),
        ];

        $sectionId = (int) self::asStr($request->input('section_id'));
        if ($sectionId > 0) {
            $data['section_id'] = $sectionId;
        }
        if ($contentType === 'video') {
            $data['video_url'] = trim(self::asStr($request->input('media_url')));
        } elseif ($contentType === 'pdf') {
            $data['attachment_url'] = trim(self::asStr($request->input('media_url')));
        } elseif ($contentType === 'embed') {
            $data['embed_url'] = trim(self::asStr($request->input('media_url')));
        }

        return [$data, $errors];
    }

    /** Marketplace + merchant_coupons feature gate (mirrors the seller controller). */
    private function commerceCouponFeatureGate(): void
    {
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        abort_unless(TenantContext::hasFeature('merchant_coupons'), 403);
    }

    /** Fetch the member's seller profile or 403 (must be a seller to manage coupons). */
    private function commerceSellerProfileOr403(int $userId): \App\Models\MarketplaceSellerProfile
    {
        $profile = null;
        try {
            $profile = MarketplaceSellerService::getByUserId($userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($profile === null, 403);
        return $profile;
    }

    /**
     * Fetch one of the member's own pickup slots or abort. The slot is scoped by
     * tenant + the member's seller-profile id, so a cross-tenant or cross-seller
     * id resolves to 404 (same contract as MarketplacePickupSlotController).
     */
    private function commerceOwnedPickupSlotOr404(int $id, int $userId): \App\Models\MarketplacePickupSlot
    {
        $profile = MarketplaceSellerService::getOrCreateProfile($userId);
        $slot = \App\Models\MarketplacePickupSlot::query()
            ->where('id', $id)
            ->where('tenant_id', TenantContext::getId())
            ->where('seller_id', $profile->id)
            ->first();
        abort_if($slot === null, 404);
        return $slot;
    }

    /**
     * Parse + lightly validate the pickup-slot form input. Mirrors the React
     * seller page: start/end datetime, capacity, recurring + active toggles.
     * Recurring is a boolean only (no JSON pattern), so the json_valid CHECK on
     * recurring_pattern is never tripped.
     *
     * @return array{0: array<string,mixed>, 1: array<int,string>} [data, errors]
     */
    private function commercePickupSlotInput(Request $request): array
    {
        $errors = [];
        $start = trim(self::asStr($request->input('slot_start')));
        $end = trim(self::asStr($request->input('slot_end')));

        $startTs = $start !== '' ? strtotime($start) : false;
        $endTs = $end !== '' ? strtotime($end) : false;

        if ($startTs === false) {
            $errors[] = __('govuk_alpha_commerce.slots.error_start');
        }
        if ($endTs === false) {
            $errors[] = __('govuk_alpha_commerce.slots.error_end');
        }
        if ($startTs !== false && $endTs !== false && $endTs <= $startTs) {
            $errors[] = __('govuk_alpha_commerce.slots.error_order');
        }

        $capacityRaw = self::asStr($request->input('capacity'));
        $capacity = is_numeric($capacityRaw) ? (int) $capacityRaw : 1;
        $capacity = max(1, min(1000, $capacity));

        $data = [
            'slot_start' => $startTs !== false ? date('Y-m-d H:i:s', $startTs) : null,
            'slot_end' => $endTs !== false ? date('Y-m-d H:i:s', $endTs) : null,
            'capacity' => $capacity,
            'is_recurring' => filter_var($request->input('is_recurring'), FILTER_VALIDATE_BOOLEAN),
            'is_active' => $request->has('is_active')
                ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)
                : true,
        ];

        return [$data, $errors];
    }

    /**
     * Fetch one of the seller's own coupons or abort. Cross-tenant / wrong-seller
     * resolves to 404 (the query is scoped by tenant + seller profile id).
     */
    private function commerceOwnedCouponOr404(int $id, int $sellerProfileId): \App\Models\MerchantCoupon
    {
        $coupon = \App\Models\MerchantCoupon::where('id', $id)
            ->where('tenant_id', TenantContext::getId())
            ->where('seller_id', $sellerProfileId)
            ->first();
        abort_if($coupon === null, 404);
        return $coupon;
    }

    /**
     * Parse + lightly validate the coupon form input.
     *
     * @return array{0: array<string,mixed>, 1: array<int,string>} [data, errors]
     */
    private function commerceCouponInput(Request $request): array
    {
        $errors = [];
        $title = trim(self::asStr($request->input('title')));
        if ($title === '') {
            $errors[] = __('govuk_alpha_commerce.coupons.error_title');
        }

        $discountType = $this->allowed(self::asStr($request->input('discount_type')), self::COMMERCE_COUPON_DISCOUNT_TYPES, 'percent');
        $discountValueRaw = self::asStr($request->input('discount_value'));
        if ($discountType !== 'bogo' && ($discountValueRaw === '' || !is_numeric($discountValueRaw) || (float) $discountValueRaw <= 0)) {
            $errors[] = __('govuk_alpha_commerce.coupons.error_discount_value');
        }

        $data = [
            'title' => mb_substr($title, 0, 200),
            'description' => mb_substr(trim(self::asStr($request->input('description'))), 0, 2000),
            'discount_type' => $discountType,
            'discount_value' => is_numeric($discountValueRaw) ? max(0, (float) $discountValueRaw) : 0,
            'status' => $this->allowed(self::asStr($request->input('status')), self::COMMERCE_COUPON_STATUSES, 'draft'),
            'applies_to' => 'all_listings',
        ];

        $code = trim(self::asStr($request->input('code')));
        if ($code !== '') {
            $data['code'] = mb_substr($code, 0, 64);
        }
        $minOrder = self::asStr($request->input('min_order_cents'));
        if ($minOrder !== '' && is_numeric($minOrder)) {
            $data['min_order_cents'] = max(0, (int) $minOrder);
        }
        $maxUses = self::asStr($request->input('max_uses'));
        if ($maxUses !== '' && is_numeric($maxUses) && (int) $maxUses > 0) {
            $data['max_uses'] = (int) $maxUses;
        }
        $validUntil = trim(self::asStr($request->input('valid_until')));
        if ($validUntil !== '') {
            $data['valid_until'] = $validUntil;
        }

        return [$data, $errors];
    }

    /** Shared view-data for the create/edit coupon form. */
    private function commerceCouponFormData(string $tenantSlug, string $mode, ?\App\Models\MerchantCoupon $coupon, string $action, ?string $status = null): array
    {
        return [
            'title' => $mode === 'edit'
                ? __('govuk_alpha_commerce.coupons.title_edit')
                : __('govuk_alpha_commerce.coupons.title_create'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'mode' => $mode,
            'formAction' => $action,
            'discountTypes' => self::COMMERCE_COUPON_DISCOUNT_TYPES,
            'statuses' => self::COMMERCE_COUPON_STATUSES,
            'coupon' => $coupon !== null ? [
                'id' => (int) $coupon->id,
                'code' => (string) ($coupon->code ?? ''),
                'title' => (string) ($coupon->title ?? ''),
                'description' => (string) ($coupon->description ?? ''),
                'discount_type' => (string) ($coupon->discount_type ?? 'percent'),
                'discount_value' => $coupon->discount_value,
                'min_order_cents' => $coupon->min_order_cents,
                'max_uses' => $coupon->max_uses,
                'valid_until' => $coupon->valid_until ? $coupon->valid_until->format('Y-m-d') : '',
                'status' => (string) ($coupon->status ?? 'draft'),
            ] : null,
            'status' => $status,
        ];
    }

    /** Decode the JSON business_address column into a flat array for the form. */
    private function commerceDecodeAddress(mixed $raw): array
    {
        $decoded = [];
        if (is_string($raw) && $raw !== '') {
            $tmp = json_decode($raw, true);
            $decoded = is_array($tmp) ? $tmp : [];
        } elseif (is_array($raw)) {
            $decoded = $raw;
        }

        return [
            'street' => (string) ($decoded['street'] ?? ''),
            'city' => (string) ($decoded['city'] ?? ''),
            'postal_code' => (string) ($decoded['postal_code'] ?? ''),
            'country' => (string) ($decoded['country'] ?? ''),
        ];
    }

    /** Respect the tenant's podcast member-show-creation policy. */
    private function commerceCanAuthorPodcasts(): bool
    {
        try {
            return (bool) \App\Services\PodcastConfigurationService::get(
                \App\Services\PodcastConfigurationService::CONFIG_ALLOW_MEMBER_SHOW_CREATION
            );
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * Fetch one of the member's own podcast shows or abort. A cross-tenant id
     * resolves to 404 (defence-in-depth tenant check); a show owned by another
     * member in the same tenant returns 403.
     */
    private function commerceOwnedShowOr404(int $id, int $userId): \App\Models\PodcastShow
    {
        $show = null;
        try {
            $show = PodcastService::findShowById($id);
            if ($show !== null && (int) $show->tenant_id !== TenantContext::getId()) {
                $show = null;
            }
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($show === null, 404);
        abort_unless((int) $show->owner_user_id === $userId, 403);
        return $show;
    }

    /** Ensure an episode belongs to the given show, else 404. */
    private function commerceEpisodeInShowOr404(int $episodeId, int $showId): \App\Models\PodcastEpisode
    {
        $episode = \App\Models\PodcastEpisode::where('id', $episodeId)
            ->where('show_id', $showId)
            ->first();
        abort_if($episode === null, 404);
        return $episode;
    }

    /**
     * Parse + lightly validate the podcast show form input.
     *
     * @return array{0: array<string,mixed>, 1: array<int,string>} [data, errors]
     */
    private function commercePodcastShowInput(Request $request): array
    {
        $errors = [];
        $title = trim(self::asStr($request->input('title')));
        if ($title === '') {
            $errors[] = __('govuk_alpha_commerce.podcast_studio.error_title');
        }

        $data = [
            'title' => mb_substr($title, 0, 200),
            'summary' => mb_substr(trim(self::asStr($request->input('summary'))), 0, 600),
            'description' => mb_substr(trim(self::asStr($request->input('description'))), 0, 20000),
            'category' => mb_substr(trim(self::asStr($request->input('category'))), 0, 120),
            'visibility' => $this->allowed(self::asStr($request->input('visibility')), ['public', 'members', 'private'], 'public'),
        ];

        return [$data, $errors];
    }

    /** Shared view-data for the create/edit podcast show form. */
    private function commercePodcastFormData(string $tenantSlug, string $mode, ?\App\Models\PodcastShow $show, string $action, ?string $status = null): array
    {
        return [
            'title' => $mode === 'edit'
                ? __('govuk_alpha_commerce.podcast_studio.title_edit')
                : __('govuk_alpha_commerce.podcast_studio.title_create'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'mode' => $mode,
            'formAction' => $action,
            'visibilities' => ['public', 'members', 'private'],
            'show' => $show !== null ? [
                'id' => (int) $show->id,
                'title' => (string) ($show->title ?? ''),
                'summary' => (string) ($show->summary ?? ''),
                'description' => (string) ($show->description ?? ''),
                'category' => (string) ($show->category ?? ''),
                'visibility' => (string) ($show->visibility ?? 'public'),
                'status' => (string) ($show->status ?? 'draft'),
                'moderation_status' => (string) ($show->moderation_status ?? 'approved'),
            ] : null,
            'episodes' => [],
            'status' => $status,
        ];
    }

    /** Marketplace report reasons — mirrors the API validation `in:` list. */
    private const COMMERCE_REPORT_REASONS = [
        'counterfeit', 'illegal', 'unsafe', 'misleading', 'discrimination', 'ip_violation', 'other',
    ];

    /** Marketplace listing price types — mirrors the enum. */
    private const COMMERCE_PRICE_TYPES = ['fixed', 'negotiable', 'free', 'contact'];

    /** Marketplace condition values — mirrors the enum. */
    private const COMMERCE_CONDITIONS = ['new', 'like_new', 'good', 'fair', 'poor'];

    /** Marketplace delivery methods — mirrors the enum. */
    private const COMMERCE_DELIVERY_METHODS = ['pickup', 'shipping', 'both'];

    /**
     * Fetch one of the member's own listings or 404. HasTenantScope on the model
     * makes a cross-tenant id resolve to ModelNotFound (→ 404); a different owner
     * in the same tenant returns 403.
     */
    private function commerceOwnedListingOr404(int $id, int $userId): MarketplaceListing
    {
        try {
            $listing = MarketplaceListing::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404);
        }
        abort_unless((int) $listing->user_id === $userId, 403);
        return $listing;
    }

    /** Fetch a purchasable (fixed-price money) listing for buying, or abort. */
    private function commercePurchasableOr404(int $id, int $userId): array
    {
        $item = null;
        try {
            $item = MarketplaceListingService::getById($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($item === null, 404);
        // Cannot buy your own listing.
        abort_if((bool) ($item['is_own'] ?? false), 403);
        // Only money-priced, active, fixed listings are buyable here.
        abort_unless((string) ($item['status'] ?? '') === 'active', 404);
        $priceType = (string) ($item['price_type'] ?? '');
        abort_unless(in_array($priceType, ['fixed'], true) && (float) ($item['price'] ?? 0) > 0, 404);
        return $item;
    }

    /**
     * Fetch an order the member participates in (buyer / seller / either) or abort.
     * HasTenantScope makes cross-tenant ids 404; a non-participant gets 403.
     */
    private function commerceOrderForRoleOr404(int $id, int $userId, string $role): MarketplaceOrder
    {
        try {
            $order = MarketplaceOrder::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404);
        }

        $isBuyer = (int) $order->buyer_id === $userId;
        $isSeller = (int) $order->seller_id === $userId;

        if ($role === 'buyer') {
            abort_unless($isBuyer, 403);
        } elseif ($role === 'seller') {
            abort_unless($isSeller, 403);
        } else {
            abort_unless($isBuyer || $isSeller, 403);
        }

        return $order;
    }

    /**
     * Shared offer action handler. Cross-tenant offer ids 404; the offer service
     * enforces the seller/buyer relationship and notifies the counterparty.
     */
    private function commerceOfferAction(string $tenantSlug, int $id, string $action): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        try {
            $offer = MarketplaceOffer::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404);
        }

        // Authorise: accept/decline are seller-only; withdraw is buyer-only.
        if ($action === 'withdraw') {
            abort_unless((int) $offer->buyer_id === $userId, 403);
        } else {
            abort_unless((int) $offer->seller_id === $userId, 403);
        }

        $status = $action . '-failed';
        try {
            if ($action === 'accept') {
                MarketplaceOfferService::accept($offer, $userId);
                $status = 'accepted';
            } elseif ($action === 'decline') {
                MarketplaceOfferService::decline($offer, $userId);
                $status = 'declined';
            } else {
                MarketplaceOfferService::withdraw($offer, $userId);
                $status = 'withdrawn';
            }
        } catch (\InvalidArgumentException $e) {
            $status = $action . '-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        $tab = $action === 'withdraw' ? 'sent' : 'received';
        return redirect()->route('govuk-alpha.marketplace.offers', ['tenantSlug' => $tenantSlug, 'tab' => $tab, 'status' => $status]);
    }

    /** Build a single lesson payload for the player view. */
    private function commerceLessonPayload(\App\Models\CourseLesson $lesson, array $entry): array
    {
        $video = \App\Services\CourseLessonService::normalizeMediaUrl($lesson->video_url);
        $embed = \App\Services\CourseLessonService::normalizeMediaUrl($lesson->embed_url);
        $attachment = \App\Services\CourseLessonService::normalizeMediaUrl($lesson->attachment_url);

        return [
            'id' => (int) $lesson->id,
            'title' => (string) $lesson->title,
            'content_type' => (string) $lesson->content_type,
            'body' => (string) ($lesson->body ?? ''),
            'video_url' => is_string($video) ? $video : '',
            'embed_url' => is_string($embed) ? $embed : '',
            'attachment_url' => is_string($attachment) ? $attachment : '',
            'is_completed' => (bool) ($entry['is_completed'] ?? false),
        ];
    }

    /**
     * Build the learner-safe quiz payload for a quiz lesson, plus the viewer's
     * attempt state (attempts used/remaining + their latest result). Returns null
     * when the lesson has no quiz attached. Questions never include the correct
     * answers — forLearner() strips them, mirroring the React CoursePlayerPage.
     *
     * @return array<string,mixed>|null
     */
    private function commerceQuizPayload(\App\Models\CourseLesson $lesson, int $userId): ?array
    {
        $quizModel = $lesson->quiz; // HasOne(CourseQuiz, lesson_id)
        if ($quizModel === null) {
            return null;
        }

        $data = \App\Services\CourseQuizService::forLearner((int) $quizModel->id);
        if ($data === null) {
            return null;
        }

        $attemptsUsed = \App\Services\CourseQuizService::attemptsUsed((int) $quizModel->id, $userId);
        $maxAttempts = (int) ($data['max_attempts'] ?? 0);
        $attemptsRemaining = $maxAttempts > 0 ? max(0, $maxAttempts - $attemptsUsed) : null; // null = unlimited

        $last = \App\Models\CourseQuizAttempt::where('quiz_id', (int) $quizModel->id)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();

        $data['attempts_used'] = $attemptsUsed;
        $data['attempts_remaining'] = $attemptsRemaining;
        $data['last_attempt'] = $last ? [
            'score_percent' => (float) $last->score_percent,
            'passed' => (bool) $last->passed,
            'grading_status' => (string) $last->grading_status,
        ] : null;

        return $data;
    }

    /**
     * Parse + lightly validate the listing form input.
     *
     * @return array{0: array<string,mixed>, 1: array<int,string>} [data, errors]
     */
    private function commerceListingInput(Request $request): array
    {
        $errors = [];

        $title = trim(self::asStr($request->input('title')));
        $description = trim(self::asStr($request->input('description')));
        $priceType = $this->allowed(self::asStr($request->input('price_type')), self::COMMERCE_PRICE_TYPES, 'fixed');

        if ($title === '') {
            $errors[] = __('govuk_alpha_commerce.listing_form.error_title');
        }
        if ($description === '') {
            $errors[] = __('govuk_alpha_commerce.listing_form.error_description');
        }

        $price = $request->input('price');
        $timeCredit = $request->input('time_credit_price');

        $data = [
            'title' => mb_substr($title, 0, 200),
            'description' => mb_substr($description, 0, 10000),
            'price_type' => $priceType,
            'status' => 'active',
        ];

        $tagline = trim(self::asStr($request->input('tagline')));
        if ($tagline !== '') {
            $data['tagline'] = mb_substr($tagline, 0, 300);
        }

        if ($priceType === 'free') {
            $data['price'] = null;
            $data['time_credit_price'] = null;
        } else {
            if ($price !== null && self::asStr($price) !== '' && is_numeric(self::asStr($price))) {
                $data['price'] = max(0, (float) self::asStr($price));
                $data['price_currency'] = mb_substr(strtoupper(trim(self::asStr($request->input('price_currency', 'EUR')))) ?: 'EUR', 0, 3);
            }
            if ($timeCredit !== null && self::asStr($timeCredit) !== '' && is_numeric(self::asStr($timeCredit))) {
                $data['time_credit_price'] = max(0, (float) self::asStr($timeCredit));
            }
            // A priced listing must carry a money price or a time-credit price.
            if ($priceType === 'fixed' && empty($data['price']) && empty($data['time_credit_price'])) {
                $errors[] = __('govuk_alpha_commerce.listing_form.error_price');
            }
        }

        $condition = $this->allowed(self::asStr($request->input('condition')), self::COMMERCE_CONDITIONS, '');
        if ($condition !== '') {
            $data['condition'] = $condition;
        }
        $delivery = $this->allowed(self::asStr($request->input('delivery_method')), self::COMMERCE_DELIVERY_METHODS, 'pickup');
        $data['delivery_method'] = $delivery;

        $categoryId = (int) self::asStr($request->input('category_id'));
        if ($categoryId > 0) {
            $data['category_id'] = $categoryId;
        }

        $location = trim(self::asStr($request->input('location')));
        if ($location !== '') {
            $data['location'] = mb_substr($location, 0, 255);
        }

        $quantity = (int) self::asStr($request->input('quantity', '1'));
        if ($quantity >= 1) {
            $data['quantity'] = $quantity;
        }

        return [$data, $errors];
    }

    /** Shared view-data for the create/edit listing form. */
    private function commerceListingFormData(string $tenantSlug, string $mode, ?MarketplaceListing $listing, string $action, ?string $status): array
    {
        $categories = [];
        try {
            $rawCats = MarketplaceListingService::getCategories();
            $categories = is_array($rawCats) ? array_map(static fn ($c) => is_array($c) ? $c : (array) $c, $rawCats) : [];
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'title' => $mode === 'edit'
                ? __('govuk_alpha_commerce.listing_form.title_edit')
                : __('govuk_alpha_commerce.listing_form.title_create'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'commerceActiveTab' => 'mine',
            'mode' => $mode,
            'formAction' => $action,
            'categories' => $categories,
            'priceTypes' => self::COMMERCE_PRICE_TYPES,
            'conditions' => self::COMMERCE_CONDITIONS,
            'deliveryMethods' => self::COMMERCE_DELIVERY_METHODS,
            'listing' => $listing !== null ? [
                'id' => (int) $listing->id,
                'title' => (string) $listing->title,
                'description' => (string) $listing->description,
                'tagline' => (string) ($listing->tagline ?? ''),
                'price' => $listing->price,
                'price_currency' => (string) ($listing->price_currency ?? 'EUR'),
                'price_type' => (string) ($listing->price_type ?? 'fixed'),
                'time_credit_price' => $listing->time_credit_price,
                'condition' => (string) ($listing->condition ?? ''),
                'delivery_method' => (string) ($listing->delivery_method ?? 'pickup'),
                'category_id' => $listing->category_id,
                'location' => (string) ($listing->location ?? ''),
                'quantity' => (int) ($listing->quantity ?? 1),
            ] : null,
            'status' => $status,
        ];
    }

    /** Course level enum — mirrors CourseService::LEVELS. */
    private const COMMERCE_COURSE_LEVELS = ['beginner', 'intermediate', 'advanced'];

    /** Course visibility — the accessible UI offers the same two the React builder does. */
    private const COMMERCE_COURSE_VISIBILITIES = ['members', 'public'];

    /** Course enrolment type enum — mirrors CourseService::ENROLLMENT_TYPES. */
    private const COMMERCE_COURSE_ENROLLMENT_TYPES = ['self_paced', 'cohort'];

    /**
     * Whether the member may author a course. Mirrors
     * InteractsWithCourses::requireCourseAuthor: open to any member by default;
     * a tenant may restrict to granted instructors via the
     * `courses.allow_member_authoring` setting.
     */
    private function commerceCanAuthorCourses(int $userId): bool
    {
        $allowMembers = filter_var(
            TenantContext::getSetting('courses.allow_member_authoring', true),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($allowMembers) {
            return true;
        }

        try {
            return CourseInstructorService::isInstructor($userId);
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * Fetch one of the member's own courses or abort. Course has HasTenantScope,
     * so a cross-tenant id resolves to null (→ 404); a course authored by another
     * member in the same tenant returns 403 (owner-only management).
     */
    private function commerceOwnedCourseOr404(int $id, int $userId): Course
    {
        $course = null;
        try {
            $course = CourseService::findById($id);
            if ($course !== null && (int) $course->tenant_id !== TenantContext::getId()) {
                $course = null;
            }
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($course === null, 404);
        abort_unless((int) $course->author_user_id === $userId, 403);
        return $course;
    }

    /**
     * Parse + lightly validate the course form input.
     *
     * @return array{0: array<string,mixed>, 1: array<int,string>} [data, errors]
     */
    private function commerceCourseInput(Request $request): array
    {
        $errors = [];

        $title = trim(self::asStr($request->input('title')));
        if ($title === '') {
            $errors[] = __('govuk_alpha_commerce.instructor.error_title');
        }

        $data = [
            'title' => mb_substr($title, 0, 200),
            'summary' => mb_substr(trim(self::asStr($request->input('summary'))), 0, 500),
            'description' => mb_substr(trim(self::asStr($request->input('description'))), 0, 20000),
            'level' => $this->allowed(self::asStr($request->input('level')), self::COMMERCE_COURSE_LEVELS, 'beginner'),
            'visibility' => $this->allowed(self::asStr($request->input('visibility')), self::COMMERCE_COURSE_VISIBILITIES, 'members'),
            'enrollment_type' => $this->allowed(self::asStr($request->input('enrollment_type')), self::COMMERCE_COURSE_ENROLLMENT_TYPES, 'self_paced'),
        ];

        $creditCost = self::asStr($request->input('credit_cost'));
        if ($creditCost !== '' && is_numeric($creditCost)) {
            $data['credit_cost'] = max(0, (float) $creditCost);
        }

        $categoryId = (int) self::asStr($request->input('category_id'));
        $data['category_id'] = $categoryId > 0 ? $categoryId : null;

        return [$data, $errors];
    }

    /** Shared view-data for the create/edit course form. */
    private function commerceCourseFormData(string $tenantSlug, string $mode, ?Course $course, string $action, ?string $status = null): array
    {
        $categories = [];
        try {
            $rawCats = \App\Services\CourseCategoryService::all();
            $categories = is_array($rawCats) ? array_map(static fn ($c) => is_array($c) ? $c : (array) $c, $rawCats) : [];
        } catch (\Throwable $e) {
            report($e);
        }

        // Builder structure (edit mode only): sections + the lessons in each,
        // plus any lessons not assigned to a section.
        $builderSections = [];
        $builderUnsectioned = [];
        if ($mode === 'edit' && $course !== null) {
            try {
                $sections = \App\Models\CourseSection::where('course_id', $course->id)
                    ->orderBy('position')->orderBy('id')
                    ->get(['id', 'title']);
                $lessons = \App\Models\CourseLesson::where('course_id', $course->id)
                    ->orderBy('position')->orderBy('id')
                    ->get(['id', 'section_id', 'title', 'content_type']);

                $bySection = [];
                foreach ($lessons as $lesson) {
                    $sid = $lesson->section_id !== null ? (int) $lesson->section_id : 0;
                    $bySection[$sid][] = [
                        'id' => (int) $lesson->id,
                        'title' => (string) $lesson->title,
                        'content_type' => (string) ($lesson->content_type ?? 'text'),
                    ];
                }
                foreach ($sections as $section) {
                    $sid = (int) $section->id;
                    $builderSections[] = [
                        'id' => $sid,
                        'title' => (string) $section->title,
                        'lessons' => $bySection[$sid] ?? [],
                    ];
                }
                $builderUnsectioned = $bySection[0] ?? [];
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [
            'title' => $mode === 'edit'
                ? __('govuk_alpha_commerce.instructor.title_edit')
                : __('govuk_alpha_commerce.instructor.title_create'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'mode' => $mode,
            'formAction' => $action,
            'categories' => $categories,
            'levels' => self::COMMERCE_COURSE_LEVELS,
            'visibilities' => self::COMMERCE_COURSE_VISIBILITIES,
            'enrollmentTypes' => self::COMMERCE_COURSE_ENROLLMENT_TYPES,
            'course' => $course !== null ? [
                'id' => (int) $course->id,
                'title' => (string) $course->title,
                'summary' => (string) ($course->summary ?? ''),
                'description' => (string) ($course->description ?? ''),
                'level' => (string) ($course->level ?? 'beginner'),
                'visibility' => (string) ($course->visibility ?? 'members'),
                'enrollment_type' => (string) ($course->enrollment_type ?? 'self_paced'),
                'credit_cost' => $course->credit_cost,
                'category_id' => $course->category_id,
                'status' => (string) ($course->status ?? 'draft'),
                'moderation_status' => (string) ($course->moderation_status ?? 'pending'),
            ] : null,
            'builderSections' => $builderSections,
            'builderUnsectioned' => $builderUnsectioned,
            'contentTypes' => self::COMMERCE_LESSON_CONTENT_TYPES,
            'status' => $status,
        ];
    }

    /**
     * Marketplace advanced search — faceted filters (condition, price range,
     * delivery method, seller type, posted-within, sort). All of these are
     * already honoured by MarketplaceListingService::getAll; this page just
     * exposes them in a no-JS GOV.UK form. Mirrors React's advanced search.
     */
    public function commerceMarketplaceAdvancedSearch(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('marketplace'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $q = trim(self::asStr($request->query('q')));
        $categoryId = (int) self::asStr($request->query('category_id'));
        $priceMinRaw = self::asStr($request->query('price_min'));
        $priceMaxRaw = self::asStr($request->query('price_max'));
        $priceMin = $priceMinRaw !== '' ? max(0.0, (float) $priceMinRaw) : null;
        $priceMax = $priceMaxRaw !== '' ? max(0.0, (float) $priceMaxRaw) : null;

        // Condition: multi-select checkbox group, whitelisted.
        $allowedConditions = ['new', 'like_new', 'good', 'fair', 'poor'];
        $conditions = array_values(array_intersect(
            (array) $request->query('condition', []),
            $allowedConditions
        ));

        $sellerType = $this->allowed(self::asStr($request->query('seller_type')), ['private', 'business'], '');
        $deliveryMethod = $this->allowed(self::asStr($request->query('delivery_method')), ['pickup', 'shipping', 'both', 'community_delivery'], '');
        $postedWithinRaw = self::asStr($request->query('posted_within'));
        $postedWithin = in_array($postedWithinRaw, ['1', '7', '30', '90'], true) ? (int) $postedWithinRaw : null;
        $sort = $this->allowed(self::asStr($request->query('sort')), ['newest', 'price_asc', 'price_desc', 'popular'], 'newest');

        $filters = ['limit' => 30, 'current_user_id' => $userId];
        if ($q !== '') {
            $filters['search'] = $q;
        }
        if ($categoryId > 0) {
            $filters['category_id'] = $categoryId;
        }
        if ($priceMin !== null) {
            $filters['price_min'] = $priceMin;
        }
        if ($priceMax !== null) {
            $filters['price_max'] = $priceMax;
        }
        if (! empty($conditions)) {
            $filters['condition'] = $conditions;
        }
        if ($sellerType !== '') {
            $filters['seller_type'] = $sellerType;
        }
        if ($deliveryMethod !== '') {
            $filters['delivery_method'] = $deliveryMethod;
        }
        if ($postedWithin !== null) {
            $filters['posted_within'] = $postedWithin;
        }
        if ($sort !== 'newest') {
            $filters['sort'] = $sort;
        }

        $items = [];
        $categories = [];
        try {
            $items = \App\Services\MarketplaceListingService::getAll($filters)['items'] ?? [];
            $items = array_map(static fn ($i) => is_array($i) ? $i : (array) $i, $items);
            $rawCats = \App\Services\MarketplaceListingService::getCategories();
            $categories = is_array($rawCats) ? array_map(static fn ($c) => is_array($c) ? $c : (array) $c, $rawCats) : [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::marketplace-advanced-search', [
            'title' => __('govuk_alpha_commerce.marketplace_advanced.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'listings' => is_array($items) ? $items : [],
            'marketplaceQuery' => $q,
            'categories' => $categories,
            'marketplaceCategoryId' => $categoryId > 0 ? $categoryId : null,
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
            'selectedConditions' => $conditions,
            'sellerType' => $sellerType,
            'deliveryMethod' => $deliveryMethod,
            'postedWithin' => $postedWithin,
            'sort' => $sort,
        ]);
    }
}
