<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\SEO;

class PageController
{
    /**
     * Helper to get tenant-specific overrides for Hero/SEO
     */
    private function getTenantOverrides(string $page): array
    {
        $tenant = \Nexus\Core\TenantContext::get()['slug'] ?? '';
        $overrides = [];

        if ($tenant === 'hour-timebank' || $tenant === 'hour_timebank') {
            switch ($page) {
                case 'our-story':
                    return [
                        'hero_title' => 'Our Story',
                        'hero_subtitle' => 'Building a stronger, more connected community through timebanking.',
                        'hero_type' => 'About Us'
                    ];
                case 'timebanking-guide':
                    return [
                        'hero_title' => 'Timebanking Guide',
                        'hero_subtitle' => 'Everything you need to know to start exchanging time.',
                        'hero_type' => 'Resources'
                    ];
                case 'partner':
                    return [
                        'hero_title' => 'Partner With Us',
                        'hero_subtitle' => 'Collaborate with Hour Timebank to create lasting social impact.',
                        'hero_type' => 'Partnerships'
                    ];
                case 'social-prescribing':
                    return [
                        'hero_title' => 'Social Prescribing',
                        'hero_subtitle' => 'Connecting healthcare with community support for better well-being.',
                        'hero_type' => 'Wellness'
                    ];
                case 'faq':
                    return [
                        'hero_title' => 'Timebanking FAQ',
                        'hero_subtitle' => 'Common questions about earning, spending, and exchanging credits.',
                        'hero_type' => 'Support'
                    ];
                case 'impact-summary':
                    return [
                        'hero_title' => 'Impact Summary',
                        'hero_subtitle' => 'A snapshot of the difference we make together.',
                        'hero_type' => 'Our Impact'
                    ];
                case 'impact-report':
                    return [
                        'hero_title' => 'Full Impact Report',
                        'hero_subtitle' => 'Detailed analysis and metrics of our community engagement.',
                        'hero_type' => 'Our Impact'
                    ];
                case 'strategic-plan':
                    return [
                        'hero_title' => 'Strategic Plan 2026-2030',
                        'hero_subtitle' => 'Our vision and roadmap for the future of timebanking.',
                        'hero_type' => 'Our Impact'
                    ];
                case 'contact':
                    return [
                        'hero_title' => 'Contact Us',
                        'hero_subtitle' => 'We are here to help. Reach out with any questions.',
                        'hero_type' => 'Contact'
                    ];
                case 'privacy':
                    return [
                        'hero_title' => 'Your Data, Your Trust',
                        'hero_subtitle' => 'How hOUR Timebank CLG protects and respects your personal information.',
                        'hero_type' => 'Legal'
                    ];
                case 'terms':
                    return [
                        'hero_title' => 'Community Guidelines',
                        'hero_subtitle' => 'The rules of engagement for a fair and safe timebanking experience.',
                        'hero_type' => 'Legal'
                    ];
                case 'legal':
                    return [
                        'hero_title' => 'Legal & Info',
                        'hero_subtitle' => 'Privacy, Terms, and Platform Information',
                        'hero_type' => 'Legal'
                    ];
            }
        }
        return [];
    }

    public function about()
    {
        SEO::setTitle('About Us');
        SEO::setDescription('Learn more about our Timebank community and mission.');

        // Mobile-only About page for Hour Timebank (tenant 2)
        $tenantId = \Nexus\Core\TenantContext::getId();
        $isMobile = preg_match('/Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|Opera Mini|IEMobile/i', $_SERVER['HTTP_USER_AGENT'] ?? '');

        if ($tenantId == 2 && $isMobile) {
            View::render('pages/mobile-about');
            return;
        }

        View::render('pages/about');
    }

    public function faq()
    {
        SEO::setTitle('Frequently Asked Questions');
        SEO::setDescription('Answers to common questions about timebanking.');

        View::render('pages/faq', $this->getTenantOverrides('faq'));
    }

    public function terms()
    {
        SEO::setTitle('Terms of Service');

        // Check for Tenant Custom Terms
        $tenant = \Nexus\Core\TenantContext::get();
        $config = !empty($tenant['configuration']) ? json_decode($tenant['configuration'], true) : [];

        if (!empty($config['terms_text'])) {
            View::render('pages/legal_dynamic', array_merge([
                'pageTitle' => 'Terms of Service',
                'content' => $config['terms_text']
            ], $this->getTenantOverrides('terms')));
            return;
        }

        View::render('pages/terms', $this->getTenantOverrides('terms'));
    }

    public function privacy()
    {
        SEO::setTitle('Privacy Policy');

        // Check for Tenant Custom Privacy
        $tenant = \Nexus\Core\TenantContext::get();
        $config = !empty($tenant['configuration']) ? json_decode($tenant['configuration'], true) : [];

        if (!empty($config['privacy_text'])) {
            View::render('pages/legal_dynamic', array_merge([
                'pageTitle' => 'Privacy Policy',
                'content' => $config['privacy_text']
            ], $this->getTenantOverrides('privacy')));
            return;
        }

        View::render('pages/privacy', $this->getTenantOverrides('privacy'));
    }

