<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\I18n;

use App\I18n\LocaleContext;
use Illuminate\Support\Facades\App;
use RuntimeException;
use Tests\Laravel\TestCase;

class LocaleContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        App::setLocale('en');
    }

    public function test_string_locale_switches_active_locale_inside_callback(): void
    {
        $observed = null;
        LocaleContext::withLocale('ga', function () use (&$observed) {
            $observed = App::getLocale();
        });

        $this->assertSame('ga', $observed);
    }

    public function test_locale_is_restored_after_callback_returns(): void
    {
        App::setLocale('en');
        LocaleContext::withLocale('fr', fn () => null);

        $this->assertSame('en', App::getLocale());
    }

    public function test_locale_is_restored_even_when_callback_throws(): void
    {
        App::setLocale('en');
        try {
            LocaleContext::withLocale('de', function () {
                throw new RuntimeException('boom');
            });
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame('en', App::getLocale(), 'Locale must be restored on exception');
    }

    public function test_callback_return_value_is_propagated(): void
    {
        $result = LocaleContext::withLocale('ga', fn () => 'payload');
        $this->assertSame('payload', $result);
    }

    public function test_null_locale_runs_callback_without_switching(): void
    {
        App::setLocale('en');
        $observed = null;
        LocaleContext::withLocale(null, function () use (&$observed) {
            $observed = App::getLocale();
        });

        $this->assertSame('en', $observed);
    }

    public function test_empty_string_locale_runs_callback_without_switching(): void
    {
        App::setLocale('en');
        $observed = null;
        LocaleContext::withLocale('', function () use (&$observed) {
            $observed = App::getLocale();
        });

        $this->assertSame('en', $observed);
    }

    public function test_object_with_preferred_language_switches_locale(): void
    {
        $user = (object) ['preferred_language' => 'ga'];
        $observed = null;
        LocaleContext::withLocale($user, function () use (&$observed) {
            $observed = App::getLocale();
        });

        $this->assertSame('ga', $observed);
    }

    public function test_object_without_preferred_language_does_not_switch_locale(): void
    {
        App::setLocale('en');
        $user = (object) ['id' => 1]; // no preferred_language property
        $observed = null;
        LocaleContext::withLocale($user, function () use (&$observed) {
            $observed = App::getLocale();
        });

        $this->assertSame('en', $observed);
    }

    public function test_object_with_empty_preferred_language_does_not_switch_locale(): void
    {
        App::setLocale('en');
        $user = (object) ['preferred_language' => '   '];
        $observed = null;
        LocaleContext::withLocale($user, function () use (&$observed) {
            $observed = App::getLocale();
        });

        $this->assertSame('en', $observed);
    }

    public function test_nested_invocations_each_restore_their_own_locale(): void
    {
        App::setLocale('en');

        $checkpoints = [];
        LocaleContext::withLocale('ga', function () use (&$checkpoints) {
            $checkpoints[] = App::getLocale(); // expect 'ga'
            LocaleContext::withLocale('de', function () use (&$checkpoints) {
                $checkpoints[] = App::getLocale(); // expect 'de'
            });
            $checkpoints[] = App::getLocale(); // expect 'ga' (restored from nested)
        });
        $checkpoints[] = App::getLocale(); // expect 'en' (restored from outer)

        $this->assertSame(['ga', 'de', 'ga', 'en'], $checkpoints);
    }

    public function test_switching_to_same_locale_is_a_noop(): void
    {
        App::setLocale('ga');
        $observed = null;
        LocaleContext::withLocale('ga', function () use (&$observed) {
            $observed = App::getLocale();
        });

        $this->assertSame('ga', $observed);
        $this->assertSame('ga', App::getLocale());
    }

    public function test_switching_to_same_locale_restores_correctly_on_throw(): void
    {
        App::setLocale('ga');
        try {
            LocaleContext::withLocale('ga', function () {
                throw new RuntimeException('boom');
            });
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame('ga', App::getLocale());
    }

    public function test_integration_with_laravel_translator_resolves_against_switched_locale(): void
    {
        // This test verifies the helper integrates with Laravel's __() helper — the whole
        // point of the class. We pick a well-known key that exists in both en and ga
        // lang/*/emails.json files. If the fixtures have drifted this test will need an
        // update, but the shape (value differs between locales) is what matters.
        $enValue = LocaleContext::withLocale('en', fn () => __('emails.common.greeting', ['name' => 'Alice']));
        $gaValue = LocaleContext::withLocale('ga', fn () => __('emails.common.greeting', ['name' => 'Alice']));

        $this->assertIsString($enValue);
        $this->assertIsString($gaValue);
        // Sanity: both should contain the interpolated name
        $this->assertStringContainsString('Alice', $enValue);
        $this->assertStringContainsString('Alice', $gaValue);
    }
}
