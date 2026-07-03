<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Pelago\Emogrifier\CssInliner;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;

/**
 * Email CSS inliner — the last step of newsletter rendering.
 *
 * Many email clients (Gmail clipping contexts, Outlook's Word engine, older
 * mobile clients) ignore <style> blocks, so rules must be inlined onto each
 * element. pelago/emogrifier:
 *  - inlines <style> rules as style="" attributes,
 *  - PRESERVES @media rules in a <style> block (responsive + dark-mode survive),
 *  - leaves already-inline styles alone (safe on pasted/MJML-exported HTML).
 *
 * CssToAttributeConverter additionally promotes width/height/align/bgcolor to
 * legacy HTML attributes — the only styling Outlook's Word engine reliably
 * honours on tables.
 *
 * FAIL-OPEN by design: any parser exception returns the original HTML so a
 * malformed paste can never block a send — worst case the email goes out
 * without inlining, exactly as it would have before this feature existed.
 */
class EmailCssInliner
{
    /**
     * Inputs larger than this are returned untouched — the inliner's DOM pass
     * gets slow on pathological documents and email bodies should never be
     * anywhere near this size anyway.
     */
    private const MAX_BYTES = 1024 * 1024;

    public static function inline(string $html): string
    {
        if (trim($html) === '' || strlen($html) > self::MAX_BYTES) {
            return $html;
        }

        try {
            $inliner = CssInliner::fromHtml($html)->inlineCss();

            return CssToAttributeConverter::fromDomDocument($inliner->getDomDocument())
                ->convertCssToVisualAttributes()
                ->render();
        } catch (\Throwable $e) {
            Log::warning('EmailCssInliner: inlining failed, sending original HTML', [
                'error' => $e->getMessage(),
            ]);

            return $html;
        }
    }
}
