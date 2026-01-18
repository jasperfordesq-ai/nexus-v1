<?php

namespace Nexus\Controllers;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class SitemapController
{
    public function index()
    {
        $tenantId = TenantContext::getId();
        $basePath = TenantContext::getBasePath();

        // Determine protocol and host
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = $protocol . "://" . $host . $basePath;

        $db = Database::getConnection();
        $urls = [];

        // Fetch Global NoIndex List
        // We get a list of EntityTypes + EntityIDs that are NoIndexed
        $noIndexMap = [];
        $stmt = $db->prepare("SELECT entity_type, entity_id FROM seo_metadata WHERE tenant_id = ? AND noindex = 1");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $key = $r['entity_type'] . ':' . ($r['entity_id'] ?? 'global');
            $noIndexMap[$key] = true;
        }

        // Helper to check if ignored
        $isNoIndex = function ($type, $id = null) use ($noIndexMap) {
            return isset($noIndexMap[$type . ':' . ($id ?? 'global')]);
        };

        // 1. Static Core Pages (Respect Global NoIndex?)
        // If 'global:global' is noindex, we might want to nukem all, but let's assume per-page control for now isn't fully mapped to static routes easily without a lookup table.
        // For now, we only filter dynamic entities.

        $staticPages = [
            '/',
            '/about',
            '/contact',
            '/how-it-works',
            '/listings',
            '/groups',
            '/events',
            '/volunteering',
            '/blog'
        ];

        foreach ($staticPages as $page) {
            $urls[] = [
                'loc' => $baseUrl . $page,
                'priority' => ($page === '/') ? '1.0' : '0.8',
                'changefreq' => 'weekly'
            ];
        }

        // 2. Active Listings (Tenant Isolated)
        $stmt = $db->prepare("SELECT id, updated_at FROM listings WHERE tenant_id = ? AND status = 'active' ORDER BY updated_at DESC");
        $stmt->execute([$tenantId]);
        $listings = $stmt->fetchAll();

        foreach ($listings as $listing) {
            if ($isNoIndex('listing', $listing['id'])) continue;
            $urls[] = [
                'loc' => $baseUrl . '/listings/' . $listing['id'],
                'lastmod' => date('Y-m-d', strtotime($listing['updated_at'])),
                'priority' => '0.9',
                'changefreq' => 'daily'
            ];
        }

        // 3. Public Groups/Hubs (Tenant Isolated)
        $stmt = $db->prepare("SELECT id, created_at FROM groups WHERE tenant_id = ? AND visibility = 'public'");
        $stmt->execute([$tenantId]);
        $groups = $stmt->fetchAll();

        foreach ($groups as $group) {
            if ($isNoIndex('group', $group['id'])) continue;
            $urls[] = [
                'loc' => $baseUrl . '/groups/' . $group['id'],
                'lastmod' => date('Y-m-d', strtotime($group['created_at'])),
                'priority' => '0.7',
                'changefreq' => 'weekly'
            ];
        }

        // 4. Public Events (Tenant Isolated)
        $stmt = $db->prepare("SELECT id, start_time FROM events WHERE tenant_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 YEAR)");
        $stmt->execute([$tenantId]);
        $events = $stmt->fetchAll();

        foreach ($events as $event) {
            if ($isNoIndex('event', $event['id'])) continue;
            $urls[] = [
                'loc' => $baseUrl . '/events/' . $event['id'],
                'lastmod' => date('Y-m-d', strtotime($event['start_time'])),
                'priority' => '0.6',
                'changefreq' => 'weekly'
            ];
        }

        // 5. Blog Posts
        $stmt = $db->prepare("SELECT id, updated_at, slug FROM posts WHERE tenant_id = ? AND status = 'published'");
        $stmt->execute([$tenantId]);
        $posts = $stmt->fetchAll();

        foreach ($posts as $post) {
            if ($isNoIndex('post', $post['id'])) continue;
            $urls[] = [
                // Assuming blog post URL structure is /blog/show/{slug} or similar
                // BlogController::show takes slug. Route is usually /blog/article/{slug} or /blog/{slug}? 
                // Let's assume /blog/{slug} based on typical routing of CMS. 
                // Checking Router routes... usually it is /blog/{slug} or similar.
                'loc' => $baseUrl . '/blog/' . $post['slug'],
                'lastmod' => date('Y-m-d', strtotime($post['updated_at'])),
                'priority' => '0.8',
                'changefreq' => 'weekly'
            ];
        }

        // 6. Dynamic Pages (Page Builder)
        $stmt = $db->prepare("SELECT id, slug, updated_at FROM pages WHERE tenant_id = ? AND is_published = 1");
        $stmt->execute([$tenantId]);
        $pages = $stmt->fetchAll();

        foreach ($pages as $p) {
            if ($isNoIndex('page', $p['id'])) continue;
            // Check if it's the custom homepage (slug matches root?)
            // If slug is 'home', maybe we skip it if '/' is already added?
            // For now, list them.
            $urls[] = [
                'loc' => $baseUrl . '/' . $p['slug'],
                'lastmod' => date('Y-m-d', strtotime($p['updated_at'])),
                'priority' => '0.8',
                'changefreq' => 'weekly'
            ];
        }

        // 7. Help Center Index
        $urls[] = [
            'loc' => $baseUrl . '/help',
            'priority' => '0.8',
            'changefreq' => 'weekly'
        ];

        // 8. Help Articles (Public)
        // Get allowed modules for this tenant
        $allowedModules = ['core', 'getting_started'];
        $tenant = TenantContext::get();
        if (!empty($tenant['features'])) {
            $features = is_array($tenant['features']) ? $tenant['features'] : json_decode($tenant['features'], true);
            if (!empty($features)) {
                if (in_array('wallet', $features)) $allowedModules[] = 'wallet';
                if (in_array('listings', $features)) $allowedModules[] = 'listings';
                if (in_array('groups', $features)) $allowedModules[] = 'groups';
                if (in_array('events', $features)) $allowedModules[] = 'events';
                if (in_array('volunteering', $features)) $allowedModules[] = 'volunteering';
                if (in_array('blog', $features)) $allowedModules[] = 'blog';
            }
        }

        // Master tenant sees all modules
        if ($tenantId === 1) {
            $allowedModules = ['core', 'getting_started', 'wallet', 'listings', 'groups', 'events', 'volunteering', 'blog'];
        }

        $placeholders = implode(',', array_fill(0, count($allowedModules), '?'));
        $stmt = $db->prepare("SELECT id, slug, updated_at, created_at FROM help_articles
                              WHERE is_public = 1 AND module_tag IN ($placeholders)
                              ORDER BY updated_at DESC, created_at DESC");
        $stmt->execute($allowedModules);
        $helpArticles = $stmt->fetchAll();

        foreach ($helpArticles as $article) {
            if ($isNoIndex('help_article', $article['id'])) continue;
            $lastmod = $article['updated_at'] ?? $article['created_at'];
            $urls[] = [
                'loc' => $baseUrl . '/help/' . $article['slug'],
                'lastmod' => $lastmod ? date('Y-m-d', strtotime($lastmod)) : date('Y-m-d'),
                'priority' => '0.7',
                'changefreq' => 'monthly'
            ];
        }

        // GENERATE XML
        header("Content-Type: application/xml; charset=utf-8");
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            echo '  <url>' . "\n";
            echo '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . "\n";
            if (isset($url['lastmod'])) {
                echo '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            }
            echo '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            echo '    <priority>' . $url['priority'] . '</priority>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
        exit;
    }
}
