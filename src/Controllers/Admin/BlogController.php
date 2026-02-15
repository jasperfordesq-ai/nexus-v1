<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

class BlogController
{
    private function checkAdmin()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            header('HTTP/1.0 403 Forbidden');
            echo "Access Denied";
            exit;
        }
    }

    public function index()
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();

        // Join with users to get author name
        $posts = Database::query(
            "
            SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as author_name 
            FROM posts p 
            LEFT JOIN users u ON p.author_id = u.id 
            WHERE p.tenant_id = ? 
            ORDER BY p.created_at DESC",
            [$tenantId]
        )->fetchAll();

        View::render('admin/blog/index', [
            'posts' => $posts
        ]);
    }

    public function create()
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];

        Database::query(
            "INSERT INTO posts (tenant_id, author_id, title, slug, content, status, created_at) VALUES (?, ?, 'New Draft', ?, '', 'draft', NOW())",
            [$tenantId, $userId, 'draft-' . time() . '-' . bin2hex(random_bytes(4))]
        );
        $id = Database::lastInsertId();

        // Initialize SEO (empty)
        \Nexus\Models\SeoMetadata::save('post', $id, ['meta_title' => '', 'meta_description' => '', 'noindex' => 0]);

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/news/builder/' . $id);
    }

    public function edit($id)
    {
        $this->checkAdmin();
        // Redirect to builder (unified interface)
        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/news/builder/' . $id);
        exit;
    }

    public function update()
    {
        // Legacy update method - strictly speaking not needed if using builder, 
        // but kept for fallback or API calls if any.
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['post_id'];
        $title = $_POST['title'];
        $slug = $_POST['slug'] ?: strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $content = $_POST['content'];
        $status = $_POST['status'];
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE posts SET title = ?, slug = ?, content = ?, status = ? WHERE id = ? AND tenant_id = ?",
            [$title, $slug, $content, $status, $id, $tenantId]
        );

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/news');
    }

    public function delete($id)
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();
        Database::query("DELETE FROM posts WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/news');
    }

    public function builder($id)
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();
        $post = Database::query("SELECT * FROM posts WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();

        if (!$post) {
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/news');
            exit;
        }

        // Load SEO for builder
        $seo = \Nexus\Models\SeoMetadata::get('post', $id);

        // Load Categories
        $categories = \Nexus\Models\Category::all();

        View::render('admin/blog/builder', [
            'post' => $post,
            'seo' => $seo,
            'categories' => $categories
        ]);
    }

    public function saveBuilder()
    {
        $this->checkAdmin();
        // Since it's an API-like call from GrapesJS, we return JSON

        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'No ID']);
            exit;
        }

        $tenantId = TenantContext::getId();
        $post = Database::query("SELECT category_id FROM posts WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();

        if (!$post) {
            echo json_encode(['success' => false, 'error' => 'Post not found']);
            exit;
        }

        $data = [
            'title' => $_POST['title'],
            'slug' => $_POST['slug'],
            'content' => $_POST['html'], // Save HTML as content too for fallback/search
            'content_json' => $_POST['json'],
            'html_render' => $_POST['html'],
            'status' => isset($_POST['is_published']) ? 'published' : 'draft',
            'excerpt' => $_POST['excerpt'] ?? '',
            'featured_image' => $_POST['featured_image'] ?? '',
            'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : $post['category_id']
        ];

        // SEO
        $seoData = [
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'noindex' => isset($_POST['noindex'])
        ];

        try {
            // We need a Post::update method or manual query. 
            // Previous code used manual query or Post model. 
            // Using manual query here for safety/speed as Post model is in main namespace.
            // Wait, previous code used Post::update. I should verify if I can use it or raw SQL.
            // Checking dependencies... previous full file used Post::update. 
            // I'll stick to raw SQL here to avoid dependency issues or use Post if imported.
            // I'll use raw SQL to be safe as I didn't import Post model in this snippet (except I did not? I only imported Admin ones).
            // Ah, I'll use raw SQL.

            Database::query(
                "UPDATE posts SET title = ?, slug = ?, content = ?, content_json = ?, html_render = ?, status = ?, excerpt = ?, featured_image = ?, category_id = ? WHERE id = ? AND tenant_id = ?",
                [
                    $data['title'],
                    $data['slug'],
                    $data['content'],
                    $data['content_json'],
                    $data['html_render'],
                    $data['status'],
                    $data['excerpt'],
                    $data['featured_image'],
                    $data['category_id'],
                    $id,
                    $tenantId
                ]
            );

            \Nexus\Models\SeoMetadata::save('post', $id, $seoData);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
