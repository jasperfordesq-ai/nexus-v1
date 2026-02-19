<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\SEO;
use Nexus\Models\HelpArticle;

class HelpController
{
    /**
     * Get allowed modules for current tenant
     */
    private function getAllowedModules()
    {
        $allowedModules = ['core', 'getting_started'];

        if (TenantContext::hasFeature('wallet')) $allowedModules[] = 'wallet';
        if (TenantContext::hasFeature('listings')) $allowedModules[] = 'listings';
        if (TenantContext::hasFeature('groups')) $allowedModules[] = 'groups';
        if (TenantContext::hasFeature('events')) $allowedModules[] = 'events';
        if (TenantContext::hasFeature('volunteering')) $allowedModules[] = 'volunteering';
        if (TenantContext::hasFeature('blog')) $allowedModules[] = 'blog';

        // Modern Modules (Added Jan 2026)
        if (TenantContext::hasFeature('polls')) $allowedModules[] = 'polls';
        if (TenantContext::hasFeature('goals')) $allowedModules[] = 'goals';
        if (TenantContext::hasFeature('proposals')) $allowedModules[] = 'governance';
        if (TenantContext::hasFeature('gamification')) $allowedModules[] = 'gamification';
        if (TenantContext::hasFeature('ai')) $allowedModules[] = 'ai_assistant';

        // Universal Modules (Always Allowed)
        $allowedModules = array_merge($allowedModules, [
            'sustainability',
            'offline',
            'mobile',
            'insights',
            'security',
            'resources'
        ]);

        // Master Tenant sees everything
        if (TenantContext::getId() === 1) {
            $allowedModules = array_unique(array_merge($allowedModules, [
                'core',
                'getting_started',
                'wallet',
                'listings',
                'groups',
                'events',
                'volunteering',
                'blog',
                'polls',
                'goals',
                'governance',
                'gamification',
                'ai_assistant'
            ]));
        }

        return $allowedModules;
    }

    /**
     * Display the Help Center Index (Grid of Topics)
     */
    public function index()
    {
        \Nexus\Middleware\TenantModuleMiddleware::require('help_center');
        $allowedModules = $this->getAllowedModules();

        // Fetch Articles
        $articles = HelpArticle::getAll($allowedModules);

        // Group by Module for UI
        $grouped = [];
        foreach ($articles as $art) {
            $grouped[$art['module_tag']][] = $art;
        }

        // Get popular articles for sidebar/featured section
        $popularArticles = [];
        try {
            $popularArticles = HelpArticle::getPopular($allowedModules, 5);
        } catch (\Exception $e) {
            // view_count column may not exist yet - gracefully degrade
        }

        // SEO: Help Center Index
        $tenant = TenantContext::get();
        $siteName = $tenant['name'] ?? 'Hour TimeBank';
        SEO::setTitle("Help Center - {$siteName}");
        SEO::setDescription("Find answers to your questions about {$siteName}. Browse help articles, guides, and documentation for all platform features.");
        SEO::setType('website');
        SEO::setUrl(TenantContext::getBasePath() . '/help');

        // Add Breadcrumbs Schema
        SEO::addBreadcrumbs([
            ['name' => 'Home', 'url' => TenantContext::getBasePath() . '/'],
            ['name' => 'Help Center', 'url' => TenantContext::getBasePath() . '/help']
        ]);

        // View Resolution - Let View class handle layout switching
        View::render('help/index', [
            'groupedArticles' => $grouped,
            'popularArticles' => $popularArticles,
            'pageTitle' => 'Help Center',

            // Layout Override Variables
            'hero_title' => 'Help Center',
            'hero_subtitle' => 'Guides and documentation for the platform.',
            'hero_type' => 'Support',
            'hero_gradient' => 'htb-hero-gradient-brand'
        ]);
    }

    /**
     * Search help articles
     */
    public function search()
    {
        $query = trim($_GET['q'] ?? '');
        $allowedModules = $this->getAllowedModules();

        $results = [];
        if (!empty($query)) {
            $results = HelpArticle::search($query, $allowedModules);
        }

        // SEO: Search Results (noindex to avoid thin content)
        $tenant = TenantContext::get();
        $siteName = $tenant['name'] ?? 'Hour TimeBank';
        SEO::setTitle("Search Help - {$siteName}");
        SEO::setDescription("Search results for help articles and documentation.");
        SEO::addMeta('robots', 'noindex, follow'); // Don't index search results pages

        // View Resolution - Let View class handle layout switching
        View::render('help/search', [
            'query' => $query,
            'results' => $results,
            'pageTitle' => 'Search Help',

            'hero_title' => 'Search Results',
            'hero_subtitle' => empty($query) ? 'Enter a search term' : 'Results for "' . htmlspecialchars($query) . '"',
            'hero_type' => 'Help',
            'hero_back_url' => TenantContext::getBasePath() . '/help',
            'hero_back_label' => 'Back to Help Center'
        ]);
    }

