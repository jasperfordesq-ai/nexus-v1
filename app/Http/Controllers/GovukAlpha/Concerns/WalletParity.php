<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wallet — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr) AND its private wallet helpers
 * ($this->messageUserSearch, $this->walletRecipientById). New method names MUST
 * be module-prefixed and unique across AlphaController and every sibling trait.
 * Resolve services via app(SomeService::class) rather than the constructor.
 *
 * The core /wallet page (AlphaController::wallet) already covers the balance,
 * earned/spent stats, the community-fund display, transfer, donate-to-fund,
 * history with filter tabs, pagination and CSV export. This companion page
 * delivers the React-only affordances the core blade does not surface:
 *   - walletManage: GET /wallet/manage — a single "manage credits" hub that
 *     mirrors react-frontend/src/pages/wallet/WalletPage.tsx more closely than
 *     the core page:
 *       * the full 3-up stat grid INCLUDING the Pending tile
 *         (pending_incoming + pending_outgoing) — WalletPage.tsx:350-372;
 *       * a live "pending in" badge on the balance card — WalletPage.tsx:298-307;
 *       * a transfer form that pre-fills the recipient from ?to=<userId>
 *         (no-JS parity for WalletPage.tsx:61-129 URL-param auto-open);
 *       * a donate form with a recipient-type toggle (community fund OR a
 *         searched member) — DonateModal.tsx:36-39,102-170. The member branch
 *         posts to the SAME AlphaController::donateCredits handler with
 *         target=user, which already calls CreditDonationService::donateToMember.
 *
 * Money/auth/notification logic is never reimplemented here: the forms POST to
 * the existing govuk-alpha.wallet.transfer / govuk-alpha.wallet.donate handlers,
 * which call the canonical WalletService / CreditDonationService. This GET-only
 * controller method just assembles the same read-model the core page uses
 * (WalletService::getBalance + CommunityFundService::getBalance) and the same
 * tenant-scoped, privacy-respecting recipient search.
 */
trait WalletParity
{
    /**
     * GET /wallet/manage — enhanced "manage credits" hub.
     *
     * Read-only assembly; all mutations are delegated to the existing wallet
     * transfer/donate POST handlers. Auth-gated and module-gated exactly like
     * the core wallet page.
     */
    public function walletManage(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('wallet'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Same balance read-model the React page consumes (/v2/wallet/balance).
        $wallet = null;
        try {
            $wallet = app(\App\Services\WalletService::class)->getBalance($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        // Community-fund balance (read-only) — one of the two donate targets.
        $fund = null;
        try {
            $fund = \App\Services\CommunityFundService::getBalance();
        } catch (\Throwable $e) {
            report($e);
        }

        // Recipient resolution mirrors the core wallet page: a picked id (JS
        // autocomplete, or the ?to= deep link) resolves to exactly that one
        // member; otherwise the no-JS text search is used. Both honour the same
        // tenant + active + search-opt-out filters via the controller helpers.
        $recipientId = (int) ($request->query('to') ?: $request->query('recipient_id'));
        $recipientQuery = trim(self::asStr($request->query('recipient_q')));
        if ($recipientId > 0) {
            $recipientResults = $this->walletRecipientById($recipientId, $userId);
        } elseif ($recipientQuery !== '') {
            $recipientResults = $this->messageUserSearch($recipientQuery, $userId);
        } else {
            $recipientResults = [];
        }

        // Which panel the donate toggle should default to. The member branch is
        // only meaningful when a recipient is in scope.
        $donateTarget = $this->allowed(self::asStr($request->query('donate_target')), ['community_fund', 'user'], 'community_fund');

        return $this->view('accessible-frontend::wallet-manage', [
            'title' => __('govuk_alpha_wallet.manage.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'wallet',
            'wallet' => $wallet,
            'fund' => $fund,
            'recipientQuery' => $recipientQuery,
            'recipientResults' => $recipientResults,
            'prefillRecipientId' => $recipientId > 0 ? $recipientId : null,
            'donateTarget' => $donateTarget,
            'status' => self::asStr($request->query('status')) ?: null,
            'transferError' => self::asStr($request->query('error')) ?: null,
            'donateError' => self::asStr($request->query('donate_error')) ?: null,
        ]);
    }
}
