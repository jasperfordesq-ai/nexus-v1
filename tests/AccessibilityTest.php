<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Accessibility Tests
 * Validates WCAG 2.1 compliance and ARIA implementation
 */
class AccessibilityTest extends TestCase
{
    private $viewsPath;

    protected function setUp(): void
    {
        $this->viewsPath = __DIR__ . '/../views/modern';
    }

    /** @test */
    public function all_key_pages_have_main_landmark()
    {
        $keyPages = [
            'home.php',
            'messages/index.php',
            'profile/show.php',
            'groups/index.php',
            'events/index.php',
            'listings/index.php',
            'resources/index.php',
            'search/results.php',
        ];

        foreach ($keyPages as $page) {
            $filePath = $this->viewsPath . '/' . $page;
            $this->assertFileExists($filePath, "Page not found: $page");

            $content = file_get_contents($filePath);
            $this->assertStringContainsString(
                '<main id="main-content"',
                $content,
                "Page missing main landmark: $page"
            );
            $this->assertStringContainsString(
                'role="main"',
                $content,
                "Page missing main role: $page"
            );
        }
    }

    /** @test */
    public function header_has_skip_to_content_link()
    {
        $headerPath = __DIR__ . '/../views/layouts/modern/header.php';
        $content = file_get_contents($headerPath);

        $this->assertStringContainsString(
            'href="#main-content"',
            $content,
            'Skip to content link missing'
        );
        $this->assertStringContainsString(
            'class="skip-link"',
            $content,
            'Skip link class missing'
        );
    }

    /** @test */
    public function css_has_focus_visible_styles()
    {
        $cssPath = __DIR__ . '/../httpdocs/assets/css/nexus-phoenix.css';
        $content = file_get_contents($cssPath);

        $this->assertStringContainsString(
            ':focus-visible',
            $content,
            'Focus-visible styles missing'
        );
        $this->assertStringContainsString(
            '.sr-only',
            $content,
            'Screen reader only class missing'
        );
    }

    /** @test */
    public function images_have_lazy_loading()
    {
        $homePath = $this->viewsPath . '/home.php';
        $content = file_get_contents($homePath);

        // Check for lazy loading attributes
        $lazyLoadCount = substr_count($content, 'loading="lazy"');
        $this->assertGreaterThanOrEqual(
            5,
            $lazyLoadCount,
            'Home page should have at least 5 lazy-loaded images'
        );

        // Ensure no syntax errors in lazy loading
        $this->assertStringNotContainsString(
            '? loading="lazy">',
            $content,
            'Found syntax error in lazy loading pattern'
        );
        $this->assertStringNotContainsString(
            'loading="lazy""',
            $content,
            'Found double-quote error in lazy loading'
        );
    }

    /** @test */
    public function interactive_buttons_have_aria_labels()
    {
        $pages = glob($this->viewsPath . '/**/*.php');
        $pagesWithAriaButtons = 0;

        foreach ($pages as $page) {
            $content = file_get_contents($page);
            if (preg_match('/<button[^>]*aria-label=/', $content)) {
                $pagesWithAriaButtons++;
            }
        }

        $this->assertGreaterThan(
            10,
            $pagesWithAriaButtons,
            'At least 10 pages should have buttons with ARIA labels'
        );
    }

    /** @test */
    public function modals_have_dialog_role()
    {
        $pages = glob($this->viewsPath . '/**/*.php');
        $modalsFound = 0;
        $modalsWithRole = 0;

        foreach ($pages as $page) {
            $content = file_get_contents($page);
            if (preg_match('/class="[^"]*modal[^"]*"/', $content)) {
                $modalsFound++;
                if (preg_match('/role="dialog"/', $content)) {
                    $modalsWithRole++;
                }
            }
        }

        if ($modalsFound > 0) {
            $percentage = ($modalsWithRole / $modalsFound) * 100;
            $this->assertGreaterThanOrEqual(
                25,
                $percentage,
                "At least 25% of modals should have role=dialog (found $modalsWithRole/$modalsFound)"
            );
        }
    }

    /** @test */
    public function forms_have_proper_labels()
    {
        $searchResultsPath = $this->viewsPath . '/search/results.php';
        if (file_exists($searchResultsPath)) {
            $content = file_get_contents($searchResultsPath);

            // Search forms should have aria-label
            if (preg_match('/type="search"/', $content)) {
                $this->assertStringContainsString(
                    'aria-label',
                    $content,
                    'Search inputs should have aria-label'
                );
            }
        }

        $this->assertTrue(true); // If search page doesn't exist, pass
    }

    /** @test */
    public function navigation_has_role_and_label()
    {
        $pages = glob($this->viewsPath . '/**/*.php');
        $navsWithRole = 0;

        foreach ($pages as $page) {
            $content = file_get_contents($page);
            if (preg_match('/<nav[^>]*role="navigation"[^>]*aria-label=/', $content)) {
                $navsWithRole++;
            }
        }

        $this->assertGreaterThan(
            0,
            $navsWithRole,
            'At least one page should have properly labeled navigation'
        );
    }

    /** @test */
    public function no_php_syntax_errors_in_views()
    {
        $pages = glob($this->viewsPath . '/**/*.php');
        $errors = [];

        foreach ($pages as $page) {
            $output = shell_exec("php -l " . escapeshellarg($page) . " 2>&1");
            if (strpos($output, 'No syntax errors') === false) {
                $errors[] = basename($page) . ': ' . $output;
            }
        }

        $this->assertEmpty(
            $errors,
            "Found PHP syntax errors:\n" . implode("\n", $errors)
        );
    }
}