    /**
     * Show a single article
     */
    public function show($slug)
    {
        $article = HelpArticle::findBySlug($slug);

        if (!$article) {
            header("HTTP/1.0 404 Not Found");
            View::render('404');
            return;
        }

        // Feature Check: If user tries to access a 'volunteering' article on a tenant where it's disabled
        if ($article['module_tag'] !== 'core' && $article['module_tag'] !== 'getting_started') {
            // If NOT Master AND feature is disabled
            if (TenantContext::getId() !== 1 && !TenantContext::hasFeature($article['module_tag'])) {
                header("HTTP/1.0 404 Not Found");
                echo "<h1>Module Disabled</h1><p>This help topic relates to a feature that is not active for this community.</p>";
                exit;
            }
        }

        // Increment view count (gracefully handle if column doesn't exist)
        try {
            HelpArticle::incrementViewCount($article['id']);
        } catch (\Exception) {
            // view_count column may not exist yet
        }

        // Get related articles from same module
        $relatedArticles = [];
        try {
            $relatedArticles = HelpArticle::getRelated($article['module_tag'], $article['id'], 5);
        } catch (\Exception) {
            // Gracefully degrade if view_count column doesn't exist
        }

        // Module names for breadcrumb
        $moduleNames = [
            'core' => 'Platform Basics',
            'getting_started' => 'Getting Started',
            'wallet' => 'Wallet & Transactions',
            'listings' => 'Marketplace',
            'groups' => 'Community Hubs',
            'events' => 'Events',
            'volunteering' => 'Volunteering',
            'blog' => 'News & Updates',
            'polls' => 'Democracy & Polling',
            'goals' => 'Goals & Mentorship',
            'governance' => 'Governance Proposals',
            'gamification' => 'Badges & Rewards',
            'ai_assistant' => 'AI Assistant',
            'sustainability' => 'Sustainability (SDGs)',
            'offline' => 'Offline & Mobile',
            'mobile' => 'Mobile App',
            'insights' => 'Your Stats',
            'security' => 'Privacy & Security',
            'resources' => 'Resource Library'
        ];

        $moduleName = $moduleNames[$article['module_tag']] ?? ucfirst(str_replace('_', ' ', $article['module_tag']));

        // SEO: Article Page (Full Google SEO Boost)
        $tenant = TenantContext::get();
        $siteName = $tenant['name'] ?? 'Hour TimeBank';
        $articleUrl = TenantContext::getBasePath() . '/help/' . $article['slug'];

        // Set title and auto-generate description from content
        SEO::setTitle($article['title'] . " - Help Center - {$siteName}");
        SEO::autoDescription($article['content']);
        SEO::setType('article');
        SEO::setUrl($articleUrl);
        SEO::setCanonical($articleUrl);

        // Add Article Schema (JSON-LD) for Google Rich Results
        SEO::autoSchema('article', [
            'title' => $article['title'],
            'content' => $article['content'],
            'created_at' => $article['created_at'] ?? date('Y-m-d'),
            'updated_at' => $article['updated_at'] ?? $article['created_at'] ?? date('Y-m-d'),
            'url' => $articleUrl,
            'image' => null // Help articles typically don't have images
        ], [
            'name' => $siteName,
            'type' => 'Organization'
        ]);

        // Add Breadcrumbs Schema
        SEO::addBreadcrumbs([
            ['name' => 'Home', 'url' => TenantContext::getBasePath() . '/'],
            ['name' => 'Help Center', 'url' => TenantContext::getBasePath() . '/help'],
            ['name' => $moduleName, 'url' => TenantContext::getBasePath() . '/help#' . $article['module_tag']],
            ['name' => $article['title'], 'url' => $articleUrl]
        ]);

        // View Resolution - Let View class handle layout switching
        View::render('help/show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles,
            'moduleNames' => $moduleNames,
            'moduleName' => $moduleName,

            // Layout Override Variables
            'hero_title' => $article['title'],
            'hero_subtitle' => 'Help Center Article',
            'hero_type' => ucfirst($article['module_tag']),
            'hero_back_url' => TenantContext::getBasePath() . '/help',
            'hero_back_label' => 'Back to Help Center'
        ]);
    }

    /**
     * API endpoint for article feedback
     */
    public function feedback()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $articleSlug = $input['article_slug'] ?? '';
        $helpful = $input['helpful'] ?? true;

        if (empty($articleSlug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing article_slug']);
            return;
        }

        $article = HelpArticle::findBySlug($articleSlug);
        if (!$article) {
            http_response_code(404);
            echo json_encode(['error' => 'Article not found']);
            return;
        }

        // Get user ID if logged in, otherwise use IP
        $userId = $_SESSION['user_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $result = HelpArticle::recordFeedback($article['id'], $helpful, $userId, $ipAddress);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Feedback recorded']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Feedback already submitted']);
            }
        } catch (\Exception) {
            // Feedback table may not exist yet
            echo json_encode(['success' => true, 'message' => 'Feedback noted']);
        }
    }
}
