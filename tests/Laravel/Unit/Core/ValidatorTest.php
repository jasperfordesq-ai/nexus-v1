<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    // -------------------------------------------------------
    // isPhone()
    // -------------------------------------------------------

    public function test_isPhone_valid_e164_returns_true(): void
    {
        $this->assertTrue(Validator::isPhone('+15551234567'));
        $this->assertTrue(Validator::isPhone('+353861234567'));
        $this->assertTrue(Validator::isPhone('+441234567890'));
    }

    public function test_isPhone_valid_local_format_returns_true(): void
    {
        $this->assertTrue(Validator::isPhone('0861234567'));
        $this->assertTrue(Validator::isPhone('1234567'));
    }

    public function test_isPhone_with_formatting_returns_true(): void
    {
        $this->assertTrue(Validator::isPhone('+1 (555) 123-4567'));
        $this->assertTrue(Validator::isPhone('+44 20 7946 0958'));
        $this->assertTrue(Validator::isPhone('(086) 123 4567'));
    }

    public function test_isPhone_too_short_returns_false(): void
    {
        $this->assertFalse(Validator::isPhone('123'));
        $this->assertFalse(Validator::isPhone('+12345'));
    }

    public function test_isPhone_too_long_returns_false(): void
    {
        $this->assertFalse(Validator::isPhone('+12345678901234567'));
    }

    public function test_isPhone_non_numeric_returns_false(): void
    {
        $this->assertFalse(Validator::isPhone('not-a-phone'));
        $this->assertFalse(Validator::isPhone('abc'));
    }

    public function test_isPhone_empty_returns_false(): void
    {
        $this->assertFalse(Validator::isPhone(''));
    }

    // -------------------------------------------------------
    // isEmail()
    // -------------------------------------------------------

    public function test_isEmail_valid_email_returns_true(): void
    {
        $this->assertTrue(Validator::isEmail('user@example.com'));
        $this->assertTrue(Validator::isEmail('first.last@domain.co.uk'));
        $this->assertTrue(Validator::isEmail('user+tag@example.org'));
    }

    public function test_isEmail_invalid_email_returns_false(): void
    {
        $this->assertFalse(Validator::isEmail('not-an-email'));
        $this->assertFalse(Validator::isEmail('@example.com'));
        $this->assertFalse(Validator::isEmail('user@'));
        $this->assertFalse(Validator::isEmail(''));
    }

    // -------------------------------------------------------
    // isUrl()
    // -------------------------------------------------------

    public function test_isUrl_valid_urls_returns_true(): void
    {
        $this->assertTrue(Validator::isUrl('https://example.com'));
        $this->assertTrue(Validator::isUrl('http://example.com/path?q=1'));
        $this->assertTrue(Validator::isUrl('https://sub.domain.co.uk/page'));
    }

    public function test_isUrl_invalid_urls_returns_false(): void
    {
        $this->assertFalse(Validator::isUrl('not-a-url'));
        $this->assertFalse(Validator::isUrl('example.com'));
        $this->assertFalse(Validator::isUrl(''));
    }
}
