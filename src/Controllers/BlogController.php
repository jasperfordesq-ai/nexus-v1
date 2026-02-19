<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Models\Post;
use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;
use Nexus\Middleware\TenantModuleMiddleware;

class BlogController
{
    /**
     * Check if blog module is enabled
     */
    private function checkFeature()
    {
        TenantModuleMiddleware::require('blog');
    }

    // --- Public Methods ---

    public function index()
    {
        $this->checkFeature();
        // Public Blog Feed
        // Pagination logic could go here
        $page = $_GET['page'] ?? 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $posts = Post::getAll($limit, $offset, 'published');
        $totalPosts = Post::count('published');
        $totalPages = ceil($totalPosts / $limit);

        $tenantName = TenantContext::get()['name'];


        if (isset($_GET['partial'])) {
            View::render('blog/partials/feed-items', [
                'posts' => $posts
            ]);
            exit;
        }

        View::render('blog/news', [
            'pageTitle' => "Latest News - $tenantName",
            'posts' => $posts,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    }

    public function show($slug)
    {
        $this->checkFeature();
        $post = Post::findBySlug($slug);

        if (!$post || $post['status'] !== 'published') {
            // Allow admin to see drafts? For now, 404.
            $role = $_SESSION['user_role'] ?? '';
            $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);
            if (!$isAdmin) {
                http_response_code(404);
                View::render('error/404');
                exit;
            }
        }

        // Set Default SEO from Post
        \Nexus\Core\SEO::setTitle($post['title']);
        if (!empty($post['featured_image'])) {
            \Nexus\Core\SEO::setImage($post['featured_image']);
        }

        // Load Post Specific SEO (Overrides)
        \Nexus\Core\SEO::load('post', $post['id']);

        // Auto-generate description from content if not set
        $content = $post['excerpt'] ?? $post['content'] ?? '';
        \Nexus\Core\SEO::autoDescription($content);

        // Add JSON-LD Article Schema
        $author = null;
        if (!empty($post['user_id'])) {
            $author = \Nexus\Models\User::findById($post['user_id']);
        }
        \Nexus\Core\SEO::autoSchema('article', $post, $author);
        \Nexus\Core\SEO::setType('article');

        // Breadcrumbs
        \Nexus\Core\SEO::addBreadcrumbs([
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Blog', 'url' => '/blog'],
            ['name' => $post['title'], 'url' => '/blog/' . ($post['slug'] ?? $post['id'])]
        ]);

        // Check for builder content
        if (!empty($post['html_render'])) {
            $post['content'] = $post['html_render'];
            // Inject styles if needed, or assume they are in html_render <style> tags (Builder saves them there usually)
        }

        View::render('blog/show', [
            'pageTitle' => $post['title'],
            'post' => $post
        ]);
    }

    // --- Admin Methods ---

    public function adminIndex()
    {
        $this->checkAdmin();
        $posts = Post::getAll(100, 0, 'all'); // Show all for admin
        View::render('admin/blog/index', [
            'pageTitle' => 'Manage News',
            'posts' => $posts
        ]);
    }

    public function create()
    {
        $this->checkAdmin();
        $categories = \Nexus\Models\Category::all();
        View::render('admin/blog/form', [
            'pageTitle' => 'Create Article',
            'post' => null,
            'categories' => $categories,
            'seo' => [] // Empty for new
        ]);
    }

    public function createDraft()
    {
        $this->checkAdmin();
        // Create an empty "Untitled" draft
        $title = "Untitled Article " . date('Y-m-d H:i');
        $slug = $this->slugify($title) . '-' . uniqid();

        $data = [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => '',
            'content' => '',
            'status' => 'draft',
            'featured_image' => '',
            'category_id' => null
        ];

        $id = Post::create($_SESSION['user_id'], $data);

        if ($id) {
            // Initialize SEO (empty)
            \Nexus\Models\SeoMetadata::save('post', $id, ['meta_title' => '', 'meta_description' => '', 'noindex' => 0]);
            header("Location: " . TenantContext::getBasePath() . "/admin-legacy/blog/builder/$id");
            exit;
        } else {
            die("Failed to create draft");
        }
    }

    public function store()
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $title = $_POST['title'];
        $slug = $_POST['slug'] ?: $this->slugify($title);

        $data = [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $_POST['excerpt'],
            'content' => $_POST['content'],
            'status' => $_POST['status'],
            'featured_image' => $_POST['featured_image'] ?? '',
            'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null
        ];

        try {
            $id = Post::create($_SESSION['user_id'], $data);

            // Save SEO
            if ($id) {
                $seoData = [
                    'meta_title' => $_POST['meta_title'] ?? '',
                    'meta_description' => $_POST['meta_description'] ?? '',
                    'noindex' => isset($_POST['noindex'])
                ];
                \Nexus\Models\SeoMetadata::save('post', $id, $seoData);
            }

            header("Location: " . TenantContext::getBasePath() . "/admin-legacy/blog");
        } catch (\Exception $e) {
            echo "Error creating post: " . $e->getMessage();
        }
    }

    public function edit($id)
    {
        $this->checkAdmin();
        $post = Post::findById($id);
        $categories = \Nexus\Models\Category::all();

        if (!$post) die("Post not found");

        // Fetch SEO
        $seo = \Nexus\Models\SeoMetadata::get('post', $id);

        View::render('admin/blog/form', [
            'pageTitle' => 'Edit Article',
            'post' => $post,
            'categories' => $categories,
            'seo' => $seo
        ]);
    }

    public function update($id)
    {
        $this->checkAdmin();
        Csrf::verifyOrDie();

        $data = [
            'title' => $_POST['title'],
            'slug' => $_POST['slug'] ?: $this->slugify($_POST['title']),
            'excerpt' => $_POST['excerpt'],
            'content' => $_POST['content'],
            'status' => $_POST['status'],
            'featured_image' => $_POST['featured_image'] ?? '',
            'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null
        ];

        Post::update($id, $data);

        // Save SEO
        $seoData = [
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'noindex' => isset($_POST['noindex'])
        ];
        \Nexus\Models\SeoMetadata::save('post', $id, $seoData);

        header("Location: " . TenantContext::getBasePath() . "/admin-legacy/blog");
    }

    public function delete($id)
    {
        $this->checkAdmin();
        Post::delete($id);
        header("Location: " . TenantContext::getBasePath() . "/admin-legacy/blog");
    }

    public function builder($id)
    {
        $this->checkAdmin();
        $post = Post::findById($id);
        if (!$post) die("Post not found");

        // Load SEO for builder
        $seo = \Nexus\Models\SeoMetadata::get('post', $id);

        // Load Categories
        $categories = \Nexus\Models\Category::all();

        // Reuse the Page Builder view structure but modified for blog
        // or passing specific variables
        View::render('admin/blog/builder', [
            'post' => $post,
            'seo' => $seo,
            'categories' => $categories
        ]);
    }

    public function saveBuilder()
    {
        $this->checkAdmin();

        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'No ID']);
            exit;
        }

        $post = Post::findById($id);
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
            'excerpt' => $post['excerpt'], // Preserve
            'featured_image' => $post['featured_image'], // Preserve or update via settings if added
            'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : $post['category_id']
        ];

        // SEO
        $seoData = [
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'noindex' => isset($_POST['noindex'])
        ];

        try {
            Post::update($id, $data);
            \Nexus\Models\SeoMetadata::save('post', $id, $seoData);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // --- Helpers ---

    private function checkAdmin()
    {
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);
        if (!$isAdmin) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }

    private function slugify($text)
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = strtolower($text);
        return $text ?: 'n-a';
    }
}