    public function accessibility()
    {
        SEO::setTitle('Accessibility Statement');

        // Check for Tenant Custom Accessibility
        $tenant = \Nexus\Core\TenantContext::get();
        $config = !empty($tenant['configuration']) ? json_decode($tenant['configuration'], true) : [];

        if (!empty($config['accessibility_text'])) {
            View::render('pages/legal_dynamic', array_merge([
                'pageTitle' => 'Accessibility Statement',
                'content' => $config['accessibility_text']
            ], $this->getTenantOverrides('accessibility')));
            return;
        }

        View::render('pages/accessibility', $this->getTenantOverrides('accessibility'));
    }

    public function legal()
    {
        SEO::setTitle('Legal & Info');
        SEO::setDescription('Privacy Policy, Terms of Service, and platform information.');

        View::render('pages/legal', $this->getTenantOverrides('legal'));
    }

    public function contact()
    {
        SEO::setTitle('Contact Us');

        View::render('pages/contact', $this->getTenantOverrides('contact'));
    }

    public function howItWorks()
    {
        SEO::setTitle('How It Works');
        View::render('pages/how-it-works');
    }

    public function ourStory()
    {
        SEO::setTitle('Our Story');

        View::render('pages/about-story', $this->getTenantOverrides('our-story'));
    }

    public function partner()
    {
        SEO::setTitle('Partner With Us');

        View::render('pages/partner', $this->getTenantOverrides('partner'));
    }

    public function socialPrescribing()
    {
        SEO::setTitle('Social Prescribing');

        View::render('pages/social-prescribing', $this->getTenantOverrides('social-prescribing'));
    }

    public function timebankingGuide()
    {
        SEO::setTitle('Timebanking Guide');

        View::render('pages/timebanking-guide', $this->getTenantOverrides('timebanking-guide'));
    }

    public function impactSummary()
    {
        SEO::setTitle('Impact Summary');

        View::render('pages/impact-summary', $this->getTenantOverrides('impact-summary'));
    }

    public function impactReport()
    {
        SEO::setTitle('Impact Report');

        View::render('pages/impact-report', $this->getTenantOverrides('impact-report'));
    }

    public function strategicPlan()
    {
        SEO::setTitle('Strategic Plan 2026-2030');

        View::render('pages/strategic-plan', $this->getTenantOverrides('strategic-plan'));
    }
    public function show($slug)
    {
        // Security: Prevent traversal
        if (strpos($slug, '.') !== false || strpos($slug, '/') !== false) {
            http_response_code(404);
            echo "Page not found.";
            return;
        }

        $tenantId = \Nexus\Core\TenantContext::getId();
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);

        // First, try to fetch from database (CMS pages)
        $db = \Nexus\Core\Database::getConnection();

        // If admin, show unpublished pages too (for preview)
        if ($isAdmin) {
            $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND tenant_id = ?");
        } else {
            $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND tenant_id = ? AND is_published = 1");
        }
        $stmt->execute([$slug, $tenantId]);
        $page = $stmt->fetch();

        if ($page) {
            // Database page found - render it
            SEO::setTitle($page['title']);

            // Check for SEO metadata
            $seo = \Nexus\Models\SeoMetadata::get('page', $page['id']);
            if (!empty($seo['meta_title'])) {
                SEO::setTitle($seo['meta_title']);
            }
            if (!empty($seo['meta_description'])) {
                SEO::setDescription($seo['meta_description']);
            }

            // Check if page uses blocks (V2) or legacy content (V1)
            $useBlocks = false;
            $pageContent = '';

            // Check if page has blocks
            $blockCheck = $db->prepare("SELECT COUNT(*) as count FROM page_blocks WHERE page_id = ?");
            $blockCheck->execute([$page['id']]);
            $blockCount = $blockCheck->fetch();

            if ($blockCount && $blockCount['count'] > 0) {
                // Page uses V2 blocks - render via PageRenderer
                $useBlocks = true;
                $pageContent = \Nexus\PageBuilder\PageRenderer::renderPage($page['id']);
            } else {
                // Page uses legacy content field (V1)
                $pageContent = $page['content'];
            }

            View::render('pages/dynamic', [
                'page' => $page,
                'content' => $pageContent,
                'useBlocks' => $useBlocks
            ]);
            return;
        }

        // Fallback: Try static view file
        $viewPath = 'pages/' . $slug;

        // Check if static view exists before rendering
        $layout = \Nexus\Services\LayoutHelper::get();
        $viewFile = __DIR__ . '/../../views/' . $layout . '/' . $viewPath . '.php';
        $fallbackViewFile = __DIR__ . '/../../views/' . $viewPath . '.php';

        if (file_exists($viewFile) || file_exists($fallbackViewFile)) {
            View::render($viewPath);
            return;
        }

        // Nothing found - 404
        http_response_code(404);
        View::render('errors/404');
    }
}
