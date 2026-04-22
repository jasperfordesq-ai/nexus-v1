<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\I18n;

use App\I18n\LocaleContext;
use Illuminate\Support\Facades\App;
use RuntimeException;
use Tests\Laravel\TestCase;

/**
 * Integration test for the recipient-locale rollout.
 *
 * Phase Y regression test: proves LocaleContext::withLocale actually causes
 * Laravel's __() helper to resolve against the switched locale, restores the
 * outer locale on both normal return and exception, and that nested wraps
 * each see their own locale — the end-to-end contract that every email and
 * notification service now depends on.
 *
 * Runs against the real lang/en/emails.json and lang/ga/emails.json fixtures
 * loaded by AppServiceProvider::loadJsonTranslations() at boot. If a fixture
 * key drifts to identical values across en/ga, swap to another key whose
 * translations actually differ — see keys under `welcome.*`, `verification.*`,
 * `password_reset.*` which currently have genuinely different translations.
 */
class EmailLocaleIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Start every test from a known baseline.
        App::setLocale('en');
    }

    /**
     * The core rollout contract: wrapping __() in LocaleContext::withLocale
     * with a user having preferred_language='ga' MUST cause Laravel's __()
     * to return the Irish translation, and the outer locale MUST be preserved
     * after the callable returns.
     */
    public function test_withLocale_user_switches_translator_and_restores_outer_locale(): void
    {
        $user = (object) ['preferred_language' => 'ga'];

        // Sanity: outer locale is English before the wrap.
        $enBefore = __('emails.welcome.greeting', ['name' => 'Alice']);
        $this->assertSame('en', App::getLocale(), 'precondition: outer locale is en');
        $this->assertStringContainsString('Welcome', $enBefore, 'precondition: en fixture resolves as English');
        $this->assertStringContainsString('Alice', $enBefore, 'precondition: placeholder is substituted');

        // Inside the wrap: Irish translation must resolve.
        $observedInsideLocale = null;
        $gaInside = LocaleContext::withLocale($user, function () use (&$observedInsideLocale) {
            $observedInsideLocale = App::getLocale();
            return __('emails.welcome.greeting', ['name' => 'Alice']);
        });

        $this->assertSame('ga', $observedInsideLocale, 'inside the wrap App::getLocale() must return ga');
        $this->assertStringContainsString('Fáilte', $gaInside, 'inside the wrap __() must resolve Irish translation');
        $this->assertStringContainsString('Alice', $gaInside, 'placeholder substitution still works under ga');
        $this->assertNotSame($enBefore, $gaInside, 'en and ga translations of the same key must differ');

        // Outer locale must be restored after the wrap exits.
        $this->assertSame('en', App::getLocale(), 'outer locale must be restored after wrap');
        $enAfter = __('emails.welcome.greeting', ['name' => 'Alice']);
        $this->assertSame($enBefore, $enAfter, '__() must resolve to English again after the wrap');
    }

    /**
     * Nested wraps: inner wrap sees its own locale, outer wrap's locale is
     * restored when inner returns, and the original outer locale is restored
     * when the outer wrap returns. This mirrors the real-world case where a
     * job handler is already wrapped and calls a service that also wraps.
     */
    public function test_nested_withLocale_each_sees_its_own_locale_and_restores_correctly(): void
    {
        // Capture translations observed at each nesting level.
        $translations = [
            'outer_before' => __('emails.welcome.cta'),   // en: "Get Started"
            'outer_during' => null,                        // ga: "Tosaigh"
            'inner_during' => null,                        // de: German (if translated) or en fallback
            'outer_after_inner' => null,                   // ga again
            'outer_after_all' => null,                     // en again
        ];

        LocaleContext::withLocale('ga', function () use (&$translations) {
            $translations['outer_during'] = __('emails.welcome.cta');
            $this->assertSame('ga', App::getLocale(), 'outer wrap sets locale to ga');

            LocaleContext::withLocale('de', function () use (&$translations) {
                $translations['inner_during'] = __('emails.welcome.cta');
                $this->assertSame('de', App::getLocale(), 'inner wrap sets locale to de');
            });

            // After the inner wrap returns, outer locale (ga) must be restored.
            $this->assertSame('ga', App::getLocale(), 'inner wrap must restore outer locale to ga');
            $translations['outer_after_inner'] = __('emails.welcome.cta');
        });

        // After the outer wrap returns, baseline (en) must be restored.
        $this->assertSame('en', App::getLocale(), 'outer wrap must restore baseline to en');
        $translations['outer_after_all'] = __('emails.welcome.cta');

        // en and ga MUST produce different strings — the whole point of the rollout.
        $this->assertStringContainsString('Get Started', $translations['outer_before']);
        $this->assertStringContainsString('Tosaigh', $translations['outer_during']);
        $this->assertNotSame(
            $translations['outer_before'],
            $translations['outer_during'],
            'en and ga translations of emails.welcome.cta must differ'
        );

        // After inner returns, outer is back to ga — must match the first ga observation.
        $this->assertSame(
            $translations['outer_during'],
            $translations['outer_after_inner'],
            're-reading the same key under restored ga must produce the same translation'
        );

        // After all wraps return, we're back to the English baseline.
        $this->assertSame(
            $translations['outer_before'],
            $translations['outer_after_all'],
            'baseline English translation must match before and after all wraps'
        );
    }

    /**
     * Exception safety: if the callable throws, the outer locale MUST still be
     * restored via the `finally` block in LocaleContext::withLocale. Without
     * this guarantee, a single failed notification send would poison every
     * subsequent __() call in the request/worker with the wrong locale.
     */
    public function test_withLocale_restores_locale_when_callable_throws(): void
    {
        $this->assertSame('en', App::getLocale(), 'precondition: baseline is en');

        try {
            LocaleContext::withLocale('ga', function () {
                // Verify we actually switched before throwing.
                $translation = __('emails.welcome.cta');
                if (!str_contains($translation, 'Tosaigh')) {
                    throw new RuntimeException('unexpected: locale did not switch to ga before throw');
                }
                throw new RuntimeException('simulated send failure inside wrap');
            });
            $this->fail('Expected RuntimeException from the inner throw was not propagated');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated send failure inside wrap', $e->getMessage());
        }

        // CRITICAL: locale must be restored even though the callable threw.
        $this->assertSame('en', App::getLocale(), 'locale must be restored after exception');

        // And __() must resolve against the restored locale.
        $afterThrow = __('emails.welcome.cta');
        $this->assertStringContainsString('Get Started', $afterThrow, 'English resolves after exception');
        $this->assertStringNotContainsString('Tosaigh', $afterThrow, 'Irish translation does not leak after exception');
    }
}
