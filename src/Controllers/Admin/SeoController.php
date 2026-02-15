<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Models\SeoMetadata;
use Nexus\Models\SeoRedirect;

class SeoController
{
    /**
     * Show Global SEO Settings
     */
    public function index()
    {
        $this->checkAdmin();

        // Fetch Global Settings (entity_id = null)
        $globalSeo = SeoMetadata::get('global', null);

        View::render('admin/seo/index', [
            'pageTitle' => 'Global SEO Settings',
            'seo' => $globalSeo
        ]);
    }

    /**
     * Save Global SEO Settings
     */
    public function store()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $data = [
            'meta_title' => $_POST['meta_title'],
            'meta_description' => $_POST['meta_description'],
            'og_image_url' => $_POST['og_image_url'],
            'noindex' => isset($_POST['noindex'])
        ];

        SeoMetadata::save('global', null, $data);

        // Clear SEO cache after saving
        \Nexus\Core\SEO::clearCache();

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/seo?saved=1');
    }

    /**
     * SEO Health Audit Dashboard
     */
    public function audit()
    {
        $this->checkAdmin();

        $tenantId = TenantContext::getId();
        $issues = [];

        // Check Listings
        $listings = Database::query(
            "SELECT l.id, l.title, l.description, s.meta_title, s.meta_description
             FROM listings l
             LEFT JOIN seo_metadata s ON s.entity_type = 'listing' AND s.entity_id = l.id
             WHERE l.tenant_id = ?",
            [$tenantId]
        )->fetchAll();
        foreach ($listings as $item) {
            $title = $item['meta_title'] ?: $item['title'];
            $desc = $item['meta_description'] ?: '';
            if (strlen($title) < 30) {
                $issues[] = ['type' => 'listing', 'id' => $item['id'], 'title' => $item['title'], 'issue' => 'Title too short (' . strlen($title) . ' chars)', 'severity' => 'warning'];
            }
            if (strlen($title) > 60) {
                $issues[] = ['type' => 'listing', 'id' => $item['id'], 'title' => $item['title'], 'issue' => 'Title too long (' . strlen($title) . ' chars)', 'severity' => 'warning'];
            }
            if (empty($desc)) {
                $issues[] = ['type' => 'listing', 'id' => $item['id'], 'title' => $item['title'], 'issue' => 'Missing meta description', 'severity' => 'error'];
            }
        }

        // Check Events
        $events = Database::query(
            "SELECT e.id, e.title, e.description, s.meta_title, s.meta_description
             FROM events e
             LEFT JOIN seo_metadata s ON s.entity_type = 'event' AND s.entity_id = e.id
             WHERE e.tenant_id = ?",
            [$tenantId]
        )->fetchAll();
        foreach ($events as $item) {
            $title = $item['meta_title'] ?: $item['title'];
            $desc = $item['meta_description'] ?: '';
            if (strlen($title) < 30) {
                $issues[] = ['type' => 'event', 'id' => $item['id'], 'title' => $item['title'], 'issue' => 'Title too short (' . strlen($title) . ' chars)', 'severity' => 'warning'];
            }
            if (strlen($title) > 60) {
                $issues[] = ['type' => 'event', 'id' => $item['id'], 'title' => $item['title'], 'issue' => 'Title too long (' . strlen($title) . ' chars)', 'severity' => 'warning'];
            }
            if (empty($desc)) {
                $issues[] = ['type' => 'event', 'id' => $item['id'], 'title' => $item['title'], 'issue' => 'Missing meta description', 'severity' => 'error'];
            }
        }

        // Check Groups
        $groups = Database::query(
            "SELECT g.id, g.name as title, g.description, s.meta_title, s.meta_description
             FROM `groups` g
             LEFT JOIN seo_metadata s ON s.entity_type = 'group' AND s.entity_id = g.id
             WHERE g.tenant_id = ?",
            [$tenantId]
        )->fetchAll();
        foreach ($groups as $item) {
            $title = $item['meta_title'] ?: $item['title'];
            $desc = $item['meta_description'] ?: '';
            if (strlen($title) < 30) {
                $issues[] = ['type' => 'group', 'id' => $item['id'], 'title' => $item['title'], 'issue' => 'Title too short (' . strlen($title) . ' chars)', 'severity' => 'warning'];
            }
            if (empty($desc)) {
                $issues[] = ['type' => 'group', 'id' => $item['id'], 'title' => $item['title'], 'issue' => 'Missing meta description', 'severity' => 'error'];
            }
        }

        // Calculate score
        $totalItems = count($listings) + count($events) + count($groups);
        $errorCount = count(array_filter($issues, fn($i) => $i['severity'] === 'error'));
        $warningCount = count(array_filter($issues, fn($i) => $i['severity'] === 'warning'));
        $score = $totalItems > 0 ? max(0, 100 - ($errorCount * 10) - ($warningCount * 3)) : 100;

        View::render('admin/seo/audit', [
            'pageTitle' => 'SEO Health Audit',
            'issues' => $issues,
            'score' => $score,
            'totalItems' => $totalItems,
            'errorCount' => $errorCount,
            'warningCount' => $warningCount
        ]);
    }

    /**
     * Bulk SEO Editor
     */
    public function bulkEdit($type = 'listing')
    {
        $this->checkAdmin();

        $tenantId = TenantContext::getId();
        $items = [];

        switch ($type) {
            case 'listing':
                $items = Database::query(
                    "SELECT l.id, l.title, l.description, s.meta_title, s.meta_description, s.noindex
                     FROM listings l
                     LEFT JOIN seo_metadata s ON s.entity_type = 'listing' AND s.entity_id = l.id
                     WHERE l.tenant_id = ? ORDER BY l.created_at DESC",
                    [$tenantId]
                )->fetchAll();
                break;
            case 'event':
                $items = Database::query(
                    "SELECT e.id, e.title, e.description, s.meta_title, s.meta_description, s.noindex
                     FROM events e
                     LEFT JOIN seo_metadata s ON s.entity_type = 'event' AND s.entity_id = e.id
                     WHERE e.tenant_id = ? ORDER BY e.created_at DESC",
                    [$tenantId]
                )->fetchAll();
                break;
            case 'group':
                $items = Database::query(
                    "SELECT g.id, g.name as title, g.description, s.meta_title, s.meta_description, s.noindex
                     FROM `groups` g
                     LEFT JOIN seo_metadata s ON s.entity_type = 'group' AND s.entity_id = g.id
                     WHERE g.tenant_id = ? ORDER BY g.created_at DESC",
                    [$tenantId]
                )->fetchAll();
                break;
            case 'post':
                $items = Database::query(
                    "SELECT p.id, p.title, p.content as description, s.meta_title, s.meta_description, s.noindex
                     FROM posts p
                     LEFT JOIN seo_metadata s ON s.entity_type = 'post' AND s.entity_id = p.id
                     WHERE p.tenant_id = ? ORDER BY p.created_at DESC",
                    [$tenantId]
                )->fetchAll();
                break;
        }

        View::render('admin/seo/bulk-edit', [
            'pageTitle' => 'Bulk SEO Editor - ' . ucfirst($type) . 's',
            'type' => $type,
            'items' => $items
        ]);
    }

    /**
     * AJAX: Save single item SEO
     */
    public function bulkSave()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        header('Content-Type: application/json');

        $type = $_POST['type'] ?? '';
        $id = intval($_POST['id'] ?? 0);

        if (!$type || !$id) {
            echo json_encode(['success' => false, 'error' => 'Missing type or id']);
            return;
        }

        $data = [
            'meta_title' => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? ''),
            'noindex' => isset($_POST['noindex'])
        ];

        SeoMetadata::save($type, $id, $data);

        echo json_encode(['success' => true]);
    }

    /**
     * 301 Redirect Manager
     */
    public function redirects()
    {
        $this->checkAdmin();

        $redirects = SeoRedirect::all();

        View::render('admin/seo/redirects', [
            'pageTitle' => '301 Redirects',
            'redirects' => $redirects
        ]);
    }

    /**
     * Store new redirect
     */
    public function storeRedirect()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $sourceUrl = trim($_POST['source_url'] ?? '');
        $destinationUrl = trim($_POST['destination_url'] ?? '');

        if ($sourceUrl && $destinationUrl) {
            SeoRedirect::create($sourceUrl, $destinationUrl);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/seo/redirects?saved=1');
    }

    /**
     * Delete redirect
     */
    public function deleteRedirect()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            SeoRedirect::delete($id);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/seo/redirects?deleted=1');
    }

    /**
     * Organization Schema Settings
     */
    public function organization()
    {
        $this->checkAdmin();

        $tenantId = TenantContext::getId();
        $tenant = TenantContext::get();

        // Get organization settings from tenant config or defaults
        $orgSettings = [
            'name' => $tenant['name'] ?? '',
            'logo_url' => $tenant['logo_url'] ?? '',
            'description' => $tenant['description'] ?? '',
            'email' => $tenant['contact_email'] ?? '',
            'phone' => $tenant['contact_phone'] ?? '',
            'address' => $tenant['address'] ?? '',
            'social_facebook' => $tenant['social_facebook'] ?? '',
            'social_twitter' => $tenant['social_twitter'] ?? '',
            'social_instagram' => $tenant['social_instagram'] ?? '',
            'social_linkedin' => $tenant['social_linkedin'] ?? '',
            'social_youtube' => $tenant['social_youtube'] ?? ''
        ];

        View::render('admin/seo/organization', [
            'pageTitle' => 'Organization Schema',
            'org' => $orgSettings
        ]);
    }

    /**
     * Save Organization Schema Settings
     */
    public function saveOrganization()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();

        // Update tenant settings
        $updates = [
            'description' => $_POST['description'] ?? '',
            'contact_email' => $_POST['email'] ?? '',
            'contact_phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'social_facebook' => $_POST['social_facebook'] ?? '',
            'social_twitter' => $_POST['social_twitter'] ?? '',
            'social_instagram' => $_POST['social_instagram'] ?? '',
            'social_linkedin' => $_POST['social_linkedin'] ?? '',
            'social_youtube' => $_POST['social_youtube'] ?? ''
        ];

        // Build SET clause dynamically
        $setClauses = [];
        $params = [];
        foreach ($updates as $key => $value) {
            $setClauses[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $tenantId;

        Database::query(
            "UPDATE tenants SET " . implode(', ', $setClauses) . " WHERE id = ?",
            $params
        );

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/seo/organization?saved=1');
    }

    /**
     * Ping Sitemaps to Search Engines
     */
    public function pingSitemaps()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $sitemapUrl = TenantContext::getBaseUrl() . '/sitemap.xml';
        $results = [];

        // Ping Google
        $googleUrl = 'https://www.google.com/ping?sitemap=' . urlencode($sitemapUrl);
        $googleResult = @file_get_contents($googleUrl);
        $results['google'] = $googleResult !== false;

        // Ping Bing
        $bingUrl = 'https://www.bing.com/ping?sitemap=' . urlencode($sitemapUrl);
        $bingResult = @file_get_contents($bingUrl);
        $results['bing'] = $bingResult !== false;

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'results' => $results]);
    }

    private function checkAdmin()
    {
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);
        if (!$isAdmin) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }
}
