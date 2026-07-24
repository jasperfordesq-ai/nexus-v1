<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\NewsletterService;
use Tests\Laravel\TestCase;

/**
 * Regression: NewsletterService::EMAIL_DELAY_MICROSECONDS must be a fixed literal,
 * NOT the runtime-defined global NEWSLETTER_EMAIL_DELAY_MICROSECONDS.
 *
 * Under opcache.preload (production) the file's top-level define() is compiled but
 * never executed, and on the HTTP send path the class is served from the preloaded
 * image without the file ever being require'd — so a class const referencing the
 * unqualified global resolved App\Services\NEWSLETTER_EMAIL_DELAY_MICROSECONDS,
 * found nothing, and threw a fatal 'Undefined constant' mid-newsletter-send
 * (Sentry PHP 130870092). ReflectionClassConstant::getValue() forces the const
 * initializer to evaluate, so this fails if the fragile global reference returns.
 */
class NewsletterEmailDelayConstantTest extends TestCase
{
    public function testEmailDelayClassConstantIsPreloadSafeLiteral(): void
    {
        $value = (new \ReflectionClassConstant(NewsletterService::class, 'EMAIL_DELAY_MICROSECONDS'))->getValue();

        $this->assertSame(
            250000,
            $value,
            'EMAIL_DELAY_MICROSECONDS must be a fixed literal so the class const is opcache-preload-safe'
        );
    }
}
