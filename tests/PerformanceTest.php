<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Performance Tests
 * Validates resource hints, lazy loading, and optimization
 */
class PerformanceTest extends TestCase
{
    private $headerPath;
    private $manifestPath;
    private $swPath;

    protected function setUp(): void
    {
        $this->headerPath = __DIR__ . '/../views/layouts/modern/header.php';
        $this->manifestPath = __DIR__ . '/../httpdocs/manifest.json';
        $this->swPath = __DIR__ . '/../httpdocs/sw.js';
    }

    /** @test */
    public function header_has_preconnect_hints()
    {
        $content = file_get_contents($this->headerPath);

        $this->assertStringContainsString(
            'rel="preconnect"',
            $content,
            'Header missing preconnect hints'
        );

        // Check for specific external domains
        $this->assertStringContainsString(
            'fonts.googleapis.com',
            $content,
            'Missing preconnect to Google Fonts'
        );
        $this->assertStringContainsString(
            'cdnjs.cloudflare.com',
            $content,
            'Missing preconnect to CDN'
        );
    }

    /** @test */
    public function header_has_dns_prefetch()
    {
        $content = file_get_contents($this->headerPath);

        $this->assertStringContainsString(
            'rel="dns-prefetch"',
            $content,
            'Header missing DNS prefetch hints'
        );

        $dnsPrefetchCount = substr_count($content, 'rel="dns-prefetch"');
        $this->assertGreaterThanOrEqual(
            3,
            $dnsPrefetchCount,
            'Should have at least 3 DNS prefetch hints'
        );
    }

    /** @test */
    public function header_preloads_critical_css()
    {
        $content = file_get_contents($this->headerPath);

        $this->assertStringContainsString(
            'rel="preload"',
            $content,
            'Header missing preload hints'
        );

        $this->assertStringContainsString(
            'as="style"',
            $content,
            'Header should preload CSS as style'
        );

        // Check for critical CSS preload
        $this->assertStringContainsString(
            'nexus-phoenix.css',
            $content,
            'Should preload main CSS file'
        );
    }

    /** @test */
    public function pwa_manifest_exists_and_valid()
    {
        $this->assertFileExists(
            $this->manifestPath,
            'PWA manifest.json not found'
        );

        $content = file_get_contents($this->manifestPath);
        $manifest = json_decode($content, true);

        $this->assertNotNull($manifest, 'manifest.json is not valid JSON');
        $this->assertArrayHasKey('name', $manifest, 'Manifest missing name');
        $this->assertArrayHasKey('short_name', $manifest, 'Manifest missing short_name');
        $this->assertArrayHasKey('start_url', $manifest, 'Manifest missing start_url');
        $this->assertArrayHasKey('display', $manifest, 'Manifest missing display');
        $this->assertArrayHasKey('icons', $manifest, 'Manifest missing icons');
        $this->assertArrayHasKey('theme_color', $manifest, 'Manifest missing theme_color');

        // Check for required icon sizes
        $iconSizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $iconSizes, 'Manifest missing 192x192 icon');
        $this->assertContains('512x512', $iconSizes, 'Manifest missing 512x512 icon');
    }

    /** @test */
    public function service_worker_exists()
    {
        $this->assertFileExists(
            $this->swPath,
            'Service worker sw.js not found'
        );

        $content = file_get_contents($this->swPath);
        $this->assertStringContainsString(
            'cache',
            strtolower($content),
            'Service worker should implement caching'
        );
    }

    /** @test */
    public function manifest_linked_in_header()
    {
        $content = file_get_contents($this->headerPath);

        $this->assertStringContainsString(
            'rel="manifest"',
            $content,
            'Header missing manifest link'
        );
        $this->assertStringContainsString(
            '/manifest.json',
            $content,
            'Header missing manifest.json reference'
        );
    }

    /** @test */
    public function css_files_have_cache_busting()
    {
        $content = file_get_contents($this->headerPath);

        $cssLinks = preg_match_all('/href="[^"]*\.css\?v=/', $content);
        $this->assertGreaterThan(
            0,
            $cssLinks,
            'CSS files should have cache-busting version parameter'
        );
    }

    /** @test */
    public function lazy_loading_implemented_correctly()
    {
        $viewsPath = __DIR__ . '/../views/modern';
        $pages = glob($viewsPath . '/**/*.php');

        $totalImages = 0;
        $lazyImages = 0;

        foreach ($pages as $page) {
            $content = file_get_contents($page);
            $totalImages += substr_count($content, '<img ');
            $lazyImages += substr_count($content, 'loading="lazy"');
        }

        if ($totalImages > 0) {
            $percentage = ($lazyImages / $totalImages) * 100;
            $this->assertGreaterThan(
                50,
                $percentage,
                "At least 50% of images should have lazy loading (found $lazyImages/$totalImages)"
            );
        }
    }

    /** @test */
    public function no_duplicate_resource_hints()
    {
        $content = file_get_contents($this->headerPath);

        // Check for duplicate preconnect to same domain
        preg_match_all('/rel="preconnect" href="([^"]+)"/', $content, $preconnects);
        $uniquePreconnects = array_unique($preconnects[1]);

        $this->assertEquals(
            count($preconnects[1]),
            count($uniquePreconnects),
            'Found duplicate preconnect hints'
        );
    }

    /** @test */
    public function theme_color_meta_present()
    {
        $content = file_get_contents($this->headerPath);

        $this->assertStringContainsString(
            'name="theme-color"',
            $content,
            'Header missing theme-color meta tag'
        );
    }

    /** @test */
    public function viewport_meta_optimized()
    {
        $content = file_get_contents($this->headerPath);

        $this->assertStringContainsString(
            'name="viewport"',
            $content,
            'Header missing viewport meta tag'
        );

        $this->assertStringContainsString(
            'viewport-fit=cover',
            $content,
            'Viewport should include viewport-fit=cover for notched devices'
        );
    }
}
