<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\HtmlSanitizer;

class PageController
{
    private function checkAdmin($jsonResponse = false)
    {
        if (!isset($_SESSION['user_id'])) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }
            header('HTTP/1.0 403 Forbidden');
            echo "Access Denied";
            exit;
        }
    }

    public function index()
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();

        $pages = Database::query(
            "SELECT * FROM pages WHERE tenant_id = ? ORDER BY sort_order ASC, created_at DESC",
            [$tenantId]
        )->fetchAll();

        View::render('admin/pages/index', [
            'pages' => $pages
        ]);
    }

    public function create()
    {
        $this->checkAdmin();

        // Only create pages via POST request or confirmed GET to prevent accidental drafts
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if (!isset($_GET['confirm']) || $_GET['confirm'] !== '1') {
                header('Location: ' . TenantContext::getBasePath() . '/admin/pages');
                exit;
            }
        }

        $tenantId = TenantContext::getId();

        // Get max sort order
        $maxOrder = Database::query(
            "SELECT MAX(sort_order) as max_order FROM pages WHERE tenant_id = ?",
            [$tenantId]
        )->fetch();
        $sortOrder = ($maxOrder['max_order'] ?? 0) + 1;

        // Create Draft with unique slug
        $title = "Untitled Page " . date('Y-m-d H:i');
        $slug = 'page-' . time() . '-' . bin2hex(random_bytes(4));

        Database::query(
            "INSERT INTO pages (tenant_id, title, slug, content, is_published, sort_order, created_at) VALUES (?, ?, ?, '', 0, ?, NOW())",
            [$tenantId, $title, $slug, $sortOrder]
        );

        $id = Database::lastInsertId();

        // Initialize SEO
        \Nexus\Models\SeoMetadata::save('page', $id, ['meta_title' => '', 'meta_description' => '', 'noindex' => 0]);

        header('Location: ' . TenantContext::getBasePath() . '/admin/pages/builder/' . $id);
    }

    public function builder($id)
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();

        $page = Database::query("SELECT * FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();

        if (!$page) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/pages');
            exit;
        }

        $seo = \Nexus\Models\SeoMetadata::get('page', $id);

        // Get version count for UI
        $versionCount = Database::query(
            "SELECT COUNT(*) as count FROM page_versions WHERE page_id = ?",
            [$id]
        )->fetch();

        // Use new V2 builder
        View::render('admin/pages/builder-v2', [
            'page' => $page,
            'seo' => $seo,
            'versionCount' => $versionCount['count'] ?? 0
        ]);
    }

    /**
     * API: Save page blocks (V2)
     */
    public function saveBlocks($id)
    {
        $this->checkAdmin(true);
        $tenantId = TenantContext::getId();

        // Verify page belongs to tenant
        $page = Database::query("SELECT id FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
        if (!$page) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Page not found']);
            exit;
        }

        // Get blocks from request
        $input = json_decode(file_get_contents('php://input'), true);
        $blocks = $input['blocks'] ?? [];

        // Save blocks using PageRenderer
        $success = \Nexus\PageBuilder\PageRenderer::saveBlocks($id, $blocks);

        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
    }

    /**
     * API: Get page blocks (V2)
     */
    public function getBlocks($id)
    {
        $this->checkAdmin(true);
        $tenantId = TenantContext::getId();

        // Verify page belongs to tenant
        $page = Database::query("SELECT id FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
        if (!$page) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Page not found']);
            exit;
        }

        $blocks = \Nexus\PageBuilder\PageRenderer::getBlocks($id);

        header('Content-Type: application/json');
        echo json_encode(['blocks' => $blocks]);
    }

    /**
     * API: Save page settings (V2)
     */
    public function saveSettings($id)
    {
        header('Content-Type: application/json');
        $this->checkAdmin(true);

        try {
            $tenantId = TenantContext::getId();

            // Verify page ownership
            $page = Database::query("SELECT * FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
            if (!$page) {
                echo json_encode(['success' => false, 'error' => 'Page not found']);
                exit;
            }

            // Get input
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate and sanitize
            $title = trim($input['title'] ?? 'Untitled');
            $slug = $this->sanitizeSlug($input['slug'] ?? 'page-' . $id);
            $isPublished = isset($input['is_published']) ? (int)$input['is_published'] : 0;
            $showInMenu = isset($input['show_in_menu']) ? (int)$input['show_in_menu'] : 0;
            $menuLocation = $input['menu_location'] ?? 'about';

            // Validate menu location
            $validLocations = ['about', 'main', 'footer'];
            if (!in_array($menuLocation, $validLocations)) {
                $menuLocation = 'about';
            }

            // Validate title
            if (empty($title)) {
                echo json_encode(['success' => false, 'error' => 'Title cannot be empty']);
                exit;
            }

            // Validate slug uniqueness
            if ($slug !== $page['slug']) {
                $slugExists = Database::query(
                    "SELECT id FROM pages WHERE slug = ? AND tenant_id = ? AND id != ?",
                    [$slug, $tenantId, $id]
                )->fetch();

                if ($slugExists) {
                    echo json_encode(['success' => false, 'error' => 'This URL slug is already in use']);
                    exit;
                }
            }

            // Prevent path traversal
            if (strpos($slug, '..') !== false || strpos($slug, '/') !== false) {
                echo json_encode(['success' => false, 'error' => 'Invalid slug format']);
                exit;
            }

            // Update page
            try {
                Database::query(
                    "UPDATE pages SET title = ?, slug = ?, is_published = ?, show_in_menu = ?, menu_location = ?, updated_at = NOW()
                     WHERE id = ? AND tenant_id = ?",
                    [$title, $slug, $isPublished, $showInMenu, $menuLocation, $id, $tenantId]
                );
            } catch (\Exception $e) {
                // Fallback: columns might not exist
                Database::query(
                    "UPDATE pages SET title = ?, slug = ?, is_published = ?, updated_at = NOW()
                     WHERE id = ? AND tenant_id = ?",
                    [$title, $slug, $isPublished, $id, $tenantId]
                );
            }

            echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
            exit;

        } catch (\Exception $e) {
            error_log("Page settings save error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to save settings']);
            exit;
        }
    }

    /**
     * API: Preview a single block (V2)
     */
    public function previewBlock()
    {
        $this->checkAdmin(true);

        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? '';
        $data = $input['data'] ?? [];

        $html = \Nexus\PageBuilder\PageRenderer::previewBlock($type, $data);

        header('Content-Type: application/json');
        echo json_encode(['html' => $html]);
    }

    public function preview($id)
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();

        $page = Database::query("SELECT * FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();

        if (!$page) {
            http_response_code(404);
            echo "Page not found";
            exit;
        }

        // Render preview (bypasses is_published check)
        View::render('admin/pages/preview', [
            'page' => $page,
            'content' => $page['content']
        ]);
    }

    public function save()
    {
        header('Content-Type: application/json');

        $this->checkAdmin(true);

        try {
            $id = $_POST['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'No page ID provided']);
                exit;
            }

            $tenantId = TenantContext::getId();
            $userId = $_SESSION['user_id'] ?? null;

            // Verify the page exists and belongs to this tenant
            $existingPage = Database::query("SELECT * FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
            if (!$existingPage) {
                echo json_encode(['success' => false, 'error' => 'Page not found']);
                exit;
            }

            // Build Data with validation
            $title = trim($_POST['title'] ?? 'Untitled');
            $slug = $this->sanitizeSlug($_POST['slug'] ?? 'page-' . $id);
            $html = $_POST['html'] ?? '';
            $isPublished = isset($_POST['is_published']) ? 1 : 0;
            $publishAt = !empty($_POST['publish_at']) ? $_POST['publish_at'] : null;
            $isAutosave = isset($_POST['autosave']) && $_POST['autosave'] === '1';
            $showInMenu = isset($_POST['show_in_menu']) ? (int)$_POST['show_in_menu'] : 0;
            $menuLocation = $_POST['menu_location'] ?? 'about';

            // Validate menu location
            $validLocations = ['about', 'main', 'footer', 'none'];
            if (!in_array($menuLocation, $validLocations)) {
                $menuLocation = 'about';
            }

            // Validate title
            if (empty($title)) {
                $title = 'Untitled';
            }
            if (mb_strlen($title) > 255) {
                $title = mb_substr($title, 0, 255);
            }

            // Validate slug uniqueness (if changed)
            if ($slug !== $existingPage['slug']) {
                $slugExists = Database::query(
                    "SELECT id FROM pages WHERE slug = ? AND tenant_id = ? AND id != ?",
                    [$slug, $tenantId, $id]
                )->fetch();

                if ($slugExists) {
                    echo json_encode(['success' => false, 'error' => 'This URL slug is already in use. Please choose a different one.']);
                    exit;
                }
            }

            // Sanitize HTML content (allows safe HTML for page builder)
            $html = HtmlSanitizer::sanitize($html);

            // Save version before updating (only for non-autosave and if content changed)
            if (!$isAutosave && $existingPage['content'] !== $html) {
                $this->saveVersion($id, $tenantId, $existingPage, $userId);
            }

            // Update the page (gracefully handle missing columns)
            try {
                Database::query(
                    "UPDATE pages SET title = ?, slug = ?, content = ?, is_published = ?, publish_at = ?, show_in_menu = ?, menu_location = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                    [$title, $slug, $html, $isPublished, $publishAt, $showInMenu, $menuLocation, $id, $tenantId]
                );
            } catch (\Exception $e) {
                // Fallback: columns don't exist yet, save without menu fields
                Database::query(
                    "UPDATE pages SET title = ?, slug = ?, content = ?, is_published = ?, publish_at = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                    [$title, $slug, $html, $isPublished, $publishAt, $id, $tenantId]
                );
            }

            // Save SEO metadata (skip for autosave to reduce DB writes)
            if (!$isAutosave) {
                $seoData = [
                    'meta_title' => trim($_POST['meta_title'] ?? ''),
                    'meta_description' => trim($_POST['meta_description'] ?? ''),
                    'noindex' => isset($_POST['noindex'])
                ];

                // Validate SEO data lengths
                if (mb_strlen($seoData['meta_title']) > 255) {
                    $seoData['meta_title'] = mb_substr($seoData['meta_title'], 0, 255);
                }
                if (mb_strlen($seoData['meta_description']) > 500) {
                    $seoData['meta_description'] = mb_substr($seoData['meta_description'], 0, 500);
                }

                \Nexus\Models\SeoMetadata::save('page', $id, $seoData);
            }

            $message = $isAutosave ? 'Draft saved' : 'Page saved successfully';
            echo json_encode(['success' => true, 'message' => $message, 'autosave' => $isAutosave]);
            exit;

        } catch (\Exception $e) {
            error_log("Page save error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to save page. Please try again.']);
            exit;
        }
    }

    /**
     * Save a version of the page for history
     */
    private function saveVersion($pageId, $tenantId, $pageData, $userId)
    {
        // Get current max version number
        $maxVersion = Database::query(
            "SELECT MAX(version_number) as max_ver FROM page_versions WHERE page_id = ?",
            [$pageId]
        )->fetch();
        $versionNumber = ($maxVersion['max_ver'] ?? 0) + 1;

        // Insert version
        Database::query(
            "INSERT INTO page_versions (page_id, tenant_id, version_number, title, slug, content, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$pageId, $tenantId, $versionNumber, $pageData['title'], $pageData['slug'], $pageData['content'], $userId]
        );

        // Keep only last 20 versions per page
        Database::query(
            "DELETE FROM page_versions WHERE page_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM page_versions WHERE page_id = ? ORDER BY version_number DESC LIMIT 20) as keep_versions)",
            [$pageId, $pageId]
        );
    }

    public function versions($id)
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();

        // Verify page ownership
        $page = Database::query("SELECT * FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
        if (!$page) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/pages');
            exit;
        }

        // Get versions with user info
        $versions = Database::query(
            "SELECT v.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name
             FROM page_versions v
             LEFT JOIN users u ON v.created_by = u.id
             WHERE v.page_id = ?
             ORDER BY v.version_number DESC",
            [$id]
        )->fetchAll();

        View::render('admin/pages/versions', [
            'page' => $page,
            'versions' => $versions
        ]);
    }

    public function versionContent($versionId)
    {
        header('Content-Type: application/json');
        $this->checkAdmin(true);

        try {
            $tenantId = TenantContext::getId();

            // Get version with tenant check
            $version = Database::query(
                "SELECT v.* FROM page_versions v
                 JOIN pages p ON v.page_id = p.id
                 WHERE v.id = ? AND p.tenant_id = ?",
                [$versionId, $tenantId]
            )->fetch();

            if (!$version) {
                echo json_encode(['success' => false, 'error' => 'Version not found']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'version' => [
                    'id' => $version['id'],
                    'version_number' => $version['version_number'],
                    'title' => $version['title'],
                    'slug' => $version['slug'],
                    'content' => HtmlSanitizer::sanitize($version['content']),
                    'created_at' => date('M j, Y g:i A', strtotime($version['created_at']))
                ]
            ]);
            exit;

        } catch (\Exception $e) {
            error_log("Version content error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to load version']);
            exit;
        }
    }

    public function restoreVersion()
    {
        header('Content-Type: application/json');
        $this->checkAdmin(true);

        try {
            $versionId = $_POST['version_id'] ?? null;
            $pageId = $_POST['page_id'] ?? null;

            if (!$versionId || !$pageId) {
                echo json_encode(['success' => false, 'error' => 'Missing parameters']);
                exit;
            }

            $tenantId = TenantContext::getId();
            $userId = $_SESSION['user_id'] ?? null;

            // Get current page (to save as version before restore)
            $currentPage = Database::query("SELECT * FROM pages WHERE id = ? AND tenant_id = ?", [$pageId, $tenantId])->fetch();
            if (!$currentPage) {
                echo json_encode(['success' => false, 'error' => 'Page not found']);
                exit;
            }

            // Get version to restore
            $version = Database::query("SELECT * FROM page_versions WHERE id = ? AND page_id = ?", [$versionId, $pageId])->fetch();
            if (!$version) {
                echo json_encode(['success' => false, 'error' => 'Version not found']);
                exit;
            }

            // Save current state as new version before restoring
            $this->saveVersion($pageId, $tenantId, $currentPage, $userId);

            // Restore the version
            Database::query(
                "UPDATE pages SET title = ?, slug = ?, content = ?, updated_at = NOW() WHERE id = ?",
                [$version['title'], $version['slug'], $version['content'], $pageId]
            );

            echo json_encode(['success' => true, 'message' => 'Version restored successfully']);
            exit;

        } catch (\Exception $e) {
            error_log("Version restore error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to restore version']);
            exit;
        }
    }

    public function duplicate($id)
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();

        // Get original page
        $original = Database::query("SELECT * FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
        if (!$original) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/pages');
            exit;
        }

        // Generate new slug
        $newSlug = $original['slug'] . '-copy-' . time();
        $newTitle = $original['title'] . ' (Copy)';

        // Get max sort order
        $maxOrder = Database::query(
            "SELECT MAX(sort_order) as max_order FROM pages WHERE tenant_id = ?",
            [$tenantId]
        )->fetch();
        $sortOrder = ($maxOrder['max_order'] ?? 0) + 1;

        // Create duplicate
        Database::query(
            "INSERT INTO pages (tenant_id, title, slug, content, is_published, sort_order, created_at) VALUES (?, ?, ?, ?, 0, ?, NOW())",
            [$tenantId, $newTitle, $newSlug, $original['content'], $sortOrder]
        );

        $newId = Database::lastInsertId();

        // Copy SEO metadata
        $originalSeo = \Nexus\Models\SeoMetadata::get('page', $id);
        if ($originalSeo) {
            \Nexus\Models\SeoMetadata::save('page', $newId, [
                'meta_title' => $originalSeo['meta_title'] ?? '',
                'meta_description' => $originalSeo['meta_description'] ?? '',
                'noindex' => $originalSeo['noindex'] ?? 0
            ]);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/pages/builder/' . $newId);
    }

    public function reorder()
    {
        header('Content-Type: application/json');
        $this->checkAdmin(true);

        try {
            $order = $_POST['order'] ?? [];
            if (empty($order) || !is_array($order)) {
                echo json_encode(['success' => false, 'error' => 'Invalid order data']);
                exit;
            }

            $tenantId = TenantContext::getId();

            foreach ($order as $position => $pageId) {
                Database::query(
                    "UPDATE pages SET sort_order = ? WHERE id = ? AND tenant_id = ?",
                    [$position, $pageId, $tenantId]
                );
            }

            echo json_encode(['success' => true, 'message' => 'Order updated']);
            exit;

        } catch (\Exception $e) {
            error_log("Reorder error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to update order']);
            exit;
        }
    }

    public function delete()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['page_id'] ?? null;
        if (!$id) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/pages');
            exit;
        }

        $tenantId = TenantContext::getId();

        // Verify ownership
        $page = Database::query("SELECT id FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();

        if ($page) {
            // Delete versions first (foreign key constraint)
            Database::query("DELETE FROM page_versions WHERE page_id = ?", [$id]);

            // Delete the page
            Database::query("DELETE FROM pages WHERE id = ?", [$id]);

            // Clean up SEO metadata
            Database::query("DELETE FROM seo_metadata WHERE entity_type = 'page' AND entity_id = ? AND tenant_id = ?", [$id, $tenantId]);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/pages');
    }

    /**
     * Sanitize a URL slug
     */
    private function sanitizeSlug(string $slug): string
    {
        $slug = mb_strtolower($slug);
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = 'page-' . time();
        }

        if (strlen($slug) > 100) {
            $slug = substr($slug, 0, 100);
            $slug = rtrim($slug, '-');
        }

        return $slug;
    }
}
