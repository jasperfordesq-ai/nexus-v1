<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\I18n;

use App\I18n\Translator;
use Tests\Laravel\TestCase;

class TranslatorTest extends TestCase
{
    private string $langDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temp lang directory structure
        $this->langDir = sys_get_temp_dir() . '/nexus_translator_test_' . uniqid();
        mkdir($this->langDir . '/en', 0777, true);
        mkdir($this->langDir . '/ga', 0777, true);

        // English translations
        file_put_contents($this->langDir . '/en/common.json', json_encode([
            'hello' => 'Hello',
            'goodbye' => 'Goodbye',
        ]));

        file_put_contents($this->langDir . '/en/admin_dashboard.json', json_encode([
            'title' => 'Dashboard',
            'meta' => [
                'description' => 'Admin panel for {{name}}',
            ],
        ]));

        // Irish translations (partial)
        file_put_contents($this->langDir . '/ga/common.json', json_encode([
            'hello' => 'Dia duit',
        ]));

        Translator::init($this->langDir);
        Translator::setLocale('en');
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $this->rrmdir($this->langDir);
        parent::tearDown();
    }

    public function test_set_and_get_locale(): void
    {
        Translator::setLocale('ga');
        $this->assertEquals('ga', Translator::getLocale());

        Translator::setLocale('en');
        $this->assertEquals('en', Translator::getLocale());
    }

    public function test_get_returns_translation_for_namespaced_key(): void
    {
        $result = Translator::get('admin_dashboard.title');
        $this->assertEquals('Dashboard', $result);
    }

    public function test_get_returns_translation_for_common_namespace_without_dot(): void
    {
        $result = Translator::get('hello');
        $this->assertEquals('Hello', $result);
    }

    public function test_get_returns_key_when_not_found(): void
    {
        $result = Translator::get('nonexistent.key');
        $this->assertEquals('nonexistent.key', $result);
    }

    public function test_get_falls_back_to_english_when_key_missing_in_locale(): void
    {
        Translator::setLocale('ga');

        // 'goodbye' exists in en but not in ga
        $result = Translator::get('goodbye');
        $this->assertEquals('Goodbye', $result);
    }

    public function test_get_uses_current_locale_when_key_exists(): void
    {
        Translator::setLocale('ga');

        $result = Translator::get('hello');
        $this->assertEquals('Dia duit', $result);
    }

    public function test_get_supports_nested_dot_notation(): void
    {
        $result = Translator::get('admin_dashboard.meta.description', ['name' => 'NEXUS']);
        $this->assertEquals('Admin panel for NEXUS', $result);
    }

    public function test_get_interpolates_params(): void
    {
        $result = Translator::get('admin_dashboard.meta.description', ['name' => 'MyTimebank']);
        $this->assertEquals('Admin panel for MyTimebank', $result);
    }

    public function test_get_returns_key_when_lang_dir_not_set(): void
    {
        Translator::init('');
        $result = Translator::get('hello');
        // With empty langDir, file loading returns null, so fallback is the key
        $this->assertEquals('hello', $result);
    }

    public function test_get_returns_key_for_nonexistent_locale(): void
    {
        Translator::setLocale('zz');
        // 'hello' should fall back to English
        $result = Translator::get('hello');
        $this->assertEquals('Hello', $result);
    }

    /**
     * Recursively remove a directory.
     */
    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
